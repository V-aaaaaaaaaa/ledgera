<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RespondeJson;
use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Puesto;
use App\Models\Usuario;
use App\Support\ClaveTemporal;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Gestión de cuentas de los tres roles (solo Administrador).
 * El Administrador NO ve datos comerciales: aquí solo hay cuentas y puestos.
 */
class UsuarioController extends Controller
{
    use RespondeJson;

    private const ROLES = ['Administrador', 'Dueno', 'Vendedor'];

    // Pantalla
    public function index(): View
    {
        return view('admin.usuarios');
    }

    // Lista de usuarios y puestos activos (para el formulario)
    public function datos(): JsonResponse
    {
        $usuarios = Usuario::with('puesto:id_puesto,nombre_puesto')
            ->orderByRaw("FIELD(rol, 'Administrador', 'Dueno', 'Vendedor')")
            ->orderBy('nombre_completo')
            ->get()
            ->map(fn (Usuario $u) => [
                'id' => $u->id_usuario,
                'usuario' => $u->usuario,
                'nombre' => $u->nombre_completo,
                'cedula' => $u->num_identificacion,
                'rol' => $u->rol,
                'estado' => $u->estado,
                'id_puesto' => $u->id_puesto,
                'puesto' => $u->puesto?->nombre_puesto,
            ]);

        $puestos = Puesto::where('estado', 'Activo')
            ->orderBy('nombre_puesto')
            ->get(['id_puesto', 'nombre_puesto'])
            ->map(fn (Puesto $p) => ['id' => $p->id_puesto, 'nombre' => $p->nombre_puesto]);

        return response()->json(['ok' => true, 'usuarios' => $usuarios, 'puestos' => $puestos]);
    }

