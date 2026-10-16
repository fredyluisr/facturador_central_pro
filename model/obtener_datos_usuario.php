<?php

 
	require_once("../model/sesion.php");
	require_once("../model/conexion.php");
	require_once("../model/var_global.php");

	$cnx = new conexion();
	$pdo = $cnx->con(); // objeto PDO

	// =======================
	// VALIDAR ENTRADAS
	// =======================
	$usu_token   = isset($_REQUEST['usu_token'])   ? trim($_REQUEST['usu_token']) : '';
	$prf_id      = isset($_REQUEST['prf_id'])      ? (int)$_REQUEST['prf_id']    : 0;
	$loc_id      = isset($_REQUEST['loc_id'])      ? (int)$_REQUEST['loc_id']    : 0;
	$remember_me = isset($_REQUEST['remember_me']) && $_REQUEST['remember_me'] == 1 ? true : false;

	if (mb_strlen($usu_token, 'UTF-8') !== 8 || $prf_id <= 0 || $loc_id <= 0) {
		$response = [
			"status" => 0,
			"message" => "Datos inválidos"
		];
		header('Content-Type: application/json');
		echo json_encode($response);
		exit;
	}

	// =======================
	// OBTENER DATOS DEL USUARIO (SIN SP)
	// =======================
	try {

		$sql = "SELECT
					u.usu_id,
					UPPER(CONCAT(e.emp_nombres, ' ', e.emp_ape_paterno, ' ', e.emp_ape_materno)) AS nombre_completo,
					UPPER(CONCAT(e.emp_ape_paterno, ' ', e.emp_ape_materno)) AS apellidos,
					UPPER(e.emp_nombres) AS nombres,
					u.usu_token,
					p.rol_id,
					UPPER(r.rol_nombre) AS rol,
					p.prf_id,
					u.emp_id
				FROM usuario u
				JOIN perfil p   ON u.usu_id = p.usu_id
				JOIN rol r      ON r.rol_id = p.rol_id
				JOIN empleado e ON e.emp_id = u.emp_id
				WHERE u.usu_token = :usu_token
				AND u.usu_estado = 1
				AND p.prf_id = :prf_id";

		$stmt = $pdo->prepare($sql);
		$stmt->bindParam(':usu_token', $usu_token);
		$stmt->bindParam(':prf_id', $prf_id);
		$stmt->execute();

		$fila = $stmt->fetch(PDO::FETCH_ASSOC);

		if ($fila && isset($fila["usu_id"])) {
			// El navegador elige un local y el servidor vuelve a validar que siga habilitado.
			$sql_alm = "SELECT loc_id,loc_nombre FROM `local` WHERE loc_id=:loc_id AND loc_estado=1";
			$stmt_alm = $pdo->prepare($sql_alm);
			$stmt_alm->execute([':loc_id'=>$loc_id]);
			$fila_alm = $stmt_alm->fetch(PDO::FETCH_ASSOC);

			if (!$fila_alm) {
				echo json_encode(["status" => 0, "message" => "El local seleccionado ya no está disponible"]);
				exit;
			}

			// =======================
			// SESIÓN (sin foto ni alias)
			// =======================
			$_SESSION["nombre_completo_ifact"] = $fila["nombre_completo"];
			$_SESSION["apellidos_ifact"]       = $fila["apellidos"];
			$_SESSION["nombres_ifact"]         = $fila["nombres"];
			$_SESSION["usu_token_ifact"]       = $fila["usu_token"];
			$_SESSION["usu_id_ifact"]          = $fila["usu_id"];
			$_SESSION["rol_id_ifact"]          = $fila["rol_id"];
			$_SESSION["prf_id_ifact"]          = $fila["prf_id"];
			$_SESSION["rol_nombre_ifact"]      = $fila["rol"];
			$_SESSION["emp_id_ifact"]          = $fila["emp_id"];

			$_SESSION["loc_id_ifact"]     = (int)$fila_alm["loc_id"];
			$_SESSION["loc_nombre_ifact"] = $fila_alm["loc_nombre"];

            session_regenerate_id(true);
            cancelarRecuerdoIFact($pdo);
            if ($remember_me) crearRecuerdoIFact($pdo,$fila,$loc_id);
            session_write_close();

			// =======================
			// RESPUESTA OK
			// =======================
			$response = [
				"status"  => 1,
				"message" => "Login exitoso",
				"usuario" => [
					"id"       => $fila["usu_id"],
					"nombre"   => $fila["nombres"],
					"apellido" => $fila["apellidos"],
					"rol"      => $fila["rol"],
					"local"    => $fila_alm["loc_nombre"]
				]
			];

		} else {
			$response = [
				"status"  => 0,
				"message" => "Usuario o perfil inválido"
			];
		}

	} catch (PDOException $e) {
		error_log("Error login: " . $e->getMessage());
		$response = [
			"status"  => 0,
			"message" => "Error interno, intente nuevamente"
		];
	}

	// =======================
	// RESPUESTA FINAL
	// =======================
	header('Content-Type: application/json');
	echo json_encode($response);
	exit;

?>
