<?php
    require_once __DIR__ . '/../model/sesion.php';
    require_once('../model/var_global.php');
    require_once('../model/validar_sesion_pos.php');
    if (empty($_SESSION['venta_pago_csrf'])) $_SESSION['venta_pago_csrf']=bin2hex(random_bytes(32));
    validarSesionPos();
    if(isset($_SESSION["rol_id_ifact"]) && $_SESSION["rol_id_ifact"] <> 0){
    $alm_nombre = isset($_SESSION['loc_nombre_ifact']) ? $_SESSION['loc_nombre_ifact'] : 'Local';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <link rel="shortcut icon" href=<?php echo $icono; ?>>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <title><?php echo $titulo; ?></title>

    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/animate.css">
    <link rel="stylesheet" href="../assets/css/bootstrap-datetimepicker.min.css">
    <link rel="stylesheet" href="../assets/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="../assets/plugins/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="../assets/plugins/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/plugins/toastr/toatr.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../css/header.css?v=<?= filemtime(__DIR__.'/../css/header.css') ?>">
    <link rel="stylesheet" href="../css/modal_form.css">
    <link rel="stylesheet" href="../css/list_venta.css?v=<?php echo filemtime('../css/list_venta.css'); ?>">
    <link rel="stylesheet" href="../css/pos_ui.css?v=<?php echo filemtime('../css/pos_ui.css'); ?>">
</head>
<body class="pos-page">

<?php include('header.php'); ?>

<div class="page-wrapper">
    <div class="content">

        <div class="page-header">
            <div class="page-title">
                <h4>Ventas</h4>
                <div class="page-breadcrumb">
                    <a href="index.php"><i class="fa fa-home"></i> Inicio</a>
                    <span class="breadcrumb-separator"><i class="fa fa-angle-right"></i></span>
                    <span class="breadcrumb-parent">Punto de Venta</span>
                    <span class="breadcrumb-separator"><i class="fa fa-angle-right"></i></span>
                    <span class="breadcrumb-current">Ventas</span>
                </div>
            </div>
            <div class="page-btn d-flex gap-2">
                <a href="form_venta_pro.php" class="btn btn-dark">
                    <i class="fa fa-plus me-1"></i> Nueva Venta
                </a>
                <button class="btn btn-warning" onclick="FiltrarFechas()">
                    <i class="fa fa-search me-1"></i> Buscar
                </button>
            </div>
             
        </div>

        <!-- FILTRO -->
        <div class="card mb-3">
            <div class="card-body">
                <div class="row align-items-end">
                    <div class="col-md-3">
                        <label><b>Fecha Inicio</b></label>
                        <div class="input-groupicon">
                            <input type="text" placeholder="DD-MM-YYYY" class="datetimepicker form-control" id="filtro_fecha_inicio">
                            <div class="addonset"><img src="../assets/img/icons/calendars.svg" alt="img"></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label><b>Fecha Fin</b></label>
                        <div class="input-groupicon">
                            <input type="text" placeholder="DD-MM-YYYY" class="datetimepicker form-control" id="filtro_fecha_fin">
                            <div class="addonset"><img src="../assets/img/icons/calendars.svg" alt="img"></div>
                        </div>
                    </div>
                   
                   
                </div>
            </div>
        </div>

        <!-- LISTADO -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table datanew dataTable no-footer" id="tablaListado" style="width:100%">
                        <thead>
                            <tr>
                                <th><b>Fecha</b></th>
                                <th><b>Comprobante</b></th>
                                <th><b>Cliente</b></th>
                                <th class="text-center"><b>Productos</b></th>
                                <th class="text-end"><b>Total</b></th>
                                <th class="text-end"><b>Pagado</b></th>
                                <th class="text-end"><b>Saldo</b></th>
                                <th class="text-center"><b>Estado</b></th>
                                <th class="text-center"><b>Ctrl</b></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- OFFCANVAS ÍTEMS -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasItems" data-bs-scroll="true">
    <div class="offcanvas-header" style="border-bottom:1px solid #eef2f7;">
        <h6 class="offcanvas-title fw-bold" id="oc_titulo">
            <i class="fa fa-list me-2 text-muted"></i> Productos de la venta
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body" id="oc_body"></div>
</div>

<!-- MODAL VER DETALLE -->
<div class="modal fade modal-form" id="modalDetalle" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fa fa-eye"></i>
                    Detalle de Venta #<span id="det_ven_id"></span>
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal"><span>×</span></button>
            </div>
            <div class="modal-body">
                <table class="table tabla-detalle table-sm">
                    <thead>
                        <tr>
                            <th>Producto / variante</th>
                            <th class="text-center">Cantidad</th>
                            <th class="text-center">Unidad</th>
                            <th class="text-end">Precio Unit.</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody id="det_tbody"></tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="text-end fw-bold" style="border:none;">TOTAL</td>
                            <td class="text-end fw-bold" id="det_total" style="border:none;"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="modal-footer-custom">
                <span class="text-muted small">Tienda: <b><?php echo htmlspecialchars($alm_nombre); ?></b></span>
                <button class="btn btn-secondary" data-bs-dismiss="modal"><i class="fa fa-times me-1"></i> Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__.'/venta_pagos_modal.php'; ?>
<link rel="stylesheet" href="../assets/plugins/select2/css/select2.min.css">
<link rel="stylesheet" href="../css/venta_pagos.css?v=<?php echo filemtime('../css/venta_pagos.css'); ?>">
<script src="../assets/js/jquery-3.6.0.min.js"></script>
<script src="../js/src/bootstrap.min.js"></script>
<script src="../assets/js/feather.min.js"></script>
<script src="../assets/js/jquery.slimscroll.min.js"></script>
<script src="../assets/js/jquery.dataTables.min.js"></script>
<script src="../assets/js/dataTables.bootstrap4.min.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
<script src="../assets/plugins/sweetalert/sweetalert2.all.min.js"></script>
<script src="../assets/plugins/toastr/toastr.min.js"></script>
<script src="../assets/js/moment.min.js"></script>
<script src="../assets/js/bootstrap-datetimepicker.min.js"></script>
<link rel="stylesheet" href="../css/pdf_visor.css?v=<?php echo filemtime('../css/pdf_visor.css'); ?>">
<script src="../js/funciones_pdf_visor.js?v=<?php echo filemtime('../js/funciones_pdf_visor.js'); ?>"></script>
<link rel="stylesheet" href="../css/venta_detalle.css?v=<?php echo filemtime('../css/venta_detalle.css'); ?>">
<script src="../js/venta_detalle.js?v=<?php echo filemtime('../js/venta_detalle.js'); ?>"></script>
<script src="../js/funciones_list_venta.js?v=<?php echo filemtime('../js/funciones_list_venta.js'); ?>"></script>
<script src="../assets/plugins/select2/js/select2.min.js"></script>
<script src="../js/venta_pagos.js?v=<?php echo filemtime('../js/venta_pagos.js'); ?>"></script>
<script src="../js/funciones_header.js?v=<?php echo filemtime('../js/funciones_header.js'); ?>"></script>

</body>
</html>
<?php } else header("Location: $pagina_redireccion"); ?>
