<?php
require_once __DIR__ . '/../model/sesion.php';
	require_once __DIR__ . '/../model/configuracion_sistema.php';
	require_once('../model/facturacion_central_pos.php');
	require_once("../model/var_global.php");

	$obj = new facturacion_central_pos();
	$opcion = isset($_REQUEST['task']) ? (int)$_REQUEST['task'] : 0;
	if (empty($_SESSION['rol_id_ifact'])) {
		http_response_code(401);
		header('Content-Type: application/json');
		echo json_encode(['estado' => 'error', 'mensaje' => 'Sesión no válida']);
		exit;
	}
	if (in_array($opcion, [4, 5, 6, 8, 9, 13, 14], true) && !configuracion_sistema::facturacionElectronicaHabilitada((new conexion())->con())) {
		http_response_code(409);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(['estado'=>'error','success'=>false,'mensaje'=>'La facturación electrónica está desactivada en la configuración del sistema.'], JSON_UNESCAPED_UNICODE);
		exit;
	}
	// Las llamadas a la central pueden tardar hasta el timeout configurado.
	// No deben bloquear mientras tanto la navegación del mismo usuario.
	if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

	switch($opcion)
	{
		case 1: // Listar

			break;


		case 2: // Cargar datos
			
			break;


		case 3: // editar

			break;


		case 4: // registrar

			$res = $obj -> registrar();
			echo $res;

			break;


		case 5: // registrar no declarados

			$res = $obj -> registrar_no_declarados();
			echo $res;

			break;


		case 6: // Emitir comprobante desde venta POS

			// La emisión debe continuar aunque el usuario cierre el visor y el
			// formulario se recargue para iniciar la siguiente venta.
			ignore_user_abort(true);
			header('Content-Type: application/json');
			$res = $obj->registrar_desde_venta();
			echo json_encode($res);
			break;

		case 7: // Verificar comprobante en SUNAT desde venta POS

			header('Content-Type: application/json');
			$res = $obj->verificar_sunat_venta();
			echo json_encode($res);
			break;

		case 8: // Emitir nota de credito desde venta POS

			header('Content-Type: application/json');
			$res = $obj->emitir_nota_venta('07');
			echo json_encode($res);
			break;

		case 9: // Emitir nota de debito desde venta POS

			header('Content-Type: application/json');
			$res = $obj->emitir_nota_venta('08');
			echo json_encode($res);
			break;

		case 10: // Listar notas emitidas de una venta POS

			header('Content-Type: application/json');
			$res = $obj->listar_notas_venta();
			echo json_encode($res);
			break;

		case 11: // Ver o descargar el PDF generado por el servicio central
			$archivo = $obj->obtener_archivo_documento_emitido();
			if (empty($archivo['success'])) {
				http_response_code((int)($archivo['http'] ?? 500));
				header('Content-Type: text/plain; charset=utf-8');
				echo $archivo['mensaje'] ?? 'Archivo no disponible';
				break;
			}
			header('Content-Type: ' . $archivo['mime']);
			header('Content-Length: ' . strlen($archivo['contenido']));
			header('Content-Disposition: ' . $archivo['modo'] . '; filename="' . $archivo['nombre'] . '"');
			header('X-Content-Type-Options: nosniff');
			echo $archivo['contenido'];
			break;

		case 12: // Trazabilidad de la última respuesta SUNAT
			header('Content-Type: application/json');
			echo json_encode($obj->obtener_trazabilidad_venta());
			break;

		case 13: // Procesar un reintento automático pendiente
			header('Content-Type: application/json');
			echo json_encode($obj->procesar_reintento_automatico());
			break;

		case 14: // Reservar serie/correlativo sin esperar firma ni respuesta SUNAT
			ignore_user_abort(true);
			header('Content-Type: application/json');
			$_REQUEST['solo_reservar'] = 1;
			echo json_encode($obj->registrar_desde_venta());
			break;


	}

?>
