<?php

namespace Tests\Feature\Tasks;

use App\Models\Task;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class DestroyTaskTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_super_admin_borra_la_tarea_y_devuelve_204_sin_contenido(): void
    {
        $admin = $this->superAdmin();
        $task = Task::factory()->create();

        $response = $this->deleteJson('/api/tasks/'.$task->id, [], $this->bearer($admin));

        $response->assertNoContent();
        $this->assertSame('', $response->getContent());
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_id_no_numerico_devuelve_400(): void
    {
        $this->deleteJson('/api/tasks/abc', [], $this->bearer($this->superAdmin()))
            ->assertStatus(400);
    }

    public function test_sin_token_devuelve_401_y_la_tarea_sigue_existiendo(): void
    {
        $task = Task::factory()->create();

        $this->deleteJson('/api/tasks/'.$task->id)->assertStatus(401);

        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_con_token_invalido_devuelve_401(): void
    {
        $task = Task::factory()->create();

        $this->deleteJson('/api/tasks/'.$task->id, [], ['Authorization' => 'Bearer token.invalido.xyz'])
            ->assertStatus(401);

        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_usuario_sin_rol_super_admin_devuelve_403_y_la_tarea_sigue_existiendo(): void
    {
        $this->seed(RoleSeeder::class);
        $task = Task::factory()->create();

        $this->deleteJson('/api/tasks/'.$task->id, [], $this->bearer($this->normalUser()))
            ->assertForbidden();

        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_usuario_sin_rol_recibe_403_aunque_la_tarea_no_exista(): void
    {
        $this->seed(RoleSeeder::class);

        $this->deleteJson('/api/tasks/99999', [], $this->bearer($this->normalUser()))
            ->assertForbidden();

        $this->resetAuth();
        $this->deleteJson('/api/tasks/abc', [], $this->bearer($this->normalUser()))
            ->assertForbidden();
    }

    public function test_tarea_inexistente_devuelve_404(): void
    {
        $this->deleteJson('/api/tasks/99999', [], $this->bearer($this->superAdmin()))
            ->assertNotFound();
    }

    public function test_borrar_dos_veces_la_misma_tarea_devuelve_404_la_segunda(): void
    {
        $admin = $this->superAdmin();
        $task = Task::factory()->create();

        $this->deleteJson('/api/tasks/'.$task->id, [], $this->bearer($admin))->assertNoContent();

        $this->resetAuth();
        $this->deleteJson('/api/tasks/'.$task->id, [], $this->bearer($admin))->assertNotFound();
    }
}
