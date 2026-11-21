<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RespondeJson;
use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Incidencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Atención de incidencias por el Administrador (pestaña "Incidencias" de Auditoría y soporte).
 */
class IncidenciaController extends Controller
{
    use RespondeJson;

    private const POR_PAGINA = 12;

    // Lista con filtros: estado ('pendientes' por defecto, 'todas' o un estado), impacto y pagina
    public function datos(Request $request): JsonResponse
    {
        try {
            $consulta = Incidencia::with([
                'usuario:id_usuario,usuario,nombre_completo,rol',
                'puesto:id_puesto,nombre_puesto',
            ])
                ->orderByRaw("FIELD(estado, 'Abierta', 'En proceso', 'Resuelta')")
                ->orderByRaw("FIELD(impacto, 'Alto', 'Medio', 'Bajo')")
                ->orderByDesc('id_incidencia');

            $estado = (string) $request->query('estado', 'pendientes');
            if (in_array($estado, Incidencia::ESTADOS, true)) {
                $consulta->where('estado', $estado);
            } elseif ($estado !== 'todas') {
                $consulta->whereIn('estado', ['Abierta', 'En proceso']);
            }

            $impacto = (string) $request->query('impacto');
            if (in_array($impacto, Incidencia::IMPACTOS, true)) {
                $consulta->where('impacto', $impacto);
            }

            $total = (clone $consulta)->count();
            $paginas = max(1, (int) ceil($total / self::POR_PAGINA));
            $pagina = min(max(1, (int) $request->query('pagina', 1)), $paginas);

            $filas = $consulta->forPage($pagina, self::POR_PAGINA)->get()->map(fn (Incidencia $i) => [
                'id' => $i->id_incidencia,
                'fecha' => $i->fecha_reporte,
                'actualizada' => $i->fecha_actualizacion,
                'persona' => $i->usuario ? "{$i->usuario->nombre_completo} ({$i->usuario->usuario})" : 'Desconocido',
                'rol' => $i->usuario?->rol,
                'puesto' => $i->puesto?->nombre_puesto,
                'modulo' => $i->modulo,
                'descripcion' => $i->descripcion,
                'impacto' => $i->impacto,
                'estado' => $i->estado,
            ]);

            $conteos = Incidencia::selectRaw('estado, COUNT(*) as total')->groupBy('estado')->pluck('total', 'estado');
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'mensaje' => 'Aún no está creada la tabla de incidencias. Ejecuta database/sql/002_incidencias.sql en Workbench.',
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'filas' => $filas,
            'total' => $total,
            'pagina' => $pagina,
            'paginas' => $paginas,
            'conteos' => [
                'Abierta' => (int) ($conteos['Abierta'] ?? 0),
                'En proceso' => (int) ($conteos['En proceso'] ?? 0),
                'Resuelta' => (int) ($conteos['Resuelta'] ?? 0),
            ],
        ]);
    }

    // Cambia el estado y/o el impacto de una incidencia
    public function actualizar(Request $request, Incidencia $incidencia): JsonResponse
    {
        $validador = Validator::make($request->all(), [
            'estado' => ['required', Rule::in(Incidencia::ESTADOS)],
            'impacto' => ['required', Rule::in(Incidencia::IMPACTOS)],
        ], [
            'estado.required' => 'Elige el estado.',
            'estado.in' => 'Elige un estado válido.',
            'impacto.required' => 'Elige el impacto.',
            'impacto.in' => 'Elige un impacto válido.',
        ]);

        if ($validador->fails()) {
            $this->abortar($validador->errors()->first(), 400);
        }

        $datos = $validador->validated();

        $incidencia->forceFill([
            'estado' => $datos['estado'],
            'impacto' => $datos['impacto'],
            'fecha_actualizacion' => now(),
        ])->save();

        Auditoria::registrar(
            'INCIDENCIA_ACTUALIZADA',
            (int) $incidencia->id_usuario,
            $incidencia->id_puesto,
            "#{$incidencia->id_incidencia}: {$datos['estado']} · Impacto {$datos['impacto']}"
        );

        return response()->json(['ok' => true, 'mensaje' => 'Incidencia actualizada.']);
    }
}
