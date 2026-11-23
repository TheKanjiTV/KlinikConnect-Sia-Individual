<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class SendIntegrationWebhook
{
    public function send(array $payload, ?string $peer = null)
    {
        $url = config('integrations.outbound_webhook_url');
        $key = config('integrations.outbound_api_key');
        $secret = config('integrations.signing_secret');
        if ($peer !== null) {
            if (!in_array($peer, ['billing', 'emr', 'laboratory_pharmacy'], true)) {
                throw new RuntimeException('Unknown integration recipient.');
            }
            $url = config('integrations.'.$peer.'.webhook_url');
            $key = config('integrations.'.$peer.'.inbound_api_key');
            $secret = config('integrations.'.$peer.'.verify_secret');
        }
        if (!$url || !$key || !$secret) {
            throw new RuntimeException('Outbound webhook URL, API key, and signing secret must be configured.');
        }

        validator($payload, [
            'event_id' => 'required|string|max:100', 'event_type' => 'required|string|max:100',
            'source' => 'required|string|max:100', 'data' => 'required|array',
        ])->validate();
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $timestamp = (string) time();

        // A failed request is returned to the caller. Retry using the same event_id.
        return Http::acceptJson()->connectTimeout(5)->timeout(15)
            ->withOptions(['allow_redirects' => false])->withHeaders([
                'X-API-Key' => $key,
                'X-Webhook-Timestamp' => $timestamp,
                'X-Webhook-Signature' => 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret),
                'Idempotency-Key' => $payload['event_id'],
            ])->withBody($body, 'application/json')->post($url)->throw();
    }
}
