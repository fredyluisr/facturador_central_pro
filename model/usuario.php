<?php

	require_once("../model/sesion.php");
	require_once("../model/conexion.php");

	class usuario extends conexion
	{
		public function __construct()
		{
			$this->cnx = new conexion();
		}

		// Fredy Luis - Listar
		function listar()
		{
			$proc = "SELECT		U.usu_id id
							,	UPPER(CONCAT(E.emp_nombres, ' ', E.emp_ape_paterno, ' ', E.emp_ape_materno)) nombre
							,	U.usu_token token
							,	U.usu_estado estado

					 FROM usuario U
					 JOIN empleado E on E.emp_id = U.emp_id
					 WHERE U.usu_estado <> 2";

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

		// Fredy Luis - Listar
		function listar_habilitados()
		{
			$proc = "SELECT		U.usu_id id
							,	CONCAT(usu_nombres, ' ', usu_ape_paterno, ' ', usu_ape_materno) descripcion

					 FROM usuario U
					 WHERE U.usu_estado <> 2
					 ORDER BY CONCAT(usu_nombres, ' ', usu_ape_paterno, ' ', usu_ape_materno)";

			$res = $this->cnx->con()->prepare($proc);
			$res->execute();

			if ($res)
			{
				$cont = 0;

				while ($fila = $res->fetch(PDO::FETCH_ASSOC)){
					$datos[] = $fila;
					$cont += 1;
				}

				if ($cont > 0)
					return $datos;
				else
					return '0';
			}else
				return '0';
		}

		// Fredy Luis - Cargar Datos de usuario
		function cargar_datos()
		{
			$proc = "SELECT
						u.usu_id,
						u.usu_estado,
						u.usu_token,
						u.emp_id

					FROM usuario u
					JOIN perfil p ON p.usu_id = u.usu_id
					JOIN rol r ON r.rol_id = p.rol_id
					WHERE u.usu_id = :usu_id";

			$res = $this->cnx->con()->prepare($proc);

			$res->bindparam(':usu_id', $_REQUEST["usu_id"]);

			$res->execute();

			if($res){
				while ($fila = $res->fetch(PDO::FETCH_ASSOC)){
					$datos[] = $fila;
				}
					return $datos;
			}else
				return $datos[] = array('usu_id'=>'0');
		}

		// Fredy Luis
		function registrar($tipo_dispositivo)
		{
			$cn = $this->cnx->con();

			try
			{
				$cn->beginTransaction();

				// =====================================================
				// OBTENER DATOS DEL EMPLEADO
				// =====================================================
				$sqlEmp = "SELECT emp_ape_paterno, emp_ape_materno 
						FROM empleado 
						WHERE emp_id = :emp_id";

				$stmtEmp = $cn->prepare($sqlEmp);
				$stmtEmp->bindParam(':emp_id', $_REQUEST["emp_id"]);
				$stmtEmp->execute();

				$emp = $stmtEmp->fetch(PDO::FETCH_ASSOC);

				if (!$emp) {
					throw new Exception("Empleado no encontrado");
				}

				// =====================================================
				// GENERAR CÓDIGO ALEATORIO (4 dígitos)
				// =====================================================
				do {
					$codigo = rand(1000, 9999);
				} while (strlen($codigo) != 4);

				// =====================================================
				// GENERAR TOKEN (con apellidos del empleado)
				// =====================================================
				$ape_pat = strtoupper(substr($emp["emp_ape_paterno"], 0, 2));
				$ape_mat = strtoupper(substr($emp["emp_ape_materno"], 0, 2));

				$token = $ape_pat . $ape_mat . $codigo;

				// =====================================================
				// INSERT USUARIO
				// =====================================================
				$sql = "INSERT INTO usuario (emp_id, usu_estado, usu_token, usuario, fechahora)
						VALUES (:emp_id, :usu_estado, :usu_token, :usuario, NOW())";

				$res = $cn->prepare($sql);

				$res->bindParam(':emp_id', $_REQUEST["emp_id"]);
				$res->bindParam(':usu_estado', $_REQUEST["usu_estado"]);
				$res->bindParam(':usu_token', $token);
				$res->bindParam(':usuario', $_REQUEST["usuario"]);

				$res->execute();

				// =====================================================
				// OBTENER ID
				// =====================================================
				$usu_id = $cn->lastInsertId();

				// =====================================================
				// INSERTAR ROLES
				// =====================================================
				if (!empty($_REQUEST["roles"])) {

					$roles = explode('-', $_REQUEST["roles"]);

					$sqlRol = "INSERT INTO perfil (usu_id, rol_id) VALUES (:usu_id, :rol_id)";
					$stmtRol = $cn->prepare($sqlRol);

					foreach ($roles as $rol) {
						if (!empty($rol)) {
							$stmtRol->bindParam(':usu_id', $usu_id);
							$stmtRol->bindParam(':rol_id', $rol);
							$stmtRol->execute();
						}
					}
				}

				// =====================================================
				// COMMIT
				// =====================================================
				$cn->commit();

				return $usu_id;
			}
			catch (Exception $e)
			{
				$cn->rollBack();
				throw new Exception("Error al registrar usuario: " . $e->getMessage());
			}
		}

		// Fredy Luis
		function editar()
		{
			$cn = $this->cnx->con();

			try
			{
				$cn->beginTransaction();

				// =====================================================
				// VALIDAR usu_id
				// =====================================================
				if (empty($_REQUEST["usu_id"])) {
					throw new Exception("ID de usuario requerido");
				}

				$usu_id = $_REQUEST["usu_id"];

				// =====================================================
				// UPDATE USUARIO (solo estado + auditoría)
				// =====================================================
				$sql = "UPDATE usuario 
						SET usu_estado = :usu_estado,
							usu_usu_ult_edicion = :usuario,
							usu_fec_ult_edicion = NOW()
						WHERE usu_id = :usu_id";

				$stmt = $cn->prepare($sql);
				$stmt->bindParam(':usu_estado', $_REQUEST["usu_estado"]);
				$stmt->bindParam(':usuario', $_REQUEST["usuario"]);
				$stmt->bindParam(':usu_id', $usu_id);
				$stmt->execute();

				// =====================================================
				// ELIMINAR ROLES ACTUALES
				// =====================================================
				$sqlDel = "DELETE FROM perfil WHERE usu_id = :usu_id";
				$stmtDel = $cn->prepare($sqlDel);
				$stmtDel->bindParam(':usu_id', $usu_id);
				$stmtDel->execute();

				// =====================================================
				// INSERTAR NUEVOS ROLES
				// =====================================================
				if (!empty($_REQUEST["roles"])) {

					$roles = explode('-', $_REQUEST["roles"]);

					$sqlRol = "INSERT INTO perfil (usu_id, rol_id) VALUES (:usu_id, :rol_id)";
					$stmtRol = $cn->prepare($sqlRol);

					foreach ($roles as $rol) {
						if (!empty($rol)) {
							$stmtRol->bindParam(':usu_id', $usu_id);
							$stmtRol->bindParam(':rol_id', $rol);
							$stmtRol->execute();
						}
					}
				}

				// =====================================================
				// COMMIT
				// =====================================================
				$cn->commit();

				return 1;

			}
			catch (Exception $e)
			{
				$cn->rollBack();
				error_log("Error editar usuario: " . $e->getMessage());
				return 0;
			}
		}

		// Fredy Luis
		function eliminar()
		{
			$proc = "UPDATE usuario SET 
										usu_estado = 2,
										usu_usu_anulado = :usuario,
										usu_fec_anulado = NOW()

					 WHERE usu_id = :usu_id";

			$res = $this->cnx->con()->prepare($proc);

			$res->bindparam(':usuario', $_REQUEST["usuario"]);
			$res->bindparam(':usu_id', $_REQUEST["usu_id"]);			

			if($res->execute())
				return 1;
			else
				return 0;
		}

		// Fredy Luis
		function generar_token()
		{
			$cn = $this->cnx->con();

			try
			{
				$cn->beginTransaction();

				// =====================================================
				// 1. OBTENER emp_id DESDE usuario
				// =====================================================
				$sqlUsu = "SELECT emp_id FROM usuario WHERE usu_id = :usu_id";
				$stmtUsu = $cn->prepare($sqlUsu);
				$stmtUsu->bindParam(':usu_id', $_REQUEST["usu_id"]);
				$stmtUsu->execute();

				$usu = $stmtUsu->fetch(PDO::FETCH_ASSOC);

				if (!$usu) {
					throw new Exception("Usuario no encontrado");
				}

				// =====================================================
				// 2. OBTENER APELLIDOS DESDE empleado
				// =====================================================
				$sqlEmp = "	SELECT emp_ape_paterno, emp_ape_materno 
							FROM empleado 
							WHERE emp_id = :emp_id";

				$stmtEmp = $cn->prepare($sqlEmp);
				$stmtEmp->bindParam(':emp_id', $usu["emp_id"]);
				$stmtEmp->execute();

				$emp = $stmtEmp->fetch(PDO::FETCH_ASSOC);

				if (!$emp) {
					throw new Exception("Empleado no encontrado");
				}

				// =====================================================
				// 3. GENERAR TOKEN ÚNICO
				// =====================================================
				do {
					$codigo = rand(1000, 9999);

					$ape_pat = strtoupper(substr($emp["emp_ape_paterno"], 0, 2));
					$ape_mat = strtoupper(substr($emp["emp_ape_materno"], 0, 2));

					$token = $ape_pat . $ape_mat . $codigo;

					// Validar que no exista
					$stmtVal = $cn->prepare("SELECT COUNT(*) FROM usuario WHERE usu_token = ?");
					$stmtVal->execute([$token]);
					$existe = $stmtVal->fetchColumn();

				} while ($existe > 0);

				// =====================================================
				// 4. ACTUALIZAR TOKEN
				// =====================================================
				$sqlUpd = "	UPDATE usuario 
							SET usu_token = :token,
								usu_usu_ult_edicion = :usuario,
								usu_fec_ult_edicion = NOW()
							WHERE usu_id = :usu_id";

				$stmtUpd = $cn->prepare($sqlUpd);
				$stmtUpd->bindParam(':token', $token);
				$stmtUpd->bindParam(':usuario', $_REQUEST["usuario"]);
				$stmtUpd->bindParam(':usu_id', $_REQUEST["usu_id"]);
				$stmtUpd->execute();

				// =====================================================
				// COMMIT
				// =====================================================
				$cn->commit();

				return json_encode(['token' => $token]);
			}
			catch (Exception $e)
			{
				$cn->rollBack();
				throw new Exception("Error al generar token: " . $e->getMessage());
			}
		}

		function actualizar_emp_id($usu_id, $emp_id)
		{
			$proc = "UPDATE usuario SET emp_id = :emp_id WHERE usu_id = :usu_id";
			$res  = $this->cnx->con()->prepare($proc);
			$res->bindParam(':emp_id', $emp_id);
			$res->bindParam(':usu_id', $usu_id);
			return $res->execute() ? 1 : 0;
		}

		// Fredy Luis
        function verificar_existencia_documento()
        {
            $proc = "SELECT COUNT(0) resultado
					 FROM  usuario U
					 WHERE U.usu_estado = 1
					 AND emp_id = :emp_id";

            $res = $this->cnx->con()->prepare($proc);

            $res->bindparam(':emp_id', $_REQUEST["emp_id"]);

            $res->execute();

            return $res;
        }

	}
?>