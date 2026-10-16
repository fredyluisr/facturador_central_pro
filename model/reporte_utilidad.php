<?php
require_once __DIR__.'/conexion.php';
final class reporte_utilidad
{
    public function __construct(private ?PDO $db=null){$this->db??=(new conexion())->con();}
    private function fecha(string $v): string {$d=DateTime::createFromFormat('!Y-m-d',$v);if(!$d||$d->format('Y-m-d')!==$v)throw new InvalidArgumentException('Selecciona un rango de fechas válido.');return $v;}
    public function obtener(int $loc,string $inicio,string $fin): array {
        $inicio=$this->fecha($inicio);$fin=$this->fecha($fin);if($inicio>$fin)throw new InvalidArgumentException('La fecha inicial no puede ser posterior a la fecha final.');
        $sql="SELECT v.ven_id,v.ven_fecha,d.vdp_id,d.vdp_nombre producto,d.vdp_sku sku,d.unidad_abrev_historica unidad,d.vdp_cantidad cantidad,d.vdp_subtotal venta,d.vdp_costo_total costo,d.vdp_costo_completo completo FROM venta v JOIN venta_detalle_producto d ON d.ven_id=v.ven_id WHERE v.loc_id=? AND v.ven_estado=1 AND v.ven_fecha>=? AND v.ven_fecha<? ORDER BY v.ven_fecha DESC,d.vdp_id DESC";
        $s=$this->db->prepare($sql);$s->execute([$loc,$inicio,(new DateTimeImmutable($fin))->modify('+1 day')->format('Y-m-d')]);$detalle=$s->fetchAll(PDO::FETCH_ASSOC);
        $k=['venta'=>0.0,'costo'=>0.0,'utilidad'=>0.0,'lineas_con_costo'=>0,'lineas_pendientes'=>0,'venta_pendiente'=>0.0,'pares'=>0.0];$dias=[];$productos=[];
        foreach($detalle as &$f){$f['cantidad']=(float)$f['cantidad'];$f['venta']=(float)$f['venta'];$f['costo']=$f['costo']===null?null:(float)$f['costo'];$f['completo']=(int)$f['completo'];$f['utilidad']=null;$f['precio_compra']=null;$f['precio_venta']=$f['cantidad']>0?round($f['venta']/$f['cantidad'],2):0;$k['pares']+=$f['cantidad'];if(!$f['completo']||$f['costo']===null){$k['lineas_pendientes']++;$k['venta_pendiente']+=$f['venta'];continue;}$f['utilidad']=round($f['venta']-$f['costo'],2);$f['precio_compra']=$f['cantidad']>0?round($f['costo']/$f['cantidad'],2):0;$k['venta']+=$f['venta'];$k['costo']+=$f['costo'];$k['utilidad']+=$f['utilidad'];$k['lineas_con_costo']++;$dia=substr($f['ven_fecha'],0,10);if(!isset($dias[$dia]))$dias[$dia]=0;$dias[$dia]+=$f['utilidad'];$p=$f['producto'];if(!isset($productos[$p]))$productos[$p]=0;$productos[$p]+=$f['utilidad'];}unset($f);
        ksort($dias);arsort($productos);$serie=[];foreach($dias as $n=>$v)$serie[]=['fecha'=>$n,'utilidad'=>round($v,2)];$top=[];foreach(array_slice($productos,0,10,true) as $n=>$v)$top[]=['nombre'=>$n,'utilidad'=>round($v,2)];$k['margen']=$k['venta']>0?round($k['utilidad']/$k['venta']*100,2):0;
        return ['estado'=>'ok','kpis'=>$k,'diario'=>$serie,'top'=>$top,'detalle'=>$detalle];
    }
}
