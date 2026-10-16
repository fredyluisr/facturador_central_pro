<?php

require_once("../model/conexion.php");

class cuenta_bancaria extends conexion
{
    private conexion $cnx;
    public function __construct()
    {
        $this->cnx = new conexion();
    }

    function listar($tpa_id = null)
    {
        $where = "c.cba_estado <> 2";
        $params = [];
        if ((int)$tpa_id > 0) { $where .= " AND c.tpa_id = :tpa_id"; $params[':tpa_id'] = (int)$tpa_id; }
        $res = $this->cnx->con()->prepare(
            "SELECT c.cba_id, c.tpa_id, c.ban_id, COALESCE(b.ban_nombre, c.cba_banco) cba_banco, c.cba_moneda, c.cba_propietario, c.cba_numero, c.cba_estado,
                    CASE WHEN c.cba_estado = 1 THEN 'Habilitado' ELSE 'Deshabilitado' END AS estado_desc
             FROM cuenta_bancaria c LEFT JOIN banco b ON b.ban_id = c.ban_id
             WHERE $where ORDER BY COALESCE(b.ban_nombre, c.cba_banco), c.cba_moneda, c.cba_numero"
        );
        $res->execute($params);
        return $res->fetchAll(PDO::FETCH_ASSOC);
    }

    function obtener_por_id($cba_id)
    {
        $res = $this->cnx->con()->prepare(
            "SELECT c.cba_id, c.tpa_id, c.ban_id, COALESCE(b.ban_nombre, c.cba_banco) cba_banco,
                    c.cba_moneda, c.cba_propietario, c.cba_numero, c.cba_estado
             FROM cuenta_bancaria c LEFT JOIN banco b ON b.ban_id = c.ban_id WHERE c.cba_id = :id"
        );
        $res->execute([':id' => $cba_id]);
        return $res->fetch(PDO::FETCH_ASSOC);
    }

    function listar_bancos()
    {
        $res = $this->cnx->con()->query("SELECT ban_id, ban_nombre FROM banco WHERE ban_estado = 1 ORDER BY ban_id");
        return $res->fetchAll(PDO::FETCH_ASSOC);
    }

    private function obtener_banco($ban_id)
    {
        $res = $this->cnx->con()->prepare("SELECT ban_id, ban_nombre FROM banco WHERE ban_id = :id AND ban_estado = 1");
        $res->execute([':id' => (int)$ban_id]);
        return $res->fetch(PDO::FETCH_ASSOC);
    }

    function registrar($ban_id, $cba_moneda, $cba_propietario, $cba_numero, $cba_estado = 1, $tpa_id = null)
    {
        try {
            $banco = $this->obtener_banco($ban_id);
            if (!$banco) return ['status' => 0, 'message' => 'Seleccione un banco válido'];
            $res = $this->cnx->con()->prepare(
                "INSERT INTO cuenta_bancaria (tpa_id, ban_id, cba_banco, cba_moneda, cba_propietario, cba_numero, cba_estado)
                 VALUES (:tpa_id, :ban_id, :banco, :moneda, :propietario, :numero, :estado)"
            );
            $res->execute([
                ':tpa_id'      => (int)$tpa_id ?: null,
                ':ban_id'      => (int)$banco['ban_id'],
                ':banco'       => $banco['ban_nombre'],
                ':moneda'      => strtoupper(trim($cba_moneda)),
                ':propietario' => strtoupper(trim($cba_propietario)),
                ':numero'      => trim($cba_numero),
                ':estado'      => $cba_estado
            ]);
            return ['status' => 1, 'message' => 'Cuenta bancaria registrada correctamente'];
        } catch (PDOException $e) {
            return ['status' => 0, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    function editar($cba_id, $ban_id, $cba_moneda, $cba_propietario, $cba_numero, $cba_estado, $tpa_id = null)
    {
        try {
            $banco = $this->obtener_banco($ban_id);
            if (!$banco) return ['status' => 0, 'message' => 'Seleccione un banco válido'];
            $res = $this->cnx->con()->prepare(
                "UPDATE cuenta_bancaria SET
                    tpa_id = :tpa_id, ban_id = :ban_id, cba_banco = :banco, cba_moneda = :moneda,
                    cba_propietario = :propietario, cba_numero = :numero, cba_estado = :estado
                 WHERE cba_id = :id"
            );
            $res->execute([
                ':id'          => $cba_id,
                ':tpa_id'      => (int)$tpa_id ?: null,
                ':ban_id'      => (int)$banco['ban_id'],
                ':banco'       => $banco['ban_nombre'],
                ':moneda'      => strtoupper(trim($cba_moneda)),
                ':propietario' => strtoupper(trim($cba_propietario)),
                ':numero'      => trim($cba_numero),
                ':estado'      => $cba_estado
            ]);
            return ['status' => 1, 'message' => 'Cuenta bancaria actualizada correctamente'];
        } catch (PDOException $e) {
            return ['status' => 0, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    function eliminar($cba_id)
    {
        try {
            $res = $this->cnx->con()->prepare("UPDATE cuenta_bancaria SET cba_estado = 2 WHERE cba_id = :id");
            $res->execute([':id' => $cba_id]);
            return ['status' => 1, 'message' => 'Cuenta bancaria eliminada'];
        } catch (PDOException $e) {
            return ['status' => 0, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
}
?>
