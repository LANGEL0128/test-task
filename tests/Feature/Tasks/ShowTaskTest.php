<?php

namespace Tests\Feature\Tasks;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class ShowTaskTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_ver_tarea_sin_token_devuelve_200_con_todos_los_campos(): void
    {
        $user = $this->normalUser(['name' => 'Ana']);
        $task = Task::factory()->create([
            'title' => 'Preparar informe',
            'description' => 'Informe mensual',
            'status' => 'en_curso',
            'assigned_to' => $user->id,
            'due_date' => '2026-12-31',
        ]);

        $this->getJson('/api/tasks/'.$task->id)
            ->assertOk()
            ->assertJsonPath('data.id', $task->id)
            ->assertJsonPath('data.title', 'Preparar informe')
            ->assertJsonPath('data.description', 'Informe mensual')
            ->assertJsonPath('data.status', 'en_curso')
            ->assertJsonPath('data.due_date', '2026-12-31')
            ->assertJsonPath('data.assignee.id', $user->id)
            ->assertJsonPath('data.assignee.name', 'Ana');
    }

    public function test_id_no_numerico_devuelve_400(): void
    {
        $this->getJson('/api/tasks/abc')
            ->assertStatus(400)
            ->assertJsonPath('message', 'El identificador de la tarea debe ser numérico.');
    }

    public function test_tarea_inexistente_devuelve_404_en_json(): void
    {
        $this->getJson('/api/tasks/99999')
            ->assertNotFound()
            ->assertJsonPath('message', 'Recurso no encontrado.');
    }
}
