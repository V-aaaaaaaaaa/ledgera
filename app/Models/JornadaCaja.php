<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        return $this->belongsTo(
            Usuario::class,
            'id_usuario_apertura',
            'id_usuario'
        );
    }

    public static function consultaAbiertaDe(
        int $idPuesto,
        int $idUsuario
    ): Builder {

        return self::query()
            ->where('id_puesto', $idPuesto)
            ->where('id_usuario_apertura', $idUsuario)
            ->where('estado', 'Abierta');
    }

    public static function abiertaDe(
        int $idPuesto,
        int $idUsuario
    ): ?self {

        return self::consultaAbiertaDe(
            $idPuesto,
            $idUsuario
        )->first();
    }
}