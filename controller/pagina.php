<?php
	require_once __DIR__ . '/../model/sesion.php';

	require_once('../model/pagina.php');

	// Fredy Luis
	$obj = new pagina();
	$opcion = isset($_REQUEST['task']) ? (int)$_REQUEST['task'] : 0;
	if ((int)($_SESSION['rol_id_ifact'] ?? 0) !== 1) {
		http_response_code(403); echo json_encode(['error'=>'Acceso reservado al webmaster']); exit;
	}
	if (in_array($opcion, [3,4], true)) {
		$_REQUEST['pag_nombre'] = trim((string)($_REQUEST['pag_nombre'] ?? ''));
		if ($_REQUEST['pag_nombre'] === '' || mb_strlen($_REQUEST['pag_nombre']) > 40
			|| !preg_match('/^[a-zA-Z0-9_ -]{1,50}$/', (string)($_REQUEST['pag_icono'] ?? ''))
			|| !preg_match('/^[a-zA-Z0-9_.\/-]{1,50}$/', (string)($_REQUEST['pag_ruta'] ?? ''))
			|| filter_var($_REQUEST['men_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) === false
			|| filter_var($_REQUEST['pag_orden'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>0]]) === false
			|| !in_array((string)($_REQUEST['pag_estado'] ?? ''), ['0','1'], true)) {
			http_response_code(422); echo json_encode(['error'=>'Datos de la página inválidos']); exit;
		}
	}

	switch($opcion)
	{
		case 1: // Listar

			$res = $obj -> listar();

			if ($res <> '0')
			{
				foreach ($res as $val)
				{
					$_arreglo[] = array('id' => $val['id'],
										'menu' => $val['menu'],
										'descripcion' => $val['descripcion'],
										'ruta' => $val['ruta'],
										'icono' => $val['icono'],
										'orden' => $val['orden'],
										'estado' => $val['estado']);
				}
			}else
				$_arreglo = '';

			echo json_encode($_arreglo);

			break;


		case 2: // Cargar datos

			$res = $obj -> cargar_datos();

			foreach ($res as $val)
			{
				$_arreglo[] = array('pag_id' => $val['pag_id'], 
									'pag_nombre' => $val['pag_nombre'],
									'men_id' => $val['men_id'], 
									'pag_ruta' => $val['pag_ruta'],
									'pag_icono' => $val['pag_icono'],
									'pag_orden' => $val['pag_orden'],
									'pag_estado' => $val['pag_estado']);
			}

			echo json_encode($_arreglo);

			break;


		case 3: //editar

			$res = $obj -> editar();

			if($res) echo  "1";
			else echo "0";

			break;
			

		case 4: // registrar

			$res = $obj -> registrar();

			if($res) echo  "1";
			else echo "0";

			break;


		case 5: // Listar Páginas x Menú x Rol
			$rolId = (int)($_REQUEST['rol_id'] ?? 0);
			if ($rolId < 1) { http_response_code(422); echo json_encode(['error'=>'Seleccione un rol válido']); break; }

			// Páginas
			require('../model/menu.php');
			$obj_men = new menu();

			$res_men = $obj_men -> listar_menu_estadoxrol_id($rolId);

			$tabla_menu = '';

			$tabla_menu = $tabla_menu.'<form id="form_lista_menu" method="post" action="">
										<table class="table table-hover">';

			while($value_men = $res_men->fetch(PDO::FETCH_ASSOC)){

				if ($value_men['estado'] == 0){
					$tabla_menu = $tabla_menu.'<tr>
										<td align="center">
											<input type="checkbox" name="checkbox_rol_menu" value="'.$value_men['men_id'].'">
										</td>';
				}else{
					$tabla_menu = $tabla_menu.'<tr>
										<td align="center">
											<input type="checkbox" checked name="checkbox_rol_menu" value="'.$value_men['men_id'].'">
										</td>';
				}
					$tabla_menu .= '<td>'.strtoupper($value_men['men_descripcion']).'</td>
								</tr>';
			}

			$tabla_menu = $tabla_menu.'</table>
								</form>';


			$res_pag = $obj -> listar_pagina_estadoxrol_id($rolId);

			$tabla_pagina = '';

			$tabla_pagina = $tabla_pagina.'<form id="form_lista_pagina" method="post" action="">
											<table class="table table-hover">';
								
			while($value_pag = $res_pag->fetch(PDO::FETCH_ASSOC)){

				if ($value_pag['estado'] == 0){
					$tabla_pagina = $tabla_pagina.'<tr>
										<td align="center">
											<input type="checkbox" name="checkbox_rol_pagina" value="'.$value_pag['pag_id'].'">
										</td>';
				}else{
					$tabla_pagina = $tabla_pagina.'<tr>
										<td align="center">
											<input type="checkbox" checked name="checkbox_rol_pagina" value="'.$value_pag['pag_id'].'">
										</td>';
				}	
					$tabla_pagina = $tabla_pagina.'<td>'.strtoupper($value_pag['pag_nombre']).'</td>
								</tr>';
			}

			$tabla_pagina = $tabla_pagina.'</table>
								</form>';

			$array = array(0 => $tabla_menu,
						   1 => $tabla_pagina);

			echo json_encode($array);

			break;
	}

?>
