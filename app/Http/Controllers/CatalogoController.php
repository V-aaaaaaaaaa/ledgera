<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Services\InventarioService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogoController extends Controller
{
    private const CATALOGOS = [
        'clientes' => [
            'id' => 'id_cliente',
            'nombre' => 'nombre_completo',
            'titulo' => 'Clientes',
            'singular' => 'Cliente',
        ],
        'productos' => [
            'id' => 'id_producto',
            'nombre' => 'nombre_producto',
            'titulo' => 'Inventario y compras',
            'singular' => 'Producto',
        ],
    ];

    public function __construct(
        private InventarioService $inventario
    ) {}

    // =====================================================
    // CONSULTAR CATÁLOGOS
    // =====================================================

    public function index(Request $request, string $tipo)
    {
        $cfg = $this->configuracion($tipo);
        $puesto = (int) $request->user()->id_puesto;

        abort_unless($puesto, 403);

        $esDueno = $request->user()->rol === 'Dueno';

        $buscar = $request->query('buscar', '');

        $buscar = is_string($buscar)
            ? mb_substr(trim($buscar), 0, 150)
            : '';

        $estado = $request->query('estado');

        $estado = in_array($estado, ['Activo', 'Inactivo'], true)
            ? $estado
            : null;

        $pestana = $request->query('pestana', 'inventario');

        $pestana = in_array(
            $pestana,
            ['inventario', 'entradas', 'catalogo'],
            true
        ) ? $pestana : 'inventario';

        $consulta = DB::table($tipo)
            ->where('id_puesto', $puesto);

        $saldos = collect();

        // Catálogo de clientes.

        if ($tipo === 'clientes') {

            $consulta->when($buscar !== '', function ($q) use ($buscar) {
                $q->where(function ($q) use ($buscar) {
                    $q->where('nombre_completo', 'like', "%{$buscar}%")
                        ->orWhere('telefono', 'like', "%{$buscar}%");
                });
            })->when($estado, fn ($q) => $q->where('estado', $estado));

            $saldos = DB::table('v_creditos_saldo')
                ->where('id_puesto', $puesto)
                ->where('saldo_pendiente', '>', 0)
                ->where('estado_venta', '!=', 'Anulada')
                ->selectRaw('id_cliente, SUM(saldo_pendiente) AS saldo')
                ->groupBy('id_cliente')
                ->pluck('saldo', 'id_cliente');

        } elseif ($pestana === 'catalogo') {

            // Catálogo de productos y tipos.

            $consulta->when($buscar !== '', function ($q) use ($buscar) {

                $q->where(function ($q) use ($buscar) {

                    $q->where('nombre_producto', 'like', "%{$buscar}%")

                        ->orWhereExists(function ($sub) use ($buscar) {

                            $sub->selectRaw('1')
                                ->from('tipos_producto as t')
                                ->whereColumn(
                                    't.id_producto',
                                    'productos.id_producto'
                                )
                                ->where(
                                    't.nombre_tipo',
                                    'like',
                                    "%{$buscar}%"
                                );
                        });
                });

            })->when($estado, fn ($q) => $q->where('estado', $estado));
        }

        $registros = $consulta
            ->orderBy($cfg['nombre'])
            ->orderBy($cfg['id'])
            ->paginate(15)
            ->withQueryString();

        $registros->through(function ($fila) use ($cfg, $saldos) {

            $fila->saldo = $saldos->get($fila->{$cfg['id']}, 0);

            return $fila;
        });

        $total = DB::table($tipo)
            ->where('id_puesto', $puesto)
            ->count();

        $activos = DB::table($tipo)
            ->where('id_puesto', $puesto)
            ->where('estado', 'Activo')
            ->count();

        $proveedores = collect();
        $existencias = null;
        $entradas = null;
        $presentacionSeleccionada = null;

        if ($tipo === 'productos') {

            $tipos = DB::table('tipos_producto')
                ->whereIn('id_producto', $registros->pluck('id_producto'))
                ->orderBy('nombre_tipo')
                ->get()
                ->groupBy('id_producto');

            $registros->through(function ($producto) use ($tipos) {

                $producto->tipos = $tipos->get(
                    $producto->id_producto,
                    collect()
                );

                return $producto;
            });

            $proveedores = DB::table('proveedores')
                ->where('id_puesto', $puesto)
                ->where('estado', 'Activo')
                ->orderBy('nombre_razon_social')
                ->get();

            if ($pestana === 'inventario') {

                $existencias = $this->existencias(
                    $puesto,
                    $buscar,
                    $estado
                );

            } elseif ($pestana === 'entradas') {

                $id = filter_var(
                    $request->query('inventario'),
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                );

                if ($id !== false) {

                    $presentacionSeleccionada =
                        $this->consultaInventario($puesto)
                            ->where('i.id_inventario', $id)
                            ->first();

                    abort_if(!$presentacionSeleccionada, 404);
                }

                $entradas = $this->entradas(
                    $puesto,
                    $buscar,
                    $id ?: null
                );
            }
        }

        return view('catalogos.index', [
            'tipo' => $tipo,
            'cfg' => $cfg,
            'registros' => $registros,
            'total' => $total,
            'activos' => $activos,
            'saldoTotal' => $saldos->sum(),
            'esDueno' => $esDueno,
            'proveedores' => $proveedores,
            'pestana' => $pestana,
            'existencias' => $existencias,
            'entradas' => $entradas,
            'presentacionSeleccionada' => $presentacionSeleccionada,
        ]);
    }

    // =====================================================
    // CONSULTAR INVENTARIO
    // =====================================================

    private function consultaInventario(int $puesto)
    {
        return DB::table('inventario_calidades as i')

            ->join(
                'tipos_producto as t',
                't.id_tipo',
                '=',
                'i.id_tipo'
            )

            ->join(
                'productos as p',
                'p.id_producto',
                '=',
                't.id_producto'
            )

            ->where('p.id_puesto', $puesto)

            ->select([
                'i.id_inventario',
                'i.id_tipo',
                'p.id_producto',
                'p.nombre_producto',
                't.nombre_tipo',
                'i.unidad_comercial',
                'i.stock_actual',
                'i.stock_minimo',
                'i.precio_venta_diario',
                'p.estado',
            ]);
    }

    private function existencias(
        int $puesto,
        string $buscar,
        ?string $estado
    ) {

        return $this->consultaInventario($puesto)

            ->when($buscar !== '', function ($q) use ($buscar) {

                $q->where(function ($q) use ($buscar) {

                    $q->where('p.nombre_producto', 'like', "%{$buscar}%")
                        ->orWhere('t.nombre_tipo', 'like', "%{$buscar}%")
                        ->orWhere('i.unidad_comercial', 'like', "%{$buscar}%");
                });
            })

            ->when($estado, fn ($q) => $q->where('p.estado', $estado))

            ->orderBy('p.nombre_producto')
            ->orderBy('t.nombre_tipo')
            ->orderBy('i.unidad_comercial')
            ->orderBy('i.id_inventario')

            ->paginate(15, ['*'], 'pagina_inventario')
            ->withQueryString();
    }

    // =====================================================
    // CONSULTAR ENTRADAS Y SUS SALDOS
    // =====================================================

    private function entradas(
        int $puesto,
        string $buscar,
        ?int $idInventario
    ) {

        $entradas = DB::table('entradas_mercancia as e')

            ->join('proveedores as pr', function ($join) {

                $join->on('pr.id_proveedor', '=', 'e.id_proveedor')
                    ->on('pr.id_puesto', '=', 'e.id_puesto');
            })

            ->join(
                'v_entradas_saldo as s',
                's.id_entrada',
                '=',
                'e.id_entrada'
            )

            ->where('e.id_puesto', $puesto)
            ->where('s.id_puesto', $puesto)

            ->when($buscar !== '', function ($q) use ($buscar) {

                $q->where(function ($q) use ($buscar) {

                    $q->where('e.documento_soporte', 'like', "%{$buscar}%")
                        ->orWhere('pr.nombre_razon_social', 'like', "%{$buscar}%");
                });
            })

            ->when($idInventario, function ($q) use ($idInventario) {

                $q->whereExists(function ($sub) use ($idInventario) {

                    $sub->selectRaw('1')
                        ->from('detalle_entrada as d')
                        ->whereColumn('d.id_entrada', 'e.id_entrada')
                        ->where('d.id_inventario', $idInventario);
                });
            })

            ->select([
                'e.id_entrada',
                'e.id_proveedor',
                'e.documento_soporte',
                'e.fecha_entrada',
                'pr.nombre_razon_social',
                's.total_calculado',
                's.valor_acordado',
                's.pagado',
                's.saldo_pendiente',
                's.estado_pago',
            ])

            ->orderByDesc('e.fecha_entrada')
            ->orderByDesc('e.id_entrada')
            ->paginate(15, ['*'], 'pagina_entradas')
            ->withQueryString();

        $detalles = DB::table('detalle_entrada as d')

            ->join(
                'entradas_mercancia as e',
                'e.id_entrada',
                '=',
                'd.id_entrada'
            )

            ->join(
                'inventario_calidades as i',
                'i.id_inventario',
                '=',
                'd.id_inventario'
            )

            ->join(
                'tipos_producto as t',
                't.id_tipo',
                '=',
                'i.id_tipo'
            )

            ->join(
                'productos as p',
                'p.id_producto',
                '=',
                't.id_producto'
            )

            ->where('e.id_puesto', $puesto)
            ->where('p.id_puesto', $puesto)
            ->whereIn('d.id_entrada', $entradas->pluck('id_entrada'))

            ->orderBy('d.id_detalle_entrada')

            ->get([
                'd.id_entrada',
                'd.id_inventario',
                'p.nombre_producto',
                't.nombre_tipo',
                'i.unidad_comercial',
                'd.cantidad_recibida',
                'd.costo_unitario',
                'd.subtotal',
            ])

            ->groupBy('id_entrada');

        return $entradas->through(function ($entrada) use ($detalles) {

            $entrada->detalles = $detalles->get(
                $entrada->id_entrada,
                collect()
            );

            return $entrada;
        });
    }

    // =====================================================
    // REGISTRAR CLIENTES
    // =====================================================

    public function store(Request $request, string $tipo)
    {
        abort_unless($tipo === 'clientes', 404);

        $actor = $request->user();
        $puesto = (int) $actor->id_puesto;

        abort_unless($puesto, 403);

        $datos = $this->validar($request, $tipo, true);

        $datos['id_puesto'] = $puesto;
        $datos['estado'] = 'Activo';

        try {

            DB::transaction(function () use ($datos, $puesto, $actor) {

                $id = DB::table('clientes')
                    ->insertGetId($datos, 'id_cliente');

                Auditoria::registrar(
                    'CLIENTE_CREADO',
                    $id,
                    $puesto,
                    'Registro de catálogo',
                    $actor->id_usuario,
                    'clientes'
                );
            });

        } catch (QueryException $e) {

            return $this->errorDuplicado($e);
        }

        return back()->with(
            'success',
            'Cliente registrado correctamente.'
        );
    }

    // =====================================================
    // ACTUALIZAR PRODUCTOS Y CLIENTES
    // =====================================================

    public function update(
        Request $request,
        string $tipo,
        int $id
    ) {

        $cfg = $this->configuracion($tipo);
        $actor = $request->user();
        $puesto = (int) $actor->id_puesto;

        abort_unless($puesto && $actor->rol === 'Dueno', 403);

        $datos = $this->validar($request, $tipo, false);

        try {

            DB::transaction(function () use (
                $tipo, $cfg, $id, $puesto, $datos, $actor
            ) {

                if ($tipo === 'productos') {

                    $this->inventario->bloquearPuesto($actor);
                }

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
                    $actor->id_usuario,
                    $tipo,
                    array_intersect_key((array) $anterior, $datos),
                    $datos
                );

            }, 3);

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
        $actor = $request->user();
        $puesto = (int) $actor->id_puesto;

        abort_unless($puesto && $actor->rol === 'Dueno', 403);

        $v = $request->validate([
            'estado' => ['required', 'in:Activo,Inactivo'],
        ]);

        $estado = $v['estado'];

        DB::transaction(function () use (
            $tipo, $cfg, $id, $puesto, $estado, $actor
        ) {

            if ($tipo === 'productos') {

                $this->inventario->bloquearPuesto($actor);
            }

            $consulta = DB::table($tipo)
                ->where($cfg['id'], $id)
                ->where('id_puesto', $puesto);

            $fila = (clone $consulta)
                ->lockForUpdate()
                ->first();

            abort_if(!$fila, 404);

            if ($fila->estado === $estado) {

                return;
            }

            $consulta->update(['estado' => $estado]);

            Auditoria::registrar(
                strtoupper($cfg['singular'])
                    . ($estado === 'Activo'
                        ? '_ACTIVADO'
                        : '_DESACTIVADO'),
                $id,
                $puesto,
                'Cambio de estado',
                $actor->id_usuario,
                $tipo,
                ['estado' => $fila->estado],
                ['estado' => $estado]
            );

        }, 3);

        return back()->with(
            'success',
            'Estado actualizado correctamente.'
        );
    }

    // =====================================================
    // FUNCIONES AUXILIARES
    // =====================================================

    private function configuracion(string $tipo): array
    {
        abort_unless(isset(self::CATALOGOS[$tipo]), 404);

        return self::CATALOGOS[$tipo];
    }

    private function validar(
        Request $request,
        string $tipo,
        bool $creando
    ): array {

        $cfg = $this->configuracion($tipo);
        $esDueno = $request->user()->rol === 'Dueno';

        if (is_string($request->input('nombre'))) {

            $request->merge([
                'nombre' => trim(
                    preg_replace(
                        '/\s+/u',
                        ' ',
                        $request->input('nombre')
                    ) ?? ''
                ),
            ]);
        }

        $reglas = [

            'nombre' => [
                'required',
                'string',
                'min:2',
                $tipo === 'productos' ? 'max:100' : 'max:150',
                'regex:/\p{L}/u',

                $tipo === 'clientes'
                    ? "regex:/^[\p{L}\p{M} .'\-]+$/u"
                    : "regex:/^[\p{L}\p{M}\p{N} .,'&#()\/\-]+$/u",
            ],
        ];

        if ($tipo === 'clientes') {

            $reglas['telefono'] = [
                'required',
                'string',
                'max:20',
                'regex:/^\+?[0-9]{7,19}$/',
            ];

            if ($creando) {

                $reglas['consentimiento'] = ['accepted'];
            }

            if ($esDueno) {

                $reglas['plazo'] = [
                    'required',
                    'integer',
                    'min:1',
                    'max:3650',
                ];
            }
        }

        $v = $request->validate($reglas, [

            'nombre.required' => 'Escribe el nombre.',

            'nombre.min' =>
                'El nombre debe contener al menos dos caracteres.',

            'nombre.max' =>
                'El nombre supera la longitud permitida.',

            'nombre.regex' =>
                'El nombre contiene caracteres no permitidos.',

            'telefono.required' =>
                'Escribe el teléfono.',

            'telefono.regex' =>
                'Ingresa un número de teléfono válido.',

            'consentimiento.accepted' =>
                'Debes confirmar la autorización de tratamiento de datos.',

            'plazo.required' =>
                'Indica el plazo de crédito.',

            'plazo.min' =>
                'El plazo debe ser de al menos un día.',

            'plazo.max' =>
                'El plazo supera el máximo permitido.',
        ]);

        $datos = [
            $cfg['nombre'] => $v['nombre'],
        ];

        // Los productos solo permiten modificar su nombre.
        // La descripción histórica permanece intacta.

        if ($tipo === 'clientes') {

            $datos['telefono'] = $v['telefono'];

            if ($esDueno) {

                $datos['plazo_credito_dias'] = $v['plazo'];

            } elseif ($creando) {

                $datos['plazo_credito_dias'] = 15;
            }

            if ($creando) {

                $datos['consentimiento_datos'] = now();
            }
        }

        return $datos;
    }

    private function errorDuplicado(QueryException $e)
    {
        if ((int) ($e->errorInfo[1] ?? 0) === 1062) {

            return back()
                ->withInput()
                ->withErrors([
                    'duplicado' =>
                        'Ya existe un registro con esos datos en este puesto.',
                ]);
        }

        throw $e;
    }
}