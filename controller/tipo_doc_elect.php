<?php
require_once __DIR__ . '/../model/sesion.php';
require_once __DIR__ . '/../model/comprobantes_pos.php';
header('Content-Type: application/json; charset=utf-8');
if(empty($_SESSION['rol_id_ifact'])){http_response_code(401);echo json_encode(['estado'=>'error','mensaje'=>'Sesión no válida']);exit;}
if((int)($_REQUEST['task']??0)===8){echo json_encode(comprobantes_pos::listar());exit;}
http_response_code(410);
echo json_encode(['estado'=>'error','mensaje'=>'Los comprobantes se administran en la central.']);
