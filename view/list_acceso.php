<?php

  require_once __DIR__ . '/../model/sesion.php';
  require_once('../model/var_global.php');

  if((int)($_SESSION["rol_id_ifact"] ?? 0) === 1){
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
        <link rel="stylesheet" href="../assets/css/dataTables.bootstrap4.min.css">
        <link rel="stylesheet" href="../assets/plugins/fontawesome/css/fontawesome.min.css">
        <link rel="stylesheet" href="../assets/plugins/fontawesome/css/all.min.css">
        <link rel="stylesheet" href="../assets/plugins/alertify/alertify.min.css">
        <link rel="stylesheet" href="../assets/plugins/toastr/toatr.css">
        <link rel="stylesheet" href="../assets/css/style.css">
        <link rel="stylesheet" href="../css/header.css?v=<?= filemtime(__DIR__.'/../css/header.css') ?>">
        <link rel="stylesheet" href="../css/pos_ui.css?v=<?php echo filemtime('../css/pos_ui.css'); ?>">

        <link rel="stylesheet" href="../assets/plugins/icons/feather/feather.css">
         <style>
            tfoot th {
                background: #f8f9fa;
                font-weight: bold;
                font-size: 15px;
            }
        </style>

    </head>
  <body class="pos-page">

  <?php include('header.php'); ?>

  <div class="page-wrapper">
    <div class="content">
        <div class="page-header">
            <div class="page-title">
                <h4>Control de Accesos</h4>
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
                        Control de Accesos
                    </span>
                </div>
            </div>
            <div class="page-btn d-flex flex-wrap gap-2 align-items-center">
              <button type="button" class="btn btn-danger" onclick="Cerrar_Todas_Las_Sesiones()">
                <i class="fas fa-sign-out-alt me-1"></i> Cerrar todas las activas
              </button>
              <button type="button" class="btn btn-dark" onclick="Aplicar_Filtros()">
                <i class="fa fa-search me-1"></i> Buscar Sesiones
              </button>
            </div>
        </div>
        <div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-lg-3 col-sm-6 col-12">
                <div class="form-group">
                    <label>Fecha Inicio</label>
                    <div class="input-groupicon">
                        <input type="text" placeholder="DD-MM-YYYY" class="datetimepicker" id="filtro_fecha_inicio">
                        <div class="addonset">
                            <img src="../assets/img/icons/calendars.svg" alt="img">
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-sm-6 col-12">
                <div class="form-group">
                    <label>Fecha Fin</label>
                    <div class="input-groupicon">
                        <input type="text" placeholder="DD-MM-YYYY" class="datetimepicker" id="filtro_fecha_fin">
                        <div class="addonset">
                            <img src="../assets/img/icons/calendars.svg" alt="img">
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-sm-6 col-12">
                <div class="form-group">
                    <label>Estado</label>
                    <select class="select2" id="filtro_estado">
                        <option value="">Todos</option>
                        <option value="1">Activas</option>
                        <option value="0">Cerradas</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
</div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table datanew dataTable no-footer" id="tablaListado" style="width:100%">
                        <thead>
                        <tr>
                            <th style="display:none;"><b>ID</b></th>
                            <th><b>Usuario</b></th>
                            <th><b>Rol</b></th>
                            <th><b>Fecha Login</b></th>
                            <th><b>IP</b></th>
                            <th><b>Duración</b></th>
                            <th><b>Dispositivo</b></th>
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


         <div class="modal fade" id="modal_detalles" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-primary modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Detalles de la Sesión</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><strong>Usuario</strong></label>
                            <p id="det_usuario" class="form-control-plaintext border-bottom"></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><strong>Rol</strong></label>
                            <p id="det_rol" class="form-control-plaintext border-bottom"></p>
                        </div>
                    </div>
                </div>

                <div class="row mt-2">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><strong>Fecha de Ingreso</strong></label>
                            <p id="det_fecha_login" class="form-control-plaintext border-bottom"></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><strong>Estado de Sesión</strong></label>
                            <p id="det_estado" class="form-control-plaintext border-bottom"></p>
                        </div>
                    </div>
                </div>

                <div class="row mt-2">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><strong>Dirección IP</strong></label>
                            <p id="det_ip" class="form-control-plaintext border-bottom"></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><strong>ID de Sesión</strong></label>
                            <p id="det_session_id" class="form-control-plaintext border-bottom" style="font-family: monospace; font-size: 11px;"></p>
                        </div>
                    </div>
                </div>

                <hr> <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label><strong>Dispositivo / User Agent</strong></label>
                            <div class="bg-light p-2 rounded border" style="font-size: 0.85rem;">
                                <span id="det_user_agent"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label><strong>Razón de Cierre / Observación</strong></label>
                            <p id="det_logout_reason" class="text-muted italic"></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-footer d-flex justify-content-end">
                <button class="btn btn-sm btn-secondary" type="button" data-bs-dismiss="modal">
                    <i class="fa fa-times me-1"></i> Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

          <!-- FIN MODAL ESTADÍSTICAS -->

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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
   <script src="../assets/js/bootstrap-datetimepicker.min.js"></script>

    <script>window.IFACT_SESSION_ID = <?php echo json_encode(session_id()); ?>;</script>
    <script src="../js/funciones_list_acceso.js?v=<?php echo filemtime('../js/funciones_list_acceso.js'); ?>"></script>
    <script src="../js/funciones_header.js?v=<?php echo filemtime('../js/funciones_header.js'); ?>"></script>

    <script>
      $(document).ready(function() {
        $('#fec_ini').datetimepicker({
          format: 'DD/MM/YYYY'
        });
      });

      $(document).ready(function() {
        $('#fec_fin').datetimepicker({
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
