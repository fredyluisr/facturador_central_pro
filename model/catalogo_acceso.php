<?php
require_once __DIR__ . '/sesion.php';
require_once __DIR__ . '/conexion.php';
function catalogoSesionActiva(): bool
{
    return (int)($_SESSION['usu_id_ifact']??0)>0
        && (int)($_SESSION['rol_id_ifact']??0)>0;
}
function catalogoPuedeAcceder(string $ruta): bool
{
    $rol=(int)($_SESSION['rol_id_ifact']??0);
    if (!catalogoSesionActiva()) return false;
    if ($rol===1) return true;
    $s=(new conexion())->con()->prepare('SELECT COUNT(*) FROM rol_pagina rp JOIN pagina p ON p.pag_id=rp.pag_id JOIN rol_menu rm ON rm.rol_id=rp.rol_id AND rm.men_id=p.men_id JOIN menu m ON m.men_id=p.men_id WHERE rp.rol_id=? AND p.pag_ruta=? AND p.pag_estado=1 AND m.men_estado=1');
    $s->execute([$rol,$ruta]); return (bool)$s->fetchColumn();
}
if (empty($_SESSION['catalogo_csrf'])) $_SESSION['catalogo_csrf']=bin2hex(random_bytes(32));
