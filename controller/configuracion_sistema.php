<?php
require_once __DIR__ . '/../model/sesion.php';
require_once __DIR__ . '/../model/configuracion_sistema.php';
header('Content-Type: application/json; charset=utf-8');
if ((int)($_SESSION['rol_id_ifact'] ?? 0) !== 1) { http_response_code(403); echo json_encode(['ok'=>false,'mensaje'=>'Operación reservada para Webmaster'],JSON_UNESCAPED_UNICODE); exit; }
try {
    $modelo=new configuracion_sistema(); $task=(int)($_REQUEST['task']??1);
    if($task===1)$r=['ok'=>true,'datos'=>$modelo->obtenerLocal()];
    elseif($task===2)$r=$modelo->guardar();
    elseif($task===3)$r=$modelo->buscarEmpresas((string)($_GET['q']??''));
    elseif($task===4)$r=$modelo->cambiarEmpresa((int)($_POST['empresa_id']??0));
    elseif($task===5)$r=$modelo->guardarStock();
    elseif($task===6)$r=$modelo->guardarNombreComercial();
    elseif($task===7)$r=['ok'=>true,'central'=>$modelo->obtenerCentral()];
    elseif($task===8)$r=$modelo->guardarNotaPedido();
    elseif($task===9)$r=$modelo->guardarFacturacionElectronica();
    else {http_response_code(400);$r=['ok'=>false,'mensaje'=>'Operación no válida'];}
    echo json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch(Throwable $e) { http_response_code(500); echo json_encode(['ok'=>false,'mensaje'=>'No se pudo completar la operación de configuración'],JSON_UNESCAPED_UNICODE); }
