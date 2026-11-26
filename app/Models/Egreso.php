<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Salida de dinero del puesto (tabla `egresos_caja`), RF-014.
 * Categorías: Pago_proveedor, Gasto_urgencia, Otros.
 * Siempre pertenece a una jornada de caja abierta.
 * Solo los egresos en efectivo restan del efectivo esperado del cierre.
 */
class Egreso extends Model
{
    protected $table = 'egresos_caja';
    protected $primaryKey = 'id_egreso';
    public $timestamps = false;

    public const CATEGORIAS = ['Pago_proveedor', 'Gasto_urgencia', 'Otros'];
    public const MEDIOS = ['Efectivo', 'Nequi', 'Daviplata', 'Bre-B'];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha_egreso' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'id_proveedor', 'id_proveedor');
    }
}
