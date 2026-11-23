<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IntegrationApiKey
{
    public function handle(Request $request, Closure $next)
    {
        $provided = $request->header('X-API-Key', '');
        if (!$provided) {
            return response()->json(['message' => 'Invalid integration API key.'], 401);
        }

        $expected = config('integrations.inbound_api_key');
        if ($expected && hash_equals($expected, $provided)) {
            return $next($request);
        }

        foreach (['billing', 'emr', 'laboratory_pharmacy'] as $peer) {
            $expected = config('integrations.'.$peer.'.outbound_api_key');
            if ($expected && hash_equals($expected, $provided)) {
                $request->attributes->set('integration_peer', $peer);
                return $next($request);
            }
        }

        return response()->json(['message' => 'Invalid integration API key.'], 401);
    }
}
