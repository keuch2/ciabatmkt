<?php

namespace Tests\Feature\Admin;

use App\Models\Dashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsDashboardHtml;
use Tests\TestCase;

class AdminHistoryTest extends TestCase
{
    use BuildsDashboardHtml, RefreshDatabase;

    private Dashboard $dashboard;

    private User $admin;

    private User $ana;

    private User $bruno;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dashboard = Dashboard::factory()->create(['manifest' => $this->fullManifest()]);
        $this->admin = User::factory()->superAdmin()->create();
        $this->ana = User::factory()->create();
        $this->bruno = User::factory()->create();
        $url = "/api/dashboards/{$this->dashboard->id}";

        $this->actingAs($this->admin)->putJson("{$url}/params/meta", ['value' => 500, 'scope' => 'base']);
        $this->actingAs($this->ana)->putJson("{$url}/params/meta", ['value' => 1]);
        $this->actingAs($this->ana)->putJson("{$url}/params/titulo", ['value' => 'a']);
        $this->actingAs($this->bruno)->putJson("{$url}/params/meta", ['value' => 2]);
        $this->actingAs($this->bruno)->deleteJson("{$url}/params/meta");
    }

    private function history(string $query = ''): array
    {
        return $this->actingAs($this->admin)->getJson("/api/admin/dashboards/{$this->dashboard->id}/history{$query}")->assertOk()->json();
    }

    public function test_full_history_includes_every_user_and_base_changes(): void
    {
        $result = $this->history();

        $this->assertSame(5, $result['meta']['total']);
        $this->assertSame('delete', $result['data'][0]['action']);
        $this->assertSame($this->bruno->id, $result['data'][0]['changed_by']['id']);
        $this->assertSame('base', $result['data'][4]['scope']);
        $this->assertNull($result['data'][4]['user']);
        $this->assertSame($this->admin->id, $result['data'][4]['changed_by']['id']);
    }

    public function test_filters_by_param_user_scope_and_action(): void
    {
        $this->assertSame(4, $this->history('?param_id=meta')['meta']['total']);
        $this->assertSame(2, $this->history("?user_id={$this->ana->id}")['meta']['total']);
        $this->assertSame(1, $this->history('?scope=base')['meta']['total']);
        $this->assertSame(4, $this->history('?scope=user')['meta']['total']);
        $this->assertSame(1, $this->history('?action=delete')['meta']['total']);
        $this->assertSame(0, $this->history('?from=2099-01-01')['meta']['total']);
        $this->assertSame(5, $this->history('?from=2000-01-01&to='.date('Y-m-d'))['meta']['total']);
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->actingAs($this->admin)
            ->getJson("/api/admin/dashboards/{$this->dashboard->id}/history?scope=todos")
            ->assertUnprocessable()
            ->assertJsonPath('errors.scope.0', 'El filtro "scope" debe ser "user" o "base".');
    }

    public function test_regular_user_cannot_read_full_history(): void
    {
        $this->actingAs($this->ana)->getJson("/api/admin/dashboards/{$this->dashboard->id}/history")->assertForbidden();
    }
}
