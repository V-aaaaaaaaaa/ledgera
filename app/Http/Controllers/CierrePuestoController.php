<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondeJson;
use App\Models\Auditoria;
use App\Models\Puesto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cierre del puesto (consolidado).
 * - Cada persona cierra primero SU caja (CierreCajaController): ahí cuenta su plata.
 * - Cuando NO queda ninguna caja abierta en el puesto, cualquiera del puesto
 *   hace el cierre del puesto: suma los cierres individuales pendientes en uno solo.
 * - No se vuelve a contar la plata: solo se suma y se consolida.
 * - Confirmado, no se edita ni se borra (triggers de la BD).
 */
class CierrePuestoController extends Controller
{
    use RespondeJson;

    // Estado: quién tiene la caja abierta, qué cierres faltan por consolidar y el último cierre del puesto
    public function estado(Request $request): JsonResponse
    {
        $puesto = (int) $request->user()->id_puesto;

        $abiertas = $this->cajasAbiertas($puesto)->get()->map(fn ($j) => [
            'persona' => $j->nombre_completo,
            'desde' => Carbon::parse($j->fecha_apertura)->format('d/m g:i a'),
        ]);

        $pendientes = $this->pendientes($puesto)->get();

        $ultimo = DB::table('cierres_puesto as cp')
            ->join('usuarios as u', 'u.id_usuario', '=', 'cp.id_usuario')
            ->where('cp.id_puesto', $puesto)
            ->orderByDesc('cp.id_cierre_puesto')
            ->first(['cp.*', 'u.nombre_completo']);

        return response()->json([
            'ok' => true,
            'abiertas' => $abiertas,
            'pendientes' => $pendientes->map(fn ($c) => [
                'persona' => $c->nombre_completo,
                'hora' => Carbon::parse($c->fecha_registro)->format('d/m g:i a'),
                'esperado' => (float) $c->efectivo_esperado,
                'contado' => (float) $c->efectivo_contado,
                'diferencia' => (float) $c->diferencia,
                'descuadre' => (bool) $c->con_descuadre,
            ]),
            'totales' => $this->totales($pendientes),
            'puede_cerrar' => $abiertas->isEmpty() && $pendientes->isNotEmpty(),
            'ultimo' => $ultimo ? [
                'fecha' => Carbon::parse($ultimo->fecha_registro)->format('d/m/Y g:i a'),
                'persona' => $ultimo->nombre_completo,
                'cajas' => (int) $ultimo->cantidad_cajas,
                'esperado' => (float) $ultimo->efectivo_esperado,
                'contado' => (float) $ultimo->efectivo_contado,
                'diferencia' => (float) $ultimo->diferencia,
            ] : null,
        ]);
    }

    public function cerrar(Request $request): JsonResponse
    {
        $actor = $request->user();
        $idPuesto = (int) $actor->id_puesto;

        $resultado = DB::transaction(function () use ($actor, $idPuesto) {
            // Bloquea el puesto: si dos personas consolidan a la vez, la segunda espera
            Puesto::lockForUpdate()->findOrFail($idPuesto);

            // Regla del equipo: no se cierra el puesto mientras alguien tenga su caja abierta
            $abiertas = $this->cajasAbiertas($idPuesto)->lockForUpdate()->get();
            if ($abiertas->isNotEmpty()) {
                $quienes = $abiertas->pluck('nombre_completo')->implode(', ');
                $this->abortar("Todavía tiene la caja abierta: {$quienes}. Cada persona debe cerrar su caja primero.", 409);
            }

            $pendientes = $this->pendientes($idPuesto)->lockForUpdate()->get();
            if ($pendientes->isEmpty()) {
                $this->abortar('No hay cierres de caja pendientes por consolidar.', 409);
            }

            $t = $this->totales($pendientes);

            $id = DB::table('cierres_puesto')->insertGetId([
                'id_puesto' => $idPuesto,
                'id_usuario' => $actor->id_usuario,
                'fecha_operativa' => $pendientes->min('fecha_operativa'),
                'cantidad_cajas' => $pendientes->count(),
                'efectivo_inicial' => $t['inicial'],
                'efectivo_esperado' => $t['esperado'],
                'efectivo_contado' => $t['contado'],
                'total_digital' => $t['digital'],
                'egresos_digital' => $t['egresos_digital'],
                'total_fiado' => $t['fiado'],
                'cajas_con_descuadre' => $t['con_descuadre'],
            ], 'id_cierre_puesto');

            // UNIQUE(id_cierre) en la BD: un cierre individual no entra en dos cierres del puesto
            DB::table('cierres_puesto_detalle')->insert(
                $pendientes->map(fn ($c) => ['id_cierre_puesto' => $id, 'id_cierre' => $c->id_cierre])->all()
            );

            Auditoria::registrar(
                'CIERRE_PUESTO',
                $id,
                $idPuesto,
                $pendientes->count() . ' cajas consolidadas'
                    . ($t['con_descuadre'] ? " · {$t['con_descuadre']} con descuadre" : ''),
                $actor->id_usuario,
                'cierres_puesto',
                null,
                $t
            );

            return $t + ['cajas' => $pendientes->count()];
        });

        return response()->json(['ok' => true, 'mensaje' => 'Cierre del puesto registrado.', 'cierre' => $resultado], 201);
    }

    // ---------------------------------------------------------------

    private function cajasAbiertas(int $puesto)
    {
        return DB::table('jornadas_caja as j')
            ->join('usuarios as u', 'u.id_usuario', '=', 'j.id_usuario_apertura')
            ->where('j.id_puesto', $puesto)
            ->where('j.estado', 'Abierta')
            ->orderBy('j.fecha_apertura')
            ->select('j.id_jornada', 'j.fecha_apertura', 'u.nombre_completo');
    }

    // Cierres individuales del puesto que todavía no entran en un cierre del puesto
    private function pendientes(int $puesto)
    {
        return DB::table('cierres_caja as c')
            ->join('jornadas_caja as j', 'j.id_jornada', '=', 'c.id_jornada')
            ->join('usuarios as u', 'u.id_usuario', '=', 'c.id_usuario')
            ->join('v_efectivo_jornada as v', 'v.id_jornada', '=', 'c.id_jornada')
            ->leftJoin('cierres_puesto_detalle as d', 'd.id_cierre', '=', 'c.id_cierre')
            ->where('c.id_puesto', $puesto)
            ->whereNull('d.id_cierre')
            ->orderBy('c.id_cierre')
            ->select(
                'c.id_cierre', 'c.efectivo_esperado', 'c.efectivo_contado', 'c.diferencia',
                'c.con_descuadre', 'c.fecha_registro', 'j.fecha_operativa', 'j.efectivo_inicial',
                'u.nombre_completo',
                DB::raw('(v.ventas_digital + v.abonos_digital) AS digital'),
                'v.egresos_digital', 'v.ventas_credito'
            );
    }

    private function totales($pendientes): array
    {
        $suma = fn (string $c) => round((float) $pendientes->sum($c), 2);

        return [
            'inicial' => $suma('efectivo_inicial'),
            'esperado' => $suma('efectivo_esperado'),
            'contado' => $suma('efectivo_contado'),
            'diferencia' => round($suma('efectivo_contado') - $suma('efectivo_esperado'), 2),
            'digital' => $suma('digital'),
            'egresos_digital' => $suma('egresos_digital'),
            'fiado' => $suma('ventas_credito'),
            'con_descuadre' => $pendientes->where('con_descuadre', 1)->count(),
        ];
    }
}
