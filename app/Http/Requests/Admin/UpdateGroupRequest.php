<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGroupRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:80', Rule::unique('groups', 'name')->where('division_id', $this->route('division')->id)->ignore($this->route('group'))],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'El nombre no puede superar 80 caracteres.',
            'name.unique' => 'Ya existe un grupo con ese nombre en esta división.',
            'sort_order.integer' => 'El orden debe ser un número entero.',
        ];
    }
}
