<?php
declare(strict_types=1);
require_once __DIR__.'/conexion_central.php';
final class UsuarioCentral {
    public static function autenticar(string $usuario,string $clave): ?array {
        $stmt=ConexionCentral::conectar()->prepare('SELECT usu_id,usu_usuario,usu_password_hash FROM usuario_intelsoft WHERE usu_usuario=:usuario AND usu_activo=1 LIMIT 1');
        $stmt->execute(['usuario'=>$usuario]); $row=$stmt->fetch();
        if(!$row||!password_verify($clave,(string)$row['usu_password_hash']))return null;
        unset($row['usu_password_hash']); return $row;
    }
}
