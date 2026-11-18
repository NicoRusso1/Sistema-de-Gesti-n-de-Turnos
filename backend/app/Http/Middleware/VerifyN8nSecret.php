<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege los endpoints que llama n8n: tiene que mandar el header X-N8N-Secret
 * con el mismo valor que N8N_SECRET.
 */
class VerifyN8nSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.n8n.secret');

        if ($secret === '' || !hash_equals($secret, (string) $request->header('X-N8N-Secret'))) {
            return response()->json(['message' => 'No autorizado'], 401);
        }

        return $next($request);
    }
}
