<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondeJson;
use App\Models\Auditoria;
use App\Models\JornadaCaja;
use App\Services\CajaService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CajaController extends Controller
{
    use RespondeJson;

    private const MAXIMO = 999999999;

    public function __construct(
        private CajaService $caja
    ) {}

    // =====================================================
    // ESTADO DE LA CAJA INDIVIDUAL
    // =====================================================

    public function estado(Request $request): JsonResponse
    {
        $actor = $request->user();

        $jornada = $this->caja->abierta($actor);

        return response()->json([
            'ok' => true,

            'abierta' => $jornada !== null,

            'jornada' => $jornada ? [

                'efectivo_inicial' =>
                    (float) $jornada->efectivo_inicial,

                'hora' => $jornada->fecha_apertura
                    ?->format('g:i a'),

                'fecha' => $jornada->fecha_apertura
                    ?->format('d/m/Y'),

                'abierta_por' => $jornada->abiertaPor
                    ?->nombre_completo,

            ] : null,

            'sugerido' => $jornada
                ? null
                : $this->caja->ultimoContado($actor),
        ]);
    }

    // =====================================================
    // ABRIR CAJA
    // =====================================================

    public function abrir(Request $request): JsonResponse
    {
        $actor = $request->user();

        $monto = $this->validarMonto($request);

        DB::transaction(function () use ($actor, $monto) {

            $this->caja->bloquearPuesto($actor);

            if ($this->caja->abierta($actor, true)) {

                $this->abortar(
                    'Ya tienes una caja abierta.',
                    409
                );
            }

            $sugerido = $this->caja
                ->ultimoContado($actor);

            $jornada = (new JornadaCaja())->forceFill([

                'id_puesto' => $actor->id_puesto,

                'id_usuario_apertura' =>
                    $actor->id_usuario,

                'efectivo_inicial' => $monto,

                'efectivo_sugerido' => $sugerido,

                'estado' => 'Abierta',
            ]);

            try {

                $jornada->save();

            } catch (QueryException $e) {

                if ((int) ($e->errorInfo[1] ?? 0) === 1062) {

                    $this->abortar(
                        'Ya tienes una caja abierta.',
                        409
                    );
                }

                throw $e;
            }

            Auditoria::registrar(
                'CAJA_ABIERTA',
                $jornada->id_jornada,
                $jornada->id_puesto,

                $sugerido !== null &&
                $sugerido !== $monto

                    ? 'Monto distinto al del último cierre'
                    : null,

                $actor->id_usuario,
                'jornadas_caja',
                null,
                [
                    'efectivo_inicial' => $monto,
                    'efectivo_sugerido' => $sugerido,
                ]
            );

        }, 3);

        return response()->json([
            'ok' => true,
            'mensaje' => 'Caja abierta. ¡Buena jornada!',
        ], 201);
    }

    // =====================================================
    // CORREGIR EFECTIVO INICIAL
    // =====================================================

    public function corregir(Request $request): JsonResponse
    {
        $actor = $request->user();

        $monto = $this->validarMonto($request);

        DB::transaction(function () use ($actor, $monto) {

            $this->caja->bloquearPuesto($actor);

            $jornada = $this->caja->abierta(
                $actor,
                true
            );

            if (!$jornada) {

                $this->abortar(
                    'Tu caja está cerrada. No puedes corregir el monto.',
                    409
                );
            }

            $anterior = (float) $jornada
                ->efectivo_inicial;

            if ($anterior === $monto) {
                return;
            }

            $jornada->efectivo_inicial = $monto;

            $jornada->save();

            Auditoria::registrar(
                'CAJA_MONTO_CORREGIDO',
                $jornada->id_jornada,
                $jornada->id_puesto,
                'Corrección del efectivo inicial',
                $actor->id_usuario,
                'jornadas_caja',
                ['efectivo_inicial' => $anterior],
                ['efectivo_inicial' => $monto]
            );

        }, 3);

        return response()->json([
            'ok' => true,
            'mensaje' => 'Monto inicial corregido.',
        ]);
    }

    // =====================================================
    // VALIDAR MONTO
    // =====================================================

    private function validarMonto(Request $request): float
    {
        $validador = Validator::make(
            $request->all(),
            [
                'efectivo_inicial' => [
                    'required',
                    'numeric',
                    'min:0',
                    'max:' . self::MAXIMO,
                ],
            ],
            [
                'efectivo_inicial.required' =>
                    'Escribe con cuánto efectivo empiezas.',

                'efectivo_inicial.numeric' =>
                    'Escribe solo números.',

                'efectivo_inicial.min' =>
                    'El monto no puede ser negativo.',

                'efectivo_inicial.max' =>
                    'El monto supera el máximo permitido.',
            ]
        );

        if ($validador->fails()) {

            $this->abortar(
                $validador->errors()->first(),
                400
            );
        }

        return round(
            (float) $request->input('efectivo_inicial'),
            2
        );
    }
}