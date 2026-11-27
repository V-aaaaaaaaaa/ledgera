-- =====================================================================
-- LEDGERA · Base de datos v2 (consolidada y normalizada)
-- Reemplaza 000_ledgera_base.sql, 001_auditoria.sql y 002_incidencias.sql
-- Requiere MySQL 8.0.16+ (CHECK reales y columnas generadas).
-- ¡OJO! Este script BORRA la base `ledgera` y la recrea. Solo para desarrollo.
--
-- REGLAS DE DISEÑO (para sustentación)
--  R1. Ningún dato derivado se guarda a mano (saldos, estados de pago, saldo de
--      crédito): se calculan en vistas. Excepciones aceptadas y justificadas:
--      stock_actual, id_puesto en tablas transaccionales, jornadas_caja.estado,
--      stock_antes/stock_despues, valor_acordado (ver comentarios).
--  R2. Aislamiento por puesto (RF-002 c.7): toda tabla transaccional lleva
--      id_puesto y sus FK son COMPUESTAS (id, id_puesto). La BD impide mezclar puestos.
--  R3. Registros financieros inmutables: abonos, cierres y auditoría no se
--      editan ni se borran (triggers). Las correcciones son filas nuevas.
--  R4. La lógica transaccional vive en Laravel (DB::transaction + lockForUpdate).
--      La BD garantiza integridad estructural, no flujos.
-- =====================================================================

DROP DATABASE IF EXISTS ledgera;
CREATE DATABASE ledgera CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ledgera;

