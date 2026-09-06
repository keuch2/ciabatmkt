<?php

namespace Tests\Feature\Params;

use App\Models\Dashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsDashboardHtml;
use Tests\TestCase;

class ThreeLevelResolutionTest extends TestCase
{
    use BuildsDashboardHtml, RefreshDatabase;

    private Dashboard $dashboard;

    private User $admin;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dashboard = Dashboard::factory()->create(['manifest' => $this->fullManifest()]);
        $this->admin = User::factory()->superAdmin()->create();
        $this->user = User::factory()->create();
    }

    private function meta(User $as): array
    {
        return $this->actingAs($as)->getJson("/api/dashboards/{$this->dashboard->id}")->json('data.params.meta');
    }

    public function test_user_override_wins_over_base_which_wins_over_default(): void
    {
        // Nivel 3: default del manifiesto.
        $this->assertSame(['value' => 100, 'source' => 'default', 'has_override' => false, 'stale' => false], $this->meta($this->user));

        // Nivel 2: el super administrador define un valor base.
        $this->actingAs($this->admin)
            ->putJson("/api/dashboards/{$this->dashboard->id}/params/meta", ['value' => 500, 'scope' => 'base'])
            ->assertOk()
            ->assertJsonPath('data', ['param_id' => 'meta', 'value' => 500, 'source' => 'base', 'has_override' => true, 'stale' => false]);
        $this->assertSame(['value' => 500, 'source' => 'base', 'has_override' => false, 'stale' => false], $this->meta($this->user));

        // Nivel 1: el usuario guarda su propio valor.
        $this->actingAs($this->user)
            ->putJson("/api/dashboards/{$this->dashboard->id}/params/meta", ['value' => 750, 'scope' => 'user'])
            ->assertOk()
            ->assertJsonPath('data.source', 'user')
            ->assertJsonPath('data.has_override', true);
        $this->assertSame(['value' => 750, 'source' => 'user', 'has_override' => true, 'stale' => false], $this->meta($this->user));

        // Un cambio posterior del base no pisa el override del usuario.
        $this->actingAs($this->admin)->putJson("/api/dashboards/{$this->dashboard->id}/params/meta", ['value' => 600, 'scope' => 'base'])->assertOk();
        $this->assertSame(750, $this->meta($this->user)['value']);

        $this->assertDatabaseCount('param_values', 2);
    }

    public function test_scope_defaults_to_user(): void
    {
        $this->actingAs($this->user)
            ->putJson("/api/dashboards/{$this->dashboard->id}/params/titulo", ['value' => 'Mío'])
            ->assertOk()
            ->assertJsonPath('data.source', 'user');

        $this->assertDatabaseHas('param_values', ['param_id' => 'titulo', 'user_id' => $this->user->id, 'updated_by' => $this->user->id]);
    }

    public function test_every_type_round_trips_with_its_json_type(): void
    {
        $writes = [
            'meta' => 250, 'titulo' => 'Hola', 'activo' => false, 'periodo' => 'b',
            'descuento' => 15, 'corte' => '2026-03-01', 'color' => '#ff0000',
        ];
        foreach ($writes as $id => $value) {
            $this->actingAs($this->user)->putJson("/api/dashboards/{$this->dashboard->id}/params/{$id}", ['value' => $value])->assertOk();
        }

        $params = $this->actingAs($this->user)->getJson("/api/dashboards/{$this->dashboard->id}")->json('data.params');

        foreach ($writes as $id => $value) {
            $this->assertSame($value, $params[$id]['value'], "param {$id}");
            $this->assertSame('user', $params[$id]['source']);
        }
    }

    public function test_writing_the_same_param_twice_updates_the_same_row(): void
    {
        $this->actingAs($this->user)->putJson("/api/dashboards/{$this->dashboard->id}/params/meta", ['value' => 1])->assertOk();
        $this->actingAs($this->user)->putJson("/api/dashboards/{$this->dashboard->id}/params/meta", ['value' => 2])->assertOk();

        $this->assertDatabaseCount('param_values', 1);
        $this->assertSame(2, $this->meta($this->user)['value']);
    }

    public function test_unpublished_dashboard_cannot_be_written_by_users(): void
    {
        $draft = Dashboard::factory()->unpublished()->create(['manifest' => $this->fullManifest()]);

        $this->actingAs($this->user)->putJson("/api/dashboards/{$draft->id}/params/meta", ['value' => 1])->assertNotFound();
        $this->actingAs($this->admin)->putJson("/api/dashboards/{$draft->id}/params/meta", ['value' => 1])->assertOk();
    }
}
