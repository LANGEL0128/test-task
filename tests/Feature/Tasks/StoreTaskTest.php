<?php

namespace Tests\Feature\Tasks;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesUsers;
use Tests\Concerns\SendsRawJson;
use Tests\TestCase;

class StoreTaskTest extends TestCase
{
    use CreatesUsers, RefreshDatabase, SendsRawJson;

    private const URL = '/api/tasks';

    public function test_super_admin_crea_una_tarea_valida_y_devuelve_201(): void
    {
        $admin = $this->superAdmin();
        $responsable = $this->normalUser();

        $this->postJson(self::URL, [
            'title' => 'Nueva tarea',
            'description' => 'Detalle',
            'status' => 'en_curso',
            'assigned_to' => $responsable->id,
            'due_date' => '2026-12-31',
        ], $this->bearer($admin))
            ->assertCreated()
            ->assertJsonPath('data.title', 'Nueva tarea')
            ->assertJsonPath('data.status', 'en_curso')
            ->assertJsonPath('data.assignee.id', $responsable->id);

        $this->assertDatabaseHas('tasks', [
            'title' => 'Nueva tarea',
            'status' => 'en_curso',
            'assigned_to' => $responsable->id,
        ]);
    }

    public function test_el_estado_por_defecto_es_pendiente(): void
    {
        $this->postJson(self::URL, ['title' => 'Solo titulo'], $this->bearer($this->superAdmin()))
            ->assertCreated()
            ->assertJsonPath('data.status', 'pendiente');

        $this->assertDatabaseHas('tasks', ['title' => 'Solo titulo', 'status' => 'pendiente']);
    }

    public function test_json_mal_formado_devuelve_400(): void
    {
        $this->sendMalformedJson('POST', self::URL, $this->bearer($this->superAdmin()))
            ->assertStatus(400)
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_sin_token_devuelve_401(): void
    {
        $this->postJson(self::URL, ['title' => 'X'])
            ->assertStatus(401)
            ->assertJsonPath('message', 'No autenticado.');

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_con_token_invalido_devuelve_401(): void
    {
        $this->postJson(self::URL, ['title' => 'X'], ['Authorization' => 'Bearer token.invalido.xyz'])
            ->assertStatus(401);
    }

    public function test_usuario_sin_rol_super_admin_devuelve_403_y_no_crea_nada(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->postJson(self::URL, ['title' => 'X'], $this->bearer($this->normalUser()))
            ->assertForbidden()
            ->assertJsonPath('message', 'No tienes permiso para realizar esta acción.');

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_sin_title_devuelve_422(): void
    {
        $this->postJson(self::URL, ['description' => 'sin titulo'], $this->bearer($this->superAdmin()))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_title_mayor_a_255_caracteres_devuelve_422(): void
    {
        $this->postJson(self::URL, ['title' => str_repeat('a', 256)], $this->bearer($this->superAdmin()))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_campos_invalidos_devuelven_422_con_errores_por_campo(): void
    {
        $this->postJson(self::URL, [
            'title' => 'Ok',
            'status' => 'terminada',
            'assigned_to' => (string) Str::uuid(),
            'due_date' => '31/12/2026',
        ], $this->bearer($this->superAdmin()))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status', 'assigned_to', 'due_date'])
            ->assertJsonMissingValidationErrors(['title']);

        $this->assertSame(0, Task::count());
    }

    public function test_assigned_to_que_no_es_uuid_devuelve_422(): void
    {
        $this->postJson(self::URL, ['title' => 'Ok', 'assigned_to' => '1'], $this->bearer($this->superAdmin()))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['assigned_to']);
    }

    public function test_due_date_con_fecha_inexistente_devuelve_422(): void
    {
        $this->postJson(self::URL, ['title' => 'Ok', 'due_date' => '2026-02-31'], $this->bearer($this->superAdmin()))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['due_date']);
    }
}
