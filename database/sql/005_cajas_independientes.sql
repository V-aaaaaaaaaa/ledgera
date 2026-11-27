-- =====================================================
-- LEDGERA
-- 005: Jornadas de caja independientes por operador
-- Requiere: 004_tipos_y_presentaciones.sql
-- Ejecutar una sola vez.
-- =====================================================

ALTER TABLE jornadas_caja
    DROP INDEX uk_una_jornada_abierta,
    DROP COLUMN abierta_puesto,

    ADD COLUMN abierta_usuario INT
        GENERATED ALWAYS AS (
            CASE
                WHEN estado = 'Abierta'
                THEN id_usuario_apertura
                ELSE NULL
            END
        ) VIRTUAL,

    ADD CONSTRAINT uk_jornada_abierta_usuario
        UNIQUE (abierta_usuario),

    ALGORITHM = COPY;