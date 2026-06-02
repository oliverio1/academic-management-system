<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubjectRequest extends FormRequest
{
    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        $subjectId = $this->route('subject')?->id;
        return [
            'level_id' => 'required|exists:levels,id',
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('subjects', 'name')
                    ->ignore($subjectId)
                    ->where(fn ($q) => $q->where('level_id', $this->input('level_id'))),
            ],
            'hours_per_week' => 'required|integer|min:1|max:10',
            'type' => 'required|string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'level_id.required' => 'Debes seleccionar un nivel.',
            'level_id.exists' => 'El nivel seleccionado no es válido.',
            'name.required' => 'El nombre de la materia es obligatorio.',
            'name.max' => 'El nombre de la materia no puede exceder 150 caracteres.',
            'name.unique' => 'Ya existe una materia con ese nombre en el nivel seleccionado.',
            'hours_per_week.required' => 'Debes indicar las horas por semana.',
            'hours_per_week.integer' => 'Las horas por semana deben ser un número entero.',
            'hours_per_week.min' => 'Las horas por semana deben ser al menos 1.',
            'hours_per_week.max' => 'Las horas por semana no pueden ser mayores a 10.',
            'type.required' => 'Debes seleccionar el tipo de materia.',
        ];
    }
}
