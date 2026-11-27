<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class CatalogoController extends Controller
{
    private const CATALOGOS = [
        'proveedores' => [
            'id' => 'id_proveedor',
            'nombre' => 'nombre_razon_social',
            'titulo' => 'Proveedores',
            'singular' => 'Proveedor',
        ],
        'clientes' => [
            'id' => 'id_cliente',
            'nombre' => 'nombre_completo',
            'titulo' => 'Clientes',
            'singular' => 'Cliente',
        ],
        'productos' => [
            'id' => 'id_producto',
            'nombre' => 'nombre_producto',
            'titulo' => 'Productos',
            'singular' => 'Producto',
        ],
    ];

    // =====================================================
    // CONSULTAR CATÁLOGOS
    // =====================================================

    public function index(Request $request, string $tipo)
    {
        $cfg = $this->configuracion($tipo);
        $puesto = $request->user()->id_puesto;

        $consulta = DB::table($tipo)
            ->where('id_puesto', $puesto);

        // Filtro de búsqueda
        $buscar = trim((string) $request->query('buscar', ''));

        if ($buscar !== '') {
            $consulta->where(function ($q) use ($cfg, $buscar, $tipo) {

                $q->where(
                    $cfg['nombre'], 'like', "%{$buscar}%"
                );

                if ($tipo !== 'productos') {
                    $q->orWhere(
                        'telefono', 'like', "%{$buscar}%"
                    );
                }
            });
        }

        // Filtro por estado
        $estado = $request->query('estado');

        if (in_array($estado, ['Activo', 'Inactivo'], true)) {
            $consulta->where('estado', $estado);
        }

        // =================================================
        // SALDOS FINANCIEROS
        // =================================================

        $saldos = collect();

        // Cuentas por pagar a proveedores
        if ($tipo === 'proveedores') {

            $saldos = DB::table('v_entradas_saldo')
                ->where('id_puesto', $puesto)
                ->where('saldo_pendiente', '>', 0)
                ->selectRaw(
                    'id_proveedor, SUM(saldo_pendiente) AS saldo'
                )
                ->groupBy('id_proveedor')
                ->pluck('saldo', 'id_proveedor');
        }

        // Cuentas por cobrar a clientes
        if ($tipo === 'clientes') {

            $saldos = DB::table('v_creditos_saldo')
                ->where('id_puesto', $puesto)
                ->where('saldo_pendiente', '>', 0)
                ->where('estado_venta', '!=', 'Anulada')
                ->selectRaw(
                    'id_cliente, SUM(saldo_pendiente) AS saldo'
                )
                ->groupBy('id_cliente')
                ->pluck('saldo', 'id_cliente');
        }

        // =================================================
        // OBTENER Y PAGINAR REGISTROS
        // =================================================

        $registros = $consulta
            ->orderBy($cfg['nombre'])
            ->paginate(15)
            ->withQueryString();

        // Asociar saldo a cada registro
        $registros->through(function ($fila) use ($cfg, $saldos) {

            $fila->saldo = $saldos->get(
                $fila->{$cfg['id']}, 0
            );

            return $fila;
        });

        // =================================================
        // INDICADORES DEL CATÁLOGO
        // =================================================

        $total = DB::table($tipo)
            ->where('id_puesto', $puesto)
            ->count();

        $activos = DB::table($tipo)
            ->where('id_puesto', $puesto)
            ->where('estado', 'Activo')
            ->count();

        // =================================================
        // RETORNAR LA VISTA
        // =================================================

        return view('catalogos.index', [
            'tipo' => $tipo,
            'cfg' => $cfg,
            'registros' => $registros,
            'total' => $total,
            'activos' => $activos,
            'saldoTotal' => $saldos->sum(),
            'esDueno' => $request->user()->rol === 'Dueno',
        ]);
    }

    // =====================================================
    // REGISTRAR
    // =====================================================

    public function store(Request $request, string $tipo)
    {
        $cfg = $this->configuracion($tipo);
        $puesto = $request->user()->id_puesto;

        $datos = $this->validar($request, $tipo, true);

        $datos['id_puesto'] = $puesto;
        $datos['estado'] = 'Activo';

        try {

            DB::transaction(function () use (
                $tipo, $cfg, $datos, $puesto
            ) {

                $id = DB::table($tipo)->insertGetId(
                    $datos, $cfg['id']
                );

                Auditoria::registrar(
                    strtoupper($cfg['singular']) . '_CREADO',
                    $id,
                    $puesto,
                    'Registro de catálogo',
                    null,
                    $tipo
                );
            });

        } catch (QueryException $e) {
            return $this->errorDuplicado($e);
        }

        return back()->with(
            'success',
            $cfg['singular'] . ' registrado correctamente.'
        );
    }

    // =====================================================
    // ACTUALIZAR
    // =====================================================

    public function update(
        Request $request,
        string $tipo,
        int $id
    ) {
        $cfg = $this->configuracion($tipo);
        $puesto = $request->user()->id_puesto;

        // Solo el dueño puede editar clientes y productos
        if ($tipo !== 'proveedores') {

            abort_unless(
                $request->user()->rol === 'Dueno', 403
            );
        }

        $datos = $this->validar($request, $tipo, false);

        try {

            DB::transaction(function () use (
                $tipo, $cfg, $id, $puesto, $datos
            ) {

                $consulta = DB::table($tipo)
                    ->where($cfg['id'], $id)
                    ->where('id_puesto', $puesto);

                $anterior = (clone $consulta)
                    ->lockForUpdate()
                    ->first();

                abort_if(!$anterior, 404);

                $consulta->update($datos);

                Auditoria::registrar(
                    strtoupper($cfg['singular']) . '_EDITADO',
                    $id,
                    $puesto,
                    'Actualización de catálogo',
                    null,
                    $tipo
                );
            });

        } catch (QueryException $e) {
            return $this->errorDuplicado($e);
        }

        return back()->with(
            'success',
            'Cambios guardados correctamente.'
        );
    }

    // =====================================================
    // ACTIVAR O DESACTIVAR
    // =====================================================

    public function cambiarEstado(
        Request $request,
        string $tipo,
        int $id
    ) {
        $cfg = $this->configuracion($tipo);

        // Solo el dueño puede cambiar el estado
        abort_unless(
            $request->user()->rol === 'Dueno', 403
        );

        $puesto = $request->user()->id_puesto;

        $request->validate([
            'estado' => ['required', 'in:Activo,Inactivo'],
        ]);

        DB::transaction(function () use (
            $request, $tipo, $cfg, $id, $puesto
        ) {

            $consulta = DB::table($tipo)
                ->where($cfg['id'], $id)
                ->where('id_puesto', $puesto);

            $fila = (clone $consulta)
                ->lockForUpdate()
                ->first();

            abort_if(!$fila, 404);

            $estado = $request->input('estado');

            $consulta->update([
                'estado' => $estado
            ]);

            Auditoria::registrar(
                strtoupper($cfg['singular'])
                    . ($estado === 'Activo'
                        ? '_ACTIVADO'
                        : '_DESACTIVADO'),
                $id,
                $puesto,
                'Cambio de estado',
                null,
                $tipo,
                ['estado' => $fila->estado],
                ['estado' => $estado]
            );
        });

        return back()->with(
            'success',
            'Estado actualizado correctamente.'
        );
    }

    // =====================================================
    // CONFIGURACIÓN DEL CATÁLOGO
    // =====================================================

    private function configuracion(string $tipo): array
    {
        abort_unless(
            isset(self::CATALOGOS[$tipo]), 404
        );

        return self::CATALOGOS[$tipo];
    }

    // =====================================================
    // VALIDACIÓN DE DATOS
    // =====================================================

    private function validar(
        Request $request,
        string $tipo,
        bool $creando
    ): array {

        $cfg = $this->configuracion($tipo);
        $esDueno = $request->user()->rol === 'Dueno';

        // Normalizar espacios del nombre
        if (is_string($request->input('nombre'))) {

            $request->merge([
                'nombre' => preg_replace(
                    '/\s+/u',
                    ' ',
                    trim($request->input('nombre'))
                ),
            ]);
        }

        $reglas = [
            'nombre' => [
                'required', 
                'string',
                'min:2',
                $tipo === 'productos' ? 'max:100' : 'max:150',

                // Debe contener al menos una letra
                'regex:/\p{L}/u',

                // Caracteres permitidos según el catálogo
                $tipo === 'clientes'
                    ? "regex:/^[\p{L}\p{M} .'\-]+$/u"
                    : "regex:/^[\p{L}\p{M}\p{N} .,'&()\/\-]+$/u",
            ],
        ];

        // Teléfono de proveedores y clientes
        if ($tipo !== 'productos') {

            $reglas['telefono'] = [
                $tipo === 'clientes' ? 'required' : 'nullable',
                'string',
                'max:20',
                'regex:/^\+?[0-9]{7,19}$/'
            ];
        }

        // Descripción de productos
        if ($tipo === 'productos') {

            $reglas['descripcion'] = [
                'nullable',
                'string',
                'max:1000'
            ];
        }

        // Consentimiento de tratamiento de datos
        if ($creando && $tipo !== 'productos') {

            $reglas['consentimiento'] = ['accepted'];
        }

        // Plazo de crédito configurable por el dueño
        if ($tipo === 'clientes' && $esDueno) {

            $reglas['plazo'] = [
                'required',
                'integer',
                'min:1',
                'max:3650'
            ];
        }

        $v = $request->validate($reglas, [
            'nombre.required' => 'Escribe el nombre.',
            'nombre.max' => 'El nombre supera la longitud permitida.',
            'telefono.required' => 'Escribe el teléfono.',
            'telefono.regex' => 'Ingresa un número de teléfono válido.',
            'consentimiento.accepted' =>
                'Debes confirmar la autorización de tratamiento de datos.',
            'plazo.required' => 'Indica el plazo de crédito.',
            'plazo.min' => 'El plazo debe ser de al menos un día.',
            'plazo.max' => 'El plazo supera el máximo permitido.',
            'nombre.regex' => 'El nombre contiene caracteres no permitidos.',
            'nombre.min' =>'El nombre debe contener al menos dos caracteres.',
        ]);
            

        // Preparar datos para MySQL
        $datos = [
            $cfg['nombre'] => $v['nombre'],
        ];

        if ($tipo !== 'productos') {
            $datos['telefono'] = $v['telefono'] ?? null;
        }

        if ($tipo === 'productos') {
            $datos['descripcion'] = $v['descripcion'] ?? null;
        }

        if ($tipo === 'clientes') {

            if ($esDueno) {
                $datos['plazo_credito_dias'] = $v['plazo'];

            } elseif ($creando) {
                $datos['plazo_credito_dias'] = 15;
            }
        }

        if ($creando && $tipo !== 'productos') {
            $datos['consentimiento_datos'] = now();
        }

        return $datos;
    }

    // =====================================================
    // MANEJO DE REGISTROS DUPLICADOS
    // =====================================================

    private function errorDuplicado(QueryException $e)
    {
        if ((int) ($e->errorInfo[1] ?? 0) === 1062) {

            return back()
                ->withInput()
                ->withErrors([
                    'duplicado' =>
                        'Ya existe un registro con esos datos en este puesto.'
                ]);
        }

        throw $e;
    }
}
