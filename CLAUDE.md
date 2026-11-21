# LEDGERA — guía del proyecto

Sistema web responsive de contabilidad e inventario para vendedores de Corabastos (Bogotá).
Proyecto formativo SENA (ADSO). Documento de requisitos: SRS ISO/IEC/IEEE 29148 ("LEDGERA 3.1 - Genesis").
Idioma del código, comentarios y textos de pantalla: **español**. Tutear al usuario y usar lenguaje simple.

## Prueba de Corabastos (aplica a toda decisión)
El vendedor usa el sistema a las 3 a.m., con prisa, con celular de gama baja y datos móviles.
Cada pantalla debe ser corta, con botones grandes (mínimo 44 px), letra mínima de 16 px, mensajes claros
sin tecnicismos y respuesta del servidor en menos de 500 ms. Sin conexión no se registra nada (v1.0 es solo en línea).

## Stack
- Laravel 12 (PHP 8.2, MVC), MySQL (XAMPP), Blade + CSS + JavaScript propio (sin Vite ni frameworks de front).
- Sesión por archivo, vida de 15 minutos de inactividad (RNF-004). Zona horaria America/Bogota (-05:00).
- Ejecutar: `C:\xampp\php\php.exe artisan serve` → http://127.0.0.1:8000 (Apache y MySQL de XAMPP encendidos).
- La BD `ledgera` se creó con el script SQL del equipo (tablas, triggers, procedimientos y vistas). **No usar `php artisan migrate`
  con las migraciones por defecto** (están apartadas en `database/migraciones_laravel_originales/`). Cambios nuevos de BD: script SQL
  numerado o migraciones propias de tablas nuevas, nunca recrear las existentes.
- Credenciales de BD en `.env` (no se sube a git). Usuario `root` sin clave por defecto en XAMPP.

