<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotente: se puede ejecutar varias veces sin duplicar el usuario.
        $user = User::updateOrCreate(
            ['email' => 'admin@admin.com'],
            ['name' => 'Super Admin', 'password' => 'superadmin'],
        );

        $user->syncRoles([RoleSeeder::SUPER_ADMIN]);
    }
}
