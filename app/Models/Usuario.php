<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;

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
            'es_encargado' => 'boolean',
            'encargado_desde' => 'datetime',
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

    /**
     * Poderes del Dueño que puede recibir un Vendedor encargado.
     * Lo que NO está aquí (Mi equipo, límite de descuadre, productos y calidades,
     * desactivar proveedores, nombrar encargados) es solo del Dueño, siempre.
     */
    public const PODERES_DELEGABLES = [
        'pagar_proveedores',       // registrar pagos a proveedores (egresos)
        'gestionar_proveedores',   // crear y editar proveedores
        'ajustes_cierre',          // ajustes post-cierre (RF-016, cuando se construya)
    ];

    // ¿Puede usar este poder? Dueño: todos. Vendedor encargado: solo los delegables.
    public function puede(string $poder): bool
    {
        if ($this->rol === 'Dueno') {
            return true;
        }

        return $this->rol === 'Vendedor'
            && $this->es_encargado
            && in_array($poder, self::PODERES_DELEGABLES, true);
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

        // Primer ingreso del Dueño: si su puesto no tiene productos, primero los registra
        // (a menos que haya dicho "Lo hago después" en esta sesión)
        if (
            $this->rol === 'Dueno'
            && !session('configuracion_omitida')
            && !DB::table('productos')->where('id_puesto', $this->id_puesto)->exists()
        ) {
            return route('configuracion.inicial');
        }

        return $this->esAdministrador()
            ? route('admin.dashboard')
            : route('dashboard');
    }
}
