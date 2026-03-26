<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $userId,
            'password' => $userId ? 'nullable|min:8' : 'required|min:8',
            'role' => 'required|exists:roles,name',
            'student.group_id' => 'nullable|required_if:role,student|exists:groups,id',
            'student.enrollment_number' => 'nullable|required_if:role,student|string|max:255',
            'teacher.phone' => 'nullable|string|max:20',
            'coordinator.area' => 'nullable|string|max:200',
        ];
    }
}
