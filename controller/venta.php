<?php
require_once __DIR__ . '/../model/sesion.php';
require_once('../model/venta_catalogo.php');

$obj    = new venta_catalogo();
$task   = isset($_REQUEST['task']) ? (int)$_REQUEST['task'] : 0;
$loc_id = isset($_SESSION['loc_id_ifact']) ? (int)$_SESSION['loc_id_ifact'] : 0;
$usu_id = isset($_SESSION['usu_id_ifact']) ? (int)$_SESSION['usu_id_ifact'] : 0;
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

header('Content-Type: application/json');

if ($loc_id <= 0 || $usu_id <= 0 || empty($_SESSION['rol_id_ifact'])) {
    http_response_code(401);
    echo json_encode(['estado'=>'error','mensaje'=>'Sesión no válida']);
    exit;
}
if (in_array($task, [6,7,12,13], true) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['estado'=>'error','mensaje'=>'Utilice POST']);
    exit;
}
if (in_array($task, [2,7,13], true)) {
    $db=(new conexion())->con();
    $s=$db->prepare('SELECT COUNT(*) FROM venta WHERE ven_id=? AND loc_id=?');
    $s->execute([(int)($_REQUEST['ven_id']??0), $loc_id]);
    if (!$s->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['estado'=>'error','mensaje'=>'Venta no encontrada']);
        exit;
    }
}
if ($task===12 && (empty($_SESSION['venta_pago_csrf']) || !hash_equals($_SESSION['venta_pago_csrf'],(string)($_POST['csrf']??'')))) {
    http_response_code(403);
    echo json_encode(['estado'=>'error','mensaje'=>'Actualiza la página antes de registrar el pago.']);
    exit;
}

switch ($task) {

    case 1: // Listar ventas de la tienda en sesión
        $ventas = $obj->listar($loc_id);
        foreach ($ventas as &$venta) {
            // El total mostrado debe reflejar siempre el detalle persistido.
            // Esto también cubre ventas antiguas y ambos tipos (insumo/producto).
            $venta['total_items'] = count($obj->ver_detalle((int)$venta['ven_id']));
        }
        unset($venta);
        echo json_encode($ventas);
        break;

    case 2: // Ver detalle de una venta
        $ven_id = (int)$_REQUEST['ven_id'];
        echo json_encode($obj->ver_detalle($ven_id));
        break;

    case 3: // Listar insumos con stock
        echo json_encode([]);
        break;

    case 4: // Stock de un insumo específico
        $ins_id = (int)$_REQUEST['ins_id'];
        echo json_encode(false);
        break;

    case 5: // Listar tipos de pago
        echo json_encode($obj->listar_tipos_pago());
        break;

    case 6: // Registrar venta
        try {
            $ent_id        = isset($_REQUEST['ent_id'])       ? (int)$_REQUEST['ent_id']        : null;
            $tde_id        = isset($_REQUEST['tde_id'])       ? (int)$_REQUEST['tde_id']        : 0;
            $tpa_id        = isset($_REQUEST['tpa_id'])       ? (int)$_REQUEST['tpa_id']        : null;
            $cba_id        = isset($_REQUEST['cba_id'])       ? (int)$_REQUEST['cba_id']        : null;
            $num_operacion = isset($_REQUEST['num_operacion']) ? trim($_REQUEST['num_operacion']) : '';
            $comentario    = isset($_REQUEST['comentario'])    ? trim($_REQUEST['comentario'])    : '';
            $ven_tipo      = isset($_REQUEST['ven_tipo'])      ? (int)$_REQUEST['ven_tipo']       : 2;
            $detalle       = json_decode($_REQUEST['detalle'], true) ?: [];

            if ($loc_id <= 0) { echo json_encode(['estado' => 'error', 'mensaje' => 'Tienda no válida en sesión']); break; }
            if (empty($detalle)) { echo json_encode(['estado' => 'error', 'mensaje' => 'Agregue al menos un ítem']); break; }

            $res = $obj->registrar($loc_id, $usu_id, $ent_id, $tpa_id, $num_operacion, $comentario, $detalle, $tde_id, $cba_id, $ven_tipo, $_POST['monto_pago'] ?? null);
            echo json_encode($res);

        } catch (Exception $e) {
            echo json_encode(['estado' => 'error', 'mensaje' => $e->getMessage()]);
        }
        break;

    case 7: // Anular venta
        $ven_id = (int)$_REQUEST['ven_id'];
        echo json_encode($obj->anular($ven_id, $usu_id));
        break;

    case 8: // Historial con filtro de fechas
        $desde = isset($_REQUEST['desde']) ? $_REQUEST['desde'] : null;
        $hasta = isset($_REQUEST['hasta']) ? $_REQUEST['hasta'] : null;
        echo json_encode($obj->historial($loc_id, $desde, $hasta));
        break;

    case 9: // Listar productos con stock de la tienda
        echo json_encode($obj->listar_productos($loc_id));
        break;

    case 10: // Stock de un producto específico en la tienda
        $pro_id = (int)($_REQUEST['pva_id'] ?? 0);
        echo json_encode($obj->obtener_stock_producto($pro_id, $loc_id));
        break;

    case 11:
        try { echo json_encode($obj->informacionPagos((int)($_GET['ven_id']??0),$loc_id)); }
        catch (InvalidArgumentException $e) { http_response_code(404); echo json_encode(['estado'=>'error','mensaje'=>$e->getMessage()]); }
        break;
    case 12:
        echo json_encode($obj->registrarPago((int)($_POST['ven_id']??0),$loc_id,$usu_id,(int)($_POST['tpa_id']??0),(int)($_POST['cba_id']??0),$_POST['monto']??null,trim((string)($_POST['num_operacion']??'')),(string)($_POST['clave']??'')));
        break;
    case 13:
        echo json_encode($obj->asignarComprobante((int)($_POST['ven_id']??0),$loc_id,(int)($_POST['ent_id']??0),(int)($_POST['tde_id']??0)));
        break;
    default:
        echo json_encode(['estado' => 'error', 'mensaje' => 'Tarea no definida']);
        break;
}
?>
