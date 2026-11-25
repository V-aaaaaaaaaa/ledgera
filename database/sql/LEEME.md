# Scripts de la base de datos

Ejecutar en MySQL Workbench (MySQL 8.0.16 o superior) sobre un MySQL local.

| Orden | Archivo | Qué hace |
|---|---|---|
| 0 | `000_ledgera_v2.sql` | Borra y crea la base `ledgera`: tablas, triggers de integridad, vistas y datos de prueba. Incluye auditoría e incidencias. |

Usuarios de prueba: soporte (Administrador), cmendoza (Dueño), afelipe (Vendedor), mrojas (Dueño del puesto 2).

Reglas:
- v2 reemplaza a 000, 001 y 002 (reinicio por estar en desarrollo, sin producción).
- Todo cambio nuevo en la base = un archivo nuevo con el siguiente número (`003_...`). Nunca se edita un script que ya corrió otra persona.
- La base de datos no viaja en git: cada integrante tiene su MySQL, así que el archivo de cada cambio debe estar aquí.