<?php

namespace Tests\Feature\Tasks;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class StoreTaskTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

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
}
