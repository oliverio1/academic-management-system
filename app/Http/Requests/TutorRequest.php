<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TutorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('tutor')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => $userId
                ? ['required', 'email', 'unique:users,email,' . $userId]
                : ['required', 'email', 'unique:users,email'],
        ];
    }
}

