<?php

namespace Tests\Feature\Admin;

use App\Models\Dashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsDashboardHtml;
use Tests\TestCase;

class OverviewTest extends TestCase
{
    use BuildsDashboardHtml, RefreshDatabase;

    public function test_overview_shows_effective_value_per_user_and_param(): void
    {
        $admin = User::factory()->superAdmin()->create(['name' => 'Admin']);
        $dashboard = Dashboard::factory()->create(['manifest' => $this->fullManifest(), 'created_by' => $admin->id]);
        $ana = User::factory()->create(['name' => 'Ana']);
        $bruno = User::factory()->create(['name' => 'Bruno']);
        User::factory()->inactive()->create(['name' => 'Baja']);
        $url = "/api/dashboards/{$dashboard->id}";

        $this->actingAs($admin)->putJson("{$url}/params/meta", ['value' => 500, 'scope' => 'base']);
        $this->actingAs($ana)->putJson("{$url}/params/meta", ['value' => 750]);
        $this->actingAs($ana)->putJson("{$url}/params/titulo", ['value' => 'de Ana']);

        $response = $this->actingAs($admin)->getJson("/api/admin/dashboards/{$dashboard->id}/overview");

        $response->assertOk()
            ->assertJsonPath('dashboard.slug', $dashboard->slug)
            ->assertJsonCount(7, 'params')
            ->assertJsonPath('params.0', ['id' => 'meta', 'label' => 'Meta', 'type' => 'number', 'unit' => 'Gs.', 'default' => 100, 'options' => null])
            ->assertJsonPath('params.3.options.0.label', 'A')
            ->assertJsonPath('base.meta.value', 500)
            ->assertJsonPath('base.meta.source', 'base')
            ->assertJsonPath('base.titulo.source', 'default')
            ->assertJsonCount(3, 'users')
            ->assertJsonPath('users.0.user.name', 'Ana')
            ->assertJsonPath('users.0.override_count', 2)
            ->assertJsonPath('users.0.params.meta', ['value' => 750, 'source' => 'user', 'has_override' => true, 'stale' => false])
            ->assertJsonPath('users.0.params.titulo.value', 'de Ana');

        $bruno = collect($response->json('users'))->firstWhere('user.name', 'Bruno');
        $this->assertSame(0, $bruno['override_count']);
        $this->assertSame(['value' => 500, 'source' => 'base', 'has_override' => false, 'stale' => false], $bruno['params']['meta']);
        $this->assertNull(collect($response->json('users'))->firstWhere('user.name', 'Baja'), 'los inactivos no aparecen');
    }

    public function test_overview_requires_super_admin(): void
    {
        $dashboard = Dashboard::factory()->create();

        $this->actingAs(User::factory()->create())->getJson("/api/admin/dashboards/{$dashboard->id}/overview")->assertForbidden();
    }
}
