<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class EntradaMercanciaController extends Controller
{
    // =====================================================
    // CONSULTAR PRODUCTOS, TIPOS Y PROVEEDORES SUGERIDOS
    // =====================================================

    public function datos(Request $request)
    {
        $puesto = $request->user()->id_puesto;

        abort_unless($puesto, 403);

        $productos = DB::table('productos as p')
            ->where('p.id_puesto', $puesto)
            ->where('p.estado', 'Activo')
            ->select(
                'p.id_producto',
                'p.nombre_producto',
                'p.unidad_comercial',
                'p.descripcion'
            )
            ->selectSub(
                DB::table('detalle_entrada as d')
                    ->join(
                        'inventario_calidades as ic',
                        'ic.id_inventario',
                        '=',
                        'd.id_inventario'
                    )
                    ->join(
                        'entradas_mercancia as e',
                        'e.id_entrada',
                        '=',
                        'd.id_entrada'
                    )
                    ->join(
                        'proveedores as pr',
                        'pr.id_proveedor',
                        '=',
                        'e.id_proveedor'
                    )
                    ->whereColumn('ic.id_producto', 'p.id_producto')
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

        $tipos = DB::table('inventario_calidades')
            ->whereIn(
                'id_producto',
                $productos->pluck('id_producto')
            )
            ->orderBy('calidad')
            ->get([
                'id_inventario',
                'id_producto',
                'calidad',
                'stock_actual',
                'precio_venta_diario',
            ])
            ->groupBy('id_producto');

        foreach ($productos as $producto) {

            $producto->tipos = $tipos
                ->get($producto->id_producto, collect())
                ->map(fn ($tipo) => [
                    'id' => $tipo->id_inventario,
                    'nombre' => $tipo->calidad,
                    'stock' => $tipo->stock_actual,
                    'precio' => $tipo->precio_venta_diario,
                ])
                ->values();
        }

        return response()->json([
            'ok' => true,
            'productos' => $productos,
        ]);
    }

    // =====================================================
    // REGISTRAR ENTRADA DE MERCANCÍA
    // =====================================================

    public function store(Request $request)
    {
        $actor = $request->user();
        $puesto = $actor->id_puesto;

        abort_unless(
            $puesto && in_array(
                $actor->rol,
                ['Dueno', 'Vendedor'],
                true
            ),
            403
        );

        // Validar la información recibida.

        $v = $request->validateWithBag('producto', [

            'id_proveedor' => [
                'required', 'integer', 'min:1'
            ],

            'fecha_entrada' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:today'
            ],

            'documento_soporte' => [
                'required', 'string', 'max:50'
            ],

            'productos' => [
                'required', 'array', 'min:1', 'max:20'
            ],

            'productos.*' => [
                'required',
                'array:id_producto,nombre,unidad_comercial,descripcion,calidades'
            ],

            'productos.*.id_producto' => [
                'nullable', 'integer', 'min:1'
            ],

            'productos.*.nombre' => [
                'nullable', 'string', 'min:2', 'max:100',
                'regex:/\p{L}/u',
                "regex:/^[\p{L}\p{M}\p{N} .,'&()\/\-]+$/u"
            ],

            'productos.*.unidad_comercial' => [
                'required', 'in:Bulto,Roa,Canastilla,Canasta'
            ],

            'productos.*.descripcion' => [
                'nullable', 'string', 'max:1000'
            ],

            'productos.*.calidades' => [
                'required', 'array', 'min:1', 'max:20'
            ],

            'productos.*.calidades.*' => [
                'required', 'array:nombre,cantidad,costo,precio'
            ],

            'productos.*.calidades.*.nombre' => [
                'required', 'string', 'max:100'
            ],

            'productos.*.calidades.*.cantidad' => [
                'required', 'integer', 'min:1', 'max:1000000'
            ],

            'productos.*.calidades.*.costo' => [
                'required', 'integer', 'min:1', 'max:9999999999'
            ],

            'productos.*.calidades.*.precio' => [
                'nullable', 'integer', 'min:1', 'max:9999999999'
            ],

        ], [
            'documento_soporte.required' =>
                'Ingresa una factura o genera una referencia interna.',

            'productos.required' =>
                'Agrega al menos un producto.',

            'productos.*.calidades.required' =>
                'Agrega al menos un tipo de producto.',

            'productos.*.calidades.*.nombre.required' =>
                'Escribe o selecciona el tipo de producto.',

            'productos.*.calidades.*.cantidad.min' =>
                'La cantidad debe ser mayor que cero.',

            'productos.*.calidades.*.costo.min' =>
                'El costo debe ser mayor que cero.',

            'fecha_entrada.before_or_equal' =>
                'La fecha de ingreso no puede ser futura.',
        ]);

        // =================================================
        // CALCULAR EL VALOR TOTAL DE LA COMPRA
        // =================================================

        $total = 0;

        foreach ($v['productos'] as $producto) {

            foreach ($producto['calidades'] as $tipo) {

                $total += (int) $tipo['cantidad']
                    * (int) $tipo['costo'];

                if ($total > 9999999999) {

                    $this->rechazar(
                        'total',
                        'El valor de la compra supera el máximo permitido.'
                    );
                }
            }
        }

        // =================================================
        // TRANSACCIÓN
        // =================================================

        try {

            DB::transaction(function () use (
                $v, $actor, $puesto, $total
            ) {

                // Verificar proveedor.

                $proveedor = DB::table('proveedores')
                    ->where('id_proveedor', $v['id_proveedor'])
                    ->where('id_puesto', $puesto)
                    ->where('estado', 'Activo')
                    ->lockForUpdate()
                    ->first();

                if (!$proveedor) {

                    $this->rechazar(
                        'id_proveedor',
                        'El proveedor no está disponible.'
                    );
                }

                // Crear una sola entrada para toda la compra.

                $idEntrada = DB::table('entradas_mercancia')
                    ->insertGetId([

                        'id_puesto' => $puesto,

                        'id_proveedor' =>
                            $proveedor->id_proveedor,

                        'id_usuario' =>
                            $actor->id_usuario,

                        'documento_soporte' =>
                            trim($v['documento_soporte']),

                        'valor_acordado' => $total,

                        'fecha_entrada' =>
                            $v['fecha_entrada']
                            . ' '
                            . now()->format('H:i:s'),
                    ]);

                $productosProcesados = [];

                foreach ($v['productos'] as $datos) {

                    // Obtener producto existente o crear uno nuevo.

                    $idProducto = $this->obtenerProducto(
                        $datos,
                        $puesto,
                        $actor->id_usuario
                    );

                    if (isset($productosProcesados[$idProducto])) {

                        $this->rechazar(
                            'productos',
                            'No repitas un producto. Agrupa sus tipos en el mismo registro.'
                        );
                    }

                    $productosProcesados[$idProducto] = true;

                    $tiposProcesados = [];

                    foreach ($datos['calidades'] as $tipo) {

                        $idInventario = $this->obtenerTipo(
                            $idProducto,
                            $tipo
                        );

                        if (isset($tiposProcesados[$idInventario])) {

                            $this->rechazar(
                                'tipos',
                                'No repitas un tipo dentro del mismo producto.'
                            );
                        }

                        $tiposProcesados[$idInventario] = true;

                        // Acumular las existencias.

                        $inventario = DB::table('inventario_calidades')
                            ->where('id_inventario', $idInventario)
                            ->lockForUpdate()
                            ->first();

                        $nuevoStock = (int) $inventario->stock_actual
                            + (int) $tipo['cantidad'];

                        if ($nuevoStock > 2147483647) {

                            $this->rechazar(
                                'cantidad',
                                'La cantidad supera la capacidad del inventario.'
                            );
                        }

                        DB::table('inventario_calidades')
                            ->where('id_inventario', $idInventario)
                            ->increment(
                                'stock_actual',
                                (int) $tipo['cantidad']
                            );

                        // Conservar el costo histórico de la compra.

                        DB::table('detalle_entrada')->insert([

                            'id_entrada' => $idEntrada,

                            'id_inventario' => $idInventario,

                            'cantidad_recibida' =>
                                $tipo['cantidad'],

                            'costo_unitario' =>
                                $tipo['costo'],
                        ]);
                    }
                }

                // Registrar auditoría.

                Auditoria::registrar(
                    'ENTRADA_REGISTRADA',
                    $idEntrada,
                    $puesto,
                    'Entrada de mercancía por COP ' . $total,
                    $actor->id_usuario,
                    'entradas_mercancia'
                );

            }, 3);

        } catch (QueryException $e) {

            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {

                return back()
                    ->withInput()
                    ->withErrors([
                        'duplicado' =>
                            'El documento, producto o tipo ya existe. Verifica los datos.'
                    ], 'producto');
            }

            throw $e;
        }

        return back()->with(
            'success',
            'Entrada de mercancía registrada. Inventario actualizado correctamente.'
        );
    }

    // =====================================================
    // OBTENER O CREAR PRODUCTO
    // =====================================================

    private function obtenerProducto(
        array $datos,
        int $puesto,
        int $actor
    ): int {

        // Caso 1: producto existente.

        if (!empty($datos['id_producto'])) {

            $producto = DB::table('productos')
                ->where('id_producto', $datos['id_producto'])
                ->where('id_puesto', $puesto)
                ->lockForUpdate()
                ->first();

            if (!$producto || $producto->estado !== 'Activo') {

                $this->rechazar(
                    'producto',
                    'El producto seleccionado no está disponible.'
                );
            }

            // Completar la unidad de productos antiguos.

            if ($producto->unidad_comercial === null) {

                DB::table('productos')
                    ->where('id_producto', $producto->id_producto)
                    ->update([
                        'unidad_comercial' =>
                            $datos['unidad_comercial']
                    ]);

            } elseif (
                $producto->unidad_comercial
                !== $datos['unidad_comercial']
            ) {

                $this->rechazar(
                    'unidad_comercial',
                    'La unidad comercial no coincide con la del producto existente.'
                );
            }

            return (int) $producto->id_producto;
        }

        // Caso 2: producto completamente nuevo.

        $nombre = $this->normalizar(
            $datos['nombre'] ?? ''
        );

        if (mb_strlen($nombre) < 2) {

            $this->rechazar(
                'nombre',
                'Escribe el nombre del nuevo producto.'
            );
        }

        $existente = DB::table('productos')
            ->where('id_puesto', $puesto)
            ->where('nombre_producto', $nombre)
            ->exists();

        if ($existente) {

            $this->rechazar(
                'nombre',
                'Este producto ya existe. Selecciónalo desde el catálogo.'
            );
        }

        $idProducto = DB::table('productos')->insertGetId([

            'id_puesto' => $puesto,

            'nombre_producto' => $nombre,

            'unidad_comercial' =>
                $datos['unidad_comercial'],

            'descripcion' =>
                $datos['descripcion'] ?? null,

            'estado' => 'Activo',
        ]);

        Auditoria::registrar(
            'PRODUCTO_CREADO',
            $idProducto,
            $puesto,
            'Producto creado desde entrada de mercancía',
            $actor,
            'productos'
        );

        return (int) $idProducto;
    }

    // =====================================================
    // OBTENER O CREAR TIPO DE PRODUCTO
    // =====================================================

    private function obtenerTipo(
        int $idProducto,
        array $datos
    ): int {

        $nombre = $this->normalizar($datos['nombre']);

        if ($nombre === '') {

            $this->rechazar(
                'tipo',
                'Escribe el nombre del tipo.'
            );
        }

        $tipo = DB::table('inventario_calidades')
            ->where('id_producto', $idProducto)
            ->where('calidad', $nombre)
            ->lockForUpdate()
            ->first();

        // Si existe, conservar el precio de venta actual.

        if ($tipo) {

            return (int) $tipo->id_inventario;
        }

        // Si no existe, crear el nuevo tipo.

        if (empty($datos['precio'])) {

            $this->rechazar(
                'precio',
                'Indica el precio de venta para el nuevo tipo.'
            );
        }

        return (int) DB::table('inventario_calidades')
            ->insertGetId([

                'id_producto' => $idProducto,

                'calidad' => $nombre,

                'stock_actual' => 0,

                'stock_minimo' => 0,

                'precio_venta_diario' =>
                    $datos['precio'],
            ]);
    }

    // =====================================================
    // MÉTODOS AUXILIARES
    // =====================================================

    private function normalizar(string $texto): string
    {
        return preg_replace(
            '/\s+/u',
            ' ',
            trim($texto)
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