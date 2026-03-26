<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AcademicPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $periodId = $this->route('academic_period')?->id ?: $this->route('period')?->id;

        return [
            'name' => 'required|string|max:255',
            'modality_id' => 'required|exists:modalities,id',
            'code' => 'required|string|max:50|unique:academic_periods,code,' . $periodId,
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ];
    }
}