-- ============================ SEGURIDAD =============================
CREATE TABLE puestos (
  id_puesto        INT AUTO_INCREMENT PRIMARY KEY,
  nombre_puesto    VARCHAR(100) NOT NULL,
  ubicacion_bodega VARCHAR(100) NOT NULL DEFAULT 'Bodega 21 - Corabastos',
  umbral_descuadre DECIMAL(12,2) NOT NULL DEFAULT 1000.00,   -- RF-015 c.4
  estado           ENUM('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  fecha_creacion   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uk_puesto_nombre_bodega UNIQUE (nombre_puesto, ubicacion_bodega),
  CONSTRAINT chk_puesto_umbral CHECK (umbral_descuadre >= 0)
) ENGINE=InnoDB COMMENT='Puestos comerciales';

CREATE TABLE usuarios (
  id_usuario         INT AUTO_INCREMENT PRIMARY KEY,
  id_puesto          INT NULL,
  usuario            VARCHAR(50)  NOT NULL,
  nombre_completo    VARCHAR(150) NOT NULL,
  num_identificacion VARCHAR(50)  NOT NULL,
  password_hash      VARCHAR(255) NOT NULL,
  rol                ENUM('Administrador','Dueno','Vendedor') NOT NULL,
  estado             ENUM('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  intentos_fallidos  INT NOT NULL DEFAULT 0,
  bloqueado_hasta    DATETIME NULL,
  debe_cambiar_clave BOOLEAN NOT NULL DEFAULT TRUE,
  fecha_creacion     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  -- Solo tiene valor si es Dueño activo: el UNIQUE garantiza 1 Dueño activo por puesto
  dueno_activo_puesto INT GENERATED ALWAYS AS
      (IF(rol = 'Dueno' AND estado = 'Activo', id_puesto, NULL)) VIRTUAL,
  CONSTRAINT uk_usuario_nombre         UNIQUE (usuario),
  CONSTRAINT uk_usuario_identificacion UNIQUE (num_identificacion),
  CONSTRAINT uk_un_dueno_activo        UNIQUE (dueno_activo_puesto),
  CONSTRAINT fk_usuario_puesto FOREIGN KEY (id_puesto) REFERENCES puestos (id_puesto),
  CONSTRAINT chk_usuario_intentos CHECK (intentos_fallidos >= 0),
  CONSTRAINT chk_usuario_rol_puesto CHECK (
    (rol = 'Administrador' AND id_puesto IS NULL) OR
    (rol IN ('Dueno','Vendedor') AND id_puesto IS NOT NULL))
) ENGINE=InnoDB COMMENT='Usuarios y roles';

-- ============================ MAESTROS ==============================
CREATE TABLE proveedores (
  id_proveedor         INT AUTO_INCREMENT PRIMARY KEY,
  id_puesto            INT NOT NULL,
  nombre_razon_social  VARCHAR(150) NOT NULL,
  telefono             VARCHAR(20) NULL,
  contacto_principal   VARCHAR(100) NULL,
  estado               ENUM('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  consentimiento_datos DATETIME NULL,           -- pendiente de decisión (RNF-013)
  fecha_registro       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uk_proveedor_nombre    UNIQUE (id_puesto, nombre_razon_social),
  CONSTRAINT uk_proveedor_id_puesto UNIQUE (id_proveedor, id_puesto),
  CONSTRAINT fk_proveedor_puesto FOREIGN KEY (id_puesto) REFERENCES puestos (id_puesto)
) ENGINE=InnoDB COMMENT='Proveedores de mercancía';

CREATE TABLE clientes (
  id_cliente           INT AUTO_INCREMENT PRIMARY KEY,
  id_puesto            INT NOT NULL,
  nombre_completo      VARCHAR(150) NOT NULL,
  telefono             VARCHAR(20) NOT NULL,
  plazo_credito_dias   INT NOT NULL DEFAULT 15,
  estado               ENUM('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  consentimiento_datos DATETIME NOT NULL,        -- Ley 1581: sin consentimiento no se guarda
  fecha_registro       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uk_cliente_telefono  UNIQUE (id_puesto, telefono),
  CONSTRAINT uk_cliente_id_puesto UNIQUE (id_cliente, id_puesto),
  CONSTRAINT fk_cliente_puesto FOREIGN KEY (id_puesto) REFERENCES puestos (id_puesto),
  CONSTRAINT chk_cliente_plazo CHECK (plazo_credito_dias > 0)
) ENGINE=InnoDB COMMENT='Clientes de crédito (fiado)';

CREATE TABLE productos (
  id_producto     INT AUTO_INCREMENT PRIMARY KEY,
  id_puesto       INT NOT NULL,
  nombre_producto VARCHAR(100) NOT NULL,
  descripcion     TEXT NULL,
  estado          ENUM('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  CONSTRAINT uk_producto UNIQUE (id_puesto, nombre_producto),
  CONSTRAINT fk_producto_puesto FOREIGN KEY (id_puesto) REFERENCES puestos (id_puesto)
) ENGINE=InnoDB COMMENT='Catálogo base';

CREATE TABLE inventario_calidades (
  id_inventario       INT AUTO_INCREMENT PRIMARY KEY,
  id_producto         INT NOT NULL,
  calidad             VARCHAR(100) NOT NULL,
  -- Saldo materializado (desnormalización aceptada: RNF-010 < 500 ms y lock por fila).
  -- Solo lo modifica Laravel dentro de una transacción; puede ser negativo (RF-010).
  stock_actual        INT NOT NULL DEFAULT 0,
  stock_minimo        INT NOT NULL DEFAULT 0,
  precio_venta_diario DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  CONSTRAINT uk_producto_calidad UNIQUE (id_producto, calidad),
  CONSTRAINT fk_inv_producto FOREIGN KEY (id_producto) REFERENCES productos (id_producto),
  CONSTRAINT chk_inv_minimo CHECK (stock_minimo >= 0),
  CONSTRAINT chk_inv_precio CHECK (precio_venta_diario >= 0)
) ENGINE=InnoDB COMMENT='Inventario por producto y calidad';

-- ============================ CAJA: JORNADA (RF-020) ================
CREATE TABLE jornadas_caja (
  id_jornada          INT AUTO_INCREMENT PRIMARY KEY,
  id_puesto           INT NOT NULL,
  id_usuario_apertura INT NOT NULL,
  efectivo_inicial    DECIMAL(12,2) NOT NULL,
  efectivo_sugerido   DECIMAL(12,2) NULL,        -- precargado del último cierre (RF-020 c.1)
  -- 'estado' es redundante con la existencia de un cierre, pero habilita el UNIQUE de
  -- "una jornada abierta por puesto". Lo pasa a 'Cerrada' el trigger de cierres_caja.
  estado              ENUM('Abierta','Cerrada') NOT NULL DEFAULT 'Abierta',
  fecha_apertura      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_operativa     DATE GENERATED ALWAYS AS (DATE(fecha_apertura)) STORED,  -- RF-020 c.2
  abierta_puesto      INT GENERATED ALWAYS AS (IF(estado = 'Abierta', id_puesto, NULL)) VIRTUAL,
  CONSTRAINT uk_una_jornada_abierta UNIQUE (abierta_puesto),
  CONSTRAINT uk_jornada_id_puesto   UNIQUE (id_jornada, id_puesto),
  CONSTRAINT fk_jornada_puesto  FOREIGN KEY (id_puesto) REFERENCES puestos (id_puesto),
  CONSTRAINT fk_jornada_usuario FOREIGN KEY (id_usuario_apertura) REFERENCES usuarios (id_usuario),
  CONSTRAINT chk_jornada_inicial CHECK (efectivo_inicial >= 0),
  INDEX idx_jornada_puesto_fecha (id_puesto, fecha_operativa)
) ENGINE=InnoDB COMMENT='Jornada operativa; puede cruzar medianoche';

-- ============================ COMPRAS ===============================
CREATE TABLE entradas_mercancia (
  id_entrada        INT AUTO_INCREMENT PRIMARY KEY,
  id_puesto         INT NOT NULL,
  id_proveedor      INT NOT NULL,
  id_usuario        INT NOT NULL,
  documento_soporte VARCHAR(50) NOT NULL,        -- si no lo digitan, Laravel genera uno interno
  valor_acordado    DECIMAL(12,2) NOT NULL,      -- RF-006 c.4: se conserva junto al total calculado
  fecha_entrada     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uk_entrada_doc UNIQUE (id_proveedor, documento_soporte),
  CONSTRAINT uk_entrada_id_puesto UNIQUE (id_entrada, id_puesto),
  CONSTRAINT fk_entrada_proveedor FOREIGN KEY (id_proveedor, id_puesto)
      REFERENCES proveedores (id_proveedor, id_puesto),
  CONSTRAINT fk_entrada_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario),
  CONSTRAINT chk_entrada_valor CHECK (valor_acordado > 0),
  INDEX idx_entradas_puesto_fecha (id_puesto, fecha_entrada)
) ENGINE=InnoDB COMMENT='Factura de compra (saldo y estado de pago: ver v_entradas_saldo)';

CREATE TABLE detalle_entrada (
  id_detalle_entrada INT AUTO_INCREMENT PRIMARY KEY,
  id_entrada         INT NOT NULL,
  id_inventario      INT NOT NULL,
  cantidad_recibida  INT NOT NULL,
  costo_unitario     DECIMAL(12,2) NOT NULL,
  subtotal DECIMAL(14,2) GENERATED ALWAYS AS (cantidad_recibida * costo_unitario) STORED,
  CONSTRAINT fk_detent_entrada FOREIGN KEY (id_entrada)    REFERENCES entradas_mercancia (id_entrada),
  CONSTRAINT fk_detent_inv     FOREIGN KEY (id_inventario) REFERENCES inventario_calidades (id_inventario),
  CONSTRAINT chk_detent CHECK (cantidad_recibida > 0 AND costo_unitario > 0)
) ENGINE=InnoDB COMMENT='Líneas de la factura de compra';

-- ============================ VENTAS Y FIADO ========================
CREATE TABLE ventas (
  id_venta       INT AUTO_INCREMENT PRIMARY KEY,
  id_puesto      INT NOT NULL,
  id_jornada     INT NOT NULL,
  id_usuario     INT NOT NULL,
  id_cliente     INT NULL,
  canal          ENUM('Mostrador','Domicilio') NOT NULL DEFAULT 'Mostrador',
  total_venta    DECIMAL(12,2) NOT NULL,
  monto_efectivo DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  monto_digital  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  medio_digital  ENUM('Nequi','Daviplata','Bre-B') NULL,
  monto_credito  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  estado         ENUM('Confirmada','Anulada') NOT NULL DEFAULT 'Confirmada',
  fecha_venta    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uk_venta_id_puesto UNIQUE (id_venta, id_puesto),
  CONSTRAINT fk_venta_jornada FOREIGN KEY (id_jornada, id_puesto)
      REFERENCES jornadas_caja (id_jornada, id_puesto),
  CONSTRAINT fk_venta_cliente FOREIGN KEY (id_cliente, id_puesto)
      REFERENCES clientes (id_cliente, id_puesto),
  CONSTRAINT fk_venta_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario),
  CONSTRAINT chk_venta_montos CHECK (
      total_venta > 0 AND monto_efectivo >= 0 AND monto_digital >= 0 AND monto_credito >= 0
      AND monto_efectivo + monto_digital + monto_credito = total_venta),
  CONSTRAINT chk_venta_digital CHECK (
      (monto_digital > 0 AND medio_digital IS NOT NULL) OR (monto_digital = 0 AND medio_digital IS NULL)),
  CONSTRAINT chk_venta_credito CHECK (monto_credito = 0 OR id_cliente IS NOT NULL),
  INDEX idx_ventas_puesto_fecha (id_puesto, fecha_venta)
) ENGINE=InnoDB COMMENT='Ventas de contado, crédito y mixtas';

CREATE TABLE detalle_venta (
  id_detalle_venta INT AUTO_INCREMENT PRIMARY KEY,
  id_venta         INT NOT NULL,
  id_inventario    INT NOT NULL,
  cantidad_vendida INT NOT NULL,
  precio_unitario  DECIMAL(12,2) NOT NULL,
  subtotal DECIMAL(14,2) GENERATED ALWAYS AS (cantidad_vendida * precio_unitario) STORED,
  CONSTRAINT fk_detven_venta FOREIGN KEY (id_venta)      REFERENCES ventas (id_venta),
  CONSTRAINT fk_detven_inv   FOREIGN KEY (id_inventario) REFERENCES inventario_calidades (id_inventario),
  CONSTRAINT chk_detven CHECK (cantidad_vendida > 0 AND precio_unitario > 0)
) ENGINE=InnoDB COMMENT='Líneas de la venta';

-- Cliente y monto salen de la venta (no se repiten aquí). Se guarda solo lo que
-- NO es derivable: el plazo vigente al momento de la venta (RF-005 c.3, RF-013 c.5).
CREATE TABLE creditos_cliente (
  id_credito        INT AUTO_INCREMENT PRIMARY KEY,
  id_venta          INT NOT NULL,
  id_puesto         INT NOT NULL,
  plazo_dias        INT NOT NULL,
  fecha_vencimiento DATE NOT NULL,
  fecha_registro    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uk_credito_venta     UNIQUE (id_venta),
  CONSTRAINT uk_credito_id_puesto UNIQUE (id_credito, id_puesto),
  CONSTRAINT fk_credito_venta FOREIGN KEY (id_venta, id_puesto)
      REFERENCES ventas (id_venta, id_puesto),
  CONSTRAINT chk_credito CHECK (plazo_dias > 0),
  INDEX idx_credito_vencimiento (fecha_vencimiento)
) ENGINE=InnoDB COMMENT='Plazo y vencimiento por venta fiada (saldo/estado: ver v_creditos_saldo)';

CREATE TABLE abonos_cartera (
  id_abono       INT AUTO_INCREMENT PRIMARY KEY,
  id_puesto      INT NOT NULL,
  id_jornada     INT NOT NULL,
  id_credito     INT NOT NULL,
  id_usuario     INT NOT NULL,
  medio_pago     ENUM('Efectivo','Nequi','Daviplata','Bre-B') NOT NULL DEFAULT 'Efectivo',
  monto_recibido DECIMAL(12,2) NOT NULL,
  monto_aplicado DECIMAL(12,2) NOT NULL,         -- recibido - aplicado = excedente (RF-012 c.3)
  fecha_abono    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uk_abono_id_puesto UNIQUE (id_abono, id_puesto),
  CONSTRAINT fk_abono_jornada FOREIGN KEY (id_jornada, id_puesto)
      REFERENCES jornadas_caja (id_jornada, id_puesto),
  CONSTRAINT fk_abono_credito FOREIGN KEY (id_credito, id_puesto)
      REFERENCES creditos_cliente (id_credito, id_puesto),
  CONSTRAINT fk_abono_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario),
  CONSTRAINT chk_abono CHECK (monto_recibido > 0 AND monto_aplicado >= 0 AND monto_aplicado <= monto_recibido)
) ENGINE=InnoDB COMMENT='Abonos inmutables';

CREATE TABLE abonos_correcciones (               -- RF-025
  id_correccion     INT AUTO_INCREMENT PRIMARY KEY,
  id_abono_original INT NOT NULL,
  id_abono_nuevo    INT NULL,
  id_puesto         INT NOT NULL,
  id_jornada        INT NOT NULL,                -- jornada donde ocurre la corrección
  id_usuario        INT NOT NULL,
  motivo            VARCHAR(255) NOT NULL,
  fecha_correccion  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uk_correccion_original UNIQUE (id_abono_original),
  CONSTRAINT fk_corr_original FOREIGN KEY (id_abono_original, id_puesto)
      REFERENCES abonos_cartera (id_abono, id_puesto),
  CONSTRAINT fk_corr_nuevo FOREIGN KEY (id_abono_nuevo, id_puesto)
      REFERENCES abonos_cartera (id_abono, id_puesto),
  CONSTRAINT fk_corr_jornada FOREIGN KEY (id_jornada, id_puesto)
      REFERENCES jornadas_caja (id_jornada, id_puesto),
  CONSTRAINT fk_corr_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario),
  CONSTRAINT chk_corr CHECK (CHAR_LENGTH(TRIM(motivo)) > 0
      AND (id_abono_nuevo IS NULL OR id_abono_nuevo <> id_abono_original))
) ENGINE=InnoDB COMMENT='Reversión de abonos sin borrar el original';

-- ============================ EGRESOS (RF-014) ======================
CREATE TABLE egresos_caja (
  id_egreso    INT AUTO_INCREMENT PRIMARY KEY,
  id_puesto    INT NOT NULL,
  id_jornada   INT NOT NULL,
  id_usuario   INT NOT NULL,
  id_proveedor INT NULL,
  categoria    ENUM('Pago_proveedor','Gasto_urgencia','Otros') NOT NULL,
  medio_pago   ENUM('Efectivo','Nequi','Daviplata','Bre-B') NOT NULL DEFAULT 'Efectivo',
  monto        DECIMAL(12,2) NOT NULL,
  nota         VARCHAR(255) NULL,
  soporte      VARCHAR(255) NULL,
  fecha_egreso DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_egreso_jornada FOREIGN KEY (id_jornada, id_puesto)
      REFERENCES jornadas_caja (id_jornada, id_puesto),
  CONSTRAINT fk_egreso_proveedor FOREIGN KEY (id_proveedor, id_puesto)
      REFERENCES proveedores (id_proveedor, id_puesto),
  CONSTRAINT fk_egreso_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario),
  CONSTRAINT chk_egreso_monto CHECK (monto > 0),
  CONSTRAINT chk_egreso_categoria CHECK (
      (categoria = 'Pago_proveedor' AND id_proveedor IS NOT NULL)
      OR (categoria <> 'Pago_proveedor' AND id_proveedor IS NULL
          AND nota IS NOT NULL AND CHAR_LENGTH(TRIM(nota)) > 0)),
  INDEX idx_egresos_jornada (id_jornada)
) ENGINE=InnoDB COMMENT='Salidas de dinero';

