<?php

namespace Tests\Feature\Console;

use App\Models\Dashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\BuildsDashboardHtml;
use Tests\TestCase;

class PruneOrphansTest extends TestCase
{
    use BuildsDashboardHtml, RefreshDatabase;

    private function seedOrphans(): Dashboard
    {
        $dashboard = Dashboard::factory()->create(['slug' => 'demo', 'manifest' => $this->fullManifest()]);
        $user = User::factory()->create();

        foreach (['meta', 'viejo_a', 'viejo_a', 'viejo_b'] as $i => $paramId) {
            DB::table('param_values')->insert([
                'id' => (string) Str::uuid(), 'dashboard_id' => $dashboard->id, 'param_id' => $paramId,
                'user_id' => $i === 1 ? null : $user->id, 'value' => '1', 'updated_by' => $user->id,
            ]);
        }

        return $dashboard;
    }

    public function test_dry_run_lists_without_deleting(): void
    {
        $this->seedOrphans();

        $this->artisan('dashboards:prune-orphans', ['--dry-run' => true])
            ->expectsOutputToContain('viejo_a')
            ->expectsOutputToContain('Modo prueba: 3 fila(s) quedarían eliminadas')
            ->assertSuccessful();

        $this->assertDatabaseCount('param_values', 4);
    }

    public function test_prune_deletes_orphans_and_keeps_history(): void
    {
        $this->seedOrphans();
        $historyBefore = DB::table('param_value_history')->count();

        $this->artisan('dashboards:prune-orphans', ['--dashboard' => 'demo'])
            ->expectsOutputToContain('Eliminadas 3 fila(s) huérfana(s)')
            ->assertSuccessful();

        $this->assertDatabaseCount('param_values', 1);
        $this->assertDatabaseHas('param_values', ['param_id' => 'meta']);
        // El borrado pasa por el trigger: el historial crece, nunca se recorta.
        $this->assertSame($historyBefore + 3, DB::table('param_value_history')->count());
    }

    public function test_nothing_to_prune(): void
    {
        Dashboard::factory()->create();

        $this->artisan('dashboards:prune-orphans')->expectsOutputToContain('No hay valores huérfanos.')->assertSuccessful();
    }
}
