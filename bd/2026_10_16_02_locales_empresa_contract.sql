-- Locales por empresa - Fase 2 (CONTRACT)
-- NO ejecutar hasta desplegar y verificar API, panel central e iFact con loc_id.

SELECT COUNT(*) AS empresas_sin_local_principal
FROM empresa e
WHERE NOT EXISTS (SELECT 1 FROM local_empresa l WHERE l.em_id=e.em_id AND l.loc_principal=1 AND l.loc_activo=1);
SELECT COUNT(*) AS series_sin_local FROM serie_documento WHERE loc_id IS NULL;
SELECT COUNT(*) AS comprobantes_sin_local FROM comprobante WHERE loc_id IS NULL;

-- Continuar solamente si los tres controles devuelven 0.
ALTER TABLE serie_documento MODIFY loc_id INT NOT NULL;
ALTER TABLE comprobante MODIFY loc_id INT NOT NULL;

-- La serie sigue siendo única dentro de la empresa para impedir duplicidad tributaria.
-- loc_id identifica el local propietario y el contador que consume.
