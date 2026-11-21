<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RespondeJson;
use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Puesto;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Gestión de puestos (RF-002, solo Administrador).
 * El Administrador administra puestos y Dueños, pero no registra operaciones comerciales.
 */
class PuestoController extends Controller
{
    use RespondeJson;

    // Pantalla
    public function index(): View
    {
        return view('admin.puestos');
    }

    // Lista de puestos (con su Dueño activo) y de Dueños disponibles para asignar
    public function datos(): JsonResponse
    {
        $puestos = Puesto::with(['usuarios' => fn ($q) => $q->where('rol', 'Dueno')->where('estado', 'Activo')])
            ->orderBy('estado')
            ->orderBy('nombre_puesto')
            ->get()
            ->map(fn (Puesto $p) => [
                'id' => $p->id_puesto,
                'nombre' => $p->nombre_puesto,
                'bodega' => $p->ubicacion_bodega,
                'estado' => $p->estado,
                'dueno' => $p->usuarios->first()?->nombre_completo,
            ]);

        $duenos = Usuario::with('puesto:id_puesto,nombre_puesto')
            ->where('rol', 'Dueno')
            ->where('estado', 'Activo')
            ->orderBy('nombre_completo')
            ->get()
            ->map(fn (Usuario $u) => [
                'id' => $u->id_usuario,
                'nombre' => $u->nombre_completo,
                'id_puesto' => $u->id_puesto,
                'puesto' => $u->puesto?->nombre_puesto,
            ]);

        return response()->json(['ok' => true, 'puestos' => $puestos, 'duenos' => $duenos]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request);

        $puesto = (new Puesto())->forceFill([
            'nombre_puesto' => $datos['nombre'],
            'ubicacion_bodega' => $datos['bodega'],
        ]);
        $this->guardar($puesto);

        Auditoria::registrar('PUESTO_CREADO', null, (int) $puesto->id_puesto, $puesto->nombre_puesto);

        return response()->json(['ok' => true, 'mensaje' => 'Puesto creado.'], 201);
    }

    public function update(Request $request, Puesto $puesto): JsonResponse
    {
        $datos = $this->validar($request);

        $this->guardar($puesto->forceFill([
            'nombre_puesto' => $datos['nombre'],
            'ubicacion_bodega' => $datos['bodega'],
        ]));

        Auditoria::registrar('PUESTO_EDITADO', null, (int) $puesto->id_puesto, $puesto->nombre_puesto);

        return response()->json(['ok' => true, 'mensaje' => 'Cambios guardados.']);
    }

    // Asigna o cambia el Dueño. El Dueño anterior pierde el acceso a este puesto.
    public function cambiarDueno(Request $request, Puesto $puesto): JsonResponse
    {
        if ($puesto->estado !== 'Activo') {
            $this->abortar('Activa el puesto antes de asignarle un Dueño.', 400);
        }

        $nuevo = Usuario::where('id_usuario', (int) $request->input('dueno_id'))
            ->where('rol', 'Dueno')
            ->where('estado', 'Activo')
            ->first();

        if (! $nuevo) {
            $this->abortar('Elige una cuenta de Dueño que esté activa.', 400);
        }
        if ((int) $nuevo->id_puesto === (int) $puesto->id_puesto) {
            $this->abortar('Esa persona ya es Dueño de este puesto.', 400);
        }

        DB::transaction(function () use ($puesto, $nuevo) {
            // El Dueño anterior queda sin puesto (no podrá iniciar sesión hasta que se le asigne otro)
            Usuario::where('id_puesto', $puesto->id_puesto)
                ->where('rol', 'Dueno')
                ->where('id_usuario', '<>', $nuevo->id_usuario)
                ->update(['id_puesto' => null]);

            $nuevo->forceFill(['id_puesto' => $puesto->id_puesto])->save();
        });

        Auditoria::registrar('PUESTO_DUENO_CAMBIADO', (int) $nuevo->id_usuario, (int) $puesto->id_puesto, $puesto->nombre_puesto);

        return response()->json(['ok' => true, 'mensaje' => 'Dueño asignado.']);
    }

    // Activar o desactivar (nunca se borra: se conserva el historial)
    public function cambiarEstado(Request $request, Puesto $puesto): JsonResponse
    {
        $activar = $request->boolean('activo');
        $puesto->forceFill(['estado' => $activar ? 'Activo' : 'Inactivo'])->save();
        Auditoria::registrar($activar ? 'PUESTO_ACTIVADO' : 'PUESTO_DESACTIVADO', null, (int) $puesto->id_puesto, $puesto->nombre_puesto);

        return response()->json([
            'ok' => true,
            'mensaje' => $activar ? 'Puesto activado.' : 'Puesto desactivado.',
        ]);
    }

    // ---------------------------------------------------------------

    private function validar(Request $request): array
    {
        $validador = Validator::make($request->all(), [
            'nombre' => ['required', 'string', 'max:100'],
            'bodega' => ['required', 'string', 'max:100'],
        ], [
            'nombre.required' => 'Escribe el nombre del puesto.',
            'nombre.max' => 'El nombre admite máximo 100 letras.',
            'bodega.required' => 'Escribe la bodega.',
            'bodega.max' => 'La bodega admite máximo 100 letras.',
        ]);

        if ($validador->fails()) {
            $this->abortar($validador->errors()->first(), 400);
        }

        return $validador->validated();
    }

    // Nombre + bodega no se pueden repetir (lo garantiza la BD)
    private function guardar(Puesto $puesto): void
    {
        try {
            $puesto->save();
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                $this->abortar('Este puesto ya existe.', 409);
            }
            throw $e;
        }
    }
}
