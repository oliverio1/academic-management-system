<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TutorAssignmentController extends Controller
{
    public function index()
    {
        $students = Student::query()
            ->with(['user', 'group', 'guardian'])
            ->join('users', 'users.id', '=', 'students.user_id')
            ->orderBy('users.name')
            ->select('students.*')
            ->get();

        $tutors = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['guardian', 'tutor']))
            ->orderBy('name')
            ->get();

        return view('coordination.tutor_assignments.index', [
            'students' => $students,
            'tutors' => $tutors,
        ]);
    }

    public function update(Request $request, Student $student)
    {
        $data = $request->validate([
            'guardian_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        if (! empty($data['guardian_user_id'])) {
            $isTutor = User::query()
                ->whereKey($data['guardian_user_id'])
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['guardian', 'tutor']))
                ->exists();

            abort_if(! $isTutor, 422, 'El usuario seleccionado no tiene rol de tutor.');
        }

        $student->update([
            'guardian_user_id' => $data['guardian_user_id'] ?? null,
        ]);

        return back()->with('info', 'Tutor asignado correctamente.');
    }

    public function bulkAssign(Request $request)
    {
        $data = $request->validate([
            'guardian_user_id' => ['required', 'integer', 'exists:users,id'],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['required', 'integer', 'exists:students,id'],
        ]);

        $isTutor = User::query()
            ->whereKey($data['guardian_user_id'])
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['guardian', 'tutor']))
            ->exists();

        abort_if(! $isTutor, 422, 'El usuario seleccionado no tiene rol de tutor.');

        $studentIds = collect($data['student_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        Student::query()
            ->whereIn('id', $studentIds)
            ->update(['guardian_user_id' => (int) $data['guardian_user_id']]);

        return back()->with('info', 'Tutor asignado a los alumnos seleccionados correctamente.');
    }
}
