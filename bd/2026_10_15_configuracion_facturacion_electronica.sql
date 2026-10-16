ALTER TABLE `configuracion_sistema`
  ADD COLUMN `csi_facturacion_electronica` TINYINT UNSIGNED NOT NULL DEFAULT 1
  AFTER `csi_vender_sin_stock`;

UPDATE `configuracion_sistema`
SET `csi_facturacion_electronica` = 1
WHERE `csi_id` = 1;
