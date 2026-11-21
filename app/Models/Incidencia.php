<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Problema que un usuario reporta al soporte (tabla `incidencias`).
 */
class Incidencia extends Model
{
    protected $table = 'incidencias';
    protected $primaryKey = 'id_incidencia';
    public $timestamps = false;

    // Partes del sistema que puede elegir quien reporta
    public const MODULOS = [
        'Ventas',
        'Inventario',
        'Fiado y clientes',
        'Egresos',
        'Cierre de caja',
        'Exportaciones',
        'Mi equipo',
        'Ingreso y clave',
        'Otro',
    ];

    public const IMPACTOS = ['Alto', 'Medio', 'Bajo'];
    public const ESTADOS = ['Abierta', 'En proceso', 'Resuelta'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function puesto(): BelongsTo
    {
        return $this->belongsTo(Puesto::class, 'id_puesto', 'id_puesto');
    }
}
