<?php
require_once __DIR__.'/conexion.php';

final class reporte_stock_valorizado
{
    private PDO $db;
    public function __construct(?PDO $db=null){$this->db=$db??(new conexion())->con();}
    public function obtener(int $loc): array {
        if($loc<=0)throw new InvalidArgumentException('No se pudo identificar el local.');
        $sql="SELECT v.pva_id,p.pro_id,p.pro_nombre,c.cat_nombre,v.pva_sku,u.und_abrev,
            COALESCE(a.atributos,'Sin atributos') atributos,COALESCE(s.cantidad,0) stock,
            v.pva_precio precio,COALESCE(s.cantidad,0)*v.pva_precio valorizado
            FROM producto_variante v JOIN producto p ON p.pro_id=v.pro_id
            JOIN unidad_medida u ON u.und_id=p.und_med LEFT JOIN categoria c ON c.cat_id=p.cat_id
            LEFT JOIN stock_variante s ON s.pva_id=v.pva_id AND s.loc_id=?
            LEFT JOIN (SELECT vv.pva_id,GROUP_CONCAT(CONCAT(at.atr_nombre,': ',av.ava_nombre) ORDER BY at.atr_id SEPARATOR ' · ') atributos FROM producto_variante_valor vv JOIN atributo at ON at.atr_id=vv.atr_id JOIN atributo_valor av ON av.ava_id=vv.ava_id GROUP BY vv.pva_id) a ON a.pva_id=v.pva_id
            WHERE p.pro_estado=1 AND v.pva_estado=1 AND u.und_estado=1 ORDER BY valorizado DESC,p.pro_nombre,a.atributos";
        $s=$this->db->prepare($sql);$s->execute([$loc]);$detalle=$s->fetchAll(PDO::FETCH_ASSOC);
        $productos=[];$categorias=[];$total=0;$unidades=0;$conStock=0;$agotadas=0;$negativas=0;
        foreach($detalle as &$f){foreach(['stock','precio','valorizado'] as $k)$f[$k]=(float)$f[$k];$total+=$f['valorizado'];$unidades+=$f['stock'];$f['stock']>0?$conStock++:($f['stock']<0?$negativas++:$agotadas++);$pn=$f['pro_nombre'];if(!isset($productos[$pn]))$productos[$pn]=0;$productos[$pn]+=$f['valorizado'];$cat=$f['cat_nombre']?:'Sin categoría';if(!isset($categorias[$cat]))$categorias[$cat]=0;$categorias[$cat]+=$f['valorizado'];}unset($f);
        arsort($productos);arsort($categorias);$top=[];foreach(array_slice($productos,0,10,true) as $n=>$v)$top[]=['nombre'=>$n,'valor'=>$v];$dist=[];foreach($categorias as $n=>$v)$dist[]=['nombre'=>$n,'valor'=>$v];
        return ['estado'=>'ok','kpis'=>['valor_total'=>$total,'unidades'=>$unidades,'variantes'=>count($detalle),'con_stock'=>$conStock,'agotadas'=>$agotadas,'negativas'=>$negativas,'producto_mayor'=>array_key_first($productos)?:'—','valor_mayor'=>$productos[array_key_first($productos)]??0],'top'=>$top,'categorias'=>$dist,'detalle'=>$detalle,'actualizado'=>date('d/m/Y H:i:s')];
    }
}
