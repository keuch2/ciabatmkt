<?php

namespace Tests\Feature\Admin;

use App\Models\Dashboard;
use App\Models\Division;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DivisionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->superAdmin()->create();
    }

    public function test_crud_of_divisions_and_groups(): void
    {
        $division = $this->actingAs($this->admin)->postJson('/api/admin/divisions', ['name' => 'Comercial'])
            ->assertCreated()->assertJsonPath('data.name', 'Comercial')->assertJsonPath('data.groups', [])->json('data');

        $group = $this->actingAs($this->admin)->postJson("/api/admin/divisions/{$division['id']}/groups", ['name' => 'Sucursales'])
            ->assertCreated()->assertJsonPath('data.division_id', $division['id'])->json('data');

        $this->actingAs($this->admin)->putJson("/api/admin/divisions/{$division['id']}", ['name' => 'Comercial y Marketing', 'sort_order' => 3])
            ->assertOk()->assertJsonPath('data.name', 'Comercial y Marketing')->assertJsonPath('data.sort_order', 3);
        $this->actingAs($this->admin)->putJson("/api/admin/divisions/{$division['id']}/groups/{$group['id']}", ['name' => 'Sucursales del interior'])
            ->assertOk()->assertJsonPath('data.name', 'Sucursales del interior');

        $user = User::factory()->create();
        $user->syncMemberships([$division['id']], [$group['id']]);

        $this->actingAs($this->admin)->getJson('/api/admin/divisions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.users_count', 1)
            ->assertJsonPath('data.0.dashboards_count', 0)
            ->assertJsonPath('data.0.groups.0.users_count', 1);

        $this->actingAs($this->admin)->deleteJson("/api/admin/divisions/{$division['id']}/groups/{$group['id']}")->assertNoContent();
        $this->assertDatabaseMissing('group_user', ['user_id' => $user->id]);
        $this->actingAs($this->admin)->deleteJson("/api/admin/divisions/{$division['id']}")->assertNoContent();
        $this->assertDatabaseMissing('division_user', ['user_id' => $user->id]);
        $this->assertDatabaseCount('divisions', 0);
    }

    public function test_validation_messages(): void
    {
        $division = Division::factory()->create(['name' => 'Comercial']);
        Group::factory()->create(['division_id' => $division->id, 'name' => 'Sucursales']);

        $this->actingAs($this->admin)->postJson('/api/admin/divisions', ['name' => ''])
            ->assertUnprocessable()->assertJsonPath('errors.name.0', 'Ingresá el nombre de la división.');
        $this->actingAs($this->admin)->postJson('/api/admin/divisions', ['name' => 'Comercial'])
            ->assertUnprocessable()->assertJsonPath('errors.name.0', 'Ya existe una división con ese nombre.');
        $this->actingAs($this->admin)->postJson("/api/admin/divisions/{$division->id}/groups", ['name' => 'Sucursales'])
            ->assertUnprocessable()->assertJsonPath('errors.name.0', 'Ya existe un grupo con ese nombre en esta división.');
    }

    public function test_group_routes_are_scoped_to_their_division(): void
    {
        $a = Division::factory()->create();
        $b = Division::factory()->create();
        $group = Group::factory()->create(['division_id' => $a->id]);

        $this->actingAs($this->admin)->putJson("/api/admin/divisions/{$b->id}/groups/{$group->id}", ['name' => 'x'])->assertNotFound();
        $this->actingAs($this->admin)->deleteJson("/api/admin/divisions/{$b->id}/groups/{$group->id}")->assertNotFound();
    }

    public function test_deletion_is_blocked_while_dashboards_are_assigned(): void
    {
        $division = Division::factory()->create(['name' => 'Comercial']);
        $group = Group::factory()->create(['division_id' => $division->id, 'name' => 'Sucursales']);
        $dashboard = Dashboard::factory()->restricted()->create();
        $dashboard->groups()->sync([$group->id]);

        $this->actingAs($this->admin)->deleteJson("/api/admin/divisions/{$division->id}")
            ->assertUnprocessable()
            ->assertJsonPath('errors.division.0', 'La división «Comercial» tiene 1 dashboard(s) asignado(s), directamente o en sus grupos. Reasignalos antes de eliminarla.');
        $this->actingAs($this->admin)->deleteJson("/api/admin/divisions/{$division->id}/groups/{$group->id}")
            ->assertUnprocessable()
            ->assertJsonPath('errors.group.0', 'El grupo «Sucursales» tiene 1 dashboard(s) asignado(s). Reasignalos antes de eliminarlo.');

        $dashboard->groups()->sync([]);
        $this->actingAs($this->admin)->deleteJson("/api/admin/divisions/{$division->id}")->assertNoContent();
        $this->assertDatabaseCount('groups', 0);
    }

    public function test_regular_user_cannot_manage_divisions(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/admin/divisions')->assertForbidden();
        $this->actingAs(User::factory()->create())->postJson('/api/admin/divisions', ['name' => 'X'])->assertForbidden();
    }
}
