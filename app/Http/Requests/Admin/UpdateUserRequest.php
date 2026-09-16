<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Http\Requests\Admin\Concerns\ValidatesMemberships;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    use ValidatesMemberships;

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => ['sometimes', 'nullable', 'string', Password::min(8)],
            'role' => ['sometimes', Rule::enum(UserRole::class)],
            'is_active' => ['sometimes', 'boolean'],
        ] + $this->membershipRules();
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            // Sin division_ids en el cuerpo, valen las divisiones actuales del usuario.
            $effective = $this->has('division_ids')
                ? (array) $this->input('division_ids', [])
                : $this->route('user')->divisions()->pluck('divisions.id')->all();
            $this->assertGroupsBelongToDivisions($v, $effective);
        });
    }

    public function messages(): array
    {
        return [
            'name.max' => 'El nombre no puede superar 120 caracteres.',
            'email.email' => 'El correo electrónico no tiene un formato válido.',
            'email.unique' => 'Ya existe otro usuario con ese correo.',
            'password.min' => 'La contraseña debe tener al menos :min caracteres.',
            'role.enum' => 'El rol debe ser "super_admin" o "user".',
        ] + $this->membershipMessages();
    }
}
