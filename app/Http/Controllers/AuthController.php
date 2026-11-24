<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    private const MAX_INTENTOS = 3;
    public const MINUTOS_BLOQUEO = 3;

    // Mostrar formulario de inicio de sesión
    public function mostrarLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect(Auth::user()->rutaInicio());
        }

        return view('auth.login');
    }

    // Iniciar sesión
    public function login(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'usuario' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'max:255'],
        ], [
            'usuario.required' => 'Escribe tu usuario.',
            'password.required' => 'Escribe tu contraseña.',
        ]);

        $resultado = DB::transaction(function () use ($datos) {

            $u = Usuario::with('puesto')
                ->where('usuario', trim($datos['usuario']))
                ->lockForUpdate()
                ->first();

            $generico = 'Usuario o contraseña incorrectos.';

            // Usuario inexistente
            if (!$u) {
                Hash::make('credencial-inexistente');

                Auditoria::registrar(
                    'LOGIN_FALLIDO',
                    null,
                    null,
                    'Usuario inexistente',
                    null,
                    null
                );

                return $generico;
            }

            // Verificar bloqueo temporal
            if ($u->bloqueado_hasta !== null) {
                if (now()->lt($u->bloqueado_hasta)) {
                    $minutos = max(
                        1,
                        (int) ceil(
                            now()->diffInSeconds(
                                $u->bloqueado_hasta,
                                true
                            ) / 60
                        )
                    );

                    return "Cuenta bloqueada. Intenta de nuevo en {$minutos} min.";
                }

                $u->forceFill([
                    'intentos_fallidos' => 0,
                    'bloqueado_hasta' => null,
                ])->save();
            }

            // Verificar contraseña
            if (!$this->claveCorrecta(
                $datos['password'],
                $u->password_hash
            )) {
                $intentos = $u->intentos_fallidos + 1;

                $u->intentos_fallidos = $intentos;

                if ($intentos >= self::MAX_INTENTOS) {
                    $u->bloqueado_hasta = now()
                        ->addMinutes(self::MINUTOS_BLOQUEO);

                    $u->save();

                    Auditoria::registrar(
                        'CUENTA_BLOQUEADA',
                        $u->id_usuario,
                        $u->id_puesto,
                        'Tres intentos fallidos'
                    );

                    return 'Demasiados intentos. Cuenta bloqueada por 3 minutos.';
                }

                $u->save();

                Auditoria::registrar(
                    'LOGIN_FALLIDO',
                    $u->id_usuario,
                    $u->id_puesto,
                    'Contraseña incorrecta'
                );

                return $generico;
            }

            // Verificar estado de la cuenta
            if ($u->estado !== 'Activo') {
                Auditoria::registrar(
                    'LOGIN_RECHAZADO',
                    $u->id_usuario,
                    $u->id_puesto,
                    'Cuenta inactiva'
                );

                return 'Tu cuenta no está activa.';
            }

            // Verificar puesto para Dueño y Vendedor
            if (
                !$u->esAdministrador()
                && $u->puesto?->estado !== 'Activo'
            ) {
                Auditoria::registrar(
                    'LOGIN_RECHAZADO',
                    $u->id_usuario,
                    $u->id_puesto,
                    'Puesto inexistente o inactivo'
                );

                return 'Tu puesto no está activo.';
            }

            // Reiniciar intentos fallidos
            $u->forceFill([
                'intentos_fallidos' => 0,
                'bloqueado_hasta' => null,
            ])->save();

            Auditoria::registrar(
                'INGRESO',
                $u->id_usuario,
                $u->id_puesto,
                null,
                $u->id_usuario
            );

            return $u;
        });

        // Si no se obtuvo un usuario, devolver el error
        if (!$resultado instanceof Usuario) {
            return back()
                ->withErrors(['login' => $resultado])
                ->onlyInput('usuario');
        }

        // Crear sesión autenticada
        Auth::login($resultado);

        $request->session()->regenerate();

        return redirect($resultado->rutaInicio());
    }

    // Cerrar sesión
    public function logout(
        Request $request
    ): JsonResponse|RedirectResponse
    {
        $u = $request->user();

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($u) {
            Auditoria::registrar(
                'LOGOUT',
                $u->id_usuario,
                $u->id_puesto,
                null,
                $u->id_usuario
            );
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'destino' => route('login'),
            ]);
        }

        return redirect()->route('login');
    }

    // Mostrar formulario de cambio de contraseña
    public function mostrarCambioClave(Request $request): View
    {
        $u = $request->user();

        return view('auth.cambiar-clave', [
            'obligatorio' => $u->debe_cambiar_clave,
            'destino' => $u->debe_cambiar_clave
                ? route('login')
                : $u->rutaInicio(),
        ]);
    }

    // Cambiar contraseña
    public function cambiarClave(Request $request): JsonResponse
    {
        $actual = $request->input('actual');
        $nueva = $request->input('nueva');
        $confirmar = $request->input('confirmar');

        // Validaciones
        if (
            !is_string($actual)
            || !is_string($nueva)
            || !is_string($confirmar)
            || $actual === ''
            || $nueva === ''
            || $confirmar === ''
        ) {
            return $this->fallo(
                'Completa los tres campos.',
                400
            );
        }

        if (mb_strlen($nueva) < 8) {
            return $this->fallo(
                'Usa mínimo 8 caracteres.',
                400
            );
        }

        if (mb_strlen($nueva) > 64 || strlen($nueva) > 72) {
            return $this->fallo(
                'La contraseña nueva es demasiado larga.',
                400
            );
        }

        if (
            !preg_match('/[A-Za-z]/', $nueva)
            || !preg_match('/[0-9]/', $nueva)
        ) {
            return $this->fallo(
                'La contraseña debe tener una letra y un número.',
                400
            );
        }

        if ($nueva !== $confirmar) {
            return $this->fallo(
                'Las contraseñas nuevas no coinciden.',
                400
            );
        }

        if ($nueva === $actual) {
            return $this->fallo(
                'La contraseña nueva debe ser distinta a la actual.',
                400
            );
        }

        $id = Auth::id();

        $resultado = DB::transaction(function () use (
            $id,
            $actual,
            $nueva
        ) {
            $u = Usuario::with('puesto')
                ->lockForUpdate()
                ->findOrFail($id);

            if (
                $u->estado !== 'Activo'
                || (
                    !$u->esAdministrador()
                    && $u->puesto?->estado !== 'Activo'
                )
            ) {
                return [
                    'ok' => false,
                    'cerrar' => true,
                    'mensaje' => 'Tu cuenta o puesto está inactivo.',
                    'codigo' => 403,
                ];
            }

            // Bloqueo vigente
            if ($u->bloqueado_hasta !== null) {
                if (now()->lt($u->bloqueado_hasta)) {
                    return [
                        'ok' => false,
                        'cerrar' => true,
                        'mensaje' => 'Tu cuenta está bloqueada.',
                        'codigo' => 423,
                    ];
                }

                $u->forceFill([
                    'intentos_fallidos' => 0,
                    'bloqueado_hasta' => null,
                ])->save();
            }

            // Verificar contraseña actual
            if (!$this->claveCorrecta(
                $actual,
                $u->password_hash
            )) {
                $intentos = $u->intentos_fallidos + 1;

                $u->intentos_fallidos = $intentos;

                if ($intentos >= self::MAX_INTENTOS) {
                    $u->bloqueado_hasta = now()
                        ->addMinutes(self::MINUTOS_BLOQUEO);

                    $u->save();

                    Auditoria::registrar(
                        'CUENTA_BLOQUEADA',
                        $u->id_usuario,
                        $u->id_puesto,
                        'Intentos fallidos al cambiar contraseña'
                    );

                    return [
                        'ok' => false,
                        'cerrar' => true,
                        'mensaje' => 'Cuenta bloqueada por 3 minutos.',
                        'codigo' => 423,
                    ];
                }

                $u->save();

                return [
                    'ok' => false,
                    'mensaje' => 'La contraseña actual no es correcta.',
                    'codigo' => 401,
                ];
            }

            // Guardar la nueva contraseña
            $u->forceFill([
                'password_hash' => Hash::make($nueva),
                'debe_cambiar_clave' => false,
                'intentos_fallidos' => 0,
                'bloqueado_hasta' => null,
            ])->save();

            Auditoria::registrar(
                'CLAVE_CAMBIADA',
                $u->id_usuario,
                $u->id_puesto
            );

            return [
                'ok' => true,
                'mensaje' => 'Contraseña cambiada correctamente.',
                'destino' => $u->rutaInicio(),
                'codigo' => 200,
            ];
        });

        if ($resultado['cerrar'] ?? false) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();
        } elseif ($resultado['ok']) {
            $request->session()->regenerate();
        }

        $codigo = $resultado['codigo'];

        unset($resultado['codigo']);

        return response()->json($resultado, $codigo);
    }

    // Comparar contraseña con su hash
    private function claveCorrecta(
        string $plana,
        ?string $hash
    ): bool {
        if (
            $hash === null
            || $hash === ''
            || strlen($plana) > 72
        ) {
            return false;
        }

        try {
            return Hash::check($plana, $hash);
        } catch (\RuntimeException) {
            return false;
        }
    }

    // Respuesta JSON para errores
    private function fallo(
        string $mensaje,
        int $codigo
    ): JsonResponse {
        return response()->json([
            'ok' => false,
            'mensaje' => $mensaje,
        ], $codigo);
    }
}
