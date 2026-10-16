<?php

require_once("../model/conexion.php");

class rol_menu extends conexion
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

    // Fredy Luis - Obtener Menús asignados por Rol
    public function cantidad_menuxrol_id(): array|string
    {
        $sql = "SELECT M.men_id
                FROM menu M
                JOIN rol_menu R ON R.men_id = M.men_id
                WHERE M.men_estado = 1
                  AND R.rol_id = :rol_id";

        $stmt = $this->cnx->con()->prepare($sql);
        $stmt->bindParam(':rol_id', $_SESSION["rol_id_ifact"], PDO::PARAM_INT);
        $stmt->execute();

        $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return count($datos) > 0 ? $datos : '0';
    }

    // Fredy Luis - Cargar Menu x Rol
    public function cargar_menuxrol(): array|string
    {
        $sql = "SELECT  m.men_id,
                        m.men_descripcion,
                        m.men_icono
                FROM menu m
                JOIN rol_menu r ON r.men_id = m.men_id
                WHERE m.men_estado = 1
                  AND r.rol_id = :rol_id
                  AND EXISTS (
                      SELECT 1 FROM pagina p
                      JOIN rol_pagina rp ON rp.pag_id = p.pag_id
                      WHERE p.men_id = m.men_id
                        AND p.pag_estado = 1
                        AND rp.rol_id = r.rol_id
                  )
                ORDER BY m.men_orden";

        $stmt = $this->cnx->con()->prepare($sql);
        $stmt->bindParam(':rol_id', $_SESSION["rol_id_ifact"], PDO::PARAM_INT);
        $stmt->execute();

        $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return count($datos) > 0 ? $datos : '0';
    }

    // Fredy Luis - Obtener cantidad de SubMenus x Menu
    public function obtener_cant_submenuxmen_id(int $men_id): int
    {
        $sql = "SELECT COUNT(0) AS cantidad
                FROM rol_pagina r
                JOIN pagina p ON p.pag_id = r.pag_id
                WHERE p.men_id = :men_id
                  AND p.pag_estado = 1
                  AND r.rol_id = :rol_id";

        $stmt = $this->cnx->con()->prepare($sql);
        $stmt->bindParam(':men_id', $men_id, PDO::PARAM_INT);
        $stmt->bindParam(':rol_id', $_SESSION["rol_id_ifact"], PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ? (int)$fila['cantidad'] : -1;
    }
}
