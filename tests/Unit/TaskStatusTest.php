<?php

namespace Tests\Unit;

use App\Enums\TaskStatus;
use PHPUnit\Framework\TestCase;

class TaskStatusTest extends TestCase
{
    public function test_el_enum_tiene_los_tres_estados_esperados(): void
    {
        $this->assertSame(['pendiente', 'en_curso', 'hecha'], TaskStatus::values());
    }

    public function test_se_puede_crear_desde_su_valor(): void
    {
        $this->assertSame(TaskStatus::EnCurso, TaskStatus::from('en_curso'));
        $this->assertNull(TaskStatus::tryFrom('inexistente'));
    }
}
