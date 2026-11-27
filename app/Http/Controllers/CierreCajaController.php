<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondeJson;
use App\Models\Auditoria;
use App\Models\CierreCaja;
use App\Models\JornadaCaja;
use App\Models\Puesto;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Cierre de caja (RF-015). Lo pueden hacer el Dueño y el Vendedor de su puesto.
 * - El efectivo esperado lo calcula el SERVIDOR con la vista v_efectivo_jornada
 *   (base + ventas en efectivo + abonos en efectivo - reversiones - egresos en efectivo).
 * - Los pagos digitales se muestran aparte: no están en el cajón.
 * - Si la diferencia supera el umbral del puesto, se exige una explicación (pero deja cerrar).
 * - El cierre no se puede editar ni borrar.
 * El umbral lo configura solo el Dueño.
 */
class CierreCajaController extends Controller
{
    use RespondeJson;

    private const MAXIMO = 999999999;
    private const MAXIMO_UMBRAL = 10000000;

    public function index(): View
    {
        return view('operativo.cierre');
    }

    // Lo que se muestra antes de cerrar
    public function resumen(Request $request): JsonResponse
    {
        $actor = $request->user();
        $puesto = Puesto::find($actor->id_puesto);
        $jornada = JornadaCaja::with('abiertaPor:id_usuario,nombre_completo')
            ->where('id_puesto', $actor->id_puesto)
            ->where('estado', 'Abierta')
            ->first();

        return response()->json([
            'ok' => true,
            'abierta' => $jornada !== null,
            'es_dueno' => $actor->rol === 'Dueno',
            'umbral' => (float) $puesto->umbral_descuadre,
            'jornada' => $jornada ? [
                'hora' => $jornada->fecha_apertura?->format('g:i a'),
                'fecha' => $jornada->fecha_apertura?->format('d/m/Y'),
                'abierta_por' => $jornada->abiertaPor?->nombre_completo,
            ] : null,
            'cuentas' => $jornada ? $this->cuentas($jornada->id_jornada) : null,
        ]);
    }

