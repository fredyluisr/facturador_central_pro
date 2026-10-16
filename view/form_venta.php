<?php
require_once __DIR__ . '/../model/sesion.php';
require_once('../model/var_global.php');
require_once('../model/validar_sesion_pos.php');
validarSesionPos();
require_once __DIR__.'/../model/configuracion_sistema.php';
$vender_sin_stock=configuracion_sistema::venderSinStock((new conexion())->con());
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
    <link rel="stylesheet" href="../css/form_venta.css?v=<?php echo filemtime('../css/form_venta.css'); ?>">
    <link rel="stylesheet" href="../css/venta_selector.css?v=<?php echo filemtime('../css/venta_selector.css'); ?>">
    <link rel="stylesheet" href="../css/list_venta.css">
    <link rel="stylesheet" href="../css/pos_ui.css?v=<?php echo filemtime('../css/pos_ui.css'); ?>">
    <link rel="stylesheet" href="../css/venta_compacta.css?v=<?php echo filemtime('../css/venta_compacta.css'); ?>">
</head>
<body class="pos-page venta-pos">

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
                <button class="btn-confirmar btn" onclick="Guardar()">
                    <i class="fa fa-check me-1"></i> Confirmar Venta
                </button>
            </div>
        </div>

<div class="venta-col-principal">
        <!-- DATOS DE LA VENTA -->
        <div class="seccion-comprobante mb-4 venta-datos">
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

                    <p class="text-muted mb-3" style="font-size:13px">Busca por nombre, SKU o código de barras. Selecciona la talla y el color que vas a vender; el stock corresponde a esta tienda.</p><div class="row g-2 align-items-end mb-3 venta-item-campos">
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

                    <div class="table-responsive">
                        <table class="table tabla-detalle table-sm">
                            <thead>
                                <tr>
                                    <th id="th_item">Producto / variante</th>
                                    <th class="text-center">Cant.</th>
                                    <th class="text-center">Und.</th>
                                    <th class="text-center">P.Unit.</th>
                                    <th class="text-center">Subtotal</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="tbody_detalle">
                                <tr>
                                    <td colspan="6" class="text-center text-muted" style="padding:22px;">
                                        <i class="fa fa-inbox me-1"></i> Sin ítems agregados
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
</div>
<aside class="venta-col-cobro">
                <!-- RESUMEN -->
                <div class="seccion-comprobante mb-3 venta-resumen">
                    <div class="seccion-label-minimal">
                        <i class="fa fa-receipt"></i> Resumen
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted" style="font-size:.88rem;">Ítems</span>
                        <b id="total_items">0</b>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted" style="font-size:.82rem;">Subtotal (sin IGV)</span>
                        <span style="font-size:.88rem; font-weight:600;">S/ <span id="total_subtotal">0.00</span></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted" style="font-size:.82rem;">IGV 18%</span>
                        <span style="font-size:.88rem; font-weight:600;">S/ <span id="total_igv">0.00</span></span>
                    </div>
                    <hr style="margin:8px 0;">
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-weight:700; text-transform:uppercase; font-size:.78rem; letter-spacing:.04em;">Total</span>
                        <h3 class="m-0 fw-bold">S/ <span id="total_monto">0.00</span></h3>
                    </div>
                    <div class="venta-saldo"><span>Saldo pendiente</span><strong id="pago_saldo">S/ 0.00</strong></div><small id="pago_estado" class="venta-estado-pago">Agrega productos para calcular el saldo.</small>
                </div>

                <!-- FORMA DE PAGO -->
                <div class="seccion-comprobante venta-pagos">
                    <div class="seccion-label-minimal">
                        <i class="fa fa-credit-card"></i> Forma de Pago
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Método <span class="text-danger">*</span></label>
                        <button type="button" class="form-control venta-eleccion-trigger" onclick="AbrirSelectorPagoVenta('metodo')"><span id="metodo_pago_seleccionado">Seleccione método de pago</span><i class="fa fa-chevron-up"></i></button>
                        <select class="d-none" id="ven_tpa_id" tabindex="-1" aria-hidden="true">
                            <option value="0">Seleccione método</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Cuenta <span class="text-danger">*</span></label>
                        <button type="button" class="form-control venta-eleccion-trigger" onclick="AbrirSelectorPagoVenta('cuenta')"><span id="cuenta_pago_seleccionada">Seleccione cuenta</span><i class="fa fa-chevron-up"></i></button>
                        <select class="d-none" id="ven_cba_id" tabindex="-1" aria-hidden="true">
                            <option value="0">Seleccione cuenta</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom" for="ven_monto_pago">Monto a pagar ahora <span class="text-danger">*</span></label>
                        <input type="number" id="ven_monto_pago" class="form-control" min="0" step="0.01" placeholder="0.00" oninput="ActualizarSaldoVenta()">
                        <small class="text-muted">Pago inicial · 0 para dejar pendiente.</small>
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
                </div>
