<?php

	require_once __DIR__ . '/../model/sesion.php';
	if (empty($_SESSION['usu_id_ifact']) || empty($_SESSION['rol_id_ifact'])) {
		http_response_code(403); exit;
	}
	header('Content-Type: application/json; charset=utf-8');
	require_once('../model/cargo.php');

	// Fredy Luis
	$obj = new cargo();
	$opcion = $_REQUEST['task'];

	switch($opcion)
	{
		case 1: // Listar

			$res = $obj -> listar();

			if ($res <> '0')
			{
				foreach ($res as $val)
				{
					$_arreglo[] = array('car_id' => $val['car_id'], 
										'car_nombre' => $val['car_nombre'],
										'car_estado' => $val['car_estado']);
				}
			}else
				$_arreglo = '';

			echo json_encode($_arreglo);

			break;


		case 2: // Cargar datos

			$res = $obj -> cargar_datos();

			foreach ($res as $val)
			{
				$_arreglo[] = array('car_id' => $val['car_id'], 
									'car_nombre' => $val['car_nombre'],
									'car_estado' => $val['car_estado']);
			}

			echo json_encode($_arreglo);

			break;


		case 3: //editar

			$res = $obj -> editar();
			echo $res;

			break;


		case 4: // registrar

			$res = $obj -> registrar();
			echo $res;

			break;


		case 5: // anular

			$res = $obj -> anular();
			echo $res;

			break;


		case 6: // verificar existencia

			$res = $obj-> verificar_existencia();

			if($res){
				$fila = $res->fetch(PDO::FETCH_ASSOC);
				echo $fila["resultado"];
			}else
				echo -1;
			
			break;


		case 7: // Listar habilitados

			$res = $obj -> listar_habilitados();
			echo json_encode($res);

			break;

	}

?>
