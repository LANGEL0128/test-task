<?php

namespace Tests\Unit;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_crea_el_super_admin_con_su_rol(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::where('email', 'admin@admin.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('superadmin', $user->password));
        $this->assertTrue($user->hasRole(RoleSeeder::SUPER_ADMIN));
    }

    public function test_ejecutar_el_seeder_dos_veces_no_duplica_el_usuario(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::where('email', 'admin@admin.com')->count());
        $this->assertSame(1, User::role(RoleSeeder::SUPER_ADMIN)->count());
    }
}