</aside>
        <!-- BOTÓN MÓVIL -->
        <div class="d-block d-md-none mb-4 mt-3">
            <button class="btn-confirmar btn w-100" onclick="Guardar()">
                <i class="fa fa-check me-1"></i> Confirmar Venta
            </button>
        </div>

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
        <div class="modal-body"><div class="venta-selector-busqueda"><i class="fa fa-search"></i><input type="search" id="buscar_cliente_venta" placeholder="Buscar por nombre o documento…" autocomplete="off"></div><div id="clientes_venta_lista" class="venta-selector-lista"></div></div>
    </div></div>
</div>

<!-- SELECTOR INFERIOR DE MÉTODO Y CUENTA -->
<div class="offcanvas offcanvas-bottom venta-pago-panel" tabindex="-1" id="selectorPagoVenta" aria-labelledby="selectorPagoVentaTitulo">
    <div class="offcanvas-header"><div><small>FORMA DE PAGO</small><h5 id="selectorPagoVentaTitulo">Seleccionar</h5></div><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button></div>
    <div class="offcanvas-body"><div id="opcionesPagoVenta" class="venta-pago-opciones"></div></div>
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
                        <div class="col-12 col-md-4">
                            <label class="form-label-custom">Tipo de Documento</label>
                            <select class="form-select" id="venta_cli_tipo_doc">
                                <option value="0">Sin documento</option>
                                <option value="1" selected>DNI</option>
                                <option value="6">RUC</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label-custom">N° Documento</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="venta_cli_nro_doc" placeholder="Opcional" maxlength="15">
                                <button class="btn btn-outline-secondary" type="button" onclick="ConsultarDocumentoClienteVenta()" title="Buscar por DNI / RUC">
                                    <i class="fa fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label-custom">Teléfono</label>
                            <input type="text" class="form-control" id="venta_cli_telefono" placeholder="Opcional" maxlength="20">
                        </div>
                        <div class="col-12">
                            <label class="form-label-custom">Nombres / Razón Social <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="venta_cli_nombres" placeholder="Nombre completo o razón social" maxlength="200">
                        </div>
                        <div class="col-12">
                            <label class="form-label-custom">Dirección</label>
                            <input type="text" class="form-control" id="venta_cli_direccion" placeholder="Opcional" maxlength="300">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label-custom">Email</label>
                            <input type="email" class="form-control" id="venta_cli_email" placeholder="Opcional" maxlength="100">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label-custom">Marca <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="venta_ent_marca"
                                   placeholder="Ingrese marca" minlength="3" maxlength="150" required>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer-custom">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fa fa-times me-1"></i> Cancelar</button>
                <button type="button" class="btn btn-warning" id="btnGuardarClienteVenta" onclick="GuardarClienteVenta()"><i class="fa fa-save me-1"></i> Guardar</button>
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
</script>
<link rel="stylesheet" href="../css/pdf_visor.css?v=<?php echo filemtime('../css/pdf_visor.css'); ?>">
<script src="../js/funciones_pdf_visor.js?v=<?php echo filemtime('../js/funciones_pdf_visor.js'); ?>"></script>
<script src="../js/funciones_form_venta.js?v=<?php echo filemtime('../js/funciones_form_venta.js'); ?>"></script>
<script src="../js/venta_selector.js?v=<?php echo filemtime('../js/venta_selector.js'); ?>"></script>
<script src="../js/funciones_header.js?v=<?php echo filemtime('../js/funciones_header.js'); ?>"></script>

</body>
</html>
<?php } else header("Location: $pagina_redireccion"); ?>
