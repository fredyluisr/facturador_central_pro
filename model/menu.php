<?php

	require_once("../model/conexion.php");

	class menu extends conexion
	{
		private conexion $cnx;
		public function __construct()
		{
			$this->cnx = new conexion();
		}

		// Fredy Luis
		function listar()
		{
			$proc = "SELECT     men_id id
							, 	men_descripcion descripcion
							,	men_orden orden
							,	men_icono icono
							,	men_estado estado

					FROM menu";

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

		// Fredy Luis
		function listar_habilitados()
		{
			$proc = "SELECT 	men_id id
							, 	UPPER(men_descripcion) descripcion

					 FROM menu
					 WHERE men_estado = 1
					 ORDER BY men_orden";

			$res = $this->cnx->con()->prepare($proc);
			$res->execute();

			if($res){
				while ($fila = $res->fetch(PDO::FETCH_ASSOC)){
					$datos[] = $fila;
				}
					return $datos;
			}else
				return $datos[] = array('men_id'=>'0');	

			return $res;
		}

		// Fredy Luis
		function listar_habilitados_permisos()
		{
			$proc = "SELECT 	men_id id
							, 	UPPER(men_descripcion) descripcion

					 FROM menu
					 WHERE men_estado = 1
					 ORDER BY men_orden";

			$res = $this->cnx->con()->prepare($proc);
			$res->execute();

			return $res;
		}

		// Fredy
		function listar_menu_estadoxrol_id(?int $rolId = null)
		{
			$rolId = $rolId ?? (int)($_REQUEST["rol_id"] ?? 0);
			$proc = "SELECT m.men_id,
						UPPER(m.men_descripcion) men_descripcion,
						CASE WHEN rm.rol_id IS NULL THEN 0 ELSE 1 END estado
				 FROM menu m
				 LEFT JOIN rol_menu rm ON rm.men_id = m.men_id AND rm.rol_id = :rol_id
				 WHERE m.men_estado = 1
				 ORDER BY m.men_orden, m.men_id";

			$res = $this->cnx->con()->prepare($proc);
			$res->execute([':rol_id' => $rolId]);

			return $res;
		}

		// Fredy Luis
		function cargar_datos()
		{
			$proc = "SELECT		m.men_id
							,	UPPER(m.men_descripcion) men_descripcion
							,	m.men_icono
							,	m.men_orden
							,	m.men_estado

					FROM menu m
					WHERE m.men_id = :men_id";

			$res = $this->cnx->con()->prepare($proc);

			$res->bindparam(':men_id', $_REQUEST["men_id"]);

			$res->execute();

			if($res){
				while ($fila = $res->fetch(PDO::FETCH_ASSOC)){
					$datos[] = $fila;
				}
					return $datos;
			}else
				return $datos[] = array('men_id'=>'0');
		}

		// Fredy Luis
		function editar()
		{
			$proc = "UPDATE menu 	SET men_descripcion = :men_descripcion,
												men_icono = :men_icono,
												men_orden = :men_orden,
												men_estado = :men_estado
					 WHERE men_id = :men_id";

			$res = $this->cnx->con()->prepare($proc);

			$res->bindparam(':men_descripcion', $_REQUEST["men_descripcion"]);
			$res->bindparam(':men_icono', $_REQUEST["men_icono"]);
			$res->bindparam(':men_orden', $_REQUEST["men_orden"]);
			$res->bindparam(':men_estado', $_REQUEST["men_estado"]);
			$res->bindparam(':men_id', $_REQUEST["men_id"]);

			if($res->execute())
				return 1;
			else
				return 0;
		}

		// Fredy Luis
		function registrar()
		{
			$proc = "INSERT INTO menu (men_descripcion, men_icono, men_orden, men_estado)
					 VALUES (:men_descripcion, :men_icono, :men_orden, :men_estado)";

			$res = $this->cnx->con()->prepare($proc);

			$res->bindparam(':men_descripcion', $_REQUEST["men_descripcion"]);
			$res->bindparam(':men_icono', $_REQUEST["men_icono"]);
			$res->bindparam(':men_orden', $_REQUEST["men_orden"]);
			$res->bindparam(':men_estado', $_REQUEST["men_estado"]);

			if($res->execute())
				return 1;
			else
				return 0;
		}

	}
?>
