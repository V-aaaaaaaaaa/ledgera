<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondeJson;
use App\Models\Auditoria;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Actividad del negocio (solo el Dueño).
 * Junta en una sola lista todo lo que pasó en SU puesto: ventas, abonos, egresos,
 * entradas de mercancía, ajustes de inventario, aperturas y cierres de caja,
 * y cambios de configuración (de la tabla auditoria).
 * Se lee directo de las tablas de operación (la fuente de verdad), no de un registro aparte.
 * Solo consulta: no modifica nada.
 */
class ActividadNegocioController extends Controller
{
    use RespondeJson;

    public const TIPOS = [
        'Venta', 'Venta anulada', 'Abono', 'Egreso', 'Entrada de mercancía',
        'Ajuste de inventario', 'Apertura de caja', 'Cierre de caja', 'Cierre del puesto', 'Cambio', 'Ingreso',
    ];

    private const POR_PAGINA = 30;
    private const MAX_DIAS = 92;   // rango máximo para que la consulta no se vuelva pesada

    // Estas acciones de auditoría ya salen desde su propia tabla (no se repiten)
    private const ACCIONES_REPETIDAS = ['CAJA_ABIERTA', 'CAJA_CERRADA', 'EGRESO_REGISTRADO', 'CIERRE_PUESTO', 'AJUSTE_INVENTARIO'];

    // Acciones de la auditoría que se muestran como "Ingreso" (entradas al sistema)
    private const ACCIONES_INGRESO = ['INGRESO', 'LOGIN_FALLIDO', 'LOGIN_RECHAZADO', 'CUENTA_BLOQUEADA'];

    public function index(): View
    {
        return view('operativo.actividad', ['tipos' => self::TIPOS]);
    }

    public function datos(Request $request): JsonResponse
    {
        $puesto = (int) $request->user()->id_puesto;
        [$desde, $hasta] = $this->rango($request);

        $tipos = array_values(array_intersect((array) $request->query('tipos', []), self::TIPOS));
        $usuario = $request->integer('usuario') ?: null;
        $buscar = trim((string) $request->query('buscar', ''));

        $union = $this->movimientos($puesto, $desde, $hasta, $tipos);

        if (!$union) {
            return response()->json(['ok' => true, 'filas' => [], 'resumen' => [], 'hay_mas' => false, 'equipo' => $this->equipo($puesto)]);
        }

        $base = DB::query()->fromSub($union, 'm')
            ->when($usuario, fn ($q) => $q->where('m.id_usuario', $usuario))
            ->when($buscar !== '', fn ($q) => $q->where('m.detalle', 'like', '%' . $buscar . '%'));

        // Resumen por tipo (con los mismos filtros)
        $resumen = (clone $base)
            ->selectRaw('m.tipo, COUNT(*) AS cantidad, SUM(m.monto) AS total, SUM(m.alerta) AS alertas')
            ->groupBy('m.tipo')
            ->get()
            ->map(fn ($r) => [
                'tipo' => $r->tipo,
                'cantidad' => (int) $r->cantidad,
                'total' => $r->total === null ? null : (float) $r->total,
                'alertas' => (int) $r->alertas,
            ]);

        // Página de resultados, lo más reciente primero
        $pagina = max(1, $request->integer('pagina') ?: 1);
        $filas = (clone $base)
            ->leftJoin('usuarios as u', 'u.id_usuario', '=', 'm.id_usuario')
            ->orderByDesc('m.fecha')
            ->offset(($pagina - 1) * self::POR_PAGINA)
            ->limit(self::POR_PAGINA + 1)          // uno extra para saber si hay más
            ->get(['m.*', 'u.nombre_completo', 'u.rol', 'u.es_encargado']);

        $hayMas = $filas->count() > self::POR_PAGINA;

        return response()->json([
            'ok' => true,
            'filas' => $filas->take(self::POR_PAGINA)->map(fn ($f) => $this->paraPantalla($f))->values(),
            'resumen' => $resumen,
            'hay_mas' => $hayMas,
            'equipo' => $this->equipo($puesto),
        ]);
    }

    // ---------------------------------------------------------------

