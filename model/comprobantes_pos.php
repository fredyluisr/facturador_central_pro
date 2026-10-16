<?php
final class comprobantes_pos
{
    public const SQL = "(SELECT 1 AS tde_id, '03' AS tde_codigo, 'BOLETA' AS tde_nombre UNION ALL SELECT 2, '07', 'NOTA DE CREDITO' UNION ALL SELECT 3, '01', 'FACTURA')";
    public static function listar(): array {
        return [['tde_id'=>1,'tde_codigo'=>'03','tde_nombre'=>'BOLETA','tde_serie'=>'','tde_correlativo'=>0],['tde_id'=>3,'tde_codigo'=>'01','tde_nombre'=>'FACTURA','tde_serie'=>'','tde_correlativo'=>0]];
    }
    public static function codigo(int $id): ?string { return [1=>'03',3=>'01'][$id]??null; }
}
