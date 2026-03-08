<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\AttendanceJustificationService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AttendanceJustificationController extends Controller
{
    public function create()
    {
        $students = Student::orderBy('enrollment_number')->get();

        return view('attendance_justifications.create', compact('students'));
    }

    public function store(
        Request $request,
        AttendanceJustificationService $service
    ) {
        $data = $request->validate([
            'student_id' => 'required|exists:students,id',
            'from_date'  => 'required|date',
            'to_date'    => 'required|date|after_or_equal:from_date',
            'reason'     => 'required|string|max:255',
            'document'   => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:4096',
        ]);

        $path = null;
        if ($request->hasFile('document')) {
            $path = $request->file('document')
                ->store('attendance_justifications', 'public');
        }

        $service->justify(
            Student::findOrFail($data['student_id']),
            Carbon::parse($data['from_date']),
            Carbon::parse($data['to_date']),
            [
                'reason' => $data['reason'],
                'document_path' => $path,
            ],
            auth()->user()
        );

        return redirect()
            ->route('attendance_justifications.create')
            ->with('success', 'Justificante emitido y asistencias actualizadas.');
    }
}
