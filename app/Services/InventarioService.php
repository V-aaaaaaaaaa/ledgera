<?php

namespace App\Services;

use App\Models\Auditoria;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class InventarioService
{
    public const UNIDADES = [
        'Unidad',
        'Bulto',
        'Roa',
        'Canastilla',
        'Canasta',
    ];

    // =====================================================
    // BLOQUEAR PUESTO
    // =====================================================

    public function bloquearPuesto(Usuario $actor): void
    {
        $puesto = $this->puesto($actor);

        $registro = DB::table('puestos')
            ->where('id_puesto', $puesto)
            ->lockForUpdate()
            ->first();

        abort_unless(
            $registro && $registro->estado === 'Activo',
            403
        );
    }

    // =====================================================
    // OBTENER O CREAR PRODUCTO
    // =====================================================

    public function obtenerProducto(
        Usuario $actor,
        array $datos,
        bool $reutilizarPorNombre = false
    ): array {

        $puesto = $this->puesto($actor);

        if (!empty($datos['id_producto'])) {

            $producto = $this->productoActivo(
                $actor,
                (int) $datos['id_producto']
            );

            return [
                'id' => (int) $producto->id_producto,
                'creado' => false,
            ];
        }

        $nombre = $this->normalizar($datos['nombre'] ?? '');

        $this->validarNombre($nombre, 'nombre', 2);

        $existente = DB::table('productos')
            ->where('id_puesto', $puesto)
            ->where('nombre_producto', $nombre)
            ->lockForUpdate()
            ->first();

        if ($existente) {

            if ($existente->estado !== 'Activo') {
                $this->rechazar(
                    'producto',
                    'El producto existe, pero está inactivo.'
                );
            }

            if (!$reutilizarPorNombre) {
                $this->rechazar(
                    'producto',
                    'El producto ya existe. Selecciónalo del catálogo.'
                );
            }

            return [
                'id' => (int) $existente->id_producto,
                'creado' => false,
            ];
        }

        $id = DB::table('productos')->insertGetId([
            'id_puesto' => $puesto,
            'nombre_producto' => $nombre,
            'descripcion' => $datos['descripcion'] ?? null,
            'estado' => 'Activo',
        ]);

        Auditoria::registrar(
            'PRODUCTO_CREADO',
            $id,
            $puesto,
            'Registro de producto: ' . $nombre,
            $actor->id_usuario,
            'productos',
            null,
            ['nombre_producto' => $nombre]
        );

        return [
            'id' => (int) $id,
            'creado' => true,
        ];
    }

    // =====================================================
    // OBTENER O CREAR TIPO
    // =====================================================

    public function obtenerTipo(
        Usuario $actor,
        int $idProducto,
        string $nombre
    ): array {

        $puesto = $this->puesto($actor);

        $this->productoActivo($actor, $idProducto);

        $nombre = $this->normalizar($nombre);

        $this->validarNombre($nombre, 'tipo');

        $tipo = DB::table('tipos_producto')
            ->where('id_producto', $idProducto)
            ->where('nombre_tipo', $nombre)
            ->lockForUpdate()
            ->first();

        if ($tipo) {

            return [
                'id' => (int) $tipo->id_tipo,
                'creado' => false,
            ];
        }

        $id = DB::table('tipos_producto')->insertGetId([
            'id_producto' => $idProducto,
            'nombre_tipo' => $nombre,
        ]);

        Auditoria::registrar(
            'TIPO_CREADO',
            $id,
            $puesto,
            'Registro de tipo: ' . $nombre,
            $actor->id_usuario,
            'tipos_producto',
            null,
            [
                'id_producto' => $idProducto,
                'nombre_tipo' => $nombre,
            ]
        );

        return [
            'id' => (int) $id,
            'creado' => true,
        ];
    }

    // =====================================================
    // RECIBIR MERCANCÍA
    // =====================================================

    public function recibir(
        Usuario $actor,
        int $idProducto,
        array $datos
    ): int {

        $puesto = $this->puesto($actor);

        $unidad = $datos['unidad_comercial'];

        $cantidad = (int) $datos['cantidad'];

        $precio = isset($datos['precio'])
            ? (int) $datos['precio']
            : null;

        if (!in_array($unidad, self::UNIDADES, true)) {
            $this->rechazar(
                'unidad_comercial',
                'Selecciona una unidad comercial válida.'
            );
        }

        if ($cantidad <= 0 || $cantidad > 1000000) {
            $this->rechazar(
                'cantidad',
                'La cantidad recibida no es válida.'
            );
        }

        // Obtener o crear el tipo.

        $tipo = $this->obtenerTipo(
            $actor,
            $idProducto,
            $datos['nombre']
        );

        $idTipo = $tipo['id'];

        // Buscar la presentación comercial específica.

        $inventario = DB::table('inventario_calidades')
            ->where('id_tipo', $idTipo)
            ->where('unidad_comercial', $unidad)
            ->lockForUpdate()
            ->first();

        if (!$inventario) {

            $this->validarPrecio($precio);

            $idInventario = DB::table('inventario_calidades')
                ->insertGetId([
                    'id_tipo' => $idTipo,
                    'unidad_comercial' => $unidad,
                    'stock_actual' => 0,
                    'stock_minimo' => 0,
                    'precio_venta_diario' => $precio,
                ]);

            Auditoria::registrar(
                'PRESENTACION_CREADA',
                $idInventario,
                $puesto,
                'Nueva presentación: ' . $unidad,
                $actor->id_usuario,
                'inventario_calidades',
                null,
                [
                    'id_tipo' => $idTipo,
                    'unidad_comercial' => $unidad,
                    'precio_venta_diario' => $precio,
                ]
            );

            $stockActual = 0;

        } else {

            $idInventario = $inventario->id_inventario;

            $stockActual = (int) $inventario->stock_actual;

            // Asignar precio inicial si todavía no existe.

            if ((float) $inventario->precio_venta_diario === 0.0) {

                $this->validarPrecio($precio);

                DB::table('inventario_calidades')
                    ->where('id_inventario', $idInventario)
                    ->update([
                        'precio_venta_diario' => $precio,
                    ]);

                Auditoria::registrar(
                    'PRECIO_ACTUALIZADO',
                    $idInventario,
                    $puesto,
                    'Asignación de precio inicial',
                    $actor->id_usuario,
                    'inventario_calidades',
                    ['precio_venta_diario' => 0],
                    ['precio_venta_diario' => $precio]
                );
            }
        }

        // Validar capacidad del inventario.

        if ($stockActual + $cantidad > 2147483647) {

            $this->rechazar(
                'cantidad',
                'La cantidad supera la capacidad del inventario.'
            );
        }

        // Acumular existencias sin duplicar registros.

        DB::table('inventario_calidades')
            ->where('id_inventario', $idInventario)
            ->increment('stock_actual', $cantidad);

        return (int) $idInventario;
    }

    // =====================================================
    // FUNCIONES AUXILIARES
    // =====================================================

    private function productoActivo(
        Usuario $actor,
        int $idProducto
    ): object {

        $producto = DB::table('productos')
            ->where('id_producto', $idProducto)
            ->where('id_puesto', $this->puesto($actor))
            ->lockForUpdate()
            ->first();

        if (!$producto || $producto->estado !== 'Activo') {

            $this->rechazar(
                'producto',
                'El producto no está disponible en tu puesto.'
            );
        }

        return $producto;
    }

    private function puesto(Usuario $actor): int
    {
        abort_unless(
            $actor->id_puesto &&
            in_array($actor->rol, ['Dueno', 'Vendedor'], true),
            403
        );

        if (DB::transactionLevel() === 0) {

            throw new LogicException(
                'Las operaciones de inventario requieren una transacción.'
            );
        }

        return (int) $actor->id_puesto;
    }

    private function normalizar(string $texto): string
    {
        return trim(
            preg_replace('/\s+/u', ' ', $texto) ?? ''
        );
    }

    private function validarNombre(
        string $nombre,
        string $campo,
        int $minimo = 1
    ): void {

        if (
            mb_strlen($nombre) < $minimo ||
            mb_strlen($nombre) > 100 ||
            !preg_match(
                "/^[\p{L}\p{M}\p{N} .,'&#()\/\-]+$/u",
                $nombre
            )
        ) {
            $this->rechazar(
                $campo,
                'Escribe un nombre válido.'
            );
        }
    }

    private function validarPrecio(?int $precio): void
    {
        if (
            $precio === null ||
            $precio <= 0 ||
            $precio > 9999999999
        ) {
            $this->rechazar(
                'precio',
                'Indica un precio de venta inicial válido.'
            );
        }
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