<?php

namespace App\Http\Controllers;

use App\Http\Requests\TutorRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class TutorController extends Controller
{
    public function index()
    {
        $tutors = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['guardian', 'tutor']))
            ->orderBy('name')
            ->get();

        return view('tutors.index', compact('tutors'));
    }

    public function create()
    {
        return view('tutors.create');
    }

    public function store(TutorRequest $request)
    {
        $tutor = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make('123123123'),
        ]);

        $tutor->assignRole('guardian');
        $tutor->assignRole('tutor');

        return redirect()
            ->route('tutors.index')
            ->with('info', 'Tutor creado correctamente. Contrasena inicial: 123123123');
    }

    public function edit(User $tutor)
    {
        abort_unless(
            $tutor->hasRole('guardian') || $tutor->hasRole('tutor'),
            404
        );

        return view('tutors.edit', compact('tutor'));
    }

    public function update(TutorRequest $request, User $tutor)
    {
        abort_unless(
            $tutor->hasRole('guardian') || $tutor->hasRole('tutor'),
            404
        );

        $tutor->update([
            'name' => $request->name,
        ]);

        return redirect()
            ->route('tutors.index')
            ->with('info', 'Tutor actualizado correctamente.');
    }
}

