<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Ingresá el nombre del usuario.',
            'name.max' => 'El nombre no puede superar 120 caracteres.',
            'email.required' => 'Ingresá el correo electrónico.',
            'email.email' => 'El correo electrónico no tiene un formato válido.',
            'email.unique' => 'Ya existe un usuario con ese correo.',
            'password.required' => 'Definí una contraseña inicial.',
            'password.min' => 'La contraseña debe tener al menos :min caracteres.',
            'role.required' => 'Elegí un rol.',
            'role.enum' => 'El rol debe ser "super_admin" o "user".',
        ];
    }
}
