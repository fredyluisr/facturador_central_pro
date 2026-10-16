<?php

require_once("../model/conexion.php");

class rol_pagina extends conexion
{
    /**
     * Conexión a base de datos
     */
    protected conexion $cnx;

    public function __construct()
    {
        // Evita propiedad dinámica (PHP 8.2)
        $this->cnx = new conexion();
    }

    // Cargar Páginas por Rol y Menú
    public function cargar_paginaxrol_menu(int $men_id): array|string
    {
        $sql = "SELECT  p.pag_id,
                        p.men_id,
                        p.pag_nombre,
                        p.pag_ruta,
                        p.pag_icono,
                        p.pag_orden
                FROM pagina p
                JOIN menu m ON m.men_id = p.men_id
                JOIN rol_pagina rp ON rp.pag_id = p.pag_id
                WHERE rp.rol_id = :rol_id
                  AND m.men_id = :men_id
                  AND p.pag_estado = 1
                ORDER BY m.men_orden, p.pag_orden";

        $stmt = $this->cnx->con()->prepare($sql);

        $stmt->bindParam(':rol_id', $_SESSION["rol_id_ifact"], PDO::PARAM_INT);
        $stmt->bindParam(':men_id', $men_id, PDO::PARAM_INT);

        $stmt->execute();

        $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return count($datos) > 0 ? $datos : '0';
    }
}
