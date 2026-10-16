<?php
require_once __DIR__ . '/conexion.php';
class configuracion_sistema
{
    private conexion $cnx;
    public function __construct(){ $this->cnx=new conexion(); }
    public function obtenerLocal():array{
        $stmt=$this->cnx->con()->query("SELECT csi_formato_pdf,csi_nota_pedido_serie,csi_nota_pedido_siguiente,csi_vender_sin_stock,csi_facturacion_electronica,csi_nombre_comercial,csi_razon_social,csi_ruc,csi_fecha_modificacion FROM configuracion_sistema WHERE csi_id=1");
        $local=$stmt->fetch(PDO::FETCH_ASSOC)?:['csi_formato_pdf'=>'ticket','csi_vender_sin_stock'=>0,'csi_facturacion_electronica'=>1,'csi_fecha_modificacion'=>null];
        $config=$this->configApi(false);
        if(empty($local['csi_ruc']))$local['csi_ruc']=(string)($config['ruc']??'');
        return $local;
    }
    public function obtenerCentral():array{
        $config=$this->configApi();
        $r=$this->api('GET','/api/empresa?'.http_build_query(['ruc'=>$config['ruc']]),null,$config);
        $e=$r['empresa']??[];
        $central=['conectado'=>!empty($r['success']),'ruc'=>(string)($e['em_ruc']??$config['ruc']),'razon_social'=>(string)($e['em_razon_social']??''),'modo'=>(string)($e['qpse_modo']??''),'activo'=>array_key_exists('activo',$e)?(int)$e['activo']:null];
        if($central['conectado']&&$central['razon_social']!==''){$s=$this->cnx->con()->prepare('UPDATE configuracion_sistema SET csi_razon_social=?,csi_ruc=? WHERE csi_id=1');$s->execute([$central['razon_social'],$central['ruc']]);}
        return $central;
    }
    public function obtener():array{
        $local=$this->obtenerLocal();
        try{$local['central']=$this->obtenerCentral();}catch(Throwable $e){$local['central']=['conectado'=>false,'ruc'=>(string)($local['csi_ruc']??''),'razon_social'=>(string)($local['csi_razon_social']??''),'mensaje'=>$e->getMessage()];}
        return $local;
    }
    public static function venderSinStock(PDO $cn): bool {
        return (int)$cn->query('SELECT csi_vender_sin_stock FROM configuracion_sistema WHERE csi_id=1')->fetchColumn()===1;
    }
    public static function facturacionElectronicaHabilitada(PDO $cn): bool {
        return (int)$cn->query('SELECT csi_facturacion_electronica FROM configuracion_sistema WHERE csi_id=1')->fetchColumn()===1;
    }
    public function guardarFacturacionElectronica():array {
        $valor=(string)($_POST['habilitada']??'');
        if (!in_array($valor,['0','1'],true)) return ['ok'=>false,'mensaje'=>'Seleccione una configuración de facturación válida'];
        $s=$this->cnx->con()->prepare('UPDATE configuracion_sistema SET csi_facturacion_electronica=?,csi_usuario_modificacion=?,csi_fecha_modificacion=NOW() WHERE csi_id=1');
        $s->execute([(int)$valor,(int)($_SESSION['usu_id_ifact']??0)?:null]);
        return ['ok'=>true,'habilitada'=>(int)$valor,'mensaje'=>(int)$valor===1?'La facturación electrónica quedó activada.':'La facturación electrónica quedó desactivada.'];
    }
    public function guardarStock():array {
        $valor=(string)($_POST['vender_sin_stock']??'');
        if (!in_array($valor,['0','1'],true)) return ['ok'=>false,'mensaje'=>'Seleccione el modo de venta'];
        $s=$this->cnx->con()->prepare('INSERT INTO configuracion_sistema(csi_id,csi_formato_pdf,csi_vender_sin_stock,csi_usuario_modificacion,csi_fecha_modificacion,csi_instancia_uuid) VALUES(1,\'ticket\',?,?,NOW(),LOWER(REPLACE(UUID(),\'-\',\'\'))) ON DUPLICATE KEY UPDATE csi_vender_sin_stock=VALUES(csi_vender_sin_stock),csi_usuario_modificacion=VALUES(csi_usuario_modificacion),csi_fecha_modificacion=NOW()');
        $s->execute([(int)$valor,(int)($_SESSION['usu_id_ifact']??0)?:null]);
        return ['ok'=>true,'mensaje'=>'Modo de venta guardado correctamente'];
    }
    public function guardarNombreComercial():array {
        $nombre=trim((string)($_POST['nombre_comercial']??''));
        if(mb_strlen($nombre)<2||mb_strlen($nombre)>100)return ['ok'=>false,'mensaje'=>'El nombre comercial debe tener entre 2 y 100 caracteres'];
        $s=$this->cnx->con()->prepare('INSERT INTO configuracion_sistema(csi_id,csi_nombre_comercial,csi_usuario_modificacion,csi_fecha_modificacion,csi_instancia_uuid) VALUES(1,?,?,NOW(),LOWER(REPLACE(UUID(),\'-\',\'\'))) ON DUPLICATE KEY UPDATE csi_nombre_comercial=VALUES(csi_nombre_comercial),csi_usuario_modificacion=VALUES(csi_usuario_modificacion),csi_fecha_modificacion=NOW()');
        $s->execute([$nombre,(int)($_SESSION['usu_id_ifact']??0)?:null]);
        return ['ok'=>true,'mensaje'=>'Nombre comercial guardado correctamente','nombre_comercial'=>$nombre];
    }
    public static function datosEmisor(PDO $cn):array {
        $c=$cn->query('SELECT csi_nombre_comercial,csi_razon_social,csi_ruc FROM configuracion_sistema WHERE csi_id=1')->fetch(PDO::FETCH_ASSOC)?:[];
        $ruc=(string)(self::configFacturacion(false)['ruc']??$c['csi_ruc']??'');
        $razon=trim((string)($c['csi_razon_social']??''));
        $direccion='';
        if($ruc!==''){
            $s=$cn->prepare('SELECT ent_nombres,ent_direccion FROM entidad WHERE ent_nro_doc=? AND ent_estado=1 ORDER BY ent_id LIMIT 1');
            $s->execute([$ruc]); $entidad=$s->fetch(PDO::FETCH_ASSOC)?:[];
            if($razon==='')$razon=(string)($entidad['ent_nombres']??'');
            $direccion=trim((string)($entidad['ent_direccion']??''));
        }
        return ['nombre_comercial'=>trim((string)($c['csi_nombre_comercial']??'')),'razon_social'=>$razon,'ruc'=>$ruc,'direccion'=>$direccion];
    }
    public function guardarNotaPedido():array {
        $serie=strtoupper(trim((string)($_POST['nota_pedido_serie']??'')));
        $siguiente=(int)($_POST['nota_pedido_siguiente']??0);
        if(!preg_match('/^[A-Z0-9]{1,4}$/',$serie))return ['ok'=>false,'mensaje'=>'La serie debe tener entre 1 y 4 letras o números'];
        if($siguiente<1||$siguiente>99999999)return ['ok'=>false,'mensaje'=>'El próximo correlativo debe estar entre 1 y 99999999'];
        $cn=$this->cnx->con();
        $q=$cn->prepare('SELECT COALESCE(MAX(ven_numero),0) FROM venta WHERE ven_serie=?');$q->execute([$serie]);$ultimo=(int)$q->fetchColumn();
        if($siguiente<=$ultimo)return ['ok'=>false,'mensaje'=>'El próximo correlativo debe ser mayor que '.str_pad((string)$ultimo,8,'0',STR_PAD_LEFT).' para evitar duplicados'];
        $q=$cn->prepare('UPDATE configuracion_sistema SET csi_nota_pedido_serie=?,csi_nota_pedido_siguiente=?,csi_usuario_modificacion=?,csi_fecha_modificacion=NOW() WHERE csi_id=1');
        $q->execute([$serie,$siguiente,(int)($_SESSION['usu_id_ifact']??0)?:null]);
        return ['ok'=>true,'mensaje'=>'Correlativo de nota de venta guardado','serie'=>$serie,'siguiente'=>$siguiente];
    }
    public function guardar():array{
        $stmt=$this->cnx->con()->prepare("UPDATE configuracion_sistema SET csi_formato_pdf='ticket',csi_usuario_modificacion=:u,csi_fecha_modificacion=NOW() WHERE csi_id=1");$stmt->execute([':u'=>(int)($_SESSION['usu_id_ifact']??0)?:null]);return ['ok'=>true,'mensaje'=>'El formato Ticketera quedó establecido','formato_pdf'=>'ticket'];
    }
    public function buscarEmpresas(string $q):array{
        $q=trim($q);if(mb_strlen($q)<3)return ['ok'=>false,'mensaje'=>'Ingrese al menos 3 caracteres para buscar'];
        try{$cn=$this->conexionCentral();$st=$cn->prepare("SELECT em_id,em_ruc,em_razon_social,em_nombre_comercial,em_direccion,qpse_modo,activo FROM empresa WHERE activo=1 AND (em_ruc LIKE :r OR em_razon_social LIKE :n OR em_nombre_comercial LIKE :c) ORDER BY em_razon_social LIMIT 20");$t='%'.$q.'%';$st->execute([':r'=>$t,':n'=>$t,':c'=>$t]);return ['ok'=>true,'empresas'=>$st->fetchAll(PDO::FETCH_ASSOC)];}catch(Throwable $e){return ['ok'=>false,'mensaje'=>'No se pudo consultar el registro central: '.$e->getMessage()];}
    }
    public function cambiarEmpresa(int $id):array{
        if($id<1)return ['ok'=>false,'mensaje'=>'Seleccione una empresa válida'];$st=$this->conexionCentral()->prepare('SELECT em_ruc,em_razon_social,em_nombre_comercial,em_direccion,em_ubigeo,em_departamento,em_provincia,em_distrito,qpse_modo,activo,token FROM empresa WHERE em_id=? LIMIT 1');$st->execute([$id]);$e=$st->fetch(PDO::FETCH_ASSOC);if(!$e||(int)$e['activo']!==1||trim((string)$e['token'])==='')return ['ok'=>false,'mensaje'=>'La empresa no existe, está inactiva o no tiene token'];
        $anterior=$this->leerEnv();$actual=$this->configApi();$this->guardarEnvFacturacion(['base_url'=>$actual['base_url'],'ruc'=>(string)$e['em_ruc'],'token'=>(string)$e['token'],'timeout'=>(int)($actual['timeout']??60)]);
        try{
            $cfg=$this->configApi();$v=$this->api('GET','/api/empresa?'.http_build_query(['ruc'=>$cfg['ruc']]),null,$cfg);if(empty($v['success']))throw new RuntimeException($v['mensaje']??'La central rechazó las credenciales');
        }catch(Throwable $x){$this->guardarEnv($anterior);throw new RuntimeException('No se pudo validar la nueva empresa; no se aplicaron cambios. '.$x->getMessage());}
        $s=$this->cnx->con()->prepare('UPDATE configuracion_sistema SET csi_razon_social=?,csi_ruc=? WHERE csi_id=1');$s->execute([(string)$e['em_razon_social'],(string)$e['em_ruc']]);
        return ['ok'=>true,'mensaje'=>'Empresa emisora actualizada correctamente'];
    }
    public static function formatoPdf(PDO $cn):string{return 'ticket';}
    public static function configFacturacion(bool $validar=true):array{$v=self::leerEnvArchivo();$c=['base_url'=>(string)($v['FACTURACION_API_URL']??''),'ruc'=>(string)($v['FACTURACION_API_RUC']??''),'token'=>(string)($v['FACTURACION_API_TOKEN']??''),'timeout'=>(int)($v['FACTURACION_API_TIMEOUT']??60)];if($validar&&($c['base_url']===''||$c['ruc']===''||$c['token']===''))throw new RuntimeException('Falta configurar FACTURACION_API_URL, FACTURACION_API_RUC o FACTURACION_API_TOKEN en .env');return $c;}
    private function configApi(bool $validar=true):array{return self::configFacturacion($validar);}
    private function api(string $metodo,string $ruta,?array $payload,array $c):array{$ch=curl_init(rtrim((string)$c['base_url'],'/').$ruta);$h=['Accept: application/json','Authorization: Bearer '.(string)$c['token']];curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$h,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>(int)($c['timeout']??60)]);$ca=trim((string)conexion::config('FACTURACION_CA_BUNDLE',''));if($ca!==''){if(!is_file($ca)||!is_readable($ca)){curl_close($ch);throw new RuntimeException('FACTURACION_CA_BUNDLE no existe o no se puede leer');}curl_setopt($ch,CURLOPT_CAINFO,$ca);}curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,true);curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);$raw=curl_exec($ch);$err=curl_error($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);if($raw===false||$raw==='')throw new RuntimeException('No se pudo conectar con el servicio central: '.($err?:'respuesta vacía'));$r=json_decode($raw,true);if(!is_array($r))throw new RuntimeException('El servicio central devolvió una respuesta inválida');if($http>=400)throw new RuntimeException((string)($r['mensaje']??$r['error']??'Error del servicio central'));return $r;}
    private function conexionCentral():PDO{$e=$this->leerEnv();$host=$e['FACTURACION_CENTRAL_DB_HOST']??$e['DB_HOST']??'';$db=$e['FACTURACION_CENTRAL_DB_DATABASE']??'';if($host===''||$db==='')throw new RuntimeException('Falta FACTURACION_CENTRAL_DB_DATABASE en .env');return new PDO('mysql:host='.$host.';port='.($e['FACTURACION_CENTRAL_DB_PORT']??$e['DB_PORT']??'3306').';dbname='.$db.';charset=utf8mb4',$e['FACTURACION_CENTRAL_DB_USERNAME']??$e['DB_USERNAME']??'',$e['FACTURACION_CENTRAL_DB_PASSWORD']??$e['DB_PASSWORD']??'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);}
    private static function leerEnvArchivo():array{$v=[];$p=dirname(__DIR__).'/.env';foreach(is_file($p)?(file($p,FILE_IGNORE_NEW_LINES)?:[]):[] as $l){$l=trim($l);if($l===''||str_starts_with($l,'#')||!str_contains($l,'='))continue;[$k,$x]=array_map('trim',explode('=',$l,2));$v[$k]=trim($x,"\"'");}return $v;}
    private function leerEnv():array{return self::leerEnvArchivo();}
    private function guardarEnvFacturacion(array $c):void{$v=$this->leerEnv();$v['FACTURACION_API_URL']=(string)$c['base_url'];$v['FACTURACION_API_RUC']=(string)$c['ruc'];$v['FACTURACION_API_TOKEN']=(string)$c['token'];$v['FACTURACION_API_TIMEOUT']=(string)$c['timeout'];$this->guardarEnv($v);}
    private function guardarEnv(array $v):void{$p=dirname(__DIR__).'/.env';$lineas=[];foreach($v as $k=>$x){if(!preg_match('/^[A-Z][A-Z0-9_]*$/',(string)$k))continue;$x=(string)$x;if(preg_match('/[\s#="\']/', $x))$x='"'.str_replace(['\\','"'],['\\\\','\\"'],$x).'"';$lineas[]=$k.'='.$x;}if(file_put_contents($p,implode(PHP_EOL,$lineas).PHP_EOL,LOCK_EX)===false)throw new RuntimeException('No se pudo actualizar .env');}
}
