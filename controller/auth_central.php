<?php
declare(strict_types=1);
require_once __DIR__.'/../model/sesion_central.php';
require_once __DIR__.'/../model/usuario_central.php';
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){header('Location: ../view/login.php');exit;}
$usuario=trim((string)($_POST['username']??'')); $clave=(string)($_POST['password']??''); $_SESSION['login_usuario']=$usuario;
if(!validarCsrfCentral($_POST['csrf']??null)){$_SESSION['login_error']='La sesión de seguridad venció. Actualiza la página e inténtalo nuevamente.';header('Location: ../view/login.php');exit;}
if($usuario===''||$clave===''){$_SESSION['login_error']='Completa tu usuario y contraseña para continuar.';header('Location: ../view/login.php');exit;}
try{$admin=UsuarioCentral::autenticar($usuario,$clave);if(!$admin){$_SESSION['login_error']='El usuario o la contraseña no son correctos.';header('Location: ../view/login.php');exit;}session_regenerate_id(true);$_SESSION['facturador_admin_id']=(int)$admin['usu_id'];$_SESSION['facturador_admin_usuario']=(string)$admin['usu_usuario'];$_SESSION['facturador_login_at']=time();unset($_SESSION['login_usuario'],$_SESSION['login_error']);header('Location: ../view/dashboard_central.php');}
catch(Throwable $e){error_log('Facturador Central - fallo de acceso: '.$e->getMessage());$_SESSION['login_error']='No pudimos conectar con el servicio central. Inténtalo nuevamente en unos minutos.';header('Location: ../view/login.php');}exit;
