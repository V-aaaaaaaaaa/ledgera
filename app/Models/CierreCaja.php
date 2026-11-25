<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cierre de caja (tabla `cierres_caja`), RF-015.
 * Es inmutable: la BD no deja editarlo ni borrarlo (triggers).
 * `diferencia` y `con_descuadre` las calcula la BD (columnas generadas).
 * Al guardarlo, un trigger marca la jornada como 'Cerrada'.
 */
class CierreCaja extends Model
{
    protected $table = 'cierres_caja';
    protected $primaryKey = 'id_cierre';
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'efectivo_esperado' => 'decimal:2',
            'efectivo_contado' => 'decimal:2',
            'umbral_aplicado' => 'decimal:2',
            'diferencia' => 'decimal:2',
            'con_descuadre' => 'boolean',
            'fecha_registro' => 'datetime',
        ];
    }
}
