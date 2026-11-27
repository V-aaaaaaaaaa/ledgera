<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CuentaVigente
{
    public function handle(
        Request $request,
        Closure $next,
        ?string $modo = null
    ): Response {
        $u = $request->user();
        $motivo = null;

        if ($u->estado !== 'Activo') {
            $motivo = 'Tu cuenta no está activa.';
        } elseif (
            $u->bloqueado_hasta !== null
            && now()->lt($u->bloqueado_hasta)
        ) {
            $motivo = 'Tu cuenta está bloqueada.';
        } elseif (!$u->esAdministrador()) {
            if ($u->puesto === null) {
                $motivo = 'No tienes un puesto asignado.';
            } elseif ($u->puesto->estado !== 'Activo') {
                $motivo = 'Tu puesto está inactivo.';
            }
        }

        if ($motivo !== null) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json([
                    'ok' => false,
                    'mensaje' => $motivo,
                ], 401)
                : redirect()->route('login')
                    ->withErrors(['login' => $motivo]);
        }

        // Impedir el acceso con contraseña temporal
        if (
            $modo !== 'temporal'
            && $u->debe_cambiar_clave
        ) {
            return $request->expectsJson()
                ? response()->json([
                    'ok' => false,
                    'cambiar_clave' => true,
                    'mensaje' => 'Primero debes cambiar tu contraseña temporal.',
                ], 403)
                : redirect()->route('clave.form');
        }

        return $next($request);
    }
}
