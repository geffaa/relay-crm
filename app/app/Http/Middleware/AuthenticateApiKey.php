<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Authenticates /api/v1 requests with "Authorization: Bearer rly_…" and binds the key's company as tenant. */
class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $key = $token ? ApiKey::with('company')->where('key_hash', hash('sha256', $token))->first() : null;

        if (! $key || ! $key->company) {
            return response()->json(['error' => 'unauthenticated', 'message' => 'Missing or invalid API key.'], 401);
        }

        $key->forceFill(['last_used_at' => now()])->save();
        app()->instance('relay.company', $key->company);
        $request->attributes->set('api_key', $key);

        return $next($request);
    }
}
