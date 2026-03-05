<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TeachingAssignment;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

class TeamController extends Controller
{
    public function assign(Request $request) {
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'team_id' => 'required|exists:teams,id',
        ]);

        // Quitar de cualquier equipo previo
        DB::table('team_student')
            ->where('student_id', $request->student_id)
            ->delete();

        // Asignar al nuevo equipo
        DB::table('team_student')->insert([
            'student_id' => $request->student_id,
            'team_id' => $request->team_id,
        ]);

        return back();
    }

    public function store(Request $request) {
        $request->validate([
            'name' => 'required|string|max:100',
            'teaching_assignment_id' => 'required|exists:teaching_assignments,id',
        ]);

        Team::create([
            'name' => $request->name,
            'teaching_assignment_id' => $request->teaching_assignment_id,
        ]);

        return back()->with('success', 'Equipo creado');
    }
}