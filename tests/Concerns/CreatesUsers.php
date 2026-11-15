<?php

namespace Tests\Concerns;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

trait CreatesUsers
{
    protected function superAdmin(array $attributes = []): User
    {
        $this->seed(RoleSeeder::class);

        return User::factory()->create($attributes)->assignRole(RoleSeeder::SUPER_ADMIN);
    }

    protected function normalUser(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    protected function tokenFor(User $user): string
    {
        return JWTAuth::fromUser($user);
    }

    /**
     * Cabeceras con el token Bearer del usuario.
     *
     * @return array<string, string>
     */
    protected function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.$this->tokenFor($user)];
    }

    /**
     * Simula una petición "limpia": en producción cada request arranca la app de cero,
     * pero dentro de un test el guard y el JWT conservan el token de la petición anterior.
     */
    protected function resetAuth(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app->forgetInstance('tymon.jwt');
        $this->app->forgetInstance('tymon.jwt.auth');
    }
}
