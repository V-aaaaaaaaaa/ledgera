
# LEDGERA — Gestión de la base de datos

## 1. Entorno de desarrollo

- Motor: MySQL 8.0.16+ o MariaDB compatible.
- Base de datos local: definida en el archivo `.env`.
- Administración: MySQL Workbench.
- Los cambios estructurales se versionan mediante scripts SQL.
- Cada desarrollador administra su propia base de datos local.

## 2. Orden de ejecución

| Orden | Archivo | Descripción |
|---|---|---|
| 000 | `000_ledgera_v2.sql` | Crea la estructura base, restricciones, vistas, triggers y datos iniciales. |
| 003 | `003_unidad_comercial.sql` | Incorpora la unidad comercial al producto. |
| 004 | `004_tipos_y_presentaciones.sql` | Separa productos, tipos y presentaciones comerciales independientes. |

**Advertencia:** el script 000 elimina y recrea la base `ledgera`.
No debe ejecutarse sobre una instalación existente que necesite conservar sus datos.

## 3. Actualizar una base existente

1. Confirmar la base de datos utilizada por Laravel.
2. Realizar un respaldo completo de estructura y datos.
3. Seleccionar explícitamente la base de datos en Workbench.
4. Ejecutar únicamente los scripts pendientes, en orden ascendente.
5. Verificar los cambios y la integridad de los datos.
6. Actualizar el código de la aplicación correspondiente.

Cada script estructural debe ejecutarse una sola vez.

Si ocurre un error, detener la ejecución y revisar qué instrucciones
se aplicaron. No repetir automáticamente el script completo.

## 4. Modelo de inventario

- Un producto es único por nombre dentro de cada puesto.
- Cada producto puede tener múltiples tipos.
- Cada tipo puede tener múltiples unidades comerciales.
- Cada presentación tiene existencias y precio independientes.
- No se realizan conversiones automáticas entre unidades.
- Los costos de adquisición se conservan en el historial de compras.
- Los proveedores se relacionan con las entradas de mercancía.
- Los identificadores históricos de inventario se conservan.

Las presentaciones migradas con unidad "Por definir" deben
identificarse antes de realizar nuevas operaciones sobre ellas.

## 5. Datos de prueba

Archivo:

`database/seeders/catalogo_pruebas.sql`

Requiere que la migración 004 esté aplicada.

Registra productos y tipos de referencia para el puesto 1,
sin generar compras, existencias ni precios ficticios.

Puede ejecutarse nuevamente sin duplicar los registros existentes,
siempre que se mantengan sus nombres y restricciones de unicidad.

Su uso es exclusivo de desarrollo.

## 6. Reglas del equipo

- No modificar scripts históricos ya aplicados por otros integrantes.
- Crear un nuevo script numerado para cada cambio estructural.
- Separar las modificaciones estructurales de los datos de prueba.
- No publicar respaldos, credenciales ni archivos `.env` en Git.
- Conservar las restricciones de integridad referencial.
- Validar los cambios antes de integrar una versión a main.
- Registrar en el entorno local las migraciones ejecutadas.

## 7. Usuarios de prueba originales

- soporte: Administrador.
- cmendoza: Dueño.
- afelipe: Vendedor.
- mrojas: Dueño del puesto 2.

## 8. Próximas modificaciones

- Jornadas de caja independientes por operador.
- Integración de compras, inventario y egresos.
- Integración de ventas y cartera.
