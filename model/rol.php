<?php

	require_once("../model/conexion.php");

	class rol extends conexion
	{
		public function __construct()
		{
			$this->cnx = new conexion();
		}

		// Fredy Luis
		function listar()
		{
			$esWebmaster = (int)($_SESSION['rol_id_ifact'] ?? 0) === 1;
			$proc = "SELECT     R.rol_id
							,	R.rol_nombre
							,	R.rol_descripcion
							,	R.rol_estado

					 FROM rol R
					 WHERE rol_estado <> 2" . ($esWebmaster ? "" : " AND R.rol_id <> 1") . "
					 ORDER BY R.rol_nombre";

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
				return $datos[] = array('men_id'=>'0');
		}

		// Fredy Luis
		function cargar_datos()
		{
			$proc = "SELECT		R.rol_id
							,	R.rol_nombre
							,	R.rol_descripcion
							,	R.rol_estado

					FROM rol R
					WHERE R.rol_id = :rol_id";

			$res = $this->cnx->con()->prepare($proc);

			$res->bindparam(':rol_id', $_REQUEST["rol_id"]);

			$res->execute();

			if($res){
				while ($fila = $res->fetch(PDO::FETCH_ASSOC)){
					$datos[] = $fila;
				}

				return $datos;
			}else
				return $datos[] = array('rol_id'=>'0');
		}

		// Fredy Luis
		function editar()
		{
			$proc = "UPDATE rol SET rol_nombre = :rol_nombre,
									rol_descripcion = :rol_descripcion,
									rol_estado = :rol_estado
					 WHERE rol_id = :rol_id";

			$res = $this->cnx->con()->prepare($proc);

			$res->bindparam(':rol_nombre', $_REQUEST["rol_nombre"]);
			$res->bindparam(':rol_descripcion', $_REQUEST["rol_descripcion"]);
			$res->bindparam(':rol_estado', $_REQUEST["rol_estado"]);
			$res->bindparam(':rol_id', $_REQUEST["rol_id"]);

			if($res->execute())
				return 1;
			else
				return 0;
		}

		// Fredy Luis
		function registrar()
		{
			$proc = "INSERT INTO rol (rol_nombre, rol_descripcion, rol_estado)
					 VALUES (:rol_nombre, :rol_descripcion, :rol_estado)";

			$res = $this->cnx->con()->prepare($proc);

			$res->bindparam(':rol_nombre', $_REQUEST["rol_nombre"]);
			$res->bindparam(':rol_descripcion', $_REQUEST["rol_descripcion"]);
			$res->bindparam(':rol_estado', $_REQUEST["rol_estado"]);

			if($res->execute())
				return 1;
			else
				return 0;
		}

		// Fredy Luis
		function listar_habilitados($ignorado = null)
		{
			$esWebmaster = (int)($_SESSION['rol_id_ifact'] ?? 0) === 1;
			$proc = "SELECT 	rol_id id
							, 	UPPER(rol_nombre) descripcion

					 FROM rol
					 WHERE rol_estado = 1" . ($esWebmaster ? "" : " AND rol_id <> 1") . "
					 ORDER BY rol_nombre";

			$res = $this->cnx->con()->prepare($proc);
			$res->execute();

			if($res){
				while ($fila = $res->fetch(PDO::FETCH_ASSOC)){
					$datos[] = $fila;
				}

				return $datos;
			}else
				return $datos[] = array('rol_id'=>'0');
		}

		// Fredy Luis
		function editar_permisosxrol()
		{
			$proc_rol_menu = "DELETE FROM rol_menu WHERE rol_id = ".$_REQUEST["rol_id"]."";
			$resultado_rol_menu = $this->cnx->con()->prepare($proc_rol_menu);
			$resultado_rol_menu->execute();

			$lista_menu = explode(",", $_REQUEST["lista_rol_menu"]);
 
			for ($i = 0; $i < count($lista_menu); $i++){
				$proc_insert_menu = "INSERT INTO rol_menu (rol_id, men_id) VALUES (".$_REQUEST["rol_id"].",".$lista_menu[$i].")";
				$res_proc_rol_menu = $this->cnx->con()->prepare($proc_insert_menu);
				$res_proc_rol_menu->execute();
			}

			$proc_rol_pagina = "DELETE FROM rol_pagina WHERE rol_id = ".$_REQUEST["rol_id"]."";
			$resultado_pagina = $this->cnx->con()->prepare($proc_rol_pagina);
			$resultado_pagina->execute();

			$lista_pagina = explode(",", $_REQUEST["lista_rol_pagina"]);
 
			for ($i = 0; $i < count($lista_pagina); $i++){
				$proc_insert_pagina = "INSERT INTO rol_pagina (rol_id, pag_id) VALUES (".$_REQUEST["rol_id"].",".$lista_pagina[$i].")";
				$res_proc_rol_pagina = $this->cnx->con()->prepare($proc_insert_pagina);
				$res_proc_rol_pagina->execute();
			}

			return $resultado_rol_menu;
		}
	}
?>
