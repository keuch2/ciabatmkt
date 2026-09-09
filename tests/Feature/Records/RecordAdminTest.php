<?php

namespace Tests\Feature\Records;

use App\Models\Dashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_export_and_history(): void
    {
        $dashboard = Dashboard::factory()->withCollections([['id' => 'solicitudes', 'label' => 'Solicitudes']])->create(['slug' => 'traslados']);
        $admin = User::factory()->superAdmin()->create();
        $ana = User::factory()->create(['name' => 'Ana']);
        $url = "/api/dashboards/{$dashboard->id}/data/solicitudes";

        $this->actingAs($ana)->putJson("{$url}/ev-1", ['data' => ['evento' => 'EXPO']]);
        $this->actingAs($ana)->putJson("{$url}/ev-2", ['data' => ['evento' => 'FERIA']]);
        $this->actingAs($ana)->deleteJson("{$url}/ev-2");

        $this->actingAs($admin)->getJson("/api/admin/dashboards/{$dashboard->id}/data")
            ->assertOk()
            ->assertJsonPath('collections.0.id', 'solicitudes')
            ->assertJsonPath('collections.0.label', 'Solicitudes')
            ->assertJsonPath('collections.0.records', 1)
            ->assertJsonPath('collections.0.max_records', 5000)
            ->assertJsonPath('orphan_collections', []);

        $this->actingAs($admin)->getJson("/api/admin/dashboards/{$dashboard->id}/data/solicitudes")
            ->assertOk()->assertJsonCount(1, 'records')->assertJsonPath('records.0.id', 'ev-1');

        $export = $this->actingAs($admin)->get("/api/admin/dashboards/{$dashboard->id}/data/solicitudes/export");
        $export->assertOk()->assertDownload('traslados-solicitudes-'.now()->format('Y-m-d').'.json');
        $payload = json_decode($export->streamedContent(), true);
        $this->assertSame('solicitudes', $payload['collection']);
        $this->assertSame([['id' => 'ev-1', 'data' => ['evento' => 'EXPO']]], $payload['records']);

        $history = $this->actingAs($admin)->getJson("/api/admin/dashboards/{$dashboard->id}/data-history")->assertOk()->json();
        $this->assertSame(3, $history['meta']['total']);
        $this->assertSame('delete', $history['data'][0]['action']);
        $this->assertSame('Ana', $history['data'][0]['changed_by']['name']);
        $this->assertSame(['evento' => 'FERIA'], $history['data'][0]['old_data']);

        $this->assertSame(1, $this->actingAs($admin)->getJson("/api/admin/dashboards/{$dashboard->id}/data-history?action=delete")->json('meta.total'));
        $this->assertSame(2, $this->actingAs($admin)->getJson("/api/admin/dashboards/{$dashboard->id}/data-history?record_id=ev-2")->json('meta.total'));

        $this->actingAs($ana)->getJson("/api/admin/dashboards/{$dashboard->id}/data")->assertForbidden();
    }
}
