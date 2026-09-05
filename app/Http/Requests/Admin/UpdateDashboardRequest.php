<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDashboardRequest extends FormRequest
{
    public function rules(): array
    {
        $max = (int) config('dashboards.max_html_bytes');

        return [
            'html' => ['sometimes', 'string', "max:{$max}"],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        $kb = (int) (config('dashboards.max_html_bytes') / 1024);

        return [
            'html.string' => 'El contenido del dashboard debe ser texto HTML.',
            'html.max' => "El HTML supera el tamaño máximo permitido ({$kb} KB).",
            'is_published.boolean' => 'El campo is_published debe ser verdadero o falso.',
        ];
    }

    protected function passedValidation(): void
    {
        if (! $this->has('html') && ! $this->has('is_published')) {
            abort(422, 'Indicá al menos un cambio: html nuevo o is_published.');
        }
    }
}
