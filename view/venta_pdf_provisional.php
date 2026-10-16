<?php
require_once __DIR__.'/../model/sesion.php';
require_once __DIR__.'/../model/venta_pdf_provisional.php';
$loc=(int)($_SESSION['loc_id_ifact']??0); $usu=(int)($_SESSION['usu_id_ifact']??0);
if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
if(!$loc||!$usu){http_response_code(401);exit('Inicia sesión nuevamente.');}
try{
    $venId=(int)($_GET['ven_id']??0); $forzarNota=($_GET['tipo']??'')==='nota';
    $ruta=venta_pdf_provisional::obtenerPdfLocal($venId,$loc,$forzarNota); $pdf=file_get_contents($ruta);
    if($pdf===false||strncmp($pdf,'%PDF-',5)!==0)throw new RuntimeException('La representación guardada no es un PDF válido.');
    $modo=($_GET['modo']??'inline')==='attachment'?'attachment':'inline';
    header('Content-Type: application/pdf'); header('X-Content-Type-Options: nosniff'); header('Cache-Control: private, no-store');
    header('Content-Disposition: '.$modo.'; filename="documento-venta-'.$venId.'.pdf"'); header('Content-Length: '.strlen($pdf)); echo $pdf;
}catch(InvalidArgumentException $e){http_response_code(404);echo 'Venta no encontrada.';}
catch(Throwable $e){error_log('venta_pdf_provisional: '.$e->getMessage());http_response_code(500);header('Content-Type: text/plain; charset=utf-8');echo 'No se pudo generar la representación impresa.';}