-- Un egreso cubre varias facturas (RF-014 c.3-4).
-- Reglas en Laravel: sum(monto_aplicado) = egreso.monto; la entrada es del mismo proveedor;
-- monto_aplicado <= saldo vigente (bloquear la fila de entradas_mercancia con lockForUpdate).
CREATE TABLE pagos_proveedor_detalle (
  id_pago_detalle INT AUTO_INCREMENT PRIMARY KEY,
  id_egreso       INT NOT NULL,
  id_entrada      INT NOT NULL,
  monto_aplicado  DECIMAL(12,2) NOT NULL,
  CONSTRAINT uk_pago_egreso_entrada UNIQUE (id_egreso, id_entrada),
  CONSTRAINT fk_pagodet_egreso  FOREIGN KEY (id_egreso)  REFERENCES egresos_caja (id_egreso),
  CONSTRAINT fk_pagodet_entrada FOREIGN KEY (id_entrada) REFERENCES entradas_mercancia (id_entrada),
  CONSTRAINT chk_pagodet CHECK (monto_aplicado > 0)
) ENGINE=InnoDB COMMENT='Aplicación de un egreso a facturas de compra';

-- ============================ INVENTARIO: AJUSTES (RF-008) ==========
CREATE TABLE ajustes_inventario (
  id_ajuste     INT AUTO_INCREMENT PRIMARY KEY,
  id_inventario INT NOT NULL,
  id_usuario    INT NOT NULL,
  tipo_ajuste   ENUM('Merma','Devolucion','Correccion_conteo') NOT NULL,
  cantidad      INT NOT NULL,
  stock_antes   INT NOT NULL,                    -- hecho histórico (kardex), no derivable después
  stock_despues INT NOT NULL,
  motivo        VARCHAR(255) NOT NULL,
  fecha_ajuste  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ajuste_inv     FOREIGN KEY (id_inventario) REFERENCES inventario_calidades (id_inventario),
  CONSTRAINT fk_ajuste_usuario FOREIGN KEY (id_usuario)    REFERENCES usuarios (id_usuario),
  CONSTRAINT chk_ajuste CHECK (cantidad >= 0 AND (tipo_ajuste = 'Correccion_conteo' OR cantidad > 0)
      AND CHAR_LENGTH(TRIM(motivo)) > 0)
) ENGINE=InnoDB COMMENT='Mermas, devoluciones y correcciones de conteo';

