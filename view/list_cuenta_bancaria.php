<?php
require_once __DIR__ . '/../model/sesion.php';
require_once('../model/var_global.php');

// Las cuentas se administran ahora dentro de cada forma de pago.
header('Location: list_forma_pago.php');
exit;

if (($_SESSION["rol_id_ifact"] ?? 0) <> 0) {
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
    <link rel="stylesheet" href="../assets/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="../assets/plugins/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="../assets/plugins/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../css/header.css?v=<?= filemtime(__DIR__.'/../css/header.css') ?>">
    <link rel="stylesheet" href="../css/modal_form.css">
    
    

<link rel="stylesheet" href="../css/pos_ui.css?v=<?php echo filemtime('../css/pos_ui.css'); ?>"><link rel="stylesheet" href="../css/administration_maintenance.css"></head>
<body class="pos-page administration-maintenance">

<?php include('header.php'); ?>

<div class="page-wrapper">
    <div class="content">

        <div class="page-header">
            <div class="page-title">
                <h4>Cuentas Bancarias</h4>
                <div class="page-breadcrumb">
                    <a href="index.php">
                        <i class="fa fa-home"></i>
                        Inicio
                    </a>
                    <span class="breadcrumb-separator">
                        <i class="fa fa-angle-right"></i>
                    </span>
                    <span class="breadcrumb-parent">
                        Finanzas
                    </span>
                    <span class="breadcrumb-separator">
                        <i class="fa fa-angle-right"></i>
                    </span>
                    <span class="breadcrumb-current">
                        Cuentas Bancarias
                    </span>
                </div>
            </div>
            <div class="page-btn">
                <a class="btn btn-added" style="background-color:#000;border-color:#000;color:#fff;" onclick="Nuevo()">
                     <i class="fa fa-plus me-1"></i> Nuevo
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table datanew dataTable no-footer" id="tablaListado" style="width:100%">
                        <thead>
                            <tr>
                                <th><b>Banco</b></th>
                                <th><b>Moneda</b></th>
                                <th><b>Propietario</b></th>
                                <th><b>Número</b></th>
                                <th><b>Estado</b></th>
                                <th><b>Controles</b></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- MODAL -->
<div class="modal fade modal-form" id="reg_edi" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title mb-0" id="nombre_opcion">Nueva Cuenta Bancaria</h5>
                <button type="button" class="close" data-bs-dismiss="modal"><span>×</span></button>
            </div>
            <div class="modal-body">
                <form action="#">
                    <div class="seccion-comprobante">
                        <div class="seccion-label-minimal">
                            <i class="fa fa-university"></i>
                            Datos de la Cuenta Bancaria
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label-custom">Banco <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="cba_banco" placeholder="Ej: BANCO DE CRÉDITO DEL PERÚ" maxlength="100">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">Moneda <span class="text-danger">*</span></label>
                                <select class="form-select" id="cba_moneda" style="width:100%;">
                                    <option value="PEN">PEN - Soles</option>
                                    <option value="USD">USD - Dólares</option>
                                    <option value="EUR">EUR - Euros</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">Propietario <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="cba_propietario" placeholder="Nombre del titular" maxlength="150">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">Número de Cuenta <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="cba_numero" placeholder="Número de cuenta" maxlength="30">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">Estado</label>
                                <select id="cba_estado" data-ifact-state hidden style="display:none">
                                    <option value="1">Habilitado</option>
                                    <option value="0">Deshabilitado</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer-custom">
                <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">
                    <i class="fa fa-times me-1"></i> Cerrar
                </button>
                <button class="btn" type="button" id="btn_guardar" onclick="Guardar()">
                    <i class="fa fa-check me-1"></i> Guardar
                </button>
            </div>
        </div>
    </div>
</div>
<!-- FIN MODAL -->

<script src="../assets/js/jquery-3.6.0.min.js"></script>

<script src="../assets/js/feather.min.js"></script>
<script src="../assets/js/jquery.slimscroll.min.js"></script>
<script src="../assets/js/jquery.dataTables.min.js"></script>
<script src="../assets/js/dataTables.bootstrap4.min.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
<script src="../assets/plugins/sweetalert/sweetalert2.all.min.js"></script>
<script src="../js/funciones_list_cuenta_bancaria.js?v=<?= filemtime(__DIR__.'/../js/funciones_list_cuenta_bancaria.js') ?>"></script>
<script src="../js/funciones_header.js?v=<?=filemtime(__DIR__.'/../js/funciones_header.js')?>"></script>

</body>
</html>
<?php
} else
    header("Location: $pagina_redireccion");
?>
