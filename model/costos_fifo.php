<?php
final class costos_fifo
{
    public function __construct(private PDO $db){}
    private function q(string $sql,array $params=[]): PDOStatement {$s=$this->db->prepare($sql);$s->execute($params);return $s;}
    public function ingreso(int $alm,int $pva,string $fecha,float $cantidad,float $costo,int $detalle): void {
        $this->q("INSERT INTO stock_lote_costo(loc_id,pva_id,origen_tipo,origen_detalle_id,fecha,cantidad_original,cantidad_disponible,costo_unitario) VALUES(?,?,'INGRESO',?,?,?,?,?)",[$alm,$pva,$detalle,$fecha,$cantidad,$cantidad,$costo]);
    }
    public function ajustePositivo(int $alm,int $pva,string $fecha,float $cantidad,float $costo,int $detalle): void {
        $this->q("INSERT INTO stock_lote_costo(loc_id,pva_id,origen_tipo,origen_detalle_id,fecha,cantidad_original,cantidad_disponible,costo_unitario) VALUES(?,?,'AJUSTE',?,?,?,?,?)",[$alm,$pva,$detalle,$fecha,$cantidad,$cantidad,$costo]);
    }
    public function consumir(int $alm,int $pva,float $cantidad,string $tipo,int $detalle): array {
        $faltante=round($cantidad,3);$costo=0.0;$desconocido=false;
        $lotes=$this->q('SELECT lote_id,cantidad_disponible,costo_unitario FROM stock_lote_costo WHERE loc_id=? AND pva_id=? AND cantidad_disponible>0 ORDER BY fecha,lote_id FOR UPDATE',[$alm,$pva])->fetchAll(PDO::FETCH_ASSOC);
        foreach($lotes as $lote){if($faltante<=0.000001)break;$uso=round(min($faltante,(float)$lote['cantidad_disponible']),3);$faltante=round($faltante-$uso,3);if($lote['costo_unitario']===null)$desconocido=true;else $costo+=$uso*(float)$lote['costo_unitario'];$this->q('UPDATE stock_lote_costo SET cantidad_disponible=cantidad_disponible-? WHERE lote_id=?',[$uso,$lote['lote_id']]);$this->q($tipo==='venta'?'INSERT INTO venta_consumo_lote(vdp_id,lote_id,cantidad,costo_unitario) VALUES(?,?,?,?)':'INSERT INTO ajuste_consumo_lote(ajd_id,lote_id,cantidad,costo_unitario) VALUES(?,?,?,?)',[$detalle,$lote['lote_id'],$uso,$lote['costo_unitario']]);}
        if($tipo==='venta')$this->q('UPDATE venta_detalle_producto SET vdp_costo_total=?,vdp_costo_completo=? WHERE vdp_id=?',[$desconocido||$faltante>0.000001?null:round($costo,2),!$desconocido&&$faltante<=0.000001?1:0,$detalle]);
        return ['costo'=>round($costo,2),'faltante'=>$faltante];
    }
    public function revertirVenta(int $detalle): void {
        $consumos=$this->q('SELECT lote_id,cantidad FROM venta_consumo_lote WHERE vdp_id=? ORDER BY lote_id FOR UPDATE',[$detalle])->fetchAll(PDO::FETCH_ASSOC);
        foreach($consumos as $c)$this->q('UPDATE stock_lote_costo SET cantidad_disponible=cantidad_disponible+? WHERE lote_id=?',[$c['cantidad'],$c['lote_id']]);
        $this->q('DELETE FROM venta_consumo_lote WHERE vdp_id=?',[$detalle]);
    }
    public function anularIngreso(int $alm,int $pva,int $detalle): void {
        $lote=$this->q("SELECT lote_id,cantidad_original,cantidad_disponible FROM stock_lote_costo WHERE origen_tipo='INGRESO' AND origen_detalle_id=? AND loc_id=? AND pva_id=? FOR UPDATE",[$detalle,$alm,$pva])->fetch(PDO::FETCH_ASSOC);
        if(!$lote)return;
        if((float)$lote['cantidad_disponible']+0.000001<(float)$lote['cantidad_original'])throw new InvalidArgumentException('No se puede eliminar el ingreso: parte de este lote ya se vendió o utilizó.');
        $this->q('DELETE FROM stock_lote_costo WHERE lote_id=?',[$lote['lote_id']]);
    }
    public function valorarLegado(int $alm,int $pva,float $costo): void {
        $this->q("UPDATE stock_lote_costo SET costo_unitario=? WHERE loc_id=? AND pva_id=? AND origen_tipo='LEGADO' AND costo_unitario IS NULL AND cantidad_disponible>0",[$costo,$alm,$pva]);
    }
}
