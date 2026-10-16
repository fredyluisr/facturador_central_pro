<?php

	require_once __DIR__ . '/conexion.php';

	class tipo_pago extends conexion
	{
		private conexion $cnx;
        public function __construct()
		{
			$this->cnx = new conexion();
		}


		function listar_habilitados()
		{
			$proc = "SELECT tpa_id id, UPPER(tpa_nombre) descripcion
					 FROM tipo_pago WHERE tpa_estado = 1 ORDER BY tpa_nombre";

			$con = $this->cnx->con();
			$res = $con->prepare($proc);
			$res->execute();

			$cont  = 0;
			$datos = array();

			while($fila = $res->fetch(PDO::FETCH_ASSOC)){
				$datos[] = $fila;
				$cont++;
			}

			return $cont > 0 ? $datos : '0';
		}

		function listar()
		{
			$proc = "SELECT tp.tpa_id, tp.tpa_nombre, tp.tpa_estado,
						COUNT(CASE WHEN cb.cba_estado <> 2 THEN 1 END) AS cuentas_total,
						CASE WHEN tpa_estado = 1 THEN 'Habilitado' ELSE 'Deshabilitado' END AS estado_desc
					 FROM tipo_pago tp LEFT JOIN cuenta_bancaria cb ON cb.tpa_id=tp.tpa_id
					 WHERE tp.tpa_estado <> 2 GROUP BY tp.tpa_id,tp.tpa_nombre,tp.tpa_estado ORDER BY tp.tpa_nombre";
			$res = $this->cnx->con()->prepare($proc);
			$res->execute();
			return $res->fetchAll(PDO::FETCH_ASSOC);
		}

		function obtener_por_id($tpa_id)
		{
			$res = $this->cnx->con()->prepare("SELECT tpa_id, tpa_nombre, tpa_estado FROM tipo_pago WHERE tpa_id = :id");
			$res->execute([':id' => $tpa_id]);
			return $res->fetch(PDO::FETCH_ASSOC);
		}

		function registrar($tpa_nombre, $tpa_estado = 1)
		{
			try {
				if ($this->existe_nombre($tpa_nombre))
					return ['status' => 0, 'message' => 'Ya existe una forma de pago con ese nombre'];
				$con = $this->cnx->con();
				$res = $con->prepare("INSERT INTO tipo_pago (tpa_nombre, tpa_estado) VALUES (:nombre, :estado)");
				$res->execute([':nombre' => strtoupper(trim($tpa_nombre)), ':estado' => $tpa_estado]);
				return ['status' => 1, 'message' => 'Forma de pago registrada correctamente', 'tpa_id' => (int)$con->lastInsertId()];
			} catch (PDOException $e) {
				return ['status' => 0, 'message' => 'Error: ' . $e->getMessage()];
			}
		}

		function editar($tpa_id, $tpa_nombre, $tpa_estado)
		{
			try {
				if ($this->existe_nombre($tpa_nombre, $tpa_id))
					return ['status' => 0, 'message' => 'Ya existe una forma de pago con ese nombre'];
				$res = $this->cnx->con()->prepare("UPDATE tipo_pago SET tpa_nombre = :nombre, tpa_estado = :estado WHERE tpa_id = :id");
				$res->execute([':nombre' => strtoupper(trim($tpa_nombre)), ':estado' => $tpa_estado, ':id' => $tpa_id]);
				return ['status' => 1, 'message' => 'Forma de pago actualizada correctamente'];
			} catch (PDOException $e) {
				return ['status' => 0, 'message' => 'Error: ' . $e->getMessage()];
			}
		}

		function eliminar($tpa_id)
		{
			try {
				$res = $this->cnx->con()->prepare("UPDATE tipo_pago SET tpa_estado = 2 WHERE tpa_id = :id");
				$res->execute([':id' => $tpa_id]);
				return ['status' => 1, 'message' => 'Forma de pago eliminada'];
			} catch (PDOException $e) {
				return ['status' => 0, 'message' => 'Error: ' . $e->getMessage()];
			}
		}

		private function existe_nombre($tpa_nombre, $excluir = 0)
		{
			$res = $this->cnx->con()->prepare(
				"SELECT COUNT(*) FROM tipo_pago WHERE UPPER(tpa_nombre) = UPPER(:nombre) AND tpa_id != :excluir AND tpa_estado <> 2"
			);
			$res->execute([':nombre' => trim($tpa_nombre), ':excluir' => $excluir]);
			return $res->fetchColumn() > 0;
		}
	}
?>
