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

        // 3. For manager prefix, allow any salon staff role (manager, accountant, sales-person, receptionist, staff) or user with assigned permissions
        if (in_array('manager', $roles) && ($user->hasAnyRole(['manager', 'accountant', 'sales-person', 'receptionist', 'staff']) || ($user->relationLoaded('permissions') ? $user->permissions->isNotEmpty() : $user->permissions()->exists()))) {
            return $next($request);
        }

        abort(403, 'Unauthorized access: You do not have the required role to view this page.');
    }
}
