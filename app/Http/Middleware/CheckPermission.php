<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$permissions
     */
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        $user = $request->user();

        // 1. Admin always has full access
        if ($user->hasRole('admin')) {
            return $next($request);
        }

        // 2. Check if user has any of the required permissions
        if (!empty($permissions)) {
            foreach ($permissions as $permission) {
                // Split comma-separated permissions if passed like permission:perm1,perm2
                $subPerms = explode(',', $permission);
                foreach ($subPerms as $perm) {
                    $trimmed = trim($perm);
                    if (!empty($trimmed) && $user->hasPermission($trimmed)) {
                        return $next($request);
                    }
                }
            }
        }

        abort(403, 'Unauthorized access: You do not have permission to access this page.');
    }
}
