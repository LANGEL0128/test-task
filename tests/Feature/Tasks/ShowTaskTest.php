<?php

namespace Tests\Feature\Tasks;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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

    public function test_los_ids_de_tarea_y_usuario_son_uuid(): void
    {
        $task = Task::factory()->create();

        $this->assertTrue(Str::isUuid($task->id));

        $this->getJson('/api/tasks/'.$task->id)
            ->assertOk()
            ->assertJsonPath('data.id', $task->id)
            ->assertJsonPath('data.assignee.id', $task->assigned_to);

        $this->assertTrue(Str::isUuid($task->assigned_to));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('idsInvalidos')]
    public function test_id_que_no_es_uuid_devuelve_400(string $id): void
    {
        $this->getJson('/api/tasks/'.$id)
            ->assertStatus(400)
            ->assertJsonPath('message', 'El identificador de la tarea debe ser un UUID válido.');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function idsInvalidos(): array
    {
        return [
            'texto' => ['abc'],
            'numerico (ya no se acepta)' => ['1'],
            'uuid incompleto' => ['0199a1b2-3c4d-7e5f-8a9b'],
        ];
    }

    public function test_un_id_numerico_existente_en_otros_sistemas_no_da_acceso(): void
    {
        $task = Task::factory()->create();

        $this->getJson('/api/tasks/1')->assertStatus(400);
        $this->getJson('/api/tasks/'.$task->id)->assertOk();
    }

    public function test_tarea_inexistente_devuelve_404_en_json(): void
    {
        $this->getJson('/api/tasks/'.(string) Str::uuid())
            ->assertNotFound()
            ->assertJsonPath('message', 'Recurso no encontrado.');
    }
}
