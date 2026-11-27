<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Services\InventarioService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EntradaMercanciaController extends Controller
{
    private const MAXIMO = 9999999999;
    private const MAX_LINEAS = 150;

    public function __construct(
        private InventarioService $inventario
    ) {}

    // Catálogo, tipos, presentaciones y proveedor sugerido.

    public function datos(Request $request): JsonResponse
    {
        $actor = $request->user();

        abort_unless($actor->id_puesto && in_array(
            $actor->rol, ['Dueno', 'Vendedor'], true
        ), 403);

        $productos = DB::table('productos as p')
            ->where('p.id_puesto', $actor->id_puesto)
            ->where('p.estado', 'Activo')
            ->select('p.id_producto', 'p.nombre_producto', 'p.descripcion')
            ->selectSub(
                DB::table('detalle_entrada as d')
                    ->join('inventario_calidades as i', 'i.id_inventario', '=', 'd.id_inventario')
                    ->join('tipos_producto as t', 't.id_tipo', '=', 'i.id_tipo')
                    ->join('entradas_mercancia as e', 'e.id_entrada', '=', 'd.id_entrada')
                    ->join('proveedores as pr', 'pr.id_proveedor', '=', 'e.id_proveedor')
                    ->whereColumn('t.id_producto', 'p.id_producto')
                    ->whereColumn('e.id_puesto', 'p.id_puesto')
                    ->whereColumn('pr.id_puesto', 'p.id_puesto')
                    ->where('pr.estado', 'Activo')
                    ->orderByDesc('e.fecha_entrada')
                    ->orderByDesc('e.id_entrada')
                    ->select('e.id_proveedor')
                    ->limit(1),
                'ultimo_proveedor'
            )
            ->orderBy('p.nombre_producto')
            ->get();

        $tipos = DB::table('tipos_producto')
            ->whereIn('id_producto', $productos->pluck('id_producto'))
            ->orderBy('nombre_tipo')
            ->get(['id_tipo', 'id_producto', 'nombre_tipo']);

        $presentaciones = DB::table('inventario_calidades')
            ->whereIn('id_tipo', $tipos->pluck('id_tipo'))
            ->orderBy('unidad_comercial')
            ->get([
                'id_inventario',
                'id_tipo',
                'unidad_comercial',
                'stock_actual',
                'precio_venta_diario'
            ])
            ->groupBy('id_tipo');

        $tiposPorProducto = $tipos->groupBy('id_producto');

        foreach ($productos as $producto) {

            $producto->tipos = $tiposPorProducto
                ->get($producto->id_producto, collect())
                ->map(fn ($tipo) => [

                    'id' => $tipo->id_tipo,
                    'nombre' => $tipo->nombre_tipo,

                    'presentaciones' => $presentaciones
                        ->get($tipo->id_tipo, collect())
                        ->map(fn ($p) => [

                            'id' => $p->id_inventario,
                            'unidad_comercial' => $p->unidad_comercial,
                            'stock' => $p->stock_actual,
                            'precio' => $p->precio_venta_diario,

                        ])->values(),

                ])->values();
        }

        return response()->json([
            'ok' => true,
            'productos' => $productos,
            'unidades' => InventarioService::UNIDADES,
        ]);
    }

    // Registrar entrada de mercancía.

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();

        abort_unless($actor->id_puesto && in_array(
            $actor->rol, ['Dueno', 'Vendedor'], true
        ), 403);

        // 1. Validar información.

        $v = $request->validateWithBag('producto', [

            'id_proveedor' => ['required', 'integer', 'min:1'],

            'fecha_entrada' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:today'
            ],

            'documento_soporte' => [
                'required', 'string', 'max:50'
            ],

            'valor_acordado' => [
                'nullable', 'integer', 'min:1',
                'max:' . self::MAXIMO
            ],

            'productos' => [
                'required', 'array', 'min:1', 'max:20'
            ],

            'productos.*' => [
                'required',
                'array:id_producto,nombre,descripcion,calidades'
            ],

            'productos.*.id_producto' => [
                'nullable', 'integer', 'min:1'
            ],

            'productos.*.nombre' => [
                'nullable', 'string', 'max:100'
            ],

            'productos.*.descripcion' => [
                'nullable', 'string', 'max:1000'
            ],

            'productos.*.calidades' => [
                'required', 'array', 'min:1', 'max:20'
            ],

            'productos.*.calidades.*' => [
                'required',
                'array:nombre,unidad_comercial,cantidad,costo,precio'
            ],

            'productos.*.calidades.*.nombre' => [
                'required', 'string', 'max:100'
            ],

            'productos.*.calidades.*.unidad_comercial' => [
                'required',
                Rule::in(InventarioService::UNIDADES)
            ],

            'productos.*.calidades.*.cantidad' => [
                'required', 'integer', 'min:1', 'max:1000000'
            ],

            'productos.*.calidades.*.costo' => [
                'required', 'integer', 'min:1',
                'max:' . self::MAXIMO
            ],

            'productos.*.calidades.*.precio' => [
                'nullable', 'integer', 'min:1',
                'max:' . self::MAXIMO
            ],

            // Se habilitará mediante el servicio de egresos.
            'pago_inmediato' => ['prohibited'],

        ], [

            'documento_soporte.required' =>
                'Ingresa una factura o genera una referencia interna.',

            'productos.required' =>
                'Agrega al menos un producto.',

            'productos.*.calidades.required' =>
                'Agrega al menos una presentación.',

            'productos.*.calidades.*.unidad_comercial.required' =>
                'Selecciona la unidad comercial.',

            'productos.*.calidades.*.cantidad.min' =>
                'La cantidad debe ser mayor que cero.',

            'productos.*.calidades.*.costo.min' =>
                'El costo debe ser mayor que cero.',

            'fecha_entrada.before_or_equal' =>
                'La fecha no puede ser futura.',

            'pago_inmediato.prohibited' =>
                'Registra el pago desde Egresos.',
        ]);

        // 2. Calcular total y valor acordado.

        $total = 0;
        $lineas = 0;

        foreach ($v['productos'] as $producto) {

            foreach ($producto['calidades'] as $tipo) {

                $lineas++;

                $total += (int) $tipo['cantidad']
                    * (int) $tipo['costo'];

                if (
                    $lineas > self::MAX_LINEAS ||
                    $total > self::MAXIMO
                ) {
                    $this->rechazar(
                        'total',
                        'La compra supera el máximo de líneas o valor permitido.'
                    );
                }
            }
        }

        $acordado = (int) ($v['valor_acordado'] ?? $total);

        $documento = trim($v['documento_soporte']);

        if ($documento === '') {
            $this->rechazar(
                'documento_soporte',
                'Ingresa un documento válido.'
            );
        }

        // 3. Registrar la operación de forma atómica.

        try {

            DB::transaction(function () use (
                $actor, $v, $total, $acordado, $documento
            ) {

                $this->inventario->bloquearPuesto($actor);

                $proveedor = DB::table('proveedores')
                    ->where('id_proveedor', $v['id_proveedor'])
                    ->where('id_puesto', $actor->id_puesto)
                    ->where('estado', 'Activo')
                    ->lockForUpdate()
                    ->first();

                if (!$proveedor) {

                    $this->rechazar(
                        'id_proveedor',
                        'El proveedor no está disponible en tu puesto.'
                    );
                }

                // Crear encabezado de la compra.

                $idEntrada = DB::table('entradas_mercancia')
                    ->insertGetId([

                        'id_puesto' => $actor->id_puesto,

                        'id_proveedor' => $proveedor->id_proveedor,

                        'id_usuario' => $actor->id_usuario,

                        'documento_soporte' => $documento,

                        'valor_acordado' => $acordado,

                        'fecha_entrada' => $v['fecha_entrada']
                            . ' ' . now()->format('H:i:s'),
                    ]);

                $productosProcesados = [];
                $presentacionesProcesadas = [];

                foreach ($v['productos'] as $datos) {

                    $producto = $this->inventario
                        ->obtenerProducto($actor, $datos);

                    $idProducto = $producto['id'];

                    if (isset($productosProcesados[$idProducto])) {

                        $this->rechazar(
                            'productos',
                            'Agrupa las presentaciones del mismo producto en una sola tarjeta.'
                        );
                    }

                    $productosProcesados[$idProducto] = true;

                    foreach ($datos['calidades'] as $tipo) {

                        // Delegar el incremento de existencias.

                        $idInventario = $this->inventario
                            ->recibir($actor, $idProducto, $tipo);

                        if (isset($presentacionesProcesadas[$idInventario])) {

                            $this->rechazar(
                                'tipos',
                                'No repitas la misma combinación de tipo y unidad comercial.'
                            );
                        }

                        $presentacionesProcesadas[$idInventario] = true;

                        // Registrar el costo histórico.

                        DB::table('detalle_entrada')->insert([

                            'id_entrada' => $idEntrada,

                            'id_inventario' => $idInventario,

                            'cantidad_recibida' => $tipo['cantidad'],

                            'costo_unitario' => $tipo['costo'],
                        ]);
                    }
                }

                // Registrar trazabilidad.

                Auditoria::registrar(
                    'ENTRADA_REGISTRADA',
                    $idEntrada,
                    $actor->id_puesto,
                    'Entrada de mercancía: ' . $documento,
                    $actor->id_usuario,
                    'entradas_mercancia',
                    null,
                    [
                        'id_proveedor' => $proveedor->id_proveedor,
                        'documento_soporte' => $documento,
                        'total_calculado' => $total,
                        'valor_acordado' => $acordado,
                        'presentaciones' => array_keys(
                            $presentacionesProcesadas
                        ),
                    ]
                );

            }, 3);

        } catch (QueryException $e) {

            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {

                $mensaje = str_contains(
                    (string) ($e->errorInfo[2] ?? ''),
                    'uk_entrada_doc'
                )
                    ? 'Este documento ya está registrado para el proveedor.'
                    : 'Existe un registro duplicado. Recarga el catálogo y verifica los datos.';

                return back()
                    ->withInput()
                    ->withErrors([
                        'duplicado' => $mensaje,
                    ], 'producto');
            }

            throw $e;
        }

        return back()->with(
            'success',
            'Entrada registrada. Inventario actualizado y obligación pendiente de pago.'
        );
    }

    private function rechazar(
        string $campo,
        string $mensaje
    ): never {

        throw ValidationException::withMessages([
            $campo => $mensaje,
        ])->errorBag('producto');
    }
}