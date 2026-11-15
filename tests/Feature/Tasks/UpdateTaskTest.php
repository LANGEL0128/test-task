<?php

namespace Tests\Feature\Tasks;

use App\Models\Task;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\Concerns\SendsRawJson;
use Tests\TestCase;

class UpdateTaskTest extends TestCase
{
    use CreatesUsers, RefreshDatabase, SendsRawJson;

    public function test_super_admin_edita_con_put_y_devuelve_200(): void
    {
        $admin = $this->superAdmin();
        $nuevo = $this->normalUser();
        $task = Task::factory()->create(['status' => 'pendiente']);

        $this->putJson('/api/tasks/'.$task->id, [
            'title' => 'Titulo editado',
            'description' => 'Nueva descripcion',
            'status' => 'hecha',
            'assigned_to' => $nuevo->id,
            'due_date' => '2027-01-15',
        ], $this->bearer($admin))
            ->assertOk()
            ->assertJsonPath('data.title', 'Titulo editado')
            ->assertJsonPath('data.status', 'hecha')
            ->assertJsonPath('data.assignee.id', $nuevo->id);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Titulo editado',
            'status' => 'hecha',
            'assigned_to' => $nuevo->id,
        ]);
    }

    public function test_super_admin_edita_parcialmente_con_patch_y_no_toca_el_resto(): void
    {
        $admin = $this->superAdmin();
        $task = Task::factory()->create(['title' => 'Original', 'status' => 'pendiente']);

        $this->patchJson('/api/tasks/'.$task->id, ['status' => 'en_curso'], $this->bearer($admin))
            ->assertOk()
            ->assertJsonPath('data.status', 'en_curso')
            ->assertJsonPath('data.title', 'Original');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Original', 'status' => 'en_curso']);
    }

    public function test_se_puede_quitar_el_responsable_y_la_fecha_con_null(): void
    {
        $task = Task::factory()->create();

        $this->patchJson('/api/tasks/'.$task->id, ['assigned_to' => null, 'due_date' => null], $this->bearer($this->superAdmin()))
            ->assertOk()
            ->assertJsonPath('data.assigned_to', null)
            ->assertJsonPath('data.due_date', null);
    }

    public function test_id_no_numerico_devuelve_400(): void
    {
        $this->putJson('/api/tasks/abc', ['title' => 'X'], $this->bearer($this->superAdmin()))
            ->assertStatus(400);
    }

    public function test_json_mal_formado_devuelve_400(): void
    {
        $task = Task::factory()->create(['title' => 'Original']);

        $this->sendMalformedJson('PUT', '/api/tasks/'.$task->id, $this->bearer($this->superAdmin()))
            ->assertStatus(400);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Original']);
    }

    public function test_sin_token_devuelve_401(): void
    {
        $task = Task::factory()->create(['title' => 'Original']);

        $this->putJson('/api/tasks/'.$task->id, ['title' => 'Hackeada'])->assertStatus(401);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Original']);
    }

    public function test_con_token_invalido_devuelve_401(): void
    {
        $task = Task::factory()->create();

        $this->putJson('/api/tasks/'.$task->id, ['title' => 'X'], ['Authorization' => 'Bearer token.invalido.xyz'])
            ->assertStatus(401);
    }

    public function test_usuario_sin_rol_super_admin_devuelve_403_y_la_tarea_no_cambia(): void
    {
        $this->seed(RoleSeeder::class);
        $task = Task::factory()->create(['title' => 'Original']);

        $this->putJson('/api/tasks/'.$task->id, ['title' => 'Hackeada'], $this->bearer($this->normalUser()))
            ->assertForbidden();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Original']);
    }

    public function test_tarea_inexistente_devuelve_404(): void
    {
        $this->putJson('/api/tasks/99999', ['title' => 'X'], $this->bearer($this->superAdmin()))
            ->assertNotFound()
            ->assertJsonPath('message', 'Recurso no encontrado.');
    }

    public function test_datos_invalidos_devuelven_422(): void
    {
        $admin = $this->superAdmin();
        $task = Task::factory()->create(['title' => 'Original', 'status' => 'pendiente']);

        $this->putJson('/api/tasks/'.$task->id, [
            'title' => '',
            'status' => 'terminada',
            'assigned_to' => 99999,
            'due_date' => 'mañana',
        ], $this->bearer($admin))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'status', 'assigned_to', 'due_date']);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Original', 'status' => 'pendiente']);
    }
}
