<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_gets_401_on_admin_routes(): void
    {
        $this->getJson('/api/admin/users')->assertUnauthorized();
    }

    public function test_regular_user_gets_403_on_admin_routes(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/admin/users')
            ->assertForbidden()
            ->assertJsonPath('message', 'Esta acción requiere el rol de super administrador.');
    }

    public function test_super_admin_can_use_admin_routes(): void
    {
        $admin = User::factory()->superAdmin()->create();
        User::factory()->count(2)->create();

        $this->actingAs($admin)
            ->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_deactivated_user_is_cut_off_even_with_a_live_session(): void
    {
        $user = User::factory()->superAdmin()->inactive()->create();

        $this->actingAs($user)
            ->getJson('/api/admin/users')
            ->assertForbidden()
            ->assertJsonPath('message', 'Tu cuenta está desactivada. Contactá al administrador.');
    }
}
