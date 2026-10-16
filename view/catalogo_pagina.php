<?php
if (!isset($catalogoTipo,$catalogoTitulo)) { http_response_code(404); exit; }
require_once __DIR__ . '/../model/catalogo_acceso.php';
if (!catalogoSesionActiva()) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/../model/var_global.php';
if (!catalogoPuedeAcceder(basename($_SERVER['SCRIPT_NAME']))) {
    http_response_code(403); exit('No tiene acceso a este catálogo.');
}
$csrf=htmlspecialchars($_SESSION['catalogo_csrf'],ENT_QUOTES,'UTF-8');
$e=fn($s)=>htmlspecialchars($s,ENT_QUOTES,'UTF-8');
$producto=$catalogoTipo==='producto';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($catalogoTitulo) ?> · iFact</title>
    <link rel="shortcut icon" href="<?= $e($icono) ?>">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="../assets/plugins/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="../assets/plugins/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../css/header.css?v=<?= filemtime(__DIR__.'/../css/header.css') ?>">
    <link rel="stylesheet" href="../css/modal_form.css">
    <link rel="stylesheet" href="../css/pos_ui.css?v=20260917">
    <link rel="stylesheet" href="../css/administration_maintenance.css">
    <link rel="stylesheet" href="../css/catalogo.css?v=<?= filemtime(__DIR__.'/../css/catalogo.css') ?>">
</head>
<body class="pos-page administration-maintenance catalogo-page" data-catalogo="<?= $e($catalogoTipo) ?>" data-csrf="<?= $csrf ?>">
<?php include __DIR__ . '/header.php'; ?>
<div class="page-wrapper"><div class="content">
    <div class="page-header">
        <div class="page-title"><h4><?= $e($catalogoTitulo) ?></h4>
            <div class="page-breadcrumb"><a href="index.php"><i aria-hidden="true" class="fa fa-home"></i> Inicio</a>
                <span class="breadcrumb-separator"><i aria-hidden="true" class="fa fa-angle-right"></i></span><span>Catálogo</span>
                <span class="breadcrumb-separator"><i aria-hidden="true" class="fa fa-angle-right"></i></span><span><?= $e($catalogoTitulo) ?></span></div>
        </div>
        <div class="page-btn"><button class="btn btn-added" id="catalogoNuevo"><i aria-hidden="true" class="fa fa-plus me-1"></i> Nuevo</button></div>
    </div>
    <?php if ($catalogoTipo==='atributo'): ?>
    <div class="catalogo-orientacion" id="atributosOrientacion"><span class="catalogo-orientacion-icono"><i class="fa fa-layer-group" aria-hidden="true"></i></span><div><strong id="atributosAyudaTitulo">Define las opciones de tus productos</strong><p id="atributosAyudaTexto">Crea atributos como Color o Talla. Pulsa Valores para registrar sus opciones, como Azul o 38; después combínalas en Productos.</p></div></div>
    <?php elseif ($producto): ?>
    <div class="catalogo-orientacion"><span class="catalogo-orientacion-icono"><i class="fa fa-boxes" aria-hidden="true"></i></span><div><strong>Un modelo, todas sus opciones</strong><p>Organiza tus productos por categoría y combina tallas, colores u otros atributos. Cada opción tiene su propio código y precio.</p></div></div>
    <?php endif; ?>
    <div class="card" id="catalogoLista"><div class="card-body"><div class="table-responsive">
        <table class="table" id="tablaCatalogo" style="width:100%"><thead><tr>
            <?php foreach ($producto ? ['Producto','Categoría','Unidad','Precio base','Variantes','Estado','Acciones'] : ($catalogoTipo==='unidad' ? ['Nombre','Abreviatura','Código SUNAT','Precisión de cantidad','Estado','Acciones'] : ['Nombre','Estado','Acciones']) as $label): ?>
            <th><?= $e($label) ?></th>
            <?php endforeach; ?>
        </tr></thead><tbody></tbody></table>
    </div></div></div>
    <?php if ($catalogoTipo==='atributo'): ?>
    <div class="card" id="catalogoValores" hidden><div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <div class="catalogo-valores-cabecera"><button type="button" class="btn catalogo-volver" id="volverAtributos"><i aria-hidden="true" class="fa fa-arrow-left"></i> Volver a atributos</button> <strong id="valoresTitulo"></strong></div>
            <button type="button" class="btn catalogo-nuevo-valor" id="valorNuevo"><i aria-hidden="true" class="fa fa-plus"></i> Nuevo valor</button>
        </div>
        <div class="table-responsive"><table class="table" id="tablaValores" style="width:100%"><thead><tr><th>Valor</th><th>Color</th><th>Estado</th><th>Acciones</th></tr></thead><tbody></tbody></table></div>
    </div></div>
    <?php endif; ?>
