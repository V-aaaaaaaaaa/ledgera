<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $table = 'usuarios';
    protected $primaryKey = 'id_usuario';
    public $timestamps = false;

    protected $hidden = ['password_hash'];

    protected $casts = [
        'bloqueado_hasta' => 'datetime',
        'debe_cambiar_clave' => 'boolean',
    ];

    public function getAuthPasswordName()
    {
        return 'password_hash';
    }

    public function getRememberTokenName()
    {
        return '';
    }
}
