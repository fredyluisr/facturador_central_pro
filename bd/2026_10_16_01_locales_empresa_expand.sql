-- Locales por empresa - Fase 1 (EXPAND)
-- Ejecutar primero en respaldo/staging y luego en producción.
-- Compatible con el código actual: las nuevas FK quedan NULL durante el despliegue coordinado.

CREATE TABLE `local_empresa` (
  `loc_id` INT NOT NULL AUTO_INCREMENT,
  `em_id` INT NOT NULL,
  `loc_codigo` VARCHAR(20) NOT NULL,
  `loc_nombre` VARCHAR(120) NOT NULL,
  `loc_direccion` VARCHAR(300) DEFAULT NULL,
  `loc_ubigeo` VARCHAR(6) DEFAULT NULL,
  `loc_departamento` VARCHAR(100) DEFAULT NULL,
  `loc_provincia` VARCHAR(100) DEFAULT NULL,
  `loc_distrito` VARCHAR(100) DEFAULT NULL,
  `loc_principal` TINYINT(1) NOT NULL DEFAULT 0,
  `loc_activo` TINYINT(1) NOT NULL DEFAULT 1,
  `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`loc_id`),
  UNIQUE KEY `uq_local_empresa_codigo` (`em_id`,`loc_codigo`),
  KEY `idx_local_empresa_estado` (`em_id`,`loc_activo`),
  CONSTRAINT `fk_local_empresa_empresa` FOREIGN KEY (`em_id`) REFERENCES `empresa` (`em_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `local_empresa`
 (`em_id`,`loc_codigo`,`loc_nombre`,`loc_direccion`,`loc_ubigeo`,`loc_departamento`,`loc_provincia`,`loc_distrito`,`loc_principal`,`loc_activo`)
SELECT e.em_id,'PRINCIPAL','Local principal',e.em_direccion,e.em_ubigeo,e.em_departamento,e.em_provincia,e.em_distrito,1,1
FROM empresa e
WHERE NOT EXISTS (SELECT 1 FROM local_empresa l WHERE l.em_id=e.em_id);

ALTER TABLE `serie_documento`
 ADD COLUMN `loc_id` INT NULL AFTER `em_id`,
 ADD KEY `idx_serie_local_tipo_activo` (`loc_id`,`tipo_doc`,`activo`),
 ADD CONSTRAINT `fk_serie_local` FOREIGN KEY (`loc_id`) REFERENCES `local_empresa` (`loc_id`);

UPDATE serie_documento s
JOIN local_empresa l ON l.em_id=s.em_id AND l.loc_principal=1
SET s.loc_id=l.loc_id
WHERE s.loc_id IS NULL;

ALTER TABLE `comprobante`
 ADD COLUMN `loc_id` INT NULL AFTER `em_id`,
 ADD KEY `idx_comprobante_local_fecha` (`loc_id`,`fecha_emision`),
 ADD CONSTRAINT `fk_comprobante_local` FOREIGN KEY (`loc_id`) REFERENCES `local_empresa` (`loc_id`);

UPDATE comprobante c
JOIN local_empresa l ON l.em_id=c.em_id AND l.loc_principal=1
SET c.loc_id=l.loc_id
WHERE c.loc_id IS NULL;

SELECT COUNT(*) AS series_sin_local FROM serie_documento WHERE loc_id IS NULL;
SELECT COUNT(*) AS comprobantes_sin_local FROM comprobante WHERE loc_id IS NULL;
