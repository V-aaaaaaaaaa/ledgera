<?php

namespace App\Services;

use App\Models\JornadaCaja;
use App\Models\Puesto;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use LogicException;

class CajaService
{
    public function bloquearPuesto(Usuario $actor): Puesto
    {
        $this->validarOperador($actor);

        $this->validarTransaccion();

        $puesto = Puesto::query()
            ->lockForUpdate()
            ->findOrFail($actor->id_puesto);

        abort_unless($puesto->estado === 'Activo', 403);

        return $puesto;
    }

    public function abierta(
        Usuario $actor,
        bool $bloquear = false
    ): ?JornadaCaja {

        $this->validarOperador($actor);

        $consulta = JornadaCaja::consultaAbiertaDe(
            (int) $actor->id_puesto,
            (int) $actor->id_usuario
        );

        if ($bloquear) {

            $this->validarTransaccion();

            $consulta->lockForUpdate();

        } else {

            $consulta->with(
                'abiertaPor:id_usuario,nombre_completo'
            );
        }

        return $consulta->first();
    }

    public function ultimoContado(Usuario $actor): ?float
    {
        $this->validarOperador($actor);

        $valor = DB::table('cierres_caja as c')
            ->join(
                'jornadas_caja as j',
                'j.id_jornada',
                '=',
                'c.id_jornada'
            )
            ->where('j.id_puesto', $actor->id_puesto)
            ->where('c.id_puesto', $actor->id_puesto)
            ->where(
                'j.id_usuario_apertura',
                $actor->id_usuario
            )
            ->where('j.estado', 'Cerrada')
            ->orderByDesc('c.id_cierre')
            ->value('c.efectivo_contado');

        return $valor === null
            ? null
            : (float) $valor;
    }

    private function validarOperador(Usuario $actor): void
    {
        abort_unless(
            $actor->id_puesto &&
            in_array(
                $actor->rol,
                ['Dueno', 'Vendedor'],
                true
            ),
            403
        );
    }

    private function validarTransaccion(): void
    {
        if (DB::transactionLevel() === 0) {

            throw new LogicException(
                'El bloqueo de caja requiere una transacción.'
            );
        }
    }
}