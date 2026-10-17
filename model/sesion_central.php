<?php
declare(strict_types=1);
date_default_timezone_set('America/Lima');
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode','1');
    ini_set('session.use_only_cookies','1');
    session_name('FACTURADORCENTRALSESSID');
    $secure=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO']??''))==='https';
    session_set_cookie_params(['lifetime'=>10800,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
    session_start();
}
if(!headers_sent()){
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}
function tokenCsrfCentral(): string { if(empty($_SESSION['csrf_central'])) $_SESSION['csrf_central']=bin2hex(random_bytes(32)); return (string)$_SESSION['csrf_central']; }
function validarCsrfCentral(?string $token): bool { $saved=(string)($_SESSION['csrf_central']??''); return $saved!==''&&is_string($token)&&hash_equals($saved,$token); }
function sesionCentralActiva(): bool {
    if(empty($_SESSION['facturador_admin_id']))return false;
    $last=(int)($_SESSION['facturador_ultima_actividad']??$_SESSION['facturador_login_at']??0);
    if($last<1||time()-$last>10800){$_SESSION=[];return false;}
    $_SESSION['facturador_ultima_actividad']=time();return true;
}
function exigirSesionCentral(): void { if(!sesionCentralActiva()){header('Location: login.php');exit;} }
function liberarSesionCentral(): void { if(session_status()===PHP_SESSION_ACTIVE)session_write_close(); }
