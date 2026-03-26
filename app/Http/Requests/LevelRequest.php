<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $levelId = $this->route('level')?->id;

        return [
            'modality_id' => 'required|exists:modalities,id',
            'name' => 'required|string|max:255|unique:levels,name,' . $levelId,
        ];
    }
}
