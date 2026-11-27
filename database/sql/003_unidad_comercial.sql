-- LEDGERA
-- 003: Incorporación de la unidad comercial.

ALTER TABLE productos
ADD COLUMN unidad_comercial VARCHAR(30) NULL
AFTER nombre_producto;