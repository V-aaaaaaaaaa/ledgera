<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondeJson;
use App\Models\Auditoria;
use App\Models\JornadaCaja;
use App\Models\Puesto;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Apertura de caja (RF-020). La pueden usar el Dueño y el Vendedor de su puesto.
 * - Solo una caja abierta por puesto.
 * - El monto inicial se precarga con el efectivo contado en el último cierre.
 * - El monto se puede corregir mientras la caja esté abierta (queda en auditoría).
 */
class CajaController extends Controller
{
    use RespondeJson;

    private const MAXIMO = 999999999;   // tope razonable para evitar errores de digitación

    // ¿La caja del puesto está abierta? Lo usa el dashboard al cargar.
    public function estado(Request $request): JsonResponse
    {
        $idPuesto = (int) $request->user()->id_puesto;
        $jornada = JornadaCaja::with('abiertaPor:id_usuario,nombre_completo')
            ->where('id_puesto', $idPuesto)
            ->where('estado', 'Abierta')
            ->first();

        return response()->json([
            'ok' => true,
            'abierta' => $jornada !== null,
            'jornada' => $jornada ? [
                'efectivo_inicial' => (float) $jornada->efectivo_inicial,
                'hora' => $jornada->fecha_apertura?->format('g:i a'),
                'fecha' => $jornada->fecha_apertura?->format('d/m/Y'),
                'abierta_por' => $jornada->abiertaPor?->nombre_completo,
            ] : null,
            // Solo se usa si la caja está cerrada: monto sugerido para abrir
            'sugerido' => $jornada ? null : $this->ultimoContado($idPuesto),
        ]);
    }

    public function abrir(Request $request): JsonResponse
    {
        $actor = $request->user();
        $monto = $this->validarMonto($request);

        $jornada = DB::transaction(function () use ($actor, $monto) {
            // Bloquea el puesto: si dos personas abren al mismo tiempo, la segunda espera
            // y luego ve que ya hay caja abierta.
            Puesto::lockForUpdate()->findOrFail($actor->id_puesto);

            if (JornadaCaja::abiertaDe($actor->id_puesto)) {
                $this->abortar('La caja ya está abierta. Recarga la pantalla.', 409);
            }

            // El sugerido lo calcula el servidor (no se confía en lo que mande el navegador)
            $sugerido = $this->ultimoContado($actor->id_puesto);

            $j = (new JornadaCaja())->forceFill([
                'id_puesto' => $actor->id_puesto,
                'id_usuario_apertura' => $actor->id_usuario,
                'efectivo_inicial' => $monto,
                'efectivo_sugerido' => $sugerido,
                'estado' => 'Abierta',
            ]);

            try {
                $j->save();
            } catch (QueryException $e) {
                // Respaldo: el UNIQUE de la BD también impide dos cajas abiertas
                if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                    $this->abortar('La caja ya está abierta. Recarga la pantalla.', 409);
                }
                throw $e;
            }

            Auditoria::registrar(
                'CAJA_ABIERTA',
                $j->id_jornada,
                $j->id_puesto,
                $sugerido !== null && (float) $sugerido !== $monto
                    ? 'Monto distinto al del último cierre'
                    : null,
                null,
                'jornadas_caja',
                null,
                ['efectivo_inicial' => $monto, 'efectivo_sugerido' => $sugerido]
            );

            return $j;
        });

        return response()->json([
            'ok' => true,
            'mensaje' => 'Caja abierta. ¡Buena jornada!',
        ], 201);
    }

    // Corregir el monto inicial mientras la caja esté abierta (RF-020: conserva valor anterior)
    public function corregir(Request $request): JsonResponse
    {
        $actor = $request->user();
        $monto = $this->validarMonto($request);

        DB::transaction(function () use ($actor, $monto) {
            $j = JornadaCaja::where('id_puesto', $actor->id_puesto)
                ->where('estado', 'Abierta')
                ->lockForUpdate()
                ->first();

            if (!$j) {
                $this->abortar('La caja ya está cerrada; el monto no se puede corregir.', 409);
            }

            $anterior = (float) $j->efectivo_inicial;

            if ($anterior === $monto) {
                return;   // nada que cambiar
            }

            $j->efectivo_inicial = $monto;
            $j->save();

            Auditoria::registrar(
                'CAJA_MONTO_CORREGIDO',
                $j->id_jornada,
                $j->id_puesto,
                'Corrección del efectivo inicial',
                null,
                'jornadas_caja',
                ['efectivo_inicial' => $anterior],
                ['efectivo_inicial' => $monto]
            );
        });

        return response()->json(['ok' => true, 'mensaje' => 'Monto inicial corregido.']);
    }

    // ---------------------------------------------------------------

    // Efectivo contado en el último cierre del puesto (null si nunca ha cerrado)
    private function ultimoContado(int $idPuesto): ?float
    {
        $valor = DB::table('cierres_caja')
            ->where('id_puesto', $idPuesto)
            ->orderByDesc('id_cierre')
            ->value('efectivo_contado');

        return $valor === null ? null : (float) $valor;
    }

    private function validarMonto(Request $request): float
    {
        $validador = Validator::make($request->all(), [
            'efectivo_inicial' => ['required', 'numeric', 'min:0', 'max:' . self::MAXIMO],
        ], [
            'efectivo_inicial.required' => 'Escribe con cuánto efectivo empiezas (puede ser 0).',
            'efectivo_inicial.numeric' => 'Escribe solo números.',
            'efectivo_inicial.min' => 'El monto no puede ser negativo.',
            'efectivo_inicial.max' => 'Ese monto es demasiado alto. Revisa los ceros.',
        ]);

        if ($validador->fails()) {
            $this->abortar($validador->errors()->first(), 400);
        }

        return round((float) $request->input('efectivo_inicial'), 2);
    }
}
