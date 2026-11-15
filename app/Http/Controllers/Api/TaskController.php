<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexTaskRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Assignee',
    nullable: true,
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Super Admin'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@admin.com'),
    ],
)]
#[OA\Schema(
    schema: 'Task',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'title', type: 'string', example: 'Preparar informe mensual'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Incluir métricas del último mes'),
        new OA\Property(property: 'status', type: 'string', enum: ['pendiente', 'en_curso', 'hecha'], example: 'pendiente'),
        new OA\Property(property: 'assigned_to', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'assignee', ref: '#/components/schemas/Assignee'),
        new OA\Property(property: 'due_date', type: 'string', format: 'date', nullable: true, example: '2026-12-31'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
)]
#[OA\Schema(
    schema: 'TaskPaginated',
    properties: [
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Task')),
        new OA\Property(
            property: 'links',
            type: 'object',
            properties: [
                new OA\Property(property: 'first', type: 'string', nullable: true),
                new OA\Property(property: 'last', type: 'string', nullable: true),
                new OA\Property(property: 'prev', type: 'string', nullable: true),
                new OA\Property(property: 'next', type: 'string', nullable: true),
            ],
        ),
        new OA\Property(
            property: 'meta',
            type: 'object',
            properties: [
                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                new OA\Property(property: 'from', type: 'integer', nullable: true, example: 1),
                new OA\Property(property: 'last_page', type: 'integer', example: 3),
                new OA\Property(property: 'per_page', type: 'integer', example: 15),
                new OA\Property(property: 'to', type: 'integer', nullable: true, example: 15),
                new OA\Property(property: 'total', type: 'integer', example: 40),
            ],
        ),
    ],
)]
class TaskController extends Controller
{
    #[OA\Get(
        path: '/api/tasks',
        summary: 'Listar tareas (público) con filtros y paginación',
        tags: ['Tareas'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, description: 'Filtrar por estado', schema: new OA\Schema(type: 'string', enum: ['pendiente', 'en_curso', 'hecha'])),
            new OA\Parameter(name: 'assigned_to', in: 'query', required: false, description: 'Filtrar por id del responsable', schema: new OA\Schema(type: 'integer', example: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, description: 'Tareas por página (1-100, por defecto 15)', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 15)),
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Número de página', schema: new OA\Schema(type: 'integer', minimum: 1, default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lista paginada de tareas', content: new OA\JsonContent(ref: '#/components/schemas/TaskPaginated')),
            new OA\Response(response: 422, description: 'Filtros inválidos', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function index(IndexTaskRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $tasks = Task::query()
            ->with('assignee')
            ->when(isset($filters['status']), fn ($q) => $q->status($filters['status']))
            ->when(isset($filters['assigned_to']), fn ($q) => $q->assignedTo($filters['assigned_to']))
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();

        return TaskResource::collection($tasks);
    }

    #[OA\Get(
        path: '/api/tasks/{id}',
        summary: 'Ver una tarea (público)',
        tags: ['Tareas'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Id de la tarea', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tarea encontrada',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Task')]),
            ),
            new OA\Response(response: 400, description: 'Id no numérico', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 404, description: 'Tarea no encontrada', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
        ],
    )]
    public function show(Task $task): TaskResource
    {
        return new TaskResource($task->load('assignee'));
    }

    #[OA\Post(
        path: '/api/tasks',
        summary: 'Crear una tarea (solo super_admin)',
        security: [['bearerAuth' => []]],
        tags: ['Tareas'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', maxLength: 255, example: 'Preparar informe mensual'),
                    new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Incluir métricas del último mes'),
                    new OA\Property(property: 'status', type: 'string', enum: ['pendiente', 'en_curso', 'hecha'], default: 'pendiente'),
                    new OA\Property(property: 'assigned_to', type: 'integer', nullable: true, description: 'Id del usuario responsable', example: 1),
                    new OA\Property(property: 'due_date', type: 'string', format: 'date', nullable: true, description: 'Formato Y-m-d', example: '2026-12-31'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Tarea creada',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Task')]),
            ),
            new OA\Response(response: 400, description: 'JSON mal formado', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 403, description: 'Sin rol super_admin', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 422, description: 'Error de validación', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = Task::create($request->validated());

        return (new TaskResource($task->load('assignee')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    #[OA\Put(
        path: '/api/tasks/{id}',
        summary: 'Editar una tarea (solo super_admin). PATCH acepta los mismos campos',
        security: [['bearerAuth' => []]],
        tags: ['Tareas'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Id de la tarea', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title', type: 'string', maxLength: 255, example: 'Título editado'),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'status', type: 'string', enum: ['pendiente', 'en_curso', 'hecha'], example: 'en_curso'),
                    new OA\Property(property: 'assigned_to', type: 'integer', nullable: true, example: 1),
                    new OA\Property(property: 'due_date', type: 'string', format: 'date', nullable: true, example: '2027-01-15'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tarea actualizada',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Task')]),
            ),
            new OA\Response(response: 400, description: 'Id no numérico o JSON mal formado', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 403, description: 'Sin rol super_admin', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 404, description: 'Tarea no encontrada', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 422, description: 'Error de validación', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    #[OA\Patch(
        path: '/api/tasks/{id}',
        summary: 'Editar parcialmente una tarea (solo super_admin)',
        security: [['bearerAuth' => []]],
        tags: ['Tareas'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Id de la tarea', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title', type: 'string', maxLength: 255),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'status', type: 'string', enum: ['pendiente', 'en_curso', 'hecha'], example: 'hecha'),
                    new OA\Property(property: 'assigned_to', type: 'integer', nullable: true),
                    new OA\Property(property: 'due_date', type: 'string', format: 'date', nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tarea actualizada',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Task')]),
            ),
            new OA\Response(response: 400, description: 'Id no numérico o JSON mal formado', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 403, description: 'Sin rol super_admin', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 404, description: 'Tarea no encontrada', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 422, description: 'Error de validación', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $task->update($request->validated());

        return new TaskResource($task->load('assignee'));
    }

    #[OA\Delete(
        path: '/api/tasks/{id}',
        summary: 'Borrar una tarea (solo super_admin)',
        security: [['bearerAuth' => []]],
        tags: ['Tareas'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Id de la tarea', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Tarea borrada (sin contenido)'),
            new OA\Response(response: 400, description: 'Id no numérico', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 403, description: 'Sin rol super_admin', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 404, description: 'Tarea no encontrada', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
        ],
    )]
    public function destroy(Task $task): Response
    {
        $task->delete();

        return response()->noContent();
    }
}
