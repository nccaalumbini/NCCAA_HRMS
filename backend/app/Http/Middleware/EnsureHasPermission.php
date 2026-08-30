<?php

namespace App\Http\Middleware;

use App\Models\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasPermission
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        $isSuperAdmin = $user !== null && $user->hasAnyRole([Role::SUPER_ADMIN]);

        if ($user === null || (! $isSuperAdmin && ! $user->hasAnyPermission($permissions))) {
            abort(403, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}
