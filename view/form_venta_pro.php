<?php
require_once __DIR__ . '/../model/sesion.php';
require_once('../model/var_global.php');
require_once('../model/validar_sesion_pos.php');
validarSesionPos();
require_once __DIR__.'/../model/configuracion_sistema.php';
$vender_sin_stock=configuracion_sistema::venderSinStock((new conexion())->con());
$facturacion_electronica_habilitada=configuracion_sistema::facturacionElectronicaHabilitada((new conexion())->con());
if(isset($_SESSION["rol_id_ifact"]) && $_SESSION["rol_id_ifact"] <> 0){
    $loc_nombre = isset($_SESSION['loc_nombre_ifact']) ? $_SESSION['loc_nombre_ifact'] : 'Tienda';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <link rel="shortcut icon" href="<?php echo $icono; ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <title><?php echo $titulo; ?></title>

    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/animate.css">
    <link rel="stylesheet" href="../assets/plugins/select2/css/select2.min.css">
    <link rel="stylesheet" href="../assets/plugins/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="../assets/plugins/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/plugins/toastr/toatr.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../css/header.css?v=<?= filemtime(__DIR__.'/../css/header.css') ?>">
    <link rel="stylesheet" href="../css/modal_form.css">
    <link rel="stylesheet" href="../css/list_venta.css">
    <link rel="stylesheet" href="../css/pos_ui.css?v=<?php echo filemtime('../css/pos_ui.css'); ?>">
    <link rel="stylesheet" href="../css/venta_compacta.css?v=<?php echo filemtime('../css/venta_compacta.css'); ?>">
    <link rel="stylesheet" href="../css/form_venta_pro.css?v=<?php echo filemtime('../css/form_venta_pro.css'); ?>">
    <link rel="stylesheet" href="../css/venta_selector_pro.css?v=<?php echo filemtime('../css/venta_selector_pro.css'); ?>">
</head>
<body class="pos-page venta-pos venta-pro-page">

<?php include('header.php'); ?>

<div class="page-wrapper">
    <div class="content">

        <!-- HEADER -->
        <div class="page-header">
            <div class="page-title">
                <h4>Nueva Venta</h4>
                <div class="page-breadcrumb">
                    <a href="index.php"><i class="fa fa-home"></i> Inicio</a>
                    <span class="breadcrumb-separator"><i class="fa fa-angle-right"></i></span>
                    <span class="breadcrumb-parent">Punto de Venta</span>
                    <span class="breadcrumb-separator"><i class="fa fa-angle-right"></i></span>
                    <span class="breadcrumb-current">Nueva Venta</span>
                </div>
            </div>
            <div class="page-btn d-flex gap-2 btn-group-header">
                <a href="list_venta.php" class="btn btn-secondary"
                   style="height:42px; border-radius:12px; display:flex; align-items:center; gap:6px;">
                    <i class="fa fa-arrow-left"></i> Volver
                </a>
                <button type="button" id="btn_confirmar_venta_superior" class="btn-confirmar btn btn-confirmar-venta" onclick="Guardar()">
                    <i class="fa fa-check me-1"></i> Confirmar Venta
                </button>
            </div>
        </div>

<div class="venta-col-principal">
        <!-- DATOS DE LA VENTA -->
        <div class="seccion-comprobante mb-4 venta-datos venta-pro-datos">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                <div>
                    <div class="seccion-label-minimal mb-2">
                        <i class="fa fa-user"></i> Datos de la Venta
                    </div>
                    <div class="text-muted" style="font-size:13px;">
                        Seleccione cliente y comprobante
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <div class="badge rounded-pill bg-light text-dark px-3 py-2 border"
                         style="font-size:12px; font-weight:600;">
                        <i class="fa fa-store me-1 text-primary"></i>
                        <?= htmlspecialchars($loc_nombre); ?>
                    </div>
                    <div class="badge rounded-pill bg-light text-success px-3 py-2 border"
                         style="font-size:12px; font-weight:600;">
                        <i class="fa fa-calendar-alt me-1"></i>
                        <?= date('d/m/Y'); ?>
                    </div>
                </div>
            </div>

            <div class="row g-3 align-items-end">
                <!-- CLIENTE -->
                <div class="col-12 col-lg-4">
                    <label class="form-label-custom">Cliente</label>
                    <div class="cliente-selector-group">
                        <div class="cliente-selector-control">
                        <button type="button" class="form-control venta-eleccion-trigger" id="abrir_selector_cliente" onclick="AbrirSelectorClienteVenta()">
                            <span id="cliente_seleccionado">Cliente general / sin registro</span><i class="fa fa-chevron-down"></i>
                        </button>
                        <select class="d-none" id="ven_cli_id" tabindex="-1" aria-hidden="true">
                            <option value="0">Cliente general / sin registro</option>
                        </select>
                        </div>
                        <button type="button" class="btn btn-warning btn-agregar-cliente" onclick="AbrirModalClienteVenta()"
                                title="Agregar cliente" aria-label="Agregar cliente">
                            <i class="fa fa-plus"></i>
                        </button>
                    </div>
                </div>
                <!-- TIPO DOCUMENTO -->
                <div class="col-12 col-lg-3">
                    <label class="form-label-custom">Comprobante <span style="color:#9ca3af; font-weight:400;"></span></label>
                    <select class="form-select" id="ven_tde_id" style="height:42px; border-radius:12px;">
                        <option value="0">Seleccione...</option>
                    </select>
                </div>
                <!-- SERIE -->
                <div class="col-6 col-lg-2">
                    <label class="form-label-custom">Serie</label>
                    <div class="comp-field">
                        <span id="comp_serie" class="comp-auto">—</span>
                    </div>
                </div>
                <!-- N° COMPROBANTE -->
                <div class="col-6 col-lg-2">
                    <label class="form-label-custom">N° Comprobante</label>
                    <div class="comp-field">
                        <span id="comp_numero" class="comp-auto">—</span>
                    </div>
                </div>
            </div>
        </div>

                <div class="seccion-item venta-productos">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                        <div class="seccion-label-minimal mb-0">
                            <i class="fa fa-box"></i> <span id="label_ins_prod_titulo">Productos</span> a Vender
                        </div>
                        <input type="hidden" name="ven_tipo" value="2">
                    </div>

                    <p class="text-muted mb-3" style="font-size:13px">Busca por nombre, SKU o código de barras. Selecciona un producto para elegir la talla o variante disponible.</p>
                    <div class="venta-pro-buscador"><i class="fa fa-search"></i><input id="buscar_producto_pro" type="search" placeholder="Buscar productos..." autocomplete="off"><span id="venta_pro_resultados"></span></div>
                    <div id="venta_pro_catalogo" class="venta-pro-catalogo" aria-live="polite"></div>
                    <div class="row g-2 align-items-end mb-3 venta-item-campos venta-pro-item-compat" aria-hidden="true">
                        <div class="col-12 venta-producto-fila">
                            <label class="form-label-custom">Producto / variante <span class="text-danger">*</span></label>
                            <button type="button" class="form-control venta-selector-trigger" id="abrir_selector_producto" onclick="AbrirSelectorProducto()"><span id="producto_seleccionado">Seleccionar producto y atributos</span><i class="fa fa-chevron-up"></i></button>
                            <input type="hidden" id="ven_ins_id" value="0">
                            <div class="venta-unidad" id="venta_unidad">Unidad de medida: —</div>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label-custom">Cantidad <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="ven_cantidad"
                                   step="0.001" min="0.001" placeholder="0.000">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label-custom">Precio</label>
                            <input type="number" class="form-control" id="ven_precio_unit"
                                   step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label-custom">Stock</label>
                            <input type="text" class="form-control text-center fw-bold" id="ven_stock" readonly>
                        </div>
                        <div class="col-6 col-md-2"><label class="form-label-custom" for="ven_total_item">Total</label><input id="ven_total_item" class="form-control fw-bold" type="text" value="S/ 0.00" readonly></div><div class="col-6 col-md-1 venta-agregar">
                            <button class="btn btn-dark w-100" onclick="AgregarItem()"
                                    style="height:42px; border-radius:12px;">
                                <i class="fa fa-plus"></i>
                            </button>
                        </div>
                    </div>

                </div>
</div>
<aside class="venta-col-cobro">
                <div class="seccion-comprobante mb-3 venta-pro-carrito">
                    <div class="seccion-label-minimal"><i class="fa fa-shopping-cart"></i> Carrito de venta <span id="venta_pro_badge" class="venta-pro-badge">0</span></div>
                    <div id="tbody_detalle" class="venta-pro-carrito-lista"><div class="venta-pro-carrito-vacio"><i class="fa fa-inbox"></i><span>Sin ítems agregados</span></div></div>
                    <div class="venta-pro-carrito-total"><span>Total</span><strong>S/ <span id="venta_pro_total_carrito">0.00</span></strong></div>
                </div>
                <!-- FORMA DE PAGO -->
                <div class="seccion-comprobante venta-pagos">
                    <div class="seccion-label-minimal">
                        <i class="fa fa-credit-card"></i> Forma de Pago
                    </div>
                    <div class="venta-metodos-wrap">
                        <label class="form-label-custom">Método <span class="text-danger">*</span></label>
                        <div id="venta_metodos_botones" class="venta-metodos-botones" role="group" aria-label="Métodos de pago">
                            <div class="venta-metodos-cargando"><i class="fa fa-spinner fa-spin"></i> Cargando métodos…</div>
                        </div>
                        <select class="d-none" id="ven_tpa_id" tabindex="-1" aria-hidden="true">
                            <option value="0">Seleccione método</option>
                        </select>
                    </div>
                    <div class="venta-cuenta-wrap" id="venta_cuenta_wrap">
                        <label class="form-label-custom">Cuenta <span class="text-danger">*</span></label>
                        <select class="form-select venta-cuenta-select" id="ven_cba_id" onchange="ActualizarCuentaPagoVenta()">
                            <option value="0">Seleccione primero el método</option>
                        </select>
                        <small id="venta_cuenta_ayuda" class="venta-cuenta-ayuda">Elige una forma de pago para ver su cuenta asociada.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom" for="ven_monto_pago">Monto a pagar <span class="text-danger">*</span></label>
                        <input type="number" id="ven_monto_pago" class="form-control" min="0" step="0.01" placeholder="0.00" oninput="ActualizarSaldoVenta()">
                        <small class="text-muted">Puedes modificarlo si el cliente realizará un pago parcial.</small>
                    </div>

                    <details class="venta-opcionales"><summary>Operación y comentario <span class="text-muted">(opcional)</span></summary><div class="mb-3">
                        <label class="form-label-custom">N° Operación</label>
                        <input type="text" class="form-control" id="ven_num_operacion"
                               placeholder="Opcional" maxlength="60">
                    </div>
                    <div>
                        <label class="form-label-custom">Comentario</label>
                        <input type="text" class="form-control" id="ven_comentario"
                               placeholder="Opcional" maxlength="200">
                    </div></details>
                    <div class="venta-confirmar-inferior">
                        <button type="button" id="btn_confirmar_venta_inferior" class="btn-confirmar btn btn-confirmar-venta w-100" onclick="Guardar()">
                            <i class="fa fa-check me-1"></i> Confirmar Venta
                        </button>
                    </div>
                </div>
</aside>

    </div>
</div>

<div class="offcanvas offcanvas-bottom venta-producto-panel" tabindex="-1" id="selectorProducto" aria-labelledby="selectorProductoTitulo">
    <div class="offcanvas-header"><div><small class="text-primary">CATÁLOGO · NUEVA VENTA</small><h5 id="selectorProductoTitulo">Selecciona tu producto</h5><p class="text-muted mb-0">Elige sus atributos para encontrar la variante exacta.</p></div><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button></div>
    <div class="offcanvas-body"><div class="venta-selector-layout">
        <section class="venta-productos-busqueda"><label for="buscar_producto_panel" class="form-label-custom">Producto, SKU o código de barras</label><input id="buscar_producto_panel" class="form-control" type="search" placeholder="Buscar en el catálogo…"><div id="panel_productos" class="venta-productos-lista"></div></section>
        <section class="venta-atributos"><h4 id="panel_producto_nombre">Elige un producto</h4><div id="panel_atributos"></div><div id="panel_variante_info" class="venta-variante-info">Selecciona el producto y todos sus atributos.</div><button type="button" id="panel_usar_variante" class="btn-confirmar btn mt-3" disabled onclick="UsarVarianteSeleccionada()"><i class="fa fa-check me-1"></i> Agregar selección</button></section>
    </div></div>
</div>
<script>window.ventaSinStock=<?= $vender_sin_stock?'true':'false' ?>;</script>
<!-- SELECTOR DE CLIENTE -->
<div class="modal fade venta-selector-cliente" id="selectorClienteVenta" tabindex="-1" aria-labelledby="selectorClienteVentaTitulo">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><div><small>NUEVA VENTA</small><h5 id="selectorClienteVentaTitulo">Seleccionar cliente</h5></div><button type="button" class="venta-selector-cerrar" data-bs-dismiss="modal" aria-label="Cerrar"><i class="fa fa-times"></i></button></div>
        <div class="modal-body"><div class="venta-selector-busqueda"><i class="fa fa-search"></i><input type="search" id="buscar_cliente_venta" placeholder="Buscar por nombre o documento…" autocomplete="off"></div><button type="button" class="venta-selector-nuevo" onclick="AbrirNuevoClienteEmisionPro()"><i class="fa fa-user-plus"></i> Agregar nuevo cliente</button><div id="clientes_venta_lista" class="venta-selector-lista"></div></div>
    </div></div>
</div>

<!-- MODAL NUEVO CLIENTE -->
<div class="modal fade modal-form" id="modalClienteVenta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-user-plus me-2"></i>Nuevo Cliente</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Cerrar"><span>×</span></button>
            </div>
            <div class="modal-body">
                <div class="seccion-bloque">
                    <div class="seccion-label-minimal"><i class="fa fa-id-card"></i> Datos del Cliente</div>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label-custom">Tipo de Documento <span class="text-danger">*</span></label>
                            <select class="form-select" id="venta_cli_tipo_doc">
                                <option value="1" selected>DNI</option>
                                <option value="6">RUC</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label-custom">N° Documento <span class="text-danger">*</span></label>
                            <div class="input-group venta-documento-consulta">
                                <input type="text" class="form-control" id="venta_cli_nro_doc" placeholder="Ingrese DNI o RUC" maxlength="11" inputmode="numeric">
                                <button class="btn venta-documento-buscar" id="btnConsultarDocumentoClienteVenta" type="button" onclick="ConsultarDocumentoClienteVenta()" title="Buscar por DNI / RUC" aria-label="Buscar documento">
                                    <i class="fa fa-search"></i>
                                </button>
                            </div>
                            <small id="venta_cli_consulta_estado" class="venta-cli-consulta-estado"></small>
                        </div>
                        <div class="col-12">
                            <label class="form-label-custom">Nombres / Razón Social <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="venta_cli_nombres" placeholder="Nombre completo o razón social" maxlength="200">
                        </div>
                        <div class="col-12">
                            <label class="form-label-custom">Dirección <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="venta_cli_direccion" placeholder="Ingrese dirección" maxlength="300">
                        </div>
                        <div class="col-12">
                            <label class="form-label-custom">Marca <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="venta_ent_marca"
                                   placeholder="Ingrese marca" minlength="3" maxlength="150" required>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer-custom">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fa fa-times me-1"></i> Cancelar</button>
                <button type="button" class="btn btn-warning" id="btnGuardarClienteVenta" onclick="GuardarClienteVenta()"><i class="fa fa-save me-1"></i> Guardar cliente</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DE VALIDACIÓN DEL CLIENTE -->
<div class="modal fade" id="modalValidacionClienteVenta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content venta-validacion-cliente">
            <div class="modal-body">
                <div class="venta-validacion-icono"><i class="fa fa-exclamation"></i></div>
                <h5>Falta completar un dato</h5>
                <p id="venta_validacion_cliente_mensaje"></p>
                <button type="button" class="btn" data-bs-dismiss="modal">Entendido</button>
            </div>
        </div>
    </div>
</div>

<!-- OFF-CANVAS COMPROBANTE -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasComprobante" data-bs-scroll="true">
    <div class="offcanvas-header" style="border-bottom:1px solid #eef2f7;">
        <h6 class="offcanvas-title fw-bold" id="oc_comp_titulo">
            <i class="fa fa-file-invoice me-2 text-success"></i> Comprobante
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body" id="oc_comp_body"></div>
</div>

<script src="../assets/js/jquery-3.6.0.min.js"></script>
<script src="../js/src/bootstrap.min.js"></script>
<script src="../assets/js/feather.min.js"></script>
<script src="../assets/js/jquery.slimscroll.min.js"></script>
<script src="../assets/plugins/select2/js/select2.min.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
<script src="../assets/plugins/sweetalert/sweetalert2.all.min.js"></script>
<script src="../assets/plugins/toastr/toastr.min.js"></script>
<script>
var id_usuario = <?= (int)$_SESSION['usu_id_ifact']; ?>;
var id_local = <?= isset($_SESSION['loc_id_ifact']) ? (int)$_SESSION['loc_id_ifact'] : 0; ?>;
var facturacion_electronica_habilitada = <?= $facturacion_electronica_habilitada ? 'true' : 'false'; ?>;
</script>
<link rel="stylesheet" href="../css/pdf_visor.css?v=<?php echo filemtime('../css/pdf_visor.css'); ?>">
<script src="../js/funciones_pdf_visor.js?v=<?php echo filemtime('../js/funciones_pdf_visor.js'); ?>"></script>
<script src="../js/funciones_form_venta_pro.js?v=<?php echo filemtime('../js/funciones_form_venta_pro.js'); ?>"></script>
<script src="../js/venta_selector_pro.js?v=<?php echo filemtime('../js/venta_selector_pro.js'); ?>"></script>
<script src="../js/funciones_header.js?v=<?php echo filemtime('../js/funciones_header.js'); ?>"></script>

</body>
</html>
<?php } else header("Location: $pagina_redireccion"); ?>
