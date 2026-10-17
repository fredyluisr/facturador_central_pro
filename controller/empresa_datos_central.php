<?php
declare(strict_types=1);
require_once __DIR__.'/../model/sesion_central.php';
require_once __DIR__.'/../model/panel_central.php';
if(!sesionCentralActiva()){header('Location: ../view/login.php');exit;}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||!validarCsrfCentral($_POST['csrf']??null)){http_response_code(403);exit('Solicitud no válida.');}
try{
 $id=(int)($_POST['id']??0);$d=['ruc'=>trim((string)($_POST['ruc']??'')),'razon_social'=>trim((string)($_POST['razon_social']??'')),'nombre_comercial'=>trim((string)($_POST['nombre_comercial']??'')),'ubigeo'=>trim((string)($_POST['ubigeo']??'')),'direccion'=>trim((string)($_POST['direccion']??'')),'departamento'=>trim((string)($_POST['departamento']??'')),'provincia'=>trim((string)($_POST['provincia']??'')),'distrito'=>trim((string)($_POST['distrito']??'')),'afectacion'=>(string)($_POST['afectacion']??'10'),'igv'=>(float)($_POST['igv']??18),'qpse_user'=>trim((string)($_POST['qpse_user']??'')),'qpse_pass'=>(string)($_POST['qpse_pass']??''),'qpse_modo'=>($_POST['qpse_modo']??'beta')==='produccion'?'produccion':'beta','sunat_client_id'=>trim((string)($_POST['sunat_client_id']??'')),'sunat_client_secret'=>(string)($_POST['sunat_client_secret']??''),'sunat_sol_usuario'=>trim((string)($_POST['sunat_sol_usuario']??'')),'sunat_sol_clave'=>(string)($_POST['sunat_sol_clave']??'')];
 if($id<1||!preg_match('/^\d{11}$/',$d['ruc']))throw new InvalidArgumentException('Empresa o RUC no válido.');
 if($d['razon_social']===''||$d['qpse_user']==='')throw new InvalidArgumentException('Completa razón social y usuario QPSE.');
 if(PanelCentral::existeRucOtro($d['ruc'],$id))throw new InvalidArgumentException('Ya existe otra empresa con ese RUC.');
 PanelCentral::actualizarDatosEmpresa($id,$d);$_SESSION['central_ok']='Empresa actualizada correctamente.';
}catch(Throwable $e){error_log('Datos empresa central: '.$e->getMessage());$_SESSION['central_error']=$e instanceof InvalidArgumentException?$e->getMessage():'No se pudo actualizar la empresa.';}
header('Location: ../view/empresas_central.php');exit;
