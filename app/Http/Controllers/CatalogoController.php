<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
        $proveedores = $tipo === 'productos'
            ? DB::table('proveedores')
                ->where('id_puesto', $puesto)
                ->where('estado', 'Activo')
                ->orderBy('nombre_razon_social')
                ->get()
            : collect();

        return view('catalogos.index', [
            'tipo' => $tipo,
            'cfg' => $cfg,
            'registros' => $registros,
            'total' => $total,
            'activos' => $activos,
            'saldoTotal' => $saldos->sum(),
            'esDueno' => $request->user()->rol === 'Dueno',
            'proveedores' => $proveedores,
        ]);
    }

    // =====================================================
    // REGISTRAR
    // =====================================================

    public function store(Request $request, string $tipo)
    {
        if ($tipo === 'productos') {
            return $this->registrarProducto($request);
        }

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
    // REGISTRAR PRODUCTO E INGRESO INICIAL DE MERCANCÍA
    // =====================================================

    private function registrarProducto(Request $request)
    {
        $actor = $request->user();
        $puesto = $actor->id_puesto;

        abort_unless(in_array($actor->rol, ['Dueno', 'Vendedor'], true) && $puesto, 403);

        // Normalizar nombres antes de validar duplicados.
        if (is_string($request->input('nombre'))) {
            $request->merge([
                'nombre' => preg_replace('/\s+/u', ' ', trim($request->input('nombre'))),
            ]);
        }

        $calidades = $request->input('calidades');
        if (is_array($calidades)) {
            foreach ($calidades as &$calidad) {
                if (is_array($calidad) && is_string($calidad['nombre'] ?? null)) {
                    $calidad['nombre'] = preg_replace('/\s+/u', ' ', trim($calidad['nombre']));
                }
            }
            unset($calidad);
            $request->merge(['calidades' => $calidades]);
        }

        $v = $request->validateWithBag('producto', [
            'nombre' => [
                'required', 'string', 'min:2', 'max:100',
                'regex:/\p{L}/u',
                "regex:/^[\p{L}\p{M}\p{N} .,'&()\/\-]+$/u",
            ],
            'unidad_comercial' => ['required', 'in:Bulto,Roa,Canastilla,Canasta'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'id_proveedor' => ['required', 'integer', 'min:1'],
            'fecha_entrada' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'documento_soporte' => ['required', 'string', 'max:50'],
            'calidades' => ['required', 'array', 'min:1', 'max:20'],
            'calidades.*' => ['required', 'array:nombre,cantidad,costo,precio'],
            'calidades.*.nombre' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
            'calidades.*.cantidad' => ['required', 'integer', 'min:1', 'max:1000000'],
            'calidades.*.costo' => ['required', 'integer', 'min:1', 'max:9999999999'],
            'calidades.*.precio' => ['required', 'integer', 'min:1', 'max:9999999999'],
        ], [
            'nombre.regex' => 'El nombre del producto contiene caracteres no permitidos.',

            'calidades.*.nombre.distinct' =>
                'Los tipos del producto no pueden repetirse.',

                'documento_soporte.required' =>
                'Ingresa un documento o genera una referencia interna.',

                'calidades.required' =>
                'Debes registrar al menos un tipo de producto.',

                'calidades.*.nombre.required' =>
                'Escribe el nombre del tipo de producto.',


            'calidades.*.cantidad.min' => 'La cantidad recibida debe ser mayor que cero.',
            'calidades.*.costo.min' => 'El costo unitario debe ser mayor que cero.',
            'calidades.*.precio.min' => 'El precio de venta debe ser mayor que cero.',
            'fecha_entrada.before_or_equal' => 'La fecha de ingreso no puede ser futura.',
        ]);

        // Pesos enteros; el total se calcula en servidor, nunca desde el navegador.
        // DECIMAL(12,2) admite hasta 9.999.999.999,99 COP.
        $total = 0;
        foreach ($v['calidades'] as $calidad) {
            $total += (int) $calidad['cantidad'] * (int) $calidad['costo'];
            if ($total > 9999999999) {
                return back()->withInput()->withErrors([
                    'total' => 'El valor de la compra supera el máximo permitido.',
                ], 'producto');
            }
        }

        try {
            DB::transaction(function () use ($v, $actor, $puesto, $total) {
                // El proveedor debe estar activo y pertenecer al mismo puesto.
                $proveedor = DB::table('proveedores')
                    ->where('id_proveedor', $v['id_proveedor'])
                    ->where('id_puesto', $puesto)
                    ->where('estado', 'Activo')
                    ->lockForUpdate()
                    ->first();

                if (!$proveedor) {
                    throw ValidationException::withMessages([
                        'id_proveedor' => 'El proveedor no está disponible en tu puesto.',
                    ])->errorBag('producto');
                }

                $idProducto = DB::table('productos')->insertGetId([
                    'id_puesto' => $puesto,
                    'nombre_producto' => $v['nombre'],
                    'unidad_comercial' => $v['unidad_comercial'],
                    'descripcion' => $v['descripcion'] ?? null,
                    'estado' => 'Activo',
                ]);

                $idEntrada = DB::table('entradas_mercancia')->insertGetId([
                    'id_puesto' => $puesto,
                    'id_proveedor' => $proveedor->id_proveedor,
                    'id_usuario' => $actor->id_usuario,
                    'documento_soporte' => $v['documento_soporte'],
                    // MVP: valor acordado igual al total calculado; compra pendiente de pago.
                    'valor_acordado' => $total,
                    'fecha_entrada' => $v['fecha_entrada'] . ' ' . now()->format('H:i:s'),
                ]);

                foreach ($v['calidades'] as $calidad) {
                    $idInventario = DB::table('inventario_calidades')->insertGetId([
                        'id_producto' => $idProducto,
                        'calidad' => $calidad['nombre'],
                        'stock_actual' => $calidad['cantidad'],
                        'stock_minimo' => 0,
                        'precio_venta_diario' => $calidad['precio'],
                    ]);

                    DB::table('detalle_entrada')->insert([
                        'id_entrada' => $idEntrada,
                        'id_inventario' => $idInventario,
                        'cantidad_recibida' => $calidad['cantidad'],
                        'costo_unitario' => $calidad['costo'],
                    ]);
                }

                Auditoria::registrar(
                    'PRODUCTO_CREADO', $idProducto, $puesto,
                    'Registro de producto con inventario inicial',
                    $actor->id_usuario, 'productos'
                );

                Auditoria::registrar(
                    'ENTRADA_REGISTRADA', $idEntrada, $puesto,
                    'Entrada inicial de mercancía',
                    $actor->id_usuario, 'entradas_mercancia'
                );
            });
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                return back()->withInput()->withErrors([
                    'duplicado' => 'El producto, calidad o documento ya existe.',
                ], 'producto');
            }

            throw $e;
        }

        return back()->with('success', 'Producto y mercancía registrados correctamente.');
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