-- ============================ CIERRE DE CAJA (RF-015) ===============
CREATE TABLE cierres_caja (
  id_cierre         INT AUTO_INCREMENT PRIMARY KEY,
  id_jornada        INT NOT NULL,
  id_puesto         INT NOT NULL,
  id_usuario        INT NOT NULL,
  efectivo_esperado DECIMAL(12,2) NOT NULL,      -- foto de lo que vio el usuario al cerrar
  efectivo_contado  DECIMAL(12,2) NOT NULL,
  umbral_aplicado   DECIMAL(12,2) NOT NULL,
  explicacion       VARCHAR(500) NULL,
  fecha_registro    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diferencia    DECIMAL(12,2) GENERATED ALWAYS AS (efectivo_contado - efectivo_esperado) STORED,
  con_descuadre TINYINT(1) GENERATED ALWAYS AS
      (ABS(efectivo_contado - efectivo_esperado) > umbral_aplicado) STORED,
  CONSTRAINT uk_cierre_jornada UNIQUE (id_jornada),
  CONSTRAINT fk_cierre_jornada FOREIGN KEY (id_jornada, id_puesto)
      REFERENCES jornadas_caja (id_jornada, id_puesto),
  CONSTRAINT fk_cierre_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario),
  CONSTRAINT chk_cierre_explicacion CHECK (
      ABS(efectivo_contado - efectivo_esperado) <= umbral_aplicado
      OR (explicacion IS NOT NULL AND CHAR_LENGTH(TRIM(explicacion)) > 0))
) ENGINE=InnoDB COMMENT='Cierre inmutable, 1:1 con jornada';

