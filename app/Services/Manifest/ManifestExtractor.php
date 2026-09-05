<?php

namespace App\Services\Manifest;

use JsonException;

/**
 * Reglas 1 y 2 del validador: el HTML contiene exactamente un bloque
 * <script type="application/json" id="dashboard-manifest"> y su contenido es JSON válido.
 *
 * Los comentarios HTML se ignoran (un comentario puede mencionar la etiqueta del manifiesto
 * sin ser el manifiesto). Al quitarlos se conservan los saltos de línea, así los números de
 * línea que reporta el escáner siguen coincidiendo con el archivo original.
 */
class ManifestExtractor
{
    private const PATTERN = '~<script\b(?=[^>]*\btype\s*=\s*["\']application/json["\'])(?=[^>]*\bid\s*=\s*["\']dashboard-manifest["\'])[^>]*>(.*?)</script\s*>~is';

    private const COMMENT = '~<!--.*?-->~s';

    /**
     * @return array{manifest: ?array<string, mixed>, raw: ?string, problems: list<ManifestProblem>}
     */
    public function extract(string $html): array
    {
        $count = preg_match_all(self::PATTERN, $this->withoutComments($html), $matches);

        if ($count === 0) {
            return $this->failure(1, 'html', 'El archivo no contiene el bloque <script type="application/json" id="dashboard-manifest">.');
        }

        if ($count > 1) {
            return $this->failure(1, 'html', "El archivo contiene {$count} bloques dashboard-manifest; debe haber exactamente uno.");
        }

        $raw = trim($matches[1][0]);

        if ($raw === '') {
            return $this->failure(2, 'manifest', 'El bloque dashboard-manifest está vacío.');
        }

        try {
            $manifest = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return $this->failure(2, 'manifest', 'El bloque dashboard-manifest no es JSON válido: '.$e->getMessage().'.');
        }

        if (! is_array($manifest) || array_is_list($manifest)) {
            return $this->failure(2, 'manifest', 'El bloque dashboard-manifest debe ser un objeto JSON.');
        }

        return ['manifest' => $manifest, 'raw' => $raw, 'problems' => []];
    }

    /**
     * Versión del HTML sin comentarios ni bloque del manifiesto, con las líneas preservadas,
     * para que el escáner de seguridad analice sólo código real.
     */
    public function strip(string $html): string
    {
        return preg_replace_callback(self::PATTERN, fn ($m) => $this->newlinesOf($m[0]), $this->withoutComments($html)) ?? $html;
    }

    private function withoutComments(string $html): string
    {
        return preg_replace_callback(self::COMMENT, fn ($m) => $this->newlinesOf($m[0]), $html) ?? $html;
    }

    private function newlinesOf(string $text): string
    {
        return str_repeat("\n", substr_count($text, "\n"));
    }

    private function failure(int $rule, string $path, string $message): array
    {
        return ['manifest' => null, 'raw' => null, 'problems' => [new ManifestProblem($rule, $path, $message)]];
    }
}
