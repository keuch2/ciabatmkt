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
