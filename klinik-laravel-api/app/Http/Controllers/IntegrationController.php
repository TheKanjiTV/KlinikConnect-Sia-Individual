<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Patient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class IntegrationController extends Controller
{
    public function health()
    {
        return response()->json(['system' => 'KlinikConnect', 'status' => 'healthy']);
    }

    public function patients(Request $request)
    {
        $filters = $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:100',
            'updated_since' => 'sometimes|date',
        ]);
        $patients = Patient::select('id', 'user_id', 'name', 'email', 'gender', 'date_of_birth', 'created_at', 'updated_at')
            ->when($filters['updated_since'] ?? null, fn ($query, $date) => $query->where('updated_at', '>=', $date))
            ->orderBy('id')->paginate($filters['per_page'] ?? 25);

        return response()->json($patients);
    }

    public function appointments(Request $request)
    {
        $filters = $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:100',
            'updated_since' => 'sometimes|date',
            'date' => 'sometimes|date',
        ]);
        $appointments = Appointment::select(
            'id', 'user_id', 'doctor_id', 'date', 'day', 'start_time', 'end_time',
            'general_status', 'payment_status', 'created_at', 'updated_at'
        )->when($filters['updated_since'] ?? null, fn ($query, $date) => $query->where('updated_at', '>=', $date))
            ->when($filters['date'] ?? null, fn ($query, $date) => $query->where('date', $date))
            ->orderBy('id')->paginate($filters['per_page'] ?? 25);

        return response()->json($appointments);
    }

    public function webhook(Request $request)
    {
        $secret = config('integrations.verify_secret');
        $timestamp = $request->header('X-Webhook-Timestamp', '');
        $signature = $request->header('X-Webhook-Signature', '');
        if (!$secret || !ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300) {
            return response()->json(['message' => 'Missing or expired webhook signature.'], 401);
        }

        $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);
        if (!hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        $payload = $request->validate([
            'event_id' => 'required|string|max:100',
            'event_type' => 'required|string|max:100',
            'source' => 'required|string|max:100',
            'data' => 'required|array',
        ]);
        $hash = hash('sha256', $request->getContent());
        $inserted = DB::table('integration_events')->insertOrIgnore([
            'event_id' => $payload['event_id'],
            'event_type' => $payload['event_type'],
            'source' => $payload['source'],
            'payload_hash' => $hash,
            'encrypted_payload' => Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR)),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $stored = DB::table('integration_events')->where('event_id', $payload['event_id'])->first();
        if (!$stored || !hash_equals($stored->payload_hash, $hash)) {
            return response()->json(['message' => 'Event ID already belongs to a different payload.'], 409);
        }

        return response()->json([
            'status' => $inserted ? 'received' : 'duplicate',
            'event_id' => $payload['event_id'],
        ], $inserted ? 202 : 200);
    }

    public function status()
    {
        return response()->json([
            'billing' => [
                'base_url' => config('integrations.billing.base_url'),
                'configured' => (bool) config('integrations.billing.token'),
                'required' => 'Billing-issued Sanctum bearer token',
            ],
            'emr' => [
                'base_url' => config('integrations.emr.base_url'),
                'configured' => (bool) config('integrations.emr.base_url') && (bool) config('integrations.emr.token'),
            ],
            'laboratory_pharmacy' => [
                'base_url' => config('integrations.laboratory_pharmacy.base_url'),
                'configured' => false,
                'required' => 'Correct Laboratory and Pharmacy endpoints, deployed URL, and credentials',
            ],
            'webhook' => ['mode' => 'signed event inbox', 'applies_business_updates' => false],
        ]);
    }

    public function billing(Request $request, string $resource)
    {
        abort_unless(in_array($resource, ['customers', 'orders', 'invoices'], true), 404);
        $token = config('integrations.billing.token');
        if (!$token) {
            return response()->json(['message' => 'Billing API token is not configured.'], 503);
        }
        $filters = $request->validate([
            'page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|min:1|max:100',
            'status' => 'sometimes|string|max:50', 'customer_id' => 'sometimes|integer|min:1',
        ]);

        try {
            $response = Http::acceptJson()->withToken($token)->connectTimeout(5)->timeout(15)
                ->withOptions(['allow_redirects' => false])
                ->get(rtrim(config('integrations.billing.base_url'), '/').'/sales/'.$resource, $filters);
        } catch (ConnectionException $e) {
            return response()->json(['message' => 'Billing server is unavailable.'], 502);
        }

        if (!$response->successful() || !is_array($response->json())) {
            return response()->json([
                'message' => 'Billing server rejected the request.', 'upstream_status' => $response->status(),
            ], 502);
        }

        return response()->json($response->json());
    }
}
