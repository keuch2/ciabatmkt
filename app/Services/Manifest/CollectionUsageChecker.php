<?php

namespace App\Services\Manifest;

/**
 * Regla 11 del validador de carga: toda colección que el código usa con Dashboard.data debe estar
 * declarada en "collections" del manifiesto. Si falta, el servidor rechaza cada escritura y el
 * dashboard "no guarda" sin explicación visible; conviene detectarlo al publicar.
 *
 * Es un análisis estático: reconoce nombres literales y constantes simples (const X = 'nombre').
 * Un nombre que llega por parámetro de función no se puede resolver y se ignora.
 */
class CollectionUsageChecker
{
    private const CALL = '/\bDashboard\s*\.\s*data\s*\.\s*(list|put|remove|seed|replace)\s*\(\s*(?:([\'"`])([^\'"`]*)\2|([A-Za-z_$][\w$]*))/';

    public function __construct(private readonly ManifestExtractor $extractor = new ManifestExtractor) {}

    /**
     * @param  array<string, mixed>  $manifest
     * @return list<ManifestProblem>
     */
    public function check(string $html, array $manifest): array
    {
        $code = $this->extractor->strip($html);
        if (! preg_match_all(self::CALL, $code, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $declared = [];
        foreach (is_array($manifest['collections'] ?? null) ? $manifest['collections'] : [] as $collection) {
            if (is_array($collection) && is_string($collection['id'] ?? null)) {
                $declared[$collection['id']] = true;
            }
        }

        $used = []; // nombre => primera línea donde aparece
        foreach ($matches as $match) {
            $name = ($match[3][1] ?? -1) >= 0 && $match[3][0] !== '' ? $match[3][0] : $this->resolveConstant($code, $match[4][0] ?? '');
            if ($name === null || $name === '') {
                continue;
            }
            $used[$name] ??= substr_count($code, "\n", 0, $match[0][1]) + 1;
        }

        $problems = [];
        foreach ($used as $name => $line) {
            if (! isset($declared[$name])) {
                $list = $declared === [] ? '(ninguna)' : implode(', ', array_keys($declared));
                $problems[] = new ManifestProblem(11, "línea {$line}", "El código usa la colección «{$name}» con Dashboard.data, pero no está declarada en \"collections\" del manifiesto. Agregala ({ \"id\": \"{$name}\", \"label\": \"…\" }) o el dashboard no va a poder guardar esos datos. Declaradas: {$list}.");
            }
        }

        return $problems;
    }

    /** Valor de una constante simple: const|let|var NOMBRE = 'valor'. */
    private function resolveConstant(string $code, string $identifier): ?string
    {
        if ($identifier === '') {
            return null;
        }
        $pattern = '/\b(?:const|let|var)\s+'.preg_quote($identifier, '/').'\s*=\s*([\'"`])([^\'"`]*)\1/';

        return preg_match($pattern, $code, $m) ? $m[2] : null;
    }
}
