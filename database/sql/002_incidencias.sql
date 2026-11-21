-- LEDGERA · Tabla de incidencias (problemas que reportan los usuarios al soporte)
-- Ejecutar UNA vez en MySQL Workbench sobre la base `ledgera`.
-- Cubre RSM-002 (registro del problema) y RSM-003 (clasificación por impacto).

USE ledgera;

CREATE TABLE IF NOT EXISTS incidencias (
  id_incidencia       INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario          INT NOT NULL,                                   -- quién reporta
  id_puesto           INT NULL,                                       -- puesto de quien reporta
  modulo              VARCHAR(40) NOT NULL,                           -- parte del sistema afectada
  descripcion         VARCHAR(500) NOT NULL,
  impacto             ENUM('Alto','Medio','Bajo') NOT NULL DEFAULT 'Medio',
  estado              ENUM('Abierta','En proceso','Resuelta') NOT NULL DEFAULT 'Abierta',
  fecha_reporte       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion DATETIME NULL,
  CONSTRAINT fk_inc_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario),
  CONSTRAINT fk_inc_puesto  FOREIGN KEY (id_puesto)  REFERENCES puestos (id_puesto),
  INDEX idx_inc_estado (estado, impacto),
  INDEX idx_inc_fecha (fecha_reporte)
) ENGINE=InnoDB COMMENT='Problemas reportados por los usuarios (soporte)';
