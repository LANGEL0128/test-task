<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class MeTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private const URL = '/api/auth/me';

    public function test_me_devuelve_el_usuario_con_sus_roles_y_sin_password(): void
    {
        $user = $this->superAdmin();

        $this->getJson(self::URL, $this->bearer($user))
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.roles', ['super_admin'])
            ->assertJsonMissingPath('data.password');
    }

    public function test_me_de_usuario_sin_roles_devuelve_lista_vacia(): void
    {
        $this->getJson(self::URL, $this->bearer($this->normalUser()))
            ->assertOk()
            ->assertJsonPath('data.roles', []);
    }

    public function test_me_sin_token_devuelve_401(): void
    {
        $this->getJson(self::URL)->assertStatus(401);
    }

    public function test_me_con_token_invalido_devuelve_401(): void
    {
        $this->getJson(self::URL, ['Authorization' => 'Bearer token.invalido.xyz'])
            ->assertStatus(401);
    }

    public function test_me_con_token_expirado_devuelve_401(): void
    {
        $headers = $this->bearer($this->superAdmin());

        Carbon::setTestNow(now()->addMinutes(config('jwt.ttl') + 1));

        try {
            $this->getJson(self::URL, $headers)->assertStatus(401);
        } finally {
            Carbon::setTestNow();
        }
    }
}
