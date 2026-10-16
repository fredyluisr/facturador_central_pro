<?php

  require_once __DIR__ . '/../model/sesion.php';
  require_once('../model/var_global.php');

  if(($_SESSION["rol_id_ifact"] ?? 0) <> 0){
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
        <link rel="stylesheet" href="../assets/css/dataTables.bootstrap4.min.css">
        <link rel="stylesheet" href="../assets/plugins/fontawesome/css/fontawesome.min.css">
        <link rel="stylesheet" href="../assets/plugins/fontawesome/css/all.min.css">
        <link rel="stylesheet" href="../assets/plugins/select2/css/select2.min.css">
        <link rel="stylesheet" href="../assets/plugins/alertify/alertify.min.css">
        <link rel="stylesheet" href="../assets/plugins/toastr/toatr.css">
        <link rel="stylesheet" href="../assets/css/style.css">
        <link rel="stylesheet" href="../css/header.css?v=<?= filemtime(__DIR__.'/../css/header.css') ?>">
        <link rel="stylesheet" href="../css/modal_form.css">
        <link rel="stylesheet" href="../css/pos_ui.css?v=<?php echo filemtime('../css/pos_ui.css'); ?>">
        <link rel="stylesheet" href="../css/list_entidad.css">

    </head>
  <body class="pos-page entidad-page">

  <?php include('header.php'); ?>

  <div class="page-wrapper">
    <div class="content">
        <div class="page-header">
            <div class="page-title">
                <h4>Mantenedor Entidades </h4>
                <div class="page-breadcrumb">
                    <a href="index.php">
                        <i class="fa fa-home"></i>
                        Inicio
                    </a>
                    <span class="breadcrumb-separator">
                        <i class="fa fa-angle-right"></i>
                    </span>
                    <span class="breadcrumb-parent">
                        Catálogo
                    </span>
                    <span class="breadcrumb-separator">
                        <i class="fa fa-angle-right"></i>
                    </span>
                    <span class="breadcrumb-current">
                        Entidades
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
                                
                                <th><b>Tipo Doc</b></th>
                                <th><b>Nro Doc</b></th>
                                <th><b>Nombres</b></th>
                                <th><b>Dirección</b></th>
                                <th><b>Email</b></th>
                                <th><b>Contacto</b></th>
                                <th><b>Tipo</b></th>
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
                  <h5 class="modal-title mb-0" id="nombre_opcion">Nombre</h5>
                  <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                </div>

                <div class="modal-body">
                    <form action="#">
                        <div class="seccion-comprobante">
                            <div class="seccion-label-minimal">
                                <i class="fa fa-building"></i>
                                Datos de la Entidad
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label-custom">Tipo Documento <span class="text-danger">*</span></label>
                                    <select class="form-select" style="width: 100%;" id="ent_tipo_doc" required>
                                        <option value=''>Seleccione</option>
                                        <option value='0'>Doc.Trib.No.Dom.Sin:Ruc</option>
                                        <option value='1'>DNI</option>
                                        <option value='4'>Carne Extranjeria</option>
                                        <option value='6'>RUC</option>
                                        <option value='7'>Pasaporte</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-custom">Nro Documento <span class="text-danger">*</span></label>
                                    <div class="entity-document-field">
                                        <input type="text" class="form-control" id="ent_nro_doc" placeholder="Ingrese número" maxlength="15" required>
                                        <button class="btn" type="button" id="btn_buscar_doc" title="Buscar DNI/RUC" hidden>
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label-custom">Nombres/Razón Social <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="ent_nombres" placeholder="Ingrese nombres o razón social" maxlength="200" required>
                                </div>
                                <!-- Dirección: ocupa toda la fila -->
                                <div class="col-md-12">
                                    <label class="form-label-custom">Dirección</label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="ent_direccion"
                                        placeholder="Ingrese dirección"
                                        maxlength="300"
                                    >
                                </div>

                                <!-- Marca: mitad de la fila -->
                                <div class="col-md-6">
                                    <label class="form-label-custom">Marca</label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="ent_marca"
                                        placeholder="Ingrese marca"
                                        maxlength="150"
                                    >
                                </div>

                                <!-- Contacto: mitad de la fila -->
                                <div class="col-md-6">
                                    <label class="form-label-custom">Contacto</label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="ent_contacto"
                                        placeholder="Teléfono o celular"
                                        maxlength="25"
                                    >
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label-custom">Email</label>
                                    <input type="email" class="form-control" id="ent_email" placeholder="correo@ejemplo.com" maxlength="100">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label-custom" for="ent_cliente">Es Cliente</label>
                                    <label class="entity-switch" for="ent_cliente"><input type="checkbox" id="ent_cliente" role="switch"><span class="entity-switch-track" aria-hidden="true"></span><span class="entity-switch-value" aria-hidden="true"></span></label>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label-custom" for="ent_proveedor">Es Proveedor</label>
                                    <label class="entity-switch" for="ent_proveedor"><input type="checkbox" id="ent_proveedor" role="switch"><span class="entity-switch-track" aria-hidden="true"></span><span class="entity-switch-value" aria-hidden="true"></span></label>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label-custom">Estado</label>
                                    <select id="ent_estado" data-ifact-state hidden style="display:none">
                                        <option value="1" selected="selected">Habilitado</option>
                                        <option value="0">Deshabilitado</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">
                        <i class="fa fa-times me-1"></i> Cerrar
                    </button>
                    <button class="btn btn-primary" type="button" id="btn_guardar" onClick="Guardar();">
                        <i class="fa fa-check me-1"></i> Guardar
                    </button>
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
    <script src="../assets/plugins/alertify/alertify.min.js"></script>
    <script src="../assets/plugins/toastr/toastr.min.js"></script>
    <script src="../assets/plugins/select2/js/select2.min.js"></script>
    <script src="../js/funciones_list_entidad.js?v=<?php echo filemtime('../js/funciones_list_entidad.js'); ?>"></script>
    <script src="../js/funciones_header.js?v=<?=filemtime(__DIR__.'/../js/funciones_header.js')?>"></script>
  </body>
</html>

<?php
}else
  header("Location: $pagina_redireccion");
?>
