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
        $modalityParam = $this->route('modality');
        $modalityId = is_object($modalityParam) ? $modalityParam->id : $modalityParam;

        return [
            'name' => 'required|string|max:255|unique:modalities,name,' . $modalityId,
        ];
    }
}
