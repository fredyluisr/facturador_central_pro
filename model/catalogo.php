<?php
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/producto_imagen.php';

class catalogo
{
    private PDO $db;
    private int $usuario;
    private const MAESTROS = [
        'categoria' => ['categoria','cat_id','cat_nombre','cat_estado'],
        'unidad' => ['unidad_medida','und_id','und_nombre','und_estado'],
        'atributo' => ['atributo','atr_id','atr_nombre','atr_estado'],
        'valor' => ['atributo_valor','ava_id','ava_nombre','ava_estado']
    ];
    public function __construct(?PDO $db = null, int $usuario = 0)
    {
        $this->db = $db ?? (new conexion())->con();
        $this->usuario = $usuario;
    }
    private function query(string $sql, array $args = []): PDOStatement
    {
        $s = $this->db->prepare($sql); $s->execute($args); return $s;
    }
    private function texto($value, int $max, string $label, bool $required = true): string
    {
        if (!is_scalar($value) && $value !== null) throw new InvalidArgumentException("$label no es válido.");
        $value = trim((string)$value);
        if (($required && $value === '') || mb_strlen($value) > $max) throw new InvalidArgumentException("Complete $label (máximo $max caracteres).");
        return $value;
    }
    private function estado($value): int
    {
        if (!in_array((string)$value,['0','1'],true)) throw new InvalidArgumentException('Estado no válido.');
        return (int)$value;
    }
    private function precio($value): string
    {
        if (!is_scalar($value) || !preg_match('/^\d{1,10}(\.\d{1,2})?$/',(string)$value)) throw new InvalidArgumentException('Ingrese un precio válido, positivo o cero, con hasta dos decimales.');
        return (string)$value;
    }
    private function maestro(string $tipo): array
    {
        if (!isset(self::MAESTROS[$tipo])) throw new InvalidArgumentException('Mantenedor no válido.');
        return self::MAESTROS[$tipo];
    }
    public function listar(string $tipo, int $padre = 0): array
    {
        [$table,$id,$name,$state]=$this->maestro($tipo);
        $where = "$state<>2"; $args=[];
        if ($tipo==='valor') { $where.=' AND atr_id=?'; $args[]=$padre; }
        return $this->query("SELECT * FROM $table WHERE $where ORDER BY $name,$id",$args)->fetchAll();
    }
    public function obtener(string $tipo, int $id): array
    {
        [$table,$pk,,$state]=$this->maestro($tipo);
        $row=$this->query("SELECT * FROM $table WHERE $pk=? AND $state<>2",[$id])->fetch();
        if (!$row) throw new InvalidArgumentException('El registro ya no está disponible.');
        return $row;
    }
    public function guardar(string $tipo, array $data): int
    {
        [$table,$pk,$name,$state]=$this->maestro($tipo);
        $id=(int)($data['id']??0);
        $values=[$name=>$this->texto($data[$name]??'', ['categoria'=>100,'unidad'=>20,'atributo'=>80,'valor'=>80][$tipo],'el nombre'),
            $state=>$this->estado($data[$state]??1)];
        if ($tipo==='categoria') $values['cat_descripcion']=$this->texto($data['cat_descripcion']??'',500,'la descripción',false);
        if ($tipo==='unidad') {
            $values['und_abrev']=mb_strtoupper($this->texto($data['und_abrev']??'',5,'la abreviatura'));
            $code=$this->texto($data['und_codigo_sunat']??'',3,'el código de unidad',false);
            if ($code!=='' && !preg_match('/^[A-Z0-9]{2,3}$/',$code)) throw new InvalidArgumentException('El código de unidad debe tener dos o tres letras mayúsculas o números.');
            $values['und_codigo_sunat']=$code ?: null;
            if (!in_array((string)($data['und_decimales']??0),['0','1','2','3'],true)) throw new InvalidArgumentException('Precisión de cantidad no válida.');
            $values['und_decimales']=(int)($data['und_decimales']??0);
        }
        if ($tipo==='valor') {
            $values['atr_id']=(int)($data['atr_id']??0);
            $attr=$this->obtener('atributo',$values['atr_id']);
            if ($attr['atr_estado']!=1 && !$id) throw new InvalidArgumentException('Habilite primero el atributo.');
            $hex=$this->texto($data['ava_color']??'',7,'el color',false);
            if ($hex!=='' && !preg_match('/^#[a-f0-9]{6}$/i',$hex)) throw new InvalidArgumentException('El color debe tener formato #RRGGBB.');
            $values['ava_color']=$hex ?: null;
        }
        $this->db->beginTransaction();
        try {
            $scope=$tipo==='valor'?' AND atr_id=?':'';
            $args=[$values[$name],$id]; if ($tipo==='valor') $args[]=$values['atr_id'];
            if ($this->query("SELECT COUNT(*) FROM $table WHERE $name=? AND $pk<>? $scope",$args)->fetchColumn()) throw new InvalidArgumentException('Ya existe un registro con ese nombre, incluso si fue retirado.');
            if ($tipo==='unidad' && $this->query('SELECT COUNT(*) FROM unidad_medida WHERE und_abrev=? AND und_id<>?',[$values['und_abrev'],$id])->fetchColumn()) throw new InvalidArgumentException('La abreviatura ya existe.');
            if ($id) {
                $old=$this->obtener($tipo,$id);
                if ($tipo==='valor' && $old['atr_id']!=$values['atr_id']) throw new InvalidArgumentException('No se puede cambiar el atributo de un valor.');
                $values[$tipo==='unidad'?'und_usu_ult_edicion':'usuario_edicion']=$this->usuario;
                $date=$tipo==='unidad'?'und_fec_ult_edicion':'fecha_edicion';
                $set=implode(',',array_map(fn($key)=>"$key=?",array_keys($values)));
                $this->query("UPDATE $table SET $set,$date=NOW() WHERE $pk=?",[...array_values($values),$id]);
            } else {
                $values['usuario']=$this->usuario;
                $columns=implode(',',array_keys($values)); $marks=implode(',',array_fill(0,count($values),'?'));
                $this->query("INSERT INTO $table($columns,fechahora) VALUES($marks,NOW())",array_values($values));
                $id=(int)$this->db->lastInsertId();
            }
            $this->db->commit(); return $id;
        } catch(Throwable $e) { $this->db->rollBack(); throw $e; }
    }
    public function eliminar(string $tipo, int $id): void
    {
        [$table,$pk,,$state]=$this->maestro($tipo);
        $this->db->beginTransaction();
        try {
            $this->query("SELECT $pk FROM $table WHERE $pk=? FOR UPDATE",[$id]);
            $this->obtener($tipo,$id);
            $used=match($tipo) {
                'categoria'=>$this->query('SELECT COUNT(*) FROM producto WHERE cat_id=?',[$id])->fetchColumn(),
                'unidad'=>$this->query('SELECT COUNT(*) FROM producto WHERE und_med=?',[$id])->fetchColumn()
                    +$this->query('SELECT COUNT(*) FROM venta_detalle_producto WHERE und_med=?',[$id])->fetchColumn(),
                'atributo'=>$this->query('SELECT COUNT(*) FROM atributo_valor WHERE atr_id=?',[$id])->fetchColumn(),
                'valor'=>$this->query('SELECT COUNT(*) FROM producto_variante_valor WHERE ava_id=?',[$id])->fetchColumn()
            };
            if ($used) throw new InvalidArgumentException('El registro tiene datos relacionados. Puede deshabilitarlo, pero no eliminarlo.');
            if ($tipo==='unidad') $this->query('UPDATE unidad_medida SET und_estado=2,und_usu_anulado=?,und_fec_anulado=NOW() WHERE und_id=?',[$this->usuario,$id]);
            else $this->query("UPDATE $table SET $state=2,usuario_edicion=?,fecha_edicion=NOW() WHERE $pk=?",[$this->usuario,$id]);
            $this->db->commit();
        } catch(Throwable $e) { $this->db->rollBack(); throw $e; }
    }
    public function opciones(): array
    {
        return ['categorias'=>$this->listar('categoria'),'unidades'=>$this->listar('unidad'),
            'atributos'=>$this->listar('atributo'),
            'valores'=>$this->query('SELECT * FROM atributo_valor WHERE ava_estado<>2 ORDER BY ava_nombre,ava_id')->fetchAll()];
    }
    public function productos(): array
    {
        return $this->query("SELECT p.pro_id,p.pro_nombre,p.pro_estado,p.pro_prec_venta,c.cat_nombre,u.und_abrev,
            COUNT(v.pva_id) AS variantes,SUM(v.pva_estado=1) AS variantes_activas
            FROM producto p LEFT JOIN categoria c ON c.cat_id=p.cat_id
            JOIN unidad_medida u ON u.und_id=p.und_med LEFT JOIN producto_variante v ON v.pro_id=p.pro_id
            WHERE p.pro_estado<>2 GROUP BY p.pro_id,p.pro_nombre,p.pro_estado,p.pro_prec_venta,c.cat_nombre,u.und_abrev ORDER BY p.pro_id DESC")->fetchAll();
    }
    public function producto(int $id): array
    {
        $p=$this->query('SELECT pro_id,pro_nombre,cat_id,und_med,pro_prec_venta,pro_estado,pro_imagen,usuario,fechahora,pro_usu_ult_edicion,pro_fec_ult_edicion FROM producto WHERE pro_id=? AND pro_estado<>2',[$id])->fetch();
        if (!$p) throw new InvalidArgumentException('El producto ya no está disponible.');
        $p['variantes']=$this->query('SELECT * FROM producto_variante WHERE pro_id=? ORDER BY pva_id',[$id])->fetchAll();
        foreach ($p['variantes'] as &$v) {
            $v['valores']=$this->query('SELECT ava_id FROM producto_variante_valor WHERE pva_id=? ORDER BY atr_id',[$v['pva_id']])->fetchAll(PDO::FETCH_COLUMN);
        } unset($v);
        $p['version']=hash('sha256',json_encode($p)); return $p;
    }

    public function guardarImagenVariante(int $varianteId, array $archivo): string
    {
        $variante=$this->query('SELECT v.pva_id,v.pva_imagen FROM producto_variante v JOIN producto p ON p.pro_id=v.pro_id WHERE v.pva_id=? AND p.pro_estado<>2',[$varianteId])->fetch();
        if(!$variante) throw new InvalidArgumentException('La variante ya no está disponible.');
        if(($archivo['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new InvalidArgumentException('Selecciona una imagen válida.');
        $size=(int)($archivo['size']??0);
        if($size<=0 || $size>5*1024*1024) throw new InvalidArgumentException('La imagen debe pesar como máximo 5 MB.');
        $tmp=(string)($archivo['tmp_name']??'');
        $info=@getimagesize($tmp);
        $tipos=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
        $mime=$info['mime']??'';
        if(!isset($tipos[$mime])) throw new InvalidArgumentException('Usa una imagen JPG, PNG, WEBP o GIF.');
        $dir=dirname(__DIR__).'/img/product/variants';
        if(!is_dir($dir) && !mkdir($dir,0775,true) && !is_dir($dir)) throw new RuntimeException('No se pudo preparar la carpeta de imágenes.');
        $nombre='variante_'.$varianteId.'_'.bin2hex(random_bytes(8)).'.'.$tipos[$mime];
        $destino=$dir.'/'.$nombre;
        if(!move_uploaded_file($tmp,$destino)) throw new RuntimeException('No se pudo guardar la imagen.');
        $ruta='img/product/variants/'.$nombre;
        try {
            $this->query('UPDATE producto_variante SET pva_imagen=?,usuario_edicion=?,fecha_edicion=NOW() WHERE pva_id=?',[$ruta,$this->usuario,$varianteId]);
        } catch(Throwable $e) { @unlink($destino); throw $e; }
        $this->borrarArchivoImagen($variante['pva_imagen']??null);
        return $ruta;
    }

    public function guardarImagenProducto(int $productoId, array $archivo): string
    {
        $producto=$this->query('SELECT pro_imagen FROM producto WHERE pro_id=? AND pro_estado<>2',[$productoId])->fetch();
        if(!$producto) throw new InvalidArgumentException('El producto ya no está disponible.');
        if(($archivo['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new InvalidArgumentException('Selecciona una imagen válida.');
        $size=(int)($archivo['size']??0);
        if($size<=0 || $size>ProductoImagen::MAX_BYTES) throw new InvalidArgumentException('La imagen debe pesar como máximo 10 MB.');
        $tmp=(string)($archivo['tmp_name']??''); $info=@getimagesize($tmp);
        $tipos=['image/jpeg','image/png','image/webp','image/gif']; $mime=$info['mime']??'';
        if(!in_array($mime,$tipos,true)) throw new InvalidArgumentException('Usa una imagen JPG, PNG, WebP o GIF.');
        $dir=dirname(__DIR__).'/img/product/products';
        if(!is_dir($dir) && !mkdir($dir,0775,true) && !is_dir($dir)) throw new RuntimeException('No se pudo preparar la carpeta de imágenes.');
        $nombre='producto_'.$productoId.'_'.bin2hex(random_bytes(8)).'.webp'; $destino=$dir.'/'.$nombre;
        ProductoImagen::convertir($tmp,$destino);
        $ruta='img/product/products/'.$nombre;
        try{$this->query('UPDATE producto SET pro_imagen=?,pro_usu_ult_edicion=?,pro_fec_ult_edicion=NOW() WHERE pro_id=?',[$ruta,$this->usuario,$productoId]);}
        catch(Throwable $e){@unlink($destino);throw $e;}
        $this->borrarArchivoImagenProducto($producto['pro_imagen']??null); return $ruta;
    }

    public function eliminarImagenProducto(int $productoId): void
    {
        $producto=$this->query('SELECT pro_imagen FROM producto WHERE pro_id=? AND pro_estado<>2',[$productoId])->fetch();
        if(!$producto) throw new InvalidArgumentException('El producto ya no está disponible.');
        $this->query('UPDATE producto SET pro_imagen=NULL,pro_usu_ult_edicion=?,pro_fec_ult_edicion=NOW() WHERE pro_id=?',[$this->usuario,$productoId]);
        $this->borrarArchivoImagenProducto($producto['pro_imagen']??null);
    }

    private function borrarArchivoImagenProducto(?string $ruta): void
    {
        if(!$ruta || !preg_match('#^img/product/(?:products/)?[^/]+$#',$ruta))return;
        $base=realpath(dirname(__DIR__).'/img/product');$archivo=realpath(dirname(__DIR__).'/'.$ruta);
        if($base&&$archivo&&str_starts_with($archivo,$base.DIRECTORY_SEPARATOR)&&is_file($archivo))@unlink($archivo);
    }

    public function eliminarImagenVariante(int $varianteId): void
    {
        $variante=$this->query('SELECT v.pva_imagen FROM producto_variante v JOIN producto p ON p.pro_id=v.pro_id WHERE v.pva_id=? AND p.pro_estado<>2',[$varianteId])->fetch();
        if(!$variante) throw new InvalidArgumentException('La variante ya no está disponible.');
        $this->query('UPDATE producto_variante SET pva_imagen=NULL,usuario_edicion=?,fecha_edicion=NOW() WHERE pva_id=?',[$this->usuario,$varianteId]);
        $this->borrarArchivoImagen($variante['pva_imagen']??null);
    }

    private function borrarArchivoImagen(?string $ruta): void
    {
        if(!$ruta || !str_starts_with($ruta,'img/product/variants/')) return;
        $base=realpath(dirname(__DIR__).'/img/product/variants');
        $archivo=realpath(dirname(__DIR__).'/'.$ruta);
        if($base && $archivo && str_starts_with($archivo,$base.DIRECTORY_SEPARATOR) && is_file($archivo)) @unlink($archivo);
    }
    public function guardarProducto(array $data): int
    {
        $id=(int)($data['pro_id']??0);
        $nombre=$this->texto($data['pro_nombre']??'',150,'el nombre del producto');
        $cat=(int)($data['cat_id']??0); $und=(int)($data['und_med']??0);
        $precio=$this->precio($data['pro_prec_venta']??''); $estado=$this->estado($data['pro_estado']??1);
        $rows=$data['variantes']??[];
        if (!is_array($rows) || !count($rows) || count($rows)>300) throw new InvalidArgumentException('Registre entre 1 y 300 variantes por producto.');
        $this->db->beginTransaction();
        try {
            $old=null; $original=[];
            if ($id) {
                $this->query('SELECT pro_id FROM producto WHERE pro_id=? FOR UPDATE',[$id]);
                $old=$this->producto($id);
                if (!hash_equals($old['version'],(string)($data['version']??''))) throw new InvalidArgumentException('Otro usuario modificó el producto. Cierre y vuelva a abrirlo antes de guardar.');
                foreach ($old['variantes'] as $v) $original[(int)$v['pva_id']]=$v;
            }
            $category=$this->obtener('categoria',$cat); $unit=$this->obtener('unidad',$und);
            if ($category['cat_estado']!=1 && (!$old || $old['cat_id']!=$cat)) throw new InvalidArgumentException('Seleccione una categoría habilitada.');
            if ($unit['und_estado']!=1 && (!$old || $old['und_med']!=$und)) throw new InvalidArgumentException('Seleccione una unidad habilitada.');
            $seenIds=[]; $seenSku=[]; $seenBars=[]; $seenComb=[]; $expectedAttrs=null; $validated=[]; $active=0;
            foreach ($rows as $row) {
                if (!is_array($row)) throw new InvalidArgumentException('Variante no válida.');
                $vid=(int)($row['pva_id']??0);
                if ($vid && (!isset($original[$vid]) || isset($seenIds[$vid]))) throw new InvalidArgumentException('La variante no pertenece al producto o está repetida.');
                if ($vid) $seenIds[$vid]=true;
                $sku=$this->texto($row['pva_sku']??'',60,'el SKU de cada variante');
                $bars=$this->texto($row['pva_codigo_barras']??'',80,'el código de barras',false);
                $price=$this->precio($row['pva_precio']??''); $state=$this->estado($row['pva_estado']??1); $active+=$state;
                if (isset($seenSku[mb_strtolower($sku)]) || ($bars!=='' && isset($seenBars[mb_strtolower($bars)]))) throw new InvalidArgumentException('No repita SKU ni códigos de barras.');
                $seenSku[mb_strtolower($sku)]=true; if ($bars!=='') $seenBars[mb_strtolower($bars)]=true;
                if ($this->query('SELECT COUNT(*) FROM producto_variante WHERE (pva_sku=? OR (?<>\'\' AND pva_codigo_barras=?)) AND pva_id<>?',[$sku,$bars,$bars,$vid])->fetchColumn()) throw new InvalidArgumentException('El SKU o código de barras ya pertenece a otra variante.');
                $vals=$row['valores']??[];
                if (!is_array($vals) || count($vals)>3) throw new InvalidArgumentException('Use hasta tres atributos por variante.');
                $map=[];
                foreach ($vals as $val) {
                    if (!is_scalar($val)) throw new InvalidArgumentException('Valor no válido.');
                    $value=$this->obtener('valor',(int)$val); $attribute=$this->obtener('atributo',(int)$value['atr_id']);
                    if (isset($map[$value['atr_id']])) throw new InvalidArgumentException('Una variante no puede tener dos valores del mismo atributo.');
                    $existing=$vid && in_array((int)$val,array_map('intval',$original[$vid]['valores']),true);
                    if (($value['ava_estado']!=1 || $attribute['atr_estado']!=1) && !$existing) throw new InvalidArgumentException('Seleccione valores y atributos habilitados.');
                    $map[(int)$value['atr_id']]=(int)$val;
                }
                ksort($map); $attrs=array_keys($map);
                if ($state===1) {
                    if ($expectedAttrs===null) $expectedAttrs=$attrs;
                    if ($expectedAttrs!==$attrs) throw new InvalidArgumentException('Las variantes habilitadas deben utilizar los mismos atributos. Deshabilite las combinaciones anteriores antes de cambiar los atributos.');
                }
                $combo=$map ? implode('-',array_values($map)) : 'simple';
                if (isset($seenComb[$combo])) throw new InvalidArgumentException('La combinación de atributos está repetida.');
                $seenComb[$combo]=true;
                if ($vid && $original[$vid]['pva_combinacion']!==$combo) throw new InvalidArgumentException('No cambie la combinación de una variante existente: deshabilítela y cree otra para conservar su identidad.');
                $validated[]=[$vid,$sku,$bars ?: null,$combo,$price,$state,$map];
            }
            if (count($seenIds)!==count($original)) throw new InvalidArgumentException('No retire variantes existentes: deshabilítelas para conservar su historial.');
            if ($estado===1 && !$active) throw new InvalidArgumentException('Un producto habilitado necesita al menos una variante habilitada.');
            if (!$id) {
                $this->query('INSERT INTO producto(pro_nombre,cat_id,und_med,pro_prec_venta,pro_estado,usuario,fechahora) VALUES(?,?,?,?,?,?,NOW())',[$nombre,$cat,$und,$precio,$estado,$this->usuario]);
                $id=(int)$this->db->lastInsertId();
            } else $this->query('UPDATE producto SET pro_nombre=?,cat_id=?,und_med=?,pro_prec_venta=?,pro_estado=?,pro_usu_ult_edicion=?,pro_fec_ult_edicion=NOW() WHERE pro_id=?',[$nombre,$cat,$und,$precio,$estado,$this->usuario,$id]);
            foreach ($validated as [$vid,$sku,$bars,$combo,$price,$state,$map]) {
                if ($vid) $this->query('UPDATE producto_variante SET pva_sku=?,pva_codigo_barras=?,pva_precio=?,pva_estado=?,usuario_edicion=?,fecha_edicion=NOW() WHERE pva_id=?',[$sku,$bars,$price,$state,$this->usuario,$vid]);
                else {
                    $this->query('INSERT INTO producto_variante(pro_id,pva_sku,pva_codigo_barras,pva_combinacion,pva_precio,pva_estado,usuario,fechahora) VALUES(?,?,?,?,?,?,?,NOW())',[$id,$sku,$bars,$combo,$price,$state,$this->usuario]);
                    $vid=(int)$this->db->lastInsertId();
                    foreach ($map as $atr=>$val) $this->query('INSERT INTO producto_variante_valor(pva_id,atr_id,ava_id) VALUES(?,?,?)',[$vid,$atr,$val]);
                }
            }
            $this->db->commit(); return $id;
        } catch(Throwable $e) { $this->db->rollBack(); throw $e; }
    }
    public function eliminarProducto(int $id): void
    {
        $this->db->beginTransaction();
        try {
            $this->query('SELECT pro_id FROM producto WHERE pro_id=? FOR UPDATE',[$id]);
            $this->producto($id);
            if ($this->query('SELECT COUNT(*) FROM venta_detalle_producto WHERE pro_id=?',[$id])->fetchColumn()) throw new InvalidArgumentException('El producto tiene ventas. Deshabilítelo para conservar su historial.');
            $this->query('UPDATE producto SET pro_estado=2,pro_usu_anulado=?,pro_fec_anulado=NOW() WHERE pro_id=?',[$this->usuario,$id]);
            $this->query('UPDATE producto_variante SET pva_estado=0,usuario_edicion=?,fecha_edicion=NOW() WHERE pro_id=?',[$this->usuario,$id]);
            $this->db->commit();
        } catch(Throwable $e) { $this->db->rollBack(); throw $e; }
    }
}
