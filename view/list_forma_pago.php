<?php
require_once __DIR__ . '/../model/sesion.php';
require_once('../model/var_global.php');

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
    <link rel="stylesheet" href="../assets/plugins/toastr/toatr.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../css/header.css?v=<?= filemtime(__DIR__.'/../css/header.css') ?>">
    <link rel="stylesheet" href="../css/modal_form.css">
    
    

<link rel="stylesheet" href="../css/pos_ui.css?v=<?php echo filemtime('../css/pos_ui.css'); ?>"><link rel="stylesheet" href="../css/administration_maintenance.css"><link rel="stylesheet" href="../css/formas_pago_cuentas.css?v=<?= filemtime(__DIR__.'/../css/formas_pago_cuentas.css') ?>"></head>
<body class="pos-page administration-maintenance">

<?php include('header.php'); ?>

<div class="page-wrapper">
    <div class="content">

        <div class="page-header">
            <div class="page-title">
                <h4>Formas de Pago</h4>
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
                        Formas de Pago
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
                                <th><b>Nombre</b></th>
                                <th><b>Cuentas asociadas</b></th>
                                <th><b>Estado</b></th>
                                <th><b>Ctrl</b></th>
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
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title mb-0" id="nombre_opcion">Nueva Forma de Pago</h5>
                <button type="button" class="close" data-bs-dismiss="modal"><span>×</span></button>
            </div>
            <div class="modal-body">
                <form action="#">
                    <div class="seccion-comprobante">
                        <div class="seccion-label-minimal">
                            <i class="fa fa-credit-card"></i>
                            Datos de la Forma de Pago
                        </div>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label-custom">Nombre <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="tpa_nombre" placeholder="Ej: EFECTIVO, YAPE, TRANSFERENCIA" maxlength="40">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label-custom">Estado</label>
                                <select id="tpa_estado" data-ifact-state hidden style="display:none">
                                    <option value="1">Habilitado</option>
                                    <option value="0">Deshabilitado</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="forma-cuentas-panel" id="forma_cuentas_panel">
                        <div class="forma-cuentas-header"><div><span>CUENTAS ASOCIADAS</span><h6 id="cuentas_forma_titulo">Guarda primero la forma de pago</h6></div><button type="button" class="btn-cuenta-nueva" id="btn_nueva_cuenta" onclick="NuevaCuentaForma()"><i class="fa fa-plus"></i> Agregar cuenta</button></div>
                        <div id="cuentas_forma_lista" class="forma-cuentas-lista"></div>
                        <div id="cuenta_forma_editor" class="cuenta-forma-editor" hidden>
                            <input type="hidden" id="cba_id">
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label-custom">Banco <span class="text-danger">*</span></label><select class="form-select" id="ban_id"><option value="">Seleccione banco</option></select></div>
                                <div class="col-md-6"><label class="form-label-custom">Moneda <span class="text-danger">*</span></label><select class="form-select" id="cba_moneda"><option value="PEN">PEN - Soles</option><option value="USD">USD - Dólares</option><option value="EUR">EUR - Euros</option></select></div>
                                <div class="col-md-6"><label class="form-label-custom">Propietario <span class="text-danger">*</span></label><input class="form-control" id="cba_propietario" maxlength="150" placeholder="Titular o nombre de caja"></div>
                                <div class="col-md-6"><label class="form-label-custom">Número <span class="text-danger">*</span></label><input class="form-control" id="cba_numero" maxlength="30" placeholder="Cuenta, celular o código interno"></div>
                                <div class="col-md-6"><label class="form-label-custom">Estado</label><select class="form-select" id="cba_estado"><option value="1">Habilitado</option><option value="0">Deshabilitado</option></select></div>
                                <div class="col-md-6 d-flex align-items-end gap-2"><button type="button" class="btn btn-light flex-fill" onclick="CancelarCuentaForma()">Cancelar</button><button type="button" class="btn btn-primary flex-fill" onclick="GuardarCuentaForma()"><i class="fa fa-check me-1"></i> Guardar cuenta</button></div>
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
<script src="../assets/plugins/toastr/toastr.min.js"></script>
<script src="../js/funciones_list_forma_pago.js?v=<?= filemtime(__DIR__.'/../js/funciones_list_forma_pago.js') ?>"></script>
<script src="../js/funciones_header.js?v=<?=filemtime(__DIR__.'/../js/funciones_header.js')?>"></script>

</body>
</html>
<?php
} else
    header("Location: $pagina_redireccion");
?>
