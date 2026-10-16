<?php
require_once __DIR__ . '/conexion.php';
class facturacion extends conexion
{
    protected conexion $cnx;
    public function __construct(){ $this->cnx=new conexion(); }
    public function registrar(){ return json_encode(['estado'=>'error','mensaje'=>'La emisión se realiza desde ventas mediante la central.']); }
    public function registrar_no_declarados(){ return $this->registrar(); }
		function listar_notas_venta(): array
		{
			$cn = $this->cnx->con();
			$ven_id = isset($_REQUEST['ven_id']) ? (int)$_REQUEST['ven_id'] : 0;

			if ($ven_id <= 0) {
				return ['estado' => 'error', 'mensaje' => 'Venta no valida'];
			}

			try {
				$stmt = $cn->prepare(
					"SELECT n.fac_id, n.fac_tipo_doc, n.fac_serie, n.fac_correlativo,
					        n.fac_fecha_emision, n.fac_monto_imp_venta, n.fac_estado_sunat,
					        n.fac_motivo_descripcion,
					        CONCAT(o.fac_serie,'-',LPAD(o.fac_correlativo,8,'0')) AS documento_referencia
					 FROM facturacion n
					 LEFT JOIN facturacion o ON o.fac_id=n.fac_doc_referencia_id
					 WHERE n.ven_id = :ven_id
					   AND n.fac_tipo_doc IN ('07', '08')
					   AND n.fac_estado = 2
					 ORDER BY n.fac_id DESC"
				);
				$stmt->execute([':ven_id' => $ven_id]);
				$notas = $stmt->fetchAll(PDO::FETCH_ASSOC);

				$totalCredito = 0;
				$totalDebito = 0;
				foreach ($notas as &$nota) {
					$nota['tipo_nombre'] = $nota['fac_tipo_doc'] === '07' ? 'NC' : 'ND';
					$nota['documento'] = $nota['fac_serie'] . '-' . str_pad((string)$nota['fac_correlativo'], 8, '0', STR_PAD_LEFT);
					$nota['total'] = number_format((float)$nota['fac_monto_imp_venta'], 2, '.', '');
					if ($nota['fac_tipo_doc'] === '07') $totalCredito++;
					if ($nota['fac_tipo_doc'] === '08') $totalDebito++;
				}
				unset($nota);

				return [
					'estado' => 'ok',
					'total' => count($notas),
					'total_credito' => $totalCredito,
					'total_debito' => $totalDebito,
					'notas' => $notas,
				];
			} catch (Exception $e) {
				error_log('ERROR facturacion::listar_notas_venta - ' . $e->getMessage());
				return ['estado' => 'error', 'mensaje' => $e->getMessage()];
			}
		}

}
