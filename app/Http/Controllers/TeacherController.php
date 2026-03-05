<?php

namespace App\Http\Controllers;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\TeacherRequest;

class TeacherController extends Controller
{
    public function index() {
        $teachers = Teacher::with('user')->get();
        return view('teachers.index', compact('teachers'));
    }

    public function create() {
        return view('teachers.create');
    }

    public function store(TeacherRequest $request) {
        DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make('password'),
            ]);
            $user->assignRole('teacher');
            Teacher::create([
                'user_id' => $user->id,
                'phone' => $request->phone,
                'address' => $request->address,
                'is_active' => true,
            ]);
        });
        return redirect()->route('teachers.index')->with('info', 'Profesor creado correctamente');
    }

    public function edit(Teacher $teacher) {
        return view('teachers.edit', compact('teacher'));
    }

    public function update(TeacherRequest $request, Teacher $teacher) {
        DB::transaction(function () use ($request, $teacher) {
            $teacher->user->update([
                'name' => $request->name,
            ]);
            $teacher->update([
                'phone' => $request->phone,
                'address' => $request->address,
                'is_active' => $request->has('is_active'),
            ]);
        });
        return redirect()->route('teachers.index')->with('info', 'Profesor actualizado correctamente');
    }

    public function deactivate(Teacher $teacher) {
        $this->authorize('deactivate', $teacher);
        if (! $teacher->is_active) {
            return back()->with('info', 'El profesor ya está dado de baja');
        }
        $teacher->update(['is_active' => false]);
        return back()->with('info', 'Profesor dado de baja correctamente');
    }

    public function activate(Teacher $teacher) {
        $this->authorize('activate', $teacher);
        if ($teacher->is_active) {
            return back()->with('info', 'El profesor ya está activo');
        }
        $teacher->update(['is_active' => true]);
        return back()->with('info', 'Profesor activado correctamente');
    }

    public function show(Teacher $teacher) {
        $teacher->load([
            'teachingAssignments.group',
            'teachingAssignments.subject',
            'teachingAssignments.schedules'
        ]);

        $hours = [
            '07:00', '07:50', '08:40', '09:30',
            '10:00', '10:50', '11:40',
            '12:10', '13:00', '13:50'
        ];
    
        $days = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes'];

        $grid = [];
        foreach ($hours as $hour) {
            foreach ($days as $day) {
                $grid[$hour][$day] = null;
            }
        }
    
        // Llenar horario
        foreach ($teacher->teachingAssignments as $assignment) {
            foreach ($assignment->schedules as $schedule) {
                $hourKey = \Carbon\Carbon::createFromFormat('H:i:s', $schedule->start_time)->format('H:i');
                $grid[$hourKey][$schedule->day_of_week] = $assignment;
            }
        }
    
        return view('teachers.show', compact(
            'teacher',
            'hours',
            'days',
            'grid'
        ));
    }
}
