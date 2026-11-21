<?php

namespace App\Support;

/**
 * Genera claves temporales legibles (sin letras confusas como 0/O o 1/l).
 * La usan el Administrador (crear Dueños) y, más adelante, el Dueño (crear Vendedores).
 */
class ClaveTemporal
{
    public static function generar(int $largo = 8): string
    {
        $caracteres = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $clave = '';

        for ($i = 0; $i < $largo; $i++) {
            $clave .= $caracteres[random_int(0, strlen($caracteres) - 1)];
        }

        return $clave;
    }
}
