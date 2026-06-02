<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GroupRequest extends FormRequest
{
    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        $groupId = $this->route('group')?->id;
        return [
            'level_id' => 'required|exists:levels,id',
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('groups', 'name')
                    ->ignore($groupId)
                    ->where(fn ($q) => $q
                        ->where('level_id', $this->input('level_id'))),
            ],
            'capacity' => 'required|integer|min:1|max:60',
        ];
    }
}
