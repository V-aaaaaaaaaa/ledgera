<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Registro de acciones sobre cuentas, puestos y accesos (solo Administrador).
 * No muestra datos comerciales.
 */
class AuditoriaController extends Controller
{
    private const POR_PAGINA = 30;

    // Pantalla
    public function index(): View
    {
        return view('admin.auditoria');
    }

    // Lista con filtros: desde, hasta (AAAA-MM-DD), accion, q (usuario o nombre) y pagina
    public function datos(Request $request): JsonResponse
    {
        $consulta = Auditoria::with([
            'actor:id_usuario,usuario,nombre_completo',
            'afectado:id_usuario,usuario,nombre_completo',
            'puesto:id_puesto,nombre_puesto',
        ])->orderByDesc('id_auditoria');

        $desde = (string) $request->query('desde');
        $hasta = (string) $request->query('hasta');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
            $consulta->where('fecha', '>=', $desde . ' 00:00:00');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            $consulta->where('fecha', '<=', $hasta . ' 23:59:59');
        }

        $accion = (string) $request->query('accion');
        if ($accion !== '' && array_key_exists($accion, Auditoria::ACCIONES)) {
            $consulta->where('accion', $accion);
        }

        $q = trim((string) $request->query('q'));
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $consulta->where(function ($w) use ($like) {
                $w->whereHas('actor', fn ($a) => $a->where('usuario', 'like', $like)->orWhere('nombre_completo', 'like', $like))
                  ->orWhereHas('afectado', fn ($a) => $a->where('usuario', 'like', $like)->orWhere('nombre_completo', 'like', $like));
            });
        }

        $total = (clone $consulta)->count();
        $paginas = max(1, (int) ceil($total / self::POR_PAGINA));
        $pagina = min(max(1, (int) $request->query('pagina', 1)), $paginas);

        $filas = $consulta->forPage($pagina, self::POR_PAGINA)->get()->map(fn (Auditoria $a) => $a->paraPantalla());

        return response()->json([
            'ok' => true,
            'filas' => $filas,
            'total' => $total,
            'pagina' => $pagina,
            'paginas' => $paginas,
            'acciones' => Auditoria::ACCIONES,
        ]);
    }
}
