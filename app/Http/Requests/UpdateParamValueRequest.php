<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateParamValueRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // "present" y no "required": false, 0 y "" son valores legítimos.
            'value' => ['present'],
            'scope' => ['sometimes', 'string', Rule::in(['user', 'base'])],
        ];
    }

    public function messages(): array
    {
        return [
            'value.present' => 'Enviá el campo "value" con el nuevo valor del parámetro.',
            'scope.in' => 'El campo "scope" debe ser "user" (tu override) o "base" (valor para todos).',
        ];
    }

    public function scope(): string
    {
        return $this->input('scope', 'user');
    }
}
