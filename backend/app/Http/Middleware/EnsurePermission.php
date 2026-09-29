<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$flags): Response
    {
        $user = $request->user();

        if ($user->hasAdminAccess()) {
            return $next($request);
        }

        foreach ($flags as $flag) {
            if ($user->hasPermissionFlag($flag)) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => 'No tenés permiso para realizar esta acción'
        ], 403);
    }
}