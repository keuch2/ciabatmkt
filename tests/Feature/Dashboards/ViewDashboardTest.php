<?php

namespace Tests\Feature\Dashboards;

use App\Models\Dashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\BuildsDashboardHtml;
use Tests\TestCase;

class ViewDashboardTest extends TestCase
{
    use BuildsDashboardHtml, RefreshDatabase;

    public function test_list_shows_only_published_dashboards(): void
    {
        Dashboard::factory()->create(['title' => 'Visible']);
        Dashboard::factory()->unpublished()->create(['title' => 'Oculto']);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/dashboards')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Visible')
            ->assertJsonMissingPath('data.0.html');
    }

    public function test_show_returns_html_manifest_and_default_values(): void
    {
        $dashboard = Dashboard::factory()->create(['manifest' => $this->fullManifest(), 'html' => $this->htmlWithManifest($this->fullManifest())]);

        $response = $this->actingAs(User::factory()->create())->getJson("/api/dashboards/{$dashboard->id}");

        $response->assertOk()
            ->assertJsonPath('data.slug', $dashboard->slug)
            ->assertJsonPath('data.manifest.params.0.id', 'meta')
            ->assertJsonPath('data.params.meta', ['value' => 100, 'source' => 'default', 'has_override' => false, 'stale' => false])
            ->assertJsonPath('data.params.activo.value', true)
            ->assertJsonPath('data.params.color.value', '#1f4e79')
            ->assertJsonPath('data.security.cdn_allowlist', config('dashboards.cdn_allowlist'));

        $this->assertStringContainsString('dashboard-manifest', $response->json('data.html'));
        $this->assertStringContainsString("default-src 'none'", $response->json('data.security.csp'));
    }

    public function test_show_resolves_user_over_base_over_default(): void
    {
        $dashboard = Dashboard::factory()->create(['manifest' => $this->fullManifest()]);
        $user = User::factory()->create();
        $admin = User::factory()->superAdmin()->create();

        $insert = fn (string $paramId, ?string $userId, mixed $value) => DB::table('param_values')->insert([
            'id' => (string) Str::uuid(), 'dashboard_id' => $dashboard->id, 'param_id' => $paramId,
            'user_id' => $userId, 'value' => json_encode($value), 'updated_by' => $admin->id,
        ]);
        $insert('meta', null, 500);          // base
        $insert('meta', $user->id, 750);     // override del usuario
        $insert('titulo', null, 'Base');     // sólo base
        $insert('descuento', $user->id, 999); // override obsoleto: fuera de rango
        $insert('fantasma', $user->id, 1);   // huérfano: no está en el manifiesto

        $response = $this->actingAs($user)->getJson("/api/dashboards/{$dashboard->id}");

        $response->assertOk()
            ->assertJsonPath('data.params.meta', ['value' => 750, 'source' => 'user', 'has_override' => true, 'stale' => false])
            ->assertJsonPath('data.params.titulo', ['value' => 'Base', 'source' => 'base', 'has_override' => false, 'stale' => false])
            ->assertJsonPath('data.params.descuento', ['value' => 5, 'source' => 'default', 'has_override' => true, 'stale' => true])
            ->assertJsonPath('data.params.activo.source', 'default')
            ->assertJsonMissingPath('data.params.fantasma');

        // Otro usuario no ve el override ajeno.
        $this->actingAs(User::factory()->create())
            ->getJson("/api/dashboards/{$dashboard->id}")
            ->assertJsonPath('data.params.meta', ['value' => 500, 'source' => 'base', 'has_override' => false, 'stale' => false]);
    }

    public function test_unpublished_dashboard_is_404_for_users_but_visible_for_admin(): void
    {
        $dashboard = Dashboard::factory()->unpublished()->create();

        $this->actingAs(User::factory()->create())
            ->getJson("/api/dashboards/{$dashboard->id}")
            ->assertNotFound()
            ->assertJsonPath('message', 'El dashboard solicitado no existe o no está publicado.');

        $this->actingAs(User::factory()->superAdmin()->create())
            ->getJson("/api/dashboards/{$dashboard->id}")
            ->assertOk();
    }

    public function test_invalid_id_is_404(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/dashboards/no-existe')->assertNotFound();
    }

    public function test_guest_cannot_list(): void
    {
        $this->getJson('/api/dashboards')->assertUnauthorized();
    }
}
