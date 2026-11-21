<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Incidencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Reporte de problemas por parte del Dueño y del Vendedor (RSM-002 y RSM-003).
 * El Administrador los atiende desde Auditoría y soporte.
 */
class IncidenciaController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validador = Validator::make($request->all(), [
            'modulo' => ['required', Rule::in(Incidencia::MODULOS)],
            'impacto' => ['required', Rule::in(Incidencia::IMPACTOS)],
            'descripcion' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'modulo.required' => 'Elige en qué parte pasó.',
            'modulo.in' => 'Elige en qué parte pasó.',
            'impacto.required' => 'Elige qué tan grave es.',
            'impacto.in' => 'Elige qué tan grave es.',
            'descripcion.required' => 'Cuéntanos qué pasó.',
            'descripcion.min' => 'Cuéntanos un poco más (mínimo 10 letras).',
            'descripcion.max' => 'El mensaje es muy largo (máximo 500 letras).',
        ]);

        if ($validador->fails()) {
            return response()->json(['ok' => false, 'mensaje' => $validador->errors()->first()], 400);
        }

        $datos = $validador->validated();
        $usuario = $request->user();

        try {
            $incidencia = (new Incidencia())->forceFill([
                'id_usuario' => $usuario->id_usuario,
                'id_puesto' => $usuario->id_puesto,
                'modulo' => $datos['modulo'],
                'descripcion' => $datos['descripcion'],
                'impacto' => $datos['impacto'],
            ]);
            $incidencia->save();
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'mensaje' => 'No se pudo enviar el reporte. Intenta de nuevo o avisa al Administrador.',
            ], 500);
        }

        Auditoria::registrar(
            'INCIDENCIA_REPORTADA',
            null,
            $usuario->id_puesto,
            "#{$incidencia->id_incidencia} · {$datos['modulo']} · Impacto {$datos['impacto']}"
        );

        return response()->json([
            'ok' => true,
            'mensaje' => "Reporte enviado (número {$incidencia->id_incidencia}). Gracias por avisarnos.",
            'numero' => $incidencia->id_incidencia,
        ], 201);
    }
}
