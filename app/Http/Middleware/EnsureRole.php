<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

class EnsureRole
{
    /**
     * @param  Closure(Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if (! in_array($user->role, $roles, true)) {
            throw new AuthorizationException('Forbidden');
        }

        return $next($request);
    }
}

