<?php

	require_once('../model/perfil.php');

	// Fredy Luis
	$obj = new perfil();
	$opcion = $_REQUEST['task'];

	switch($opcion)
	{
		case 1: // Listar

			$res = $obj -> listar();

			if ($res <> '0')
			{
				foreach ($res as $val)
				{
					$_arreglo[] = array('prf_id' => $val['prf_id'],
										'rol_id' => $val['rol_id'],
										'descripcion' => $val['descripcion']);
				}
			}else
				$_arreglo = '';

			echo json_encode($_arreglo);

			break;


		case 2: // Cargar datos

		 	break;


		case 3: //editar

			break;


		case 4: // registrar

			break;


		case 5: //

			$res = $obj -> listar_x_usu_id();

			foreach ($res as $val) {
				$_arreglo[] = array('id' => $val['id'],
									'nombre' => $val['descripcion']);
			}

			echo json_encode($_arreglo);

			break;
	}

?>