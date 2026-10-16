<?php

	require_once __DIR__ . '/../model/sesion.php';
	if (empty($_SESSION['usu_id_ifact']) || empty($_SESSION['rol_id_ifact'])) {
		http_response_code(403); exit;
	}
	header('Content-Type: application/json; charset=utf-8');
	require_once('../model/empleado.php');
	// El formulario usa DD-MM-YYYY; MySQL requiere YYYY-MM-DD y NULL en opcionales vacíos.
	if (in_array((int)($_REQUEST['task'] ?? 0), [3,4], true)) {
		foreach (['emp_fec_nac'] as $campo) {
			$valor = trim((string)($_REQUEST[$campo] ?? ''));
			if ($valor === '' || $valor === '0000-00-00') { $_REQUEST[$campo] = null; continue; }
			$fecha = DateTimeImmutable::createFromFormat('!d-m-Y', $valor);
			if (!$fecha || $fecha->format('d-m-Y') !== $valor) {
				$fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
				if (!$fecha || $fecha->format('Y-m-d') !== $valor) { http_response_code(422); echo '0'; exit; }
			}
			$_REQUEST[$campo] = $fecha->format('Y-m-d');
		}
		foreach (['emp_sexo','emp_est_civil'] as $campo) {
			if (trim((string)($_REQUEST[$campo] ?? '')) === '') $_REQUEST[$campo] = null;
		}
	}

	$obj = new empleado();
	$opcion = $_REQUEST['task'];

	switch($opcion)
	{
		case 1: // Listar

			$res = $obj->listar();

			if ($res != '0')
			{
				foreach ($res as $val)
				{
					$_arreglo[] = array(
						'emp_id' 		=> $val['emp_id'], 
						'emp_tipo_doc' 	=> $val['emp_tipo_doc'],
						'emp_nro_doc' 	=> $val['emp_nro_doc'],
						'emp_ape_paterno' => $val['emp_ape_paterno'],
						'emp_ape_materno' => $val['emp_ape_materno'],
						'emp_nombres' 	=> $val['emp_nombres'],
						'emp_alias' 	=> $val['emp_alias'],
						'car_nombre' 	=> $val['car_nombre'],
						'emp_estado' 	=> $val['emp_estado']
					);
				}
			}else
				$_arreglo = [];

			echo json_encode($_arreglo);

			break;


		case 2: // Cargar datos

			$res = $obj->cargar_datos();

			if($res && $res != '0')
			{
				foreach ($res as $val)
				{
					$_arreglo[] = array(
						'emp_id' 				=> $val['emp_id'], 
						'emp_tipo_doc' 			=> $val['emp_tipo_doc'],
						'emp_nro_doc' 			=> $val['emp_nro_doc'],
						'emp_ape_paterno' 		=> $val['emp_ape_paterno'],
						'emp_ape_materno' 		=> $val['emp_ape_materno'],
						'emp_nombres' 			=> $val['emp_nombres'],
						'emp_alias' 			=> $val['emp_alias'],
						'emp_fec_nac' 			=> $val['emp_fec_nac'],
						'emp_sexo' 				=> $val['emp_sexo'],
						'emp_est_civil' 		=> $val['emp_est_civil'],
						'emp_direccion' 		=> $val['emp_direccion'],
						'emp_email' 			=> $val['emp_email'],
						'emp_movil' 			=> $val['emp_movil'],
						'car_id' 				=> $val['car_id'],
						'emp_estado' 			=> $val['emp_estado']
					);
				}
				echo json_encode($_arreglo);
			}else
				echo json_encode([]);

			break;


		case 3: // Editar

			$res = $obj->editar();
			echo $res;

			break;


		case 4: // Registrar

			$res = $obj->registrar();
			echo $res;

			break;


		case 5: // Anular

			$res = $obj->anular();
			echo $res;

			break;


		case 6: // Verificar existencia

			$res = $obj->verificar_existencia();

			if($res){
				$fila = $res->fetch(PDO::FETCH_ASSOC);
				echo $fila["resultado"];
			}else
				echo -1;

			break;


		case 7: // Consulta API Reniec

			$dni = $_GET["dni"];

			$url = "https://intelsoftperu.com/ruc/api/datos_perudevs_dni_hns.php?dni=".$dni;

			$ch = curl_init();

			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
			curl_setopt($ch, CURLOPT_TIMEOUT, 20);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
			$caBundle = trim((string)conexion::config('CONSULTAS_CA_BUNDLE', ''));
			if ($caBundle !== '') {
				if (!is_file($caBundle) || !is_readable($caBundle)) {
					curl_close($ch);
					echo json_encode(['error' => 'El certificado CA configurado no está disponible']);
					exit();
				}
				curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
			}

			$response = curl_exec($ch);

			if(curl_errno($ch)){
				echo json_encode([
					"error" => curl_error($ch)
				]);
			} else {
				echo $response;
			}

			curl_close($ch);

			exit();


		case 8: // Listar Habilitados

			$res = $obj -> listar_habilitados();

			foreach ($res as $val)
			{
				$_arreglo[] = array('id' => $val['id'], 
									'descripcion' => $val['descripcion']);
			}

			echo json_encode($_arreglo);

			break;
	}

?>
