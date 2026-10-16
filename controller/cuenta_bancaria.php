<?php
require_once __DIR__ . '/../model/sesion.php';
if ((int)($_SESSION['rol_id_ifact'] ?? 0) < 1) { http_response_code(403); exit; }
require_once('../model/cuenta_bancaria.php');

$obj    = new cuenta_bancaria();
$action = $_POST['action'] ?? $_GET['action'] ?? '';
header('Content-Type: application/json');

try {
    switch ($action) {

        case 1: // Listar
            echo json_encode(['status' => 1, 'data' => $obj->listar($_REQUEST['tpa_id'] ?? null)]);
            break;

        case 2: // Obtener por ID
            $id  = $_POST['cba_id'] ?? 0;
            $res = $obj->obtener_por_id($id);
            echo json_encode($res ? ['status' => 1, 'data' => $res] : ['status' => 0, 'message' => 'No encontrado']);
            break;

        case 3: // Registrar
            $ban_id      = (int)($_POST['ban_id']         ?? 0);
            $moneda      = trim($_POST['cba_moneda']      ?? '');
            $propietario = trim($_POST['cba_propietario'] ?? '');
            $numero      = trim($_POST['cba_numero']      ?? '');
            $estado      = $_POST['cba_estado'] ?? 1;
            $tpa_id      = $_POST['tpa_id'] ?? null;

            if (!$ban_id || !$moneda || !$propietario || !$numero) {
                echo json_encode(['status' => 0, 'message' => 'Todos los campos son obligatorios']);
                break;
            }
            echo json_encode($obj->registrar($ban_id, $moneda, $propietario, $numero, $estado, $tpa_id));
            break;

        case 4: // Editar
            $id          = $_POST['cba_id']          ?? 0;
            $ban_id      = (int)($_POST['ban_id']         ?? 0);
            $moneda      = trim($_POST['cba_moneda']      ?? '');
            $propietario = trim($_POST['cba_propietario'] ?? '');
            $numero      = trim($_POST['cba_numero']      ?? '');
            $estado      = $_POST['cba_estado'] ?? 1;
            $tpa_id      = $_POST['tpa_id'] ?? null;

            if (!$id || !$ban_id || !$moneda || !$propietario || !$numero) {
                echo json_encode(['status' => 0, 'message' => 'Todos los campos son obligatorios']);
                break;
            }
            echo json_encode($obj->editar($id, $ban_id, $moneda, $propietario, $numero, $estado, $tpa_id));
            break;

        case 5: // Eliminar
            $id = $_POST['cba_id'] ?? 0;
            if (!$id) { echo json_encode(['status' => 0, 'message' => 'ID no válido']); break; }
            echo json_encode($obj->eliminar($id));
            break;

        case 6: // Catálogo de bancos
            echo json_encode(['status' => 1, 'data' => $obj->listar_bancos()]);
            break;

        default:
            echo json_encode(['status' => 0, 'message' => 'Acción no reconocida']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['status' => 0, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
