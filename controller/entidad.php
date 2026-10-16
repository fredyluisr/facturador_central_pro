<?php

    require_once('../model/sesion.php');
    require_once('../model/entidad.php');

    $obj = new entidad();
	$opcion = $_REQUEST['task'];
    header('Content-Type: application/json');
    if ((int)($_SESSION['rol_id_ifact'] ?? 0) < 1) {
        http_response_code(403);
        echo json_encode(['status'=>0,'message'=>'Debe iniciar sesión']);
        exit;
    }

    try {

        switch ($opcion) {
            
            // =========================
            // LISTAR TODOS LOS REGISTROS
            // =========================
            case 1:
                $resultado = $obj->listar();
                echo json_encode([
                    'status' => 1,
                    'data' => $resultado
                ]);

                break;

                
            // =========================
            // LISTAR SOLO CLIENTES
            // =========================
            case 2: // Listar habilitados para select

                $res = $obj->listar_clientes();
                echo json_encode($res, JSON_UNESCAPED_UNICODE);
                
                break;

            // case 2:
            //     $resultado = $obj->listar_clientes();
            //     echo json_encode([
            //         'status' => 1,
            //         'data' => $resultado
            //     ]);

            //     break;
                

            // =========================
            // LISTAR SOLO PROVEEDORES
            // =========================
            case 3:
                $resultado = $obj->listar_proveedores();
                echo json_encode([
                    'status' => 1,
                    'data' => $resultado
                ]);

                break;
                

            // =========================
            // OBTENER REGISTRO POR ID
            // =========================
            case 4:
                $ent_id = $_POST['ent_id'] ?? 0;
                
                if (empty($ent_id) || !is_numeric($ent_id)) {
                    echo json_encode([
                        'status' => 0,
                        'message' => 'ID no válido'
                    ]);
                    break;
                }
                
                $resultado = $obj->obtener_por_id($ent_id);
                
                if ($resultado) {
                    echo json_encode([
                        'status' => 1,
                        'data' => $resultado
                    ]);
                } else {
                    echo json_encode([
                        'status' => 0,
                        'message' => 'Entidad no encontrada'
                    ]);
                }
                break;

                
            // =========================
            // REGISTRAR NUEVA ENTIDAD
            // =========================
            case 5:

                // Validar campos requeridos
                $ent_tipo_doc = $_POST['ent_tipo_doc'] ?? '';
                $ent_nro_doc = trim($_POST['ent_nro_doc'] ?? '');
                $ent_nombres = trim($_POST['ent_nombres'] ?? '');
                $ent_direccion = trim($_POST['ent_direccion'] ?? '');
                $ent_marca = trim($_POST['ent_marca'] ?? '');
                $ent_email = trim($_POST['ent_email'] ?? '');
                $ent_contacto = trim($_POST['ent_contacto'] ?? '');
                $ent_cliente = $_POST['ent_cliente'] ?? 0;
                $ent_proveedor = $_POST['ent_proveedor'] ?? 0;
                $ent_estado = $_POST['ent_estado'] ?? 1;
                
                // Validaciones
                if (empty($ent_tipo_doc) || !in_array($ent_tipo_doc, [0, 1, 4, 6, 7])) {
                    echo json_encode([
                        'status' => 0,
                        'message' => 'Tipo de documento no válido'
                    ]);
                    break;
                }
                
                if (empty($ent_nro_doc)) {
                    echo json_encode([
                        'status' => 0,
                        'message' => 'El número de documento es obligatorio'
                    ]);
                    break;
                }
                
                if (strlen($ent_nro_doc) > 15) {
                    echo json_encode([
                        'status' => 0,
                        'message' => 'El número de documento no puede exceder 15 caracteres'
                    ]);
                    break;
                }
                
                if (empty($ent_nombres)) {
                    echo json_encode([
                        'status' => 0,
                        'message' => 'El nombre o razón social es obligatorio'
                    ]);
                    break;
                }
                
                if (strlen($ent_nombres) > 200) {
                    echo json_encode([
                        'status' => 0,
                        'message' => 'El nombre no puede exceder 200 caracteres'
                    ]);
                    break;
                }
                
                if (strlen($ent_direccion) > 300) {
                    echo json_encode([
                        'status' => 0,
                        'message' => 'La dirección no puede exceder 300 caracteres'
                    ]);
                    break;
                }
                if (strlen($ent_marca) > 150) {
                    echo json_encode([
                        'status' => 0,
                        'message' => 'La marca no puede exceder 150 caracteres'
                    ]);
                    break;
                }
                
                if (!empty($ent_email) && strlen($ent_email) > 100) {
                    echo json_encode([
                        'status' => 0,
                        'message' => 'El email no puede exceder 100 caracteres'
                    ]);
                    break;
                }

                if (!empty($ent_email) && !filter_var($ent_email, FILTER_VALIDATE_EMAIL)) {
                    echo json_encode([
                        'status' => 0,
                        'message' => 'El email no es válido'
                    ]);
                    break;
                }
                
                if (strlen($ent_contacto) > 25) {
                    echo json_encode([
                        'status' => 0,
                        'message' => 'El contacto no puede exceder 25 caracteres'
                    ]);
                    break;
                }
                
                if (!in_array($ent_cliente, [0, 1])) {
                    echo json_encode([
                        'status' => 0,
                        'message' => 'Valor de cliente no válido'
                    ]);
                    break;
                }
                
                if (!in_array($ent_proveedor, [0, 1])) {
                    echo json_encode([
                        'status' => 0,
                        'message' => 'Valor de proveedor no válido'
                    ]);
                    break;
                }
                
                if (!in_array($ent_estado, [0, 1, 2])) {
                    echo json_encode([
                        'status' => 0,
                        'message' => 'Estado no válido'
                    ]);
                    break;
                }
                
                // Validar que sea cliente o proveedor (o ambos)
                if ($ent_cliente == 0 && $ent_proveedor == 0) {
                    echo json_encode([
                        'status' => 0,
                        'message' => 'La entidad debe ser cliente, proveedor o ambos'
                    ]);
                    break;
                }
                
                // Registrar
                $resultado = $obj->registrar(
                    $ent_tipo_doc, 
                    $ent_nro_doc, 
                    $ent_nombres, 
                    $ent_direccion,
                    $ent_marca,
                    $ent_email, 
                    $ent_contacto, 
                    $ent_cliente, 
                    $ent_proveedor, 
                    $ent_estado
                );
                echo json_encode($resultado);
                break;

                
            // =========================
            // EDITAR ENTIDAD
            // =========================
            case 6:

                // Validar campos
                $ent_id = $_POST['ent_id'] ?? 0;
                $ent_tipo_doc = $_POST['ent_tipo_doc'] ?? '';
                $ent_nro_doc = trim($_POST['ent_nro_doc'] ?? '');
                $ent_nombres = trim($_POST['ent_nombres'] ?? '');
                $ent_direccion = trim($_POST['ent_direccion'] ?? '');
                $ent_marca = trim($_POST['ent_marca'] ?? '');
                $ent_email = trim($_POST['ent_email'] ?? '');
                $ent_contacto = trim($_POST['ent_contacto'] ?? '');
                $ent_cliente = $_POST['ent_cliente'] ?? 0;
                $ent_proveedor = $_POST['ent_proveedor'] ?? 0;
                $ent_estado = $_POST['ent_estado'] ?? 1;
                
                // Validaciones
                if (empty($ent_id) || !is_numeric($ent_id)) {
                    echo json_encode([
                        'status' => 0,
                        'message' => 'ID no válido'
                    ]);
                    break;
                }
                
                if (empty($ent_tipo_doc) || !in_array($ent_tipo_doc, [0, 1, 4, 6, 7])) {
                    echo json_encode([
                        'status' => 0,
                        'message' => 'Tipo de documento no válido'
                    ]);
                    break;
                }
                
                if (empty($ent_nro_doc)) {
                    echo json_encode([
                        'status' => 0,
                        'message' => 'El número de documento es obligatorio'
                    ]);
                    break;
                }
                
                if (empty($ent_nombres)) {
                    echo json_encode([
                        'status' => 0,
                        'message' => 'El nombre o razón social es obligatorio'
                    ]);
                    break;
                }
                
                if (strlen($ent_marca) > 150) {
                    echo json_encode([
                        'status' => 0,
                        'message' => 'La marca no puede exceder 150 caracteres'
                    ]);
                    break;
                }

                if (!empty($ent_email) && !filter_var($ent_email, FILTER_VALIDATE_EMAIL)) {
                    echo json_encode([
                        'status' => 0,
                        'message' => 'El email no es válido'
                    ]);
                    break;
                }
                
                if ($ent_cliente == 0 && $ent_proveedor == 0) {
                    echo json_encode([
                        'status' => 0,
                        'message' => 'La entidad debe ser cliente, proveedor o ambos'
                    ]);
                    break;
                }
                
                // Editar
                $resultado = $obj->editar(
                    $ent_id, 
                    $ent_tipo_doc, 
                    $ent_nro_doc, 
                    $ent_nombres, 
                    $ent_direccion,
                    $ent_marca,
                    $ent_email, 
                    $ent_contacto, 
                    $ent_cliente, 
                    $ent_proveedor, 
                    $ent_estado
                );
                echo json_encode($resultado);
                break;

                
            // =========================
            // ELIMINAR (ANULAR) ENTIDAD
            // =========================
            case 7:

                $ent_id = $_POST['ent_id'] ?? 0;
                
                if (empty($ent_id) || !is_numeric($ent_id)) {
                    echo json_encode([
                        'status' => 0,
                        'message' => 'ID no válido'
                    ]);
                    break;
                }
                
                $resultado = $obj->eliminar($ent_id);
                echo json_encode($resultado);
                break;

                
            // =========================
            // ACCIÓN NO RECONOCIDA
            // =========================
            default:
                echo json_encode([
                    'status' => 0,
                    'message' => 'Acción no reconocida'
                ]);
                break;
        }
        
    } catch (Exception $e) {
        // Capturar cualquier error no controlado
        echo json_encode([
            'status' => 0,
            'message' => 'Error en el servidor: ' . $e->getMessage()
        ]);
    }

?>
