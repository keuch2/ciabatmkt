<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Ícono propio como data: URI: PNG o SVG, hasta 64 KB decodificados. El SVG se muestra siempre
 * como <img>, donde el navegador no ejecuta scripts, pero igual se rechaza cualquier SVG con
 * scripts, manejadores de eventos o referencias externas.
 */
class DashboardIconData implements ValidationRule
{
    public const MAX_BYTES = 65536;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('~^data:image/(png|svg\+xml);base64,([A-Za-z0-9+/=\s]+)$~', $value, $m)) {
            $fail('El ícono debe ser un archivo PNG o SVG.');

            return;
        }
        $bytes = base64_decode(preg_replace('/\s+/', '', $m[2]), true);
        if ($bytes === false || $bytes === '') {
            $fail('El archivo del ícono está dañado.');

            return;
        }
        if (strlen($bytes) > self::MAX_BYTES) {
            $fail('El ícono no puede superar 64 KB. Usá una imagen más chica (alcanza con 64×64 px).');

            return;
        }
        if ($m[1] === 'png' && ! str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            $fail('El archivo no es un PNG válido.');

            return;
        }
        if ($m[1] === 'svg+xml') {
            if (! preg_match('/<svg\b/i', $bytes)) {
                $fail('El archivo no es un SVG válido.');

                return;
            }
            if (preg_match('/<script\b|\bon[a-z]+\s*=|javascript:|<foreignObject\b|<iframe\b|<embed\b|<object\b|xlink:href\s*=\s*["\']\s*(?!#|data:)/i', $bytes)) {
                $fail('El SVG contiene scripts, eventos o referencias externas; exportalo como imagen simple.');
            }
        }
    }
}
