<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private const URL = '/api/auth/login';

    public function test_login_correcto_devuelve_token_y_responde_200(): void
    {
        $user = $this->superAdmin(['email' => 'admin@admin.com', 'password' => 'superadmin']);

        $response = $this->postJson(self::URL, ['email' => 'admin@admin.com', 'password' => 'superadmin']);

        $response->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in'])
            ->assertJsonPath('token_type', 'bearer');

        $this->resetAuth();
        $this->getJson('/api/auth/me', ['Authorization' => 'Bearer '.$response->json('access_token')])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_json_mal_formado_devuelve_400(): void
    {
        $this->call('POST', self::URL, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], '{"email": "admin@admin.com", ')
            ->assertStatus(400)
            ->assertJsonStructure(['message']);
    }

    public function test_password_incorrecta_devuelve_401(): void
    {
        $this->superAdmin(['email' => 'admin@admin.com', 'password' => 'superadmin']);

        $this->postJson(self::URL, ['email' => 'admin@admin.com', 'password' => 'incorrecta'])
            ->assertStatus(401)
            ->assertJsonPath('message', 'Credenciales inválidas.');
    }

    public function test_email_inexistente_devuelve_401_con_el_mismo_mensaje(): void
    {
        $this->postJson(self::URL, ['email' => 'nadie@admin.com', 'password' => 'superadmin'])
            ->assertStatus(401)
            ->assertJsonPath('message', 'Credenciales inválidas.');
    }

    public function test_campos_faltantes_devuelven_422_con_errores_por_campo(): void
    {
        $this->postJson(self::URL, [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_email_con_formato_invalido_devuelve_422(): void
    {
        $this->postJson(self::URL, ['email' => 'no-es-un-email', 'password' => 'x'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email'])
            ->assertJsonMissingValidationErrors(['password']);
    }
}