## Estructura y convenciones MVC
- Modelos Eloquent en `app/Models` con la tabla y la llave primaria reales (`$table`, `$primaryKey = 'id_xxx'`, `$timestamps = false`).
- Controladores en `app/Http/Controllers` (un controlador por módulo; el de administración en `Admin\`). Lógica de negocio en el
  controlador o en clases de servicio; **nunca confiar en el navegador**: total, puesto y usuario salen de la sesión y del servidor.
- Middleware: `auth` → `cuenta` (cuenta y puesto activos, clave definitiva) → `rol:Dueno,Vendedor` (o `rol:Administrador`).
- Vistas Blade en `resources/views` extendiendo `layouts/app` (secciones `titulo`, `contenido`; pilas `estilos` y `scripts`).
  El layout entrega `window.LEDGERA = { base, usuario }` y carga `public/js/api.js`.
- Cada pantalla = una ruta web (vista) + rutas JSON en `routes/web.php` bajo el prefijo `/api/<modulo>`, con CSRF.
  El JS llama con `api('/api/modulo/...')` (redirige solo si la sesión cae) o `ledgeraFetch` (sin redirección).
- Respuestas JSON: `{ ok: true|false, mensaje: '...', ...datos }`. Errores con código HTTP correcto (400, 401, 403, 404, 409, 423).
- CSS y JS de cada pantalla en `public/css` y `public/js`. Ocultar botones según el rol en el JS es solo estética; el control real va en middleware.
- Operaciones que tocan varias tablas: `DB::transaction`. Stock: leer con `lockForUpdate()`. Dinero: calcular en centavos (enteros).

## Roles (3, fijos)
- **Administrador**: gestiona puestos y cuentas de los tres roles. **Nunca ve datos comerciales** (ventas, inventario, cartera, egresos, caja).
  No tiene puesto. No puede desactivar su propia cuenta ni al último Administrador activo.
- **Dueño**: un solo Dueño activo por puesto. Todo lo del Vendedor más: dashboard del puesto, costos y saldos de proveedores, pagar proveedores,
  cambiar stock mínimo y precio del día, corrección de conteo, anular, "Mi equipo" (crear Vendedores y restablecer sus claves), reportes y exportar .xlsx,
  ajustes sobre días cerrados (con clave y justificación).
- **Vendedor**: ventas, fiado y abonos, inventario (consultar, registrar facturas de compra, mermas y devoluciones), egresos solo de
  "gasto de urgencia" y "otros", cierre de caja. No ve costos ni saldos de proveedores.
- Login con **usuario y contraseña** (no hay correo ni registro público). Las cuentas las crean el Administrador (Dueños) y el Dueño (Vendedores).
  Clave temporal al crear o restablecer: se muestra una sola vez y debe cambiarse al primer ingreso (mín. 8 caracteres, letra y número).
  3 fallos seguidos bloquean 3 minutos. Contraseñas con bcrypt (`Hash::make`). Sin puesto asignado o con puesto inactivo no se puede entrar.

## Reglas de negocio ya definidas
- **Producto y calidad**: cada producto define sus propias calidades (texto libre; las 5 de tomate Extra/Primera/Semi/Parejo/Riche son solo un ejemplo).
  Productos sin calidad usan "Única". La BD compara sin distinguir mayúsculas ni tildes.
- **Venta** (3 pasos, menos de 10 s): producto+calidad → cantidad y precio (editable) → forma de pago (Efectivo, Nequi, Daviplata, Bre-B, Fiado, Mixto)
  y canal (Mostrador por defecto o Domicilio). Mixto = efectivo + fiado. Un producto por venta por ahora.
- **Regla del 20 %**: si la cantidad supera el stock y `(cantidad - stock) * 5 < stock` → advertir y dejar vender; si no, o si el stock es 0 o negativo → bloquear.
- **Fiado**: una deuda por venta (tabla `credito_cliente`) con el plazo del cliente (15 días por defecto); el vencimiento se calcula, no se escribe.
  Abonos se aplican FIFO (la deuda más antigua primero) y no pueden superar la deuda total. Mora por transacción, no por cliente.
  Cliente nuevo exige consentimiento Habeas Data (Ley 1581 de 2012), guardado con fecha en `clientes.consentimiento_datos`.
- **Entrada de mercancía** = factura de compra de un proveedor (varias líneas con costo y precio de venta del día). Factura repetida del mismo proveedor se rechaza.
  El precio del día queda con el último ingresado. Los triggers suman stock y saldo del proveedor. El Vendedor también puede registrarla.
- **Ajustes de inventario**: Merma (resta), Devolución (suma), Corrección de conteo (deja el stock en lo contado; solo Dueño). Motivo obligatorio.
- **Cierre de caja**: efectivo esperado = base inicial + ventas en efectivo + abonos en efectivo − egresos en efectivo. Diferencia sobre el umbral del puesto exige
  explicación. Confirmar escribiendo "CERRAR". Jornada cerrada queda bloqueada; los ajustes posteriores los hace el Dueño con clave, justificación y registro de auditoría.
- **Egresos**: categorías Pago_proveedor, Gasto_urgencia, Otros. Gasto de urgencia y otros exigen nota. Un pago a proveedor no supera el saldo de la factura.
- Facturación electrónica DIAN, nómina, pasarelas de pago y modo sin conexión están **fuera** del alcance de la v1.0.

## Estado de la migración a Laravel
Proyecto anterior en PHP plano (referencia, **no modificar**): `C:\xampp\htdocs\ledgera` — endpoints en `api/*.php`, pantallas `*.html`, `css/`, `js/`.
Hecho en Laravel: configuración, modelos `Usuario` y `Puesto`, login/logout, cambio de clave, middleware `cuenta` y `rol`, layout, dashboard de Administrador y
de Dueño/Vendedor (las tarjetas de módulos aún no navegan).

Por portar (en este orden), reutilizando HTML/CSS/JS del proyecto anterior y la lógica de su PHP:
1. Administrador: Usuarios (`api/usuarios.php`) y Puestos (`api/puestos.php`).
2. Ventas (`api/ventas.php`, `ventas.html`) e Inventario con Existencias, Entradas y Ajustes (`api/inventario.php`).
Por construir (nuevos): Fiado con pestañas Cartera y Clientes (gestión de clientes: editar, desactivar, anonimizar por el Dueño), Egresos, Cierre de caja con historial,
Proveedores (dentro de Inventario, solo Dueño, con pagos), Mi equipo, Exportaciones/Reportes (.xlsx, solo Dueño), anulación de ventas y entradas,
Auditoría e incidencias e indicadores del sistema (Administrador), recuperación de clave del Dueño.

## Pendientes de seguridad y calidad
- Cifrado AES-256 de cédula y teléfono (RNF-014): decidir; cifrar rompe `UNIQUE` y búsquedas por esas columnas.
- Registro de auditoría de acciones sensibles (restablecer clave, desactivar, ajustes, anulaciones).
- Verificar RNF-009 (carga inicial < 2 MB): la imagen de fondo del login es externa.
- Pruebas manuales por rol antes de cada entrega: un Vendedor no debe poder abrir rutas de Dueño ni de Administrador (probar escribiendo la URL).

## Trabajo en equipo
Una rama de git por módulo. El archivo `.env` y `vendor/` nunca se suben. Pedir confirmación antes de cambiar la estructura de la BD.

## Reparto del trabajo y rutas por área
- Rutas del Administrador: `routes/modulos/admin.php` (prefijo `/admin`, nombres `admin.*`).
- Rutas del Dueño y del Vendedor: `routes/modulos/operativo.php`. Lo exclusivo del Dueño se marca con `->middleware('rol:Dueno')` en la ruta.
- Un controlador por módulo; el del Administrador va en `app/Http/Controllers/Admin/`.
- Orden de trabajo del equipo: una persona termina Administrador (Usuarios y Puestos); otra termina la parte operativa (Ventas, Inventario y Fiado);
  cuando la parte operativa esté lista se agregan las pantallas y permisos exclusivos del Dueño.
- Cambios de base de datos: script SQL numerado en `database/sql/` (se corren en orden) y avisar al otro integrante; cada uno tiene su MySQL local.
- Antes de unir una rama a `main`: probar con los tres roles y escribiendo a mano las URL de otro rol (debe rechazarlas).

## Auditoría
- Toda acción sensible sobre cuentas, puestos y accesos llama `Auditoria::registrar('CODIGO', $idAfectado, $idPuesto, 'detalle')` (modelo `App\Models\Auditoria`).
  Los códigos válidos y su texto están en `Auditoria::ACCIONES`; al crear una acción nueva se agrega ahí. Nunca se guardan IP ni datos comerciales.
- La tabla se crea con `database/sql/001_auditoria.sql` (se corre una vez en Workbench). Si la tabla no existe, registrar() no rompe nada: solo anota en el log.
- Pantalla del Administrador: `/admin/auditoria` (`Admin\AuditoriaController`). Los ajustes sobre jornadas cerradas serán una auditoría aparte, visible para el Dueño de su puesto.

## Colores y tema (cada módulo tiene su color)
- `public/css/tema.css` define todos los colores. Cada pantalla elige su tono con `@section('tema', 'tema-xxx')` en su vista Blade
  (el layout lo pone como clase del `<body>`). Temas: admin, usuarios, puestos, auditoria, reportes, operativo, ventas, inventario, fiado, egresos, cierre.
  Un módulo nuevo agrega su línea `body.tema-modulo { --h: NNN; --s: NN%; }` y, si es tarjeta del dashboard, `.card[data-modulo="..."]`.
- Los CSS de las pantallas usan SOLO variables (`var(--acento)`, `var(--acento-oscuro)`, `var(--claro)`, `var(--suave)`, `var(--borde)`, `var(--fondo-pagina)`, `var(--texto-suave)`),
  nunca verdes fijos. Los estados (error rojo, aviso ámbar, "activo" verde) sí llevan color fijo.
- Al portar el CSS de un módulo del proyecto anterior: `python tools/aplicar-tema.py public/css/modulo.css` y agregar su `@section('tema', ...)`.
- El login y el cambio de clave conservan el verde de marca.

## Incidencias (soporte)
- Dueño y Vendedor reportan desde el menú del perfil del inicio ("Reportar un problema": módulo, gravedad y descripción) → `IncidenciaController@store`.
- El Administrador las atiende en la pestaña "Incidencias" de Auditoría y soporte (`Admin\IncidenciaController`): cambia estado (Abierta, En proceso, Resuelta) e impacto (Alto, Medio, Bajo).
- Tabla: `database/sql/002_incidencias.sql`. La lista de módulos que se pueden elegir está en `Incidencia::MODULOS`; al crear un módulo nuevo se agrega ahí.
- Versión mínima a propósito: sin adjuntos, comentarios ni notificaciones.
