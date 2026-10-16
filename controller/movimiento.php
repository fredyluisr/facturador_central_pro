<?php
require_once __DIR__.'/../model/catalogo_acceso.php';
require_once __DIR__.'/../model/movimiento.php';
$usu=(int)($_SESSION['usu_id_ifact']??0);$loc=(int)($_SESSION['loc_id_ifact']??0);$csrf=(string)($_SESSION['movimiento_csrf']??'');
$permitido=catalogoPuedeAcceder('list_movimientos.php');
if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
header('Content-Type: application/json; charset=utf-8');
if(!$usu||!$permitido){http_response_code($usu?403:401);echo json_encode(['estado'=>'error','mensaje'=>$usu?'No tienes acceso a Movimientos.':'Inicia sesión nuevamente.']);exit;}
try{
    $m=new movimiento();$task=(int)($_REQUEST['task']??0);$id=(int)($_REQUEST['com_id']??0);
    if($id>0&&in_array($task,[2,4,5,10,11,13,14,15],true)&&!$m->perteneceAlLocal($id,$loc)){http_response_code(404);throw new InvalidArgumentException('El movimiento no está disponible en el local actual.');}
    if(in_array($task,[4,5,10,11,13,14],true)&&($_SERVER['REQUEST_METHOD']!=='POST'||!$csrf||!hash_equals($csrf,(string)($_POST['csrf']??'')))){http_response_code(403);throw new InvalidArgumentException('Actualiza la página antes de guardar.');}
    switch($task){
        case 1:$r=['data'=>$m->listar($loc,(string)($_GET['inicio']??''),(string)($_GET['fin']??''))];break;
        case 2:$r=$m->obtener($id);break;
        case 3:$r=$m->opciones();break;
        case 4:$d=$_POST;$d['items']=json_decode((string)($_POST['items']??''),true);$r=['com_id'=>$m->guardar($loc,$usu,$d),'mensaje'=>'Movimiento guardado. Ya puedes registrar pagos y documentos.'];break;
        case 5:$m->anular($id,$usu);$r=['mensaje'=>'Movimiento anulado.'];break;
        case 10:$m->pagar($usu,$_POST);$r=['mensaje'=>'Pago registrado correctamente.'];break;
        case 11:$m->anularPago($id,(int)($_POST['cop_id']??0));$r=['mensaje'=>'Pago anulado.'];break;
        case 13:$m->subir($id,$usu,$_FILES['documento']??[]);$r=['mensaje'=>'Documento adjuntado correctamente.'];break;
        case 14:$m->eliminarDocumento($id,(int)($_POST['cdo_id']??0));$r=['mensaje'=>'Documento retirado del movimiento.'];break;
        case 15:
            $d=$m->documento($id,(int)($_GET['cdo_id']??0));header('Content-Type: '.$d['mime']);header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');header('Content-Length: '.filesize($d['path']));$name=preg_replace('/[^a-zA-Z0-9._-]/','_',$d['cdo_nombre']);header('Content-Disposition: '.(!empty($_GET['descargar'])?'attachment':'inline').'; filename="'.$name.'"; filename*=UTF-8\'\''.rawurlencode($d['cdo_nombre']));readfile($d['path']);exit;
        default:throw new InvalidArgumentException('Opción no válida.');
    }echo json_encode(['estado'=>'ok']+$r,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(InvalidArgumentException $e){echo json_encode(['estado'=>'error','mensaje'=>$e->getMessage()]);}
catch(Throwable $e){error_log('movimiento: '.$e->getMessage());http_response_code(500);echo json_encode(['estado'=>'error','mensaje'=>'No se pudo completar la operación. Inténtalo nuevamente.']);}
