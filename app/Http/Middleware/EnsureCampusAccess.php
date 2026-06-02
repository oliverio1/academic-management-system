<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCampusAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        if ($user->hasRole('admin')) {
            return $next($request);
        }

        $campusIds = $user->campuses()->pluck('campuses.id')->map(fn ($id) => (int) $id)->values();
        if ($campusIds->isEmpty()) {
            abort(403, 'No tienes campus asignados. Contacta al administrador.');
        }

        $activeCampusId = (int) $request->session()->get('active_campus_id', 0);
        if ($activeCampusId <= 0 || ! $campusIds->contains($activeCampusId)) {
            $defaultCampusId = (int) ($user->default_campus_id ?? 0);
            if ($defaultCampusId > 0 && $campusIds->contains($defaultCampusId)) {
                $activeCampusId = $defaultCampusId;
            } else {
                $activeCampusId = (int) $campusIds->first();
            }

            $request->session()->put('active_campus_id', $activeCampusId);
        }

        app()->instance('activeCampusId', $activeCampusId);

        return $next($request);
    }
}

