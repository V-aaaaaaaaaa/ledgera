# Scripts de la base de datos

Ejecutar en MySQL Workbench, **en este orden**, sobre un MySQL local:

| Orden | Archivo | Qué hace |
|---|---|---|
| 0 | `000_ledgera_base.sql` | Crea la base `ledgera`: tablas, triggers, procedimientos, vistas y datos de prueba. |
| 1 | `001_auditoria.sql` | Tabla `auditoria` (registro de acciones). |
| 2 | `002_incidencias.sql` | Tabla `incidencias` (soporte). |

Reglas:
- Un cambio nuevo en la base = un archivo nuevo con el siguiente número (`003_...`). Nunca se edita un script que ya corrió otra persona.
- La base de datos no viaja en git: cada integrante tiene su MySQL, así que el archivo de cada cambio debe estar aquí.
