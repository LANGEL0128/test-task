<?php

namespace Tests\Feature\Tasks;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class IndexTaskTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private const URL = '/api/tasks';

    public function test_lista_paginada_sin_token_devuelve_200_con_15_por_pagina(): void
    {
        Task::factory()->count(20)->create();

        $this->getJson(self::URL)
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'title', 'description', 'status', 'assigned_to', 'assignee', 'due_date']],
                'links',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ])
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.per_page', 15);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('estados')]
    public function test_filtro_por_estado_devuelve_solo_ese_estado(TaskStatus $status): void
    {
        Task::factory()->create(['status' => TaskStatus::Pendiente]);
        Task::factory()->create(['status' => TaskStatus::EnCurso]);
        Task::factory()->create(['status' => TaskStatus::Hecha]);

        $response = $this->getJson(self::URL.'?status='.$status->value)->assertOk();

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', $status->value);
    }

    /**
     * @return array<string, array<int, TaskStatus>>
     */
    public static function estados(): array
    {
        return [
            'pendiente' => [TaskStatus::Pendiente],
            'en_curso' => [TaskStatus::EnCurso],
            'hecha' => [TaskStatus::Hecha],
        ];
    }

    public function test_filtro_por_responsable_devuelve_solo_sus_tareas(): void
    {
        $ana = $this->normalUser();
        $luis = $this->normalUser();
        Task::factory()->count(2)->create(['assigned_to' => $ana->id]);
        Task::factory()->count(3)->create(['assigned_to' => $luis->id]);

        $this->getJson(self::URL.'?assigned_to='.$ana->id)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.assigned_to', $ana->id);
    }

    public function test_filtros_combinados_estado_y_responsable(): void
    {
        $ana = $this->normalUser();
        Task::factory()->create(['assigned_to' => $ana->id, 'status' => TaskStatus::Hecha]);
        Task::factory()->create(['assigned_to' => $ana->id, 'status' => TaskStatus::Pendiente]);
        Task::factory()->create(['status' => TaskStatus::Hecha]);

        $this->getJson(self::URL.'?status=hecha&assigned_to='.$ana->id)
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_per_page_y_page_se_respetan(): void
    {
        Task::factory()->count(7)->create();

        $this->getJson(self::URL.'?per_page=3&page=3')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 3)
            ->assertJsonPath('meta.per_page', 3)
            ->assertJsonPath('meta.last_page', 3);
    }

    public function test_pagina_fuera_de_rango_devuelve_data_vacia(): void
    {
        Task::factory()->count(2)->create();

        $this->getJson(self::URL.'?page=50')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('filtrosInvalidos')]
    public function test_filtros_invalidos_devuelven_422(string $query, string $campo): void
    {
        $this->getJson(self::URL.'?'.$query)
            ->assertStatus(422)
            ->assertJsonValidationErrors([$campo]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function filtrosInvalidos(): array
    {
        return [
            'status desconocido' => ['status=terminada', 'status'],
            'assigned_to no numerico' => ['assigned_to=abc', 'assigned_to'],
            'assigned_to inexistente' => ['assigned_to=99999', 'assigned_to'],
            'per_page cero' => ['per_page=0', 'per_page'],
            'per_page mayor a 100' => ['per_page=101', 'per_page'],
            'per_page no numerico' => ['per_page=muchos', 'per_page'],
            'page cero' => ['page=0', 'page'],
        ];
    }

    public function test_sin_resultados_devuelve_200_con_data_vacia(): void
    {
        $this->getJson(self::URL)
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }
}
