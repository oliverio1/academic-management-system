<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\StudentFollowUpTeacher;

class StudentFollowUpResponseController extends Controller
{
    /**
     * Ver la respuesta de un profesor
     */
    public function show(StudentFollowUpTeacher $assignment)
    {
        // seguridad básica
        abort_if(! $assignment->response, 404);

        $assignment->load([
            'teacher.user',
            'followUp.student.user',
            'response',
        ]);

        return view(
            'admin.follow_ups.responses.show',
            compact('assignment')
        );
    }
}