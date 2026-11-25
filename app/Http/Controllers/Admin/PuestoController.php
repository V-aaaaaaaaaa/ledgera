<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\RespondeJson;
use App\Models\Auditoria;
use App\Models\Puesto;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PuestoController extends Controller
{
    use RespondeJson;

    public function index(): View
    {
        return view('admin.puestos');
    }

    public function datos(): JsonResponse
    {
        $puestos = Puesto::with([
            'usuarios' => fn ($q) => $q
                ->where('rol', 'Dueno')
                ->where('estado', 'Activo')
        ])
            ->orderBy('estado')
            ->orderBy('nombre_puesto')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id_puesto,
                'nombre' => $p->nombre_puesto,
                'bodega' => $p->ubicacion_bodega,
                'estado' => $p->estado,
                'dueno' => $p->usuarios->first()?->nombre_completo,
            ]);

        $duenos = Usuario::with('puesto')
            ->where('rol', 'Dueno')
            ->where('estado', 'Inactivo')
            ->orderBy('nombre_completo')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id_usuario,
                'nombre' => $u->nombre_completo,
                'id_puesto' => $u->id_puesto,
                'puesto' => $u->puesto?->nombre_puesto,
            ]);

        return response()->json([
            'ok' => true,
            'puestos' => $puestos,
            'duenos' => $duenos,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request);

        DB::transaction(function () use ($datos) {

            $puesto = new Puesto();

            $puesto->nombre_puesto = $datos['nombre'];
            $puesto->ubicacion_bodega = $datos['bodega'];
            $puesto->estado = 'Activo';

            $this->guardar($puesto);

            Auditoria::registrar(
                'PUESTO_CREADO',
                $puesto->id_puesto,
                $puesto->id_puesto,
                'Registro de puesto comercial',
                null,
                'puestos',
                null,
                $puesto->only([
                    'nombre_puesto',
                    'ubicacion_bodega',
                    'estado'
                ])
            );
        });

        return response()->json([
            'ok' => true,
            'mensaje' => 'Puesto creado correctamente.'
        ], 201);
    }

    public function update(
        Request $request,
        Puesto $puesto
    ): JsonResponse {
        $datos = $this->validar($request);

        DB::transaction(function () use ($puesto, $datos) {

            $p = Puesto::lockForUpdate()
                ->findOrFail($puesto->id_puesto);

            $anterior = $p->only([
                'nombre_puesto',
                'ubicacion_bodega'
            ]);

            $p->nombre_puesto = $datos['nombre'];
            $p->ubicacion_bodega = $datos['bodega'];

            $this->guardar($p);

            Auditoria::registrar(
                'PUESTO_EDITADO',
                $p->id_puesto,
                $p->id_puesto,
                'Actualización del puesto',
                null,
                'puestos',
                $anterior,
                $p->only([
                    'nombre_puesto',
                    'ubicacion_bodega'
                ])
            );
        });

        return response()->json([
            'ok' => true,
            'mensaje' => 'Cambios guardados.'
        ]);
    }

    public function cambiarDueno(
        Request $request,
        Puesto $puesto
    ): JsonResponse {

        $validador = Validator::make(
            $request->all(),
            ['dueno_id' => ['required', 'integer', 'min:1']]
        );

        if ($validador->fails()) {
            $this->abortar('Selecciona un dueño válido.', 400);
        }

        DB::transaction(function () use ($request, $puesto) {

            $p = Puesto::lockForUpdate()
                ->findOrFail($puesto->id_puesto);

            if ($p->estado !== 'Activo') {
                $this->abortar('El puesto está inactivo.', 409);
            }

            $nuevo = Usuario::where(
                'id_usuario',
                $request->input('dueno_id')
            )
                ->where('rol', 'Dueno')
                ->where('estado', 'Inactivo')
                ->where('id_puesto', $p->id_puesto)
                ->lockForUpdate()
                ->first();

            if (!$nuevo) {
                $this->abortar(
                    'Selecciona un dueño inactivo registrado en este puesto.',
                    409
                );
            }

            $anterior = Usuario::where('id_puesto', $p->id_puesto)
                ->where('rol', 'Dueno')
                ->where('estado', 'Activo')
                ->lockForUpdate()
                ->first();

            if ($anterior) {
                $anterior->estado = 'Inactivo';
                $anterior->save();
            }

            $nuevo->forceFill([
                'estado' => 'Activo',
                'debe_cambiar_clave' => true,
                'intentos_fallidos' => 0,
                'bloqueado_hasta' => null,
            ])->save();

            Auditoria::registrar(
                'PUESTO_DUENO_CAMBIADO',
                $p->id_puesto,
                $p->id_puesto,
                'Cambio de responsable del puesto',
                null,
                'puestos',
                ['id_dueno' => $anterior?->id_usuario],
                ['id_dueno' => $nuevo->id_usuario]
            );
        });

        return response()->json([
            'ok' => true,
            'mensaje' => 'Dueño asignado correctamente.'
        ]);
    }

    public function cambiarEstado(
        Request $request,
        Puesto $puesto
    ): JsonResponse {

        $validador = Validator::make(
            $request->all(),
            ['activo' => ['required', 'boolean']]
        );

        if ($validador->fails()) {
            $this->abortar('Estado no válido.', 400);
        }

        $activar = $request->boolean('activo');

        DB::transaction(function () use ($puesto, $activar) {

            $p = Puesto::lockForUpdate()
                ->findOrFail($puesto->id_puesto);

            $anterior = $p->estado;

            $p->estado = $activar ? 'Activo' : 'Inactivo';
            $p->save();

            Auditoria::registrar(
                $activar
                    ? 'PUESTO_ACTIVADO'
                    : 'PUESTO_DESACTIVADO',
                $p->id_puesto,
                $p->id_puesto,
                'Cambio de estado del puesto',
                null,
                'puestos',
                ['estado' => $anterior],
                ['estado' => $p->estado]
            );
        });

        return response()->json([
            'ok' => true,
            'mensaje' => $activar
                ? 'Puesto activado.'
                : 'Puesto desactivado.'
        ]);
    }

    private function validar(Request $request): array
    {
        $validador = Validator::make(
            $request->all(),
            [
                'nombre' => ['required', 'string', 'max:100', 'regex:/^[\pL\pN .,&#-]+$/u'],
                'bodega' => ['required', 'string', 'max:100', 'regex:/^[\pL\pN .,&#-]+$/u'],
            ],
            [
                'nombre.required' => 'Escribe el nombre del puesto.',
                'bodega.required' => 'Escribe la bodega.',
                'nombre.max' => 'Máximo 100 caracteres.',
                'bodega.max' => 'Máximo 100 caracteres.',
                'nombre.regex' => 'El nombre solo puede tener letras, números, espacios y . , - & #',
                'bodega.regex' => 'La bodega solo puede tener letras, números, espacios y . , - & #',
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

    private function guardar(Puesto $puesto): void
    {
        try {
            $puesto->save();
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                $this->abortar(
                    'Ya existe un puesto con ese nombre en la misma bodega.',
                    409
                );
            }

            throw $e;
        }
    }
}
