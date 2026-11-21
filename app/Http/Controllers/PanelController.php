<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * Pantallas de inicio de cada tipo de usuario.
 */
class PanelController extends Controller
{
    // Administrador: gestión de puestos y cuentas (sin datos comerciales)
    public function admin(): View
    {
        return view('admin.dashboard');
    }

    // Dueño y Vendedor: módulos operativos del puesto
    public function operativo(): View
    {
        return view('dashboard');
    }
}
