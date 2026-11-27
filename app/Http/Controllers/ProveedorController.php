<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondeJson;
use App\Models\Auditoria;
use App\Models\Proveedor;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Proveedores del puesto.
 * - El Dueño los crea, edita y activa/desactiva.
 * - El Vendedor solo consulta los activos (los necesita al registrar facturas de compra).
 * Cada consulta se filtra por el puesto de quien está en sesión: nadie ve proveedores de otro puesto.
 */
class ProveedorController extends Controller
{
    use RespondeJson;

    // Pantalla de gestión (solo Dueño: lo protege la ruta)
    public function index(): View
    {
        return view('operativo.proveedores');
    }

    // Lista del puesto. El Vendedor solo recibe los activos.
    public function datos(Request $request): JsonResponse
    {
        $actor = $request->user();

        $consulta = Proveedor::where('id_puesto', $actor->id_puesto);

        // Quien no gestiona proveedores (Vendedor normal) solo ve los activos
        if (!$actor->puede('gestionar_proveedores')) {
            $consulta->where('estado', 'Activo');
        }

        $proveedores = $consulta
            ->orderBy('estado')                 // Activos primero
            ->orderBy('nombre_razon_social')
            ->get()
            ->map(fn (Proveedor $p) => $p->paraPantalla());

        return response()->json([
            'ok' => true,
            'proveedores' => $proveedores,
            'puede_desactivar' => $actor->rol === 'Dueno',   // nunca se delega
        ]);
    }

    public function store(Request $request): JsonResponse
    {
    $actor = $request->user();
    $datos = $this->validar($request);

    if (!$request->boolean('consentimiento')) {
        $this->abortar(
            'Confirma que el proveedor autorizó el tratamiento de sus datos.',
            400
        );
    }

    $proveedor = DB::transaction(function () use ($actor, $datos) {

        $p = (new Proveedor())->forceFill([
            'id_puesto' => $actor->id_puesto,
            'nombre_razon_social' => $datos['nombre'],
            'telefono' => $datos['telefono'] ?? null,
            'contacto_principal' => $datos['contacto'] ?? null,
            'estado' => 'Activo',
            'consentimiento_datos' => now(),
        ]);

        $this->guardar($p);

        Auditoria::registrar(
            'PROVEEDOR_CREADO',
            $p->id_proveedor,
            $p->id_puesto,
            $p->nombre_razon_social,
            null,
            'proveedores',
            null,
            $this->datosAuditoria($p)
        );

        return $p;
    });

    return response()->json([
        'ok' => true,
        'mensaje' => 'Proveedor registrado.',
        'proveedor' => $proveedor->paraPantalla(),
    ], 201);
}
    public function update(Request $request, Proveedor $proveedor): JsonResponse
    {
        $datos = $this->validar($request);

        DB::transaction(function () use ($request, $proveedor, $datos) {
            $p = $this->delPuesto($request, $proveedor);

            // Proveedores viejos sin autorización: se pide al editarlos
            if ($p->consentimiento_datos === null && !$request->boolean('consentimiento')) {
                $this->abortar('Confirma que el proveedor autorizó el tratamiento de sus datos.', 400);
            }

            $anterior = $this->datosAuditoria($p);

            $p->forceFill([
                'nombre_razon_social' => $datos['nombre'],
                'telefono' => $datos['telefono'] ?? null,
                'contacto_principal' => $datos['contacto'] ?? null,
                'consentimiento_datos' => $p->consentimiento_datos ?? now(),
            ]);

            $this->guardar($p);

            Auditoria::registrar(
                'PROVEEDOR_EDITADO',
                $p->id_proveedor,
                $p->id_puesto,
                $p->nombre_razon_social,
                null,
                'proveedores',
                $anterior,
                $this->datosAuditoria($p)
            );
        });

        return response()->json(['ok' => true, 'mensaje' => 'Cambios guardados.']);
    }

    // Activar o desactivar (nunca se borra)
    public function cambiarEstado(Request $request, Proveedor $proveedor): JsonResponse
    {
        $validador = Validator::make($request->all(), ['activo' => ['required', 'boolean']]);

        if ($validador->fails()) {
            $this->abortar('Estado no válido.', 400);
        }

        $activar = $request->boolean('activo');

        DB::transaction(function () use ($request, $proveedor, $activar) {
            $p = $this->delPuesto($request, $proveedor);

            $anterior = $p->estado;
            $p->estado = $activar ? 'Activo' : 'Inactivo';
            $p->save();

            Auditoria::registrar(
                $activar ? 'PROVEEDOR_ACTIVADO' : 'PROVEEDOR_DESACTIVADO',
                $p->id_proveedor,
                $p->id_puesto,
                $p->nombre_razon_social,
                null,
                'proveedores',
                ['estado' => $anterior],
                ['estado' => $p->estado]
            );
        });

        return response()->json([
            'ok' => true,
            'mensaje' => $activar ? 'Proveedor activado.' : 'Proveedor desactivado.',
        ]);
    }

    // ---------------------------------------------------------------

    // Bloquea la fila y verifica que el proveedor sea del puesto de quien hace la petición.
    // Si es de otro puesto responde "no existe" (no se revela que existe).
    private function delPuesto(Request $request, Proveedor $proveedor): Proveedor
    {
        $p = Proveedor::lockForUpdate()->find($proveedor->id_proveedor);

        if (!$p || (int) $p->id_puesto !== (int) $request->user()->id_puesto) {
            $this->abortar('Ese proveedor no existe.', 404);
        }

        return $p;
    }

    private function validar(Request $request): array
    {
        $validador = Validator::make($request->all(), [
            'nombre' => ['required', 'string', 'max:150', 'regex:/^[\pL\pN .,&#-]+$/u'],
            'telefono' => ['nullable', 'regex:/^[0-9]{7,10}$/'],
            'contacto' => ['nullable', 'string', 'max:100', 'regex:/^[\pL ]+$/u'],
        ], [
            'nombre.required' => 'Escribe el nombre del proveedor.',
            'nombre.max' => 'El nombre es muy largo (máximo 150 letras).',
            'nombre.regex' => 'El nombre solo puede tener letras, números, espacios y . , - & #',
            'contacto.regex' => 'El contacto solo puede tener letras y espacios.',
            'telefono.regex' => 'El teléfono debe tener solo números (7 a 10 dígitos).',
            'contacto.max' => 'El contacto es muy largo (máximo 100 letras).',
        ]);

        if ($validador->fails()) {
            $this->abortar($validador->errors()->first(), 400);
        }

        $datos = $validador->validated();
        $datos['nombre'] = trim($datos['nombre']);

        return $datos;
    }

    // El nombre no se puede repetir dentro del mismo puesto (lo garantiza la BD)
    private function guardar(Proveedor $p): void
    {
        try {
            $p->save();
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                $this->abortar('Ya tienes un proveedor con ese nombre.', 409);
            }
            throw $e;
        }
    }

    private function datosAuditoria(Proveedor $p): array
    {
        return $p->only(['nombre_razon_social', 'telefono', 'contacto_principal', 'estado']);
    }
}
