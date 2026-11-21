<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Puesto comercial de Corabastos (tabla `puestos`).
 */
class Puesto extends Model
{
    protected $table = 'puestos';
    protected $primaryKey = 'id_puesto';
    public $timestamps = false;

    // Cuentas (Dueño y Vendedores) asociadas a este puesto
    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'id_puesto', 'id_puesto');
    }
}
