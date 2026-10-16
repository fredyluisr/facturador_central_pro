<?php

	require_once("../model/conexion.php");

	class acceso extends conexion
	{
		public function __construct()
		{
			$this->cnx = new conexion();
		}

		// Listar todas las sesiones activas e históricas
		function listar()
		{
			$proc = "SELECT 
						s.id,
						s.usu_id,
						s.prf_id,
						s.rol_id,
						UPPER(CONCAT_WS(' ',e.emp_nombres,e.emp_ape_paterno,e.emp_ape_materno)) AS usuario_nombre,
						r.rol_nombre,
						s.fecha_login,
						s.fecha_logout,
						s.ip_address,
						s.user_agent,
						s.session_status,
						s.logout_reason,
						CASE 
							WHEN s.session_status = 1 THEN 'Activa'
							WHEN s.session_status = 0 THEN 'Cerrada'
							ELSE 'Desconocido'
						END AS estado_texto,
						CASE 
							WHEN s.fecha_logout IS NULL THEN TIMESTAMPDIFF(MINUTE, s.fecha_login, NOW())
							ELSE TIMESTAMPDIFF(MINUTE, s.fecha_login, s.fecha_logout)
						END AS duracion_minutos
					FROM sessions s
					LEFT JOIN usuario u ON u.usu_id = s.usu_id
					LEFT JOIN empleado e ON e.emp_id = u.emp_id
					LEFT JOIN rol r ON r.rol_id = s.rol_id
					ORDER BY s.fecha_login DESC
					LIMIT 500";

			$res = $this->cnx->con()->prepare($proc);
			$res->execute();

			$cont = 0;

			while ($fila = $res->fetch(PDO::FETCH_ASSOC)){
				$datos[] = $fila;
				$cont += 1;
			}

			if ($cont > 0)
				return $datos;
			else
				return '0';
		}

		// Listar solo sesiones activas
		function listar_activas()
		{
			$proc = "SELECT 
						s.id,
						s.usu_id,
						s.prf_id,
						s.rol_id,
						UPPER(CONCAT_WS(' ',e.emp_nombres,e.emp_ape_paterno,e.emp_ape_materno)) AS usuario_nombre,
						r.rol_nombre,
						s.fecha_login,
						s.ip_address,
						s.user_agent,
						s.last_activity,
						TIMESTAMPDIFF(MINUTE, s.fecha_login, NOW()) AS duracion_minutos,
						TIMESTAMPDIFF(SECOND, FROM_UNIXTIME(s.last_activity), NOW()) AS inactividad_segundos
					FROM sessions s
					LEFT JOIN usuario u ON u.usu_id = s.usu_id
					LEFT JOIN empleado e ON e.emp_id = u.emp_id
					LEFT JOIN rol r ON r.rol_id = s.rol_id
					WHERE s.session_status = 1
					ORDER BY s.last_activity DESC";

			$res = $this->cnx->con()->prepare($proc);
			$res->execute();

			$cont = 0;

			while ($fila = $res->fetch(PDO::FETCH_ASSOC)){
				$datos[] = $fila;
				$cont += 1;
			}

			if ($cont > 0)
				return $datos;
			else
				return '0';
		}

		// Obtener detalles de una sesión específica -Gerson
		function cargar_datos()
		{
			$proc = "SELECT 
						s.*,
						UPPER(CONCAT_WS(' ',e.emp_nombres,e.emp_ape_paterno,e.emp_ape_materno)) AS usuario_nombre,
						u.usu_token,
						r.rol_nombre,
						p.prf_id
					FROM sessions s
					LEFT JOIN usuario u ON u.usu_id = s.usu_id
					LEFT JOIN empleado e ON e.emp_id = u.emp_id
					LEFT JOIN rol r ON r.rol_id = s.rol_id
					LEFT JOIN perfil p ON p.prf_id = s.prf_id
					WHERE s.id = :session_id";

			$res = $this->cnx->con()->prepare($proc);
			$res->bindparam(':session_id', $_REQUEST["session_id"]);
			$res->execute();

			if($res){
				while ($fila = $res->fetch(PDO::FETCH_ASSOC)){
					$datos[] = $fila;
				}
				return $datos;
			}else
				return $datos[] = array('id'=>'0');
		}

		// Cerrar sesión manualmente (por administrador)
		function cerrar_sesion()
		{
			$pdo = $this->cnx->con();
			try {
				$pdo->beginTransaction();
				$consulta = $pdo->prepare("SELECT usu_id, prf_id FROM sessions WHERE id = :session_id FOR UPDATE");
				$consulta->execute([':session_id' => $_REQUEST['session_id'] ?? '']);
				$sesion = $consulta->fetch(PDO::FETCH_ASSOC);
				if (!$sesion) {
					$pdo->rollBack();
					return 0;
				}

				$actualizar = $pdo->prepare("UPDATE sessions SET data = '', fecha_logout = NOW(), session_status = 0, logout_reason = 'admin_forced', updated_at = NOW() WHERE id = :session_id");
				$actualizar->execute([':session_id' => $_REQUEST['session_id']]);

				if (!empty($sesion['usu_id'])) {
					$revocar = $pdo->prepare("DELETE FROM remember_tokens WHERE usu_id = :usu_id AND (:prf_id IS NULL OR prf_id = :prf_id_match)");
					$revocar->execute([
						':usu_id' => $sesion['usu_id'],
						':prf_id' => $sesion['prf_id'] ?: null,
						':prf_id_match' => $sesion['prf_id'] ?: null
					]);
				}

				$pdo->commit();
				return 1;
			} catch (Throwable $e) {
				if ($pdo->inTransaction()) $pdo->rollBack();
				return 0;
			}
		}

		function cerrar_todas_sesiones_activas()
		{
			$pdo = $this->cnx->con();
			try {
				$pdo->beginTransaction();
				$actualizar = $pdo->prepare("UPDATE sessions SET data = '', fecha_logout = NOW(), session_status = 0, logout_reason = 'admin_forced_all_active', updated_at = NOW() WHERE session_status = 1");
				$actualizar->execute();
				$cantidad = $actualizar->rowCount();
				$pdo->exec("DELETE FROM remember_tokens");
				$pdo->commit();
				return $cantidad;
			} catch (Throwable $e) {
				if ($pdo->inTransaction()) $pdo->rollBack();
				return -1;
			}
		}

		// Cerrar todas las sesiones de un usuario específico -Gerson
		function cerrar_sesiones_usuario()
		{
			$proc = "UPDATE sessions 
					SET fecha_logout = NOW(),
						session_status = 0,
						logout_reason = 'admin_forced_all'
					WHERE usu_id = :usu_id 
					AND session_status = 1";

			$res = $this->cnx->con()->prepare($proc);
			$res->bindparam(':usu_id', $_REQUEST["usu_id"]);

			if($res->execute())
				return $res->rowCount(); 
			else
				return 0;
		}

		// Obtener accesos por usuario
		function listar_por_usuario()
		{
			$proc = "SELECT 
						s.id,
						s.fecha_login,
						s.fecha_logout,
						s.ip_address,
						s.user_agent,
						s.session_status,
						CASE 
							WHEN s.session_status = 1 THEN 'Activa'
							WHEN s.session_status = 0 THEN 'Cerrada'
							ELSE 'Desconocido'
						END AS estado_texto,
						CASE 
							WHEN s.fecha_logout IS NULL THEN TIMESTAMPDIFF(MINUTE, s.fecha_login, NOW())
							ELSE TIMESTAMPDIFF(MINUTE, s.fecha_login, s.fecha_logout)
						END AS duracion_minutos
					FROM sessions s
					WHERE s.usu_id = :usu_id
					ORDER BY s.fecha_login DESC
					LIMIT 100";

			$res = $this->cnx->con()->prepare($proc);
			$res->bindparam(':usu_id', $_REQUEST["usu_id"]);
			$res->execute();

			$cont = 0;

			while ($fila = $res->fetch(PDO::FETCH_ASSOC)){
				$datos[] = $fila;
				$cont += 1;
			}

			if ($cont > 0)
				return $datos;
			else
				return '0';
		}

		
		function limpiar_sesiones_antiguas()
		{
			$proc = "DELETE FROM sessions 
					WHERE fecha_login < DATE_SUB(NOW(), INTERVAL 30 DAY)
					AND session_status = 0";

			$res = $this->cnx->con()->prepare($proc);

			if($res->execute())
				return $res->rowCount(); 
			else
				return 0;
		}


		function filtrado($f_ini = '', $f_fin = '', $estado = '')
		{
			$condiciones = "";
			$parametros = [];

			// Filtro de Fecha Inicio
			if (!empty($f_ini)) {
				$condiciones .= " AND DATE(s.fecha_login) >= :f_ini";
				$parametros[':f_ini'] = date("Y-m-d", strtotime($f_ini));
			}

			// Filtro de Fecha Fin
			if (!empty($f_fin)) {
				$condiciones .= " AND DATE(s.fecha_login) <= :f_fin";
				$parametros[':f_fin'] = date("Y-m-d", strtotime($f_fin));
			}

			// Filtro de Estado
			if ($estado !== '') { // Usamos !== para permitir el valor '0'
				$condiciones .= " AND s.session_status = :estado";
				$parametros[':estado'] = $estado;
			}

			$proc = "SELECT 
						s.id,
						s.usu_id,
						s.prf_id,
						s.rol_id,
						UPPER(CONCAT_WS(' ',e.emp_nombres,e.emp_ape_paterno,e.emp_ape_materno)) AS usuario_nombre,
						r.rol_nombre,
						s.fecha_login,
						s.fecha_logout,
						s.ip_address,
						s.user_agent,
						s.session_status,
						s.logout_reason,
						CASE 
							WHEN s.session_status = 1 THEN 'Activa'
							WHEN s.session_status = 0 THEN 'Cerrada'
							ELSE 'Desconocido'
						END AS estado_texto,
						CASE 
							WHEN s.fecha_logout IS NULL THEN TIMESTAMPDIFF(MINUTE, s.fecha_login, NOW())
							ELSE TIMESTAMPDIFF(MINUTE, s.fecha_login, s.fecha_logout)
						END AS duracion_minutos
					FROM sessions s
					LEFT JOIN usuario u ON u.usu_id = s.usu_id
					LEFT JOIN empleado e ON e.emp_id = u.emp_id
					LEFT JOIN rol r ON r.rol_id = s.rol_id
					WHERE 1=1 $condiciones 
					ORDER BY s.fecha_login DESC
					LIMIT 1000";

			$res = $this->cnx->con()->prepare($proc);
			
			// Ejecutar con el array de parámetros vinculados
			$res->execute($parametros);

			$datos = [];
			while ($fila = $res->fetch(PDO::FETCH_ASSOC)){
				$datos[] = $fila;
			}

			return (count($datos) > 0) ? $datos : '0';
		}
	}
?>
