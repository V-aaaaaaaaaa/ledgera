<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Incidencia;
use App\Models\Puesto;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

/**
 * Reportes e indicadores DEL SISTEMA (solo Administrador).
 * Cuenta puestos, cuentas, seguridad y actividad. No muestra ventas, inventario ni caja de los puestos.
 */
class ReporteController extends Controller
{
    // Pantalla
    public function index(): View
    {
        return view('admin.reportes');
    }

    public function datos(): JsonResponse
    {
        // ---------- Puestos ----------
        $puestos = [
            'activos' => Puesto::where('estado', 'Activo')->count(),
            'inactivos' => Puesto::where('estado', 'Inactivo')->count(),
            'sin_dueno' => Puesto::where('estado', 'Activo')
                ->whereDoesntHave('usuarios', fn ($q) => $q->where('rol', 'Dueno')->where('estado', 'Activo'))
                ->count(),
        ];

        // ---------- Cuentas ----------
        $porRol = Usuario::where('estado', 'Activo')
            ->selectRaw('rol, COUNT(*) as total')
            ->groupBy('rol')
            ->pluck('total', 'rol');

        $cuentas = [
            'administradores' => (int) ($porRol['Administrador'] ?? 0),
            'duenos' => (int) ($porRol['Dueno'] ?? 0),
            'vendedores' => (int) ($porRol['Vendedor'] ?? 0),
            'inactivas' => Usuario::where('estado', 'Inactivo')->count(),
        ];

        // ---------- Seguridad ----------
        $seguridad = [
            'bloqueadas_ahora' => Usuario::whereNotNull('bloqueado_hasta')   // BD v2: guarda hasta cuándo dura el bloqueo
                ->where('bloqueado_hasta', '>', now())
                ->count(),
            'clave_temporal' => Usuario::where('estado', 'Activo')->where('debe_cambiar_clave', 1)->count(),
            'sin_puesto' => Usuario::where('estado', 'Activo')
                ->whereIn('rol', ['Dueno', 'Vendedor'])
                ->whereNull('id_puesto')
                ->count(),
        ];

        // ---------- Actividad (sale de la auditoría; si la tabla aún no existe, se avisa) ----------
        $actividad = null;
        $ultimos = [];
        try {
            $desde = now()->subDays(6)->startOfDay();

            $ingresosPorDia = Auditoria::where('accion', 'INGRESO')
                ->where('fecha', '>=', $desde)
                ->selectRaw('DATE(fecha) as dia, COUNT(*) as total')
                ->groupBy('dia')
                ->pluck('total', 'dia');

            $serie = [];
            for ($i = 6; $i >= 0; $i--) {
                $dia = now()->subDays($i);
                $serie[] = [
                    'dia' => $dia->format('d/m'),
                    'total' => (int) ($ingresosPorDia[$dia->toDateString()] ?? 0),
                ];
            }

            $actividad = [
                'ingresos_hoy' => (int) ($ingresosPorDia[now()->toDateString()] ?? 0),
                'bloqueos_7' => Auditoria::where('accion', 'CUENTA_BLOQUEADA')->where('fecha', '>=', $desde)->count(),
                'acciones_7' => Auditoria::where('accion', '<>', 'INGRESO')->where('fecha', '>=', $desde)->count(),
                'serie' => $serie,
            ];

            $ultimos = Auditoria::with([
                'actor:id_usuario,usuario,nombre_completo',
                'afectado:id_usuario,usuario,nombre_completo',
                'puesto:id_puesto,nombre_puesto',
            ])->orderByDesc('id_auditoria')->limit(10)->get()
                ->map(fn (Auditoria $a) => $a->paraPantalla());
        } catch (\Throwable $e) {
            report($e);
        }

        // ---------- Soporte (si la tabla aún no existe, queda en blanco) ----------
        $incidencias = null;
        try {
            $pendientes = Incidencia::whereIn('estado', ['Abierta', 'En proceso']);
            $incidencias = [
                'pendientes' => (clone $pendientes)->count(),
                'altas' => (clone $pendientes)->where('impacto', 'Alto')->count(),
            ];
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'ok' => true,
            'generado' => now()->format('d/m/Y H:i'),
            'puestos' => $puestos,
            'cuentas' => $cuentas,
            'seguridad' => $seguridad,
            'incidencias' => $incidencias,
            'actividad' => $actividad,
            'ultimos' => $ultimos,
        ]);
    }
}
