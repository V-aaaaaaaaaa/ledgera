<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditoriaController extends Controller
{
    private const POR_PAGINA = 30;

    public function index(): View
    {
        return view('admin.auditoria');
    }

    public function datos(Request $request): JsonResponse
    {
        $consulta = Auditoria::with([
            'actor:id_usuario,usuario,nombre_completo',
            'afectado:id_usuario,usuario,nombre_completo',
            'puesto:id_puesto,nombre_puesto',
        ])->orderByDesc('id_auditoria');

        // 1. Filtro por fechas
        $desde = (string) $request->query('desde');
        $hasta = (string) $request->query('hasta');

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
            $consulta->where(
                'fecha',
                '>=',
                $desde . ' 00:00:00'
            );
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            $consulta->where(
                'fecha',
                '<=',
                $hasta . ' 23:59:59'
            );
        }

        // 2. Filtro por acción
        $accion = (string) $request->query('accion');

        if ($accion !== '') {
            $consulta->where('accion', $accion);
        }

        // 3. Búsqueda general
        $q = trim((string) $request->query('q'));

        if ($q !== '') {

            $like = '%' . $q . '%';

            $consulta->where(function ($w) use ($like) {

                // Buscar por responsable de la acción
                $w->whereHas('actor', function ($a) use ($like) {
                    $a->where(function ($b) use ($like) {
                        $b->where('usuario', 'like', $like)
                            ->orWhere('nombre_completo', 'like', $like);
                    });
                });

                // Buscar por usuario afectado
                $w->orWhere(function ($b) use ($like) {
                    $b->where('entidad', 'usuarios')
                        ->whereHas('afectado', function ($a) use ($like) {
                            $a->where(function ($c) use ($like) {
                                $c->where('usuario', 'like', $like)
                                    ->orWhere('nombre_completo', 'like', $like);
                            });
                        });
                });

                // Buscar por puesto comercial
                $w->orWhereHas('puesto', function ($p) use ($like) {
                    $p->where('nombre_puesto', 'like', $like);
                });

                // Buscar por descripción o acción
                $w->orWhere('motivo', 'like', $like)
                    ->orWhere('accion', 'like', $like);
            });
        }

        // 4. Paginación
        $total = (clone $consulta)->count();

        $paginas = max(
            1,
            (int) ceil($total / self::POR_PAGINA)
        );

        $pagina = min(
            max(1, (int) $request->query('pagina', 1)),
            $paginas
        );

        $filas = $consulta
            ->forPage($pagina, self::POR_PAGINA)
            ->get()
            ->map(fn (Auditoria $a) => $a->paraPantalla());

        // 5. Catálogo actualizado de acciones
        $acciones = Auditoria::ACCIONES;

        $accionesRegistradas = Auditoria::distinct()
            ->orderBy('accion')
            ->pluck('accion');

        foreach ($accionesRegistradas as $codigo) {
            if (!isset($acciones[$codigo])) {
                $acciones[$codigo] = ucfirst(
                    strtolower(str_replace('_', ' ', $codigo))
                );
            }
        }

        return response()->json([
            'ok' => true,
            'filas' => $filas,
            'total' => $total,
            'pagina' => $pagina,
            'paginas' => $paginas,
            'acciones' => $acciones,
        ]);
    }
}
