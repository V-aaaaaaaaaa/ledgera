<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondeJson;
use App\Services\InventarioService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ConfiguracionInicialController extends Controller
{
    use RespondeJson;

    private const MAX_PRODUCTOS = 30;
    private const MAX_TIPOS = 20;
    private const SIN_TIPO = 'Única';

    public function __construct(
        private InventarioService $inventario
    ) {}

    // =====================================================
    // MOSTRAR CONFIGURACIÓN INICIAL
    // =====================================================

    public function mostrar(Request $request): View
    {
        $actor = $request->user();

        abort_unless(
            $actor->rol === 'Dueno' && $actor->id_puesto,
            403
        );

        return view('operativo.configuracion-inicial', [
            'existentes' => $this->productosDelPuesto(
                (int) $actor->id_puesto
            ),
        ]);
    }

    // =====================================================
    // GUARDAR CATÁLOGO INICIAL
    // =====================================================

    public function guardar(Request $request): JsonResponse
    {
        $actor = $request->user();

        abort_unless(
            $actor->rol === 'Dueno' && $actor->id_puesto,
            403
        );

        $productos = $this->validar($request);

        try {

            $resumen = DB::transaction(function () use (
                $actor,
                $productos
            ) {

                $this->inventario->bloquearPuesto($actor);

                $nuevosProductos = 0;
                $nuevosTipos = 0;

                foreach ($productos as $datos) {

                    $producto = $this->inventario->obtenerProducto(
                        $actor,
                        ['nombre' => $datos['nombre']],
                        true
                    );

                    if ($producto['creado']) {
                        $nuevosProductos++;
                    }

                    foreach ($datos['calidades'] as $nombreTipo) {

                        $tipo = $this->inventario->obtenerTipo(
                            $actor,
                            $producto['id'],
                            $nombreTipo
                        );

                        if ($tipo['creado']) {
                            $nuevosTipos++;
                        }
                    }
                }

                return [
                    'productos' => $nuevosProductos,
                    'tipos' => $nuevosTipos,
                ];

            }, 3);

        } catch (ValidationException $e) {

            return response()->json([
                'ok' => false,
                'mensaje' => collect($e->errors())
                    ->flatten()
                    ->first() ?? 'Verifica los datos ingresados.',
            ], 422);
        }

        $request->session()->forget('configuracion_omitida');

        return response()->json([
            'ok' => true,

            'mensaje' =>
                "Listo: {$resumen['productos']} productos y "
                . "{$resumen['tipos']} tipos nuevos.",

            'destino' => route('dashboard'),
        ]);
    }

    // =====================================================
    // OMITIR CONFIGURACIÓN
    // =====================================================

    public function omitir(Request $request): JsonResponse
    {
        $actor = $request->user();

        abort_unless(
            $actor->rol === 'Dueno' && $actor->id_puesto,
            403
        );

        $request->session()->put(
            'configuracion_omitida',
            true
        );

        return response()->json([
            'ok' => true,
            'destino' => route('dashboard'),
        ]);
    }

    // =====================================================
    // VALIDAR INFORMACIÓN RECIBIDA
    // =====================================================

    private function validar(Request $request): array
    {
        $validador = Validator::make($request->all(), [

            'productos' => [
                'required',
                'array',
                'min:1',
                'max:' . self::MAX_PRODUCTOS,
            ],

            'productos.*' => [
                'required',
                'array:nombre,calidades',
            ],

            'productos.*.nombre' => [
                'required',
                'string',
                'min:2',
                'max:100',
            ],

            'productos.*.calidades' => [
                'nullable',
                'array',
                'max:' . self::MAX_TIPOS,
            ],

            'productos.*.calidades.*' => [
                'required',
                'string',
                'max:100',
            ],

        ], [
            'productos.required' =>
                'Agrega al menos un producto.',

            'productos.max' =>
                'Puedes registrar máximo 30 productos a la vez.',

            'productos.*.nombre.required' =>
                'Escribe el nombre de cada producto.',

            'productos.*.nombre.min' =>
                'El nombre debe tener al menos dos caracteres.',

            'productos.*.calidades.max' =>
                'Cada producto admite máximo 20 tipos por registro.',
        ]);

        if ($validador->fails()) {

            $this->abortar(
                $validador->errors()->first(),
                400
            );
        }

        $datos = $validador->validated();

        $resultado = [];

        foreach ($datos['productos'] as $producto) {

            $nombre = $this->normalizar(
                $producto['nombre']
            );

            $tipos = array_map(
                fn ($tipo) => $this->normalizar($tipo),
                $producto['calidades'] ?? []
            );

            $tipos = array_values(
                array_filter(
                    $tipos,
                    fn ($tipo) => $tipo !== ''
                )
            );

            if (!$tipos) {
                $tipos = [self::SIN_TIPO];
            }

            $clave = mb_strtolower($nombre);

            $previos = $resultado[$clave]['calidades'] ?? [];

            $unicos = [];

            foreach (array_merge($previos, $tipos) as $tipo) {

                $unicos[mb_strtolower($tipo)] =
                    $this->capitalizar($tipo);
            }

            if (count($unicos) > self::MAX_TIPOS) {

                $this->abortar(
                    "El producto {$nombre} supera el máximo de tipos.",
                    400
                );
            }

            $resultado[$clave] = [
                'nombre' => $this->capitalizar($nombre),
                'calidades' => array_values($unicos),
            ];
        }

        return array_values($resultado);
    }

    // =====================================================
    // CONSULTAR CATÁLOGO DEL PUESTO
    // =====================================================

    private function productosDelPuesto(int $idPuesto): array
    {
        return DB::table('productos as p')

            ->leftJoin(
                'tipos_producto as t',
                't.id_producto',
                '=',
                'p.id_producto'
            )

            ->where('p.id_puesto', $idPuesto)
            ->where('p.estado', 'Activo')

            ->orderBy('p.nombre_producto')
            ->orderBy('t.nombre_tipo')

            ->get([
                'p.nombre_producto',
                't.nombre_tipo',
            ])

            ->groupBy('nombre_producto')

            ->map(fn ($filas, $nombre) => [

                'nombre' => $nombre,

                'calidades' => $filas
                    ->pluck('nombre_tipo')
                    ->filter()
                    ->values()
                    ->all(),

            ])

            ->values()
            ->all();
    }

    // =====================================================
    // FUNCIONES AUXILIARES
    // =====================================================

    private function normalizar(string $texto): string
    {
        return trim(
            preg_replace('/\s+/u', ' ', $texto) ?? ''
        );
    }

    private function capitalizar(string $texto): string
    {
        return mb_strtoupper(
            mb_substr($texto, 0, 1)
        ) . mb_substr($texto, 1);
    }
}