<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private const URL = '/api/auth/logout';

    public function test_logout_con_token_valido_devuelve_200(): void
    {
        $user = $this->superAdmin();

        $this->postJson(self::URL, [], $this->bearer($user))
            ->assertOk()
            ->assertJsonPath('message', 'Sesión cerrada correctamente.');
    }

    public function test_logout_sin_token_devuelve_401(): void
    {
        $this->postJson(self::URL)->assertStatus(401);
    }

    public function test_logout_con_token_invalido_devuelve_401(): void
    {
        $this->postJson(self::URL, [], ['Authorization' => 'Bearer token.invalido.xyz'])
            ->assertStatus(401);
    }

    public function test_token_usado_tras_logout_devuelve_401(): void
    {
        $headers = $this->bearer($this->superAdmin());

        $this->postJson(self::URL, [], $headers)->assertOk();

        $this->resetAuth();
        $this->postJson(self::URL, [], $headers)->assertStatus(401);

        $this->resetAuth();
        $this->getJson('/api/auth/me', $headers)->assertStatus(401);
    }
}
