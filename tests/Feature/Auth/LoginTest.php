<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.test']);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'ana@example.test',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.role', 'user')
            ->assertJsonMissingPath('data.password');

        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_login_rejects_wrong_password_with_readable_message(): void
    {
        User::factory()->create(['email' => 'ana@example.test']);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'ana@example.test',
            'password' => 'incorrecta',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'El correo o la contraseña no son correctos.');

        $this->assertGuest('web');
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Ingresá tu correo electrónico.')
            ->assertJsonPath('errors.password.0', 'Ingresá tu contraseña.');
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->inactive()->create(['email' => 'baja@example.test']);

        $this->postJson('/api/auth/login', [
            'email' => 'baja@example.test',
            'password' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Tu cuenta está desactivada. Contactá al administrador.');

        $this->assertGuest('web');
    }

    public function test_me_returns_the_authenticated_user(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.role', 'super_admin');
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Necesitás iniciar sesión para usar este recurso.');
    }

    public function test_logout_ends_the_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/auth/logout')->assertNoContent();

        $this->assertGuest('web');
    }
}
