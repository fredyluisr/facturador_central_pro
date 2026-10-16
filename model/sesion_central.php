<?php
declare(strict_types=1);
date_default_timezone_set('America/Lima');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('FACTURADORCENTRALSESSID');
    session_set_cookie_params(['lifetime'=>10800,'path'=>'/','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Lax']);
    session_start();
}
function tokenCsrfCentral(): string { if(empty($_SESSION['csrf_central'])) $_SESSION['csrf_central']=bin2hex(random_bytes(32)); return (string)$_SESSION['csrf_central']; }
function validarCsrfCentral(?string $token): bool { $saved=(string)($_SESSION['csrf_central']??''); return $saved!==''&&is_string($token)&&hash_equals($saved,$token); }
function exigirSesionCentral(): void { if(empty($_SESSION['facturador_admin_id'])){header('Location: login.php');exit;} }
