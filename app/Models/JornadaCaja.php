<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jornada de caja de un puesto (tabla `jornadas_caja`), RF-020.
 * Se crea al abrir caja y se cierra sola cuando se registra el cierre (trigger de la BD).
 * Puede cruzar medianoche: la fecha operativa es la de apertura.
 * La BD garantiza que solo haya UNA jornada abierta por puesto.
 */
class JornadaCaja extends Model
{
    protected $table = 'jornadas_caja';
    protected $primaryKey = 'id_jornada';
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'efectivo_inicial' => 'decimal:2',
            'efectivo_sugerido' => 'decimal:2',
            'fecha_apertura' => 'datetime',
        ];
    }

    public function abiertaPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_apertura', 'id_usuario');
    }

    // La jornada abierta del puesto (o null si la caja está cerrada)
    public static function abiertaDe(int $idPuesto): ?self
    {
        return self::where('id_puesto', $idPuesto)
            ->where('estado', 'Abierta')
            ->first();
    }
}
