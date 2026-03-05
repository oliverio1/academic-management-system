<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubjectRequest extends FormRequest
{
    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        $subjectId = $this->route('subject')?->id;
        return [
            'level_id' => 'required|exists:levels,id',
            'name' => 'required|string|max:150|unique:subjects,name,' . $subjectId,
            'hours_per_week' => 'required|integer|min:1|max:10',
            'type' => 'required|string|max:50',
        ];
    }
}
