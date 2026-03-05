<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StudentRequest extends FormRequest
{
    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        $studentId = $this->route('student')?->id;
        return [
            'email' => $studentId
                ? 'required|email|unique:users,email,' . $this->route('student')->user_id
                : 'required|email|unique:users,email',
            'group_id' => 'required|exists:groups,id',
            'enrollment_number' => 'required|unique:students,enrollment_number,' . $studentId,
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
        ];
    }
}
