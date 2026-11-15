<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
        ]);

        // El rol se valida antes de resolver el modelo de la ruta: un usuario sin permiso
        // recibe 403 y no puede averiguar qué ids existen (404).
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \Spatie\Permission\Middleware\RoleMiddleware::class,
        );

        $middleware->api(prepend: [
            \App\Http\Middleware\EnsureValidJson::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApi = fn (Request $request) => $request->is('api/*') || $request->expectsJson();

        $exceptions->shouldRenderJsonWhen($isApi);

        // 401: sin token, token inválido/expirado o en blacklist.
        $exceptions->render(fn (AuthenticationException $e, Request $request) => $isApi($request)
            ? response()->json(['message' => 'No autenticado.'], 401)
            : null);

        $exceptions->render(fn (JWTException $e, Request $request) => $isApi($request)
            ? response()->json(['message' => 'Token inválido o expirado.'], 401)
            : null);

        // 403: autenticado pero sin el rol requerido.
        $exceptions->render(fn (UnauthorizedException $e, Request $request) => $isApi($request)
            ? response()->json(['message' => 'No tienes permiso para realizar esta acción.'], 403)
            : null);

        // 404: recurso (modelo) o ruta inexistente. No se expone el nombre de la clase.
        $exceptions->render(fn (ModelNotFoundException $e, Request $request) => $isApi($request)
            ? response()->json(['message' => 'Recurso no encontrado.'], 404)
            : null);

        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => $isApi($request)
            ? response()->json(['message' => 'Recurso no encontrado.'], 404)
            : null);

        // 405: método HTTP no permitido para la ruta.
        $exceptions->render(fn (MethodNotAllowedHttpException $e, Request $request) => $isApi($request)
            ? response()->json(['message' => 'Método no permitido.'], 405)
            : null);

        // 400: JSON mal formado o identificador inválido.
        $exceptions->render(fn (BadRequestHttpException $e, Request $request) => $isApi($request)
            ? response()->json(['message' => $e->getMessage() ?: 'Petición incorrecta.'], 400)
            : null);
    })->create();
