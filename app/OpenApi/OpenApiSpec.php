<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'API de Tareas',
    description: 'API REST para gestionar tareas, con autenticación JWT y roles.',
)]
#[OA\Server(url: L5_SWAGGER_CONST_HOST, description: 'Servidor local')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'Token obtenido en POST /api/auth/login',
)]
class OpenApiSpec
{
}
