<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CampusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $campusId = $this->route('campus')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('campuses', 'name')->ignore($campusId),
            ],
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('campuses', 'code')->ignore($campusId),
            ],
        ];
    }
}

