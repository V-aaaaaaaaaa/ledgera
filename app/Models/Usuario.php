<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Cuenta de acceso a Ledgera (tabla `usuarios`).
 * Roles: Administrador, Dueno, Vendedor.
 */
class Usuario extends Authenticatable
{
    protected $table = 'usuarios';
    protected $primaryKey = 'id_usuario';
    public $timestamps = false;

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return [
            'debe_cambiar_clave' => 'boolean',
            'fecha_bloqueo' => 'datetime',
        ];
    }

    // La clave vive en la columna password_hash (Laravel espera "password")
    public function getAuthPassword(): string
    {
        return (string) $this->password_hash;
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    // La tabla no tiene remember_token y no usamos "recordarme"
    public function getRememberTokenName(): string
    {
        return '';
    }

    public function puesto(): BelongsTo
    {
        return $this->belongsTo(Puesto::class, 'id_puesto', 'id_puesto');
    }

    public function esAdministrador(): bool
    {
        return $this->rol === 'Administrador';
    }

    // Página a la que va el usuario según su estado y su rol
    public function rutaInicio(): string
    {
        if ($this->debe_cambiar_clave) {
            return route('clave.form');
        }

        return $this->esAdministrador() ? route('admin.dashboard') : route('dashboard');
    }
}
