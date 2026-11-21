<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Revalida en cada petición que la cuenta siga activa (y su puesto) y que ya no
 * tenga una clave temporal. Uso: ->middleware('cuenta') o 'cuenta:temporal'
 * (esta última deja pasar a quien aún tiene clave temporal: solo para cambiarla).
 */
class CuentaVigente
{
    public function handle(Request $request, Closure $next, ?string $modo = null): Response
    {
        $u = $request->user();
        $motivo = null;

        if ($u->estado !== 'Activo') {
            $motivo = 'Tu cuenta no está activa. Habla con tu superior.';
        } elseif (! $u->esAdministrador()) {
            if ($u->puesto === null) {
                $motivo = 'Tu cuenta no tiene un puesto asignado. Habla con el Administrador.';
            } elseif ($u->puesto->estado !== 'Activo') {
                $motivo = 'Tu puesto está inactivo. Habla con el Administrador.';
            }
        }

        if ($motivo !== null) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['ok' => false, 'mensaje' => $motivo], 401)
                : redirect()->route('login');
        }

        if ($modo !== 'temporal' && $u->debe_cambiar_clave) {
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'cambiar_clave' => true,
                                    'mensaje' => 'Primero debes cambiar tu clave temporal.'], 403)
                : redirect()->route('clave.form');
        }

        return $next($request);
    }
}
