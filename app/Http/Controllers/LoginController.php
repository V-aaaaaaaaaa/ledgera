<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    private const MAX_INTENTOS = 3;
    private const MINUTOS_BLOQUEO = 3;
    private const MSG_FALLO = 'Usuario o contraseña incorrectos.';

    public function show()
    {
        if (Auth::check()) {
            return redirect($this->destino(Auth::user()));
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $datos = $request->validate([
            'usuario'  => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
        ], [
            'usuario.required'  => 'Escribe tu usuario.',
            'password.required' => 'Escribe tu contraseña.',
        ]);

        $user = User::where('usuario', $datos['usuario'])->first();

        // Usuario inexistente: mismo mensaje y tiempo parecido (no revela quién existe)
        if (!$user) {
            Hash::make($datos['password']);
            $this->auditar(null, 'LOGIN_FALLIDO', 'usuario inexistente');
            return $this->fallo(self::MSG_FALLO);
        }

        // 1) Bloqueo vigente (aunque la clave sea correcta, sigue bloqueado)
        if ($user->bloqueado_hasta !== null) {
            if (now()->lt($user->bloqueado_hasta)) {
                $min = max(1, (int) ceil(now()->diffInSeconds($user->bloqueado_hasta, true) / 60));
                return $this->fallo("Cuenta bloqueada. Intenta de nuevo en {$min} min.");
            }
            // Bloqueo vencido: reiniciar
            $user->forceFill(['intentos_fallidos' => 0, 'bloqueado_hasta' => null])->save();
        }

        // 2) Verificar la clave
        if (!Hash::check($datos['password'], $user->getAuthPassword())) {
            $intentos = $user->intentos_fallidos + 1;

            if ($intentos >= self::MAX_INTENTOS) {
                $user->forceFill([
                    'intentos_fallidos' => $intentos,
                    'bloqueado_hasta'   => now()->addMinutes(self::MINUTOS_BLOQUEO),
                ])->save();
                $this->auditar($user, 'CUENTA_BLOQUEADA', 'tres intentos fallidos');
                return $this->fallo('Demasiados intentos. Cuenta bloqueada por ' . self::MINUTOS_BLOQUEO . ' minutos.');
            }

            $user->forceFill(['intentos_fallidos' => $intentos])->save();
            $this->auditar($user, 'LOGIN_FALLIDO', 'clave incorrecta');
            return $this->fallo(self::MSG_FALLO . ' Te quedan ' . (self::MAX_INTENTOS - $intentos) . ' intentos.');
        }

        // 3) Clave correcta, pero ¿cuenta y puesto activos?
        $puestoActivo = $user->id_puesto === null
            || DB::table('puestos')->where('id_puesto', $user->id_puesto)->value('estado') === 'Activo';

        if ($user->estado !== 'Activo' || !$puestoActivo) {
            $this->auditar($user, 'LOGIN_RECHAZADO', 'cuenta o puesto inactivo');
            return $this->fallo('Tu cuenta no está activa. Habla con tu superior.');
        }

        // 4) Éxito
        $user->forceFill(['intentos_fallidos' => 0, 'bloqueado_hasta' => null])->save();
        Auth::login($user);
        $request->session()->regenerate();   // evita fijación de sesión
        $this->auditar($user, 'LOGIN_OK');

        return redirect($user->debe_cambiar_clave ? '/cambiar-clave' : $this->destino($user));
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            $this->auditar($user, 'LOGOUT');
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    private function destino(User $u): string
    {
        return $u->rol === 'Administrador' ? '/admin' : '/dashboard';
    }

    private function fallo(string $mensaje)
    {
        return back()->withErrors(['login' => $mensaje])->onlyInput('usuario');
    }

    private function auditar(?User $u, string $accion, ?string $motivo = null): void
    {
        DB::table('auditoria')->insert([
            'id_usuario' => $u?->id_usuario,
            'id_puesto'  => $u?->id_puesto,
            'accion'     => $accion,
            'entidad'    => $u ? 'usuarios' : null,
            'id_entidad' => $u?->id_usuario,
            'motivo'     => $motivo,
        ]);
    }
}