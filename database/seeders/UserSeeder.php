<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Cuentas de prueba para desarrollo. Contraseña de todas: "ciabay2026".
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['name' => 'Administrador Ciabay', 'email' => 'admin@ciabay.local', 'role' => UserRole::SuperAdmin],
            ['name' => 'Ana Prueba', 'email' => 'ana@ciabay.local', 'role' => UserRole::User],
            ['name' => 'Bruno Prueba', 'email' => 'bruno@ciabay.local', 'role' => UserRole::User],
        ];

        foreach ($accounts as $account) {
            User::query()->updateOrCreate(
                ['email' => $account['email']],
                $account + ['password' => 'ciabay2026', 'is_active' => true, 'email_verified_at' => now()],
            );
        }
    }
}
