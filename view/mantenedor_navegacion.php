<?php
require_once __DIR__ . '/../model/sesion.php';
require_once __DIR__ . '/../model/var_global.php';
if ((int)($_SESSION['rol_id_ifact'] ?? 0) !== 1) {
    header('Location: index.php'); exit;
}
if (!isset($mantenedorPaginas)) { http_response_code(404); exit; }
$nombre = $mantenedorPaginas ? 'Páginas' : 'Menús';
?>
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $nombre ?> · iFact</title>
<link rel="shortcut icon" href="<?= htmlspecialchars($icono, ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="../assets/plugins/fontawesome/css/all.min.css">
<link rel="stylesheet" href="../assets/plugins/simpleline/simple-line-icons.css">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../css/header.css?v=<?= filemtime(__DIR__.'/../css/header.css') ?>">
<link rel="stylesheet" href="../css/modal_form.css?v=<?= filemtime('../css/modal_form.css') ?>">
<link rel="stylesheet" href="../css/pos_ui.css?v=<?= filemtime('../css/pos_ui.css') ?>">
<link rel="stylesheet" href="../css/mantenedor_navegacion.css?v=<?= filemtime(__DIR__ . '/../css/mantenedor_navegacion.css') ?>">
</head><body class="pos-page">
<?php include __DIR__ . '/header.php'; ?>
<div class="page-wrapper"><div class="content" id="mantenedorNavegacion" data-tipo="<?= $mantenedorPaginas ? 'pagina' : 'menu' ?>">
<div class="page-header"><div class="page-title"><h4>Mantenedor <?= $nombre ?></h4>
<div class="page-breadcrumb"><a href="index.php"><i class="fa fa-home"></i> Inicio</a><span> › Herramientas › <?= $nombre ?></span></div></div>
<div class="page-btn"><button class="btn btn-added" id="nuevoRegistro"><i class="fa fa-plus me-1"></i> Nuevo</button></div></div>
<div class="card"><div class="card-body"><div class="table-responsive">
<table class="table" id="tablaListado" style="width:100%"><thead><tr>
<th>Nombre</th><?php if ($mantenedorPaginas): ?><th>Menú</th><th>Ruta</th><?php endif; ?><th>Icono</th><th>Orden</th><th>Estado</th>
</tr></thead><tbody></tbody></table></div></div></div></div></div>
<div class="modal fade modal-form" id="reg_edi" tabindex="-1" aria-labelledby="nombre_opcion" aria-hidden="true">
<div class="modal-dialog modal-lg"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title" id="nombre_opcion">Nuevo registro</h5><button class="close" type="button" data-bs-dismiss="modal" aria-label="Cerrar">×</button></div>
<form id="formNavegacion" novalidate><div class="modal-body"><div class="row g-3">
<div class="col-md-6"><label for="registroNombre" class="form-label">Nombre</label><input id="registroNombre" class="form-control" required maxlength="<?= $mantenedorPaginas ? 40 : 50 ?>"></div>
<?php if ($mantenedorPaginas): ?>
<div class="col-md-6"><label for="registroMenu" class="form-label">Menú</label><select id="registroMenu" class="form-select" required><option value="">Seleccionar menú</option></select></div>
<div class="col-12"><label for="registroRuta" class="form-label">Ruta</label><input id="registroRuta" class="form-control" required maxlength="50" placeholder="list_ejemplo.php"></div>
<?php endif; ?>
<div class="col-md-4"><label for="registroIcono" class="form-label">Icono</label><div class="input-group"><span class="input-group-text" aria-hidden="true"><i id="iconoPreview" class="fas fa-bars"></i></span><input id="registroIcono" class="form-control" required maxlength="100" placeholder="fas fa-bars" list="iconosSugeridos"></div><datalist id="iconosSugeridos"><option value="fas fa-bars"><option value="far fa-file-alt"><option value="fas fa-users"><option value="fas fa-cog"><option value="fas fa-home"><option value="fas fa-box"><option value="fas fa-chart-line"></datalist></div>
<div class="col-md-2"><label for="registroOrden" class="form-label">Orden</label><input id="registroOrden" type="number" min="0" step="1" value="1" class="form-control" required></div>
<div class="col-md-6"><label for="registroEstado" class="form-label">Estado</label><select id="registroEstado" data-ifact-state hidden style="display:none"><option value="1">Habilitado</option><option value="0">Deshabilitado</option></select></div>
</div></div><div class="modal-footer d-flex justify-content-between"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button><button id="guardarNavegacion" type="submit" class="btn btn-primary"><i class="fa fa-check me-1"></i> Guardar</button></div></form>
</div></div></div>
<script src="../assets/js/jquery-3.6.0.min.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/feather.min.js"></script>
<script src="../assets/js/jquery.slimscroll.min.js"></script>
<script src="../assets/js/jquery.dataTables.min.js"></script>
<script src="../assets/js/dataTables.bootstrap4.min.js"></script>
<script src="../assets/js/script.js"></script>
<script src="../assets/plugins/sweetalert/sweetalert2.all.min.js"></script>
<script src="../js/funciones_header.js?v=<?=filemtime(__DIR__.'/../js/funciones_header.js')?>"></script>
<script src="../js/funciones_mantenedor_navegacion.js?v=<?= filemtime('../js/funciones_mantenedor_navegacion.js') ?>"></script>
</body></html>
