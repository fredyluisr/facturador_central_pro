<?php
require_once __DIR__.'/conexion.php';

final class reporte_ventas
{
    private PDO $db;
    public function __construct(?PDO $db=null){$this->db=$db??(new conexion())->con();}
    private function fecha(string $v): string {$d=DateTime::createFromFormat('!Y-m-d',$v);if(!$d||$d->format('Y-m-d')!==$v)throw new InvalidArgumentException('Selecciona un rango de fechas válido.');return $v;}
    public function obtener(int $loc,string $inicio,string $fin,string $documento,string $pago): array {
        $inicio=$this->fecha($inicio);$fin=$this->fecha($fin);if($inicio>$fin)throw new InvalidArgumentException('La fecha inicial no puede ser mayor que la final.');
        $params=[':loc'=>$loc,':inicio'=>$inicio,':fin'=>(new DateTimeImmutable($fin))->modify('+1 day')->format('Y-m-d')];$where='v.loc_id=:loc AND v.ven_estado=1 AND v.ven_fecha>=:inicio AND v.ven_fecha<:fin';
        if($documento==='sin')$where.=' AND v.tde_id IS NULL';elseif(in_array($documento,['1','3'],true)){$where.=' AND v.tde_id=:doc';$params[':doc']=(int)$documento;}
        if($pago!==''&&ctype_digit($pago)&&((int)$pago)>0){$where.=' AND v.ven_tpa_id=:pago';$params[':pago']=(int)$pago;}
        $q=function(string $sql)use($params){$s=$this->db->prepare($sql);$s->execute($params);return $s;};
        $cards=$q("SELECT COUNT(*) operaciones,COALESCE(SUM(v.ven_total),0) venta,COALESCE(AVG(v.ven_total),0) ticket,COUNT(DISTINCT v.ent_id) clientes FROM venta v WHERE $where")->fetch(PDO::FETCH_ASSOC);
        $detalle=$q("SELECT d.pva_id,d.vdp_nombre producto,d.vdp_sku sku,COALESCE(d.unidad_abrev_historica,u.und_abrev,'-') unidad,SUM(d.vdp_cantidad) cantidad,COUNT(DISTINCT v.ven_id) operaciones,SUM(d.vdp_subtotal) venta,CASE WHEN SUM(d.vdp_cantidad)>0 THEN SUM(d.vdp_subtotal)/SUM(d.vdp_cantidad) ELSE 0 END precio_promedio FROM venta v JOIN venta_detalle_producto d ON d.ven_id=v.ven_id LEFT JOIN unidad_medida u ON u.und_id=d.und_med WHERE $where GROUP BY d.pva_id,d.vdp_nombre,d.vdp_sku,COALESCE(d.unidad_abrev_historica,u.und_abrev,'-') ORDER BY venta DESC")->fetchAll(PDO::FETCH_ASSOC);
        foreach($detalle as &$f)foreach(['cantidad','operaciones','venta','precio_promedio'] as $k)$f[$k]=(float)$f[$k];unset($f);
        $diario=$q("SELECT DATE(v.ven_fecha) fecha,COUNT(*) operaciones,SUM(v.ven_total) venta FROM venta v WHERE $where GROUP BY DATE(v.ven_fecha) ORDER BY fecha")->fetchAll(PDO::FETCH_ASSOC);
        $medios=$q("SELECT COALESCE(tp.tpa_nombre,'Sin especificar') nombre,COUNT(*) operaciones,SUM(v.ven_total) venta FROM venta v LEFT JOIN tipo_pago tp ON tp.tpa_id=v.ven_tpa_id WHERE $where GROUP BY v.ven_tpa_id,tp.tpa_nombre ORDER BY venta DESC")->fetchAll(PDO::FETCH_ASSOC);
        $docs=$q("SELECT CASE WHEN v.tde_id=1 THEN 'Boleta' WHEN v.tde_id=3 THEN 'Factura' ELSE 'Sin comprobante' END nombre,COUNT(*) operaciones,SUM(v.ven_total) venta FROM venta v WHERE $where GROUP BY v.tde_id ORDER BY venta DESC")->fetchAll(PDO::FETCH_ASSOC);
        $cantidad=array_sum(array_column($detalle,'cantidad'));$cards['cantidad']=$cantidad;$cards['articulos']=count($detalle);$cards['top']=$detalle[0]??null;
        return ['estado'=>'ok','kpis'=>$cards,'top'=>array_slice($detalle,0,10),'diario'=>$diario,'medios'=>$medios,'documentos'=>$docs,'detalle'=>$detalle,'pagos'=>$this->db->query('SELECT tpa_id,tpa_nombre FROM tipo_pago WHERE tpa_estado=1 ORDER BY tpa_nombre')->fetchAll(PDO::FETCH_ASSOC)];
    }
}
