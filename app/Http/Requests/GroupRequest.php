<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GroupRequest extends FormRequest
{
    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        $groupId = $this->route('group')?->id;
        return [
            'level_id' => 'required|exists:levels,id',
            'name' => 'required|string|max:100|unique:groups,name,' . $groupId,
            'capacity' => 'required|integer|min:1|max:60',
        ];
    }
}
