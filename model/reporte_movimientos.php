<?php
require_once __DIR__.'/conexion.php';

final class reporte_movimientos
{
    public function __construct(private ?PDO $db=null){$this->db??=(new conexion())->con();}
    private function q(string $sql,array $p=[]): array {$s=$this->db->prepare($sql);$s->execute($p);return $s->fetchAll(PDO::FETCH_ASSOC);}
    private function fecha(string $v): string {$d=DateTimeImmutable::createFromFormat('!Y-m-d',$v);if(!$d||$d->format('Y-m-d')!==$v)throw new InvalidArgumentException('Selecciona fechas válidas.');return $v;}
    public function obtener(int $loc,string $inicio,string $fin,string $tipo='',string $estado=''): array
    {
        if($loc<=0)throw new InvalidArgumentException('Selecciona nuevamente el local.');
        $inicio=$this->fecha($inicio);$fin=$this->fecha($fin);
        if($inicio>$fin||$inicio<(new DateTimeImmutable($fin))->modify('-366 days')->format('Y-m-d'))throw new InvalidArgumentException('Selecciona un rango de hasta un año.');
        if($tipo!==''&&!in_array($tipo,['INGRESO','EGRESO'],true))throw new InvalidArgumentException('Tipo de movimiento inválido.');
        if($estado!==''&&!in_array($estado,['pagado','parcial','pendiente'],true))throw new InvalidArgumentException('Estado de pago inválido.');
        $finEx=(new DateTimeImmutable($fin))->modify('+1 day')->format('Y-m-d');
        $where="m.loc_id=? AND m.mov_estado=1 AND m.mov_fecha>=? AND m.mov_fecha<?";$args=[$loc,$inicio,$finEx];
        if($tipo!==''){$where.=' AND m.mov_tipo=?';$args[]=$tipo;}
        $sql="SELECT m.mov_id com_id,m.mov_tipo,m.mov_fecha com_fecha,m.mov_tipo_doc com_tipo_doc,m.mov_serie com_serie,m.mov_correlativo com_correlativo,m.mov_total com_total,m.mov_observaciones com_observaciones,e.ent_nombres entidad,COALESCE(p.pagado,0) pagado,GREATEST(m.mov_total-COALESCE(p.pagado,0),0) saldo,COALESCE(d.conceptos,'') conceptos FROM movimiento m LEFT JOIN entidad e ON e.ent_id=m.ent_id LEFT JOIN (SELECT mov_id,SUM(mpa_monto) pagado FROM movimiento_pago WHERE mpa_estado=1 GROUP BY mov_id) p ON p.mov_id=m.mov_id LEFT JOIN (SELECT md.mov_id,GROUP_CONCAT(DISTINCT md.mde_nombre ORDER BY md.mde_nombre SEPARATOR ', ') conceptos FROM movimiento_detalle md GROUP BY md.mov_id) d ON d.mov_id=m.mov_id WHERE $where ORDER BY m.mov_fecha DESC,m.mov_id DESC";
        $movimientos=$this->q($sql,$args);
        if($estado!=='')$movimientos=array_values(array_filter($movimientos,static function($m)use($estado){$t=(int)round((float)$m['com_total']*100);$p=(int)round((float)$m['pagado']*100);return $estado==='pagado'?$p>=$t:($estado==='pendiente'?$p===0:$p>0&&$p<$t);}));
        $k=['operaciones'=>count($movimientos),'ingresos'=>0.0,'egresos'=>0.0,'ingresos_n'=>0,'egresos_n'=>0,'pagado'=>0.0,'saldo'=>0.0,'pagados'=>0,'parciales'=>0,'pendientes'=>0];$ids=[];$diario=[];$distribucion=['INGRESO'=>0.0,'EGRESO'=>0.0];
        foreach($movimientos as &$m){$t=(float)$m['com_total'];$p=(float)$m['pagado'];$s=max(0,$t-$p);$m['estado_pago']=$p+0.005>=$t?'pagado':($p>0?'parcial':'pendiente');$k[$m['estado_pago']==='pagado'?'pagados':($m['estado_pago']==='parcial'?'parciales':'pendientes')]++;$clave=$m['mov_tipo']==='INGRESO'?'ingresos':'egresos';$k[$clave]+=$t;$k[$clave.'_n']++;$k['pagado']+=$p;$k['saldo']+=$s;$distribucion[$m['mov_tipo']]+=$t;$ids[]=(int)$m['com_id'];$dia=$m['com_fecha'];if(!isset($diario[$dia]))$diario[$dia]=['fecha'=>$dia,'ingresos'=>0,'egresos'=>0];$diario[$dia][$clave]+=$t;}unset($m);ksort($diario);
        $tipos=[];$conceptos=[];
        if($ids){$holders=implode(',',array_fill(0,count($ids),'?'));$conceptos=$this->q("SELECT m.mov_tipo tipo,md.mde_nombre concepto,SUM(md.mde_subtotal) total,COUNT(DISTINCT md.mov_id) movimientos FROM movimiento_detalle md JOIN movimiento m ON m.mov_id=md.mov_id WHERE md.mov_id IN ($holders) GROUP BY m.mov_tipo,md.mde_nombre ORDER BY total DESC",$ids);}
        $tipos=[['tipo'=>'Ingresos','total'=>$distribucion['INGRESO']],['tipo'=>'Egresos','total'=>$distribucion['EGRESO']]];
        return ['estado'=>'ok','kpis'=>$k,'diario'=>array_values($diario),'tipos'=>$tipos,'conceptos'=>$conceptos,'movimientos'=>$movimientos];
    }
}
