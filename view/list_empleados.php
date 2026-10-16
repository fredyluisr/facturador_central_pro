<?php

  require_once('../model/sesion.php');

  require_once('../model/var_global.php');

  if(($_SESSION["rol_id_ifact"] ?? 0) <> 0){

?>

<!DOCTYPE html>
<html lang="es">
  <head>
      <meta charset="utf-8">
      <meta http-equiv="X-UA-Compatible" content="IE=edge">
      <link rel="shortcut icon" href=<?php echo $icono; ?>>
      <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
      <meta name="description" content="<?php echo $descripcion; ?>">
      <meta name="author" content="<?php echo $autor; ?>">
      <title><?php echo $titulo; ?></title>

      <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
      <link rel="stylesheet" href="../assets/css/animate.css">
      <link rel="stylesheet" href="../assets/plugins/select2/css/select2.min.css">
      <link rel="stylesheet" href="../assets/css/bootstrap-datetimepicker.min.css">
      <link rel="stylesheet" href="../assets/css/dataTables.bootstrap4.min.css">
      <link rel="stylesheet" href="../assets/plugins/icons/ionic/ionicons.css">
      <link rel="stylesheet" href="../assets/plugins/fontawesome/css/fontawesome.min.css">
      <link rel="stylesheet" href="../assets/plugins/fontawesome/css/all.min.css">
      <link rel="stylesheet" href="../assets/plugins/alertify/alertify.min.css">
      <link rel="stylesheet" href="../assets/plugins/toastr/toatr.css">
      <link rel="stylesheet" href="../assets/css/style.css">
      <link rel="stylesheet" href="../css/header.css?v=<?= filemtime(__DIR__.'/../css/header.css') ?>">
      <link rel="stylesheet" href="../css/modal_form.css?v=<?php echo filemtime('../css/modal_form.css'); ?>">

      <link rel="stylesheet" href="../css/list_empleados.css?v=<?php echo filemtime('../css/list_empleados.css'); ?>">

  <link rel="stylesheet" href="../css/pos_ui.css?v=<?php echo filemtime('../css/pos_ui.css'); ?>"></head>

  <body class="pos-page">

  <?php include('header.php'); ?>

  <div class="page-wrapper">
    <div class="content">
        <div class="page-header">
            <div class="page-title">
                <h4>Mantenedor Empleados</h4>
                <div class="page-breadcrumb">
                    <a href="index.php">
                        <i class="fa fa-home"></i>
                        Inicio
                    </a>
                    <span class="breadcrumb-separator">
                        <i class="fa fa-angle-right"></i>
                    </span>
                    <span class="breadcrumb-parent">
                        Recursos Humanos
                    </span>
                    <span class="breadcrumb-separator">
                        <i class="fa fa-angle-right"></i>
                    </span>
                    <span class="breadcrumb-current">
                        Empleados
                    </span>
                </div>
            </div>
            <div class="page-btn">
                <a class="btn btn-added" style="background-color: #000; border-color: #000; color: #fff;" onclick="Nuevo()"><img src="../assets/img/icons/plus.svg" alt="img" class="me-1">Nuevo</a>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table datanew dataTable no-footer" id="tablaListado" style="width:100%">
                        <thead>
                            <tr>
                                <th><b>Nombres</b></th>
                                <th><b>Documento</b></th>
                                <th><b>Cargo</b></th>
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


  <!-- MODAL PARA EL REGISTRO -->
  <div class="modal fade modal-form" id="reg_edi" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title mb-0" id="nombre_opcion">Empleado</h5>
          <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
        </div>

        <div class="modal-body">

            <input type="hidden" id="emp_id" value="0">

            <!-- Tabs -->
            <ul class="nav nav-tabs mb-0" id="myTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="datos-personales-tab" data-bs-toggle="tab"
                       href="#datos-personales" role="tab" aria-controls="datos-personales" aria-selected="true">
                        Datos Personales
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="opcionales-tab" data-bs-toggle="tab"
                       href="#opcionales" role="tab" aria-controls="opcionales" aria-selected="false">
                        Opcionales
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="fotografia-tab" data-bs-toggle="tab"
                       href="#fotografia" role="tab" aria-controls="fotografia" aria-selected="false">
                        Fotografía
                    </a>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="myTabContent">

              <!-- Datos Personales Tab -->
              <div class="tab-pane fade show active" id="datos-personales" role="tabpanel" aria-labelledby="datos-personales-tab">
                <form class="form-horizontal" action="" method="post" enctype="multipart/form-data">
                  <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label-custom">Tipo Documento</label>
                            <select class="form-select" style="width: 100%;" id="emp_tipo_doc">
                                <option value='1'>DNI</option>
                                <option value='2'>CE</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label-custom">Nro Documento</label>
                            <div class="employee-document-field">
                                <input class="form-control" id="emp_nro_doc" type="text" placeholder="Nro Documento" maxlength="11" onKeyPress="return Solo_Numeros(event);">
                                <button class="btn btn-sm btn-primary" type="button" id="btn_reniec" onclick="ConsultaDNI();" title="Buscar DNI" aria-label="Buscar DNI">
                                    <i class="fa fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label-custom">Apellido Paterno</label>
                            <input class="form-control" id="emp_ape_paterno" type="text" placeholder="Apellido Paterno" maxlength="50" oninput="this.value = this.value.toUpperCase();">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label-custom">Apellido Materno</label>
                            <input class="form-control" id="emp_ape_materno" type="text" placeholder="Apellido Materno" maxlength="50" oninput="this.value = this.value.toUpperCase();">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label-custom">Nombres</label>
                            <input class="form-control" id="emp_nombres" type="text" placeholder="Nombres" maxlength="70" oninput="this.value = this.value.toUpperCase();">
                        </div>
                    </div>
                  </div>

                  <div class="row">
                    
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label-custom">Alias</label>
                            <input class="form-control" id="emp_alias" type="text" placeholder="Alias" maxlength="70" oninput="this.value = this.value.toUpperCase();">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label-custom">Cargo</label>
                            <select class="form-select" style="width: 100%;" id="car_id">
                                <option value='0'>Seleccione Cargo</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label-custom">Estado</label>
                            <select id="emp_estado" data-ifact-state hidden style="display:none">
                                <option value='1'>HABILITADO</option>
                                <option value='0'>DESHABILITADO</option>
                            </select>
                        </div>
                    </div>
                  </div>
                </form>
              </div>

              <!-- Opcionales Tab -->
              <div class="tab-pane fade" id="opcionales" role="tabpanel" aria-labelledby="opcionales-tab">
                <form class="form-horizontal" action="" method="post" enctype="multipart/form-data">
                  <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label-custom">Dirección</label>
                            <input class="form-control" id="emp_direccion" type="text" placeholder="Dirección" maxlength="200">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label-custom">Email</label>
                            <input class="form-control" id="emp_email" type="email" placeholder="Email" maxlength="200">
                        </div>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="form-label-custom">Fecha Nacimiento</label>
                            <input type="date" class="form-control" id="emp_fec_nac" min="1900-01-01">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="form-label-custom">Celular</label>
                            <input class="form-control" id="emp_movil" type="text" placeholder="Celular" maxlength="45">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="form-label-custom">Estado Civil</label>
                            <select class="form-select" style="width: 100%;" id="emp_est_civil_opcional">
                                <option value='1'>SOLTERO</option>
                                <option value='2'>CASADO</option>
                                <option value='3'>VIUDO</option>
                                <option value='4'>DIVORCIADO</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="form-label-custom">Sexo</label>
                            <select class="form-select" style="width: 100%;" id="emp_sexo_opcional">
                                <option value='1'>MASCULINO</option>
                                <option value='2'>FEMENINO</option>
                            </select>
                        </div>
                    </div>
                  </div>
                </form>
              </div>

              <!-- Fotografía Tab -->
              <div class="tab-pane fade" id="fotografia" role="tabpanel" aria-labelledby="fotografia-tab">
                <div class="employee-photo-view">
                    <div class="employee-photo-frame">
                        <img id="empleado_foto" src="../img/trabajadores/usuario-default.svg" alt="Fotografía del empleado">
                    </div>
                    <div class="employee-photo-info">
                        <span class="employee-photo-label">FOTOGRAFÍA DEL PERFIL</span>
                        <h5 id="empleado_foto_nombre">Empleado</h5>
                        <p id="empleado_foto_estado">Sin fotografía disponible. Se muestra la imagen predeterminada.</p>
                        <button type="button" class="btn btn-danger" id="btn_eliminar_foto" onclick="EliminarFotoEmpleado();" disabled>
                            <i class="fa fa-trash-alt me-1"></i> Eliminar fotografía
                        </button>
                    </div>
                </div>
              </div>

            </div><!-- /tab-content -->
        </div><!-- /modal-body -->

        <div class="modal-footer-custom">
            <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">
                <i class="fa fa-times me-1"></i> Cerrar
            </button>
            <button class="btn btn-primary" type="button" id="btn_guardar" onClick="Guardar();">
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
    <script src="../assets/plugins/select2/js/select2.min.js"></script> 
    <script src="../assets/js/jquery.dataTables.min.js"></script>
    <script src="../assets/js/dataTables.bootstrap4.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
    <script src="../assets/plugins/sweetalert/sweetalert2.all.min.js"></script>
    <script src="../assets/plugins/alertify/alertify.min.js"></script>
    <script src="../assets/plugins/toastr/toastr.min.js"></script>

    <script src="../assets/js/moment.min.js"></script>
    <script src="../assets/js/bootstrap-datetimepicker.min.js"></script>

    <script src="../js/funciones_list_empleados.js?v=<?php echo filemtime('../js/funciones_list_empleados.js'); ?>"></script>
    <script src="../js/funciones_header.js?v=<?php echo filemtime('../js/funciones_header.js'); ?>"></script>
  </body>
</html>

<?php
} else {
    header("Location: $pagina_redireccion");
}
?>