    // Arma un UNION con cada tipo de movimiento. Cada parte filtra por puesto y fechas
    // (así aprovecha los índices de cada tabla). Todas devuelven las mismas columnas.
    private function movimientos(int $puesto, string $desde, string $hasta, array $tipos): ?Builder
    {
        $quiere = fn (string ...$t) => empty($tipos) || count(array_intersect($t, $tipos)) > 0;
        $partes = [];
        $pesos = fn (string $col) => "FORMAT($col, 0, 'de_DE')";   // 1234567 -> 1.234.567

        if ($quiere('Venta', 'Venta anulada')) {
            $partes[] = DB::table('ventas as v')
                ->leftJoin('clientes as cl', 'cl.id_cliente', '=', 'v.id_cliente')
                ->where('v.id_puesto', $puesto)
                ->whereBetween('v.fecha_venta', [$desde, $hasta])
                ->when(!empty($tipos), fn ($q) => $q->whereIn(
                    DB::raw("IF(v.estado = 'Anulada', 'Venta anulada', 'Venta')"), $tipos))
                ->selectRaw("v.fecha_venta AS fecha,
                    IF(v.estado = 'Anulada', 'Venta anulada', 'Venta') AS tipo,
                    v.id_usuario,
                    CONCAT_WS(' · ', v.canal,
                        IF(v.monto_efectivo > 0, CONCAT('Efectivo $', {$pesos('v.monto_efectivo')}), NULL),
                        IF(v.monto_digital > 0, CONCAT(v.medio_digital, ' $', {$pesos('v.monto_digital')}), NULL),
                        IF(v.monto_credito > 0, CONCAT('Fiado $', {$pesos('v.monto_credito')}, ' a ', cl.nombre_completo), NULL)
                    ) AS detalle,
                    IF(v.estado = 'Anulada', NULL, v.total_venta) AS monto,
                    IF(v.monto_digital > 0 AND v.monto_efectivo = 0 AND v.monto_credito = 0, v.medio_digital,
                       IF(v.monto_credito > 0 AND v.monto_efectivo = 0 AND v.monto_digital = 0, 'Fiado',
                          IF(v.monto_efectivo > 0 AND v.monto_digital = 0 AND v.monto_credito = 0, 'Efectivo', 'Mixto'))) AS medio,
                    IF(v.estado = 'Anulada', 1, 0) AS alerta,
                    NULL AS codigo");
        }

        if ($quiere('Abono')) {
            $partes[] = DB::table('abonos_cartera as a')
                ->join('creditos_cliente as c', 'c.id_credito', '=', 'a.id_credito')
                ->join('ventas as v', 'v.id_venta', '=', 'c.id_venta')
                ->leftJoin('clientes as cl', 'cl.id_cliente', '=', 'v.id_cliente')
                ->leftJoin('abonos_correcciones as x', 'x.id_abono_original', '=', 'a.id_abono')
                ->where('a.id_puesto', $puesto)
                ->whereBetween('a.fecha_abono', [$desde, $hasta])
                ->selectRaw("a.fecha_abono AS fecha, 'Abono' AS tipo, a.id_usuario,
                    CONCAT('Abono de ', COALESCE(cl.nombre_completo, 'cliente'),
                           IF(x.id_correccion IS NOT NULL, CONCAT(' · CORREGIDO: ', x.motivo), '')) AS detalle,
                    a.monto_recibido AS monto, a.medio_pago AS medio,
                    IF(x.id_correccion IS NOT NULL, 1, 0) AS alerta, NULL AS codigo");
        }

        if ($quiere('Egreso')) {
            $partes[] = DB::table('egresos_caja as e')
                ->leftJoin('proveedores as p', 'p.id_proveedor', '=', 'e.id_proveedor')
                ->where('e.id_puesto', $puesto)
                ->whereBetween('e.fecha_egreso', [$desde, $hasta])
                ->selectRaw("e.fecha_egreso AS fecha, 'Egreso' AS tipo, e.id_usuario,
                    CONCAT_WS(' · ',
                        CASE e.categoria WHEN 'Pago_proveedor' THEN CONCAT('Pago a ', p.nombre_razon_social)
                                         WHEN 'Gasto_urgencia' THEN 'Gasto de urgencia' ELSE 'Otros' END,
                        e.nota, IF(e.soporte IS NULL, NULL, CONCAT('Ref. ', e.soporte))) AS detalle,
                    e.monto AS monto, e.medio_pago AS medio, 0 AS alerta, NULL AS codigo");
        }

        if ($quiere('Entrada de mercancía')) {
            $partes[] = DB::table('entradas_mercancia as en')
                ->join('proveedores as p', 'p.id_proveedor', '=', 'en.id_proveedor')
                ->where('en.id_puesto', $puesto)
                ->whereBetween('en.fecha_entrada', [$desde, $hasta])
                ->selectRaw("en.fecha_entrada AS fecha, 'Entrada de mercancía' AS tipo, en.id_usuario,
                    CONCAT('Factura ', en.documento_soporte, ' de ', p.nombre_razon_social) AS detalle,
                    en.valor_acordado AS monto, NULL AS medio, 0 AS alerta, NULL AS codigo");
        }

        if ($quiere('Ajuste de inventario')) {
            // Inventario: producto -> tipo (calidad) -> presentación (unidad comercial)
            $partes[] = DB::table('ajustes_inventario as aj')
                ->join('inventario_calidades as ic', 'ic.id_inventario', '=', 'aj.id_inventario')
                ->join('tipos_producto as tp', 'tp.id_tipo', '=', 'ic.id_tipo')
                ->join('productos as pr', 'pr.id_producto', '=', 'tp.id_producto')
                ->where('pr.id_puesto', $puesto)
                ->whereBetween('aj.fecha_ajuste', [$desde, $hasta])
                ->selectRaw("aj.fecha_ajuste AS fecha, 'Ajuste de inventario' AS tipo, aj.id_usuario,
                    CONCAT(REPLACE(aj.tipo_ajuste, '_', ' de '), ': ', pr.nombre_producto, ' ', tp.nombre_tipo,
                           ' (', ic.unidad_comercial, ')',
                           ' (', aj.stock_antes, ' → ', aj.stock_despues, ') · ', aj.motivo) AS detalle,
                    NULL AS monto, NULL AS medio,
                    IF(aj.tipo_ajuste = 'Merma', 1, 0) AS alerta, NULL AS codigo");
        }

        if ($quiere('Apertura de caja')) {
            $partes[] = DB::table('jornadas_caja as j')
                ->where('j.id_puesto', $puesto)
                ->whereBetween('j.fecha_apertura', [$desde, $hasta])
                ->selectRaw("j.fecha_apertura AS fecha, 'Apertura de caja' AS tipo, j.id_usuario_apertura AS id_usuario,
                    CONCAT('Base inicial $', {$pesos('j.efectivo_inicial')},
                        IF(j.efectivo_sugerido IS NOT NULL AND j.efectivo_sugerido <> j.efectivo_inicial,
                           CONCAT(' (el último cierre dejó $', {$pesos('j.efectivo_sugerido')}, ')'), '')) AS detalle,
                    j.efectivo_inicial AS monto, 'Efectivo' AS medio,
                    IF(j.efectivo_sugerido IS NOT NULL AND j.efectivo_sugerido <> j.efectivo_inicial, 1, 0) AS alerta,
                    NULL AS codigo");
        }

        if ($quiere('Cierre de caja')) {
            $partes[] = DB::table('cierres_caja as ci')
                ->where('ci.id_puesto', $puesto)
                ->whereBetween('ci.fecha_registro', [$desde, $hasta])
                ->selectRaw("ci.fecha_registro AS fecha, 'Cierre de caja' AS tipo, ci.id_usuario,
                    CONCAT('Esperado $', {$pesos('ci.efectivo_esperado')}, ' · Contado $', {$pesos('ci.efectivo_contado')},
                        IF(ci.diferencia = 0, ' · Cuadró',
                           CONCAT(IF(ci.diferencia > 0, ' · Sobró $', ' · Faltó $'), {$pesos('ABS(ci.diferencia)')})),
                        IF(ci.explicacion IS NULL, '', CONCAT(' · ', ci.explicacion))) AS detalle,
                    ci.efectivo_contado AS monto, 'Efectivo' AS medio, ci.con_descuadre AS alerta, NULL AS codigo");
        }

        if ($quiere('Cierre del puesto')) {
            $partes[] = DB::table('cierres_puesto as cp')
                ->where('cp.id_puesto', $puesto)
                ->whereBetween('cp.fecha_registro', [$desde, $hasta])
                ->selectRaw("cp.fecha_registro AS fecha, 'Cierre del puesto' AS tipo, cp.id_usuario,
                    CONCAT(cp.cantidad_cajas, ' cajas · Esperado $', {$pesos('cp.efectivo_esperado')},
                        ' · Contado $', {$pesos('cp.efectivo_contado')},
                        IF(cp.diferencia = 0, ' · Cuadró',
                           CONCAT(IF(cp.diferencia > 0, ' · Sobró $', ' · Faltó $'), {$pesos('ABS(cp.diferencia)')}))) AS detalle,
                    cp.efectivo_contado AS monto, 'Efectivo' AS medio,
                    IF(cp.cajas_con_descuadre > 0, 1, 0) AS alerta, NULL AS codigo");
        }

        if ($quiere('Cambio', 'Ingreso')) {
            $ingreso = "'" . implode("','", self::ACCIONES_INGRESO) . "'";
            $partes[] = DB::table('auditoria as au')
                ->where('au.id_puesto', $puesto)
                ->whereBetween('au.fecha', [$desde, $hasta])
                ->whereNotIn('au.accion', self::ACCIONES_REPETIDAS)
                ->when(!empty($tipos) && !in_array('Cambio', $tipos, true),
                    fn ($q) => $q->whereIn('au.accion', self::ACCIONES_INGRESO))
                ->when(!empty($tipos) && !in_array('Ingreso', $tipos, true),
                    fn ($q) => $q->whereNotIn('au.accion', self::ACCIONES_INGRESO))
                ->selectRaw("au.fecha AS fecha,
                    IF(au.accion IN ($ingreso), 'Ingreso', 'Cambio') AS tipo,
                    COALESCE(au.id_usuario, IF(au.entidad = 'usuarios', au.id_entidad, NULL)) AS id_usuario,
                    COALESCE(au.motivo, '') AS detalle, NULL AS monto, NULL AS medio,
                    IF(au.accion IN ('CUENTA_BLOQUEADA', 'LOGIN_FALLIDO', 'LOGIN_RECHAZADO', 'UMBRAL_CAMBIADO',
                                     'ENCARGADO_ACTIVADO', 'CAJA_MONTO_CORREGIDO'), 1, 0) AS alerta,
                    au.accion AS codigo");
        }

        if (empty($partes)) {
            return null;
        }

        $union = array_shift($partes);
        foreach ($partes as $p) {
            $union->unionAll($p);
        }

        return $union;
    }

    private function paraPantalla($f): array
    {
        // Los cambios muestran el texto de la acción (ej.: "Registró un proveedor") + su detalle
        $detalle = $f->detalle;
        if ($f->codigo) {
            $texto = Auditoria::ACCIONES[$f->codigo] ?? $f->codigo;
            $detalle = $detalle ? "{$texto} · {$detalle}" : $texto;
        }

        $quien = $f->nombre_completo ?? 'Sistema';
        if ($f->rol === 'Vendedor') {
            $quien .= $f->es_encargado ? ' (encargado)' : ' (vendedor)';
        }

        return [
            'fecha' => Carbon::parse($f->fecha)->format('d/m/Y'),
            'hora' => Carbon::parse($f->fecha)->format('g:i a'),
            'tipo' => $f->tipo,
            'quien' => $quien,
            'detalle' => $detalle,
            'monto' => $f->monto === null ? null : (float) $f->monto,
            'medio' => $f->medio,
            'alerta' => (bool) $f->alerta,
        ];
    }

    // Personas del puesto (para el filtro "¿Quién?")
    private function equipo(int $puesto): array
    {
        return Usuario::where('id_puesto', $puesto)
            ->orderByRaw("FIELD(rol, 'Dueno', 'Vendedor')")
            ->orderBy('nombre_completo')
            ->get(['id_usuario', 'nombre_completo', 'rol'])
            ->map(fn ($u) => ['id' => $u->id_usuario, 'nombre' => $u->nombre_completo, 'rol' => $u->rol])
            ->all();
    }

    // Fechas del filtro. Por defecto: hoy. Máximo 92 días para cuidar el rendimiento.
    private function rango(Request $request): array
    {
        try {
            $desde = Carbon::parse($request->query('desde', now()->toDateString()))->startOfDay();
            $hasta = Carbon::parse($request->query('hasta', now()->toDateString()))->endOfDay();
        } catch (\Throwable) {
            $this->abortar('Fecha no válida.', 400);
        }

        if ($desde->gt($hasta)) {
            $this->abortar('La fecha "desde" no puede ser después de "hasta".', 400);
        }
        if ($desde->diffInDays($hasta) > self::MAX_DIAS) {
            $this->abortar('Consulta máximo 3 meses a la vez.', 400);
        }

        return [$desde->toDateTimeString(), $hasta->toDateTimeString()];
    }
}
