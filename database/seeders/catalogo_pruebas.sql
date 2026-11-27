-- LEDGERA: Catalogo de pruebas
-- Requiere la migracion 005 (tipos y presentaciones).
-- Ejecutar exclusivamente en desarrollo.

START TRANSACTION;

SET @puesto = 1;

-- Registrar productos que no existan.

INSERT INTO productos (id_puesto, nombre_producto, estado)
SELECT @puesto, n.nombre, 'Activo'
FROM (
    SELECT 'Tomate' AS nombre
    UNION ALL SELECT 'Papa'
    UNION ALL SELECT 'Cebolla'
    UNION ALL SELECT 'Pepino'
    UNION ALL SELECT 'Aguacate'
) n
WHERE NOT EXISTS (
    SELECT 1
    FROM productos p
    WHERE p.id_puesto = @puesto
    AND p.nombre_producto = n.nombre
);

-- Registrar los tipos correspondientes.

INSERT INTO tipos_producto (id_producto, nombre_tipo)
SELECT p.id_producto, t.tipo
FROM productos p
INNER JOIN (
    SELECT 'Tomate' AS producto, 'Redondo' AS tipo
    UNION ALL SELECT 'Tomate', 'Cherry'
    UNION ALL SELECT 'Tomate', 'Pera'

    UNION ALL SELECT 'Papa', 'Pastusa'
    UNION ALL SELECT 'Papa', 'Granel'
    UNION ALL SELECT 'Papa', 'Criolla'

    UNION ALL SELECT 'Cebolla', 'Cabezona'
    UNION ALL SELECT 'Cebolla', 'Larga'
    UNION ALL SELECT 'Cebolla', 'Morada'

    UNION ALL SELECT 'Pepino', 'Cohombro'
    UNION ALL SELECT 'Pepino', 'Persa'
    UNION ALL SELECT 'Pepino', 'Europeo'

    UNION ALL SELECT 'Aguacate', 'Hass'
    UNION ALL SELECT 'Aguacate', 'Criollo'
    UNION ALL SELECT 'Aguacate', 'Papelillo'
) t ON p.nombre_producto = t.producto
WHERE p.id_puesto = @puesto
AND NOT EXISTS (
    SELECT 1
    FROM tipos_producto tp
    WHERE tp.id_producto = p.id_producto
    AND tp.nombre_tipo = t.tipo
);

COMMIT;