<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Eager-loads the authenticated user's roles and permissions in a single
 * batch query at the start of every web request. This prevents N+1 query
 * storms caused by repeated hasRole() / hasPermission() calls in Blade
 * sidebar menus, Gate checks, and controller authorization.
 *
 * Without this middleware, each hasRole/hasPermission call in the layout
 * triggers separate DB queries, quickly exhausting Hostinger's
 * max_connections_per_hour limit of 500.
 */
class EagerLoadUserRelations
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            // Load roles (with their permissions) and direct user permissions
            // in exactly 3 queries total, then cache the slug arrays in memory.
            // All subsequent hasRole() / hasPermission() calls this request
            // will use the cached arrays — zero additional DB queries.
            $user->eagerLoadForAuthChecks();
        }

        return $next($request);
    }
}