    // Crear cuenta con clave temporal (se muestra una sola vez)
    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request, true);
        $idPuesto = $this->puestoParaRol($datos['rol'], $datos['id_puesto'] ?? null);

        $clave = ClaveTemporal::generar();

        $usuario = (new Usuario())->forceFill([
            'id_puesto' => $idPuesto,
            'usuario' => $datos['usuario'],
            'nombre_completo' => $datos['nombre'],
            'num_identificacion' => $datos['cedula'],
            'password_hash' => Hash::make($clave),
            'rol' => $datos['rol'],
            'debe_cambiar_clave' => true,
        ]);

        try {
            $usuario->save();
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                $this->abortar('Ese usuario o esa cédula ya existe.', 409);
            }
            throw $e;
        }

        Auditoria::registrar('USUARIO_CREADO', (int) $usuario->id_usuario, $usuario->id_puesto, 'Rol: ' . $usuario->rol);

        return response()->json(['ok' => true, 'mensaje' => 'Usuario creado.', 'clave' => $clave], 201);
    }

    // Editar nombre, usuario, cédula y puesto (el rol no se cambia)
    public function update(Request $request, Usuario $usuario): JsonResponse
    {
        $datos = $this->validar($request, false);
        $idPuesto = $this->puestoParaRol($usuario->rol, $datos['id_puesto'] ?? null, (int) $usuario->id_usuario);

        $usuario->forceFill([
            'usuario' => $datos['usuario'],
            'nombre_completo' => $datos['nombre'],
            'num_identificacion' => $datos['cedula'],
            'id_puesto' => $idPuesto,
        ]);

        try {
            $usuario->save();
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                $this->abortar('Ese usuario o esa cédula ya existe.', 409);
            }
            throw $e;
        }

        Auditoria::registrar('USUARIO_EDITADO', (int) $usuario->id_usuario, $usuario->id_puesto);

        return response()->json(['ok' => true, 'mensaje' => 'Cambios guardados.']);
    }

    // Nueva clave temporal: obliga a cambiarla al próximo ingreso y desbloquea la cuenta
    public function restablecerClave(Request $request, Usuario $usuario): JsonResponse
    {
        if ((int) $usuario->id_usuario === (int) $request->user()->id_usuario) {
            $this->abortar('No puedes restablecer tu propia clave desde aquí.', 400);
        }

        $clave = ClaveTemporal::generar();

        $usuario->forceFill([
            'password_hash' => Hash::make($clave),
            'debe_cambiar_clave' => true,
            'intentos_fallidos' => 0,
            'fecha_bloqueo' => null,
        ])->save();

        Auditoria::registrar('CLAVE_RESTABLECIDA', (int) $usuario->id_usuario, $usuario->id_puesto);

        return response()->json(['ok' => true, 'mensaje' => 'Clave restablecida.', 'clave' => $clave]);
    }

    // Activar o desactivar (nunca se borra)
    public function cambiarEstado(Request $request, Usuario $usuario): JsonResponse
    {
        $activar = $request->boolean('activo');

        if (! $activar) {
            if ((int) $usuario->id_usuario === (int) $request->user()->id_usuario) {
                $this->abortar('No puedes desactivar tu propia cuenta.', 400);
            }
            if ($usuario->rol === 'Administrador') {
                $hayOtro = Usuario::where('rol', 'Administrador')
                    ->where('estado', 'Activo')
                    ->where('id_usuario', '<>', $usuario->id_usuario)
                    ->exists();
                if (! $hayOtro) {
                    $this->abortar('Debe quedar al menos un Administrador activo.', 400);
                }
            }
        } elseif ($usuario->rol === 'Dueno') {
            // Al reactivar un Dueño no puede haber otro Dueño activo en su puesto
            $this->puestoParaRol('Dueno', $usuario->id_puesto, (int) $usuario->id_usuario);
        }

        $usuario->forceFill(['estado' => $activar ? 'Activo' : 'Inactivo'])->save();
        Auditoria::registrar($activar ? 'USUARIO_ACTIVADO' : 'USUARIO_DESACTIVADO', (int) $usuario->id_usuario, $usuario->id_puesto);

        return response()->json([
            'ok' => true,
            'mensaje' => $activar ? 'Usuario activado.' : 'Usuario desactivado.',
        ]);
    }

    // ---------------------------------------------------------------

    private function validar(Request $request, bool $conRol): array
    {
        $reglas = [
            'usuario' => ['required', 'regex:/^[A-Za-z0-9._-]{3,50}$/'],
            'nombre' => ['required', 'string', 'max:150'],
            'cedula' => ['required', 'regex:/^[0-9]{5,15}$/'],
            'id_puesto' => ['nullable', 'integer'],
        ];
        if ($conRol) {
            $reglas['rol'] = ['required', Rule::in(self::ROLES)];
        }

        $validador = Validator::make($request->all(), $reglas, [
            'usuario.required' => 'Escribe el usuario.',
            'usuario.regex' => 'El usuario debe tener entre 3 y 50 letras, números, punto o guion, sin espacios.',
            'nombre.required' => 'Escribe el nombre completo.',
            'nombre.max' => 'El nombre es muy largo (máximo 150 letras).',
            'cedula.required' => 'Escribe la cédula.',
            'cedula.regex' => 'La cédula debe tener solo números (5 a 15 dígitos).',
            'rol.required' => 'Elige un rol válido.',
            'rol.in' => 'Elige un rol válido.',
            'id_puesto.integer' => 'El puesto no es válido.',
        ]);

        if ($validador->fails()) {
            $this->abortar($validador->errors()->first(), 400);
        }

        return $validador->validated();
    }

    // Reglas del puesto según el rol. Devuelve el id a guardar (null para el Administrador).
    private function puestoParaRol(string $rol, mixed $idPuesto, int $excluirUsuario = 0): ?int
    {
        if ($rol === 'Administrador') {
            return null;
        }
        if (! $idPuesto) {
            $this->abortar('Elige el puesto del usuario.', 400);
        }

        $puesto = Puesto::find((int) $idPuesto);
        if (! $puesto || $puesto->estado !== 'Activo') {
            $this->abortar('Ese puesto no existe o está inactivo.', 400);
        }

        if ($rol === 'Dueno') {
            $yaHayDueno = Usuario::where('id_puesto', $puesto->id_puesto)
                ->where('rol', 'Dueno')
                ->where('estado', 'Activo')
                ->where('id_usuario', '<>', $excluirUsuario)
                ->exists();
            if ($yaHayDueno) {
                $this->abortar('Ese puesto ya tiene un Dueño activo.', 409);
            }
        }

        return (int) $puesto->id_puesto;
    }

}
