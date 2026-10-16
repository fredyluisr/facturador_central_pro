ALTER TABLE venta
    ADD COLUMN ven_nota_serie VARCHAR(4) NULL AFTER ven_numero,
    ADD COLUMN ven_nota_numero BIGINT UNSIGNED NULL AFTER ven_nota_serie;

UPDATE venta
SET ven_nota_serie = ven_serie,
    ven_nota_numero = ven_numero
WHERE tde_id IS NULL
  AND ven_serie IS NOT NULL
  AND ven_numero IS NOT NULL
  AND ven_nota_serie IS NULL;

CREATE UNIQUE INDEX uq_venta_nota_numero
    ON venta (ven_nota_serie, ven_nota_numero);

-- La nueva denominación usa NV; las notas históricas NP conservan su serie.
UPDATE configuracion_sistema
SET csi_nota_pedido_serie = 'NV01'
WHERE csi_id = 1 AND csi_nota_pedido_serie = 'NP01';
