<?php

namespace Tests\Unit\Manifest;

use App\Services\Manifest\HtmlSecurityScanner;
use PHPUnit\Framework\TestCase;
use Tests\Support\BuildsDashboardHtml;

class HtmlSecurityScannerTest extends TestCase
{
    use BuildsDashboardHtml;

    private HtmlSecurityScanner $scanner;

    protected function setUp(): void
    {
        $this->scanner = new HtmlSecurityScanner(['cdn.jsdelivr.net', 'cdnjs.cloudflare.com']);
    }

    private function scan(string $body, string $head = ''): array
    {
        return array_map(fn ($p) => $p->toArray(), $this->scanner->scan($this->htmlWithManifest($this->fullManifest(), $body, $head)));
    }

    public function test_clean_dashboard_passes(): void
    {
        $this->assertSame([], $this->scan('<script>Dashboard.ready();</script>'));
        $this->assertSame([], $this->scanner->scan($this->kitHtml()));
    }

    public function test_rule_9_storage_and_cookies_are_reported_with_line(): void
    {
        $problems = $this->scan("<script>\nvar a = localStorage.getItem('x');\nsessionStorage.clear();\ndocument.cookie = 'a=1';\n</script>");

        $this->assertCount(3, $problems);
        $this->assertSame([9, 9, 9], array_column($problems, 'rule'));
        $this->assertStringContainsString('localStorage', $problems[0]['message']);
        $this->assertMatchesRegularExpression('/^línea \d+$/', $problems[0]['path']);
    }

    public function test_rule_9_fetch_to_external_domain(): void
    {
        $problems = $this->scan('<script>fetch("https://api.ejemplo.com/datos")</script>');

        $this->assertSame(9, $problems[0]['rule']);
        $this->assertStringContainsString('api.ejemplo.com', $problems[0]['message']);
    }

    public function test_rule_9_fetch_to_allowed_cdn_is_ok_but_dynamic_url_is_not(): void
    {
        $this->assertSame([], $this->scan('<script>fetch("https://cdn.jsdelivr.net/npm/x/data.json")</script>'));

        $problems = $this->scan('<script>fetch(url)</script>');
        $this->assertStringContainsString('no es un texto literal', $problems[0]['message']);
    }

    public function test_rule_9_other_network_apis(): void
    {
        $problems = $this->scan('<script>var x = new XMLHttpRequest(); new WebSocket("wss://a"); navigator.sendBeacon("/x");</script>');

        $this->assertCount(3, $problems);
    }

    public function test_rule_10_script_src_must_be_on_allowlist(): void
    {
        $problems = $this->scan('', '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script src="https://evil.example/x.js"></script><script src="./local.js"></script>');

        $this->assertCount(2, $problems);
        $this->assertSame([10, 10], array_column($problems, 'rule'));
        $this->assertStringContainsString('evil.example', $problems[0]['message']);
        $this->assertStringContainsString('./local.js', $problems[1]['message']);
    }

    public function test_rule_10_external_stylesheets_are_reported(): void
    {
        $problems = $this->scan('', '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter">');

        $this->assertSame(10, $problems[0]['rule']);
        $this->assertStringContainsString('fonts.googleapis.com', $problems[0]['message']);
    }

    public function test_manifest_block_is_not_scanned(): void
    {
        $manifest = $this->fullManifest(['title' => 'Uso de localStorage y fetch(x)']);

        $this->assertSame([], $this->scanner->scan($this->htmlWithManifest($manifest)));
    }

    public function test_csp_lists_the_allowed_cdns(): void
    {
        $csp = $this->scanner->contentSecurityPolicy();

        $this->assertStringContainsString("default-src 'none'", $csp);
        $this->assertStringContainsString('script-src \'unsafe-inline\' \'unsafe-eval\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com', $csp);
        $this->assertStringContainsString('connect-src https://cdn.jsdelivr.net', $csp);
    }
}
