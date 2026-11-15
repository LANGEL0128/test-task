<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class RefreshTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private const URL = '/api/auth/refresh';

    public function test_refresh_devuelve_un_token_nuevo_que_funciona(): void
    {
        $user = $this->superAdmin();
        $oldToken = $this->tokenFor($user);

        $response = $this->postJson(self::URL, [], ['Authorization' => 'Bearer '.$oldToken]);

        $response->assertOk()->assertJsonStructure(['access_token', 'token_type', 'expires_in']);
        $newToken = $response->json('access_token');
        $this->assertNotSame($oldToken, $newToken);

        $this->resetAuth();
        $this->getJson('/api/auth/me', ['Authorization' => 'Bearer '.$newToken])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_refresh_sin_token_devuelve_401(): void
    {
        $this->postJson(self::URL)->assertStatus(401);
    }

    public function test_refresh_con_token_invalido_devuelve_401(): void
    {
        $this->postJson(self::URL, [], ['Authorization' => 'Bearer token.invalido.xyz'])
            ->assertStatus(401);
    }

    public function test_el_token_anterior_queda_invalidado_tras_el_refresh(): void
    {
        $oldToken = $this->tokenFor($this->superAdmin());

        $this->postJson(self::URL, [], ['Authorization' => 'Bearer '.$oldToken])->assertOk();

        $this->resetAuth();
        $this->getJson('/api/auth/me', ['Authorization' => 'Bearer '.$oldToken])->assertStatus(401);
    }
}
