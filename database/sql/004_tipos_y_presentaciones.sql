-- =====================================================
-- LEDGERA
-- 004: Normalizacion de tipos y presentaciones
-- Requiere: 003_unidad_comercial.sql
-- Ejecutar una sola vez sobre la BD correspondiente.
-- Realizar respaldo antes de ejecutar.
-- =====================================================

-- 1. Crear catalogo independiente de tipos.

CREATE TABLE tipos_producto (
    id_tipo INT AUTO_INCREMENT PRIMARY KEY,
    id_producto INT NOT NULL,
    nombre_tipo VARCHAR(100) NOT NULL,

    CONSTRAINT uk_tipo_producto
        UNIQUE (id_producto, nombre_tipo),

    CONSTRAINT fk_tipo_producto
        FOREIGN KEY (id_producto)
        REFERENCES productos (id_producto)

) ENGINE = InnoDB;


-- 2. Recuperar los tipos existentes.

INSERT INTO tipos_producto (id_producto, nombre_tipo)
SELECT DISTINCT id_producto, calidad
FROM inventario_calidades;


-- 3. Incorporar las nuevas relaciones.

ALTER TABLE inventario_calidades
    ADD COLUMN id_tipo INT NULL,
    ADD COLUMN unidad_comercial VARCHAR(30) NULL;


-- 4. Trasladar los datos existentes.
-- Desactivar temporalmente Safe Update Mode.

SET @safe_anterior = @@SESSION.SQL_SAFE_UPDATES;
SET SESSION SQL_SAFE_UPDATES = 0;

UPDATE inventario_calidades i
INNER JOIN productos p
    ON i.id_producto = p.id_producto
INNER JOIN tipos_producto t
    ON t.id_producto = p.id_producto
    AND t.nombre_tipo = i.calidad
SET
    i.id_tipo = t.id_tipo,
    i.unidad_comercial = COALESCE(
        NULLIF(TRIM(p.unidad_comercial), ''),
        'Por definir'
    )
WHERE i.id_inventario > 0;

SET SESSION SQL_SAFE_UPDATES = @safe_anterior;


-- 5. Establecer integridad referencial.

ALTER TABLE inventario_calidades
    MODIFY COLUMN id_tipo INT NOT NULL,
    MODIFY COLUMN unidad_comercial VARCHAR(30) NOT NULL,

    ADD CONSTRAINT uk_tipo_presentacion
        UNIQUE (id_tipo, unidad_comercial),

    ADD CONSTRAINT fk_inventario_tipo
        FOREIGN KEY (id_tipo)
        REFERENCES tipos_producto (id_tipo);


-- 6. Actualizar la vista de alertas.

CREATE OR REPLACE VIEW v_alertas_inventario AS
SELECT
    p.id_puesto,
    p.nombre_producto,
    i.id_inventario,
    t.nombre_tipo AS calidad,
    i.stock_actual,
    i.stock_minimo,
    i.unidad_comercial
FROM inventario_calidades i
INNER JOIN tipos_producto t
    ON i.id_tipo = t.id_tipo
INNER JOIN productos p
    ON t.id_producto = p.id_producto
WHERE p.estado = 'Activo'
    AND i.stock_actual <= i.stock_minimo;


-- 7. Eliminar columnas redundantes.

ALTER TABLE inventario_calidades
    DROP FOREIGN KEY fk_inv_producto,
    DROP INDEX uk_producto_calidad,
    DROP COLUMN id_producto,
    DROP COLUMN calidad;

ALTER TABLE productos
    DROP COLUMN unidad_comercial;