</div></div>

<div class="modal fade modal-form" id="reg_edi" tabindex="-1" aria-labelledby="nombre_opcion" aria-hidden="true">
    <div class="modal-dialog <?= $producto ? 'modal-xl' : 'modal-lg' ?>"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="nombre_opcion"></h5><button type="button" class="close" data-bs-dismiss="modal" aria-label="Cerrar"><span>×</span></button></div>
        <div class="modal-body"><form id="catalogoForm" autocomplete="off">
        <?php if (!$producto): ?>
            <div class="seccion-comprobante"><div class="seccion-label-minimal"><i aria-hidden="true" class="fa fa-edit"></i> Datos del registro</div>
                <div class="row g-3" id="maestroCampos"></div>
            </div>
        <?php else: ?>
            <div class="seccion-comprobante producto-datos-foto"><div class="producto-datos-campos"><div class="seccion-label-minimal"><i aria-hidden="true" class="fa fa-box"></i> Datos del producto</div>
            <div class="row g-3">
                <div class="col-md-8"><label for="pro_nombre" class="form-label-custom">Nombre <span class="text-danger">*</span></label><input class="form-control" id="pro_nombre" maxlength="150" required></div>
                <div class="col-md-4"><label for="cat_id" class="form-label-custom">Categoría <span class="text-danger">*</span></label><select class="form-select" id="cat_id" required></select></div>
                <div class="col-md-4"><label for="und_med" class="form-label-custom">Unidad de venta <span class="text-danger">*</span></label><select class="form-select" id="und_med" required></select></div>
                <div class="col-md-4"><label for="pro_prec_venta" class="form-label-custom">Precio base <span class="text-danger">*</span></label><input type="number" class="form-control" id="pro_prec_venta" min="0" max="9999999999.99" step="0.01" required value="0.00"></div>
                <div class="col-md-4"><label class="form-label-custom" for="pro_estado">Estado</label><select id="pro_estado" data-ifact-state hidden><option value="1">Habilitado</option><option value="0">Deshabilitado</option></select></div>
            </div></div><div class="producto-foto-panel"><label class="form-label-custom">Foto del producto</label><button type="button" id="verFotoProducto" class="producto-foto-preview" aria-label="Ver foto del producto"><span><i class="far fa-image"></i><small>Sin foto</small></span></button><div class="producto-foto-acciones"><button type="button" class="btn" id="seleccionarFotoProducto"><i class="fa fa-camera me-1"></i> Seleccionar</button><button type="button" class="btn" id="quitarFotoProducto" hidden><i class="fa fa-trash-alt me-1"></i> Quitar</button></div><input type="file" id="archivoFotoProducto" accept="image/jpeg,image/png,image/webp,image/gif" hidden><small>JPG, PNG, WebP o GIF · máximo 10 MB; se optimiza automáticamente</small></div></div>
            <div class="seccion-comprobante"><button type="button" class="catalogo-seccion-toggle collapsed" data-bs-toggle="collapse" data-bs-target="#contenidoAtributos" aria-expanded="false" aria-controls="contenidoAtributos"><span><i aria-hidden="true" class="fa fa-layer-group"></i> Tallas y combinaciones</span><small>Despliega para agregar o modificar tallas</small><i class="fa fa-chevron-down catalogo-toggle-icon" aria-hidden="true"></i></button><div class="collapse" id="contenidoAtributos">
                <p class="catalogo-ayuda">Selecciona hasta tres atributos y sus valores. Ejemplo: dos colores y tres tallas generan seis variantes. Para un producto sin opciones, usa «Sin atributos». Las cantidades de stock se registrarán por separado.</p>
                <div class="row g-3" id="productoAtributos"></div>
                <p class="catalogo-atributos-ayuda">¿Faltan tallas o colores? <a href="list_variantes.php" target="_blank" rel="noopener">Administrar atributos y valores <i class="fa fa-external-link-alt" aria-hidden="true"></i></a>. En ese mantenedor pulsa <strong>Valores</strong> para registrar las opciones. Al volver, <button type="button" class="catalogo-actualizar" id="actualizarAtributos">Actualizar atributos</button>.</p>
                <div class="d-flex flex-wrap gap-2 mt-3"><button class="btn btn-outline-dark" type="button" id="agregarAtributo"><i aria-hidden="true" class="fa fa-plus"></i> Atributo</button>
                    <button class="btn btn-dark" type="button" id="generarVariantes">Generar combinaciones</button>
                    <button class="btn btn-light" type="button" id="varianteSimple">Sin atributos (variante simple)</button></div>
            </div></div>
            <div class="seccion-comprobante"><div class="seccion-label-minimal"><i aria-hidden="true" class="fa fa-tags"></i> Variantes vendibles</div>
                <p class="catalogo-ayuda">Cada combinación tiene su SKU único. El precio base se copia al crear nuevas variantes; las existentes conservan su precio. Deshabilita variantes antiguas para conservar su identidad.</p>
                <div class="table-responsive"><table class="table catalogo-variantes"><thead><tr><th>Combinación</th><th>SKU *</th><th>Código de barras</th><th>Precio *</th><th>Estado</th><th>Acciones</th></tr></thead><tbody id="productoVariantes"></tbody></table></div>
                <small id="variantesResumen" class="text-muted"></small>
            </div>
        <?php endif; ?>
        </form></div>
        <div class="modal-footer-custom"><button class="btn btn-secondary" type="button" data-bs-dismiss="modal"><i aria-hidden="true" class="fa fa-times me-1"></i> Cerrar</button>
            <button class="btn" type="submit" form="catalogoForm" id="btn_guardar"><i aria-hidden="true" class="fa fa-check me-1"></i> Guardar</button></div>
    </div></div>
