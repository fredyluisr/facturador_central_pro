<?php
require_once __DIR__ . '/../model/sesion.php';
require_once('../model/entidad_cliente.php');

header('Content-Type: application/json; charset=utf-8');
$obj = new entidad_cliente();
$task = (int)($_REQUEST['task'] ?? 0);
$usuarioId = (int)($_SESSION['usu_id_ifact'] ?? 0);

$data = [
    'ent_tipo_doc' => $_REQUEST['ent_tipo_doc'] ?? 1,
    'ent_nro_doc' => $_REQUEST['ent_nro_doc'] ?? '',
    'ent_nombres' => $_REQUEST['ent_nombres'] ?? '',
    'ent_contacto' => $_REQUEST['ent_contacto'] ?? '',
    'ent_direccion' => $_REQUEST['ent_direccion'] ?? '',
    'ent_email' => $_REQUEST['ent_email'] ?? '',
    'ent_marca' => $_REQUEST['ent_marca'] ?? ''
];

$marcaValida = mb_strlen(trim((string)$data['ent_marca']), 'UTF-8') >= 3;
$registroRapido = (int)($_REQUEST['registro_rapido'] ?? 0) === 1;
$tipoDocumento = (int)$data['ent_tipo_doc'];
$numeroDocumento = trim((string)$data['ent_nro_doc']);
$documentoValido = ($tipoDocumento === 1 && preg_match('/^\d{8}$/', $numeroDocumento))
    || ($tipoDocumento === 6 && preg_match('/^\d{11}$/', $numeroDocumento));
$direccionValida = trim((string)$data['ent_direccion']) !== '';

switch ($task) {
    case 1: echo json_encode($obj->listar()); break;
    case 2: echo json_encode($obj->listar(true)); break;
    case 3: echo json_encode($obj->obtener((int)($_REQUEST['ent_id'] ?? 0))); break;
    case 4:
        echo json_encode($registroRapido && !$documentoValido
            ? ['estado'=>'error', 'mensaje'=>'Ingrese un DNI de 8 dígitos o un RUC de 11 dígitos']
            : (trim((string)$data['ent_nombres']) === ''
            ? ['estado'=>'error', 'mensaje'=>'El nombre es obligatorio']
            : ($registroRapido && !$direccionValida
                ? ['estado'=>'error', 'mensaje'=>'La dirección es obligatoria']
                : (!$marcaValida
                ? ['estado'=>'error', 'mensaje'=>'La marca es obligatoria y debe tener al menos 3 caracteres']
                : $obj->guardar($usuarioId, $data)))));
        break;
    case 5:
        echo json_encode(trim((string)$data['ent_nombres']) === ''
            ? ['estado'=>'error', 'mensaje'=>'El nombre es obligatorio']
            : (!$marcaValida
                ? ['estado'=>'error', 'mensaje'=>'La marca es obligatoria y debe tener al menos 3 caracteres']
                : $obj->editar((int)($_REQUEST['ent_id'] ?? 0), $usuarioId, $data)));
        break;
    case 6: echo json_encode($obj->cambiarEstado((int)($_REQUEST['ent_id'] ?? 0), 0, $usuarioId)); break;
    case 7: echo json_encode($obj->cambiarEstado((int)($_REQUEST['ent_id'] ?? 0), 1, $usuarioId)); break;
    default: echo json_encode(['estado'=>'error', 'mensaje'=>'Tarea no definida']);
}
