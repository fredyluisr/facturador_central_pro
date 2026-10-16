<?php

	require_once("../model/conexion.php");

	class perfil extends conexion
	{
		public function __construct()
		{
			$this->cnx = new conexion();
		}

		// Fredy Luis
		function listar_x_usu_id()
		{
			$proc = "SELECT 	P.prf_id id
							, 	UPPER(R.rol_nombre) descripcion

					 FROM perfil P
					 JOIN usuario U ON U.usu_id = P.usu_id
					 JOIN rol R ON R.rol_id = P.rol_id
					 WHERE U.usu_token = :usu_token
					 AND U.usu_estado = 1
					 AND P.prf_estado = 1";

			$res = $this->cnx->con()->prepare($proc);

			$res->bindparam(':usu_token', $_REQUEST["usu_token"]);

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

		// Fredy Luis
		function listar()
		{
			$proc = "SELECT 	P.prf_id
							,	P.rol_id
							, 	UPPER(R.rol_nombre) descripcion

					 FROM perfil P
					 JOIN rol R ON R.rol_id = P.rol_id
					 WHERE P.usu_id = :usu_id
					 AND P.prf_estado = 1";

			$res = $this->cnx->con()->prepare($proc);

			$res->bindparam(':usu_id', $_REQUEST["usu_id"]);

			$res->execute();

			if($res){
				while ($fila = $res->fetch(PDO::FETCH_ASSOC)){
					$datos[] = $fila;
				}
					return $datos;
			}else
				return $datos[] = array('prf_id'=>'0');
		}

	}
?>