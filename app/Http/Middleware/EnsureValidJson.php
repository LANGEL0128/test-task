<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Responde 400 cuando el cuerpo declara ser JSON pero está mal formado.
 */
class EnsureValidJson
{
    public function handle(Request $request, Closure $next): Response
    {
        $body = $request->getContent();

        if ($request->isJson() && $body !== '' && ! json_validate($body)) {
            throw new BadRequestHttpException('El cuerpo de la petición no es un JSON válido.');
        }

        return $next($request);
    }
}
