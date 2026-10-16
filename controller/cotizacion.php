<?php
require_once __DIR__.'/../model/sesion.php';
require_once __DIR__.'/../model/cotizacion.php';
header('Content-Type: application/json; charset=utf-8');
$loc=(int)($_SESSION['loc_id_ifact']??0);$usu=(int)($_SESSION['usu_id_ifact']??0);$csrf=(string)($_SESSION['cotizacion_csrf']??'');
if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
if(!$loc||!$usu){http_response_code(401);echo json_encode(['estado'=>'error','mensaje'=>'Inicia sesión nuevamente.']);exit;}
try {
    $task=(int)($_REQUEST['task']??0);$m=new cotizacion();
    if(in_array($task,[4,5],true)&&($_SERVER['REQUEST_METHOD']!=='POST'||$csrf===''||!hash_equals($csrf,(string)($_POST['csrf']??'')))){http_response_code(403);throw new InvalidArgumentException('Actualiza la página antes de guardar.');}
    switch($task){
        case 1:$r=['estado'=>'ok','data'=>$m->listar($loc,(string)($_GET['inicio']??''),(string)($_GET['fin']??''))];break;
        case 2:$r=['estado'=>'ok']+$m->obtener((int)($_GET['cot_id']??0),$loc);break;
        case 4:$data=$_POST;$data['items']=json_decode((string)($_POST['items']??''),true);$id=$m->guardar($loc,$usu,$data);$r=['estado'=>'ok','cot_id'=>$id,'mensaje'=>'Cotización guardada correctamente.'];break;
        case 5:$m->anular((int)($_POST['cot_id']??0),$loc,$usu);$r=['estado'=>'ok','mensaje'=>'Cotización anulada.'];break;
        default:throw new InvalidArgumentException('Opción no válida.');
    }
    echo json_encode($r,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(InvalidArgumentException $e){echo json_encode(['estado'=>'error','mensaje'=>$e->getMessage()]);}
catch(Throwable $e){error_log('cotizacion: '.$e->getMessage());http_response_code(500);echo json_encode(['estado'=>'error','mensaje'=>'No se pudo completar la operación.']);}
