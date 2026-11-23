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
        $peer = $request->attributes->get('integration_peer');
        $secret = $peer ? config('integrations.'.$peer.'.signing_secret') : config('integrations.verify_secret');
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
        $storedEventId = $peer ? hash('sha256', $peer.'|'.$payload['event_id']) : $payload['event_id'];
        $inserted = DB::table('integration_events')->insertOrIgnore([
            'event_id' => $storedEventId,
            'event_type' => $payload['event_type'],
            'source' => $peer ?? $payload['source'],
            'payload_hash' => $hash,
            'encrypted_payload' => Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR)),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $stored = DB::table('integration_events')->where('event_id', $storedEventId)->first();
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
                'name' => config('integrations.billing.name'),
                'base_url' => config('integrations.billing.base_url'),
                'configured' => (bool) config('integrations.billing.token') || (bool) config('integrations.billing.inbound_api_key'),
                'authentication' => config('integrations.billing.token') ? 'sanctum' : 'api_key',
                'connection_check' => '/api/v1/integrations/billing/connection',
                'required' => 'Billing sales endpoints must accept the supplied API key or a Billing-issued Sanctum token',
            ],
            'emr' => [
                'name' => config('integrations.emr.name'),
                'base_url' => config('integrations.emr.base_url'),
                'configured' => false,
                'inbound_key_configured' => (bool) config('integrations.emr.inbound_api_key'),
                'required' => 'Doctor and Medical Records endpoints and its inbound API key',
            ],
            'laboratory_pharmacy' => [
                'name' => config('integrations.laboratory_pharmacy.name'),
                'base_url' => config('integrations.laboratory_pharmacy.base_url'),
                'configured' => false,
                'credentials_configured' => (bool) config('integrations.laboratory_pharmacy.inbound_api_key'),
                'required' => 'Laboratory and Pharmacy endpoint contract',
            ],
            'webhook' => ['mode' => 'signed event inbox', 'applies_business_updates' => false],
        ]);
    }

    public function billing(Request $request, string $resource)
    {
        abort_unless(in_array($resource, ['customers', 'orders', 'invoices'], true), 404);
        $token = config('integrations.billing.token');
        $key = config('integrations.billing.inbound_api_key');
        if (!$token && !$key) {
            return response()->json(['message' => 'Billing API credentials are not configured.'], 503);
        }
        $filters = $request->validate([
            'page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|min:1|max:100',
            'status' => 'sometimes|string|max:50', 'customer_id' => 'sometimes|integer|min:1',
            'search' => 'sometimes|string|max:255',
        ]);

        try {
            $response = $this->billingRequest()->get('/sales/'.$resource, $filters);
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

    public function billingConnection()
    {
        if (!config('integrations.billing.token') && !config('integrations.billing.inbound_api_key')) {
            return response()->json(['connected' => false, 'reason' => 'missing_credentials'], 503);
        }
        try {
            $response = $this->billingRequest()->get('/sales/invoices', ['per_page' => 1]);
        } catch (ConnectionException $e) {
            return response()->json(['connected' => false, 'reason' => 'unreachable'], 502);
        }

        $connected = $response->successful() && is_array($response->json());
        return response()->json([
            'connected' => $connected,
            'upstream_status' => $response->status(),
            'reason' => $connected ? 'authenticated_sales_access' : 'upstream_rejected_request',
        ]);
    }

    private function billingRequest(): \Illuminate\Http\Client\PendingRequest
    {
        $request = Http::acceptJson()->baseUrl(rtrim(config('integrations.billing.base_url'), '/'))
            ->connectTimeout(5)->timeout(15)->withOptions(['allow_redirects' => false]);
        if ($token = config('integrations.billing.token')) {
            $request = $request->withToken($token);
        }
        if ($key = config('integrations.billing.inbound_api_key')) {
            $request = $request->withHeaders(['X-API-Key' => $key]);
        }

        return $request;
    }
}
