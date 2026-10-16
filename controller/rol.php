<?php
	require_once __DIR__ . '/../model/sesion.php';

	require_once('../model/rol.php');

	// Fredy Luis
	$obj = new rol();
	$opcion = isset($_REQUEST['task']) ? (int)$_REQUEST['task'] : 0;
	$rolSesion = (int)($_SESSION['rol_id_ifact'] ?? 0);
	$rolObjetivo = (int)($_REQUEST['rol_id'] ?? 0);
	if ($rolSesion <= 0 || ($rolObjetivo === 1 && $rolSesion !== 1)) {
		http_response_code(403);
		echo json_encode(['error' => 'No autorizado para administrar el rol webmaster']);
		exit;
	}

	switch($opcion)
	{
		case 1: // Listar

			$res = $obj -> listar();

			if ($res <> '0')
			{
				foreach ($res as $val)
				{
					$_arreglo[] = array('id' => $val['rol_id'], 
										'nombre' => $val['rol_nombre'],
										'descripcion' => $val['rol_descripcion'],
										'estado' => $val['rol_estado']);
				}
			}else
				$_arreglo = '';

			echo json_encode($_arreglo);

			break;


		case 2: // Cargar datos

			$res = $obj -> cargar_datos();

			foreach ($res as $val)
			{
				$_arreglo[] = array('rol_id' => $val['rol_id'], 
									'rol_nombre' => $val['rol_nombre'],
									'rol_estado' => $val['rol_estado'],
									'rol_descripcion' => $val['rol_descripcion']);
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


		case 5: // eliminar

			$res = $obj -> eliminar();
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


		case 7:

			$res = $obj -> listar_habilitados();

			foreach ($res as $val)
			{
				$_arreglo[] = array('id' => $val['id'], 
									'descripcion' => $val['descripcion']);
			}

			echo json_encode($_arreglo);

			break;


		case 8: // Guardar Permisos x Rol

			$res = $obj -> editar_permisosxrol();

			if($res) echo  "1";
			else echo "0";

			break;
	}

?>
