<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondeJson;
use App\Models\Auditoria;
use App\Models\Egreso;
use App\Models\Proveedor;
use App\Services\CajaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EgresoController extends Controller
{
    use RespondeJson;

    private const MAXIMO = 999999999;

    public function __construct(
        private CajaService $caja
    ) {}

    // =====================================================
    // VISTA DE EGRESOS
    // =====================================================

    public function index(): View
    {
        return view('operativo.egresos');
    }

    // =====================================================
    // EGRESOS DE LA CAJA INDIVIDUAL
    // =====================================================

    public function datos(Request $request): JsonResponse
    {
        $actor = $request->user();

        $jornada = $this->caja->abierta($actor);

        // Pagar a proveedores: Dueño o Vendedor encargado
        $puedePagar = $actor->puede('pagar_proveedores');

        $egresos = $jornada

            ? Egreso::with([
                'usuario:id_usuario,nombre_completo',
                'proveedor:id_proveedor,nombre_razon_social',
            ])
                ->where('id_jornada', $jornada->id_jornada)
                ->where('id_puesto', $actor->id_puesto)
                ->orderByDesc('id_egreso')
                ->get()
                ->map(fn (Egreso $e) => [

                    'hora' => $e->fecha_egreso
                        ?->format('g:i a'),

                    'categoria' => $e->categoria,

                    'detalle' => $e->categoria === 'Pago_proveedor'

                        ? (
                            $e->proveedor?->nombre_razon_social .
                            ($e->nota ? ' · ' . $e->nota : '')
                        )

                        : $e->nota,

                    'medio' => $e->medio_pago,

                    'monto' => (float) $e->monto,

                    'usuario' => $e->usuario
                        ?->nombre_completo,

                    'soporte' => $e->soporte,
                ])

            : collect();

        return response()->json([

            'ok' => true,

            'abierta' => $jornada !== null,

            'puede_pagar' => $puedePagar,

            'egresos' => $egresos,

            'total_efectivo' => $egresos
                ->where('medio', 'Efectivo')
                ->sum('monto'),

            'total_digital' => $egresos
                ->where('medio', '!=', 'Efectivo')
                ->sum('monto'),

            'proveedores' => $puedePagar
                ? $this->proveedoresConDeuda(
                    (int) $actor->id_puesto
                )
                : [],
        ]);
    }

    // =====================================================
    // FACTURAS PENDIENTES DE UN PROVEEDOR
    // =====================================================

    public function obligaciones(
        Request $request,
        int $proveedor
    ): JsonResponse {

        $actor = $request->user();

        abort_unless(
            $actor->puede('pagar_proveedores'),
            403
        );

        $p = Proveedor::where(
            'id_proveedor',
            $proveedor
        )
            ->where('id_puesto', $actor->id_puesto)
            ->first();

        if (!$p) {

            $this->abortar(
                'Ese proveedor no existe.',
                404
            );
        }

        $facturas = DB::table('v_entradas_saldo as s')
            ->join(
                'entradas_mercancia as e',
                'e.id_entrada',
                '=',
                's.id_entrada'
            )
            ->where('s.id_proveedor', $p->id_proveedor)
            ->where('s.id_puesto', $p->id_puesto)
            ->where('s.saldo_pendiente', '>', 0)
            ->orderBy('e.fecha_entrada')
            ->orderBy('e.id_entrada')
            ->get([
                's.id_entrada',
                'e.documento_soporte',
                'e.fecha_entrada',
                's.valor_acordado',
                's.pagado',
                's.saldo_pendiente',
                's.estado_pago',
            ])
            ->map(fn ($f) => [

                'id' => $f->id_entrada,

                'documento' => $f->documento_soporte,

                'fecha' => date(
                    'd/m/Y',
                    strtotime($f->fecha_entrada)
                ),

                'valor' => (float) $f->valor_acordado,

                'pagado' => (float) $f->pagado,

                'saldo' => (float) $f->saldo_pendiente,

                'estado' => $f->estado_pago,
            ]);

        return response()->json([
            'ok' => true,
            'facturas' => $facturas,
        ]);
    }

    // =====================================================
    // REGISTRAR EGRESO
    // =====================================================

    public function store(Request $request): JsonResponse
    {
        $actor = $request->user();

        $datos = $this->validar($request);

        $esPago = $datos['categoria'] === 'Pago_proveedor';

        if ($esPago && !$actor->puede('pagar_proveedores')) {

            $this->abortar(
                'Solo el Dueño o el encargado pueden registrar pagos a proveedores.',
                403
            );
        }

        DB::transaction(function () use (
            $actor,
            $datos,
            $esPago
        ) {

            $this->caja->bloquearPuesto($actor);

            $jornada = $this->caja->abierta(
                $actor,
                true
            );

            if (!$jornada) {

                $this->abortar(
                    'Tu caja está cerrada. Ábrela para registrar egresos.',
                    409
                );
            }

            $aplicaciones = $esPago

                ? $this->repartirPago($actor, $datos)

                : [];

            $egreso = (new Egreso())->forceFill([

                'id_puesto' => $actor->id_puesto,

                'id_jornada' => $jornada->id_jornada,

                'id_usuario' => $actor->id_usuario,

                'id_proveedor' => $esPago
                    ? $datos['id_proveedor']
                    : null,

                'categoria' => $datos['categoria'],

                'medio_pago' => $datos['medio'],

                'monto' => $datos['monto'],

                'nota' => $datos['nota'],

                'soporte' => $datos['soporte'],
            ]);

            $egreso->save();

            foreach ($aplicaciones as $aplicacion) {

                DB::table('pagos_proveedor_detalle')
                    ->insert([

                        'id_egreso' => $egreso->id_egreso,

                        'id_entrada' =>
                            $aplicacion['id_entrada'],

                        'monto_aplicado' =>
                            $aplicacion['monto'],
                    ]);
            }

            Auditoria::registrar(
                'EGRESO_REGISTRADO',
                $egreso->id_egreso,
                $actor->id_puesto,
                str_replace('_', ' ', $datos['categoria']),
                $actor->id_usuario,
                'egresos_caja',
                null,
                [
                    'monto' => $datos['monto'],
                    'medio' => $datos['medio'],
                    'nota' => $datos['nota'],
                    'aplicado_a' => $aplicaciones,
                ]
            );

        }, 3);

        return response()->json([
            'ok' => true,
            'mensaje' => 'Egreso registrado.',
        ], 201);
    }

    // =====================================================
    // DISTRIBUIR PAGO ENTRE FACTURAS
    // =====================================================

    private function repartirPago(
        $actor,
        array $datos
    ): array {

        $facturas = DB::table('entradas_mercancia')
            ->whereIn('id_entrada', $datos['facturas'])
            ->where('id_puesto', $actor->id_puesto)
            ->where('id_proveedor', $datos['id_proveedor'])
            ->orderBy('fecha_entrada')
            ->orderBy('id_entrada')
            ->lockForUpdate()
            ->get([
                'id_entrada',
                'documento_soporte',
            ]);

        if ($facturas->count() !== count($datos['facturas'])) {

            $this->abortar(
                'Alguna factura no es de este proveedor. Recarga la pantalla.',
                409
            );
        }

        $saldos = DB::table('v_entradas_saldo')
            ->whereIn('id_entrada', $datos['facturas'])
            ->where('id_puesto', $actor->id_puesto)
            ->where('id_proveedor', $datos['id_proveedor'])
            ->pluck(
                'saldo_pendiente',
                'id_entrada'
            );

        $totalDeuda = round(
            $saldos->sum(),
            2
        );

        if ($totalDeuda <= 0) {

            $this->abortar(
                'Esas facturas ya están pagadas.',
                409
            );
        }

        if ($datos['monto'] > $totalDeuda) {

            $this->abortar(
                'El monto supera lo que se debe en esas facturas ($' .
                number_format($totalDeuda, 2, ',', '.') .
                '). Corrige el monto.',
                400
            );
        }

        $restante = $datos['monto'];

        $aplicaciones = [];

        foreach ($facturas as $factura) {

            $saldo = round(
                (float) (
                    $saldos[$factura->id_entrada] ?? 0
                ),
                2
            );

            if ($restante <= 0 || $saldo <= 0) {
                continue;
            }

            $aplica = min($saldo, $restante);

            $aplicaciones[] = [
                'id_entrada' => $factura->id_entrada,
                'documento' => $factura->documento_soporte,
                'monto' => $aplica,
            ];

            $restante = round(
                $restante - $aplica,
                2
            );
        }

        if ($restante > 0) {

            $this->abortar(
                'No se pudo aplicar completamente el pago.',
                409
            );
        }

        return $aplicaciones;
    }

    // =====================================================
    // PROVEEDORES CON DEUDA
    // =====================================================

    private function proveedoresConDeuda(
        int $idPuesto
    ): array {

        return DB::table('v_entradas_saldo as s')
            ->join(
                'proveedores as p',
                'p.id_proveedor',
                '=',
                's.id_proveedor'
            )
            ->where('s.id_puesto', $idPuesto)
            ->where('s.saldo_pendiente', '>', 0)
            ->groupBy(
                'p.id_proveedor',
                'p.nombre_razon_social'
            )
            ->orderBy('p.nombre_razon_social')
            ->selectRaw(
                'p.id_proveedor AS id,
                 p.nombre_razon_social AS nombre,
                 SUM(s.saldo_pendiente) AS deuda'
            )
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'deuda' => (float) $p->deuda,
            ])
            ->all();
    }

    // =====================================================
    // VALIDAR EGRESO
    // =====================================================

    private function validar(Request $request): array
    {
        $validador = Validator::make(
            $request->all(),
            [
                'categoria' => [
                    'required',
                    'in:' . implode(',', Egreso::CATEGORIAS),
                ],

                'monto' => [
                    'required',
                    'numeric',
                    'min:0.01',
                    'max:' . self::MAXIMO,
                ],

                'medio' => [
                    'required',
                    'in:' . implode(',', Egreso::MEDIOS),
                ],

                'nota' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'soporte' => [
                    'nullable',
                    'string',
                    'max:255',
                    'regex:/^[\pL\pN .,#\/-]+$/u',
                ],

                'id_proveedor' => [
                    'required_if:categoria,Pago_proveedor',
                    'nullable',
                    'integer',
                ],

                'facturas' => [
                    'required_if:categoria,Pago_proveedor',
                    'array',
                ],

                'facturas.*' => [
                    'integer',
                ],
            ],
            [
                'categoria.*' =>
                    'Elige el tipo de egreso.',

                'monto.required' =>
                    'Escribe el monto.',

                'monto.numeric' =>
                    'Escribe solo números.',

                'monto.min' =>
                    'El monto debe ser mayor que cero.',

                'monto.max' =>
                    'El monto supera el máximo permitido.',

                'medio.*' =>
                    'Elige cómo se pagó.',

                'nota.max' =>
                    'La nota supera los 255 caracteres.',

                'soporte.max' =>
                    'El soporte es muy largo.',

                'soporte.regex' =>
                    'El soporte solo puede tener letras, números y . , # / -',

                'id_proveedor.required_if' =>
                    'Elige el proveedor.',

                'facturas.required_if' =>
                    'Elige al menos una factura a pagar.',

                'facturas.*' =>
                    'Factura no válida.',
            ]
        );

        if ($validador->fails()) {

            $this->abortar(
                $validador->errors()->first(),
                400
            );
        }

        $nota = trim(
            (string) $request->input('nota')
        ) ?: null;

        $categoria = $request->input('categoria');

        if (
            $categoria !== 'Pago_proveedor' &&
            $nota === null
        ) {

            $this->abortar(
                'Escribe en qué se gastó. La nota es obligatoria.',
                400
            );
        }

        if (
            $categoria === 'Pago_proveedor' &&
            count((array) $request->input('facturas')) === 0
        ) {

            $this->abortar(
                'Elige al menos una factura a pagar.',
                400
            );
        }

        return [

            'categoria' => $categoria,

            'monto' => round(
                (float) $request->input('monto'),
                2
            ),

            'medio' => $request->input('medio'),

            'nota' => $nota,

            'soporte' => trim(
                (string) $request->input('soporte')
            ) ?: null,

            'id_proveedor' => $request->input('id_proveedor')

                ? (int) $request->input('id_proveedor')

                : null,

            'facturas' => array_values(
                array_unique(
                    array_map(
                        'intval',
                        (array) $request->input('facturas', [])
                    )
                )
            ),
        ];
    }
}