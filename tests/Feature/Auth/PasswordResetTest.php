<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_a_link_to_the_spa_route(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;

            return str_contains($url, '/reset-password/'.$notification->token)
                && str_contains($url, 'email='.urlencode($user->email));
        });
    }

    public function test_forgot_password_does_not_reveal_unknown_emails(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/forgot-password', ['email' => 'nadie@example.test'])
            ->assertOk()
            ->assertJsonPath('message', 'Si el correo está registrado, vas a recibir un enlace para restablecer la contraseña.');

        Notification::assertNothingSent();
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $this->postJson('/api/auth/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'nueva-clave-123',
                'password_confirmation' => 'nueva-clave-123',
            ])->assertOk()->assertJsonPath('message', 'La contraseña fue restablecida.');

            $this->assertTrue(Hash::check('nueva-clave-123', $user->fresh()->password));

            return true;
        });
    }

    public function test_reset_rejects_an_invalid_token(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/auth/reset-password', [
            'token' => 'token-inventado',
            'email' => $user->email,
            'password' => 'nueva-clave-123',
            'password_confirmation' => 'nueva-clave-123',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'El enlace de restablecimiento no es válido o ya venció. Pedí uno nuevo.');
    }
}
