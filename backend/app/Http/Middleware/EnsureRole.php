<?php

namespace App\Http\Middleware;

use App\Enums\RoleName;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $userRole = $request->user()?->role?->name;

        // El SuperAdmin hereda todo lo que puede hacer un Administrator
        $allowed = in_array($userRole, $roles, true)
            || ($userRole === RoleName::SuperAdmin->value
                && in_array(RoleName::Administrator->value, $roles, true));

        if (! $allowed) {
            abort(403);
        }

        return $next($request);
    }
}
