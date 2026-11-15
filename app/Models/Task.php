<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['title', 'description', 'status', 'assigned_to', 'due_date'])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    /**
     * Valores por defecto (para que la respuesta de crear ya incluya el estado).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pendiente',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'due_date' => 'date:Y-m-d',
        ];
    }

    /**
     * Responsable de la tarea.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function scopeStatus(Builder $query, TaskStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof TaskStatus ? $status->value : $status);
    }

    public function scopeAssignedTo(Builder $query, int|string $userId): Builder
    {
        return $query->where('assigned_to', $userId);
    }
}
