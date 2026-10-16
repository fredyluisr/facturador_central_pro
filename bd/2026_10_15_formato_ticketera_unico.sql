UPDATE `configuracion_sistema`
SET `csi_formato_pdf` = 'ticket'
WHERE `csi_id` = 1;

ALTER TABLE `configuracion_sistema`
  MODIFY COLUMN `csi_formato_pdf` ENUM('ticket') NOT NULL DEFAULT 'ticket';
