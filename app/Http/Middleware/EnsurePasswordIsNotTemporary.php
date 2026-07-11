<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsNotTemporary
{
    private const TEMPORARY_PASSWORD = '123123123';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $this->isAllowedRoute($request)) {
            return $next($request);
        }

        if (Hash::check(self::TEMPORARY_PASSWORD, $user->password)) {
            return redirect()->route('temporary-password.edit');
        }

        return $next($request);
    }

    private function isAllowedRoute(Request $request): bool
    {
        foreach (['temporary-password.*', 'logout'] as $routeName) {
            if ($request->routeIs($routeName)) {
                return true;
            }
        }

        return false;
    }
}
