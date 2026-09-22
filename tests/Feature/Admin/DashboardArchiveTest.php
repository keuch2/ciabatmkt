<?php

namespace Tests\Feature\Admin;

use App\Models\Dashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DashboardArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_dashboard_archives_its_data_and_the_command_restores_it(): void
    {
        Storage::fake('local');
        $admin = User::factory()->superAdmin()->create();
        $dashboard = Dashboard::factory()->withCollections([['id' => 'solicitudes', 'label' => 'S']])->create(['slug' => 'demo']);
        $url = "/api/dashboards/{$dashboard->id}/data/solicitudes";
        $this->actingAs($admin)->putJson("{$url}/a", ['data' => ['n' => 1]]);
        $this->actingAs($admin)->putJson("{$url}/b", ['data' => ['n' => 2]]);
        $this->actingAs($admin)->putJson("{$url}/b", ['data' => ['n' => 3]]);

        $this->actingAs($admin)->deleteJson("/api/admin/dashboards/{$dashboard->id}")->assertNoContent();

        $files = Storage::disk('local')->files('dashboard-archive');
        $this->assertCount(1, $files);
        $payload = json_decode(Storage::disk('local')->get($files[0]), true);
        $this->assertSame('demo', $payload['dashboard']['slug']);
        $this->assertSame($admin->email, $payload['archived_by']['email']);
        $this->assertCount(2, $payload['records']);
        $this->assertSame(['n' => 3], collect($payload['records'])->firstWhere('id', 'b')['data']);
        $this->assertCount(3, $payload['record_history']);
        $this->assertDatabaseCount('dashboard_records', 0);

        // Sin destino, avisa; con el dashboard republicado, restaura.
        $this->artisan('dashboards:restore', ['archive' => basename($files[0])])->assertFailed();
        $again = Dashboard::factory()->withCollections([['id' => 'solicitudes', 'label' => 'S']])->create(['slug' => 'demo']);
        $this->artisan('dashboards:restore', ['archive' => basename($files[0]), '--dry-run' => true])->expectsOutputToContain('2 registros se cargarían')->assertSuccessful();
        $this->assertDatabaseCount('dashboard_records', 0);
        $this->artisan('dashboards:restore', ['archive' => basename($files[0])])->expectsOutputToContain('solicitudes: 2 registros cargados')->assertSuccessful();
        $this->assertDatabaseHas('dashboard_records', ['dashboard_id' => $again->id, 'record_id' => 'b']);

        $this->artisan('dashboards:restore')->expectsOutputToContain(basename($files[0]))->assertSuccessful();
    }
}
