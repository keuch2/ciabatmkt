<?php

namespace Tests\Support;

trait BuildsDashboardHtml
{
    /** HTML mínimo válido con el manifiesto dado. */
    protected function htmlWithManifest(array $manifest, string $body = '<h1>Demo</h1>', string $extraHead = ''): string
    {
        $json = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return "<!doctype html>\n<html>\n<head>\n<meta charset=\"utf-8\">\n{$extraHead}\n<script type=\"application/json\" id=\"dashboard-manifest\">\n{$json}\n</script>\n</head>\n<body>\n{$body}\n</body>\n</html>\n";
    }

    /** Manifiesto válido con un parámetro de cada tipo. */
    protected function fullManifest(array $overrides = []): array
    {
        return array_merge([
            'id' => 'demo-completo',
            'version' => '1.0.0',
            'title' => 'Demo completo',
            'params' => [
                ['id' => 'meta', 'label' => 'Meta', 'type' => 'number', 'default' => 100, 'min' => 0, 'max' => 1000, 'step' => 10, 'unit' => 'Gs.'],
                ['id' => 'titulo', 'label' => 'Título', 'type' => 'text', 'default' => 'Hola', 'maxLength' => 20],
                ['id' => 'activo', 'label' => 'Activo', 'type' => 'boolean', 'default' => true],
                ['id' => 'periodo', 'label' => 'Período', 'type' => 'select', 'default' => 'a', 'options' => [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']]],
                ['id' => 'descuento', 'label' => 'Descuento', 'type' => 'range', 'default' => 5, 'min' => 0, 'max' => 50, 'step' => 5, 'unit' => '%'],
                ['id' => 'corte', 'label' => 'Corte', 'type' => 'date', 'default' => '2026-06-30', 'min' => '2026-01-01', 'max' => '2026-12-31'],
                ['id' => 'color', 'label' => 'Color', 'type' => 'color', 'default' => '#1f4e79'],
            ],
        ], $overrides);
    }

    protected function kitHtml(): string
    {
        return file_get_contents(dirname(__DIR__, 2).'/kit/dashboard-referencia.html');
    }
}
