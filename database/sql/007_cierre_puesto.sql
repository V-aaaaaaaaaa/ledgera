-- =====================================================================
-- LEDGERA · 007 · Cierre del puesto (consolidado)
-- Requiere: 006_cajas_independientes.sql
-- Cada persona cierra su propia caja (cierres_caja). Cuando TODAS las cajas
-- del puesto están cerradas, cualquiera del puesto hace el cierre del puesto:
-- suma los cierres individuales en un solo cierre del día.
-- Ejecutar una sola vez.
-- =====================================================================

CREATE TABLE cierres_puesto (
  id_cierre_puesto  INT AUTO_INCREMENT PRIMARY KEY,
  id_puesto         INT NOT NULL,
  id_usuario        INT NOT NULL,                 -- quién consolidó
  fecha_operativa   DATE NOT NULL,                -- día de la apertura más temprana incluida
  cantidad_cajas    INT NOT NULL,
  efectivo_inicial  DECIMAL(12,2) NOT NULL,
  efectivo_esperado DECIMAL(12,2) NOT NULL,
  efectivo_contado  DECIMAL(12,2) NOT NULL,
  total_digital     DECIMAL(12,2) NOT NULL DEFAULT 0.00,   -- ventas + abonos digitales
  egresos_digital   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total_fiado       DECIMAL(12,2) NOT NULL DEFAULT 0.00,   -- vendido a crédito
  cajas_con_descuadre INT NOT NULL DEFAULT 0,
  fecha_registro    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diferencia DECIMAL(12,2) GENERATED ALWAYS AS (efectivo_contado - efectivo_esperado) STORED,
  CONSTRAINT fk_cpuesto_puesto  FOREIGN KEY (id_puesto)  REFERENCES puestos (id_puesto),
  CONSTRAINT fk_cpuesto_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario),
  CONSTRAINT chk_cpuesto CHECK (cantidad_cajas > 0),
  INDEX idx_cpuesto_fecha (id_puesto, fecha_operativa)
) ENGINE=InnoDB COMMENT='Cierre consolidado del puesto (suma de cierres individuales)';

-- Qué cierres individuales entraron en cada cierre del puesto.
-- UNIQUE(id_cierre): un cierre individual solo se consolida una vez.
CREATE TABLE cierres_puesto_detalle (
  id_cierre_puesto INT NOT NULL,
  id_cierre        INT NOT NULL,
  CONSTRAINT pk_cpuesto_detalle PRIMARY KEY (id_cierre_puesto, id_cierre),
  CONSTRAINT uk_cierre_consolidado UNIQUE (id_cierre),
  CONSTRAINT fk_cpdet_puesto FOREIGN KEY (id_cierre_puesto) REFERENCES cierres_puesto (id_cierre_puesto),
  CONSTRAINT fk_cpdet_cierre FOREIGN KEY (id_cierre) REFERENCES cierres_caja (id_cierre)
) ENGINE=InnoDB COMMENT='Cierres individuales incluidos en cada cierre del puesto';

-- Igual que el cierre individual: confirmado, no se edita ni se borra
CREATE TRIGGER trg_cpuesto_no_update BEFORE UPDATE ON cierres_puesto FOR EACH ROW
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Un cierre del puesto no se modifica.';
CREATE TRIGGER trg_cpuesto_no_delete BEFORE DELETE ON cierres_puesto FOR EACH ROW
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Un cierre del puesto no se borra.';
CREATE TRIGGER trg_cpdet_no_update BEFORE UPDATE ON cierres_puesto_detalle FOR EACH ROW
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El detalle del cierre del puesto no se modifica.';
CREATE TRIGGER trg_cpdet_no_delete BEFORE DELETE ON cierres_puesto_detalle FOR EACH ROW
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El detalle del cierre del puesto no se borra.';
