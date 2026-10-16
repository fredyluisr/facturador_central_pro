<?php

class entidad {
    
    private $cnx;
    private PDO $pdo;
    
    public function __construct() {
        require_once __DIR__ . '/conexion.php';
        $this->cnx = new conexion();
        $this->pdo = $this->cnx->con();
    }
    
   
    public function listar() {
        try {
            $sql = "SELECT 
                        ent_id,
                        ent_tipo_doc,
                        ent_nro_doc,
                        ent_nombres,
                        ent_direccion,
                        ent_marca,
                        ent_email,
                        ent_contacto,
                        ent_cliente,
                        ent_proveedor,
                        ent_estado,
                        CASE 
                            WHEN ent_tipo_doc = 0 THEN 'Doc.Trib.No.Dom.Sin:Ruc'
                            WHEN ent_tipo_doc = 1 THEN 'DNI'
                            WHEN ent_tipo_doc = 4 THEN 'Carne Extranjeria'
                            WHEN ent_tipo_doc = 6 THEN 'RUC'
                            WHEN ent_tipo_doc = 7 THEN 'Pasaporte'
                            ELSE 'Desconocido'
                        END as tipo_doc_desc,
                        CASE 
                            WHEN ent_estado = 1 THEN 'Habilitado'
                            WHEN ent_estado = 0 THEN 'Deshabilitado'
                            WHEN ent_estado = 2 THEN 'Anulado'
                            ELSE 'Desconocido'
                        END as estado_desc,
                        CASE 
                            WHEN ent_cliente = 1 AND ent_proveedor = 1 THEN 'Cliente y Proveedor'
                            WHEN ent_cliente = 1 THEN 'Cliente'
                            WHEN ent_proveedor = 1 THEN 'Proveedor'
                            ELSE 'Ninguno'
                        END as tipo_entidad
                    FROM entidad 
                    WHERE ent_estado <> 2
                    ORDER BY ent_nombres";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            echo "Error en listar: " . $e->getMessage();
            return array();
        }
    }
    

    // public	function listar_habilitados()
	// 	{
	// 		$proc = "SELECT teg_id AS id,
	// 						UPPER(teg_nombre) AS descripcion
	// 				FROM tipo_egreso
	// 				WHERE teg_estado = 1
	// 				ORDER BY UPPER(teg_nombre)";

	// 		$res = $this->cnx->con()->prepare($proc);
	// 		$res->execute();

	// 		$datos = [];

	// 		while ($fila = $res->fetch(PDO::FETCH_ASSOC)) {
	// 			$datos[] = $fila;
	// 		}

	// 		return $datos;
	// 	}
    
