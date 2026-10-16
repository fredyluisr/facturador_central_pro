<?php
require_once __DIR__ . '/../model/catalogo_acceso.php';
require_once __DIR__ . '/../model/catalogo.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (!catalogoSesionActiva()) {
    http_response_code(401);
    echo json_encode(['status'=>0,'message'=>'Su sesión venció o no ha iniciado sesión. Inicie sesión nuevamente.']);
    exit;
}
$tipo=(string)($_POST['tipo']??''); $action=(string)($_POST['action']??'');
$rutas=['categoria'=>'list_categorias.php','unidad'=>'list_unidad_medida.php',
    'atributo'=>'list_variantes.php','valor'=>'list_variantes.php','producto'=>'list_producto.php'];
if (!isset($rutas[$tipo]) || !catalogoPuedeAcceder($rutas[$tipo])) {
    http_response_code(403); echo json_encode(['status'=>0,'message'=>'No tiene acceso a este catálogo.']); exit;
}
if (!hash_equals($_SESSION['catalogo_csrf'],(string)($_POST['csrf']??''))) {
    http_response_code(403); echo json_encode(['status'=>0,'message'=>'La sesión del formulario expiró. Recargue la página.']); exit;
}
$obj=new catalogo(null,(int)$_SESSION['usu_id_ifact']);
try {
    $id=(int)($_POST['id']??0);
    switch ($action) {
        case 'listar': $result=$tipo==='producto' ? $obj->productos() : $obj->listar($tipo,(int)($_POST['padre']??0)); break;
        case 'obtener': $result=$tipo==='producto' ? $obj->producto($id) : $obj->obtener($tipo,$id); break;
        case 'opciones': $result=$obj->opciones(); break;
        case 'guardar':
            if ($tipo==='producto') {
                $data=json_decode((string)($_POST['datos']??''),true,512,JSON_THROW_ON_ERROR);
                if (!is_array($data)) throw new InvalidArgumentException('Datos de producto no válidos.');
                $result=$obj->guardarProducto($data);
            } else $result=$obj->guardar($tipo,$_POST);
            echo json_encode(['status'=>1,'data'=>$result,'message'=>'Registro guardado correctamente.']); exit;
        case 'subir_imagen_variante':
            if ($tipo!=='producto') throw new InvalidArgumentException('Acción no válida.');
            $ruta=$obj->guardarImagenVariante($id,$_FILES['imagen']??[]);
            echo json_encode(['status'=>1,'data'=>['ruta'=>$ruta],'message'=>'Imagen guardada correctamente.']); exit;
        case 'eliminar_imagen_variante':
            if ($tipo!=='producto') throw new InvalidArgumentException('Acción no válida.');
            $obj->eliminarImagenVariante($id);
            echo json_encode(['status'=>1,'message'=>'Imagen eliminada correctamente.']); exit;
        case 'subir_imagen_producto':
            if ($tipo!=='producto') throw new InvalidArgumentException('Acción no válida.');
            $ruta=$obj->guardarImagenProducto($id,$_FILES['imagen']??[]);
            echo json_encode(['status'=>1,'data'=>['ruta'=>$ruta],'message'=>'Foto guardada correctamente.']); exit;
        case 'eliminar_imagen_producto':
            if ($tipo!=='producto') throw new InvalidArgumentException('Acción no válida.');
            $obj->eliminarImagenProducto($id);
            echo json_encode(['status'=>1,'message'=>'Foto eliminada correctamente.']); exit;
        case 'eliminar':
            if ($tipo==='producto') $obj->eliminarProducto($id); else $obj->eliminar($tipo,$id);
            echo json_encode(['status'=>1,'message'=>'Registro retirado del catálogo.']); exit;
        default: throw new InvalidArgumentException('Acción no válida.');
    }
    echo json_encode(['status'=>1,'data'=>$result],JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException|JsonException $e) {
    echo json_encode(['status'=>0,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('Catálogo: '.$e->getMessage());
    echo json_encode(['status'=>0,'message'=>$e->getCode()==='23000' ? 'Hay un código, nombre o relación duplicados. Revise los datos.' : 'No se pudo guardar o consultar el catálogo. Intente nuevamente.']);
} catch (Throwable $e) {
    error_log('Catálogo: '.$e->getMessage());
    echo json_encode(['status'=>0,'message'=>'No se pudo completar la operación.']);
}