</div>
<?php if ($producto): ?>
<div class="modal fade" id="modalImagenVariante" tabindex="-1" aria-labelledby="tituloImagenVariante" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content catalogo-imagen-modal">
        <div class="modal-header"><h5 class="modal-title" id="tituloImagenVariante">Imagen de la variante</h5><button type="button" class="close" data-bs-dismiss="modal" aria-label="Cerrar"><span>×</span></button></div>
        <div class="modal-body"><img id="imagenVarianteAmpliada" alt="Vista ampliada de la variante"></div>
    </div></div>
</div>
<?php endif; ?>
<script src="../assets/js/jquery-3.6.0.min.js"></script>
<script src="../assets/js/feather.min.js"></script>
<script src="../assets/js/jquery.slimscroll.min.js"></script>
<script src="../assets/js/jquery.dataTables.min.js"></script>
<script src="../assets/js/dataTables.bootstrap4.min.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
<script src="../assets/plugins/sweetalert/sweetalert2.all.min.js"></script>
<script src="../js/funciones_header.js?v=<?= filemtime(__DIR__.'/../js/funciones_header.js') ?>"></script>
<script src="../js/funciones_catalogo.js?v=<?= filemtime(__DIR__.'/../js/funciones_catalogo.js') ?>"></script>
<?php if ($producto): ?><script src="../js/funciones_catalogo_productos.js?v=<?= filemtime(__DIR__.'/../js/funciones_catalogo_productos.js') ?>"></script><?php endif; ?>
</body></html>
