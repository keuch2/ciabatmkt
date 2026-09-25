<?php

namespace Tests\Feature\Admin;

use App\Models\Dashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_diagnostics_report_failures_undeclared_data_and_large_records(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $user = User::factory()->create(['name' => 'Ana']);
        $dashboard = Dashboard::factory()->withCollections([['id' => 'reservas', 'label' => 'Reservas', 'maxBytes' => 2048]])->create();
        $dashboard->forceFill(['html' => str_replace('</head>', "<script>const COL='reservas'; Dashboard.data.put(COL, 'x', {}); Dashboard.data.list('fantasma');</script></head>", $dashboard->html)])->save();
        $url = "/api/dashboards/{$dashboard->id}/data";

        $this->actingAs($user)->putJson("{$url}/reservas/r1", ['data' => ['x' => str_repeat('a', 1800)]])->assertOk();
        $this->actingAs($user)->putJson("{$url}/fantasma/r1", ['data' => ['x' => 1]])->assertUnprocessable();
        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($user)->putJson("{$url}/reservas/r1", ['data' => ['x' => str_repeat('a', 1800)]])->assertOk();
        }

        $result = $this->actingAs($admin)->getJson("/api/admin/dashboards/{$dashboard->id}/diagnostics")->assertOk()->json();

        $this->assertSame('error', $result['status']);
        $areas = array_column($result['findings'], 'area');
        $this->assertContains('validador', $areas, 'la colección fantasma usada en el código no está declarada (regla 11)');
        $this->assertContains('escrituras', $areas);
        $this->assertContains('tamaño', $areas);
        $this->assertContains('guardado', $areas);
        $this->assertSame(1, $result['summary']['failures_last_days']['invalid']);
        $this->assertStringContainsString('INFORME DE DIAGNÓSTICO', $result['report']);
        $this->assertStringContainsString('fantasma', $result['report']);

        $this->actingAs($admin)->getJson('/api/admin/dashboards')->assertJsonPath('data.0.failures_7d', 1);
        $this->actingAs($user)->getJson("/api/admin/dashboards/{$dashboard->id}/diagnostics")->assertForbidden();
    }

    public function test_download_with_data_embeds_current_records_and_is_safe_to_reupload(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $dashboard = Dashboard::factory()->withCollections([['id' => 'reservas', 'label' => 'R']])->create();
        $original = $dashboard->html;
        $this->actingAs($admin)->putJson("/api/dashboards/{$dashboard->id}/data/reservas/r1", ['data' => ['name' => 'Hotel </script> Muller', 'days' => new \stdClass]])->assertOk();

        $response = $this->actingAs($admin)->get("/api/admin/dashboards/{$dashboard->id}/html?data=1");
        $response->assertOk();
        $this->assertStringContainsString("{$dashboard->slug}-v1.0.0-con-datos-", $response->headers->get('content-disposition'));
        $html = $response->streamedContent();

        $this->assertStringContainsString('<script id="ciabay-snapshot">', $html);
        $this->assertStringContainsString("if (typeof window.Dashboard === 'undefined')", $html);
        $this->assertStringContainsString('"name":"Hotel \\u003c/script> Muller"', $html, 'el "<" va escapado para no cortar el bloque');
        $this->assertStringContainsString('"days":{}', $html, 'un objeto vacío se conserva como objeto');
        $this->assertLessThan(strpos($html, 'dashboard-manifest'), strpos($html, 'ciabay-snapshot'), 'la copia se inyecta antes de cualquier script');

        // Volver a subir la copia: pasa el validador, se guarda sin el bloque y los datos quedan intactos.
        $this->actingAs($admin)->putJson("/api/admin/dashboards/{$dashboard->id}", ['html' => $html])->assertOk();
        $this->assertSame($original, $dashboard->fresh()->html, 'al volver a subir la copia queda exactamente el archivo original');
        $this->assertDatabaseHas('dashboard_records', ['dashboard_id' => $dashboard->id, 'record_id' => 'r1']);
        $this->assertDatabaseCount('dashboard_records', 1);

        // Sin datos: el archivo original tal cual.
        $plain = $this->actingAs($admin)->get("/api/admin/dashboards/{$dashboard->id}/html")->streamedContent();
        $this->assertStringNotContainsString('ciabay-snapshot', $plain);
        $this->assertSame($dashboard->fresh()->html, $plain);
    }

    public function test_admin_can_download_the_current_html(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $dashboard = Dashboard::factory()->create(['slug' => 'demo', 'version' => '1.2.0']);

        $response = $this->actingAs($admin)->get("/api/admin/dashboards/{$dashboard->id}/html");
        $response->assertOk()->assertDownload('demo-v1.2.0.html');
        $this->assertSame($dashboard->html, $response->streamedContent());

        $this->actingAs(User::factory()->create())->get("/api/admin/dashboards/{$dashboard->id}/html")->assertForbidden();
    }
}
