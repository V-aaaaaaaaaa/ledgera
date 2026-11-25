<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Proveedor de mercancía de un puesto (tabla `proveedores`).
 * Cada puesto tiene sus propios proveedores: nunca se comparten entre puestos.
 * No se borra: se desactiva, para no perder el historial de facturas y pagos.
 */
class Proveedor extends Model
{
    protected $table = 'proveedores';
    protected $primaryKey = 'id_proveedor';
    public $timestamps = false;   // la tabla usa fecha_registro, no created_at/updated_at

    protected function casts(): array
    {
        return [
            'consentimiento_datos' => 'datetime',
            'fecha_registro' => 'datetime',
        ];
    }

    public function puesto(): BelongsTo
    {
        return $this->belongsTo(Puesto::class, 'id_puesto', 'id_puesto');
    }

    // Lo que necesita la pantalla (sin datos internos)
    public function paraPantalla(): array
    {
        return [
            'id' => $this->id_proveedor,
            'nombre' => $this->nombre_razon_social,
            'telefono' => $this->telefono,
            'contacto' => $this->contacto_principal,
            'estado' => $this->estado,
            'consentimiento' => $this->consentimiento_datos !== null,
        ];
    }
}
