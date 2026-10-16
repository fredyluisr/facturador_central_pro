<?php

  require_once __DIR__ . '/../model/sesion.php';
  require_once('../model/var_global.php');

  if($_SESSION["rol_id_ifact"] <> 0){
?>

<!DOCTYPE html>
  <html lang="en">
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
        <link rel="stylesheet" href="../css/pos_ui.css?v=20260916-state2">

    </head>
  <body class="pos-page">

  <?php include('header.php'); ?>

  <div class="page-wrapper">
    <div class="content">
        <div class="page-header">
            <div class="page-title">
                <h4>Mantenedor Roles</h4>
                <div class="page-breadcrumb">
                    <a href="index.php">
                        <i class="fa fa-home"></i>
                        Inicio
                    </a>
                    <span class="breadcrumb-separator">
                        <i class="fa fa-angle-right"></i>
                    </span>
                    <span class="breadcrumb-parent">
                        Sistema
                    </span>
                    <span class="breadcrumb-separator">
                        <i class="fa fa-angle-right"></i>
                    </span>
                    <span class="breadcrumb-current">
                        Roles
                    </span>
                </div>
            </div>
            <div class="page-btn">
                <a class="btn btn-added" onclick="Nuevo()"><img src="../assets/img/icons/plus.svg" alt="img" class="me-1">Nuevo</a>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table datanew dataTable no-footer" id="tablaListado" style="width:100%">
                        <thead>
                            <tr>
                                <th><b>ID</b></th>
                                <th><b>Nombre</b></th>
                                <th><b>Descripción</b></th>
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
            <div class="modal-dialog modal-primary" role="document">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title mb-0" id="nombre_opcion">rol</h5>
                  <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                </div>
                
                <div class="card-body">
                  <form class="form-horizontal" action="" method="post" enctype="multipart/form-data">
                      
                    <div class="form-group row">
                      <label class="col-md-3 col-form-label" for="password-input">Nombre</label>
                      <div class="col-md-9">
                        <input class="form-control" id="rol_nombre" type="Nombre" name="password-input" placeholder="Nombre" maxlength="40" autofocus="">
                      </div>
                    </div>
                    <div class="form-group row">
                      <label class="col-md-3 col-form-label" for="password-input">Descripción</label>
                      <div class="col-md-9">
                        <textarea class="form-control" id="rol_descripcion" rows="2" placeholder="Descripción del Rol" maxlength="100"></textarea>
                      </div>
                    </div>

                    <div class="form-group row">
                      <label class="col-md-3 col-form-label" for="password-input" style="text-align:right;">Estado</label>
                      <div class="col-md-9">
                        <select id="rol_estado" data-ifact-state hidden style="display:none">
                          <option value="1" selected="selected">Habilitado</option>
                          <option value="0">Deshabilitado</option>
                        </select>
                      </div>
                    </div>
                  </form>
                </div>
                <div class="card-footer d-flex justify-content-between">
                    <button class="btn btn-sm btn-secondary" type="button" data-bs-dismiss="modal">
                        <i class="ion-close"></i> Cerrar
                    </button>
                    <button class="btn btn-sm btn-primary" type="button" onClick="Guardar();">
                        <i class="fa fa-check"></i> Guardar
                    </button>
                </div>
              </div>
            </div>
          </div>
          <!-- FIN MODAL PERFILES -->




    <script src="../assets/js/jquery-3.6.0.min.js"></script>
    <script src="../js/src/bootstrap.min.js"></script>
    <script src="../assets/js/feather.min.js"></script>
    <script src="../assets/js/jquery.slimscroll.min.js"></script>
    <script src="../assets/js/jquery.dataTables.min.js"></script>
    <script src="../assets/js/dataTables.bootstrap4.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
    <script src="../assets/plugins/sweetalert/sweetalert2.all.min.js"></script>
    <script src="../assets/plugins/alertify/alertify.min.js"></script>
    <script src="../assets/plugins/toastr/toastr.min.js"></script>
    <script src="../js/funciones_list_rols.js?v=<?php echo filemtime('../js/funciones_list_rols.js'); ?>"></script>
    <script src="../js/funciones_header.js?v=<?=filemtime(__DIR__.'/../js/funciones_header.js')?>"></script>
  </body>
</html>

<?php
}else
  header("Location: $pagina_redireccion");
?>
