<?php
	require_once __DIR__ . '/../model/sesion.php';
	header('Content-Type: application/json; charset=utf-8');

	require_once('../model/sesionmodel.php');
	require_once("../model/var_global.php");

	$obj = new acceso();
	$opcion = isset($_REQUEST['task']) ? (int)$_REQUEST['task'] : 0;
	$_arreglo = [];
	if ((int)($_SESSION['rol_id_ifact'] ?? 0) !== 1) {
		http_response_code(403);
		echo json_encode(['error' => 'Acceso reservado al webmaster']);
		exit;
	}

	switch($opcion)
	{
		case 1: 

			$res = $obj -> listar();

			if ($res <> '0')
			{
				foreach ($res as $val)
				{
					$_arreglo[] = array(
						'id' => $val['id'],
						'usu_id' => $val['usu_id'],
						'prf_id' => $val['prf_id'],
						'rol_id' => $val['rol_id'],
						'usuario_nombre' => $val['usuario_nombre'] ?? 'Anónimo',
						'rol_nombre' => $val['rol_nombre'] ?? 'N/A',
						'fecha_login' => $val['fecha_login'],
						'fecha_logout' => $val['fecha_logout'],
						'ip_address' => $val['ip_address'],
						'user_agent' => $val['user_agent'],
						'session_status' => $val['session_status'],
						'logout_reason' => $val['logout_reason'],
						'estado_texto' => $val['estado_texto'],
						'duracion_minutos' => $val['duracion_minutos']
					);
				}
			}else
				$_arreglo = '';

			echo json_encode($_arreglo);

			break;


		case 2: 

			$res = $obj -> listar_activas();

			if ($res <> '0')
			{
				foreach ($res as $val)
				{
					$_arreglo[] = array(
						'id' => $val['id'],
						'usu_id' => $val['usu_id'],
						'usuario_nombre' => $val['usuario_nombre'] ?? 'Anónimo',
						'rol_nombre' => $val['rol_nombre'] ?? 'N/A',
						'fecha_login' => $val['fecha_login'],
						'ip_address' => $val['ip_address'],
						'user_agent' => $val['user_agent'],
						'last_activity' => $val['last_activity'],
						'duracion_minutos' => $val['duracion_minutos'],
						'inactividad_segundos' => $val['inactividad_segundos']
					);
				}
			}else
				$_arreglo = '';

			echo json_encode($_arreglo);

			break;


		case 3: 

			$res = $obj->cargar_datos();

			foreach ($res as $val)
			{
				$_arreglo[] = array(
					'id' => $val['id'],
					'usu_id' => $val['usu_id'],
					'prf_id' => $val['prf_id'],
					'rol_id' => $val['rol_id'],
					'usuario_nombre' => $val['usuario_nombre'],
					'usu_token' => $val['usu_token'],
					'rol_nombre' => $val['rol_nombre'],
					'fecha_login' => $val['fecha_login'],
					'fecha_logout' => $val['fecha_logout'],
					'ip_address' => $val['ip_address'],
					'user_agent' => $val['user_agent'],
					'session_status' => $val['session_status'],
					'logout_reason' => $val['logout_reason'],
					'last_activity' => $val['last_activity']
				);
			}

			echo json_encode($_arreglo);

			break;


		case 4: 

			$res = $obj -> cerrar_sesion();
			echo $res;

			break;


		case 5: 

			$res = $obj -> cerrar_sesiones_usuario();
			echo $res; 

			break;		


		case 6: 

			$res = $obj -> listar_por_usuario();

			if ($res <> '0')
			{
				foreach ($res as $val)
				{
					$_arreglo[] = array(
						'id' => $val['id'],
						'fecha_login' => $val['fecha_login'],
						'fecha_logout' => $val['fecha_logout'],
						'ip_address' => $val['ip_address'],
						'user_agent' => $val['user_agent'],
						'session_status' => $val['session_status'],
						'estado_texto' => $val['estado_texto'],
						'duracion_minutos' => $val['duracion_minutos']
					);
				}
			}else
				$_arreglo = '';

			echo json_encode($_arreglo);

			break;


		case 7: 

			$res = $obj -> limpiar_sesiones_antiguas();
			echo $res; 

			break;


		case 8:
        	
			$f_ini = isset($_GET['f_ini']) ? $_GET['f_ini'] : '';
			$f_fin = isset($_GET['f_fin']) ? $_GET['f_fin'] : '';
			$estado = isset($_GET['est']) ? $_GET['est'] : '';

			$datos = $obj->filtrado($f_ini, $f_fin, $estado);
			
			if($datos == '0')
				echo json_encode([]); 
			else
				echo json_encode($datos);
			
			break;

		case 9:

			$res = $obj->cerrar_todas_sesiones_activas();
			echo json_encode(['success' => $res >= 0, 'cerradas' => max(0, $res)]);

			break;


	}

?>
