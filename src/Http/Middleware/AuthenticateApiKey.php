<?php

namespace RadThemes\RadpackCrm\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use RadThemes\RadpackCrm\Models\ApiKey;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates REST API requests with "Authorization: Bearer <key>" (or an "X-Api-Key" header).
 */
class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        $key = ApiKey::findByPlainKey((string) ($request->bearerToken() ?? $request->header('X-Api-Key')));

        if (! $key) {
            return response()->json(['message' => 'Invalid or missing API key.'], 401);
        }

        if (! $key->can_write && ! $request->isMethodSafe()) {
            return response()->json(['message' => 'This API key is read-only.'], 403);
        }

        if (! $key->last_used_at || $key->last_used_at->lt(now()->subMinute())) {
            $key->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        $request->attributes->set('radpack_api_key', $key);

        return $next($request);
    }
}
