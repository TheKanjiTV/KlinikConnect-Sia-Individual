<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ResolveApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken() && !Auth::check()) {
            $user = Auth::guard('sanctum')->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            Auth::setUser($user);
        }

        return $next($request);
    }
}
