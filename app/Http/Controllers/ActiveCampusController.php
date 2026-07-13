<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ActiveCampusController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $data = $request->validate([
            'campus_id' => ['required', 'integer'],
        ]);

        $allowed = $user->campuses()->where('campuses.id', (int) $data['campus_id'])->exists();
        if (! $allowed) {
            return $this->safeRedirect($request)->with('error', 'No tienes acceso a ese campus.');
        }

        $request->session()->put('active_campus_id', (int) $data['campus_id']);

        return $this->safeRedirect($request)->with('info', 'Campus activo actualizado.');
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
