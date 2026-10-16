<?php

	require_once('../model/usuario.php');
	require_once("../model/obtener_tipo_dispositivo.php");
	require_once("../model/var_global.php");

	$obj = new usuario();
	$opcion = $_REQUEST['task'];

	switch($opcion)
	{
		case 1: // Listar

			$res = $obj -> listar();

			if ($res <> '0')
			{
				foreach ($res as $val)
				{
					$_arreglo[] = array('id' => $val['id'],
										'nombre' => $val['nombre'],
										'token' => $val['token'],
										'estado' => $val['estado']);
				}
			}else
				$_arreglo = '';

			echo json_encode($_arreglo);

			break;


		case 2: // Cargar datos

			$res = $obj->cargar_datos();

			foreach ($res as $val)
			{
				$_arreglo[] = array(
					'usu_id' => $val['usu_id'],
					'emp_id' => $val['emp_id'],
					'usu_estado' => $val['usu_estado'],
					'usu_token' => $val['usu_token'],
					'emp_id' => $val['emp_id']
				);
			}

			echo json_encode($_arreglo);

			break;


		case 3: // editar

			$res = $obj->editar();
			echo $res;

			break;


		case 4: // registrar

			$res = $obj -> registrar($tipo_dispositivo);
			echo $res;

			break;


		case 5: // eliminar

			$res = $obj -> eliminar();
			echo $res;

			break;


		case 6: // actualizar password

			$res = $obj -> generar_token();			
			echo $res;

			break;


		case 7: // Verificación existencia DNI

			$res = $obj-> verificar_existencia_documento();

			if($res){
				$fila = $res->fetch(PDO::FETCH_ASSOC);
				echo $fila["resultado"];
			}else
				echo -1;

			break;


	}

?>