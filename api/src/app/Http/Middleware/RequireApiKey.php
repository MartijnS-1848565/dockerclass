<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('api.key');

        // hash_equals compares in constant time, so the key can't be guessed by timing responses.
        if (! $expected || ! hash_equals($expected, (string) $request->header('X-API-KEY'))) {
            return response()->json(['message' => 'Missing or invalid X-API-KEY header.'], 401);
        }

        return $next($request);
    }
}
