<?php
	require_once __DIR__ . '/../model/sesion.php';

	require_once('../model/menu.php');

	// Fredy Luis
	$obj = new menu();
	$opcion = isset($_REQUEST['task']) ? (int)$_REQUEST['task'] : 0;
	if ((int)($_SESSION['rol_id_ifact'] ?? 0) !== 1) {
		http_response_code(403); echo json_encode(['error'=>'Acceso reservado al webmaster']); exit;
	}
	if (in_array($opcion, [3,4], true)) {
		$_REQUEST['men_descripcion'] = trim((string)($_REQUEST['men_descripcion'] ?? ''));
		if ($_REQUEST['men_descripcion'] === '' || mb_strlen($_REQUEST['men_descripcion']) > 50
			|| !preg_match('/^[a-zA-Z0-9_ -]{1,50}$/', (string)($_REQUEST['men_icono'] ?? ''))
			|| filter_var($_REQUEST['men_orden'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>0]]) === false
			|| !in_array((string)($_REQUEST['men_estado'] ?? ''), ['0','1'], true)) {
			http_response_code(422); echo json_encode(['error'=>'Datos del menú inválidos']); exit;
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
										'descripcion' => $val['descripcion'],
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
				$_arreglo[] = array('men_id' => $val['men_id'], 
									'men_descripcion' => $val['men_descripcion'],
									'men_icono' => $val['men_icono'],
									'men_orden' => $val['men_orden'],
									'men_estado' => $val['men_estado']);
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


		case 5: // Listar Menus x Rol

			$res = $obj -> listar_habilitados_permisos();

			$tabla_menu = '';

			$tabla_menu = $tabla_menu.'<form id="form_lista_menu" method="post" action="">
										<table class="table table-hover">';
							
			while($value = $res->fetch(PDO::FETCH_ASSOC)){
				$tabla_menu = $tabla_menu.'<tr>
												<td align="center">
													<input type="checkbox" name="menu" value="'.$value['id'].'">
												</td>
												<td>'.$value['descripcion'].'</td>
											</tr>';
			}
				
			$tabla_menu = $tabla_menu.'</table>
									</form>';

			// Páginas
			require('../model/pagina.php');
			$obj_pag = new pagina();

			$res_pag = $obj_pag -> listar_habilitados_permisos();

			$tabla_pagina = '';

			$tabla_pagina = $tabla_pagina.'<form id="form_lista_pagina" method="post" action="">
											<table class="table table-hover">';

			while($value_pag = $res_pag->fetch(PDO::FETCH_ASSOC)){
				$tabla_pagina = $tabla_pagina.'<tr>
													<td align="center">
														<input type="checkbox" name="pagina" value="'.$value_pag['id'].'">
													</td>
													<td>'.$value_pag['descripcion'].'</td>
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
