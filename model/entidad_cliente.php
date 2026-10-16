<?php
require_once(__DIR__ . '/conexion.php');

class entidad_cliente
{
    private $cnx;

    public function __construct()
    {
        $this->cnx = new conexion();
    }

    public function listar($soloActivos = false)
    {
        $sql = "SELECT ent_id, ent_tipo_doc, ent_nro_doc, ent_nombres,
                       ent_contacto, ent_direccion, ent_email, ent_marca, ent_estado, fechahora
                  FROM entidad
                 WHERE ent_cliente = 1" . ($soloActivos ? " AND ent_estado = 1" : "") . "
                 ORDER BY ent_nombres ASC";
        return $this->cnx->con()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtener($entId)
    {
        $stmt = $this->cnx->con()->prepare(
            "SELECT ent_id, ent_tipo_doc, ent_nro_doc, ent_nombres, ent_contacto,
                    ent_direccion, ent_email, ent_marca, ent_estado
               FROM entidad
              WHERE ent_id = :id AND ent_cliente = 1"
        );
        $stmt->execute([':id' => $entId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function guardar($usuarioId, array $data)
    {
        $cn = $this->cnx->con();
        $documento = trim((string)($data['ent_nro_doc'] ?? ''));
        try {
            if ($documento !== '') {
                $existe = $cn->prepare(
                    "SELECT ent_id FROM entidad
                      WHERE ent_cliente = 1 AND ent_tipo_doc = :tipo
                        AND TRIM(ent_nro_doc) = :documento LIMIT 1"
                );
                $existe->execute([':tipo' => (int)$data['ent_tipo_doc'], ':documento' => $documento]);
                if ($existe->fetchColumn()) {
                    return ['estado' => 'error', 'mensaje' => 'Ya existe un cliente con ese documento'];
                }
            }

            $stmt = $cn->prepare(
                "INSERT INTO entidad
                    (ent_tipo_doc, ent_nro_doc, ent_nombres, ent_direccion, ent_email,
                     ent_contacto, ent_marca, ent_cliente, ent_proveedor, ent_estado, usuario,
                     fechahora, ip, tipo_dispositivo, equipo)
                 VALUES
                    (:tipo, :documento, :nombres, :direccion, :email,
                     :contacto, :marca, 1, 0, 1, :usuario, NOW(), :ip, 1, :equipo)"
            );
            $stmt->execute([
                ':tipo' => (int)$data['ent_tipo_doc'],
                ':documento' => $documento !== '' ? $documento : null,
                ':nombres' => strtoupper(trim((string)$data['ent_nombres'])),
                ':direccion' => trim((string)$data['ent_direccion']) ?: null,
                ':email' => trim((string)$data['ent_email']) ?: null,
                ':contacto' => trim((string)$data['ent_contacto']) ?: null,
                ':marca' => strtoupper(trim((string)$data['ent_marca'])),
                ':usuario' => $usuarioId,
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                ':equipo' => gethostname() ?: 'POS'
            ]);
            return ['estado' => 'ok', 'ent_id' => (int)$cn->lastInsertId(), 'mensaje' => 'Cliente registrado'];
        } catch (Throwable $e) {
            error_log('entidad_cliente::guardar — ' . $e->getMessage());
            return ['estado' => 'error', 'mensaje' => 'Error al registrar el cliente'];
        }
    }

    public function editar($entId, $usuarioId, array $data)
    {
        try {
            $stmt = $this->cnx->con()->prepare(
                "UPDATE entidad SET ent_tipo_doc=:tipo, ent_nro_doc=:documento,
                        ent_nombres=:nombres, ent_contacto=:contacto, ent_marca=:marca,
                        ent_direccion=:direccion, ent_email=:email,
                        ent_usu_ult_edicion=:usuario, ent_fec_ult_edicion=NOW()
                  WHERE ent_id=:id AND ent_cliente=1"
            );
            $stmt->execute([
                ':tipo' => (int)$data['ent_tipo_doc'],
                ':documento' => trim((string)$data['ent_nro_doc']) ?: null,
                ':nombres' => strtoupper(trim((string)$data['ent_nombres'])),
                ':contacto' => trim((string)$data['ent_contacto']) ?: null,
                ':marca' => strtoupper(trim((string)$data['ent_marca'])),
                ':direccion' => trim((string)$data['ent_direccion']) ?: null,
                ':email' => trim((string)$data['ent_email']) ?: null,
                ':usuario' => $usuarioId, ':id' => $entId
            ]);
            return ['estado' => 'ok', 'mensaje' => 'Cliente actualizado'];
        } catch (Throwable $e) {
            error_log('entidad_cliente::editar — ' . $e->getMessage());
            return ['estado' => 'error', 'mensaje' => 'Error al actualizar el cliente'];
        }
    }

    public function cambiarEstado($entId, $estado, $usuarioId)
    {
        $stmt = $this->cnx->con()->prepare(
            "UPDATE entidad SET ent_estado=:estado, ent_usu_ult_edicion=:usuario,
                    ent_fec_ult_edicion=NOW() WHERE ent_id=:id AND ent_cliente=1"
        );
        $stmt->execute([':estado' => $estado, ':usuario' => $usuarioId, ':id' => $entId]);
        return ['estado' => 'ok', 'mensaje' => $estado ? 'Cliente activado' : 'Cliente desactivado'];
    }
}
