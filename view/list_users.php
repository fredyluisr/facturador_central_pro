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
        <link rel="stylesheet" href="../css/pos_ui.css?v=<?php echo filemtime('../css/pos_ui.css'); ?>">

        <link rel="stylesheet" href="../assets/plugins/icons/feather/feather.css">

    </head>
  <body class="pos-page">

  <?php include('header.php'); ?>


  <div class="page-wrapper">
    <div class="content">
        <div class="page-header">
            <div class="page-title">
                <h4>Mantenedor Usuario</h4>
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
                        Usuarios
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
                            <th type="hidden"><b>ID</b></th>
                            <th><b>Nombre</b></th>
                            <th><b>Token</b></th>
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
            <div class="modal-dialog modal-primary modal-lg" role="document">
              <div class="modal-content bg-white"><div class="modal-header"><h5 class="modal-title mb-0">Usuario</h5><button type="button" class="close" data-bs-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">×</span></button></div>
    
                
                <div class="card-body">
                  <div class="tabs-set">
                    <ul class="nav nav-tabs" id="myTab" role="tablist">
                      <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="personales-tab" data-bs-toggle="tab" data-bs-target="#personales" type="button" role="tab" aria-controls="personales" aria-selected="true">Principales</button>
                      </li>
                      <li class="nav-item" role="presentation">
                        <button class="nav-link" id="roles-tab" data-bs-toggle="tab" data-bs-target="#roles" type="button" role="tab" aria-controls="roles" aria-selected="false">Roles</button>
                      </li>
                    </ul>
                    
                    <div class="tab-content" id="myTabContent">

                      <div class="tab-pane fade show active" id="personales" role="tabpanel" aria-labelledby="personales-tab">                        
                          <div class="wordset mt-2">

                            <br>

                            <div class="form-group row">
                              <label class="col-md-2 col-form-label">Empleado</label>
                              <div class="col-md-10">
                                <select class="form-control select2" style="width:100%;" id="emp_id">
                                  <option value="0">Seleccione un Empleado</option>
                                </select>
                              </div>
                            </div>

                            <hr>

                            <div class="form-group row">
                              <label class="col-md-2 col-form-label">Token</label>

                              <div class="col-md-4">
                                <div class="input-group mb-3">
                                  <input type="text" class="form-control" id="usu_token" placeholder="Token" maxlength="10" disabled>

                                  <button class="btn btn-primary" type="button" id="btnGenerar_Token" onclick="Async_Generar_Token()">
                                    <i class="fe fe-rotate-ccw" data-bs-toggle="tooltip" title="" data-bs-original-title="fe fe-rotate-ccw" aria-label="fe fe-rotate-ccw"></i>
                                  </button>
                                </div>
                              </div>

                              <label class="col-md-2 col-form-label" for="password-input">Estado</label>
                              <div class="col-md-4">
                                <select id="usu_estado" data-ifact-state hidden style="display:none">
                                  <option value="1" selected="selected">HABILITADO</option>
                                  <option value="0">DESHABILITADO</option>
                                </select>
                              </div>

                            </div>
                          </div>
                      </div>

                      

                      <div class="tab-pane fade" id="roles" role="tabpanel">
                        <div class="wordset mt-2">
                          <div class="form-group row">
                            <label class="col-md-2 col-form-label">Rol</label>
                            <div class="col-md-8">
                              <select class="form-control select2" style="width: 100%;" id="rol_id">
                                <option  value="0" selected="selected" >Seleccionar Rol</option>                                
                              </select>
                            </div>
                            <div class="col-md-2">
                              <button class="btn btn-block btn-primary" type="button" style="background-color: #000000; border-color: #000000;" onclick="Agregar_Perfil();" title="Agregar"> <i class="fa fa-plus"></i> </button>
                            </div>
                          </div>

                          <div class="form-group row">
                            <div id="listDetalle_min" class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
                              <table class="table table-responsive-sm table-sm" id="tbListDet_rol">
                                <thead>
                                  <tr>
                                    <td width="40" style="text-align: center; color: #000000; font-weight: bold; background-color: #FFFFFF;">ID</td>
                                    <td width="120" style="color: #000000; font-weight: bold; background-color: #FFFFFF;">ROL</td>
                                    <td width="10" style="text-align: center; color: #000000; font-weight: bold; background-color: #FFFFFF;">CTRL</td>
                                  </tr>
                                </thead> 

                                <tbody>

                                </tbody>
                              </table>
                            </div>
                          </div>
                        </div>                        
                      </div>
                  
                    </div>
                  </div>
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
    
    <script src="../js/funciones_list_users.js?v=<?php echo filemtime('../js/funciones_list_users.js'); ?>"></script>
    <script src="../js/funciones_header.js?v=<?=filemtime(__DIR__.'/../js/funciones_header.js')?>"></script>

    <script>
      $(document).ready(function() {
        $('#per_fec_nac').datetimepicker({
          format: 'DD/MM/YYYY'
        });
      });
    </script>
    
  </body>
</html>

<?php
}else
  header("Location: $pagina_redireccion");
?>
