<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe una ruta a quien tenga un poder. Uso: ->middleware('poder:pagar_proveedores')
 * Lo tienen el Dueño y, si es delegable, el Vendedor encargado (ver Usuario::puede).
 */
class PoderRequerido
{
    public function handle(Request $request, Closure $next, string $poder): Response
    {
        $u = $request->user();

        if (!$u || !$u->puede($poder)) {
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'mensaje' => 'No tienes permiso para esto.'], 403)
                : redirect($u ? $u->rutaInicio() : '/');
        }

        return $next($request);
    }
}
