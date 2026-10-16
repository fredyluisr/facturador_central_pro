<?php
require_once __DIR__.'/../model/catalogo_acceso.php';
require_once __DIR__.'/../model/reporte_movimientos.php';
header('Content-Type: application/json; charset=utf-8');
try{
    if(!catalogoPuedeAcceder('list_reporte_movimientos.php')){http_response_code(403);throw new RuntimeException('No tienes acceso a este reporte.');}
    $loc=(int)($_SESSION['loc_id_ifact']??0);if($loc<=0)throw new InvalidArgumentException('Selecciona nuevamente el local.');
    session_write_close();
    $r=(new reporte_movimientos())->obtener($loc,(string)($_GET['inicio']??date('Y-m-01')),(string)($_GET['fin']??date('Y-m-d')),(string)($_GET['tipo']??''),(string)($_GET['estado']??''));
    echo json_encode($r,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(Throwable $e){if(http_response_code()===200)http_response_code($e instanceof InvalidArgumentException?422:500);echo json_encode(['estado'=>'error','mensaje'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}
