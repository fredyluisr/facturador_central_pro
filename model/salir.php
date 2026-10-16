<?php

require_once("sesion.php");
require_once("conexion.php");

try {
    $cnx = new conexion();
    $pdo = $cnx->con();
    
    $session_id = session_id();
    
   
    $sql = "UPDATE sessions 
            SET fecha_logout = NOW(), 
                last_activity = UNIX_TIMESTAMP(), 
                session_status = 0,
                logout_reason = 'manual',
                updated_at = NOW()
            WHERE id = :session_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':session_id' => $session_id]);
    
   
    session_destroy();
    
   
    $cookie = session_get_cookie_params();
    setcookie(session_name(), '', ['expires'=>time()-3600,'path'=>$cookie['path'],
        'domain'=>$cookie['domain'],'secure'=>$cookie['secure'],'httponly'=>true,'samesite'=>'Lax']);
    
    
    cancelarRecuerdoIFact($pdo);
    header("Location: ../view/login.php");
    
} catch (Exception $e) {
    
    session_destroy();
    header("Location: ../view/login.php");
}

?>
