<?php

namespace Tests\Feature\Admin;

use App\Models\Dashboard;
use App\Models\Division;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsDashboardHtml;
use Tests\TestCase;

class DashboardAssignmentTest extends TestCase
{
    use BuildsDashboardHtml, RefreshDatabase;

    private User $admin;

    private Division $division;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->superAdmin()->create();
        $this->division = Division::factory()->create(['name' => 'Comercial']);
        $this->group = Group::factory()->create(['division_id' => $this->division->id, 'name' => 'Sucursales']);
    }

    public function test_store_accepts_assignment_and_icon(): void
    {
        $this->actingAs($this->admin)->postJson('/api/admin/dashboards', [
            'html' => $this->kitHtml(), 'icon' => 'truck', 'visible_to_all' => false,
            'division_ids' => [$this->division->id], 'group_ids' => [$this->group->id],
        ])
            ->assertCreated()
            ->assertJsonPath('data.icon', 'truck')
            ->assertJsonPath('data.visible_to_all', false)
            ->assertJsonPath('data.divisions.0.name', 'Comercial')
            ->assertJsonPath('data.groups.0.division_name', 'Comercial');
    }

    public function test_new_dashboards_are_not_visible_to_all_by_default(): void
    {
        $id = $this->actingAs($this->admin)->postJson('/api/admin/dashboards', ['html' => $this->kitHtml()])->assertCreated()->json('data.id');

        $this->assertFalse(Dashboard::findOrFail($id)->visible_to_all);
        $this->actingAs(User::factory()->create())->getJson('/api/dashboards')->assertJsonCount(0, 'data');
    }

    public function test_assignment_can_be_updated_without_uploading_html(): void
    {
        $dashboard = Dashboard::factory()->restricted()->create();

        $this->actingAs($this->admin)->putJson("/api/admin/dashboards/{$dashboard->id}", ['division_ids' => [$this->division->id], 'icon' => 'chart-bar'])
            ->assertOk()->assertJsonPath('data.divisions.0.id', $this->division->id)->assertJsonPath('data.icon', 'chart-bar')->assertJsonPath('diff', null);

        $this->actingAs($this->admin)->putJson("/api/admin/dashboards/{$dashboard->id}", ['visible_to_all' => true, 'division_ids' => [], 'icon' => null])
            ->assertOk()->assertJsonPath('data.visible_to_all', true)->assertJsonPath('data.divisions', [])->assertJsonPath('data.icon', null);

        $this->actingAs($this->admin)->putJson("/api/admin/dashboards/{$dashboard->id}", [])->assertUnprocessable();
        $this->actingAs($this->admin)->putJson("/api/admin/dashboards/{$dashboard->id}", ['icon' => 'unicorn'])
            ->assertUnprocessable()->assertJsonPath('errors.icon.0', 'El ícono elegido no está en el catálogo.');
    }

    public function test_admin_index_includes_assignment(): void
    {
        $dashboard = Dashboard::factory()->restricted()->create();
        $dashboard->groups()->sync([$this->group->id]);

        $this->actingAs($this->admin)->getJson('/api/admin/dashboards')
            ->assertOk()->assertJsonPath('data.0.groups.0.name', 'Sucursales')->assertJsonPath('data.0.visible_to_all', false);
    }
}
