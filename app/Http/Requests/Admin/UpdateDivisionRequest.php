<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDivisionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:80', Rule::unique('divisions', 'name')->ignore($this->route('division'))],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'El nombre no puede superar 80 caracteres.',
            'name.unique' => 'Ya existe una división con ese nombre.',
            'sort_order.integer' => 'El orden debe ser un número entero.',
        ];
    }
}
