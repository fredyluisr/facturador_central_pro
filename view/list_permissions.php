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
        <link rel="stylesheet" href="../assets/css/bootstrap-datetimepicker.min.css">
        <link rel="stylesheet" href="../assets/css/dataTables.bootstrap4.min.css">
        <link rel="stylesheet" href="../assets/plugins/icons/ionic/ionicons.css">
        <link rel="stylesheet" href="../assets/plugins/fontawesome/css/fontawesome.min.css">
        <link rel="stylesheet" href="../assets/plugins/fontawesome/css/all.min.css">
        <link rel="stylesheet" href="../assets/plugins/alertify/alertify.min.css">
        <link rel="stylesheet" href="../assets/plugins/toastr/toatr.css">
        <link rel="stylesheet" href="../assets/css/style.css">
        <link rel="stylesheet" href="../css/header.css?v=<?= filemtime(__DIR__.'/../css/header.css') ?>">
        <link rel="stylesheet" href="../css/modal_form.css">
        <link rel="stylesheet" href="../css/pos_ui.css?v=<?php echo filemtime('../css/pos_ui.css'); ?>">
        <link rel="stylesheet" href="../css/list_permissions.css?v=<?php echo filemtime('../css/list_permissions.css'); ?>">

        <link rel="stylesheet" href="../assets/plugins/icons/feather/feather.css">

    </head>
  <body class="pos-page">

  <?php include('header.php'); ?>


  <div class="page-wrapper">
    <div class="content">
        <div class="page-header">
            <div class="page-title">
                <h4>Lista de Permisos</h4>
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
                        Permisos
                    </span>
                </div>
            </div>
        </div>

        <div class="permissions-workspace">
          <div class="permissions-intro">
            <div class="permissions-intro-copy"><span class="permissions-intro-icon"><i class="fa fa-shield-alt"></i></span><div><h5>Administración de permisos</h5><p>Selecciona un rol y define los menús y páginas a los que tendrá acceso.</p></div></div>
            <button type="button" class="permissions-save" onclick="Guardar_Permisos()"><i class="fa fa-check"></i> Guardar permisos</button>
          </div>
          <div class="permissions-grid">
            <div class="permission-column"><div class="permission-panel">
              <div class="permission-panel-header"><span class="permission-panel-icon"><i class="fa fa-users-cog"></i></span><span class="permission-panel-title"><strong id="rol">Roles</strong><small>Selecciona un rol</small></span><span class="permission-step">1</span></div>
              <div id="lista" class="permission-list">
                    <?php
                      require_once('../model/rol.php');
                      $obj = new rol();
                      $lista = $obj -> listar_habilitados();
                                                    
                      foreach ($lista as $value)
                      {
                    ?>
                    
                        <button type="button" onClick="Cargar_Rol_Menu(<?php echo (int)$value["id"]; ?>, '<?php echo htmlspecialchars($value["descripcion"], ENT_QUOTES); ?>', this);" class="permission-role-item"><span class="permission-row-icon"><i class="fa fa-user-tag"></i></span><span class="permission-role-name"><?php echo htmlspecialchars($value["descripcion"]); ?></span><i class="fa fa-chevron-right permission-role-arrow"></i></button>
                    <?php
                      }
                    ?>
              </div></div></div>
            <div class="permission-column"><div class="permission-panel"><div class="permission-panel-header"><span class="permission-panel-icon"><i class="fa fa-th-large"></i></span><span class="permission-panel-title"><strong>Menús</strong><small>Secciones principales</small></span><span class="permission-step">2</span></div><div id="lista_menu" class="permission-list permission-check-list"></div></div></div>
            <div class="permission-column"><div class="permission-panel"><div class="permission-panel-header"><span class="permission-panel-icon"><i class="fa fa-project-diagram"></i></span><span class="permission-panel-title"><strong>Páginas</strong><small>Accesos específicos</small></span><span class="permission-step">3</span></div><div id="lista_pagina" class="permission-list permission-check-list"></div></div></div>
          </div>
        </div>
    </div>
  
  </div>


          <!-- MODAL PARA EL REGISTRO -->
          <div class="modal fade modal-form" id="reg_edi" aria-labelledby="myModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-primary modal-lg" role="document">
              <div class="modal-content bg-white">
    
                
                <div class="card-body">
                  <div class="tabs-set">
                    <ul class="nav nav-tabs" id="myTab" role="tablist">
                      <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="personales-tab" data-bs-toggle="tab" data-bs-target="#personales" type="button" role="tab" aria-controls="personales" aria-selected="true">Principales</button>
                      </li>
                      <li class="nav-item" role="presentation">
                        <button class="nav-link" id="opcionales-tab" data-bs-toggle="tab" data-bs-target="#opcionales" type="button" role="tab" aria-controls="opcionales" aria-selected="false">Opcionales</button>
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
                              <label class="col-md-2 col-form-label">DNI</label>
                              <div class="col-md-4">
                                <select class="form-control select2" style="width: 100%;" id="usu_tipo_doc">
                                  <option value="0" selected="selected">Seleccione Tipo Doc</option>
                                  <option value="1">DNI</option>
                                  <option value="2">C.E</option>
                                  <option value="9">OTRO</option>
                                </select>
                              </div>

                              <label class="col-md-2 col-form-label">Nro Documento</label>
                              <div class="col-md-4">
                                <input class="form-control" id="usu_nro_doc" type="text" placeholder="DNI" maxlength="10" onChange="Copiar_DNI();" onKeyPress="return Solo_Numeros(event);" autofocus="">
                              </div>                              
                            </div>

                            <div class="form-group row">
                              <label class="col-md-2 col-form-label">Ap. Paterno</label>
                              <div class="col-md-4">
                                <input class="form-control" id="usu_ape_paterno" type="Nombre" placeholder="Apellido Paterno" maxlength="25">
                              </div>

                              <label class="col-md-2 col-form-label">Ap. Materno</label>
                              <div class="col-md-4">
                                <input class="form-control" id="usu_ape_materno" type="Nombre" placeholder="Apellido Materno" maxlength="25">
                              </div>
                            </div>


                            <div class="form-group row">
                              <label class="col-md-2 col-form-label">Nombres</label>
                              <div class="col-md-4">
                                <input class="form-control" id="usu_nombres" type="Nombre" placeholder="Nombres" maxlength="50">
                              </div>

                              <label class="col-md-2 col-form-label">Móvil</label>
                              <div class="col-md-4">
                                <input class="form-control" id="usu_movil" type="Nombre" placeholder="Móvil" maxlength="20">
                              </div>
                            </div>

                            <hr>

                            <div class="form-group row">
                              <label class="col-md-2 col-form-label">Token</label>

                              <div class="col-md-4">
                                <div class="input-group mb-3">
                                  <input type="text" class="form-control" id="usu_token" placeholder="Token" maxlength="10" disabled>

                                  <button class="btn btn-primary" type="button" onclick="Async_Generar_Token()">
                                    <i class="fe fe-rotate-ccw" data-bs-toggle="tooltip" title="" data-bs-original-title="fe fe-rotate-ccw" aria-label="fe fe-rotate-ccw"></i>
                                  </button>
                                </div>
                              </div>

                              <label class="col-md-2 col-form-label" for="password-input">Estado</label>
                              <div class="col-md-4">
                                <select class="js-example-basic-single select2" id="usu_estado">
                                  <option value="1" selected="selected">HABILITADO</option>
                                  <option value="0">DESHABILITADO</option>
                                </select>
                              </div>

                            </div>
                          </div>
                      </div>

                      <div class="tab-pane fade" id="opcionales" role="tabpanel">
                        <div class="wordset mt-2">

                          <div class="form-group row">
                            <label class="col-md-2 col-form-label">Sexo</label>
                            <div class="col-md-4">
                              <select class="form-control select2" style="width: 100%;" id="usu_sexo">
                                <option value="0" selected="selected">Seleccione Sexo</option>
                                <option value="1">MASCULINO</option>
                                <option value="2">FEMENINO</option>
                              </select>
                            </div> 

                            <label class="col-md-2 col-form-label">Estado Civil</label>
                            <div class="col-md-4">
                              <select class="form-control select2" style="width: 100%;" id="usu_est_civil">
                                <option value="0" selected="selected">Seleccione Est. Civil</option>
                                <option value="1">SOLTERO</option>
                                <option value="2">CASADO</option>
                                <option value="3">VIUDO</option>
                                <option value="4">DIVORCIADO</option>
                              </select>
                            </div>
                          </div>

                          <div class="form-group row">
                            <label class="col-md-2 col-form-label" for="password-input">Dirección</label>
                            <div class="col-md-10">
                              <input class="form-control" id="usu_direccion" type="Nombre" name="password-input" placeholder="Dirección" maxlength="200">
                            </div>
                          </div>


                          <div class="form-group row">
                            <label class="col-md-2 col-form-label" for="password-input">Correo</label>
                            <div class="col-md-10">
                              <input class="form-control" id="usu_email" type="Nombre" name="password-input" placeholder="Correo" maxlength="200">
                            </div>
                          </div>


                          <div class="form-group row">
                            <label class="col-md-2 col-form-label">Fec Nac</label>
                            <div class="col-md-4">
                              <div class="input-groupicon">
                                <input type="text" placeholder="DD-MM-YYYY" class="datetimepicker" id="usu_fec_nac">
                                  <div class="addonset">
                                  <img src="../assets/img/icons/calendars.svg" alt="img">
                                </div>
                              </div>                            
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
                                <?php
                                  require_once('../model/rol.php');
                                  $obj = new rol();
                                      
                                  $lista = $obj -> listar_habilitados(1);
                                      
                                  foreach ($lista as $value)
                                  {
                                    echo "<option value='".$value["id"]."'>".$value["descripcion"]."</option>";
                                  }
                                ?>
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

                <div class="card-footer">
                  <button class="btn btn-sm btn-secondary" type="button" data-dismiss="modal"><i class="fa fa-close"></i> Cerrar</button>
                  <button class="btn btn-sm btn-primary pull-right" type="button" onClick="Guardar();"><i class="fa fa-check"></i> Guardar</button>
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
    
    <script src="../js/funciones_list_permissions.js?v=<?php echo filemtime('../js/funciones_list_permissions.js'); ?>"></script>
    <script src="../js/funciones_header.js?v=<?php echo filemtime('../js/funciones_header.js'); ?>"></script>
    
  </body>
</html>

<?php
}else
  header("Location: $pagina_redireccion");
?>
