<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ModalityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $modalityId = $this->route('modality')?->id;

        return [
            'name' => 'required|string|max:255|unique:modalities,name,' . $modalityId,
        ];
    }
}
