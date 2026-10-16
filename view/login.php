<?php
require_once __DIR__.'/../model/sesion_central.php';
if(!empty($_SESSION['facturador_admin_id'])){header('Location: dashboard_central.php');exit;}
$error=$_SESSION['login_error']??null;$usuario=$_SESSION['login_usuario']??'';unset($_SESSION['login_error'],$_SESSION['login_usuario']);$csrf=tokenCsrfCentral();
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="description" content="Administración central de facturación electrónica de Intelsoft Perú"><title>Facturador Central · Intelsoft Perú</title><link rel="icon" href="../assets/img/ifact.ico"><link rel="stylesheet" href="../css/login_central.css?v=<?=filemtime(__DIR__.'/../css/login_central.css')?>"></head>
<body><main class="login-layout">
<section class="login-form-panel"><div class="login-form-wrap">
<a class="brand" href="../index.php" aria-label="Facturador Central"><img src="../assets/img/ifact-symbol.png" alt=""><span><strong>Facturador</strong> Central<small>by Intelsoft Perú</small></span></a>
<div class="login-heading"><span class="overline">Panel administrativo</span><h1>Bienvenido de nuevo</h1><p>Ingresa tus credenciales para gestionar la operación de facturación.</p></div>
<?php if($error):?><div class="alert" role="alert"><span>!</span><?=htmlspecialchars((string)$error,ENT_QUOTES,'UTF-8')?></div><?php endif;?>
<form method="post" action="../controller/auth_central.php" id="loginForm" novalidate><input type="hidden" name="csrf" value="<?=htmlspecialchars($csrf,ENT_QUOTES,'UTF-8')?>">
<div class="field"><label for="username">Usuario</label><i><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4.5 21a7.5 7.5 0 0 1 15 0"/></svg></i><input id="username" name="username" value="<?=htmlspecialchars((string)$usuario,ENT_QUOTES,'UTF-8')?>" placeholder="Escribe tu usuario" autocomplete="username" maxlength="50" required autofocus></div>
<div class="field"><label for="password">Contraseña</label><i><svg viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg></i><input id="password" name="password" type="password" placeholder="Escribe tu contraseña" autocomplete="current-password" required><button class="password-toggle" type="button" aria-label="Mostrar contraseña"><svg viewBox="0 0 24 24"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/></svg></button></div>
<button class="login-button" type="submit"><span>Ingresar al panel</span><svg viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button></form>
<p class="secure-note"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></svg> Sesión protegida y cifrada</p>
</div></section>
<section class="login-showcase"><div class="grid"></div><div class="orb"></div><div class="showcase-content"><span class="status"><i></i> Plataforma operativa</span><h2>Facturación electrónica<br><em>central, clara y segura.</em></h2><p>Administra empresas, credenciales y operaciones tributarias desde una plataforma creada para crecer contigo.</p><div class="benefits"><span><i>✓</i> Conexión SUNAT</span><span><i>✓</i> Gestión centralizada</span><span><i>✓</i> Acceso seguro</span></div></div><p class="copyright">© <?=date('Y')?> Intelsoft Perú · Tecnología y sistemas</p></section>
</main><script src="../js/login_central.js?v=<?=filemtime(__DIR__.'/../js/login_central.js')?>"></script></body></html>
