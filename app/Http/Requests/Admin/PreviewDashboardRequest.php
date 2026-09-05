<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PreviewDashboardRequest extends FormRequest
{
    public function rules(): array
    {
        $max = (int) config('dashboards.max_html_bytes');

        return [
            'html' => ['required', 'string', "max:{$max}"],
            'dashboard_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }

    public function messages(): array
    {
        $kb = (int) (config('dashboards.max_html_bytes') / 1024);

        return [
            'html.required' => 'Adjuntá el contenido HTML del dashboard.',
            'html.max' => "El HTML supera el tamaño máximo permitido ({$kb} KB).",
            'dashboard_id.uuid' => 'El identificador del dashboard no es válido.',
        ];
    }
}
