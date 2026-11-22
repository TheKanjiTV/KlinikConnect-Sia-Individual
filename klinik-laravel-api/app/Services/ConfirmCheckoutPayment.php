<?php

namespace App\Services;

use App\Mail\AppointmentReservasionSuccess;
use App\Mail\OrderPaymentSuccess;
use App\Models\Appointment;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Stripe\StripeClient;

class ConfirmCheckoutPayment
{
    public function handle(Request $request, string $type)
    {
        $validated = $request->validate(['sessionId' => 'required|string|max:255']);
        $model = $type === 'appointment' ? Appointment::class : Order::class;
        $record = $model::where('session_id', $validated['sessionId'])
            ->where('user_id', $request->user()->id)->firstOrFail();
        if ($type === 'appointment' && $record->general_status === 'canceled') {
            return response()->json(['message' => 'This appointment has been canceled.'], 409);
        }

        $secret = config('services.stripe.secret');
        if (!$secret) {
            return response()->json(['message' => 'Stripe payment is not configured.'], 503);
        }

        try {
            $stripe = app(StripeClient::class);
            $session = $stripe->checkout->sessions->retrieve($validated['sessionId']);
        } catch (\Throwable $e) {
            Log::warning('Stripe session lookup failed.', ['exception' => get_class($e)]);
            return response()->json(['message' => 'Unable to verify payment with Stripe.'], 502);
        }

        if ($session->payment_status !== 'paid' || $session->status !== 'complete') {
            return response()->json(['message' => 'Payment has not been completed.'], 409);
        }

        $changed = DB::transaction(function () use ($record, $type) {
            $record = $record->newQuery()->lockForUpdate()->findOrFail($record->id);
            abort_if($type === 'appointment' && $record->general_status === 'canceled', 409, 'This appointment has been canceled.');
            $field = $type === 'appointment' ? 'payment_status' : 'status';
            if ($record->$field === 'paid') {
                return false;
            }

            $record->$field = 'paid';
            if ($type === 'appointment') {
                $record->general_status = 'scheduled';
            }
            $record->save();
            return true;
        });

        $record->refresh();
        Cache::forget('count_orders');
        Cache::forget('count_appointments');
        Cache::forget('count_orders_patient_'.$record->user_id);
        Cache::forget('count_patient_'.$record->user_id.'_appointments');
        if ($type === 'appointment') {
            Cache::forget('count_doctor_'.$record->doctor?->user_id.'_appointments');
        }

        if ($changed) {
            try {
                $mail = $type === 'appointment'
                    ? new AppointmentReservasionSuccess($record)
                    : new OrderPaymentSuccess($record);
                Mail::to($request->user()->email)->send($mail);
            } catch (\Throwable $e) {
                Log::warning('Payment recorded but receipt email failed.', [
                    'type' => $type, 'id' => $record->id, 'exception' => get_class($e),
                ]);
            }
        }

        return response()->json([
            'status' => 'success', 'message' => 'Payment successful.', $type => $record,
        ]);
    }
}
