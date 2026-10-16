<?php
require_once __DIR__.'/../model/sesion.php';
require_once __DIR__.'/../model/cotizacion.php';
require_once __DIR__.'/../model/cotizacion_pdf.php';
$loc=(int)($_SESSION['loc_id_ifact']??0);$usu=(int)($_SESSION['usu_id_ifact']??0);
if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
if(!$loc||!$usu){http_response_code(401);exit('Inicia sesión nuevamente.');}
try {
    $r=(new cotizacion())->obtener((int)($_GET['cot_id']??0),$loc);
    $pdf=cotizacion_pdf::generar($r);$modo=($_GET['modo']??'inline')==='attachment'?'attachment':'inline';
    header('Content-Type: application/pdf');header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');
    header('Content-Disposition: '.$modo.'; filename="'.preg_replace('/[^A-Za-z0-9_-]/','',$r['cotizacion']['cot_numero']).'.pdf"');header('Content-Length: '.strlen($pdf));echo $pdf;
}catch(InvalidArgumentException $e){http_response_code(404);echo 'Cotización no encontrada.';}
catch(Throwable $e){error_log('cotizacion_pdf: '.$e->getMessage());http_response_code(500);echo 'No se pudo generar el PDF de la cotización.';}