    public function listar_clientes() {
        try {
            $sql = "SELECT ent_id id, UPPER(ent_nombres) descripcion, ent_nro_doc
                    FROM entidad 
                    WHERE ent_estado = 1 AND ent_cliente = 1 
                    ORDER BY ent_nombres";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            echo "Error en listar_clientes: " . $e->getMessage();
            return array();
        }
    }
    
   
    public function listar_proveedores() {
        try {
            $sql = "SELECT ent_id, ent_nombres, ent_nro_doc
                    FROM entidad 
                    WHERE ent_estado = 1 AND ent_proveedor = 1 
                    ORDER BY ent_nombres";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            echo "Error en listar_proveedores: " . $e->getMessage();
            return array();
        }
    }
    
  
    public function obtener_por_id($ent_id) {
        try {
            $sql = "SELECT ent_id, ent_tipo_doc, ent_nro_doc, ent_nombres, ent_direccion,
                           ent_marca, ent_email, ent_contacto, ent_cliente, ent_proveedor, ent_estado
                    FROM entidad 
                    WHERE ent_id = :ent_id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':ent_id', $ent_id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            echo "Error en obtener_por_id: " . $e->getMessage();
            return array();
        }
    }
    
   
    public function registrar($ent_tipo_doc, $ent_nro_doc, $ent_nombres, $ent_direccion,
                              $ent_marca, $ent_email, $ent_contacto, $ent_cliente, $ent_proveedor, $ent_estado = 1) {
        try {
            // Verificar si ya existe el documento
            if ($this->existe_documento($ent_nro_doc, $ent_tipo_doc)) {
                return array('status' => 0, 'message' => 'Ya existe una entidad con este número de documento');
            }
            
            // Obtener datos del usuario y sistema
            $usuario = $_SESSION["usu_id_ifact"] ?? 0;
            $ip = $this->obtener_ip();
            $equipo = $this->obtener_equipo();
            
            $sql = "INSERT INTO entidad (
                        ent_tipo_doc, 
                        ent_nro_doc, 
                        ent_nombres,
                        ent_direccion,
                        ent_marca,
                        ent_email,
                        ent_contacto,
                        ent_cliente,
                        ent_proveedor,
                        ent_estado,
                        usuario,
                        fechahora,
                        ip,
                        equipo
                    ) VALUES (
                        :ent_tipo_doc, 
                        :ent_nro_doc, 
                        :ent_nombres,
                        :ent_direccion,
                        :ent_marca,
                        :ent_email,
                        :ent_contacto,
                        :ent_cliente,
                        :ent_proveedor,
                        :ent_estado,
                        :usuario,
                        NOW(),
                        :ip,
                        :equipo
                    )";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':ent_tipo_doc', $ent_tipo_doc, PDO::PARAM_INT);
            $stmt->bindParam(':ent_nro_doc', $ent_nro_doc, PDO::PARAM_STR);
            $stmt->bindParam(':ent_nombres', $ent_nombres, PDO::PARAM_STR);
            $stmt->bindParam(':ent_direccion', $ent_direccion, PDO::PARAM_STR);
            $stmt->bindParam(':ent_marca', $ent_marca, PDO::PARAM_STR);
            $stmt->bindParam(':ent_email', $ent_email, PDO::PARAM_STR);
            $stmt->bindParam(':ent_contacto', $ent_contacto, PDO::PARAM_STR);
            $stmt->bindParam(':ent_cliente', $ent_cliente, PDO::PARAM_INT);
            $stmt->bindParam(':ent_proveedor', $ent_proveedor, PDO::PARAM_INT);
            $stmt->bindParam(':ent_estado', $ent_estado, PDO::PARAM_INT);
            $stmt->bindParam(':usuario', $usuario, PDO::PARAM_INT);
            $stmt->bindParam(':ip', $ip, PDO::PARAM_STR);
            $stmt->bindParam(':equipo', $equipo, PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                $ent_id = $this->pdo->lastInsertId();
                return array(
                    'status' => 1, 
                    'message' => 'Entidad registrada correctamente',
                    'ent_id' => $ent_id
                );
            } else {
                return array('status' => 0, 'message' => 'Error al registrar entidad');
            }
            
        } catch (PDOException $e) {
            return array('status' => 0, 'message' => 'Error: ' . $e->getMessage());
        }
    }
    
    
    public function editar($ent_id, $ent_tipo_doc, $ent_nro_doc, $ent_nombres, $ent_direccion,
                          $ent_marca, $ent_email, $ent_contacto, $ent_cliente, $ent_proveedor, $ent_estado) {
        try {
           
            $datos = $this->obtener_por_id($ent_id);
            if (!$datos) {
                return array('status' => 0, 'message' => 'Entidad no encontrada');
            }
            
          
            if ($this->existe_documento($ent_nro_doc, $ent_tipo_doc, $ent_id)) {
                return array('status' => 0, 'message' => 'Ya existe otra entidad con este número de documento');
            }
            
           
            $usuario = $_SESSION["usu_id_ifact"] ?? 0;
            $ip = $this->obtener_ip();
            $equipo = $this->obtener_equipo();
            
            $sql = "UPDATE entidad SET 
                        ent_tipo_doc = :ent_tipo_doc,
                        ent_nro_doc = :ent_nro_doc,
                        ent_nombres = :ent_nombres,
                        ent_direccion = :ent_direccion,
                        ent_marca = :ent_marca,
                        ent_email = :ent_email,
                        ent_contacto = :ent_contacto,
                        ent_cliente = :ent_cliente,
                        ent_proveedor = :ent_proveedor,
                        ent_estado = :ent_estado,
                        ent_usu_ult_edicion = :usuario,
                        ent_fec_ult_edicion = NOW(),
                        ip = :ip,
                        equipo = :equipo
                    WHERE ent_id = :ent_id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':ent_id', $ent_id, PDO::PARAM_INT);
            $stmt->bindParam(':ent_tipo_doc', $ent_tipo_doc, PDO::PARAM_INT);
            $stmt->bindParam(':ent_nro_doc', $ent_nro_doc, PDO::PARAM_STR);
            $stmt->bindParam(':ent_nombres', $ent_nombres, PDO::PARAM_STR);
            $stmt->bindParam(':ent_direccion', $ent_direccion, PDO::PARAM_STR);
            $stmt->bindParam(':ent_marca', $ent_marca, PDO::PARAM_STR);
            $stmt->bindParam(':ent_email', $ent_email, PDO::PARAM_STR);
            $stmt->bindParam(':ent_contacto', $ent_contacto, PDO::PARAM_STR);
            $stmt->bindParam(':ent_cliente', $ent_cliente, PDO::PARAM_INT);
            $stmt->bindParam(':ent_proveedor', $ent_proveedor, PDO::PARAM_INT);
            $stmt->bindParam(':ent_estado', $ent_estado, PDO::PARAM_INT);
            $stmt->bindParam(':usuario', $usuario, PDO::PARAM_INT);
            $stmt->bindParam(':ip', $ip, PDO::PARAM_STR);
            $stmt->bindParam(':equipo', $equipo, PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                return array('status' => 1, 'message' => 'Entidad actualizada correctamente');
            } else {
                return array('status' => 0, 'message' => 'Error al actualizar entidad');
            }
            
        } catch (PDOException $e) {
            return array('status' => 0, 'message' => 'Error: ' . $e->getMessage());
        }
    }
    
    // =========================
    // ELIMINAR (CAMBIAR ESTADO A ANULADO)
    // =========================
    public function eliminar($ent_id) {
        try {
            // Verificar si el registro existe
            $datos = $this->obtener_por_id($ent_id);
            if (!$datos) {
                return array('status' => 0, 'message' => 'Entidad no encontrada');
            }
            
            // Obtener datos del usuario y sistema
            $usuario = $_SESSION["usu_id_ifact"] ?? 0;
            $ip = $this->obtener_ip();
            $equipo = $this->obtener_equipo();
            
            // En lugar de eliminar físicamente, cambiamos el estado a ANULADO (2)
            $sql = "UPDATE entidad SET 
                        ent_estado = 2,
                        ent_usu_anulado = :usuario,
                        ent_fec_anulado = NOW(),
                        ip = :ip,
                        equipo = :equipo
                    WHERE ent_id = :ent_id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':ent_id', $ent_id, PDO::PARAM_INT);
            $stmt->bindParam(':usuario', $usuario, PDO::PARAM_INT);
            $stmt->bindParam(':ip', $ip, PDO::PARAM_STR);
            $stmt->bindParam(':equipo', $equipo, PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                return array('status' => 1, 'message' => 'Entidad anulada correctamente');
            } else {
                return array('status' => 0, 'message' => 'Error al anular entidad');
            }
            
        } catch (PDOException $e) {
            return array('status' => 0, 'message' => 'Error: ' . $e->getMessage());
        }
    }
    
   
    private function existe_documento($ent_nro_doc, $ent_tipo_doc, $ent_id_excluir = 0) {
        try {
            $sql = "SELECT COUNT(*) as total 
                    FROM entidad 
                    WHERE ent_nro_doc = :ent_nro_doc 
                    AND ent_tipo_doc = :ent_tipo_doc
                    AND ent_id != :ent_id_excluir";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':ent_nro_doc', $ent_nro_doc, PDO::PARAM_STR);
            $stmt->bindParam(':ent_tipo_doc', $ent_tipo_doc, PDO::PARAM_INT);
            $stmt->bindParam(':ent_id_excluir', $ent_id_excluir, PDO::PARAM_INT);
            $stmt->execute();
            
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['total'] > 0;
            
        } catch (PDOException $e) {
            return false;
        }
    }
    
    // Obtener IP del cliente
    private function obtener_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
    }
    
    // Obtener nombre del equipo
    private function obtener_equipo() {
        return gethostbyaddr($this->obtener_ip()) ?? 'Desconocido';
    }

}

?>