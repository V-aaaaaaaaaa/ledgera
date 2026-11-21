Sistema web responsive de contabilidad e inventario para vendedores de Corabastos (Bogotá).
Construido con **Laravel 12 (MVC)**, MySQL, Blade, CSS y JavaScript.

## Qué incluye hoy
- Ingreso con usuario y contraseña, cambio de clave y roles: **Administrador**, **Dueño** y **Vendedor**.
- **Administrador:** usuarios, puestos, auditoría, incidencias de soporte y reportes e indicadores del sistema.
- **Dueño y Vendedor:** inicio con sus módulos y "Reportar un problema". Ventas, inventario, fiado, egresos y cierre de caja están en construcción.

## Requisitos
- XAMPP con **PHP 8.2** y MySQL encendido. En `C:\xampp\php\php.ini` deben estar activas las extensiones `zip`, `openssl`, `mbstring`, `fileinfo`, `curl` y `pdo_mysql`.
- [Composer](https://getcomposer.org) y MySQL Workbench.

## Instalación (primera vez)
1. Clonar el repositorio y cambiar a la rama de Laravel:
   ```powershell
   git clone URL_DEL_REPOSITORIO
   cd NOMBRE_DE_LA_CARPETA
   git checkout laravel
   ```
2. Instalar las dependencias y crear el archivo de configuración:
   ```powershell
   composer install
   copy .env.example .env
   C:\xampp\php\php.exe artisan key:generate
   ```
3. Abrir `.env` y poner la clave de tu MySQL en `DB_PASSWORD=` (déjala vacía si nunca la cambiaste).
4. Crear la base de datos: en MySQL Workbench, ejecutar **en orden** los scripts de `database/sql/` (ver `database/sql/LEEME.md`).
5. Poner una clave conocida al administrador de prueba:
   ```powershell
   C:\xampp\php\php.exe artisan tinker
   ```
   y dentro escribir (en una sola línea):
   ```php
   App\Models\Usuario::where('usuario','soporte')->update(['password_hash'=>Hash::make('Prueba123'),'debe_cambiar_clave'=>false]);
   ```
6. Encender el servidor y abrir http://127.0.0.1:8000
   ```powershell
   C:\xampp\php\php.exe artisan serve
   ```

> El archivo `.env` es personal y **nunca se sube a git**.

## Cómo está organizado
- `app/Models`, `app/Http/Controllers`, `app/Http/Middleware`: modelos, controladores y permisos por rol.
- `resources/views`: pantallas en Blade. `public/css` y `public/js`: estilos y JavaScript.
- `routes/modulos/admin.php` (Administrador) y `routes/modulos/operativo.php` (Dueño y Vendedor): cada área en su archivo para no pisarse.
- `database/sql`: scripts de la base de datos. `tools/aplicar-tema.py`: aplica los colores del tema a un CSS nuevo.
- `CLAUDE.md`: reglas del negocio y convenciones del proyecto (también sirve de guía para Claude Code).

## Forma de trabajo en equipo
- Una **rama por módulo**, creada desde `laravel` (por ejemplo `fiado-cartera`). No se trabaja directamente sobre `laravel`.
- Antes de subir: probar el módulo con los tres roles y escribiendo a mano la URL de otro rol (debe rechazarla).
- Los cambios a la base de datos van en un script SQL numerado dentro de `database/sql/` y se avisa al equipo.
