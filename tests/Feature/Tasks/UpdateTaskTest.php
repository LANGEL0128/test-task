<?php

namespace Tests\Feature\Tasks;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class UpdateTaskTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

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
}
