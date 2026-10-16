<?php

require_once __DIR__ . '/conexion.php';

class foto_perfil extends conexion
{
    private $cnx;

    public function __construct()
    {
        $this->cnx = new conexion();
    }

    public function obtener_por_empleado($emp_id)
    {
        $sql = "SELECT emp_id,
                       UPPER(CONCAT(emp_nombres, ' ', emp_ape_paterno, ' ', emp_ape_materno)) AS nombre,
                       emp_foto
                FROM empleado
                WHERE emp_id = :emp_id
                  AND emp_estado <> 2";
        $stmt = $this->cnx->con()->prepare($sql);
        $stmt->execute([':emp_id' => (int)$emp_id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function actualizar($emp_id, $archivo)
    {
        $sql = "UPDATE empleado
                SET emp_foto = :archivo,
                    emp_foto_web = :archivo,
                    emp_usu_ult_edicion = :usuario,
                    emp_fec_ult_edicion = NOW()
                WHERE emp_id = :emp_id";
        $stmt = $this->cnx->con()->prepare($sql);

        return $stmt->execute([
            ':archivo' => $archivo,
            ':usuario' => (int)($_SESSION['usu_id_ifact'] ?? 0),
            ':emp_id' => (int)$emp_id
        ]);
    }

    public function eliminar($emp_id)
    {
        return $this->actualizar($emp_id, null);
    }
}

