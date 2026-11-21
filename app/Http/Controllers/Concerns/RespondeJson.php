<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Exceptions\HttpResponseException;

trait RespondeJson
{
    // Corta la petición con un mensaje claro (lo lee el JS)
    protected function abortar(string $mensaje, int $codigo): never
    {
        throw new HttpResponseException(
            response()->json(['ok' => false, 'mensaje' => $mensaje], $codigo)
        );
    }
}
