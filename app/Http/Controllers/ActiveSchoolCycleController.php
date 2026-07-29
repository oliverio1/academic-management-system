<?php

namespace App\Http\Controllers;

use App\Services\CurrentSchoolCycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ActiveSchoolCycleController extends Controller
{
    public function update(Request $request, CurrentSchoolCycle $cycles): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $data = $request->validate([
            'school_cycle_id' => ['required', 'integer'],
        ]);

        $cycle = $cycles->set((int) $data['school_cycle_id'], $user);
        if (! $cycle) {
            return $this->safeRedirect($request)->with('error', 'No tienes acceso a ese ciclo.');
        }

        return $this->safeRedirect($request)->with('info', 'Ciclo de trabajo actualizado.');
    }

    private function safeRedirect(Request $request): RedirectResponse
    {
        $previous = url()->previous(route('dashboard'));
        $current = $request->fullUrl();

        if ($previous !== $current && $this->supportsGet($previous)) {
            return redirect()->to($previous);
        }

        return redirect()->route('dashboard');
    }

    private function supportsGet(string $url): bool
    {
        $baseUrl = rtrim(config('app.url') ?: request()->getSchemeAndHttpHost(), '/');
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $query = parse_url($url, PHP_URL_QUERY);
        $uri = $path . ($query ? '?' . $query : '');
        $synthetic = Request::create($baseUrl . $uri, 'GET');

        try {
            Route::getRoutes()->match($synthetic);
            return true;
        } catch (MethodNotAllowedHttpException|NotFoundHttpException) {
            return false;
        }
    }
}