-- ============================ AUDITORÍA E INCIDENCIAS ===============
-- Sin dirección IP: es dato personal (Ley 1581) y el diseño original (001) la excluía.
CREATE TABLE auditoria (
  id_auditoria   BIGINT AUTO_INCREMENT PRIMARY KEY,
  id_usuario     INT NULL,                       -- NULL: login con usuario inexistente / sistema
  id_puesto      INT NULL,
  accion         VARCHAR(50) NOT NULL,
  entidad        VARCHAR(50) NULL,
  id_entidad     BIGINT NULL,
  valor_anterior JSON NULL,
  valor_nuevo    JSON NULL,
  motivo         VARCHAR(255) NULL,
  fecha          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_aud_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario),
  CONSTRAINT fk_aud_puesto  FOREIGN KEY (id_puesto)  REFERENCES puestos (id_puesto),
  INDEX idx_aud_entidad (entidad, id_entidad),
  INDEX idx_aud_accion (accion),
  INDEX idx_aud_fecha (fecha)
) ENGINE=InnoDB COMMENT='Solo INSERT (RSM-007)';

CREATE TABLE incidencias (                       -- RSM-002 / RSM-003
  id_incidencia       INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario          INT NOT NULL,
  id_puesto           INT NULL,
  modulo              VARCHAR(40) NOT NULL,
  descripcion         VARCHAR(500) NOT NULL,
  impacto             ENUM('Alto','Medio','Bajo') NOT NULL DEFAULT 'Medio',
  estado              ENUM('Abierta','En proceso','Resuelta') NOT NULL DEFAULT 'Abierta',
  fecha_reporte       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion DATETIME NULL,
  CONSTRAINT fk_inc_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario),
  CONSTRAINT fk_inc_puesto  FOREIGN KEY (id_puesto)  REFERENCES puestos (id_puesto),
  INDEX idx_inc_estado (estado, impacto),
  INDEX idx_inc_fecha (fecha_reporte)
) ENGINE=InnoDB COMMENT='Problemas reportados por usuarios';

