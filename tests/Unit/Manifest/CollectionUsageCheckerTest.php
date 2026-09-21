<?php

namespace Tests\Unit\Manifest;

use App\Services\Manifest\CollectionUsageChecker;
use PHPUnit\Framework\TestCase;
use Tests\Support\BuildsDashboardHtml;

class CollectionUsageCheckerTest extends TestCase
{
    use BuildsDashboardHtml;

    private function check(string $script, array $collections): array
    {
        $manifest = ['id' => 'x', 'version' => '1', 'title' => 'X', 'collections' => $collections];

        return array_map(fn ($p) => $p->toArray(), (new CollectionUsageChecker)->check($this->htmlWithManifest($manifest, "<script>\n{$script}\n</script>"), $manifest));
    }

    public function test_declared_collections_pass_with_literals_and_constants(): void
    {
        $script = "const COL = 'acciones';\nDashboard.data.list(COL);\nDashboard.data.put(\"reporte\", id, data);\nfunction load(col){ return Dashboard.data.list(col); }";

        $this->assertSame([], $this->check($script, [['id' => 'acciones', 'label' => 'A'], ['id' => 'reporte', 'label' => 'R']]));
    }

    public function test_undeclared_collection_used_through_a_constant_is_reported_once_with_its_line(): void
    {
        $script = "const COL_ACT = 'actividades';\nasync function nueva(){ await Dashboard.data.put(COL_ACT, id, data); }\nDashboard.data.remove(COL_ACT, id);";

        $problems = $this->check($script, [['id' => 'acciones', 'label' => 'A']]);

        $this->assertCount(1, $problems);
        $this->assertSame(11, $problems[0]['rule']);
        $this->assertMatchesRegularExpression('/^línea \d+$/', $problems[0]['path']);
        $this->assertStringContainsString('«actividades»', $problems[0]['message']);
        $this->assertStringContainsString('Declaradas: acciones.', $problems[0]['message']);
    }

    public function test_no_collections_declared_at_all(): void
    {
        $problems = $this->check("Dashboard.data.seed('notas', []);", []);

        $this->assertStringContainsString('Declaradas: (ninguna).', $problems[0]['message']);
    }

    public function test_kit_examples_are_consistent(): void
    {
        $checker = new CollectionUsageChecker;
        $html = file_get_contents(dirname(__DIR__, 3).'/kit/ejemplos/traslado-maquinas-eventos.html');
        preg_match('~id="dashboard-manifest">(.*?)</script>~s', $html, $m);

        $this->assertSame([], $checker->check($html, json_decode($m[1], true)));
    }
}
