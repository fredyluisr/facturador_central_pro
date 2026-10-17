-- Fase 3: índice de lectura para el historial global del panel central.
-- Ejecutar una sola vez en producción, después de desplegar el código.
-- No modifica datos ni series; únicamente acelera ORDER BY fecha_creacion DESC.

ALTER TABLE comprobante
  ADD INDEX idx_comprobante_fecha_creacion (fecha_creacion, cmp_id);
