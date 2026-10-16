<?php
require_once __DIR__.'/conexion.php';
require_once __DIR__.'/ingreso_stock.php';

final class ajuste_inventario
{
    private PDO $db;
    public function __construct(?PDO $db=null){$this->db=$db??(new conexion())->con();}
    private function q(string $sql,array $args=[]): PDOStatement {$s=$this->db->prepare($sql);$s->execute($args);return $s;}
    private function fecha(string $v): string {$d=DateTime::createFromFormat('!Y-m-d',$v);if(!$d||$d->format('Y-m-d')!==$v)throw new InvalidArgumentException('Selecciona una fecha válida.');return $v;}
    public function productos(int $loc): array {
        $productos=(new ingreso_stock($this->db))->productos($loc);
        $costos=[];
        foreach($this->q('SELECT pva_id,costo_unitario,fecha FROM stock_lote_costo WHERE loc_id=? AND cantidad_disponible>0 ORDER BY pva_id,fecha,lote_id',[$loc])->fetchAll(PDO::FETCH_ASSOC) as $lote){
            $id=(int)$lote['pva_id'];
            if(!isset($costos[$id]))$costos[$id]=['costo_fifo_referencia'=>$lote['costo_unitario'],'costo_fifo_fecha'=>$lote['fecha'],'costos_distintos'=>[]];
            $costos[$id]['costos_distintos'][$lote['costo_unitario']??'sin costo']=true;
        }
        foreach($productos as &$producto){
            $dato=$costos[(int)$producto['pva_id']]??null;
            $producto['costo_fifo_referencia']=$dato['costo_fifo_referencia']??null;
            $producto['costo_fifo_fecha']=$dato['costo_fifo_fecha']??null;
            $producto['costos_fifo_multiples']=$dato&&count($dato['costos_distintos'])>1;
        }unset($producto);
        return $productos;
    }
    public function listar(int $loc,string $inicio,string $fin): array {
        $inicio=$this->fecha($inicio);$fin=$this->fecha($fin);if($inicio>$fin)throw new InvalidArgumentException('La fecha inicial no puede ser mayor que la final.');
        return $this->q("SELECT a.aji_id,CONCAT('AJU-',LPAD(a.aji_id,6,'0')) numero,a.aji_fecha,a.aji_items,a.aji_diferencia,a.aji_observacion,a.fechahora FROM ajuste_inventario a WHERE a.loc_id=? AND a.aji_fecha BETWEEN ? AND ? ORDER BY a.aji_id DESC",[$loc,$inicio,$fin])->fetchAll(PDO::FETCH_ASSOC);
    }
    public function obtener(int $id,int $loc): array {
        $cab=$this->q("SELECT a.*,CONCAT('AJU-',LPAD(a.aji_id,6,'0')) numero,al.loc_nombre FROM ajuste_inventario a JOIN local al ON al.loc_id=a.loc_id WHERE a.aji_id=? AND a.loc_id=?",[$id,$loc])->fetch(PDO::FETCH_ASSOC);
        if(!$cab)throw new InvalidArgumentException('El ajuste no está disponible.');
        $det=$this->q('SELECT ajd_id,pva_id,ajd_producto,ajd_sku,ajd_unidad,ajd_stock_anterior,ajd_stock_contado,ajd_diferencia,ajd_costo_unitario FROM ajuste_inventario_detalle WHERE aji_id=? ORDER BY ajd_id',[$id])->fetchAll(PDO::FETCH_ASSOC);
        return ['cabecera'=>$cab,'detalle'=>$det];
    }
    public function guardar(int $loc,int $usu,array $data): int {
        $fecha=$this->fecha((string)($data['fecha']??''));$obs=trim((string)($data['observacion']??''));
        if($obs==='')throw new InvalidArgumentException('Indica el motivo del ajuste de inventario.');
        if(mb_strlen($obs)>1000)throw new InvalidArgumentException('La observación es demasiado extensa.');
        $clave=strtolower(trim((string)($data['clave']??'')));if(!preg_match('/^[a-f0-9]{32}$/',$clave))throw new InvalidArgumentException('La operación no es válida.');
        $items=$data['items']??[];if(!is_array($items)||!count($items)||count($items)>300)throw new InvalidArgumentException('Agrega entre 1 y 300 variantes.');
        $this->db->beginTransaction();try{
            $existente=$this->q('SELECT aji_id FROM ajuste_inventario WHERE aji_clave=?',[$clave])->fetchColumn();if($existente){$this->db->commit();return (int)$existente;}
            if(!$this->q('SELECT loc_id FROM local WHERE loc_id=? AND loc_estado=1 FOR UPDATE',[$loc])->fetchColumn())throw new InvalidArgumentException('La tienda seleccionada no está disponible.');
            $filas=[];$vistos=[];$total=0;usort($items,fn($a,$b)=>(int)($a['pva_id']??0)<=>(int)($b['pva_id']??0));
            foreach($items as $item){
                $id=(int)($item['pva_id']??0);$contado=(float)($item['stock_contado']??-1);
                if(!$id||isset($vistos[$id])||!is_finite($contado)||$contado<0||$contado>9999999)throw new InvalidArgumentException('Revisa las variantes y sus cantidades contadas.');$vistos[$id]=1;
                $v=$this->q("SELECT v.pva_id,v.pva_sku,p.pro_nombre,u.und_abrev,u.und_decimales,COALESCE(attrs.descripcion,'') atributos FROM producto_variante v JOIN producto p ON p.pro_id=v.pro_id JOIN unidad_medida u ON u.und_id=p.und_med LEFT JOIN (SELECT vv.pva_id,GROUP_CONCAT(CONCAT(a.atr_nombre,': ',av.ava_nombre) ORDER BY a.atr_id SEPARATOR ' · ') descripcion FROM producto_variante_valor vv JOIN atributo a ON a.atr_id=vv.atr_id JOIN atributo_valor av ON av.ava_id=vv.ava_id GROUP BY vv.pva_id) attrs ON attrs.pva_id=v.pva_id WHERE v.pva_id=? AND v.pva_estado=1 AND p.pro_estado=1 AND u.und_estado=1",[$id])->fetch(PDO::FETCH_ASSOC);
                if(!$v)throw new InvalidArgumentException('Una variante ya no está disponible.');
                if(abs($contado-round($contado,(int)$v['und_decimales']))>0.0000001)throw new InvalidArgumentException('La cantidad de '.$v['pro_nombre'].' no respeta los decimales de su unidad.');
                $this->q('INSERT IGNORE INTO stock_variante(pva_id,loc_id,cantidad) VALUES(?,?,0)',[$id,$loc]);
                $anterior=(float)$this->q('SELECT cantidad FROM stock_variante WHERE pva_id=? AND loc_id=? FOR UPDATE',[$id,$loc])->fetchColumn();
                $contado=round($contado,3);$diferencia=round($contado-$anterior,3);$total+=$diferencia;$costo=null;$valor=$item['costo_unitario']??null;
                if($diferencia>0&&($valor===null||$valor===''))throw new InvalidArgumentException('Indica el costo unitario de las unidades agregadas al inventario.');
                if($valor!==null&&$valor!==''){if(!is_numeric($valor)||!is_finite((float)$valor)||(float)$valor<0||(float)$valor>9999999||abs((float)$valor-round((float)$valor,2))>0.000001)throw new InvalidArgumentException('El costo unitario debe tener dos decimales como máximo.');$costo=round((float)$valor,2);}
                $nombre=$v['pro_nombre'].($v['atributos']?' · '.$v['atributos']:'');$filas[]=[$v,$anterior,$contado,$diferencia,$nombre,$costo];
            }
            $this->q('INSERT INTO ajuste_inventario(loc_id,aji_fecha,aji_observacion,aji_items,aji_diferencia,aji_clave,usuario) VALUES(?,?,?,?,?,?,?)',[$loc,$fecha,$obs,count($filas),$total,$clave,$usu]);$ajuste=(int)$this->db->lastInsertId();
            $fifo=new costos_fifo($this->db);foreach($filas as [$v,$anterior,$contado,$diferencia,$nombre,$costo]){
                $this->q('INSERT INTO ajuste_inventario_detalle(aji_id,pva_id,ajd_stock_anterior,ajd_stock_contado,ajd_diferencia,ajd_costo_unitario,ajd_producto,ajd_sku,ajd_unidad) VALUES(?,?,?,?,?,?,?,?,?)',[$ajuste,$v['pva_id'],$anterior,$contado,$diferencia,$costo,$nombre,$v['pva_sku'],$v['und_abrev']]);$detalleId=(int)$this->db->lastInsertId();
                if($costo!==null&&abs($diferencia)<0.000001)$fifo->valorarLegado($loc,(int)$v['pva_id'],$costo);
                if($diferencia>0){$disponible=round(max(0,$contado)-max(0,$anterior),3);if($disponible>0)$fifo->ajustePositivo($loc,(int)$v['pva_id'],$fecha,$disponible,$costo,$detalleId);}elseif($diferencia<0)$fifo->consumir($loc,(int)$v['pva_id'],abs($diferencia),'ajuste',$detalleId);
                $this->q('UPDATE stock_variante SET cantidad=? WHERE pva_id=? AND loc_id=?',[$contado,$v['pva_id'],$loc]);
                if(abs($diferencia)>0.000001)$this->q('INSERT INTO movimiento_stock_variante(pva_id,loc_id,cantidad,tipo,ven_id,isi_id,aji_id,usuario) VALUES(?,?,?,?,NULL,NULL,?,?)',[$v['pva_id'],$loc,abs($diferencia),$diferencia>0?5:6,$ajuste,$usu]);
            }
            $this->db->commit();return $ajuste;
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
}
