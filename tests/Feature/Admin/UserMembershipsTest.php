<?php

namespace Tests\Feature\Admin;

use App\Models\Division;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserMembershipsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Division $comercial;

    private Division $finanzas;

    private Group $sucursales;

    private Group $tesoreria;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->superAdmin()->create();
        $this->comercial = Division::factory()->create(['name' => 'Comercial']);
        $this->finanzas = Division::factory()->create(['name' => 'Finanzas']);
        $this->sucursales = Group::factory()->create(['division_id' => $this->comercial->id, 'name' => 'Sucursales']);
        $this->tesoreria = Group::factory()->create(['division_id' => $this->finanzas->id, 'name' => 'Tesorería']);
    }

    public function test_user_can_belong_to_several_divisions_and_their_groups(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/admin/users', [
            'name' => 'Carla', 'email' => 'carla@example.test', 'password' => 'clave-segura', 'role' => 'user',
            'division_ids' => [$this->comercial->id, $this->finanzas->id],
            'group_ids' => [$this->sucursales->id, $this->tesoreria->id],
        ]);

        $response->assertCreated()
            ->assertJsonCount(2, 'data.divisions')
            ->assertJsonCount(2, 'data.groups')
            ->assertJsonPath('data.groups.0.division_id', $this->comercial->id);

        $carla = collect($this->actingAs($this->admin)->getJson('/api/admin/users')->json('data'))->firstWhere('email', 'carla@example.test');
        $this->assertSame('Comercial', $carla['divisions'][0]['name']);
    }

    public function test_group_of_an_unassigned_division_is_rejected(): void
    {
        $this->actingAs($this->admin)->postJson('/api/admin/users', [
            'name' => 'Carla', 'email' => 'carla@example.test', 'password' => 'clave-segura', 'role' => 'user',
            'division_ids' => [$this->comercial->id],
            'group_ids' => [$this->tesoreria->id],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.group_ids.0', 'El grupo «Tesorería» pertenece a la división «Finanzas», que no está asignada al usuario.');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_removing_a_division_removes_its_groups_and_group_only_updates_validate_against_current_divisions(): void
    {
        $user = User::factory()->create();
        $user->syncMemberships([$this->comercial->id, $this->finanzas->id], [$this->sucursales->id, $this->tesoreria->id]);

        // Sólo group_ids: se valida contra las divisiones actuales.
        $this->actingAs($this->admin)->putJson("/api/admin/users/{$user->id}", ['group_ids' => [$this->sucursales->id]])
            ->assertOk()->assertJsonCount(1, 'data.groups');

        // Quitar Finanzas descarta Tesorería aunque se envíe.
        $this->actingAs($this->admin)->putJson("/api/admin/users/{$user->id}", ['division_ids' => [$this->comercial->id], 'group_ids' => [$this->sucursales->id, $this->tesoreria->id]])
            ->assertUnprocessable();
        $this->actingAs($this->admin)->putJson("/api/admin/users/{$user->id}", ['division_ids' => [$this->comercial->id], 'group_ids' => [$this->sucursales->id]])
            ->assertOk()->assertJsonCount(1, 'data.divisions')->assertJsonCount(1, 'data.groups');
        $this->assertDatabaseMissing('group_user', ['user_id' => $user->id, 'group_id' => $this->tesoreria->id]);

        // Un update sin membresías no las toca.
        $this->actingAs($this->admin)->putJson("/api/admin/users/{$user->id}", ['name' => 'Otro nombre'])
            ->assertOk()->assertJsonCount(1, 'data.divisions');
    }

    public function test_me_includes_memberships(): void
    {
        $user = User::factory()->create();
        $user->syncMemberships([$this->comercial->id], [$this->sucursales->id]);

        $this->actingAs($user)->getJson('/api/auth/me')
            ->assertOk()->assertJsonPath('data.divisions.0.name', 'Comercial')->assertJsonPath('data.groups.0.name', 'Sucursales');
    }
}
