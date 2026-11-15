<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Pendiente = 'pendiente';
    case EnCurso = 'en_curso';
    case Hecha = 'hecha';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
