-- =====================================================================
-- LEDGERA · 003 · Vendedor encargado (vendedor de confianza)
-- El Dueño puede activar a un Vendedor como "Encargado": recibe algunos
-- poderes operativos del Dueño (pagar a proveedores, crear/editar proveedores).
-- Nunca recibe los de configuración (Mi equipo, límite de descuadre,
-- productos y calidades, desactivar proveedores).
-- Ejecutar UNA vez sobre la base `ledgera` (después de 000_ledgera_v2.sql).
-- =====================================================================
USE ledgera;

ALTER TABLE usuarios
    ADD COLUMN es_encargado    BOOLEAN  NOT NULL DEFAULT FALSE
        COMMENT 'Solo Vendedores. Lo activa/desactiva el Dueño de su puesto.' AFTER rol,
    ADD COLUMN encargado_desde DATETIME NULL
        COMMENT 'Desde cuándo es encargado (NULL si no lo es).' AFTER es_encargado,
    ADD CONSTRAINT chk_encargado_solo_vendedor
        CHECK (es_encargado = FALSE OR rol = 'Vendedor');

-- Verificación
SELECT usuario, rol, es_encargado FROM usuarios;
