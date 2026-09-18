<?php

namespace App\Http\Requests\Admin;

use App\Support\DashboardIcons;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDashboardRequest extends FormRequest
{
    public function rules(): array
    {
        $max = (int) config('dashboards.max_html_bytes');

        return [
            'html' => ['required', 'string', "max:{$max}"],
            'is_published' => ['sometimes', 'boolean'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'icon' => ['sometimes', 'nullable', 'string', Rule::in(DashboardIcons::KEYS)],
            'visible_to_all' => ['sometimes', 'boolean'],
            'division_ids' => ['sometimes', 'array'],
            'division_ids.*' => ['uuid', 'exists:divisions,id'],
            'group_ids' => ['sometimes', 'array'],
            'group_ids.*' => ['uuid', 'exists:groups,id'],
        ];
    }

    public function messages(): array
    {
        $kb = (int) (config('dashboards.max_html_bytes') / 1024);

        return [
            'html.required' => 'Adjuntá el contenido HTML del dashboard.',
            'html.string' => 'El contenido del dashboard debe ser texto HTML.',
            'html.max' => "El HTML supera el tamaño máximo permitido ({$kb} KB).",
            'is_published.boolean' => 'El campo is_published debe ser verdadero o falso.',
            'description.max' => 'La descripción no puede superar 500 caracteres.',
            'icon.in' => 'El ícono elegido no está en el catálogo.',
            'visible_to_all.boolean' => 'El campo visible_to_all debe ser verdadero o falso.',
            'division_ids.*.exists' => 'Una de las divisiones elegidas no existe.',
            'group_ids.*.exists' => 'Uno de los grupos elegidos no existe.',
        ];
    }
}
