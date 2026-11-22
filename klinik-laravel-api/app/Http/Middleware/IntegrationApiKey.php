<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IntegrationApiKey
{
    public function handle(Request $request, Closure $next)
    {
        $expected = config('integrations.inbound_api_key');
        $provided = $request->header('X-API-Key', '');
        if (!$expected || !$provided || !hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Invalid integration API key.'], 401);
        }

        return $next($request);
    }
}