-- ============================ TRIGGERS (solo integridad) ============
-- Inmutabilidad (R3)
CREATE TRIGGER trg_aud_no_update BEFORE UPDATE ON auditoria FOR EACH ROW
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La auditoría no se modifica.';
CREATE TRIGGER trg_aud_no_delete BEFORE DELETE ON auditoria FOR EACH ROW
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La auditoría no se borra.';
CREATE TRIGGER trg_abono_no_update BEFORE UPDATE ON abonos_cartera FOR EACH ROW
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Un abono no se modifica: se corrige con una reversión.';
CREATE TRIGGER trg_abono_no_delete BEFORE DELETE ON abonos_cartera FOR EACH ROW
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Un abono no se borra: se corrige con una reversión.';
CREATE TRIGGER trg_cierre_no_update BEFORE UPDATE ON cierres_caja FOR EACH ROW
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Un cierre confirmado no se modifica.';
CREATE TRIGGER trg_cierre_no_delete BEFORE DELETE ON cierres_caja FOR EACH ROW
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Un cierre confirmado no se borra.';

-- Mantiene coherente la redundancia controlada jornadas_caja.estado
CREATE TRIGGER trg_cierre_cierra_jornada AFTER INSERT ON cierres_caja FOR EACH ROW
  UPDATE jornadas_caja SET estado = 'Cerrada' WHERE id_jornada = NEW.id_jornada;

-- ============================ VISTAS ================================
CREATE VIEW v_entradas_saldo AS
SELECT t.*, (t.valor_acordado - t.pagado) AS saldo_pendiente,
       CASE WHEN t.pagado = 0 THEN 'Pendiente'
            WHEN t.pagado >= t.valor_acordado THEN 'Pagado' ELSE 'Parcial' END AS estado_pago
FROM (SELECT e.id_entrada, e.id_puesto, e.id_proveedor, e.valor_acordado,
             COALESCE((SELECT SUM(d.subtotal) FROM detalle_entrada d
                       WHERE d.id_entrada = e.id_entrada), 0) AS total_calculado,
             COALESCE((SELECT SUM(p.monto_aplicado) FROM pagos_proveedor_detalle p
                       WHERE p.id_entrada = e.id_entrada), 0) AS pagado
      FROM entradas_mercancia e) t;

CREATE VIEW v_creditos_saldo AS
SELECT t.*,
       CASE WHEN t.estado_venta = 'Anulada' THEN 'Anulado'
            WHEN t.saldo_pendiente <= 0     THEN 'Pagado'
            ELSE 'Pendiente' END AS estado_credito
