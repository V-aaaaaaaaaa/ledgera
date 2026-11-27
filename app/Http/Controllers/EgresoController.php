<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondeJson;
use App\Models\Auditoria;
use App\Models\Egreso;
use App\Models\JornadaCaja;
use App\Models\Proveedor;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Egresos de caja (RF-014).
 * - Gastos de urgencia y Otros: Dueño y Vendedor, con nota obligatoria.
 * - Pago a proveedor: Dueño o Vendedor encargado. Se aplica a las facturas (entradas de mercancía)
 *   que él escoja, de la más antigua a la más nueva, sin pasarse de lo que se debe.
 * - Requiere caja abierta: todo egreso pertenece a una jornada.
 * - El egreso y su aplicación a las facturas se guardan juntos o no se guarda nada (RNF-007).
 */
class EgresoController extends Controller
{
    use RespondeJson;

    private const MAXIMO = 999999999;

    public function index(): View
    {
        return view('operativo.egresos');
    }

    // Egresos de la jornada abierta y, para el Dueño, proveedores a los que se les debe
    public function datos(Request $request): JsonResponse
    {
        $actor = $request->user();
        $jornada = JornadaCaja::abiertaDe((int) $actor->id_puesto);
        $puedePagar = $actor->puede('pagar_proveedores');

        $egresos = $jornada
            ? Egreso::with(['usuario:id_usuario,nombre_completo', 'proveedor:id_proveedor,nombre_razon_social'])
                ->where('id_jornada', $jornada->id_jornada)
                ->orderByDesc('id_egreso')
                ->get()
                ->map(fn (Egreso $e) => [
                    'hora' => $e->fecha_egreso?->format('g:i a'),
                    'categoria' => $e->categoria,
                    'detalle' => $e->categoria === 'Pago_proveedor'
                        ? ($e->proveedor?->nombre_razon_social . ($e->nota ? ' · ' . $e->nota : ''))
                        : $e->nota,
                    'medio' => $e->medio_pago,
                    'monto' => (float) $e->monto,
                    'usuario' => $e->usuario?->nombre_completo,
                    'soporte' => $e->soporte,
                ])
            : collect();

        return response()->json([
            'ok' => true,
            'abierta' => $jornada !== null,
            'puede_pagar' => $puedePagar,
            'egresos' => $egresos,
            'total_efectivo' => $egresos->where('medio', 'Efectivo')->sum('monto'),
            'total_digital' => $egresos->where('medio', '!=', 'Efectivo')->sum('monto'),
            'proveedores' => $puedePagar ? $this->proveedoresConDeuda((int) $actor->id_puesto) : [],
        ]);
    }

