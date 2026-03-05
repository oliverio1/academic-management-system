<?php

namespace App\Http\Controllers;

use App\Models\Teacher;
use App\Models\Subject;
use Illuminate\Http\Request;

class TeacherSubjectController extends Controller
{
    public function edit(Subject $subject) {
        $teachers = Teacher::with('user')->get();
        $assigned = $subject->teachers()->pluck('teachers.id')->toArray();

        return view('subjects.assign-teachers', compact('subject','teachers','assigned'));
    }

    public function update(Request $request, Subject $subject) {
        $teacherIds = $request->input('teachers', []);
        $subject->teachers()->sync($teacherIds);
        return redirect()->route('subjects.index')->with('info', 'Profesores asignados correctamente a la materia.');
    }

    public function editTeacher(Teacher $teacher) {
        $subjects = Subject::where('is_active', true)->get();
        $assigned = $teacher->subjects()->pluck('subjects.id')->toArray();

        return view('teachers.assign-subjects', compact('teacher','subjects','assigned'));
    }

    public function updateTeacher(Request $request, Teacher $teacher) {
        $subjectIds = $request->input('subjects', []);
        $teacher->subjects()->sync($subjectIds);
        return redirect()->route('teachers.index')->with('info', 'Materias asignadas correctamente al profesor.');
    }
}