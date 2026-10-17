<?php
declare(strict_types=1);
require_once __DIR__.'/../model/sesion_central.php';
require_once __DIR__.'/../model/panel_central.php';
if(!sesionCentralActiva()){header('Location: ../view/login.php');exit;}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||!validarCsrfCentral($_POST['csrf']??null)){http_response_code(403);exit('Solicitud no válida.');}
try{
 $empresaId=(int)($_POST['em_id']??0);$localId=(int)($_POST['loc_id']??0);
 $d=['codigo'=>strtoupper(trim((string)($_POST['codigo']??''))),'nombre'=>trim((string)($_POST['nombre']??'')),'direccion'=>trim((string)($_POST['direccion']??'')),'ubigeo'=>trim((string)($_POST['ubigeo']??'')),'departamento'=>trim((string)($_POST['departamento']??'')),'provincia'=>trim((string)($_POST['provincia']??'')),'distrito'=>trim((string)($_POST['distrito']??'')),'principal'=>isset($_POST['principal'])?1:0,'activo'=>isset($_POST['activo'])?1:0,'serie_factura'=>strtoupper(trim((string)($_POST['serie_factura']??'F001'))),'serie_boleta'=>strtoupper(trim((string)($_POST['serie_boleta']??'B001'))),'serie_nc_factura'=>strtoupper(trim((string)($_POST['serie_nc_factura']??'FC01'))),'serie_nc_boleta'=>strtoupper(trim((string)($_POST['serie_nc_boleta']??'BC01'))),'serie_nd_factura'=>strtoupper(trim((string)($_POST['serie_nd_factura']??'FD01'))),'serie_nd_boleta'=>strtoupper(trim((string)($_POST['serie_nd_boleta']??'BD01'))),'corr_factura'=>max(0,(int)($_POST['corr_factura']??0)),'corr_boleta'=>max(0,(int)($_POST['corr_boleta']??0)),'corr_nc_factura'=>max(0,(int)($_POST['corr_nc_factura']??0)),'corr_nc_boleta'=>max(0,(int)($_POST['corr_nc_boleta']??0)),'corr_nd_factura'=>max(0,(int)($_POST['corr_nd_factura']??0)),'corr_nd_boleta'=>max(0,(int)($_POST['corr_nd_boleta']??0))];
 if($empresaId<1||$d['nombre']===''||!preg_match('/^[A-Z0-9_-]{2,20}$/',$d['codigo']))throw new InvalidArgumentException('Completa el nombre y un código válido para el local.');
 foreach(['serie_factura','serie_nc_factura','serie_nd_factura'] as $k)if(!preg_match('/^F[A-Z0-9]{3}$/',$d[$k]))throw new InvalidArgumentException('Las series de factura deben iniciar con F y tener cuatro caracteres.');
 foreach(['serie_boleta','serie_nc_boleta','serie_nd_boleta'] as $k)if(!preg_match('/^B[A-Z0-9]{3}$/',$d[$k]))throw new InvalidArgumentException('Las series de boleta deben iniciar con B y tener cuatro caracteres.');
 foreach([['01','serie_factura'],['03','serie_boleta'],['07','serie_nc_factura'],['07','serie_nc_boleta'],['08','serie_nd_factura'],['08','serie_nd_boleta']] as [$tipo,$campo])if(PanelCentral::serieEnOtroLocal($empresaId,$localId,$tipo,$d[$campo]))throw new InvalidArgumentException('La serie '.$d[$campo].' ya pertenece a otro local de esta empresa.');
 PanelCentral::guardarLocal($localId,$empresaId,$d);$_SESSION['central_ok']=$localId?'Local actualizado correctamente.':'Local agregado correctamente.';
}catch(Throwable $e){error_log('Locales central: '.$e->getMessage());$_SESSION['central_error']=$e instanceof InvalidArgumentException?$e->getMessage():'No se pudo guardar el local. Revisa que las series no estén asignadas a otro local.';}
header('Location: ../view/empresas_central.php');exit;
