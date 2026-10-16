<?php
require_once __DIR__ . "/comprobantes_pos.php";
require_once __DIR__ . '/conexion.php';

class venta
{
    private $cnx;

    public function __construct()
    {
        $this->cnx = new conexion();
    }

    // ==================== LISTAR VENTAS ====================
    public function listar($loc_id)
    {
        $sql = "SELECT
                    v.ven_id, v.ven_fecha, v.ven_total, v.ven_subtotal, v.ven_igv,
                    v.ven_estado, v.ven_tipo, v.tde_id, v.ven_serie, v.ven_numero, v.ven_nota_serie, v.ven_nota_numero, v.fac_id,
                    v.ven_pagado, GREATEST(0,v.ven_total-v.ven_pagado) AS ven_saldo,
                    tde.tde_nombre, tde.tde_codigo, f.fac_estado,
                    a.loc_nombre,
                    CONCAT(e.emp_nombres, ' ', e.emp_ape_paterno) AS usuario,
                    tp.tpa_nombre AS forma_pago,
                    v.ven_num_operacion,
                    cp.ent_nombres AS cliente,
                    cp.ent_nro_doc AS cliente_documento,
                    (SELECT CONCAT(fn.fac_serie,'-',LPAD(fn.fac_correlativo,8,'0'))
                       FROM facturacion fn WHERE fn.ven_id=v.ven_id AND fn.fac_tipo_doc='07' AND fn.fac_estado=2
                       ORDER BY fn.fac_id DESC LIMIT 1) AS nota_credito_documento,
                    (SELECT fn.fac_id FROM facturacion fn
                       WHERE fn.ven_id=v.ven_id AND fn.fac_tipo_doc='07' AND fn.fac_estado=2
                       ORDER BY fn.fac_id DESC LIMIT 1) AS nota_credito_fac_id,
                    (SELECT COUNT(*) FROM venta_detalle_producto vdp WHERE vdp.ven_id=v.ven_id) AS total_items
                FROM venta v
                INNER JOIN local a ON a.loc_id=v.loc_id
                INNER JOIN usuario u    ON u.usu_id  = v.usu_id
                INNER JOIN empleado e   ON e.emp_id  = u.emp_id
                LEFT  JOIN tipo_pago tp ON tp.tpa_id = v.ven_tpa_id
                LEFT  JOIN entidad cp ON cp.ent_id = v.ent_id AND cp.ent_cliente = 1
                LEFT  JOIN facturacion f ON f.fac_id = v.fac_id
                LEFT  JOIN " . comprobantes_pos::SQL . " tde ON tde.tde_id = v.tde_id
                WHERE v.loc_id = :loc_id
                ORDER BY v.ven_id DESC";

        $stmt = $this->cnx->con()->prepare($sql);
        $stmt->bindParam(':loc_id', $loc_id, PDO::PARAM_INT);
        $stmt->execute();

        $datos = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC))
            $datos[] = $fila;

        return $datos;
    }

    // ==================== HISTORIAL ====================
    public function historial($loc_id, $desde = null, $hasta = null)
    {
        $where_fecha = "";
        $params = [':loc_id' => $loc_id];

        if ($desde) {
            $where_fecha .= " AND DATE(v.ven_fecha) >= :desde";
            $params[':desde'] = $desde;
        }
        if ($hasta) {
            $where_fecha .= " AND DATE(v.ven_fecha) <= :hasta";
            $params[':hasta'] = $hasta;
        }

        $sql = "SELECT
                    v.ven_id, v.ven_fecha, v.ven_total, v.ven_subtotal, v.ven_igv,
                    v.ven_estado, v.ven_tipo, v.tde_id, v.ven_serie, v.ven_numero, v.ven_nota_serie, v.ven_nota_numero, v.fac_id,
                    v.ven_pagado, GREATEST(0,v.ven_total-v.ven_pagado) AS ven_saldo,
                    v.ven_num_operacion, v.ven_comentario,
                    tde.tde_nombre, tde.tde_codigo,
                    cp.ent_nombres AS cliente,
                    cp.ent_nro_doc AS cliente_documento,
                    (SELECT CONCAT(fn.fac_serie,'-',LPAD(fn.fac_correlativo,8,'0'))
                       FROM facturacion fn WHERE fn.ven_id=v.ven_id AND fn.fac_tipo_doc='07' AND fn.fac_estado=2
                       ORDER BY fn.fac_id DESC LIMIT 1) AS nota_credito_documento,
                    (SELECT fn.fac_id FROM facturacion fn
                       WHERE fn.ven_id=v.ven_id AND fn.fac_tipo_doc='07' AND fn.fac_estado=2
                       ORDER BY fn.fac_id DESC LIMIT 1) AS nota_credito_fac_id,
                    (SELECT COUNT(*) FROM venta_detalle_producto vdp WHERE vdp.ven_id=v.ven_id) AS total_items,
                    a.loc_nombre,
                    tp.tpa_nombre AS forma_pago,
                    CONCAT(e.emp_nombres, ' ', e.emp_ape_paterno) AS usuario,
                    f.fac_estado, f.fac_estado_sunat, f.fac_respuesta_estado,
                    f.fac_respuesta_mensaje, f.fac_respuesta_json, f.fac_respuesta_fecha,
                    f.fac_reintentos, f.fac_ultimo_reintento, f.fac_proximo_reintento,
                    DATE_ADD(COALESCE(f.fac_fecha_emision,v.ven_fecha), INTERVAL 3 DAY) AS fac_plazo_limite,
                    CASE WHEN NOW() >= DATE_ADD(COALESCE(f.fac_fecha_emision,v.ven_fecha), INTERVAL 3 DAY) THEN 1 ELSE 0 END AS fac_plazo_vencido
                FROM venta v
                INNER JOIN local a ON a.loc_id=v.loc_id
                INNER JOIN usuario u ON u.usu_id = v.usu_id
                INNER JOIN empleado e ON e.emp_id = u.emp_id
                LEFT  JOIN tipo_pago tp ON tp.tpa_id = v.ven_tpa_id
                LEFT  JOIN entidad cp ON cp.ent_id = v.ent_id AND cp.ent_cliente = 1
                LEFT  JOIN " . comprobantes_pos::SQL . " tde ON tde.tde_id = v.tde_id
                LEFT  JOIN facturacion f ON f.fac_id = v.fac_id
                WHERE v.loc_id = :loc_id
                $where_fecha
                ORDER BY v.ven_id DESC";

        $stmt = $this->cnx->con()->prepare($sql);
        $stmt->execute($params);

        $datos = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC))
            $datos[] = $fila;

        return $datos;
    }

    // ==================== VER DETALLE ====================
    public function ver_detalle($ven_id)
    {
        $sql = "SELECT vd.vdp_id AS vde_id, COALESCE(vd.vdp_nombre,p.pro_nombre) AS ins_nombre, COALESCE(vd.unidad_abrev_historica,u.und_abrev) AS unidad,
                       vd.vdp_cantidad AS vde_cantidad, vd.vdp_precio_unit AS vde_precio_unit, vd.vdp_subtotal AS vde_subtotal
                FROM venta_detalle_producto vd
                LEFT JOIN producto       p ON p.pro_id = vd.pro_id
                LEFT JOIN unidad_medida u ON u.und_id = vd.und_med
                WHERE vd.ven_id = :ven_id
                ORDER BY vd.vdp_id ASC";

        $stmt = $this->cnx->con()->prepare($sql);
        $stmt->bindParam(':ven_id', $ven_id, PDO::PARAM_INT);
        $stmt->execute();

        $datos = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC))
            $datos[] = $fila;

        return $datos;
    }

    // ==================== ALM_ID DE FÁBRICA ====================
    private function alm_fabrica()
    {
        $stmt = $this->cnx->con()->prepare(
            "SELECT loc_id FROM local WHERE loc_estado=1 LIMIT 1"
        );
        $stmt->execute();
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? (int)$fila['loc_id'] : 1;
    }

    // ==================== LISTAR INSUMOS (stock por tienda) ====================
    public function listar_insumos($loc_id) { return []; }
    public function obtener_stock($ins_id, $loc_id) { return false; }

    // ==================== LISTAR PRODUCTOS (stock por tienda) ====================
    public function listar_productos($loc_id)
    {
        $sql = "SELECT p.pro_id, p.pro_nombre, p.pro_prec_venta, u.und_id, u.und_abrev,
                    GREATEST(0, COALESCE(
                        (SELECT SUM(CASE WHEN msp_tipo = 1 THEN msp_cantidad ELSE -msp_cantidad END)
                         FROM movimientos_stock_producto WHERE pro_id = p.pro_id AND loc_id = :loc_id), 0)) AS pro_stock
                FROM producto p
                INNER JOIN unidad_medida u ON u.und_id = p.und_med
                WHERE p.pro_estado = 1
                ORDER BY p.pro_nombre";

        $stmt = $this->cnx->con()->prepare($sql);
        $stmt->bindParam(':loc_id', $loc_id, PDO::PARAM_INT);
        $stmt->execute();

        $datos = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC))
            $datos[] = $fila;

        return $datos;
    }

    // ==================== STOCK DE UN PRODUCTO EN LA TIENDA ====================
    public function obtener_stock_producto($pro_id, $loc_id)
    {
        $sql = "SELECT p.pro_id, p.pro_nombre, u.und_abrev,
                    GREATEST(0, COALESCE(
                        (SELECT SUM(CASE WHEN msp_tipo = 1 THEN msp_cantidad ELSE -msp_cantidad END)
                         FROM movimientos_stock_producto WHERE pro_id = p.pro_id AND loc_id = :loc_id), 0)) AS pro_stock
                FROM producto p
                INNER JOIN unidad_medida u ON u.und_id = p.und_med
                WHERE p.pro_id = :pro_id";

        $stmt = $this->cnx->con()->prepare($sql);
        $stmt->bindParam(':pro_id', $pro_id, PDO::PARAM_INT);
        $stmt->bindParam(':loc_id', $loc_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ==================== LISTAR TIPOS DE PAGO ====================
    public function listar_tipos_pago()
    {
        $sql = "SELECT tpa_id, tpa_nombre FROM tipo_pago WHERE tpa_estado = 1 ORDER BY tpa_nombre";
        $stmt = $this->cnx->con()->prepare($sql);
        $stmt->execute();

        $datos = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC))
            $datos[] = $fila;

        return $datos;
    }

    // ==================== REGISTRAR VENTA ====================
    public function registrar($loc_id, $usu_id, $ent_id, $tpa_id, $num_operacion, $comentario, $detalle, $tde_id = 0, $cba_id = null, $ven_tipo = 1) {
        return ['estado'=>'error','mensaje'=>'Utilice el registro actual de ventas del catálogo.'];
    }
    public function anular($ven_id, $usu_id) {
        return ['estado'=>'error','mensaje'=>'Utilice la anulación actual de ventas del catálogo.'];
    }
}