FROM (SELECT c.id_credito, c.id_puesto, c.id_venta, v.id_cliente,
             v.monto_credito AS monto_original, c.plazo_dias, c.fecha_vencimiento,
             v.monto_credito - COALESCE((SELECT SUM(a.monto_aplicado) FROM abonos_cartera a
                  WHERE a.id_credito = c.id_credito
                    AND NOT EXISTS (SELECT 1 FROM abonos_correcciones x
                                    WHERE x.id_abono_original = a.id_abono)), 0) AS saldo_pendiente,
             v.estado AS estado_venta
      FROM creditos_cliente c JOIN ventas v ON v.id_venta = c.id_venta) t;

CREATE VIEW v_cartera_vencida AS
SELECT s.id_credito, s.id_puesto, cl.nombre_completo, cl.telefono,
       s.fecha_vencimiento, DATEDIFF(CURDATE(), s.fecha_vencimiento) AS dias_mora,
       s.saldo_pendiente
FROM v_creditos_saldo s JOIN clientes cl ON cl.id_cliente = s.id_cliente
WHERE s.estado_credito = 'Pendiente' AND s.fecha_vencimiento < CURDATE();

-- Efectivo esperado por jornada (RF-015 c.1). Un abono revertido sigue contando en SU jornada;
-- la reversión resta en la jornada donde se registra la corrección (RF-025 c.6).
CREATE VIEW v_efectivo_jornada AS
SELECT t.*,
       t.efectivo_inicial + t.ventas_efectivo + t.abonos_efectivo
         - t.reversiones_efectivo - t.egresos_efectivo AS efectivo_esperado
FROM (SELECT j.id_jornada, j.id_puesto, j.efectivo_inicial,
        COALESCE((SELECT SUM(v.monto_efectivo) FROM ventas v
                  WHERE v.id_jornada = j.id_jornada AND v.estado = 'Confirmada'), 0) AS ventas_efectivo,
        COALESCE((SELECT SUM(v.monto_digital) FROM ventas v
                  WHERE v.id_jornada = j.id_jornada AND v.estado = 'Confirmada'), 0) AS ventas_digital,
        COALESCE((SELECT SUM(v.monto_credito) FROM ventas v
                  WHERE v.id_jornada = j.id_jornada AND v.estado = 'Confirmada'), 0) AS ventas_credito,
        COALESCE((SELECT SUM(a.monto_recibido) FROM abonos_cartera a
                  WHERE a.id_jornada = j.id_jornada AND a.medio_pago = 'Efectivo'), 0) AS abonos_efectivo,
        COALESCE((SELECT SUM(a.monto_recibido) FROM abonos_cartera a
                  WHERE a.id_jornada = j.id_jornada AND a.medio_pago <> 'Efectivo'), 0) AS abonos_digital,
        COALESCE((SELECT SUM(a.monto_recibido) FROM abonos_correcciones x
                  JOIN abonos_cartera a ON a.id_abono = x.id_abono_original
                  WHERE x.id_jornada = j.id_jornada AND a.medio_pago = 'Efectivo'), 0) AS reversiones_efectivo,
        COALESCE((SELECT SUM(e.monto) FROM egresos_caja e
                  WHERE e.id_jornada = j.id_jornada AND e.medio_pago = 'Efectivo'), 0) AS egresos_efectivo,
        COALESCE((SELECT SUM(e.monto) FROM egresos_caja e
                  WHERE e.id_jornada = j.id_jornada AND e.medio_pago <> 'Efectivo'), 0) AS egresos_digital
      FROM jornadas_caja j) t;

CREATE VIEW v_alertas_inventario AS
SELECT p.id_puesto, p.nombre_producto, ic.id_inventario, ic.calidad, ic.stock_actual, ic.stock_minimo
FROM inventario_calidades ic JOIN productos p ON p.id_producto = ic.id_producto
WHERE p.estado = 'Activo' AND ic.stock_actual <= ic.stock_minimo;

-- ============================ DATOS DE PRUEBA =======================
-- Todos los usuarios usan el mismo hash que ya tenía 'soporte' (misma clave que ya conoces).
SET @hash = '$2y$10$.gUZKfDl0dbq1SP/t5xxpuxC4nMIzYH5HpYL3/8uyzoEdM9TvgtmC';

INSERT INTO puestos (id_puesto, nombre_puesto) VALUES
 (1, 'Comercializadora LEDGERA - Puesto 104'), (2, 'Distribuidora El Campesino - Puesto 208');

