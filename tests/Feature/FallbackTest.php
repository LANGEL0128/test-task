<?php

namespace Tests\Feature;

use Tests\TestCase;

class FallbackTest extends TestCase
{
    public function test_ruta_inexistente_devuelve_404_en_json(): void
    {
        $this->getJson('/api/no-existe')
            ->assertNotFound()
            ->assertJsonPath('message', 'Recurso no encontrado.');
    }

    public function test_metodo_no_permitido_devuelve_405_en_json(): void
    {
        $this->deleteJson('/api/tasks')
            ->assertStatus(405)
            ->assertJsonPath('message', 'Método no permitido.');
    }
}
