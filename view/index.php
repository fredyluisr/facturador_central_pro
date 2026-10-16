<?php
require_once __DIR__.'/../model/sesion.php';
require_once __DIR__.'/../model/var_global.php';
require_once __DIR__.'/../model/validar_sesion_pos.php';
require_once __DIR__.'/../model/catalogo_acceso.php';

validarSesionPos();
if (empty($_SESSION['rol_id_ifact'])) { header('Location: login.php'); exit; }

$nombreUsuario = trim((string)($_SESSION['nombres_ifact'] ?? $_SESSION['nombre_completo_ifact'] ?? 'Usuario'));
$nombreLocal = trim((string)($_SESSION['loc_nombre_ifact'] ?? 'Local'));
$nombreRol = trim((string)($_SESSION['rol_nombre_ifact'] ?? 'Usuario'));
$accesos = [
    ['ruta'=>'form_venta_pro.php','icono'=>'fa-cash-register','titulo'=>'Nueva venta','texto'=>'Registra productos, pagos y comprobantes.','clase'=>'principal'],
    ['ruta'=>'list_venta.php','icono'=>'fa-receipt','titulo'=>'Ventas','texto'=>'Consulta ventas, pagos y documentos emitidos.','clase'=>''],
    ['ruta'=>'list_cotizaciones.php','icono'=>'fa-file-signature','titulo'=>'Cotizaciones','texto'=>'Prepara y administra propuestas comerciales.','clase'=>''],
    ['ruta'=>'list_stock_pos.php','icono'=>'fa-boxes','titulo'=>'Stock','texto'=>'Revisa existencias disponibles en el local.','clase'=>''],
    ['ruta'=>'list_movimientos.php','icono'=>'fa-exchange-alt','titulo'=>'Movimientos','texto'=>'Registra y consulta ingresos y egresos.','clase'=>''],
    ['ruta'=>'list_entidad.php','icono'=>'fa-address-book','titulo'=>'Clientes y entidades','texto'=>'Administra la información de tus contactos.','clase'=>''],
    ['ruta'=>'dashboard.php','icono'=>'fa-chart-line','titulo'=>'Dashboard gerencial','texto'=>'Analiza ventas, utilidad, stock y movimientos.','clase'=>'gerencial'],
    ['ruta'=>'configuracion.php','icono'=>'fa-cog','titulo'=>'Configuración','texto'=>'Gestiona la empresa emisora y sus parámetros.','clase'=>'']
];
$accesosVisibles = array_values(array_filter($accesos, static fn(array $acceso): bool => catalogoPuedeAcceder($acceso['ruta'])));
$fechaTexto = (new DateTimeImmutable('now', new DateTimeZone('America/Lima')))->format('d/m/Y');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?=htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8')?></title>
    <link rel="shortcut icon" href="<?=htmlspecialchars($icono, ENT_QUOTES, 'UTF-8')?>">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css"><link rel="stylesheet" href="../assets/plugins/fontawesome/css/all.min.css"><link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../css/header.css?v=<?=filemtime(__DIR__.'/../css/header.css')?>"><link rel="stylesheet" href="../css/index.css?v=<?=filemtime(__DIR__.'/../css/index.css')?>">
</head>
<body class="ifact-home-page">
<?php include __DIR__.'/header.php'; ?>
<div class="page-wrapper"><main class="content ifact-home-content">
    <header class="ifact-home-hero">
        <div class="ifact-home-hero-copy"><span class="ifact-home-eyebrow"><i class="fas fa-th-large"></i> CENTRO DE OPERACIONES · <?=htmlspecialchars(mb_strtoupper($nombreLocal), ENT_QUOTES, 'UTF-8')?></span><h1>Hola, <?=htmlspecialchars($nombreUsuario, ENT_QUOTES, 'UTF-8')?></h1><p>Todo lo que necesitas para trabajar en iFact, organizado en un solo lugar.</p></div>
        <div class="ifact-home-session"><span><i class="fas fa-store"></i></span><div><small>SESIÓN ACTIVA</small><strong><?=htmlspecialchars($nombreLocal, ENT_QUOTES, 'UTF-8')?></strong><em><?=htmlspecialchars($nombreRol, ENT_QUOTES, 'UTF-8')?> · <?=$fechaTexto?></em></div></div>
    </header>
    <section class="ifact-home-heading"><div><span>ACCESOS DISPONIBLES</span><h2>¿Qué deseas hacer?</h2><p>Las opciones se muestran de acuerdo con los permisos de tu rol.</p></div></section>
    <?php if ($accesosVisibles): ?>
    <section class="ifact-home-grid" aria-label="Accesos disponibles">
        <?php foreach ($accesosVisibles as $acceso): ?>
        <a class="ifact-home-card <?=$acceso['clase']?>" href="<?=htmlspecialchars($acceso['ruta'], ENT_QUOTES, 'UTF-8')?>"><span class="ifact-home-card-icon"><i class="fas <?=$acceso['icono']?>"></i></span><span class="ifact-home-card-copy"><strong><?=htmlspecialchars($acceso['titulo'], ENT_QUOTES, 'UTF-8')?></strong><small><?=htmlspecialchars($acceso['texto'], ENT_QUOTES, 'UTF-8')?></small></span><i class="fas fa-arrow-right ifact-home-arrow"></i></a>
        <?php endforeach; ?>
    </section>
    <?php else: ?>
    <section class="ifact-home-empty"><i class="fas fa-shield-alt"></i><div><h2>Tu acceso está listo</h2><p>Este rol todavía no tiene páginas operativas asignadas. Solicita al administrador que configure sus permisos.</p></div></section>
    <?php endif; ?>
    <footer class="ifact-home-footer"><i class="fas fa-lock"></i> Solo se muestran funciones habilitadas para tu perfil.</footer>
</main></div>
<script src="../assets/js/jquery-3.6.0.min.js"></script><script src="../assets/js/bootstrap.bundle.min.js"></script><script src="../assets/js/feather.min.js"></script><script src="../assets/js/jquery.slimscroll.min.js"></script><script src="../assets/js/script.js"></script><script src="../js/funciones_header.js?v=<?=filemtime(__DIR__.'/../js/funciones_header.js')?>"></script>
</body></html>
