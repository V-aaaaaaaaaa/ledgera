<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe una ruta a ciertos roles. Uso: ->middleware('rol:Dueno,Vendedor')
 * Ocultar botones en el front es solo estética: esto es lo que realmente protege.
 */
class RolRequerido
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $u = $request->user();

        if (! in_array($u->rol, $roles, true)) {
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'mensaje' => 'No tienes permiso para esto.'], 403)
                : redirect($u->rutaInicio());
        }

        return $next($request);
    }
}
