<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdminOrOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! ($user->isSuperAdmin() || $user->isOwner())) {
            return response()->json([
                'message' => 'Solo el Super Administrador o un Administrador Propietario puede realizar esta acción'
            ], 403);
        }

        return $next($request);
    }
}