    // Facturas con saldo de un proveedor, de la más antigua a la más nueva (solo Dueño)
    public function obligaciones(Request $request, int $proveedor): JsonResponse
    {
        $p = Proveedor::where('id_proveedor', $proveedor)
            ->where('id_puesto', $request->user()->id_puesto)
            ->first();

        if (!$p) {
            $this->abortar('Ese proveedor no existe.', 404);
        }

        $facturas = DB::table('v_entradas_saldo as s')
            ->join('entradas_mercancia as e', 'e.id_entrada', '=', 's.id_entrada')
            ->where('s.id_proveedor', $p->id_proveedor)
            ->where('s.id_puesto', $p->id_puesto)
            ->where('s.saldo_pendiente', '>', 0)
            ->orderBy('e.fecha_entrada')
            ->get(['s.id_entrada', 'e.documento_soporte', 'e.fecha_entrada', 's.valor_acordado', 's.pagado', 's.saldo_pendiente', 's.estado_pago'])
            ->map(fn ($f) => [
                'id' => $f->id_entrada,
                'documento' => $f->documento_soporte,
                'fecha' => date('d/m/Y', strtotime($f->fecha_entrada)),
                'valor' => (float) $f->valor_acordado,
                'pagado' => (float) $f->pagado,
                'saldo' => (float) $f->saldo_pendiente,
                'estado' => $f->estado_pago,
            ]);

        return response()->json(['ok' => true, 'facturas' => $facturas]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $request->user();
        $d = $this->validar($request);
        $esPago = $d['categoria'] === 'Pago_proveedor';

        // Pagar a proveedores: Dueño o Vendedor encargado
        if ($esPago && !$actor->puede('pagar_proveedores')) {
            $this->abortar('Solo el Dueño o el encargado pueden registrar pagos a proveedores.', 403);
        }

        DB::transaction(function () use ($actor, $d, $esPago) {
            // Bloquea la caja: el egreso y un cierre nunca se cruzan
            $jornada = JornadaCaja::where('id_puesto', $actor->id_puesto)
                ->where('estado', 'Abierta')
                ->lockForUpdate()
                ->first();

            if (!$jornada) {
                $this->abortar('La caja está cerrada. Ábrela para registrar egresos.', 409);
            }

            $aplicaciones = $esPago ? $this->repartirPago($actor, $d) : [];

            $e = (new Egreso())->forceFill([
                'id_puesto' => $actor->id_puesto,
                'id_jornada' => $jornada->id_jornada,
                'id_usuario' => $actor->id_usuario,
                'id_proveedor' => $esPago ? $d['id_proveedor'] : null,
                'categoria' => $d['categoria'],
                'medio_pago' => $d['medio'],
                'monto' => $d['monto'],
                'nota' => $d['nota'],
                'soporte' => $d['soporte'],
            ]);
            $e->save();

            foreach ($aplicaciones as $a) {
                DB::table('pagos_proveedor_detalle')->insert([
                    'id_egreso' => $e->id_egreso,
                    'id_entrada' => $a['id_entrada'],
                    'monto_aplicado' => $a['monto'],
                ]);
            }

            Auditoria::registrar(
                'EGRESO_REGISTRADO',
                $e->id_egreso,
                $actor->id_puesto,
                str_replace('_', ' ', $d['categoria']),
                null,
                'egresos_caja',
                null,
                [
                    'monto' => $d['monto'],
                    'medio' => $d['medio'],
                    'nota' => $d['nota'],
                    'aplicado_a' => $aplicaciones,
                ]
            );
        });

        return response()->json(['ok' => true, 'mensaje' => 'Egreso registrado.'], 201);
    }

    // ---------------------------------------------------------------

    // Reparte el pago entre las facturas escogidas, de la más vieja a la más nueva.
    // Bloquea esas facturas para que dos pagos al mismo tiempo no paguen de más.
    private function repartirPago($actor, array $d): array
    {
        $facturas = DB::table('entradas_mercancia')
            ->whereIn('id_entrada', $d['facturas'])
            ->where('id_puesto', $actor->id_puesto)
            ->where('id_proveedor', $d['id_proveedor'])
            ->orderBy('fecha_entrada')
            ->orderBy('id_entrada')
            ->lockForUpdate()
            ->get(['id_entrada', 'documento_soporte']);

        if ($facturas->count() !== count($d['facturas'])) {
            $this->abortar('Alguna factura no es de este proveedor. Recarga la pantalla.', 409);
        }

        // Saldos leídos DESPUÉS de bloquear: ya incluyen cualquier pago anterior
        $saldos = DB::table('v_entradas_saldo')
            ->whereIn('id_entrada', $d['facturas'])
            ->pluck('saldo_pendiente', 'id_entrada');

        $totalDeuda = round($saldos->sum(), 2);

        if ($totalDeuda <= 0) {
            $this->abortar('Esas facturas ya están pagadas. Recarga la pantalla.', 409);
        }
        if ($d['monto'] > $totalDeuda) {
            $this->abortar('El monto supera lo que se debe en esas facturas ($'
                . number_format($totalDeuda, 0, ',', '.') . '). Corrige el monto.', 400);
        }

        $restante = $d['monto'];
        $aplicaciones = [];

        foreach ($facturas as $f) {
            $saldo = round((float) ($saldos[$f->id_entrada] ?? 0), 2);
            if ($restante <= 0 || $saldo <= 0) {
                continue;
            }
            $aplica = min($saldo, $restante);
            $aplicaciones[] = ['id_entrada' => $f->id_entrada, 'documento' => $f->documento_soporte, 'monto' => $aplica];
            $restante = round($restante - $aplica, 2);
        }

        return $aplicaciones;
    }

    private function proveedoresConDeuda(int $idPuesto): array
    {
        return DB::table('v_entradas_saldo as s')
            ->join('proveedores as p', 'p.id_proveedor', '=', 's.id_proveedor')
            ->where('s.id_puesto', $idPuesto)
            ->where('s.saldo_pendiente', '>', 0)
            ->groupBy('p.id_proveedor', 'p.nombre_razon_social')
            ->orderBy('p.nombre_razon_social')
            ->selectRaw('p.id_proveedor AS id, p.nombre_razon_social AS nombre, SUM(s.saldo_pendiente) AS deuda')
            ->get()
            ->map(fn ($p) => ['id' => $p->id, 'nombre' => $p->nombre, 'deuda' => (float) $p->deuda])
            ->all();
    }

    private function validar(Request $request): array
    {
        $v = Validator::make($request->all(), [
            'categoria' => ['required', 'in:' . implode(',', Egreso::CATEGORIAS)],
            'monto' => ['required', 'numeric', 'gt:0', 'max:' . self::MAXIMO],
            'medio' => ['required', 'in:' . implode(',', Egreso::MEDIOS)],
            'nota' => ['nullable', 'string', 'max:255'],
            'soporte' => ['nullable', 'string', 'max:255', 'regex:/^[\pL\pN .,#\/-]+$/u'],
            'id_proveedor' => ['required_if:categoria,Pago_proveedor', 'nullable', 'integer'],
            'facturas' => ['required_if:categoria,Pago_proveedor', 'array'],
            'facturas.*' => ['integer'],
        ], [
            'categoria.*' => 'Elige el tipo de egreso.',
            'monto.required' => 'Escribe el monto.',
            'monto.numeric' => 'Escribe solo números.',
            'monto.gt' => 'El monto debe ser mayor que cero.',
            'monto.max' => 'Ese monto es demasiado alto. Revisa los ceros.',
            'medio.*' => 'Elige cómo se pagó.',
            'nota.max' => 'La nota es muy larga (máximo 255 letras).',
            'soporte.max' => 'El soporte es muy largo.',
            'soporte.regex' => 'El soporte solo puede tener letras, números y . , # / -',
            'id_proveedor.required_if' => 'Elige el proveedor.',
            'facturas.required_if' => 'Elige al menos una factura a pagar.',
            'facturas.*' => 'Factura no válida.',
        ]);

        if ($v->fails()) {
            $this->abortar($v->errors()->first(), 400);
        }

        $nota = trim((string) $request->input('nota')) ?: null;
        $categoria = $request->input('categoria');

        if ($categoria !== 'Pago_proveedor' && $nota === null) {
            $this->abortar('Escribe en qué se gastó (la nota es obligatoria).', 400);
        }
        if ($categoria === 'Pago_proveedor' && count((array) $request->input('facturas')) === 0) {
            $this->abortar('Elige al menos una factura a pagar.', 400);
        }

        return [
            'categoria' => $categoria,
            'monto' => round((float) $request->input('monto'), 2),
            'medio' => $request->input('medio'),
            'nota' => $nota,
            'soporte' => trim((string) $request->input('soporte')) ?: null,
            'id_proveedor' => $request->input('id_proveedor') ? (int) $request->input('id_proveedor') : null,
            'facturas' => array_values(array_unique(array_map('intval', (array) $request->input('facturas', [])))),
        ];
    }
}
