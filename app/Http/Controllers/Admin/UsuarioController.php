<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\RespondeJson;
use App\Models\Auditoria;
use App\Models\Puesto;
use App\Models\Usuario;
use App\Support\ClaveTemporal;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UsuarioController extends Controller
{
    use RespondeJson;

    private const ROLES = ['Administrador', 'Dueno', 'Vendedor'];

    public function index(): View
    {
        return view('admin.usuarios');
    }

    public function datos(Request $request): JsonResponse
    {
        $actor = $request->user();

        $consulta = Usuario::with('puesto');

        if ($actor->esAdministrador()) {
            // El Administrador ve y gestiona las cuentas de los tres roles
            $consulta->orderByRaw("FIELD(rol, 'Administrador', 'Dueno', 'Vendedor')");
        } else {
            $consulta->where('rol', 'Vendedor')
                ->where('id_puesto', $actor->id_puesto);
        }

        $usuarios = $consulta
            ->orderBy('nombre_completo')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id_usuario,
                'usuario' => $u->usuario,
                'nombre' => $u->nombre_completo,
                'cedula' => $u->num_identificacion,
                'rol' => $u->rol,
                'estado' => $u->estado,
                'id_puesto' => $u->id_puesto,
                'puesto' => $u->puesto?->nombre_puesto,
            ]);

        $puestos = Puesto::where('estado', 'Activo');

        if (!$actor->esAdministrador()) {
            $puestos->where('id_puesto', $actor->id_puesto);
        }

        return response()->json([
            'ok' => true,
            'usuarios' => $usuarios,
            'puestos' => $puestos
                ->orderBy('nombre_puesto')
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id_puesto,
                    'nombre' => $p->nombre_puesto,
                ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $request->user();
        $datos = $this->validar($request);

        // El Administrador elige el rol; el Dueño solo crea Vendedores de su puesto
        if ($actor->esAdministrador()) {
            $rol = $datos['rol'] ?? null;

            if (!in_array($rol, self::ROLES, true)) {
                $this->abortar('Elige un rol válido.', 400);
            }
        } else {
            $rol = 'Vendedor';
        }

        $idPuesto = $actor->esAdministrador()
            ? ($datos['id_puesto'] ?? null)
            : $actor->id_puesto;

        $clave = ClaveTemporal::generar();
        $estado = 'Activo';

        DB::transaction(function () use (
            $datos, $rol, $idPuesto, $clave, &$estado
        ) {

            // El Administrador no pertenece a ningún puesto
            $puesto = null;

            if ($rol !== 'Administrador') {
                $puesto = Puesto::lockForUpdate()->find($idPuesto);

                if (!$puesto || $puesto->estado !== 'Activo') {
                    $this->abortar('Selecciona un puesto activo.', 400);
                }
            }

            // Si ya existe un dueño, la nueva cuenta queda
            // inactiva hasta realizar el cambio de responsable.
            if ($rol === 'Dueno') {
                $existeDueno = Usuario::where(
                    'id_puesto', $puesto->id_puesto
                )
                    ->where('rol', 'Dueno')
                    ->where('estado', 'Activo')
                    ->exists();

                if ($existeDueno) {
                    $estado = 'Inactivo';
                }
            }

            $usuario = (new Usuario())->forceFill([
                'usuario' => $datos['usuario'],
                'nombre_completo' => $datos['nombre'],
                'num_identificacion' => $datos['cedula'],
                'id_puesto' => $puesto?->id_puesto,
                'rol' => $rol,
                'estado' => $estado,
                'password_hash' => Hash::make($clave),
                'debe_cambiar_clave' => true,
                'intentos_fallidos' => 0,
                'bloqueado_hasta' => null,
            ]);

            $this->guardar($usuario);

            Auditoria::registrar(
                'USUARIO_CREADO',
                $usuario->id_usuario,
                $usuario->id_puesto,
                'Creación de cuenta: ' . $rol,
                null,
                'usuarios',
                null,
                $this->datosAuditoria($usuario)
            );
        });

        return response()->json([
            'ok' => true,
            'mensaje' => $estado === 'Inactivo'
                ? 'Usuario creado inactivo: el puesto ya tiene un Dueño activo. Usa "Cambiar dueño" en Puestos.'
                : 'Usuario registrado correctamente.',
            'clave' => $clave,
        ], 201);
    }

    public function update(
        Request $request,
        Usuario $usuario
    ): JsonResponse {

        $datos = $this->validar($request);

        DB::transaction(function () use (
            $request, $usuario, $datos
        ) {

            $u = Usuario::lockForUpdate()
                ->findOrFail($usuario->id_usuario);

            $this->autorizarCuenta($request, $u);

            $idPuestoNuevo = $u->id_puesto;

            $cambiaPuesto = $u->rol !== 'Administrador'
                && isset($datos['id_puesto'])
                && (int) $datos['id_puesto'] !== (int) $u->id_puesto;

            if ($cambiaPuesto) {
                // Solo el Administrador corrige el puesto (ej.: se creó en el puesto equivocado)
                if (!$request->user()->esAdministrador()) {
                    $this->abortar('Solo el Administrador puede cambiar el puesto de una cuenta.', 403);
                }

                $destino = Puesto::lockForUpdate()->find($datos['id_puesto']);

                if (!$destino || $destino->estado !== 'Activo') {
                    $this->abortar('Selecciona un puesto activo.', 400);
                }

                // Un puesto solo puede tener un Dueño activo
                if ($u->rol === 'Dueno' && $u->estado === 'Activo') {
                    $ocupado = Usuario::where('id_puesto', $destino->id_puesto)
                        ->where('rol', 'Dueno')
                        ->where('estado', 'Activo')
                        ->exists();

                    if ($ocupado) {
                        $this->abortar(
                            'Ese puesto ya tiene un Dueño activo. Desactiva uno de los dos o usa "Cambiar dueño".',
                            409
                        );
                    }
                }

                $idPuestoNuevo = $destino->id_puesto;
            }

            $anterior = $this->datosAuditoria($u);

            $u->forceFill([
                'usuario' => $datos['usuario'],
                'nombre_completo' => $datos['nombre'],
                'num_identificacion' => $datos['cedula'],
                'id_puesto' => $idPuestoNuevo,
            ]);

            $this->guardar($u);

            Auditoria::registrar(
                'USUARIO_EDITADO',
                $u->id_usuario,
                $u->id_puesto,
                $cambiaPuesto
                    ? 'Actualización de cuenta con cambio de puesto'
                    : 'Actualización de cuenta',
                null,
                'usuarios',
                $anterior,
                $this->datosAuditoria($u)
            );
        });

        return response()->json([
            'ok' => true,
            'mensaje' => 'Cambios guardados.'
        ]);
    }

    public function restablecerClave(
        Request $request,
        Usuario $usuario
    ): JsonResponse {

        $clave = ClaveTemporal::generar();

        DB::transaction(function () use (
            $request, $usuario, $clave
        ) {

            $u = Usuario::lockForUpdate()
                ->findOrFail($usuario->id_usuario);

            $this->autorizarCuenta($request, $u);

            if ((int) $u->id_usuario === (int) $request->user()->id_usuario) {
                $this->abortar('No puedes restablecer tu propia clave desde aquí.', 400);
            }

            $u->forceFill([
                'password_hash' => Hash::make($clave),
                'debe_cambiar_clave' => true,
                'intentos_fallidos' => 0,
                'bloqueado_hasta' => null,
            ])->save();

            Auditoria::registrar(
                'CLAVE_RESTABLECIDA',
                $u->id_usuario,
                $u->id_puesto,
                'Restablecimiento de contraseña temporal'
            );
        });

        return response()->json([
            'ok' => true,
            'mensaje' => 'Contraseña restablecida.',
            'clave' => $clave,
        ]);
    }

    public function cambiarEstado(
        Request $request,
        Usuario $usuario
    ): JsonResponse {

        $validador = Validator::make(
            $request->all(),
            ['activo' => ['required', 'boolean']]
        );

        if ($validador->fails()) {
            $this->abortar('Estado no válido.', 400);
        }

        $activar = $request->boolean('activo');

        DB::transaction(function () use (
            $request, $usuario, $activar
        ) {

            // El Administrador no tiene puesto
            $puesto = $usuario->id_puesto
                ? Puesto::lockForUpdate()->find($usuario->id_puesto)
                : null;

            $u = Usuario::lockForUpdate()
                ->findOrFail($usuario->id_usuario);

            $this->autorizarCuenta($request, $u);

            if (!$activar) {
                if ((int) $u->id_usuario === (int) $request->user()->id_usuario) {
                    $this->abortar('No puedes desactivar tu propia cuenta.', 400);
                }

                if ($u->rol === 'Administrador') {
                    $hayOtro = Usuario::where('rol', 'Administrador')
                        ->where('estado', 'Activo')
                        ->where('id_usuario', '<>', $u->id_usuario)
                        ->exists();

                    if (!$hayOtro) {
                        $this->abortar('Debe quedar al menos un Administrador activo.', 400);
                    }
                }
            }

            if ($activar && $puesto && $puesto->estado !== 'Activo') {
                $this->abortar('El puesto está inactivo.', 409);
            }

            if ($activar && $u->rol === 'Dueno') {

                $otroDueno = Usuario::where(
                    'id_puesto', $u->id_puesto
                )
                    ->where('rol', 'Dueno')
                    ->where('estado', 'Activo')
                    ->where('id_usuario', '<>', $u->id_usuario)
                    ->exists();

                if ($otroDueno) {
                    $this->abortar(
                        'El puesto ya tiene un dueño activo. Usa Cambiar dueño.',
                        409
                    );
                }
            }

            $anterior = $u->estado;

            $u->estado = $activar ? 'Activo' : 'Inactivo';

            $this->guardar($u);

            Auditoria::registrar(
                $activar
                    ? 'USUARIO_ACTIVADO'
                    : 'USUARIO_DESACTIVADO',
                $u->id_usuario,
                $u->id_puesto,
                'Cambio de estado de cuenta',
                null,
                'usuarios',
                ['estado' => $anterior],
                ['estado' => $u->estado]
            );
        });

        return response()->json([
            'ok' => true,
            'mensaje' => $activar
                ? 'Usuario activado.'
                : 'Usuario desactivado.',
        ]);
    }

    private function autorizarCuenta(
        Request $request,
        Usuario $usuario
    ): void {

        $actor = $request->user();

        // El Administrador gestiona cualquier cuenta
        if ($actor->esAdministrador()) {
            return;
        }

        if (
            $actor->rol === 'Dueno'
            && $usuario->rol === 'Vendedor'
            && (int) $actor->id_puesto === (int) $usuario->id_puesto
        ) {
            return;
        }

        $this->abortar(
            'No tienes permiso para gestionar esta cuenta.',
            403
        );
    }

    private function validar(Request $request): array
    {
        $validador = Validator::make(
            $request->all(),
            [
                'usuario' => [
                    'required',
                    'regex:/^[A-Za-z0-9._-]{3,50}$/'
                ],
                'nombre' => ['required', 'string', 'max:150', 'regex:/^[\pL ]+$/u'],
                'cedula' => [
                    'required',
                    'regex:/^[0-9]{5,15}$/'
                ],
                'id_puesto' => ['nullable', 'integer', 'min:1'],
                'rol' => ['nullable', 'string'],
            ],
            [
                'usuario.required' => 'Escribe el usuario.',
                'usuario.regex' => 'Usuario inválido: usa entre 3 y 50 caracteres sin espacios.',
                'nombre.required' => 'Escribe el nombre completo.',
                'nombre.regex' => 'El nombre solo puede tener letras y espacios.',
                'cedula.required' => 'Escribe la identificación.',
                'cedula.regex' => 'La identificación debe contener de 5 a 15 números.',
            ]
        );

        if ($validador->fails()) {
            $this->abortar(
                $validador->errors()->first(),
                400
            );
        }

        return $validador->validated();
    }

    private function guardar(Usuario $usuario): void
    {
        try {
            $usuario->save();
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                $this->abortar(
                    'El usuario, la identificación o la asignación de dueño ya existe.',
                    409
                );
            }

            throw $e;
        }
    }

    private function datosAuditoria(Usuario $u): array
    {
        return $u->only([
            'usuario',
            'nombre_completo',
            'rol',
            'estado',
            'id_puesto',
        ]);
    }
}
