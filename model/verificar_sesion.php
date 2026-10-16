<?php

	require_once("../model/conexion.php");
	$cnx = new conexion();

	$usu_token = $_REQUEST["usu_token"];

	// ==============================
	// 1. Verificar si existe el token
	// ==============================
	$sql_existe = "SELECT usu_estado 
				FROM usuario 
				WHERE usu_token = :usu_token";

	$stmt = $cnx->con()->prepare($sql_existe);
	$stmt->bindParam(':usu_token', $usu_token);
	$stmt->execute();

	$fila = $stmt->fetch(PDO::FETCH_ASSOC);

	// ==============================
	// 2. Lógica equivalente al SP
	// ==============================
	if ($fila) {

		if ($fila["usu_estado"] == 1) {

			// Token válido y activo → contar perfiles reales

			$sql_perfil = "SELECT COUNT(*) AS cant_perfiles, MIN(P.prf_id) AS prf_id
						FROM perfil P
						JOIN usuario U ON U.usu_id = P.usu_id
						WHERE U.usu_estado = 1
						AND P.prf_estado = 1
						AND U.usu_token = :usu_token";

			$stmt_perfil = $cnx->con()->prepare($sql_perfil);
			$stmt_perfil->bindParam(':usu_token', $usu_token);
			$stmt_perfil->execute();

			$fila_res = $stmt_perfil->fetch(PDO::FETCH_ASSOC);

			$_arreglo[] = array(
				'cant_perfiles' => $fila_res ? (int)$fila_res['cant_perfiles'] : 0,
				'prf_id'        => $fila_res ? (int)$fila_res['prf_id'] : 0
			);

		} else {

			// Token válido pero inactivo → resultado = -1
			$_arreglo[] = array(
				'cant_perfiles' => -1,
				'prf_id' => 0
			);
		}

	} else {

		// Token no existe → resultado = 0
		$_arreglo[] = array(
			'cant_perfiles' => 0,
			'prf_id' => 0
		);
	}

	// ==============================
	// 3. Respuesta
	// ==============================
	echo json_encode($_arreglo);

?>