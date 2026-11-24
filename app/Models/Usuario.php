<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

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
            'bloqueado_hasta' => 'datetime',
        ];
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function getRememberTokenName(): string
    {
        return '';
    }

    public function puesto(): BelongsTo
    {
        return $this->belongsTo(
            Puesto::class,
            'id_puesto',
            'id_puesto'
        );
    }

    public function esAdministrador(): bool
    {
        return $this->rol === 'Administrador';
    }

    public function rutaInicio(): string
    {
        if ($this->debe_cambiar_clave) {
            return route('clave.form');
        }

        return $this->esAdministrador()
            ? route('admin.dashboard')
            : route('dashboard');
    }
}
