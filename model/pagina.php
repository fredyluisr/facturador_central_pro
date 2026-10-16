<?php

	require_once("../model/conexion.php");

	class pagina extends conexion
	{
		private conexion $cnx;
		public function __construct()
		{
			$this->cnx = new conexion();
		}

		// Fredy Luis
		function listar()
		{
			$proc = "SELECT     p.pag_id id
							,	pag_nombre descripcion
							,	men_descripcion menu
							,	men_orden
							,	pag_orden orden
							,	pag_icono icono
							,	pag_ruta ruta
							,	pag_estado estado

					FROM pagina p
					JOIN menu m ON p.men_id = m.men_id";

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
		function listar_habilitados_permisos()
		{
			$proc = "SELECT 	pag_id id
							, 	UPPER(CONCAT(M.men_descripcion,' | ', pag_nombre)) descripcion

					 FROM pagina P
					 JOIN menu M ON P.men_id = M.men_id
					 WHERE pag_estado = 1
					 ORDER BY M.men_orden, P.pag_orden";

			$res = $this->cnx->con()->prepare($proc);
			$res->execute();

			return $res;
		}

		// Fredy Luis
		function listar_habilitados()
		{
			$proc = "SELECT 	pag_id id
							, 	UPPER(pag_nombre) descripcion

					 FROM pagina
					 WHERE pag_estado = 1
					 ORDER BY pag_nombre";

			$res = $this->cnx->con()->prepare($proc);
			$res->execute();

			if($res){
				while ($fila = $res->fetch(PDO::FETCH_ASSOC)){
					$datos[] = $fila;
				}
					return $datos;
			}else
				return $datos[] = array('pag_id'=>'0');	

			return $res;
		}

		// Fredy Luis
		function cargar_datos()
		{
			$proc = "SELECT	* 
					 FROM pagina p 
					 WHERE p.pag_id = :pag_id";

			$res = $this->cnx->con()->prepare($proc);

			$res->bindparam(':pag_id', $_REQUEST["pag_id"]);

			$res->execute();

			if($res){
				while ($fila = $res->fetch(PDO::FETCH_ASSOC)){
					$datos[] = $fila;
				}
					return $datos;
			}else
				return $datos[] = array('pag_id'=>'0');
		}

		// Fredy Luis
		function editar()
		{
			$proc = "UPDATE pagina 	SET pag_nombre = :pag_nombre,
												pag_icono = :pag_icono,
												pag_ruta = :pag_ruta,
												men_id = :men_id,
												pag_orden = :pag_orden,
												pag_estado = :pag_estado

					 WHERE pag_id = :pag_id";

			$res = $this->cnx->con()->prepare($proc);

			$res->bindparam(':pag_nombre', $_REQUEST["pag_nombre"]);
			$res->bindparam(':pag_icono', $_REQUEST["pag_icono"]);
			$res->bindparam(':pag_ruta', $_REQUEST["pag_ruta"]);
			$res->bindparam(':men_id', $_REQUEST["men_id"]);
			$res->bindparam(':pag_orden', $_REQUEST["pag_orden"]);
			$res->bindparam(':pag_estado', $_REQUEST["pag_estado"]);
			$res->bindparam(':pag_id', $_REQUEST["pag_id"]);

			if($res->execute())
				return 1;
			else
				return 0;
		}

		// Fredy Luis
		function registrar()
		{
			$proc = "INSERT INTO pagina (men_id, pag_nombre, pag_ruta, pag_icono, pag_orden, pag_estado)
					 VALUES (:men_id, :pag_nombre, :pag_ruta, :pag_icono, :pag_orden, :pag_estado)";

			$res = $this->cnx->con()->prepare($proc);

			$res->bindparam(':men_id', $_REQUEST["men_id"]);
			$res->bindparam(':pag_nombre', $_REQUEST["pag_nombre"]);
			$res->bindparam(':pag_ruta', $_REQUEST["pag_ruta"]);
			$res->bindparam(':pag_icono', $_REQUEST["pag_icono"]);
			$res->bindparam(':pag_orden', $_REQUEST["pag_orden"]);
			$res->bindparam(':pag_estado', $_REQUEST["pag_estado"]);

			if($res->execute())
				return 1;
			else
				return 0;
		}

		// Fredy Luis
		function listar_pagina_estadoxrol_id(?int $rolId = null)
		{
			$rolId = $rolId ?? (int)($_REQUEST["rol_id"] ?? 0);
			$proc = "SELECT p.pag_id,
						UPPER(CONCAT(m.men_descripcion, ' | ', p.pag_nombre)) pag_nombre,
						CASE WHEN rp.rol_id IS NULL THEN 0 ELSE 1 END estado
				 FROM pagina p
				 JOIN menu m ON m.men_id = p.men_id
				 LEFT JOIN rol_pagina rp ON rp.pag_id = p.pag_id AND rp.rol_id = :rol_id
				 WHERE p.pag_estado = 1 AND m.men_estado = 1
				 ORDER BY m.men_orden, p.pag_orden, p.pag_id";

			$res = $this->cnx->con()->prepare($proc);
			$res->execute([':rol_id' => $rolId]);

			return $res;
		}
	}
?>
