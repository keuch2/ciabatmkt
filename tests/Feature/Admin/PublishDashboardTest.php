<?php

namespace Tests\Feature\Admin;

use App\Models\Dashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsDashboardHtml;
use Tests\TestCase;

class PublishDashboardTest extends TestCase
{
    use BuildsDashboardHtml, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->superAdmin()->create();
    }

    public function test_reference_dashboard_is_published(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/admin/dashboards', ['html' => $this->kitHtml()]);

        $response->assertCreated()
            ->assertJsonPath('data.slug', 'ventas-sucursal')
            ->assertJsonPath('data.title', 'Ventas por sucursal')
            ->assertJsonPath('data.version', '1.0.0')
            ->assertJsonPath('data.is_published', true)
            ->assertJsonPath('data.param_count', 7);

        $dashboard = Dashboard::query()->firstOrFail();
        $this->assertSame($this->kitHtml(), $dashboard->html);
        $this->assertSame('meta_ventas', $dashboard->manifest['params'][0]['id']);
        $this->assertSame($this->admin->id, $dashboard->created_by);
    }

    public function test_can_be_stored_as_draft(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/admin/dashboards', ['html' => $this->kitHtml(), 'is_published' => false])
            ->assertCreated()
            ->assertJsonPath('data.is_published', false);
    }

    public function test_invalid_dashboard_is_rejected_with_exact_problems(): void
    {
        $manifest = $this->fullManifest();
        $manifest['params'][0]['default'] = 99999;
        $manifest['params'][3]['default'] = 'zzz';
        $html = $this->htmlWithManifest($manifest, '<script>localStorage.setItem("a", 1)</script>');

        $response = $this->actingAs($this->admin)->postJson('/api/admin/dashboards', ['html' => $html]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'El dashboard no pasó la validación: hay 3 problemas que corregir.')
            ->assertJsonCount(3, 'problems')
            ->assertJsonPath('problems.0.rule', 7)
            ->assertJsonPath('problems.0.path', 'params[0] (meta).default')
            ->assertJsonPath('problems.1.rule', 8)
            ->assertJsonPath('problems.2.rule', 9);

        $this->assertDatabaseCount('dashboards', 0);
    }

    public function test_missing_manifest_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/admin/dashboards', ['html' => '<html><body>nada</body></html>'])
            ->assertUnprocessable()
            ->assertJsonPath('problems.0.rule', 1);
    }

    public function test_duplicate_slug_returns_409_pointing_to_existing(): void
    {
        $existing = Dashboard::factory()->create(['slug' => 'ventas-sucursal', 'title' => 'Viejo']);

        $this->actingAs($this->admin)
            ->postJson('/api/admin/dashboards', ['html' => $this->kitHtml()])
            ->assertStatus(409)
            ->assertJsonPath('existing_id', $existing->id)
            ->assertJsonFragment(['message' => 'Ya existe un dashboard con id «ventas-sucursal» (Viejo, versión 1.0.0). Para reemplazarlo usá la acción de actualizar sobre ese dashboard.']);
    }

    public function test_regular_user_cannot_publish(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/admin/dashboards', ['html' => $this->kitHtml()])
            ->assertForbidden();
    }

    public function test_preview_validates_without_saving_and_shows_diff(): void
    {
        $this->actingAs($this->admin)->postJson('/api/admin/dashboards', ['html' => $this->kitHtml()])->assertCreated();

        $manifest = json_decode(preg_replace('~.*<script type="application/json" id="dashboard-manifest">(.*?)</script>.*~s', '$1', $this->kitHtml()), true);
        $manifest['version'] = '1.1.0';
        $manifest['params'][0]['type'] = 'range';
        array_pop($manifest['params']);
        $html = $this->htmlWithManifest($manifest);

        $response = $this->actingAs($this->admin)->postJson('/api/admin/dashboards/preview', ['html' => $html]);

        $response->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('problems', [])
            ->assertJsonPath('manifest.version', '1.1.0')
            ->assertJsonPath('existing.slug', 'ventas-sucursal')
            ->assertJsonPath('diff.type_changed.0', ['id' => 'meta_ventas', 'from' => 'number', 'to' => 'range'])
            ->assertJsonPath('diff.removed.0.id', 'color_principal');

        $this->assertStringContainsString('cambia de tipo number a range', $response->json('diff.warnings.0'));
        $this->assertSame('1.0.0', Dashboard::query()->firstOrFail()->version);
    }

    public function test_preview_reports_problems_for_invalid_html(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/admin/dashboards/preview', ['html' => '<p>sin manifiesto</p>'])
            ->assertOk()
            ->assertJsonPath('valid', false)
            ->assertJsonPath('problems.0.rule', 1)
            ->assertJsonPath('manifest', null);
    }

    public function test_update_replaces_html_and_returns_diff(): void
    {
        $dashboard = Dashboard::factory()->create(['slug' => 'demo-completo', 'manifest' => $this->fullManifest(), 'version' => '1.0.0']);
        $manifest = $this->fullManifest(['version' => '2.0.0', 'title' => 'Demo v2']);
        $manifest['params'][] = ['id' => 'extra', 'label' => 'Extra', 'type' => 'boolean', 'default' => false];

        $response = $this->actingAs($this->admin)->putJson("/api/admin/dashboards/{$dashboard->id}", ['html' => $this->htmlWithManifest($manifest)]);

        $response->assertOk()
            ->assertJsonPath('data.version', '2.0.0')
            ->assertJsonPath('data.title', 'Demo v2')
            ->assertJsonPath('data.param_count', 8)
            ->assertJsonPath('diff.added.0.id', 'extra')
            ->assertJsonPath('diff.unchanged', 7);
    }

    public function test_update_rejects_html_with_a_different_manifest_id(): void
    {
        $dashboard = Dashboard::factory()->create(['slug' => 'otro-dashboard']);

        $this->actingAs($this->admin)
            ->putJson("/api/admin/dashboards/{$dashboard->id}", ['html' => $this->kitHtml()])
            ->assertUnprocessable()
            ->assertJsonPath('problems.0.path', 'id');

        $this->assertSame('otro-dashboard', $dashboard->fresh()->slug);
    }

    public function test_update_can_toggle_publication_without_new_html(): void
    {
        $dashboard = Dashboard::factory()->create(['is_published' => true]);

        $this->actingAs($this->admin)
            ->putJson("/api/admin/dashboards/{$dashboard->id}", ['is_published' => false])
            ->assertOk()
            ->assertJsonPath('data.is_published', false)
            ->assertJsonPath('diff', null);
    }

    public function test_update_without_changes_is_rejected(): void
    {
        $dashboard = Dashboard::factory()->create();

        $this->actingAs($this->admin)
            ->putJson("/api/admin/dashboards/{$dashboard->id}", [])
            ->assertUnprocessable();
    }

    public function test_delete_removes_dashboard(): void
    {
        $dashboard = Dashboard::factory()->create();

        $this->actingAs($this->admin)->deleteJson("/api/admin/dashboards/{$dashboard->id}")->assertNoContent();

        $this->assertDatabaseMissing('dashboards', ['id' => $dashboard->id]);
    }

    public function test_admin_list_includes_drafts(): void
    {
        Dashboard::factory()->create(['title' => 'Publicado']);
        Dashboard::factory()->unpublished()->create(['title' => 'Borrador']);

        $this->actingAs($this->admin)
            ->getJson('/api/admin/dashboards')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Borrador')
            ->assertJsonPath('data.0.is_published', false);
    }
}
