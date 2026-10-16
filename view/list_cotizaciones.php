<?php
require_once __DIR__ . '/../model/sesion.php';
require_once('../model/var_global.php');
require_once('../model/validar_sesion_pos.php');
if(empty($_SESSION['cotizacion_csrf']))$_SESSION['cotizacion_csrf']=bin2hex(random_bytes(32));
validarSesionPos();
if (!empty($_SESSION['rol_id_ifact'])) {
    $almNombre = $_SESSION['loc_nombre_ifact'] ?? 'Local actual';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <link rel="shortcut icon" href="<?php echo $icono; ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <title><?php echo $titulo; ?></title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/animate.css">
    <link rel="stylesheet" href="../assets/plugins/select2/css/select2.min.css">
    <link rel="stylesheet" href="../assets/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="../assets/plugins/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="../assets/plugins/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/plugins/toastr/toatr.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../css/header.css?v=<?= filemtime(__DIR__.'/../css/header.css') ?>">
    <link rel="stylesheet" href="../css/modal_form.css">
    <link rel="stylesheet" href="../css/pos_ui.css?v=<?php echo filemtime('../css/pos_ui.css'); ?>">
<link rel="stylesheet" href="../css/venta_selector.css?v=<?php echo filemtime('../css/venta_selector.css'); ?>">
<link rel="stylesheet" href="../css/list_cotizaciones.css?v=<?php echo filemtime('../css/list_cotizaciones.css'); ?>">
</head>
<body class="pos-page cotizacion-page">
<?php include('header.php'); ?>

<div class="page-wrapper"><div class="content">
<div class="page-header"><div class="page-title"><h4>Cotizaciones</h4><div class="page-breadcrumb"><a href="index.php"><i class="fa fa-home"></i> Inicio</a><span>›</span><span>Punto de Venta</span><span>›</span><span>Cotizaciones</span></div></div><div class="page-btn"><button class="btn btn-dark" onclick="Nuevo()"><i class="fa fa-plus me-1"></i> Nueva cotización</button></div></div>
<div class="card mb-3"><div class="card-body"><div class="row g-3 align-items-end"><div class="col-md-3"><label for="filtro_fecha_inicio">Fecha inicio</label><input type="date" class="form-control" id="filtro_fecha_inicio"></div><div class="col-md-3"><label for="filtro_fecha_fin">Fecha fin</label><input type="date" class="form-control" id="filtro_fecha_fin"></div><div class="col-md-3"><button class="btn btn-primary" onclick="Listar()"><i class="fa fa-search me-1"></i> Buscar</button></div></div></div></div>
<div class="card"><div class="card-body"><div class="cot-listado"><table class="table" id="tablaListado"><thead><tr><th>N.º cotización</th><th>Cliente</th><th>Fecha</th><th>Total</th><th>Estado</th><th>Acciones</th></tr></thead><tbody></tbody></table></div></div></div>
</div></div>
<div class="modal fade modal-form" id="reg_edi" tabindex="-1" data-bs-backdrop="static" aria-labelledby="nombre_opcion" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
<div class="modal-header"><h5 id="nombre_opcion" class="modal-title">Nueva cotización</h5><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
<div class="modal-body"><input type="hidden" id="cot_id"><div class="cot-bloque"><h6>Datos de la cotización</h6><div class="row g-3"><div class="col-md-6"><label for="ent_id">Cliente *</label><select class="form-select" id="ent_id"><option value="">Seleccionar cliente</option></select></div><div class="col-md-3"><label for="cot_fecha">Fecha *</label><input class="form-control" type="date" id="cot_fecha"></div><div class="col-md-3"><label>N.º cotización</label><input class="form-control" id="cot_numero" readonly placeholder="Se asigna al guardar"></div></div></div>
<div class="cot-bloque" id="bloqueAgregarItem"><h6>Productos de la cotización</h6><div class="row g-3 align-items-end"><div class="col-12"><label>Producto / variante *</label><button type="button" class="form-control venta-selector-trigger" onclick="AbrirSelectorProducto()"><span id="producto_seleccionado">Seleccionar producto y atributos</span><i class="fa fa-chevron-up"></i></button><input type="hidden" id="ven_ins_id"><small id="venta_unidad">Unidad de medida: —</small></div><div class="col-6 col-md-3"><label for="ven_cantidad">Cantidad *</label><input type="number" id="ven_cantidad" class="form-control" step="0.001" min="0.001"></div><div class="col-6 col-md-3"><label for="ven_precio_unit">Precio unitario (S/) *</label><input type="number" id="ven_precio_unit" class="form-control" step="0.01" min="0"></div><div class="col-6 col-md-3"><label>Total</label><input readonly id="ven_total_item" class="form-control" value="S/ 0.00"></div><div class="col-6 col-md-3"><button id="btnAgregarItem" class="btn btn-dark w-100" onclick="AgregarItem()"><i class="fa fa-plus me-1"></i> Agregar producto</button></div></div></div>
<div class="table-responsive"><table class="table cot-detalle"><thead><tr><th>Producto / variante</th><th>Cantidad</th><th>Unidad</th><th>P. unitario</th><th>Subtotal</th><th></th></tr></thead><tbody id="tbody_detalle"></tbody></table></div>
<div class="cot-total">TOTAL <strong id="cot_total">S/ 0.00</strong></div><div class="mt-3"><label for="cot_observaciones">Observaciones / condiciones</label><textarea id="cot_observaciones" class="form-control" rows="3" maxlength="3000" placeholder="Condiciones comerciales, plazo de entrega u observaciones"></textarea></div>
</div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button><button class="btn btn-outline-primary" id="btnPdfCot" onclick="PdfCotizacion()"><i class="fa fa-file-pdf me-1"></i> Ver PDF</button><button id="btnGuardarCot" class="btn btn-primary" onclick="Guardar()"><i class="fa fa-save me-1"></i> Guardar cotización</button></div>
</div></div></div>
<div class="offcanvas offcanvas-bottom venta-producto-panel" tabindex="-1" id="selectorProducto" aria-labelledby="selectorProductoTitulo">
    <div class="offcanvas-header"><div><small class="text-primary">CATÁLOGO · COTIZACIÓN</small><h5 id="selectorProductoTitulo">Selecciona tu producto</h5><p class="text-muted mb-0">Elige sus atributos para encontrar la variante exacta.</p></div><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button></div>
    <div class="offcanvas-body"><div class="venta-selector-layout">
        <section class="venta-productos-busqueda"><label for="buscar_producto_panel" class="form-label-custom">Producto, SKU o código de barras</label><input id="buscar_producto_panel" class="form-control" type="search" placeholder="Buscar en el catálogo…"><div id="panel_productos" class="venta-productos-lista"></div></section>
        <section class="venta-atributos"><h4 id="panel_producto_nombre">Elige un producto</h4><div id="panel_atributos"></div><div id="panel_variante_info" class="venta-variante-info">Selecciona el producto y todos sus atributos.</div><button type="button" id="panel_usar_variante" class="btn-confirmar btn mt-3" disabled onclick="UsarVarianteSeleccionada()"><i class="fa fa-check me-1"></i> Agregar selección</button></section>
    </div></div>
</div>
<script src="../assets/js/jquery-3.6.0.min.js"></script><script src="../assets/js/bootstrap.bundle.min.js"></script><script src="../assets/js/feather.min.js"></script><script src="../assets/js/jquery.slimscroll.min.js"></script><script src="../assets/js/jquery.dataTables.min.js"></script><script src="../assets/js/dataTables.bootstrap4.min.js"></script><script src="../assets/plugins/select2/js/select2.min.js"></script><script src="../assets/js/script.js"></script><script src="../assets/plugins/sweetalert/sweetalert2.all.min.js"></script>
<script>window.cotizacionCsrf=<?= json_encode($_SESSION['cotizacion_csrf']) ?>;window.ventaSinStock=true;window.cotizacionContexto=true;</script>
<script src="../js/venta_selector.js?v=<?php echo filemtime('../js/venta_selector.js'); ?>"></script>
<script src="../js/funciones_list_cotizaciones.js?v=<?php echo filemtime('../js/funciones_list_cotizaciones.js'); ?>"></script>
<script src="../js/funciones_header.js?v=<?php echo filemtime('../js/funciones_header.js'); ?>"></script>
</body></html><?php } ?>
