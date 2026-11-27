<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondeJson;
use App\Models\Auditoria;
use App\Models\CierreCaja;
use App\Models\Puesto;
use App\Services\CajaService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CierreCajaController extends Controller
{
    use RespondeJson;

    private const MAXIMO = 999999999;

    private const MAXIMO_UMBRAL = 10000000;

    public function __construct(
        private CajaService $caja
    ) {}

    // =====================================================
    // VISTA DE CIERRE
    // =====================================================

    public function index(): View
    {
        return view('operativo.cierre');
    }

    // =====================================================
    // RESUMEN DE CAJA INDIVIDUAL
    // =====================================================

    public function resumen(Request $request): JsonResponse
    {
        $actor = $request->user();

        $puesto = Puesto::findOrFail(
            $actor->id_puesto
        );

        $jornada = $this->caja->abierta($actor);

        return response()->json([

            'ok' => true,

            'abierta' => $jornada !== null,

            'es_dueno' => $actor->rol === 'Dueno',

            'umbral' => (float) $puesto->umbral_descuadre,

            'jornada' => $jornada ? [

                'hora' => $jornada->fecha_apertura
                    ?->format('g:i a'),

                'fecha' => $jornada->fecha_apertura
                    ?->format('d/m/Y'),

                'abierta_por' => $jornada->abiertaPor
                    ?->nombre_completo,

            ] : null,

            'cuentas' => $jornada
                ? $this->cuentas($jornada->id_jornada)
                : null,
        ]);
    }

    // =====================================================
    // CERRAR CAJA
    // =====================================================

    public function cerrar(Request $request): JsonResponse
    {
        $actor = $request->user();

        $validador = Validator::make(
            $request->all(),
            [
                'efectivo_contado' => [
                    'required',
                    'numeric',
                    'min:0',
                    'max:' . self::MAXIMO,
                ],

                'esperado_visto' => [
                    'required',
                    'numeric',
                ],

                'explicacion' => [
                    'nullable',
                    'string',
                    'max:500',
                ],
            ],
            [
                'efectivo_contado.required' =>
                    'Escribe cuánto efectivo contaste.',

                'efectivo_contado.numeric' =>
                    'Escribe solo números.',

                'efectivo_contado.min' =>
                    'El efectivo contado no puede ser negativo.',

                'efectivo_contado.max' =>
                    'El monto supera el máximo permitido.',

                'explicacion.max' =>
                    'La explicación supera los 500 caracteres.',
            ]
        );

        if ($validador->fails()) {

            $this->abortar(
                $validador->errors()->first(),
                400
            );
        }

        $contado = round(
            (float) $request->input('efectivo_contado'),
            2
        );

        $visto = round(
            (float) $request->input('esperado_visto'),
            2
        );

        $explicacion = trim(
            (string) $request->input('explicacion')
        ) ?: null;

        $cierre = DB::transaction(function () use (
            $actor,
            $contado,
            $visto,
            $explicacion
        ) {

            $puesto = $this->caja
                ->bloquearPuesto($actor);

            $jornada = $this->caja->abierta(
                $actor,
                true
            );

            if (!$jornada) {

                $this->abortar(
                    'Tu caja ya está cerrada.',
                    409
                );
            }

            // Recalcular los valores en el servidor.

            $cuentas = $this->cuentas(
                $jornada->id_jornada
            );

            $esperado = round(
                $cuentas['efectivo_esperado'],
                2
            );

            // Evitar cierres con información desactualizada.

            if ($esperado !== $visto) {

                throw new HttpResponseException(
                    response()->json([
                        'ok' => false,
                        'cambio' => true,
                        'mensaje' =>
                            'Se registraron movimientos mientras contabas. Revisa el nuevo efectivo esperado.',
                    ], 409)
                );
            }

            $umbral = (float) $puesto
                ->umbral_descuadre;

            $diferencia = round(
                $contado - $esperado,
                2
            );

            if (
                abs($diferencia) > $umbral &&
                $explicacion === null
            ) {

                $this->abortar(
                    'La diferencia supera el límite del puesto. Escribe qué pasó.',
                    400
                );
            }

            $registro = (new CierreCaja())->forceFill([

                'id_jornada' => $jornada->id_jornada,

                'id_puesto' => $actor->id_puesto,

                'id_usuario' => $actor->id_usuario,

                'efectivo_esperado' => $esperado,

                'efectivo_contado' => $contado,

                'umbral_aplicado' => $umbral,

                'explicacion' => $explicacion,
            ]);

            try {

                // El trigger cierra únicamente esta jornada.

                $registro->save();

            } catch (QueryException $e) {

                if ((int) ($e->errorInfo[1] ?? 0) === 1062) {

                    $this->abortar(
                        'Esta jornada ya fue cerrada.',
                        409
                    );
                }

                throw $e;
            }

            Auditoria::registrar(
                'CAJA_CERRADA',
                $registro->id_cierre,
                $registro->id_puesto,

                abs($diferencia) > $umbral
                    ? 'Cierre con descuadre'
                    : null,

                $actor->id_usuario,
                'cierres_caja',
                null,
                [
                    'efectivo_esperado' => $esperado,
                    'efectivo_contado' => $contado,
                    'diferencia' => $diferencia,
                    'umbral_aplicado' => $umbral,
                ]
            );

            return [
                'esperado' => $esperado,
                'contado' => $contado,
                'diferencia' => $diferencia,
            ];

        }, 3);

        return response()->json([
            'ok' => true,
            'mensaje' => 'Caja cerrada.',
            'cierre' => $cierre,
        ], 201);
    }

    // =====================================================
    // CAMBIAR UMBRAL DE DESCUADRE
    // =====================================================

    public function cambiarUmbral(
        Request $request
    ): JsonResponse {

        $actor = $request->user();

        abort_unless(
            $actor->rol === 'Dueno',
            403
        );

        $validador = Validator::make(
            $request->all(),
            [
                'umbral' => [
                    'required',
                    'numeric',
                    'min:0',
                    'max:' . self::MAXIMO_UMBRAL,
                ],
            ],
            [
                'umbral.required' =>
                    'Escribe el límite de descuadre.',

                'umbral.numeric' =>
                    'Escribe solo números.',

                'umbral.min' =>
                    'El límite no puede ser negativo.',

                'umbral.max' =>
                    'El límite máximo es $10.000.000.',
            ]
        );

        if ($validador->fails()) {

            $this->abortar(
                $validador->errors()->first(),
                400
            );
        }

        $nuevo = round(
            (float) $request->input('umbral'),
            2
        );

        DB::transaction(function () use ($actor, $nuevo) {

            $puesto = $this->caja
                ->bloquearPuesto($actor);

            $anterior = (float) $puesto
                ->umbral_descuadre;

            $puesto->forceFill([
                'umbral_descuadre' => $nuevo,
            ])->save();

            Auditoria::registrar(
                'UMBRAL_CAMBIADO',
                $puesto->id_puesto,
                $puesto->id_puesto,
                'Límite de descuadre de caja',
                $actor->id_usuario,
                'puestos',
                ['umbral_descuadre' => $anterior],
                ['umbral_descuadre' => $nuevo]
            );

        }, 3);

        return response()->json([
            'ok' => true,
            'mensaje' => 'Límite actualizado.',
        ]);
    }

    // =====================================================
    // CUENTAS FINANCIERAS DE LA JORNADA
    // =====================================================

    private function cuentas(int $idJornada): array
    {
        $fila = DB::table('v_efectivo_jornada')
            ->where('id_jornada', $idJornada)
            ->first();

        abort_unless(
            $fila,
            500,
            'No se pudo calcular el resumen de caja.'
        );

        $n = fn ($valor) => round(
            (float) $valor,
            2
        );

        return [

            'efectivo_inicial' =>
                $n($fila->efectivo_inicial),

            'ventas_efectivo' =>
                $n($fila->ventas_efectivo),

            'abonos_efectivo' =>
                $n($fila->abonos_efectivo),

            'reversiones_efectivo' =>
                $n($fila->reversiones_efectivo),

            'egresos_efectivo' =>
                $n($fila->egresos_efectivo),

            'efectivo_esperado' =>
                $n($fila->efectivo_esperado),

            'ventas_digital' =>
                $n($fila->ventas_digital),

            'abonos_digital' =>
                $n($fila->abonos_digital),

            'egresos_digital' =>
                $n($fila->egresos_digital),

            'ventas_credito' =>
                $n($fila->ventas_credito),
        ];
    }
}