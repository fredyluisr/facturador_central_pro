<?php
require_once __DIR__ . '/../model/sesion.php';
require_once('../model/var_global.php');

if ((int)($_SESSION['rol_id_ifact'] ?? 0) !== 1) {
    header("Location: $pagina_redireccion");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="<?php echo $icono; ?>">
    <title><?php echo $titulo; ?></title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/plugins/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="../assets/plugins/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/plugins/toastr/toatr.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../css/header.css?v=<?= filemtime(__DIR__.'/../css/header.css') ?>">
    <link rel="stylesheet" href="../css/configuracion.css?v=2026090501">
<link rel="stylesheet" href="../css/pos_ui.css"><link rel="stylesheet" href="../css/configuracion_ifact.css?v=<?php echo filemtime('../css/configuracion_ifact.css'); ?>"></head>
<body class="pos-page">
<?php include('header.php'); ?>
<div class="page-wrapper">
    <div class="content configuration-page">
        <section class="config-hero">
            <div><span class="config-eyebrow"><i class="fas fa-sliders-h"></i> CENTRO DE CONTROL</span><h1>Configuración del sistema</h1><p>Administra la empresa emisora y la presentación de los comprobantes.</p></div>
            <div class="config-hero-mark"><i class="fas fa-cog"></i></div>
        </section>
        <div id="config_content" class="d-none">
            <section class="config-status-grid">
                <article class="status-card"><span class="status-icon amber"><i class="fas fa-building"></i></span><div><small>Empresa emisora</small><strong id="cfg_empresa">—</strong><span id="cfg_ruc">—</span></div></article>
                <article class="status-card"><span class="status-icon blue"><i class="fas fa-flask"></i></span><div><small>Entorno QPSE</small><strong id="cfg_modo">—</strong><span>Administrado en la central</span></div></article>
                <article class="status-card"><span class="status-icon green"><i class="fas fa-link"></i></span><div><small>Conexión</small><strong id="cfg_conexion">—</strong><span>API de facturación</span></div></article>
                <article class="status-card"><span class="status-icon violet"><i class="fas fa-shield-alt"></i></span><div><small>Estado</small><strong id="cfg_estado">—</strong><span>Credenciales protegidas</span></div></article>
            </section>
            <section class="config-panel company-panel">
                <div class="config-panel-heading"><span class="panel-icon"><i class="fas fa-building"></i></span><div><h2>Empresa emisora</h2><p>Busca por RUC o razón social y vincula este sistema con una empresa registrada en la central.</p></div></div>
                <div class="company-search-field"><label for="cfg_buscar_empresa">RUC o razón social</label><div class="input-group"><input type="text" id="cfg_buscar_empresa" class="form-control" maxlength="120" placeholder="RUC o razón social"><button type="button" id="btn_buscar_empresa" class="btn config-search"><i class="fas fa-search"></i> Buscar</button></div><small>Escribe al menos 3 caracteres. Solo se mostrarán empresas activas.</small></div>
                <div id="cfg_resultados_empresa" class="company-results d-none"></div>
            </section>
            <section class="config-panel commercial-panel">
                <div class="config-panel-heading"><span class="panel-icon"><i class="fas fa-store"></i></span><div><h2>Identidad comercial</h2><p>El nombre comercial se muestra junto a iFact en las cotizaciones. La razón social y el RUC siguen identificando a la empresa emisora.</p></div></div>
                <div class="commercial-fields"><div><label for="cfg_nombre_comercial">Nombre comercial</label><input type="text" id="cfg_nombre_comercial" class="form-control" maxlength="100" placeholder="Ej. INTELSOFT"><small>Se mostrará debajo de iFact en el PDF.</small></div><div class="commercial-issuer"><small>Empresa emisora</small><strong id="cfg_razon_social">—</strong><span id="cfg_emisor_ruc">—</span></div></div>
                <div class="config-actions"><span>Este cambio se aplica a las cotizaciones que abras desde ahora.</span><button type="button" id="btn_guardar_nombre_comercial" class="btn config-save"><i class="fas fa-save"></i> Guardar nombre comercial</button></div>
            </section>
            <section class="config-panel billing-panel">
                <div class="config-panel-heading"><span class="panel-icon billing"><i class="fas fa-file-invoice"></i></span><div><h2>Facturación electrónica</h2><p>Define si las ventas permitirán emitir boletas y facturas electrónicas.</p></div></div>
                <div class="format-options">
                    <label class="format-option" for="cfg_facturacion_si"><input type="radio" name="cfg_facturacion" id="cfg_facturacion_si" value="1"><span class="format-check"><i class="fas fa-check"></i></span><span class="paper-preview billing-preview enabled"><i class="fas fa-file-signature"></i></span><span class="format-copy"><strong>Con facturación electrónica</strong><small>Al finalizar una venta podrás emitir un comprobante electrónico o imprimir la nota de venta.</small><em>Emisión electrónica habilitada</em></span></label>
                    <label class="format-option" for="cfg_facturacion_no"><input type="radio" name="cfg_facturacion" id="cfg_facturacion_no" value="0"><span class="format-check"><i class="fas fa-check"></i></span><span class="paper-preview billing-preview disabled"><i class="fas fa-receipt"></i></span><span class="format-copy"><strong>Sin facturación electrónica</strong><small>Al finalizar una venta podrás imprimir la nota interna o continuar sin imprimir.</small><em>Sin emisión de boletas ni facturas</em></span></label>
                </div>
                <div class="config-note"><i class="fas fa-info-circle"></i><span>El cambio se aplica a las ventas nuevas y no modifica los comprobantes emitidos anteriormente.</span></div>
                <div class="config-actions"><span id="cfg_facturacion_estado">Selecciona cómo operará este negocio.</span><button type="button" id="btn_guardar_facturacion" class="btn config-save"><i class="fas fa-save"></i> Guardar configuración</button></div>
            </section>
            <section class="config-panel">
                <div class="config-panel-heading"><span class="panel-icon"><i class="fas fa-receipt"></i></span><div><h2>Nota de venta</h2><p>Configura la numeración interna de las ventas que se registran sin comprobante electrónico.</p></div></div>
                <div class="nota-pedido-fields">
                    <div><label for="cfg_nota_serie">Serie interna</label><input type="text" id="cfg_nota_serie" class="form-control" maxlength="4" placeholder="NV01"><small>Hasta cuatro letras o números.</small></div>
                    <div><label for="cfg_nota_siguiente">Próximo correlativo</label><input type="number" id="cfg_nota_siguiente" class="form-control" min="1" max="99999999" step="1"><small>Se asignará a la siguiente nota de venta.</small></div>
                </div>
                <div class="config-note"><i class="fas fa-info-circle"></i><span>La nota de venta es un documento interno. No se envía a la central ni a SUNAT.</span></div>
                <div class="config-actions"><span id="cfg_nota_estado">Numeración independiente por instalación.</span><button type="button" id="btn_guardar_nota" class="btn config-save"><i class="fas fa-save"></i> Guardar correlativo</button></div>
            </section>
            <section class="config-panel">
                <div class="config-panel-heading"><span class="panel-icon"><i class="fas fa-boxes"></i></span><div><h2>Stock para las ventas</h2><p>Define si el punto de venta exige existencias disponibles para confirmar una venta.</p></div></div>
                <div class="format-options">
                    <label class="format-option"><input type="radio" name="vender_sin_stock" value="0" checked><span class="format-check"><i class="fas fa-check"></i></span><span class="paper-preview"><i class="fas fa-box"></i></span><span class="format-copy"><strong>Vender con stock</strong><small>Solo permite vender la cantidad disponible de cada variante en la tienda.</small><em>Control de existencias activo</em></span></label>
                    <label class="format-option"><input type="radio" name="vender_sin_stock" value="1"><span class="format-check"><i class="fas fa-check"></i></span><span class="paper-preview"><i class="fas fa-shopping-bag"></i></span><span class="format-copy"><strong>Vender sin stock</strong><small>Permite confirmar ventas aunque la variante no tenga existencias suficientes.</small><em>Las salidas se registran y el stock puede quedar negativo</em></span></label>
                </div>
                <div class="config-actions"><span id="cfg_stock_estado">Aplica a las nuevas ventas de todas las tiendas.</span><button type="button" id="btn_guardar_stock" class="btn config-save"><i class="fas fa-save"></i> Guardar modo de venta</button></div>
            </section>
            <section class="security-note"><i class="fas fa-lock"></i><div><strong>Configuración sensible protegida</strong><p>El RUC y token se actualizan juntos desde el registro central; el token nunca se muestra en pantalla.</p></div></section>
        </div>
    </div>
</div>
<script src="../assets/js/jquery-3.6.0.min.js"></script><script src="../assets/js/feather.min.js"></script><script src="../assets/js/jquery.slimscroll.min.js"></script><script src="../assets/js/bootstrap.bundle.min.js"></script><script src="../assets/js/script.js"></script><script src="../assets/plugins/toastr/toastr.min.js"></script><script src="../js/funciones_configuracion.js?v=<?php echo filemtime('../js/funciones_configuracion.js'); ?>"></script>
<script src="../assets/plugins/sweetalert/sweetalert2.all.min.js"></script><script src="../js/funciones_header.js?v=<?=filemtime(__DIR__.'/../js/funciones_header.js')?>"></script></body>
</html>
