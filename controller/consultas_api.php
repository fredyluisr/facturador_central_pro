<?php
    require_once __DIR__ . '/../model/sesion.php';
    require_once('../model/var_global.php');

    if($_SESSION["rol_id_ifact"] <> 0){
        
    
        $task = isset($_REQUEST['task']) ? $_REQUEST['task'] : '';
        
        switch($task) {
            
            case 7: 
                if (isset($_GET["dni"]) && !empty($_GET["dni"])) {
                    $dni = trim($_GET["dni"]);
                    
                    
                    if (!preg_match('/^[0-9]{8}$/', $dni)) {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => false,
                            'error' => 'El DNI debe tener 8 dígitos numéricos',
                            'message' => 'Formato de DNI inválido'
                        ]);
                        exit();
                    }
                    
                    // Mismo servicio vigente utilizado por hristovlaser_dev_pro.
                    $url = "https://apiconsultadatos.intelsoftperu.com/api/v1/dni_hns/" . urlencode($dni);
                    
                    try {
                    
                        $ch = curl_init($url);
                        curl_setopt_array($ch, [
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_CONNECTTIMEOUT => 10,
                            CURLOPT_TIMEOUT => 20,
                            CURLOPT_SSL_VERIFYPEER => true,
                            CURLOPT_SSL_VERIFYHOST => 2,
                            CURLOPT_HTTPHEADER => ['Accept: application/json']
                        ]);
                        $caBundle = trim((string)conexion::config('CONSULTAS_CA_BUNDLE', ''));
                        if ($caBundle !== '') {
                            if (!is_file($caBundle) || !is_readable($caBundle)) {
                                throw new RuntimeException('CONSULTAS_CA_BUNDLE no existe o no se puede leer');
                            }
                            curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
                        }

                        $response = curl_exec($ch);
                        $curlError = curl_error($ch);
                        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        
                        if ($response === false || $curlError !== '' || $httpCode >= 400) {
                            header('Content-Type: application/json');
                            echo json_encode([
                                'success' => false,
                                'error' => 'No se pudo conectar con el servicio de consulta',
                                'message' => 'Error de conexión con la API de Reniec'
                            ]);
                            exit();
                        }
                        
                    
                        header('Content-Type: application/json');
                        echo $response;
                        
                    } catch (Exception $e) {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => false,
                            'error' => 'Error al procesar la consulta',
                            'message' => $e->getMessage()
                        ]);
                    }
                } else {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'error' => 'El parámetro DNI es requerido',
                        'message' => 'Debe proporcionar un número de DNI'
                    ]);
                }
                exit();
                
            case 8: // Consulta API RUC

                if (isset($_GET["ruc"]) && !empty($_GET["ruc"])) {
                    $ruc = trim($_GET["ruc"]);
                    
                    // Validar formato de RUC (11 dígitos)
                    if (!preg_match('/^[0-9]{11}$/', $ruc)) {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => false,
                            'error' => 'El RUC debe tener 11 dígitos numéricos',
                            'message' => 'Formato de RUC inválido'
                        ]);
                        exit();
                    }
                    
                    // Mismo servicio vigente utilizado por hristovlaser_dev_pro.
                    $url = "https://apiconsultadatos.intelsoftperu.com/api/v1/ruc/" . urlencode($ruc);
                    
                    try {
                    
                        $ch = curl_init($url);
                        curl_setopt_array($ch, [
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_CONNECTTIMEOUT => 10,
                            CURLOPT_TIMEOUT => 20,
                            CURLOPT_SSL_VERIFYPEER => true,
                            CURLOPT_SSL_VERIFYHOST => 2,
                            CURLOPT_HTTPHEADER => ['Accept: application/json']
                        ]);
                        $caBundle = trim((string)conexion::config('CONSULTAS_CA_BUNDLE', ''));
                        if ($caBundle !== '') {
                            if (!is_file($caBundle) || !is_readable($caBundle)) {
                                throw new RuntimeException('CONSULTAS_CA_BUNDLE no existe o no se puede leer');
                            }
                            curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
                        }

                        $response = curl_exec($ch);
                        $curlError = curl_error($ch);
                        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        
                        if ($response === false || $curlError !== '' || $httpCode >= 400) {
                            header('Content-Type: application/json');
                            echo json_encode([
                                'success' => false,
                                'error' => 'No se pudo conectar con el servicio de consulta',
                                'message' => 'Error de conexión con la API de SUNAT'
                            ]);
                            exit();
                        }
                        
                    
                        header('Content-Type: application/json');
                        echo $response;
                        
                    } catch (Exception $e) {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => false,
                            'error' => 'Error al procesar la consulta',
                            'message' => $e->getMessage()
                        ]);
                    }
                } else {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'error' => 'El parámetro RUC es requerido',
                        'message' => 'Debe proporcionar un número de RUC'
                    ]);
                }
                exit();
                
            default:
            
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Task no válido',
                    'message' => 'La operación solicitada no existe'
                ]);
                exit();
        }
        
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Acceso denegado',
            'message' => 'No tiene permisos para acceder a este recurso'
        ]);
    }

?>
