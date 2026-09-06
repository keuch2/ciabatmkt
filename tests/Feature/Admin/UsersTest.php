<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->superAdmin()->create();
    }

    public function test_admin_can_create_a_user(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/admin/users', ['name' => 'Carla', 'email' => 'carla@example.test', 'password' => 'clave-segura', 'role' => 'user'])
            ->assertCreated()
            ->assertJsonPath('data.email', 'carla@example.test')
            ->assertJsonPath('data.role', 'user')
            ->assertJsonPath('data.is_active', true);

        $user = User::query()->where('email', 'carla@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('clave-segura', $user->password));

        $this->postJson('/api/auth/logout');
        $this->postJson('/api/auth/login', ['email' => 'carla@example.test', 'password' => 'clave-segura'])->assertOk();
    }

    public function test_creation_validates_fields_with_readable_messages(): void
    {
        User::factory()->create(['email' => 'dup@example.test']);

        $this->actingAs($this->admin)
            ->postJson('/api/admin/users', ['name' => '', 'email' => 'dup@example.test', 'password' => '123', 'role' => 'jefe'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'Ingresá el nombre del usuario.')
            ->assertJsonPath('errors.email.0', 'Ya existe un usuario con ese correo.')
            ->assertJsonPath('errors.password.0', 'La contraseña debe tener al menos 8 caracteres.')
            ->assertJsonPath('errors.role.0', 'El rol debe ser "super_admin" o "user".');
    }

    public function test_admin_can_update_role_status_and_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)
            ->putJson("/api/admin/users/{$user->id}", ['role' => 'super_admin', 'is_active' => false, 'password' => 'otra-clave-123', 'name' => 'Nuevo Nombre'])
            ->assertOk()
            ->assertJsonPath('data.role', 'super_admin')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.name', 'Nuevo Nombre');

        $this->assertTrue(Hash::check('otra-clave-123', $user->fresh()->password));
    }

    public function test_empty_password_on_update_keeps_the_current_one(): void
    {
        $user = User::factory()->create();
        $hash = $user->password;

        $this->actingAs($this->admin)->putJson("/api/admin/users/{$user->id}", ['password' => '', 'name' => 'X'])->assertOk();

        $this->assertSame($hash, $user->fresh()->password);
    }

    public function test_admin_cannot_deactivate_or_demote_self(): void
    {
        $this->actingAs($this->admin)
            ->putJson("/api/admin/users/{$this->admin->id}", ['is_active' => false])
            ->assertUnprocessable()
            ->assertJsonPath('errors.is_active.0', 'No podés desactivar tu propia cuenta.');

        $this->actingAs($this->admin)
            ->putJson("/api/admin/users/{$this->admin->id}", ['role' => 'user'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.role.0', 'No podés quitarte el rol de super administrador a vos mismo.');
    }

    public function test_deactivated_user_cannot_login_anymore(): void
    {
        $user = User::factory()->create(['email' => 'baja@example.test']);
        $this->actingAs($this->admin)->putJson("/api/admin/users/{$user->id}", ['is_active' => false])->assertOk();

        $this->postJson('/api/auth/logout');
        $this->postJson('/api/auth/login', ['email' => 'baja@example.test', 'password' => 'password'])->assertUnprocessable();
    }

    public function test_regular_user_cannot_manage_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/admin/users', [])->assertForbidden();
        $this->actingAs($user)->putJson("/api/admin/users/{$user->id}", ['role' => 'super_admin'])->assertForbidden();
    }
}
