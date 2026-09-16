<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreDivisionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80', 'unique:divisions,name'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Ingresá el nombre de la división.',
            'name.max' => 'El nombre no puede superar 80 caracteres.',
            'name.unique' => 'Ya existe una división con ese nombre.',
            'sort_order.integer' => 'El orden debe ser un número entero.',
        ];
    }
}
