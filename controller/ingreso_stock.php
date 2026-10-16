<?php
require_once __DIR__.'/../model/sesion.php';require_once __DIR__.'/../model/ingreso_stock.php';
header('Content-Type: application/json; charset=utf-8');
try{
    if(empty($_SESSION['rol_id_ifact'])||empty($_SESSION['usu_id_ifact'])){http_response_code(401);throw new RuntimeException('La sesión ha expirado.');}
    $loc=(int)($_SESSION['loc_id_ifact']??0);if(!$loc)throw new InvalidArgumentException('Selecciona nuevamente la tienda.');$task=(int)($_REQUEST['task']??0);$m=new ingreso_stock();
    if($_SERVER['REQUEST_METHOD']==='POST'){if(!hash_equals((string)($_SESSION['ingreso_stock_csrf']??''),(string)($_POST['csrf']??'')))throw new RuntimeException('La sesión del formulario no es válida.');}
    if($task===1)$out=['estado'=>'ok','data'=>$m->listar($loc,(string)($_GET['inicio']??''),(string)($_GET['fin']??''))];
    elseif($task===2)$out=['estado'=>'ok','data'=>$m->obtener((int)($_GET['id']??0),$loc)];
    elseif($task===3)$out=['estado'=>'ok','data'=>$m->productos($loc)];
    elseif($task===4&&$_SERVER['REQUEST_METHOD']==='POST'){$items=json_decode((string)($_POST['items']??''),true);if(!is_array($items))throw new InvalidArgumentException('El detalle no es válido.');$id=$m->guardar($loc,(int)$_SESSION['usu_id_ifact'],['fecha'=>$_POST['fecha']??'','observacion'=>$_POST['observacion']??'','clave'=>$_POST['clave']??'','items'=>$items]);$out=['estado'=>'ok','id'=>$id,'mensaje'=>'Ingreso a stock registrado correctamente.'];}
    elseif($task===5&&$_SERVER['REQUEST_METHOD']==='POST'){$m->anular((int)($_POST['id']??0),$loc,(int)$_SESSION['usu_id_ifact']);$out=['estado'=>'ok','mensaje'=>'Ingreso eliminado y stock actualizado correctamente.'];}
    else throw new InvalidArgumentException('Operación no válida.');
    echo json_encode($out,JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){if(!http_response_code()||http_response_code()===200)http_response_code($e instanceof InvalidArgumentException?422:500);echo json_encode(['estado'=>'error','mensaje'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}
