<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Documentación para el super administrador, servida desde los archivos del kit para que
 * el prompt, la especificación y la guía nunca diverjan de lo que está en el repositorio.
 */
class DocsController extends Controller
{
    private const START = '---INICIO---';

    private const END = '---FIN---';

    public function index(): JsonResponse
    {
        $template = $this->read('PLANTILLA-PROMPT.md');
        [$intro, $prompt, $example] = $this->splitTemplate($template);

        $fix = $this->read('PLANTILLA-CORRECCION.md');
        [$fixIntro, $fixPrompt, $fixExample] = $this->splitTemplate($fix);

        return response()->json([
            'fix_prompt' => [
                'intro_html' => Str::markdown($fixIntro),
                'text' => $fixPrompt,
                'example_html' => Str::markdown($fixExample),
            ],
            'prompt' => [
                'intro_html' => Str::markdown($intro),
                'text' => $prompt,
                'example_html' => Str::markdown($example),
            ],
            'specification_html' => Str::markdown($this->read('ESPECIFICACION.md')),
            'guide_html' => Str::markdown($this->read('GUIA-OPERATIVA.md')),
            'cdn_allowlist' => config('dashboards.cdn_allowlist'),
        ]);
    }

    /** Descarga del dashboard de referencia, para usarlo como base o como prueba de carga. */
    public function referenceDashboard(): BinaryFileResponse
    {
        return response()->download(base_path('kit/dashboard-referencia.html'), 'dashboard-referencia.html', [
            'Content-Type' => 'text/html; charset=utf-8',
        ]);
    }

    private function read(string $file): string
    {
        return (string) file_get_contents(base_path('kit/'.$file));
    }

    /** @return array{0: string, 1: string, 2: string} intro, bloque del prompt, ejemplo posterior */
    private function splitTemplate(string $template): array
    {
        // Los marcadores valen sólo cuando ocupan una línea entera: el texto de introducción
        // los menciona entre comillas y no debe confundirse con el bloque real.
        $pattern = '/^'.preg_quote(self::START, '/').'\s*$(.*?)^'.preg_quote(self::END, '/').'\s*$/ms';

        if (! preg_match($pattern, $template, $m, PREG_OFFSET_CAPTURE)) {
            return [$template, '', ''];
        }

        $intro = trim(substr($template, 0, $m[0][1]));
        $prompt = trim($m[1][0]);
        $example = trim(substr($template, $m[0][1] + strlen($m[0][0])));

        return [$intro, $prompt, $example];
    }
}
