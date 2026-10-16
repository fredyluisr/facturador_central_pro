<?php

	require_once("../model/conexion.php");

	class cargo extends conexion
	{
		private conexion $cnx;
		public function __construct()
		{
			$this->cnx = new conexion();
		}

		function listar()
		{
			$proc = "SELECT     C.car_id
							,	C.car_nombre
							,	C.car_estado

					 FROM cargo C
					 WHERE car_estado <> 2
					 ORDER BY C.car_nombre";

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
				return $datos[] = array('car_id'=>'0');
		}

		function cargar_datos()
		{
			$proc = "SELECT		C.car_id
							,	C.car_nombre
							,	C.car_estado

					FROM cargo C
					WHERE C.car_id = :car_id";

			$res = $this->cnx->con()->prepare($proc);

			$res->bindparam(':car_id', $_REQUEST["car_id"]);

			$res->execute();

			if($res){
				while ($fila = $res->fetch(PDO::FETCH_ASSOC)){
					$datos[] = $fila;
				}

				return $datos;
			}else
				return $datos[] = array('car_id'=>'0');
		}

		function editar()
		{
			$proc = "UPDATE cargo SET car_nombre = :car_nombre,
									car_estado = :car_estado
					 WHERE car_id = :car_id";

			$res = $this->cnx->con()->prepare($proc);

			$res->bindparam(':car_nombre', $_REQUEST["car_nombre"]);
			$res->bindparam(':car_estado', $_REQUEST["car_estado"]);
			$res->bindparam(':car_id', $_REQUEST["car_id"]);

			if($res->execute())
				return 1;
			else
				return 0;
		}

		function registrar()
		{
			$proc = "INSERT INTO cargo (car_nombre, car_estado, usuario, fechahora, ip)
					 VALUES (:car_nombre, :car_estado, :usuario, NOW(), :ip)";

			$res = $this->cnx->con()->prepare($proc);

			$res->bindparam(':car_nombre', $_REQUEST["car_nombre"]);
			$res->bindparam(':car_estado', $_REQUEST["car_estado"]);
			$res->bindValue(':usuario', (int)$_SESSION['usu_id_ifact'], PDO::PARAM_INT);
			$res->bindValue(':ip', $_SERVER['REMOTE_ADDR'] ?? null);

			if($res->execute())
				return 1;
			else
				return 0;
		}

		function anular()
		{
			$proc = "UPDATE cargo SET car_estado = 2
					 WHERE car_id = :car_id";

			$res = $this->cnx->con()->prepare($proc);

			$res->bindparam(':car_id', $_REQUEST["car_id"]);

			if($res->execute())
				return 1;
			else
				return 0;
		}

		function listar_habilitados()
		{
			$proc = "SELECT 	car_id id
							, 	UPPER(car_nombre) descripcion

					 FROM cargo
					 WHERE car_estado = 1
					 ORDER BY car_nombre";

			$res = $this->cnx->con()->prepare($proc);
			$res->execute();

			if($res){
				while ($fila = $res->fetch(PDO::FETCH_ASSOC)){
					$datos[] = $fila;
				}

				return $datos;
			}else
				return $datos[] = array('car_id'=>'0');
		}
	}
?>
