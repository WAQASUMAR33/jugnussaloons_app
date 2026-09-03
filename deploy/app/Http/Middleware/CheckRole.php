<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        $user = $request->user();

        // 1. Admin always passes
        if ($user->hasRole('admin')) {
            return $next($request);
        }

        // 2. Direct role matching
        if ($user->hasAnyRole($roles)) {
            return $next($request);
        }

        // 3. If checking for manager/admin and user has direct permissions, allow entry
        if ((in_array('manager', $roles) || in_array('admin', $roles)) && ($user->permissions()->exists() || $user->roles()->exists())) {
            return $next($request);
        }

        abort(403, 'Unauthorized access: You do not have permission to view this page.');
    }
}
