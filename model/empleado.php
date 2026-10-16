<?php

	require_once("../model/conexion.php");

	class empleado extends conexion
	{
		private conexion $cnx;
		public function __construct()
		{
			$this->cnx = new conexion();
		}

		function listar()
		{
			$proc = "SELECT     E.emp_id
							,	E.emp_tipo_doc
							,	E.emp_nro_doc
							,	E.emp_ape_paterno
							,	E.emp_ape_materno
							,	E.emp_nombres
							,	E.emp_alias
							,	C.car_nombre
							,	E.emp_estado

					 FROM empleado E
					 INNER JOIN cargo C ON E.car_id = C.car_id
					 WHERE E.emp_estado <> 2
					 ORDER BY E.emp_ape_paterno, E.emp_ape_materno, E.emp_nombres";

			$res = $this->cnx->con()->prepare($proc);
			$res->execute();

			$cont = 0;

			if($res){
				while ($fila = $res->fetch(PDO::FETCH_ASSOC)){
					$datos[] = $fila;
					$cont += 1;
				}

				if ($cont > 0)
					return $datos;
				else
					return '0';	
			}else
				return $datos[] = array('emp_id'=>'0');
		}

		function cargar_datos()
		{
			$proc = "SELECT		E.emp_id
							,	E.emp_tipo_doc
							,	E.emp_nro_doc
							,	E.emp_ape_paterno
							,	E.emp_ape_materno
							,	E.emp_nombres
							,	E.emp_alias
							,	E.emp_fec_nac
							,	E.emp_sexo
							,	E.emp_est_civil
							,	E.emp_direccion
							,	E.emp_email
							,	E.emp_movil
							,	E.car_id
							,	E.emp_estado

					FROM empleado E
					WHERE E.emp_id = :emp_id";

			$res = $this->cnx->con()->prepare($proc);

			$res->bindparam(':emp_id', $_REQUEST["emp_id"]);

			$res->execute();

			if($res){
				while ($fila = $res->fetch(PDO::FETCH_ASSOC)){
					$datos[] = $fila;
				}

				return $datos;
			}else
				return $datos[] = array('emp_id'=>'0');
		}

		function editar()
		{
			$proc = "UPDATE empleado SET 
							emp_tipo_doc            = :emp_tipo_doc,
							emp_nro_doc             = :emp_nro_doc,
							emp_ape_paterno         = :emp_ape_paterno,
							emp_ape_materno         = :emp_ape_materno,
							emp_nombres             = :emp_nombres,
							emp_alias               = :emp_alias,
							emp_fec_nac             = :emp_fec_nac,
							emp_sexo                = :emp_sexo,
							emp_est_civil           = :emp_est_civil,
							emp_direccion           = :emp_direccion,
							emp_email               = :emp_email,
							emp_movil               = :emp_movil,
							car_id                  = :car_id,
							emp_usu_ult_edicion     = :emp_usu_ult_edicion,
							emp_fec_ult_edicion     = :emp_fec_ult_edicion,
							emp_estado              = :emp_estado
					WHERE emp_id = :emp_id";

			$res = $this->cnx->con()->prepare($proc);

			$res->bindparam(':emp_tipo_doc',            $_REQUEST["emp_tipo_doc"]);
			$res->bindparam(':emp_nro_doc',             $_REQUEST["emp_nro_doc"]);
			$res->bindparam(':emp_ape_paterno',         $_REQUEST["emp_ape_paterno"]);
			$res->bindparam(':emp_ape_materno',         $_REQUEST["emp_ape_materno"]);
			$res->bindparam(':emp_nombres',             $_REQUEST["emp_nombres"]);
			$res->bindparam(':emp_alias',               $_REQUEST["emp_alias"]);
			$res->bindparam(':emp_fec_nac',             $_REQUEST["emp_fec_nac"]);
			$res->bindparam(':emp_sexo',                $_REQUEST["emp_sexo"]);
			$res->bindparam(':emp_est_civil',           $_REQUEST["emp_est_civil"]);
			$res->bindparam(':emp_direccion',           $_REQUEST["emp_direccion"]);
			$res->bindparam(':emp_email',               $_REQUEST["emp_email"]);
			$res->bindparam(':emp_movil',               $_REQUEST["emp_movil"]);
			$res->bindparam(':car_id',                  $_REQUEST["car_id"]);
			$res->bindparam(':emp_estado',              $_REQUEST["emp_estado"]);

			// ✅ Asignar a variables primero
			$usu_ult_edicion = isset($_SESSION["usu_id_ifact"]) ? $_SESSION["usu_id_ifact"] : 1;
			$fec_ult_edicion = date('Y-m-d H:i:s');
			$emp_id          = $_REQUEST["emp_id"];

			$res->bindparam(':emp_usu_ult_edicion', $usu_ult_edicion);
			$res->bindparam(':emp_fec_ult_edicion', $fec_ult_edicion);
			$res->bindparam(':emp_id',              $emp_id);

			if($res->execute())
				return 1;
			else
				return 0;
		}

		function registrar()
		{
			$proc = "INSERT INTO empleado (emp_tipo_doc, emp_nro_doc, emp_ape_paterno, emp_ape_materno, 
						emp_nombres, emp_alias, emp_fec_nac, emp_sexo, emp_est_civil, emp_direccion, emp_email,
						emp_movil, car_id, 
						emp_estado, 
						usuario, fechahora, ip, tipo_dispositivo, equipo)
					VALUES (:emp_tipo_doc, :emp_nro_doc, :emp_ape_paterno, :emp_ape_materno, 
						:emp_nombres, :emp_alias, :emp_fec_nac, :emp_sexo, :emp_est_civil, :emp_direccion, :emp_email,
						:emp_movil, :car_id, 
						:emp_estado, 
						:usuario, :fechahora, :ip, :tipo_dispositivo, :equipo)";

			$res = $this->cnx->con()->prepare($proc);

			$res->bindparam(':emp_tipo_doc',            $_REQUEST["emp_tipo_doc"]);
			$res->bindparam(':emp_nro_doc',             $_REQUEST["emp_nro_doc"]);
			$res->bindparam(':emp_ape_paterno',         $_REQUEST["emp_ape_paterno"]);
			$res->bindparam(':emp_ape_materno',         $_REQUEST["emp_ape_materno"]);
			$res->bindparam(':emp_nombres',             $_REQUEST["emp_nombres"]);
			$res->bindparam(':emp_alias',               $_REQUEST["emp_alias"]);
			$res->bindparam(':emp_fec_nac',             $_REQUEST["emp_fec_nac"]);
			$res->bindparam(':emp_sexo',                $_REQUEST["emp_sexo"]);
			$res->bindparam(':emp_est_civil',           $_REQUEST["emp_est_civil"]);
			$res->bindparam(':emp_direccion',           $_REQUEST["emp_direccion"]);
			$res->bindparam(':emp_email',               $_REQUEST["emp_email"]);
			$res->bindparam(':emp_movil',               $_REQUEST["emp_movil"]);
			$res->bindparam(':car_id',                  $_REQUEST["car_id"]);
			$res->bindparam(':emp_estado',              $_REQUEST["emp_estado"]);

			// ✅ Asignar a variables primero
			$usuario        = isset($_SESSION["usu_id_ifact"]) ? $_SESSION["usu_id_ifact"] : 1;
			$fechahora      = date('Y-m-d H:i:s');
			$ip             = $_SERVER['REMOTE_ADDR'];
			$tipo_disp      = isset($_SESSION["tipo_dispositivo"]) ? (int)$_SESSION["tipo_dispositivo"] : 1;
			$equipo         = isset($_SESSION["equipo"]) ? $_SESSION["equipo"] : 'Desconocido';

			$res->bindparam(':usuario',         $usuario);
			$res->bindparam(':fechahora',       $fechahora);
			$res->bindparam(':ip',              $ip);
			$res->bindparam(':tipo_dispositivo',$tipo_disp);
			$res->bindparam(':equipo',          $equipo);

			if($res->execute())
				return 1;
			else
				return 0;
		}

		function anular()
		{
			$proc = "UPDATE empleado SET emp_estado = 2
					 WHERE emp_id = :emp_id";

			$res = $this->cnx->con()->prepare($proc);

			$res->bindparam(':emp_id', $_REQUEST["emp_id"]);

			if($res->execute())
				return 1;
			else
				return 0;
		}

		function listar_habilitados()
		{
			$proc = "SELECT 	emp_id id
							, 	UPPER(CONCAT(emp_ape_paterno, ' ', emp_ape_materno, ', ', emp_nombres)) descripcion

					 FROM empleado
					 WHERE emp_estado = 1
					 ORDER BY descripcion";

			$res = $this->cnx->con()->prepare($proc);
			$res->execute();

			if($res){
				while ($fila = $res->fetch(PDO::FETCH_ASSOC)){
					$datos[] = $fila;
				}

				return $datos;
			}else
				return $datos[] = array('emp_id'=>'0');
		}

	}
?>
