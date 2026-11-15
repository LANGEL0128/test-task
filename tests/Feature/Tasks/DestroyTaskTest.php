<?php

namespace Tests\Feature\Tasks;

use App\Models\Task;
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
}
