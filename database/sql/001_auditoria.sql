-- LEDGERA · Tabla de auditoría (registro de acciones sobre cuentas, puestos y accesos)
-- Ejecutar UNA vez en MySQL Workbench sobre la base `ledgera`.
-- No guarda direcciones IP ni datos comerciales.

USE ledgera;

CREATE TABLE IF NOT EXISTS auditoria (
  id_auditoria BIGINT AUTO_INCREMENT PRIMARY KEY,
  id_usuario   INT NULL,                              -- quién hizo la acción (NULL = el sistema)
  id_afectado  INT NULL,                              -- cuenta sobre la que se actuó
  id_puesto    INT NULL,                              -- puesto sobre el que se actuó
  accion       VARCHAR(40) NOT NULL,                  -- ej: USUARIO_CREADO
  detalle      VARCHAR(255) NULL,
  fecha        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_aud_actor    FOREIGN KEY (id_usuario)  REFERENCES usuarios (id_usuario),
  CONSTRAINT fk_aud_afectado FOREIGN KEY (id_afectado) REFERENCES usuarios (id_usuario),
  CONSTRAINT fk_aud_puesto   FOREIGN KEY (id_puesto)   REFERENCES puestos (id_puesto),
  INDEX idx_aud_fecha (fecha),
  INDEX idx_aud_accion (accion),
  INDEX idx_aud_actor (id_usuario)
) ENGINE=InnoDB COMMENT='Registro de acciones sobre cuentas, puestos y accesos';