INSERT INTO usuarios (id_usuario, id_puesto, usuario, nombre_completo, num_identificacion, password_hash, rol, debe_cambiar_clave) VALUES
 (1, NULL, 'soporte',   'Soporte Técnico', '1018293800', @hash, 'Administrador', FALSE),
 (2, 1,    'cmendoza',  'Carlos Mendoza',  '1018293801', @hash, 'Dueno',         FALSE),
 (3, 1,    'afelipe',   'Andrés Felipe',   '1018293803', @hash, 'Vendedor',      FALSE),
 (4, 2,    'mrojas',    'María Rojas',     '1018293802', @hash, 'Dueno',         FALSE);

INSERT INTO proveedores (id_proveedor, id_puesto, nombre_razon_social, telefono, contacto_principal) VALUES
 (1, 1, 'Don Pedro - Finca El Salitre', '3109876543', 'Pedro Gómez');

INSERT INTO clientes (id_cliente, id_puesto, nombre_completo, telefono, plazo_credito_dias, consentimiento_datos) VALUES
 (1, 1, 'Don Juan',                      '3201112233', 15, NOW()),
 (2, 1, 'María Rodríguez (Fruver Suba)', '3154445566', 10, NOW());

INSERT INTO productos (id_producto, id_puesto, nombre_producto, descripcion) VALUES
 (1, 1, 'Papa pastusa',  'Bultos de 50 kg'),
 (2, 1, 'Tomate chonto', 'Canastillas de 22 kg');

-- Stock ya consistente con las entradas y ventas de abajo (Laravel lo mantendrá desde aquí)
INSERT INTO inventario_calidades (id_inventario, id_producto, calidad, stock_actual, stock_minimo, precio_venta_diario) VALUES
 (1, 1, 'Extra',   58, 20, 95000),
 (2, 1, 'Primera', 33, 15, 85000),
 (3, 2, 'Extra',   33, 10, 65000);

INSERT INTO jornadas_caja (id_jornada, id_puesto, id_usuario_apertura, efectivo_inicial) VALUES (1, 1, 2, 200000);

INSERT INTO entradas_mercancia (id_entrada, id_puesto, id_proveedor, id_usuario, documento_soporte, valor_acordado)
VALUES (1, 1, 1, 2, 'REM-2026-001', 8150000);
INSERT INTO detalle_entrada (id_entrada, id_inventario, cantidad_recibida, costo_unitario) VALUES
 (1, 1, 60, 75000), (1, 2, 40, 65000), (1, 3, 35, 30000);

INSERT INTO ventas (id_venta, id_puesto, id_jornada, id_usuario, id_cliente, canal, total_venta,
                    monto_efectivo, monto_digital, medio_digital, monto_credito) VALUES
 (1, 1, 1, 2, NULL, 'Mostrador', 190000, 190000,      0, NULL,    0),
 (2, 1, 1, 3, 1,    'Mostrador', 340000,      0,      0, NULL,    340000),
 (3, 1, 1, 3, NULL, 'Domicilio', 130000,      0, 130000, 'Nequi', 0),
 (4, 1, 1, 3, 2,    'Mostrador', 255000,  55000,      0, NULL,    200000);
INSERT INTO detalle_venta (id_venta, id_inventario, cantidad_vendida, precio_unitario) VALUES
 (1, 1, 2, 95000), (2, 2, 4, 85000), (3, 3, 2, 65000), (4, 2, 3, 85000);

INSERT INTO creditos_cliente (id_credito, id_venta, id_puesto, plazo_dias, fecha_vencimiento) VALUES
 (1, 2, 1, 15, DATE_ADD(CURDATE(), INTERVAL 15 DAY)),
 (2, 4, 1, 10, DATE_ADD(CURDATE(), INTERVAL 10 DAY));

INSERT INTO abonos_cartera (id_puesto, id_jornada, id_credito, id_usuario, medio_pago, monto_recibido, monto_aplicado)
VALUES (1, 1, 1, 3, 'Efectivo', 100000, 100000);

INSERT INTO egresos_caja (id_egreso, id_puesto, id_jornada, id_usuario, id_proveedor, categoria, monto, nota) VALUES
 (1, 1, 1, 3, NULL, 'Gasto_urgencia', 30000, 'Descargue camión de papa'),
 (2, 1, 1, 2, 1,    'Pago_proveedor', 200000, NULL);
INSERT INTO pagos_proveedor_detalle (id_egreso, id_entrada, monto_aplicado) VALUES (2, 1, 200000);

-- ============================ VERIFICACIÓN ==========================
SELECT * FROM v_efectivo_jornada WHERE id_jornada = 1;   -- efectivo_esperado = 315000
SELECT * FROM v_creditos_saldo;                          -- saldos 240000 y 200000
SELECT * FROM v_entradas_saldo;                          -- saldo 7950000, Parcial
SELECT * FROM v_alertas_inventario;                      -- vacío
SELECT * FROM v_cartera_vencida;                         -- vacío (aún no vence nada);
