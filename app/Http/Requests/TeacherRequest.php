<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TeacherRequest extends FormRequest
{
    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        $teacherId = $this->route('teacher')?->id;
        return [
            'name' => 'required|string|max:255',
            'email' => $teacherId
                ? 'required|email|unique:users,email,' . $this->route('teacher')->user_id
                : 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
        ];
    }
}
