<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreDashboardRequest extends FormRequest
{
    public function rules(): array
    {
        $max = (int) config('dashboards.max_html_bytes');

        return [
            'html' => ['required', 'string', "max:{$max}"],
            'is_published' => ['sometimes', 'boolean'],
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
        ];
    }
}
