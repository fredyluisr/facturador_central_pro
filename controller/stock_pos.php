<?php
require_once __DIR__.'/../model/sesion.php';require_once __DIR__.'/../model/stock_pos.php';
header('Content-Type: application/json; charset=utf-8');

try{
    if(empty($_SESSION['rol_id_ifact'])){http_response_code(401);throw new RuntimeException('La sesión ha expirado.');}
    $loc=(int)($_SESSION['loc_id_ifact']??0);if($loc<=0){$db=(new conexion())->con();$activos=$db->query('SELECT loc_id FROM local WHERE loc_estado=1 ORDER BY loc_id')->fetchAll(PDO::FETCH_COLUMN);if(count($activos)!==1)throw new RuntimeException('No se pudo identificar el stock general.');$loc=(int)$activos[0];$_SESSION['loc_id_ifact']=$loc;}
    $m=new stock_pos();$task=(int)($_GET['task']??1);$out=$task===2?$m->ultimosMovimientos($loc,(int)($_GET['pva_id']??0)):$m->reporte($loc);echo json_encode($out,JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){if(http_response_code()===200)http_response_code($e instanceof InvalidArgumentException?422:500);echo json_encode(['estado'=>'error','mensaje'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}
