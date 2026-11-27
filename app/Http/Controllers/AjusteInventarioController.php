<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ajustes de inventario (RF-008). Dueño y Vendedor.
 * - Merma: mercancía dañada, podrida o perdida -> descuenta del stock.
 * - Corrección por conteo: se cuenta lo que hay de verdad y el sistema
 *   calcula la diferencia (sube o baja el stock).
 * El ajuste solo toca inventario: no cambia ventas, pagos ni fiados.
 * Queda en ajustes_inventario (kardex: stock antes y después) y en la auditoría.
 */
class AjusteInventarioController extends Controller
{
    public const TIPOS = ['Merma', 'Correccion_conteo'];

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();

        $v = $request->validate([
            'id_inventario' => ['required', 'integer'],
            'tipo_ajuste' => ['required', 'in:' . implode(',', self::TIPOS)],
            'cantidad' => ['required', 'integer', 'min:0', 'max:1000000'],
            'motivo' => ['required', 'string', 'max:255', 'regex:/^[\pL\pN .,#()\/-]+$/u'],
        ], [
            'tipo_ajuste.*' => 'Elige si es una merma o un conteo.',
            'cantidad.required' => 'Escribe la cantidad.',
            'cantidad.integer' => 'La cantidad debe ser un número entero.',
            'cantidad.min' => 'La cantidad no puede ser negativa.',
            'cantidad.max' => 'Esa cantidad es demasiado alta. Revisa los ceros.',
            'motivo.required' => 'Escribe el motivo del ajuste.',
            'motivo.max' => 'El motivo es muy largo (máximo 255 letras).',
            'motivo.regex' => 'El motivo solo puede tener letras, números y . , # ( ) / -',
        ]);

        $esMerma = $v['tipo_ajuste'] === 'Merma';
        $cantidad = (int) $v['cantidad'];
        $motivo = trim(preg_replace('/\s+/u', ' ', $v['motivo']));

        if ($esMerma && $cantidad === 0) {
            $this->rechazar('cantidad', 'La merma debe ser mayor que cero.');
        }

        $resultado = DB::transaction(function () use ($actor, $v, $esMerma, $cantidad, $motivo) {
            // Bloquea la presentación: un ajuste y una venta nunca se cruzan
            $inv = DB::table('inventario_calidades as i')
                ->join('tipos_producto as t', 't.id_tipo', '=', 'i.id_tipo')
                ->join('productos as p', 'p.id_producto', '=', 't.id_producto')
                ->where('i.id_inventario', $v['id_inventario'])
                ->where('p.id_puesto', $actor->id_puesto)      // solo inventario de su puesto
                ->lockForUpdate()
                ->first(['i.id_inventario', 'i.stock_actual', 'p.nombre_producto', 't.nombre_tipo', 'i.unidad_comercial']);

            if (!$inv) {
                $this->rechazar('id_inventario', 'Ese producto no existe en tu puesto.');
            }

            $antes = (int) $inv->stock_actual;

            if ($esMerma) {
                if ($cantidad > $antes) {
                    $this->rechazar('cantidad', "Solo hay {$antes} en inventario. Si hay un error de conteo, usa \"Conteo\".");
                }
                $despues = $antes - $cantidad;
            } else {
                $despues = $cantidad;   // lo que contaron es lo que queda
                if ($despues === $antes) {
                    $this->rechazar('cantidad', 'El conteo es igual a lo que dice el sistema: no hay nada que ajustar.');
                }
            }

            $idAjuste = DB::table('ajustes_inventario')->insertGetId([
                'id_inventario' => $inv->id_inventario,
                'id_usuario' => $actor->id_usuario,
                'tipo_ajuste' => $v['tipo_ajuste'],
                'cantidad' => $cantidad,
                'stock_antes' => $antes,
                'stock_despues' => $despues,
                'motivo' => $motivo,
            ], 'id_ajuste');

            DB::table('inventario_calidades')
                ->where('id_inventario', $inv->id_inventario)
                ->update(['stock_actual' => $despues]);

            $nombre = "{$inv->nombre_producto} {$inv->nombre_tipo} ({$inv->unidad_comercial})";

            Auditoria::registrar(
                'AJUSTE_INVENTARIO',
                $idAjuste,
                $actor->id_puesto,
                ($esMerma ? 'Merma' : 'Conteo') . ": {$nombre} · {$motivo}",
                $actor->id_usuario,
                'ajustes_inventario',
                ['stock' => $antes],
                ['stock' => $despues]
            );

            return [$nombre, $antes, $despues];
        });

        [$nombre, $antes, $despues] = $resultado;

        return back()->with('success', "Inventario ajustado: {$nombre} pasó de {$antes} a {$despues}.");
    }

    private function rechazar(string $campo, string $mensaje): never
    {
        throw ValidationException::withMessages([$campo => $mensaje]);
    }
}
