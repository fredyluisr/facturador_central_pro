<?php
require_once __DIR__.'/../model/sesion.php';
require_once __DIR__.'/../model/dashboard.php';
header('Content-Type: application/json; charset=utf-8');
try{
    if(empty($_SESSION['rol_id_ifact'])||empty($_SESSION['usu_id_ifact'])){http_response_code(401);throw new RuntimeException('La sesión ha expirado.');}
    $loc=(int)($_SESSION['loc_id_ifact']??0);if($loc<=0)throw new InvalidArgumentException('Selecciona una tienda.');
    $inicio=(string)($_GET['inicio']??date('Y-m-01'));$fin=(string)($_GET['fin']??date('Y-m-d'));
    session_write_close();
    echo json_encode((new dashboard())->resumen($loc,$inicio,$fin),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(Throwable $e){if(http_response_code()===200)http_response_code($e instanceof InvalidArgumentException?422:500);echo json_encode(['estado'=>'error','mensaje'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}
