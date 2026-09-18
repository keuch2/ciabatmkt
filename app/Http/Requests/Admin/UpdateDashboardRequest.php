<?php

namespace App\Http\Requests\Admin;

use App\Support\DashboardIcons;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDashboardRequest extends FormRequest
{
    public function rules(): array
    {
        $max = (int) config('dashboards.max_html_bytes');

        return [
            'html' => ['sometimes', 'string', "max:{$max}"],
            'is_published' => ['sometimes', 'boolean'],
            'title' => ['sometimes', 'required', 'string', 'max:150'],
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
            'html.string' => 'El contenido del dashboard debe ser texto HTML.',
            'html.max' => "El HTML supera el tamaño máximo permitido ({$kb} KB).",
            'is_published.boolean' => 'El campo is_published debe ser verdadero o falso.',
            'title.required' => 'El nombre del dashboard no puede quedar vacío.',
            'title.max' => 'El nombre no puede superar 150 caracteres.',
            'description.max' => 'La descripción no puede superar 500 caracteres.',
            'icon.in' => 'El ícono elegido no está en el catálogo.',
            'visible_to_all.boolean' => 'El campo visible_to_all debe ser verdadero o falso.',
            'division_ids.*.exists' => 'Una de las divisiones elegidas no existe.',
            'group_ids.*.exists' => 'Uno de los grupos elegidos no existe.',
        ];
    }

    protected function passedValidation(): void
    {
        if (! $this->hasAny(['html', 'is_published', 'title', 'description', 'icon', 'visible_to_all', 'division_ids', 'group_ids'])) {
            abort(422, 'Indicá al menos un cambio: html, is_published, title, description, icon, visible_to_all, division_ids o group_ids.');
        }
    }
}
