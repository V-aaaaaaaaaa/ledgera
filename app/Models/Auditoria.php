<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Registro de acciones sobre cuentas, puestos y accesos (tabla `auditoria`).
 * Uso desde cualquier controlador: Auditoria::registrar('USUARIO_CREADO', $idAfectado, $idPuesto, 'detalle');
 */
class Auditoria extends Model
{
    protected $table = 'auditoria';
    protected $primaryKey = 'id_auditoria';
    public $timestamps = false;

    // Código guardado en la BD => texto que ve el Administrador
    public const ACCIONES = [
        'INGRESO' => 'Inició sesión',
        'CUENTA_BLOQUEADA' => 'Cuenta bloqueada por intentos fallidos',
        'CLAVE_CAMBIADA' => 'Cambió su propia clave',
        'CLAVE_RESTABLECIDA' => 'Restableció la clave de una cuenta',
        'USUARIO_CREADO' => 'Creó una cuenta',
        'USUARIO_EDITADO' => 'Editó una cuenta',
        'USUARIO_ACTIVADO' => 'Activó una cuenta',
        'USUARIO_DESACTIVADO' => 'Desactivó una cuenta',
        'PUESTO_CREADO' => 'Creó un puesto',
        'PUESTO_EDITADO' => 'Editó un puesto',
        'PUESTO_ACTIVADO' => 'Activó un puesto',
        'PUESTO_DESACTIVADO' => 'Desactivó un puesto',
        'PUESTO_DUENO_CAMBIADO' => 'Cambió el Dueño de un puesto',
        'INCIDENCIA_REPORTADA' => 'Reportó un problema',
        'INCIDENCIA_ACTUALIZADA' => 'Actualizó una incidencia',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function afectado(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_afectado', 'id_usuario');
    }

    public function puesto(): BelongsTo
    {
        return $this->belongsTo(Puesto::class, 'id_puesto', 'id_puesto');
    }

    // Fila lista para mostrar (hay que cargar antes actor, afectado y puesto)
    public function paraPantalla(): array
    {
        return [
            'fecha' => $this->fecha,
            'accion' => $this->accion,
            'texto' => self::ACCIONES[$this->accion] ?? $this->accion,
            'actor' => $this->actor ? "{$this->actor->nombre_completo} ({$this->actor->usuario})" : 'Sistema',
            'afectado' => $this->afectado ? "{$this->afectado->nombre_completo} ({$this->afectado->usuario})" : null,
            'puesto' => $this->puesto?->nombre_puesto,
            'detalle' => $this->detalle,
        ];
    }

    // Guarda una acción. Si la usa alguien con sesión, "quién" se toma solo.
    // Nunca debe tumbar la operación principal: si falla, solo se anota en el log.
    public static function registrar(
        string $accion,
        ?int $afectado = null,
        ?int $puesto = null,
        ?string $detalle = null,
        ?int $actor = null
    ): void {
        try {
            (new self())->forceFill([
                'id_usuario' => $actor ?? Auth::id(),
                'id_afectado' => $afectado,
                'id_puesto' => $puesto,
                'accion' => $accion,
                'detalle' => $detalle !== null ? mb_substr($detalle, 0, 255) : null,
            ])->save();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
