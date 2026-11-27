<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondeJson;
use App\Models\Auditoria;
use App\Models\Puesto;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Configuración inicial del puesto (primer ingreso del Dueño).
 * El Dueño registra qué productos vende y sus calidades.
 * En LEDGERA "calidad" = variedad del producto (ej.: Tomate -> Chonto, Larga vida).
 * Cada puesto escribe solo lo suyo: no hay una lista fija para todos.
 * Se puede omitir ("Lo hago después"); se vuelve a mostrar en el siguiente ingreso
 * mientras el puesto no tenga productos.
 */
class ConfiguracionInicialController extends Controller
{
    use RespondeJson;

    private const MAX_PRODUCTOS = 30;
    private const MAX_CALIDADES = 20;
    private const SIN_CALIDAD = 'Única';   // si el producto no maneja calidades
    private const PATRON = '/^[\pL\pN .,&#-]+$/u';

    public function mostrar(Request $request): View
    {
        return view('operativo.configuracion-inicial', [
            'existentes' => $this->productosDelPuesto((int) $request->user()->id_puesto),
        ]);
    }

    public function guardar(Request $request): JsonResponse
    {
        $actor = $request->user();
        $productos = $this->validar($request);

        $resumen = DB::transaction(function () use ($actor, $productos) {
            // Bloquea el puesto: si el Dueño guarda desde dos pantallas a la vez, no se duplica
            Puesto::lockForUpdate()->findOrFail($actor->id_puesto);

            $nuevosProductos = 0;
            $nuevasCalidades = 0;

            foreach ($productos as $p) {
                // La BD compara sin mayúsculas: "tomate" y "Tomate" son el mismo producto
                $existente = DB::table('productos')
                    ->where('id_puesto', $actor->id_puesto)
                    ->where('nombre_producto', $p['nombre'])
                    ->first();

                if ($existente) {
                    $idProducto = $existente->id_producto;
                } else {
                    $idProducto = DB::table('productos')->insertGetId([
                        'id_puesto' => $actor->id_puesto,
                        'nombre_producto' => $p['nombre'],
                        'estado' => 'Activo',
                    ], 'id_producto');

                    Auditoria::registrar('PRODUCTO_CREADO', $idProducto, $actor->id_puesto,
                        'Configuración inicial', null, 'productos', null, ['nombre' => $p['nombre']]);
                    $nuevosProductos++;
                }

                // Solo se agregan las calidades que ese producto no tenga todavía
                $yaTiene = DB::table('inventario_calidades')
                    ->where('id_producto', $idProducto)
                    ->pluck('calidad')
                    ->map(fn ($c) => mb_strtolower($c))
                    ->all();

                foreach ($p['calidades'] as $calidad) {
                    if (in_array(mb_strtolower($calidad), $yaTiene, true)) {
                        continue;
                    }

                    // Stock y precio arrancan en 0: llegan con la entrada de mercancía y el precio del día
                    $idInventario = DB::table('inventario_calidades')->insertGetId([
                        'id_producto' => $idProducto,
                        'calidad' => $calidad,
                    ], 'id_inventario');

                    Auditoria::registrar('CALIDAD_CREADA', $idInventario, $actor->id_puesto,
                        'Configuración inicial', null, 'inventario_calidades', null,
                        ['producto' => $p['nombre'], 'calidad' => $calidad]);
                    $yaTiene[] = mb_strtolower($calidad);
                    $nuevasCalidades++;
                }
            }

            return [$nuevosProductos, $nuevasCalidades];
        });

        $request->session()->forget('configuracion_omitida');

        return response()->json([
            'ok' => true,
            'mensaje' => "Listo: {$resumen[0]} productos y {$resumen[1]} calidades nuevas.",
            'destino' => route('dashboard'),
        ]);
    }

    // "Lo hago después": no se vuelve a mostrar hasta el próximo inicio de sesión
    public function omitir(Request $request): JsonResponse
    {
        $request->session()->put('configuracion_omitida', true);

        return response()->json(['ok' => true, 'destino' => route('dashboard')]);
    }

    // ---------------------------------------------------------------

    // Limpia, valida y quita repetidos. Devuelve [['nombre' => ..., 'calidades' => [...]], ...]
    private function validar(Request $request): array
    {
        $lista = $request->input('productos');

        if (!is_array($lista) || count($lista) === 0) {
            $this->abortar('Agrega al menos un producto.', 400);
        }
        if (count($lista) > self::MAX_PRODUCTOS) {
            $this->abortar('Máximo ' . self::MAX_PRODUCTOS . ' productos a la vez.', 400);
        }

        $limpiar = fn ($s) => trim(preg_replace('/\s+/u', ' ', (string) $s));
        $resultado = [];

        foreach ($lista as $i => $p) {
            $nombre = $limpiar($p['nombre'] ?? '');
            $n = $i + 1;

            if ($nombre === '') {
                $this->abortar("Escribe el nombre del producto #{$n}.", 400);
            }

            $calidades = array_values(array_filter(array_map($limpiar, (array) ($p['calidades'] ?? []))));
            if (count($calidades) === 0) {
                $calidades = [self::SIN_CALIDAD];
            }
            if (count($calidades) > self::MAX_CALIDADES) {
                $this->abortar("\"{$nombre}\" tiene demasiadas calidades (máximo " . self::MAX_CALIDADES . ').', 400);
            }

            $v = Validator::make(
                ['nombre' => $nombre, 'calidades' => $calidades],
                [
                    'nombre' => ['max:100', 'regex:' . self::PATRON],
                    'calidades.*' => ['max:100', 'regex:' . self::PATRON],
                ],
                [
                    'nombre.max' => "El nombre del producto #{$n} es muy largo.",
                    'nombre.regex' => "El producto #{$n} tiene caracteres no permitidos.",
                    'calidades.*.max' => "Una calidad de \"{$nombre}\" es muy larga.",
                    'calidades.*.regex' => "Una calidad de \"{$nombre}\" tiene caracteres no permitidos.",
                ]
            );

            if ($v->fails()) {
                $this->abortar($v->errors()->first(), 400);
            }

            // Si el mismo producto viene dos veces, se juntan sus calidades
            $clave = mb_strtolower($nombre);
            $previas = $resultado[$clave]['calidades'] ?? [];
            $todas = [];
            foreach (array_merge($previas, $calidades) as $c) {
                $todas[mb_strtolower($c)] = $this->capitalizar($c);
            }

            $resultado[$clave] = [
                'nombre' => $resultado[$clave]['nombre'] ?? $this->capitalizar($nombre),
                'calidades' => array_values($todas),
            ];
        }

        return array_values($resultado);
    }

    // "tomate" -> "Tomate" (solo la primera letra; respeta lo demás)
    private function capitalizar(string $s): string
    {
        return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
    }

    private function productosDelPuesto(int $idPuesto): array
    {
        return DB::table('productos as p')
            ->leftJoin('inventario_calidades as c', 'c.id_producto', '=', 'p.id_producto')
            ->where('p.id_puesto', $idPuesto)
            ->where('p.estado', 'Activo')
            ->orderBy('p.nombre_producto')
            ->orderBy('c.calidad')
            ->get(['p.nombre_producto', 'c.calidad'])
            ->groupBy('nombre_producto')
            ->map(fn ($filas, $nombre) => [
                'nombre' => $nombre,
                'calidades' => $filas->pluck('calidad')->filter()->values()->all(),
            ])
            ->values()
            ->all();
    }
}
