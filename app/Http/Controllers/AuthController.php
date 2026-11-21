<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Inicio de sesión, cierre de sesión y cambio de clave (RF-001).
 */
class AuthController extends Controller
{
    private const MAX_INTENTOS = 3;
    public const MINUTOS_BLOQUEO = 3;

    public function mostrarLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect(Auth::user()->rutaInicio());
        }

        return view('auth.login');
    }

    public function login(Request $request): JsonResponse
    {
        $usuario = trim((string) $request->input('usuario'));
        $password = (string) $request->input('password');

        if ($usuario === '' || $password === '') {
            return $this->fallo('Escribe tu usuario y tu contraseña.', 400);
        }

        $generico = 'Usuario o contraseña incorrectos.';
        $u = Usuario::with('puesto')->where('usuario', $usuario)->first();

        if (! $u) {
            Hash::make($password); // gasta un tiempo parecido para no revelar qué usuarios existen
            return $this->fallo($generico, 401);
        }

        // 1) Bloqueo temporal vigente
        if ($u->fecha_bloqueo !== null) {
            $fin = $u->fecha_bloqueo->copy()->addMinutes(self::MINUTOS_BLOQUEO);
            if (now()->lt($fin)) {
                $minutos = max(1, (int) ceil(now()->diffInSeconds($fin) / 60));
                return $this->fallo("Cuenta bloqueada. Intenta de nuevo en {$minutos} min.", 423);
            }
            $u->forceFill(['intentos_fallidos' => 0, 'fecha_bloqueo' => null])->save();
        }

        // 2) Cuenta o puesto inactivo, o sin puesto asignado
        if ($u->estado !== 'Activo' || ($u->id_puesto !== null && $u->puesto?->estado !== 'Activo')) {
            return $this->fallo('Tu cuenta no está activa. Habla con tu superior.', 403);
        }
        if (! $u->esAdministrador() && $u->id_puesto === null) {
            return $this->fallo('Tu cuenta no tiene un puesto asignado. Habla con el Administrador.', 403);
        }

        // 3) Verificar la clave (3 fallos seguidos bloquean 3 minutos)
        if (! $this->claveCorrecta($password, $u->password_hash)) {
            $intentos = $u->intentos_fallidos + 1;
            if ($intentos >= self::MAX_INTENTOS) {
                $u->forceFill(['intentos_fallidos' => $intentos, 'fecha_bloqueo' => now()])->save();
                Auditoria::registrar('CUENTA_BLOQUEADA', (int) $u->id_usuario, null, 'Intentos fallidos seguidos');
                return $this->fallo('Demasiados intentos. Cuenta bloqueada por ' . self::MINUTOS_BLOQUEO . ' minutos.', 423);
            }
            $u->forceFill(['intentos_fallidos' => $intentos])->save();
            return $this->fallo($generico . ' Te quedan ' . (self::MAX_INTENTOS - $intentos) . ' intentos.', 401);
        }

        // 4) Éxito
        $u->forceFill(['intentos_fallidos' => 0, 'fecha_bloqueo' => null])->save();
        Auth::login($u);
        $request->session()->regenerate(); // evita fijación de sesión
        Auditoria::registrar('INGRESO', (int) $u->id_usuario);

        return response()->json([
            'ok' => true,
            'cambiar_clave' => (bool) $u->debe_cambiar_clave,
            'destino' => $u->rutaInicio(),
            'usuario' => ['nombre' => $u->nombre_completo, 'rol' => $u->rol],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true, 'destino' => route('login')]);
    }

    public function mostrarCambioClave(Request $request): View
    {
        $u = $request->user();

        return view('auth.cambiar-clave', [
            'obligatorio' => (bool) $u->debe_cambiar_clave,
            'destino' => $u->debe_cambiar_clave ? route('login') : $u->rutaInicio(),
        ]);
    }

    public function cambiarClave(Request $request): JsonResponse
    {
        $actual = (string) $request->input('actual');
        $nueva = (string) $request->input('nueva');
        $confirmar = (string) $request->input('confirmar');

        if ($actual === '' || $nueva === '' || $confirmar === '') {
            return $this->fallo('Completa los tres campos.', 400);
        }
        if (mb_strlen($nueva) < 8) {
            return $this->fallo('La clave nueva es muy corta. Usa mínimo 8 caracteres.', 400);
        }
        if (mb_strlen($nueva) > 64) {
            return $this->fallo('La clave nueva es muy larga. Usa máximo 64 caracteres.', 400);
        }
        if (! preg_match('/[A-Za-z]/', $nueva) || ! preg_match('/[0-9]/', $nueva)) {
            return $this->fallo('La clave nueva debe tener al menos una letra y un número.', 400);
        }
        if ($nueva !== $confirmar) {
            return $this->fallo('Las claves nuevas no coinciden.', 400);
        }
        if ($nueva === $actual) {
            return $this->fallo('La clave nueva debe ser distinta a la actual.', 400);
        }

        $u = $request->user();

        // Clave actual incorrecta: mismo límite de 3 intentos que el login
        if (! $this->claveCorrecta($actual, $u->password_hash)) {
            $intentos = $u->intentos_fallidos + 1;
            if ($intentos >= self::MAX_INTENTOS) {
                $u->forceFill(['intentos_fallidos' => $intentos, 'fecha_bloqueo' => now()])->save();
                Auditoria::registrar('CUENTA_BLOQUEADA', (int) $u->id_usuario, null, 'Intentos fallidos seguidos');
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return response()->json([
                    'ok' => false,
                    'cerrar' => true,
                    'mensaje' => 'Demasiados intentos. Cuenta bloqueada por ' . self::MINUTOS_BLOQUEO . ' minutos.',
                ], 423);
            }
            $u->forceFill(['intentos_fallidos' => $intentos])->save();
            return $this->fallo('La clave actual no es correcta. Te quedan ' . (self::MAX_INTENTOS - $intentos) . ' intentos.', 401);
        }

        $u->forceFill([
            'password_hash' => Hash::make($nueva),
            'debe_cambiar_clave' => false,
            'intentos_fallidos' => 0,
            'fecha_bloqueo' => null,
        ])->save();
        $request->session()->regenerate();

        Auditoria::registrar('CLAVE_CAMBIADA', (int) $u->id_usuario);

        return response()->json(['ok' => true, 'mensaje' => 'Clave cambiada.', 'destino' => $u->rutaInicio()]);
    }

    // Compara la clave escrita con el hash guardado (bcrypt). Si el hash guardado
    // no es válido (por ejemplo el "hash_de_prueba" del script SQL) simplemente no coincide.
    private function claveCorrecta(string $plana, ?string $hash): bool
    {
        if ($hash === null || $hash === '') {
            return false;
        }

        try {
            return Hash::check($plana, $hash);
        } catch (\RuntimeException) {
            return false;
        }
    }

    private function fallo(string $mensaje, int $codigo): JsonResponse
    {
        return response()->json(['ok' => false, 'mensaje' => $mensaje], $codigo);
    }
}
