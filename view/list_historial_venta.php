<?php
    require_once __DIR__ . '/../model/sesion.php';
    require_once('../model/var_global.php');
    require_once('../model/validar_sesion_pos.php');
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
    <link rel="stylesheet" href="../css/list_historial_venta.css?v=<?php echo filemtime('../css/list_historial_venta.css'); ?>">
    <link rel="stylesheet" href="../css/pos_ui.css?v=<?php echo filemtime('../css/pos_ui.css'); ?>">
</head>
<body class="pos-page">

<?php include('header.php'); ?>

<div class="page-wrapper">
    <div class="content">

        <div class="page-header">
            <div class="page-title">
                <h4>Historial de Ventas</h4>
                <div class="page-breadcrumb">
                    <a href="index.php"><i class="fa fa-home"></i> Inicio</a>
                    <span class="breadcrumb-separator"><i class="fa fa-angle-right"></i></span>
                    <span class="breadcrumb-parent">Punto de Venta</span>
                    <span class="breadcrumb-separator"><i class="fa fa-angle-right"></i></span>
                    <span class="breadcrumb-current">Historial</span>
                </div>
            </div>
            <div class="page-btn d-flex gap-2">
                <a href="list_venta.php" class="btn btn-dark">
                    <i class="fa fa-shopping-cart me-1"></i> Ir a Ventas
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
                            <input type="text" placeholder="DD-MM-YYYY" class="datetimepicker form-control" id="filtro_desde">
                            <div class="addonset"><img src="../assets/img/icons/calendars.svg" alt="img"></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label><b>Fecha Fin</b></label>
                        <div class="input-groupicon">
                            <input type="text" placeholder="DD-MM-YYYY" class="datetimepicker form-control" id="filtro_hasta">
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
                    <table class="table datanew dataTable no-footer" id="tablaHistorial" style="width:100%">
                        <thead>
                            <tr>
                                <th><b>Fecha</b></th>
                                <th><b>Comprobante</b></th>
                                <th><b>Cliente</b></th>
                                <th class="text-end"><b>Total</b></th>
                                <th class="text-center"><b>Estado</b></th>
                                <th class="text-center"><b>Estado SUNAT</b></th>
                                <th class="text-center"><b>Opciones</b></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

                <!-- TOTAL -->
                <div class="total-box">
                    <span class="text-muted" style="font-size:.85rem; font-weight:400;">Total ventas activas: </span>
                    <span id="total_visible">S/ 0.00</span>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- PANEL LATERAL DE DETALLE -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasDetalleVenta" data-bs-scroll="true">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="detalle_titulo"><i class="fa fa-receipt me-2"></i>Detalle de venta</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <div class="offcanvas-body" id="detalle_contenido"></div>
</div>

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
<script src="../js/funciones_historial_venta.js?v=<?php echo filemtime('../js/funciones_historial_venta.js'); ?>"></script>
<script src="../js/funciones_header.js?v=<?php echo filemtime('../js/funciones_header.js'); ?>"></script>

</body>
</html>
<?php } else header("Location: $pagina_redireccion"); ?>
