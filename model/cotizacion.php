<?php
require_once __DIR__.'/venta_catalogo.php';
class cotizacion
{
    private PDO $db;
    public function __construct(?PDO $db=null){$this->db=$db??(new conexion())->con();}
    private function query(string $sql,array $params=[]): PDOStatement {$s=$this->db->prepare($sql);$s->execute($params);return $s;}
    private function fecha(string $fecha): string {
        $d=DateTimeImmutable::createFromFormat('!Y-m-d',$fecha);
        if(!$d||$d->format('Y-m-d')!==$fecha)throw new InvalidArgumentException('Selecciona una fecha válida.');
        return $fecha;
    }
    public function listar(int $loc,string $inicio='',string $fin=''): array {
        $where='c.loc_id=?';$params=[$loc];
        if($inicio!==''){$where.=' AND c.cot_fecha>=?';$params[]=$this->fecha($inicio);}
        if($fin!==''){$where.=' AND c.cot_fecha<=?';$params[]=$this->fecha($fin);}
        if($inicio!==''&&$fin!==''&&$inicio>$fin)throw new InvalidArgumentException('La fecha inicial no puede superar la final.');
        return $this->query("SELECT c.*,e.ent_nombres AS cliente FROM cotizacion c JOIN entidad e ON e.ent_id=c.ent_id WHERE $where ORDER BY c.cot_id DESC",$params)->fetchAll(PDO::FETCH_ASSOC);
    }
    public function obtener(int $id,int $loc,bool $bloquear=false): array {
        $c=$this->query('SELECT c.*,e.ent_nombres,e.ent_nro_doc,e.ent_direccion,e.ent_contacto,a.loc_nombre FROM cotizacion c JOIN entidad e ON e.ent_id=c.ent_id LEFT JOIN local a ON a.loc_id=c.loc_id WHERE c.cot_id=? AND c.loc_id=?'.($bloquear?' FOR UPDATE':''),[$id,$loc])->fetch(PDO::FETCH_ASSOC);
        if(!$c)throw new InvalidArgumentException('La cotización no existe o no pertenece a esta tienda.');
        $items=$this->query('SELECT d.*,v.pva_imagen variante_imagen,p.pro_imagen producto_imagen FROM cotizacion_detalle d LEFT JOIN producto_variante v ON v.pva_id=d.pva_id LEFT JOIN producto p ON p.pro_id=d.ctd_ins_prod WHERE d.cot_id=? ORDER BY d.ctd_id',[$id])->fetchAll(PDO::FETCH_ASSOC);
        $versionItems=array_map(static function($item){unset($item['variante_imagen'],$item['producto_imagen']);return $item;},$items);
        return ['cotizacion'=>$c,'detalle'=>$items,'version'=>hash('sha256',json_encode([$c,$versionItems]))];
    }
    public function guardar(int $loc,int $usu,array $data): int {
        $id=(int)($data['cot_id']??0);$ent=(int)($data['ent_id']??0);$fecha=$this->fecha((string)($data['cot_fecha']??''));
        $observaciones=trim((string)($data['cot_observaciones']??''));
        if(mb_strlen($observaciones)>3000)throw new InvalidArgumentException('Las observaciones admiten hasta 3000 caracteres.');
        $items=$data['items']??[];
        if(!is_array($items)||!count($items)||count($items)>300)throw new InvalidArgumentException('Agrega entre 1 y 300 productos.');
        $clave=(string)($data['clave']??'');
        if(!$id&&!preg_match('/^[a-f0-9]{32}$/',$clave))throw new InvalidArgumentException('Actualiza la página antes de guardar.');
        $this->db->beginTransaction();
        try {
            if(!$id){$existente=$this->query('SELECT cot_id,loc_id FROM cotizacion WHERE cot_clave=?',[$clave])->fetch();if($existente){if((int)$existente['loc_id']!==$loc)throw new InvalidArgumentException('Solicitud no válida.');$this->db->commit();return (int)$existente['cot_id'];}}
            if($id){$actual=$this->obtener($id,$loc,true);if((int)$actual['cotizacion']['cot_estado']!==1)throw new InvalidArgumentException('La cotización está anulada.');if(!hash_equals($actual['version'],(string)($data['version']??'')))throw new InvalidArgumentException('La cotización cambió. Vuelve a abrirla antes de editar.');}
            if(!$this->query('SELECT COUNT(*) FROM entidad WHERE ent_id=? AND ent_cliente=1 AND ent_estado=1',[$ent])->fetchColumn())throw new InvalidArgumentException('Selecciona una entidad activa marcada como cliente.');
            $catalogo=[];foreach((new venta_catalogo($this->db))->listar_productos($loc) as $v)$catalogo[(int)$v['pva_id']]=$v;
            $rows=[];$seen=[];$total=0;
            foreach($items as $item){
                $pva=(int)($item['pva_id']??0);$v=$catalogo[$pva]??null;
                if(!$v||isset($seen[$pva]))throw new InvalidArgumentException('Revisa los productos: variante inválida o repetida.');$seen[$pva]=true;
                $q=$item['cantidad']??null;$p=$item['precio']??null;
                if(!is_numeric($q)||!is_numeric($p)||!is_finite((float)$q)||!is_finite((float)$p)||(float)$q<=0||(float)$q>99999999||(float)$p<0||(float)$p>99999999||abs((float)$q-round((float)$q,(int)$v['und_decimales']))>0.000001||abs((float)$p-round((float)$p,2))>0.000001)throw new InvalidArgumentException('Revisa cantidades y precios; respeta la precisión de la unidad y dos decimales en el precio.');
                $sub=(int)round((float)$q*(float)$p*100);$total+=$sub;
                if($total>999999999999)throw new InvalidArgumentException('El total supera el límite permitido.');
                $imagen=$this->query('SELECT COALESCE(NULLIF(v.pva_imagen,\'\'),NULLIF(p.pro_imagen,\'\')) FROM producto_variante v JOIN producto p ON p.pro_id=v.pro_id WHERE v.pva_id=?',[$pva])->fetchColumn();
                $rows[]=[$v['pro_id'],$v['pro_nombre'],$v['und_abrev'],round((float)$p,2),(float)$q,$sub/100,$pva,$v['pva_sku'],$imagen?:null];
            }
            if($id){$this->query('UPDATE cotizacion SET ent_id=?,cot_fecha=?,cot_total=?,cot_observaciones=?,cot_usu_ult_edicion=?,cot_fec_ult_edicion=NOW() WHERE cot_id=? AND loc_id=?',[$ent,$fecha,$total/100,$observaciones,$usu,$id,$loc]);$this->query('DELETE FROM cotizacion_detalle WHERE cot_id=?',[$id]);}
            else{$this->query("INSERT INTO cotizacion(cot_numero,cot_fecha,ent_id,cot_total,cot_estado,usuario,fechahora,loc_id,cot_observaciones,cot_clave) VALUES('',?,?,?,1,?,NOW(),?,?,?)",[$fecha,$ent,$total/100,$usu,$loc,$observaciones,$clave]);$id=(int)$this->db->lastInsertId();$this->query('UPDATE cotizacion SET cot_numero=? WHERE cot_id=?',['COT-'.str_pad((string)$id,6,'0',STR_PAD_LEFT),$id]);}
            foreach($rows as $r)$this->query('INSERT INTO cotizacion_detalle(cot_id,ctd_tipo,ctd_ins_prod,ctd_nombre,ctd_und_med,ctd_prec_unit,ctd_cantidad,ctd_subtotal,pva_id,ctd_sku,ctd_imagen) VALUES(?,2,?,?,?,?,?,?,?,?,?)',array_merge([$id],$r));
            $this->db->commit();return $id;
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    public function anular(int $id,int $loc,int $usu): void {
        $s=$this->query('UPDATE cotizacion SET cot_estado=2,cot_usu_anulado=?,cot_fec_anulado=NOW() WHERE cot_id=? AND loc_id=? AND cot_estado=1',[$usu,$id,$loc]);
        if(!$s->rowCount())throw new InvalidArgumentException('La cotización no existe o ya está anulada.');
    }
}
