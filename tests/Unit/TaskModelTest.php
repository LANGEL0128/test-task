<?php

namespace Tests\Unit;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TaskModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_scope_status_filtra_por_estado(): void
    {
        Task::factory()->create(['status' => TaskStatus::Hecha]);
        Task::factory()->count(2)->create(['status' => TaskStatus::Pendiente]);

        $this->assertSame(1, Task::status(TaskStatus::Hecha)->count());
        $this->assertSame(2, Task::status('pendiente')->count());
    }

    public function test_scope_assigned_to_filtra_por_responsable(): void
    {
        $user = User::factory()->create();
        Task::factory()->count(2)->create(['assigned_to' => $user->id]);
        Task::factory()->create();

        $this->assertSame(2, Task::assignedTo($user->id)->count());
    }

    public function test_casts_de_status_y_due_date(): void
    {
        $task = Task::factory()->create(['status' => 'en_curso', 'due_date' => '2026-12-31'])->fresh();

        $this->assertSame(TaskStatus::EnCurso, $task->status);
        $this->assertInstanceOf(Carbon::class, $task->due_date);
        $this->assertSame('2026-12-31', $task->due_date->toDateString());
    }

    public function test_al_borrar_el_responsable_la_tarea_queda_sin_responsable(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['assigned_to' => $user->id]);

        $user->delete();

        $this->assertNull($task->fresh()->assigned_to);
    }
}
