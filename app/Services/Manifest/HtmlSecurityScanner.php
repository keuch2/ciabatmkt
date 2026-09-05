<?php

namespace App\Services\Manifest;

/**
 * Reglas 9 y 10 del validador de carga. Es un análisis estático con expresiones regulares:
 * sirve para avisar al super administrador con línea exacta, no como frontera de seguridad.
 * La frontera real la ponen el sandbox del iframe y la CSP que inyecta el contenedor.
 */
class HtmlSecurityScanner
{
    /** @param  list<string>  $cdnAllowlist  Hosts permitidos para script src, estilos y fetch. */
    public function __construct(
        private readonly array $cdnAllowlist,
        private readonly ManifestExtractor $extractor = new ManifestExtractor,
    ) {}

    /** @return list<ManifestProblem> */
    public function scan(string $html): array
    {
        $code = $this->extractor->strip($html);
        $problems = [];

        // Regla 9: APIs de almacenamiento y red.
        $forbidden = [
            '/\blocalStorage\b/' => 'Uso de localStorage. El dashboard corre aislado y no puede persistir datos en el navegador; los valores se guardan por la plataforma.',
            '/\bsessionStorage\b/' => 'Uso de sessionStorage. El dashboard corre aislado y no puede persistir datos en el navegador.',
            '/\bdocument\s*\.\s*cookie\b/' => 'Uso de document.cookie. El dashboard no tiene acceso a cookies.',
            '/\bXMLHttpRequest\b/' => 'Uso de XMLHttpRequest. El dashboard debe ser autocontenido; sólo se permiten peticiones a los CDN autorizados mediante fetch.',
            '/\bnavigator\s*\.\s*sendBeacon\b/' => 'Uso de navigator.sendBeacon. El dashboard no puede enviar datos a servicios externos.',
            '/\bnew\s+WebSocket\b/' => 'Uso de WebSocket. El dashboard no puede abrir conexiones externas.',
            '/\bnew\s+EventSource\b/' => 'Uso de EventSource. El dashboard no puede abrir conexiones externas.',
        ];
        foreach ($forbidden as $pattern => $message) {
            if (preg_match_all($pattern, $code, $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[0] as [, $offset]) {
                    $problems[] = new ManifestProblem(9, 'línea '.$this->line($code, $offset), $message);
                }
            }
        }

        if (preg_match_all('/\bfetch\s*\(\s*(?:(["\'`])(.*?)\1|([^)\s,]+))/', $code, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($m as $match) {
                $line = $this->line($code, $match[0][1]);
                $literal = $match[2][0] ?? '';
                if ($literal === '' || ($match[2][1] ?? -1) === -1) {
                    $problems[] = new ManifestProblem(9, "línea {$line}", 'fetch() con un destino que no es un texto literal; no se puede verificar a qué dominio apunta. Usá una URL literal de un CDN autorizado o eliminá la petición.');
                } elseif (! $this->isAllowedUrl($literal)) {
                    $problems[] = new ManifestProblem(9, "línea {$line}", "fetch() a «{$literal}», que no es un CDN autorizado (".implode(', ', $this->cdnAllowlist).').');
                }
            }
        }

        // Regla 10: <script src> y hojas de estilo externas sólo desde los CDN autorizados.
        if (preg_match_all('/<script\b[^>]*\bsrc\s*=\s*(["\']?)([^"\'\s>]+)\1[^>]*>/i', $code, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($m as $match) {
                $src = $match[2][0];
                if (! $this->isAllowedUrl($src)) {
                    $problems[] = new ManifestProblem(10, 'línea '.$this->line($code, $match[0][1]), "<script src=\"{$src}\"> no apunta a un CDN autorizado (".implode(', ', $this->cdnAllowlist).'). Las rutas relativas no están permitidas: el dashboard debe ser autocontenido.');
                }
            }
        }
        if (preg_match_all('/<link\b[^>]*\bhref\s*=\s*(["\']?)([^"\'\s>]+)\1[^>]*>/i', $code, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($m as $match) {
                $href = $match[2][0];
                if (! $this->isAllowedUrl($href) && ! str_starts_with($href, 'data:')) {
                    $problems[] = new ManifestProblem(10, 'línea '.$this->line($code, $match[0][1]), "<link href=\"{$href}\"> no apunta a un CDN autorizado; la política de seguridad del iframe lo bloquearía. Incluí los estilos en línea o usá un CDN autorizado.");
                }
            }
        }

        return $problems;
    }

    /** Construye la Content-Security-Policy que el contenedor inyecta en el iframe. */
    public function contentSecurityPolicy(): string
    {
        $cdns = implode(' ', array_map(fn ($h) => "https://{$h}", $this->cdnAllowlist));

        return implode('; ', [
            "default-src 'none'",
            "script-src 'unsafe-inline' 'unsafe-eval' {$cdns}",
            "style-src 'unsafe-inline' {$cdns}",
            "img-src data: blob: {$cdns}",
            "font-src data: {$cdns}",
            "connect-src {$cdns}",
            "worker-src blob:",
        ]);
    }

    private function isAllowedUrl(string $url): bool
    {
        if (! preg_match('~^https://([^/:?#]+)~i', $url, $m)) {
            return false;
        }

        return in_array(strtolower($m[1]), array_map('strtolower', $this->cdnAllowlist), true);
    }

    private function line(string $code, int $offset): int
    {
        return substr_count($code, "\n", 0, $offset) + 1;
    }
}
