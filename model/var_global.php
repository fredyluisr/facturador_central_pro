<?php

	//Variables Globales
	$pagina_redireccion = "../view/validar_login.php";
	$pagina_no_encontrada = "../view/404.php";

	$pagina_actual = basename($_SERVER['PHP_SELF'] ?? '');
	$titulos_paginas = array(
		'index.php' => 'Inicio',
		'dashboard.php' => 'Dashboard',
		'list_rols.php' => 'Roles',
		'list_users.php' => 'Usuarios',
		'list_empleados.php' => 'Empleados',
		'list_cargos.php' => 'Cargo',
		'list_entidad.php' => 'Entidades',
		'list_cuenta_bancaria.php' => 'Cuentas bancarias',
		'list_forma_pago.php' => 'Formas de pago',
		'list_pages.php' => 'Páginas',
		'list_permissions.php' => 'Permisos',
		'list_acceso.php' => 'Accesos',
		'list_menu.php' => 'Menús',
		'list_menus.php' => 'Menús',
		'list_pagina.php' => 'Páginas',
		'list_paginas.php' => 'Páginas',
		'list_cotizaciones.php' => 'Cotizaciones',
		'list_venta.php' => 'Ventas',
		'list_reporte_movimientos.php' => 'Reporte de movimientos',
		'form_venta_pro.php' => 'Registrar venta',
		'list_historial_venta.php' => 'Historial de ventas',
		'list_stock_pos.php' => 'Stock',
		'list_ingreso_stock.php' => 'Ingreso a stock',
		'configuracion.php' => 'Configuración',
	);
	$nombre_pagina = $titulos_paginas[$pagina_actual] ?? '';
	if ($nombre_pagina === '') {
		$nombre_archivo = pathinfo($pagina_actual, PATHINFO_FILENAME);
		$nombre_archivo = preg_replace('/^(list|form)_/i', '', $nombre_archivo);
		$nombre_pagina = ucwords(str_replace('_', ' ', $nombre_archivo ?: 'Punto de venta'));
	}
	$titulo = $nombre_pagina . ' · iFact';
	$autor = "Fredy Luis Ruiz";
	$descripcion = "Desarrollo de Software | Intel Soft";
	$icono = "../assets/img/ifact.ico?v=2026091601";

	$direccion_img_usuarios = "../img/usuarios/";

	// // Extensiones permitidas
	// $extensiones_permitidas_todos = array('doc', 'docx', 'pdf', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png', 'mp3', 'mp4', 'm4a', 'amr');
	// $extensiones_permitidas_archivos = array('doc','docx','pdf','ppt','pptx','xls','xlsx','txt');
	// $extensiones_permitidas_imagenes = array('jpg','jpeg','png');
	// $extensiones_permitidas_audios = array('mp3','mp4','m4a', 'amr');

	// $extensiones_permitidas_excel = array('xls','xlsx');

?>
