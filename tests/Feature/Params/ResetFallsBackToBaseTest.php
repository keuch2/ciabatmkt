<?php

namespace Tests\Feature\Params;

use App\Models\Dashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsDashboardHtml;
use Tests\TestCase;

class ResetFallsBackToBaseTest extends TestCase
{
    use BuildsDashboardHtml, RefreshDatabase;

    private Dashboard $dashboard;

    private User $admin;

    private User $user;

    private string $url;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dashboard = Dashboard::factory()->create(['manifest' => $this->fullManifest()]);
        $this->admin = User::factory()->superAdmin()->create();
        $this->user = User::factory()->create();
        $this->url = "/api/dashboards/{$this->dashboard->id}";
    }

    public function test_reset_returns_to_base_and_keeps_following_later_base_changes(): void
    {
        $this->actingAs($this->admin)->putJson("{$this->url}/params/meta", ['value' => 500, 'scope' => 'base']);
        $this->actingAs($this->user)->putJson("{$this->url}/params/meta", ['value' => 750]);

        $this->actingAs($this->user)
            ->deleteJson("{$this->url}/params/meta")
            ->assertOk()
            ->assertJsonPath('data', ['param_id' => 'meta', 'value' => 500, 'source' => 'base', 'has_override' => false, 'stale' => false]);

        // El reset NO escribió el default: la fila del usuario desapareció.
        $this->assertDatabaseMissing('param_values', ['user_id' => $this->user->id, 'param_id' => 'meta']);

        // Si el base cambia después, el usuario lo ve reflejado (no quedó congelado).
        $this->actingAs($this->admin)->putJson("{$this->url}/params/meta", ['value' => 620, 'scope' => 'base']);
        $this->actingAs($this->user)->getJson($this->url)->assertJsonPath('data.params.meta', ['value' => 620, 'source' => 'base', 'has_override' => false, 'stale' => false]);
    }

    public function test_reset_without_base_falls_back_to_default(): void
    {
        $this->actingAs($this->user)->putJson("{$this->url}/params/titulo", ['value' => 'Mío']);

        $this->actingAs($this->user)
            ->deleteJson("{$this->url}/params/titulo")
            ->assertOk()
            ->assertJsonPath('data.value', 'Hola')
            ->assertJsonPath('data.source', 'default');
    }

    public function test_reset_without_override_is_a_harmless_no_op(): void
    {
        $this->actingAs($this->user)->deleteJson("{$this->url}/params/meta")->assertOk()->assertJsonPath('data.source', 'default');
    }

    public function test_reset_all_removes_every_override_of_the_user_only(): void
    {
        $other = User::factory()->create();
        $this->actingAs($this->admin)->putJson("{$this->url}/params/meta", ['value' => 500, 'scope' => 'base']);
        $this->actingAs($this->user)->putJson("{$this->url}/params/meta", ['value' => 1]);
        $this->actingAs($this->user)->putJson("{$this->url}/params/titulo", ['value' => 'x']);
        $this->actingAs($other)->putJson("{$this->url}/params/meta", ['value' => 9]);

        $this->actingAs($this->user)
            ->deleteJson("{$this->url}/params")
            ->assertOk()
            ->assertJsonPath('removed', 2)
            ->assertJsonPath('data.meta.value', 500)
            ->assertJsonPath('data.meta.source', 'base')
            ->assertJsonPath('data.titulo.source', 'default')
            ->assertJsonCount(7, 'data');

        $this->assertDatabaseCount('param_values', 2); // base + override de other
    }

    public function test_orphan_override_can_be_removed(): void
    {
        $this->actingAs($this->user)->putJson("{$this->url}/params/meta", ['value' => 1]);
        $this->dashboard->forceFill(['manifest' => $this->fullManifest(['params' => []])])->save();

        $this->actingAs($this->user)
            ->deleteJson("{$this->url}/params/meta")
            ->assertOk()
            ->assertJsonPath('data.value', null);

        $this->assertDatabaseCount('param_values', 0);
    }
}
