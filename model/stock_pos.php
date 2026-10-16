<?php
require_once __DIR__.'/conexion.php';
final class stock_pos {
    private PDO $db;
    public function __construct(?PDO $db=null){$this->db=$db??(new conexion())->con();}
    private function q(string $sql,array $args=[]):PDOStatement{$s=$this->db->prepare($sql);$s->execute($args);return $s;}
    public function reporte(int $locId):array{
        $sql="SELECT v.pva_id,v.pva_sku,v.pva_codigo_barras,p.pro_nombre,u.und_abrev,COALESCE(s.cantidad,0) stock,COALESCE(a.atributos,'Sin atributos') atributos FROM producto_variante v JOIN producto p ON p.pro_id=v.pro_id JOIN unidad_medida u ON u.und_id=p.und_med LEFT JOIN stock_variante s ON s.pva_id=v.pva_id AND s.loc_id=? LEFT JOIN (SELECT vv.pva_id,GROUP_CONCAT(CONCAT(at.atr_nombre,': ',av.ava_nombre) ORDER BY at.atr_id SEPARATOR ' · ') atributos FROM producto_variante_valor vv JOIN atributo at ON at.atr_id=vv.atr_id JOIN atributo_valor av ON av.ava_id=vv.ava_id GROUP BY vv.pva_id) a ON a.pva_id=v.pva_id WHERE p.pro_estado=1 AND v.pva_estado=1 AND u.und_estado=1 ORDER BY p.pro_nombre,a.atributos,v.pva_sku";
        return ['estado'=>'ok','productos'=>$this->q($sql,[$locId])->fetchAll(PDO::FETCH_ASSOC)];
    }
    public function ultimosMovimientos(int $locId,int $pvaId):array{
        if($pvaId<=0)throw new InvalidArgumentException('El producto no es válido.');
        $producto=$this->q("SELECT p.pro_nombre,v.pva_sku,u.und_abrev,COALESCE(a.atributos,'Sin atributos') atributos,COALESCE(s.cantidad,0) stock FROM producto_variante v JOIN producto p ON p.pro_id=v.pro_id JOIN unidad_medida u ON u.und_id=p.und_med LEFT JOIN stock_variante s ON s.pva_id=v.pva_id AND s.loc_id=? LEFT JOIN (SELECT vv.pva_id,GROUP_CONCAT(CONCAT(at.atr_nombre,': ',av.ava_nombre) ORDER BY at.atr_id SEPARATOR ' · ') atributos FROM producto_variante_valor vv JOIN atributo at ON at.atr_id=vv.atr_id JOIN atributo_valor av ON av.ava_id=vv.ava_id GROUP BY vv.pva_id) a ON a.pva_id=v.pva_id WHERE v.pva_id=? AND p.pro_estado=1 AND v.pva_estado=1",[$locId,$pvaId])->fetch(PDO::FETCH_ASSOC);if(!$producto)throw new InvalidArgumentException('El producto no está disponible.');
        $sql="SELECT m.id,m.tipo,m.cantidad,m.fecha,m.ven_id,m.isi_id,m.aji_id,
            i.isi_observacion,a.aji_observacion,
            COALESCE(e.ent_nombres,'Cliente general') cliente,
            v.ven_serie,v.ven_numero,
            (SELECT CONCAT(f.fac_serie,'-',LPAD(f.fac_correlativo,8,'0'))
               FROM facturacion f
              WHERE f.ven_id=m.ven_id AND f.fac_tipo_doc='07' AND f.fac_estado=2
              ORDER BY f.fac_id DESC LIMIT 1) nota_credito
            FROM movimiento_stock_variante m
            LEFT JOIN ingreso_stock i ON i.isi_id=m.isi_id
            LEFT JOIN ajuste_inventario a ON a.aji_id=m.aji_id
            LEFT JOIN venta v ON v.ven_id=m.ven_id
            LEFT JOIN entidad e ON e.ent_id=v.ent_id
            WHERE m.loc_id=? AND m.pva_id=?
            ORDER BY m.id DESC LIMIT 10";
        $filas=$this->q($sql,[$locId,$pvaId])->fetchAll(PDO::FETCH_ASSOC);
        $saldo=(float)$producto['stock'];$movimientos=[];
        foreach($filas as $m){
            $tipo=(int)$m['tipo'];$cantidad=(float)$m['cantidad'];$entrada=in_array($tipo,[1,3,5],true);$delta=$entrada?$cantidad:-$cantidad;
            if(in_array($tipo,[1,4],true)){$referencia='ING-'.str_pad((string)$m['isi_id'],6,'0',STR_PAD_LEFT);$detalle=$tipo===1?'Ingreso a stock':'Reversión de ingreso';$nota=$m['isi_observacion']??'';}
            elseif(in_array($tipo,[2,3],true)){$referencia=$tipo===3&&$m['nota_credito']?$m['nota_credito']:($m['ven_serie']&&$m['ven_numero']?$m['ven_serie'].'-'.str_pad((string)$m['ven_numero'],8,'0',STR_PAD_LEFT):'VEN-'.str_pad((string)$m['ven_id'],6,'0',STR_PAD_LEFT));$detalle=$tipo===2?'Venta':'Devolución por nota de crédito';$nota=$tipo===3?'Stock restituido por anulación electrónica':($m['cliente']??'');}
            else{$referencia='AJU-'.str_pad((string)$m['aji_id'],6,'0',STR_PAD_LEFT);$detalle=$tipo===5?'Ajuste positivo':'Ajuste negativo';$nota=$m['aji_observacion']??'';}
            $movimientos[]=['id'=>(int)$m['id'],'tipo'=>$tipo,'movimiento'=>$detalle,'referencia'=>$referencia,'detalle'=>$nota,'fecha'=>$m['fecha'],'entrada'=>$entrada?$cantidad:null,'salida'=>$entrada?null:$cantidad,'saldo'=>$saldo];
            $saldo=round($saldo-$delta,3);
        }
        return ['estado'=>'ok','producto'=>$producto,'movimientos'=>$movimientos];
    }
}
