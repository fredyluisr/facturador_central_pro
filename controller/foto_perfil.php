<?php

require_once __DIR__ . '/../model/sesion.php';
require_once __DIR__ . '/../model/foto_perfil.php';

header('Content-Type: application/json; charset=utf-8');

$modelo = new foto_perfil();
$task = (int)($_REQUEST['task'] ?? 0);
$emp_id_sesion = (int)($_SESSION['emp_id_ifact'] ?? 0);
$directorio = realpath(__DIR__ . '/../img/trabajadores');

function responder_foto($estado, $mensaje, $extra = [], $codigo = 200)
{
    http_response_code($codigo);
    echo json_encode(array_merge([
        'estado' => $estado,
        'mensaje' => $mensaje
    ], $extra));
    exit;
}

function url_foto_empleado($archivo)
{
    if (!$archivo || !is_file(__DIR__ . '/../img/trabajadores/' . basename($archivo))) {
        return '../img/trabajadores/usuario-default.svg';
    }

    return '../img/trabajadores/' . rawurlencode(basename($archivo));
}

function eliminar_archivo_foto($directorio, $archivo)
{
    if (!$directorio || !$archivo || basename($archivo) === 'usuario.png') {
        return;
    }

    $ruta = $directorio . DIRECTORY_SEPARATOR . basename($archivo);
    if (is_file($ruta)) {
        unlink($ruta);
    }
}

if ($emp_id_sesion <= 0) {
    responder_foto(0, 'La sesión no tiene un empleado asociado.', [], 403);
}

if ($task === 1) {
    $empleado = $modelo->obtener_por_empleado($emp_id_sesion);
    if (!$empleado) {
        responder_foto(0, 'No se encontró el perfil.', [], 404);
    }

    responder_foto(1, 'Perfil cargado.', [
        'emp_id' => (int)$empleado['emp_id'],
        'nombre' => $empleado['nombre'],
        'foto' => url_foto_empleado($empleado['emp_foto'])
    ]);
}

if ($task === 2) {
    if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        responder_foto(0, 'Selecciona una imagen válida.');
    }

    if ((int)$_FILES['foto']['size'] > 10 * 1024 * 1024) {
        responder_foto(0, 'La imagen no puede superar los 10 MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($_FILES['foto']['tmp_name']);
    $permitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $dimensiones = getimagesize($_FILES['foto']['tmp_name']);
    if (!isset($permitidos[$mime]) || !$dimensiones) {
        responder_foto(0, 'Solo se permiten imágenes JPG, PNG o WEBP.');
    }
    if ($dimensiones[0] > 12000 || $dimensiones[1] > 12000) {
        responder_foto(0, 'La resolución de la imagen es demasiado grande.');
    }

    if (!$directorio || !is_writable($directorio)) {
        responder_foto(0, 'No se puede escribir en la carpeta de fotografías.', [], 500);
    }

    $anterior = $modelo->obtener_por_empleado($emp_id_sesion);
    $archivo = 'perfil_' . $emp_id_sesion . '_' . bin2hex(random_bytes(8)) . '.' . $permitidos[$mime];
    $destino = $directorio . DIRECTORY_SEPARATOR . $archivo;

    if (!move_uploaded_file($_FILES['foto']['tmp_name'], $destino)) {
        responder_foto(0, 'No se pudo guardar la fotografía.', [], 500);
    }

    if (!$modelo->actualizar($emp_id_sesion, $archivo)) {
        unlink($destino);
        responder_foto(0, 'No se pudo actualizar el perfil.', [], 500);
    }

    eliminar_archivo_foto($directorio, $anterior['emp_foto'] ?? null);
    $_SESSION['foto_perfil_ifact'] = $archivo;
    responder_foto(1, 'Fotografía actualizada correctamente.', [
        'foto' => url_foto_empleado($archivo)
    ]);
}

if ($task === 3 || $task === 4) {
    $emp_id = (int)($_REQUEST['emp_id'] ?? 0);
    $empleado = $modelo->obtener_por_empleado($emp_id);
    if (!$empleado) {
        responder_foto(0, 'No se encontró el empleado.', [], 404);
    }

    if ($task === 3) {
        responder_foto(1, 'Fotografía cargada.', [
            'emp_id' => (int)$empleado['emp_id'],
            'nombre' => $empleado['nombre'],
            'tiene_foto' => !empty($empleado['emp_foto']) && is_file(__DIR__ . '/../img/trabajadores/' . basename($empleado['emp_foto'])),
            'foto' => url_foto_empleado($empleado['emp_foto'])
        ]);
    }

    if (!$modelo->eliminar($emp_id)) {
        responder_foto(0, 'No se pudo eliminar la fotografía.', [], 500);
    }
    eliminar_archivo_foto($directorio, $empleado['emp_foto'] ?? null);

    if ($emp_id === $emp_id_sesion) {
        unset($_SESSION['foto_perfil_ifact']);
    }

    responder_foto(1, 'Fotografía eliminada correctamente.', [
        'foto' => '../img/trabajadores/usuario-default.svg'
    ]);
}

responder_foto(0, 'Operación no válida.', [], 400);
