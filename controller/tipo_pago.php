<?php
if (session_status() === PHP_SESSION_NONE) require_once __DIR__ . '/../model/sesion.php';
if ((int)($_SESSION['rol_id_ifact'] ?? 0) < 1) { http_response_code(403); exit; }
require_once __DIR__ . '/../model/sesion.php';
require_once('../model/tipo_pago.php');

$obj = new tipo_pago();
$action = $_POST['action'] ?? $_GET['action'] ?? $_POST['task'] ?? $_GET['task'] ?? '';
header('Content-Type: application/json');

try {
    switch ($action) {

        case 1: // Listar
            $res = $obj->listar();
            echo json_encode(['status' => 1, 'data' => $res]);
            break;

        case 2: // Obtener por ID
            $tpa_id = $_POST['tpa_id'] ?? 0;
            $res = $obj->obtener_por_id($tpa_id);
            echo json_encode($res ? ['status' => 1, 'data' => $res] : ['status' => 0, 'message' => 'No encontrado']);
            break;

        case 3: // Registrar
            $nombre = trim($_POST['tpa_nombre'] ?? '');
            $estado = $_POST['tpa_estado'] ?? 1;
            if (empty($nombre)) {
                echo json_encode(['status' => 0, 'message' => 'El nombre es obligatorio']);
                break;
            }
            echo json_encode($obj->registrar($nombre, $estado));
            break;

        case 4: // Editar
            $tpa_id = $_POST['tpa_id'] ?? 0;
            $nombre = trim($_POST['tpa_nombre'] ?? '');
            $estado = $_POST['tpa_estado'] ?? 1;
            if (empty($tpa_id) || empty($nombre)) {
                echo json_encode(['status' => 0, 'message' => 'Datos incompletos']);
                break;
            }
            echo json_encode($obj->editar($tpa_id, $nombre, $estado));
            break;

        case 5: // Eliminar
            $tpa_id = $_POST['tpa_id'] ?? 0;
            if (empty($tpa_id)) {
                echo json_encode(['status' => 0, 'message' => 'ID no válido']);
                break;
            }
            echo json_encode($obj->eliminar($tpa_id));
            break;


        case 7: // Listar habilitados
        
            $res      = $obj->listar_habilitados();
            $_arreglo = [];

            if($res && $res != '0'){
                foreach($res as $val){
                    $_arreglo[] = array(
                        'id'          => $val['id'],
                        'descripcion' => $val['descripcion']
                    );
                }
            }

            echo json_encode($_arreglo);
            break;


        default:
            echo json_encode(['status' => 0, 'message' => 'Acción no reconocida']);
            break;
    }

} catch (Exception $e) {
    echo json_encode(['status' => 0, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
