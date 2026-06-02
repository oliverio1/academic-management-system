<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
            return back()->with('error', 'No tienes acceso a ese campus.');
        }

        $request->session()->put('active_campus_id', (int) $data['campus_id']);

        return back()->with('info', 'Campus activo actualizado.');
    }
}

