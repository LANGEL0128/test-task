<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

#[OA\Schema(
    schema: 'TokenResponse',
    properties: [
        new OA\Property(property: 'access_token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...'),
        new OA\Property(property: 'token_type', type: 'string', example: 'bearer'),
        new OA\Property(property: 'expires_in', type: 'integer', description: 'Segundos hasta que expira', example: 3600),
    ],
)]
#[OA\Schema(
    schema: 'User',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Super Admin'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@admin.com'),
        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string'), example: ['super_admin']),
    ],
)]
#[OA\Schema(
    schema: 'Message',
    properties: [new OA\Property(property: 'message', type: 'string')],
)]
#[OA\Schema(
    schema: 'ValidationError',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'The email field is required.'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            example: ['email' => ['The email field is required.']],
        ),
    ],
)]
class AuthController extends Controller
{
    #[OA\Post(
        path: '/api/auth/login',
        summary: 'Iniciar sesión y obtener un token JWT',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@admin.com'),
                    new OA\Property(property: 'password', type: 'string', example: 'superadmin'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Login correcto', content: new OA\JsonContent(ref: '#/components/schemas/TokenResponse')),
            new OA\Response(response: 400, description: 'JSON mal formado', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 401, description: 'Credenciales inválidas', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 422, description: 'Error de validación', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $token = $this->guard()->attempt($request->validated());

        if (! $token) {
            return response()->json(['message' => 'Credenciales inválidas.'], 401);
        }

        return $this->respondWithToken($token);
    }

    #[OA\Post(
        path: '/api/auth/logout',
        summary: 'Cerrar sesión (invalida el token)',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Sesión cerrada', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
        ],
    )]
    public function logout(): JsonResponse
    {
        $this->guard()->logout();

        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }

    #[OA\Post(
        path: '/api/auth/refresh',
        summary: 'Renovar el token (el anterior queda invalidado)',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Token renovado', content: new OA\JsonContent(ref: '#/components/schemas/TokenResponse')),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
        ],
    )]
    public function refresh(): JsonResponse
    {
        return $this->respondWithToken($this->guard()->refresh());
    }

    #[OA\Get(
        path: '/api/auth/me',
        summary: 'Usuario autenticado y sus roles',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Usuario autenticado',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/User')]),
            ),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/Message')),
        ],
    )]
    public function me(): UserResource
    {
        return new UserResource($this->guard()->user());
    }

    private function guard(): JWTGuard
    {
        /** @var JWTGuard */
        return auth('api');
    }

    private function respondWithToken(string $token): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $this->guard()->factory()->getTTL() * 60,
        ]);
    }
}
