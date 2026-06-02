<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('levels', 'name')
                    ->ignore($levelId)
                    ->where(fn ($q) => $q->where('modality_id', $this->input('modality_id'))),
            ],
        ];
    }
}