    public function cerrar(Request $request): JsonResponse
    {
        $actor = $request->user();

        $validador = Validator::make($request->all(), [
            'efectivo_contado' => ['required', 'numeric', 'min:0', 'max:' . self::MAXIMO],
            'esperado_visto' => ['required', 'numeric'],
            'explicacion' => ['nullable', 'string', 'max:500'],
        ], [
            'efectivo_contado.required' => 'Escribe cuánto efectivo contaste (puede ser 0).',
            'efectivo_contado.numeric' => 'Escribe solo números.',
            'efectivo_contado.min' => 'El efectivo contado no puede ser negativo.',
            'efectivo_contado.max' => 'Ese monto es demasiado alto. Revisa los ceros.',
            'explicacion.max' => 'La explicación es muy larga (máximo 500 letras).',
        ]);

        if ($validador->fails()) {
            $this->abortar($validador->errors()->first(), 400);
        }

        $contado = round((float) $request->input('efectivo_contado'), 2);
        $visto = round((float) $request->input('esperado_visto'), 2);
        $explicacion = trim((string) $request->input('explicacion')) ?: null;

        $cierre = DB::transaction(function () use ($actor, $contado, $visto, $explicacion) {
            $puesto = Puesto::lockForUpdate()->findOrFail($actor->id_puesto);

            // Bloquea la jornada: mientras se cierra, nadie más la cierra
            $jornada = JornadaCaja::where('id_puesto', $actor->id_puesto)
                ->where('estado', 'Abierta')
                ->lockForUpdate()
                ->first();

            if (!$jornada) {
                $this->abortar('La caja ya está cerrada.', 409);
            }

            // Se recalcula aquí: no se confía en el número que mande el navegador
            $cuentas = $this->cuentas($jornada->id_jornada);
            $esperado = round($cuentas['efectivo_esperado'], 2);

            // Si entró una venta o un gasto mientras contaban, se avisa en vez de cerrar mal
            if ($esperado !== $visto) {
                throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json([
                    'ok' => false,
                    'cambio' => true,
                    'mensaje' => 'Se registraron movimientos mientras contabas. Revisa el nuevo efectivo esperado.',
                ], 409));
            }

            $umbral = (float) $puesto->umbral_descuadre;
            $diferencia = $contado - $esperado;

            if (abs($diferencia) > $umbral && $explicacion === null) {
                $this->abortar('La diferencia supera el límite del puesto. Escribe qué pasó.', 400);
            }

            $c = (new CierreCaja())->forceFill([
                'id_jornada' => $jornada->id_jornada,
                'id_puesto' => $actor->id_puesto,
                'id_usuario' => $actor->id_usuario,
                'efectivo_esperado' => $esperado,
                'efectivo_contado' => $contado,
                'umbral_aplicado' => $umbral,
                'explicacion' => $explicacion,
            ]);

            try {
                $c->save();   // el trigger de la BD pasa la jornada a 'Cerrada'
            } catch (QueryException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                    $this->abortar('Esta caja ya fue cerrada por otra persona.', 409);
                }
                throw $e;
            }

            Auditoria::registrar(
                'CAJA_CERRADA',
                $c->id_cierre,
                $c->id_puesto,
                abs($diferencia) > $umbral ? 'Cierre con descuadre' : null,
                null,
                'cierres_caja',
                null,
                [
                    'efectivo_esperado' => $esperado,
                    'efectivo_contado' => $contado,
                    'diferencia' => round($diferencia, 2),
                    'umbral_aplicado' => $umbral,
                ]
            );

            return ['esperado' => $esperado, 'contado' => $contado, 'diferencia' => round($diferencia, 2)];
        });

        return response()->json(['ok' => true, 'mensaje' => 'Caja cerrada.', 'cierre' => $cierre], 201);
    }

    // Solo el Dueño (lo protege la ruta). Aplica a los próximos cierres.
    public function cambiarUmbral(Request $request): JsonResponse
    {
        $actor = $request->user();

        $validador = Validator::make($request->all(), [
            'umbral' => ['required', 'numeric', 'min:0', 'max:' . self::MAXIMO_UMBRAL],
        ], [
            'umbral.required' => 'Escribe el límite de descuadre.',
            'umbral.numeric' => 'Escribe solo números.',
            'umbral.min' => 'El límite no puede ser negativo.',
            'umbral.max' => 'El límite máximo es $10.000.000.',
        ]);

        if ($validador->fails()) {
            $this->abortar($validador->errors()->first(), 400);
        }

        $nuevo = round((float) $request->input('umbral'), 2);

        DB::transaction(function () use ($actor, $nuevo) {
            $p = Puesto::lockForUpdate()->findOrFail($actor->id_puesto);
            $anterior = (float) $p->umbral_descuadre;

            $p->forceFill(['umbral_descuadre' => $nuevo])->save();

            Auditoria::registrar(
                'UMBRAL_CAMBIADO',
                $p->id_puesto,
                $p->id_puesto,
                'Límite de descuadre de caja',
                null,
                'puestos',
                ['umbral_descuadre' => $anterior],
                ['umbral_descuadre' => $nuevo]
            );
        });

        return response()->json(['ok' => true, 'mensaje' => 'Límite actualizado.']);
    }

    // ---------------------------------------------------------------

    // Cuentas de la jornada desde la vista v_efectivo_jornada (la fórmula vive en la BD)
    private function cuentas(int $idJornada): array
    {
        $f = DB::table('v_efectivo_jornada')->where('id_jornada', $idJornada)->first();

        $n = fn ($v) => round((float) $v, 2);

        return [
            'efectivo_inicial' => $n($f->efectivo_inicial),
            'ventas_efectivo' => $n($f->ventas_efectivo),
            'abonos_efectivo' => $n($f->abonos_efectivo),
            'reversiones_efectivo' => $n($f->reversiones_efectivo),
            'egresos_efectivo' => $n($f->egresos_efectivo),
            'efectivo_esperado' => $n($f->efectivo_esperado),
            'ventas_digital' => $n($f->ventas_digital),
            'abonos_digital' => $n($f->abonos_digital),
            'egresos_digital' => $n($f->egresos_digital),
            'ventas_credito' => $n($f->ventas_credito),
        ];
    }
}
