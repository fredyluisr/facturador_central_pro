<?php
function rutaCookieIFact(): string
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/'));
    $directorio = str_replace('\\', '/', dirname($script));
    // Las entradas del sistema viven en /view, /model o /controller. La cookie
    // debe pertenecer a la carpeta raíz de la instalación, sea /ifact_pro/ en
    // local, / en un dominio propio o cualquier otro subdirectorio.
    $base = preg_replace('#/(?:view|model|controller)$#i', '', rtrim($directorio, '/'));
    $base = is_string($base) ? trim($base) : '';
    return $base === '' || $base === '.' ? '/' : '/' . trim($base, '/') . '/';
}

function opcionesCookieRecuerdoIFact(int $expires): array
{
    return ['expires'=>$expires,'path'=>rutaCookieIFact(),'httponly'=>true,
        'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','samesite'=>'Lax'];
}
function cancelarRecuerdoIFact(PDO $db): void
{
    $cookie=(string)($_COOKIE['ifact_remember_me']??'');
    $selector=explode('|',$cookie,2)[0];
    if ($selector!=='') {
        $q=$db->prepare('DELETE FROM remember_tokens WHERE selector=?'); $q->execute([$selector]);
    }
    setcookie('ifact_remember_me','',opcionesCookieRecuerdoIFact(time()-3600));
    unset($_COOKIE['ifact_remember_me']);
}
function crearRecuerdoIFact(PDO $db,array $user,int $loc): void
{
    $selector=bin2hex(random_bytes(8)); $token=bin2hex(random_bytes(32));
    $expires=time()+86400*30;
    $q=$db->prepare('INSERT INTO remember_tokens(usu_id,rol_id,prf_id,loc_id,selector,token_hash,expires_at) VALUES(?,?,?,?,?,?,?)');
    $q->execute([$user['usu_id'],$user['rol_id'],$user['prf_id'],$loc,$selector,hash('sha256',$token),date('Y-m-d H:i:s',$expires)]);
    setcookie('ifact_remember_me',$selector.'|'.$token,opcionesCookieRecuerdoIFact($expires));
}
function restaurarRecuerdoIFact(PDO $db,string $cookie): bool
{
    $parts=explode('|',$cookie,2);
    if (count($parts)!==2 || !preg_match('/^[a-f0-9]{16}$/',$parts[0]) || !preg_match('/^[a-f0-9]{64}$/',$parts[1])) {
        cancelarRecuerdoIFact($db); return false;
    }
    $q=$db->prepare("SELECT rt.id,rt.token_hash,rt.expires_at,u.usu_id,u.usu_token,u.emp_id,p.prf_id,p.rol_id,
        UPPER(r.rol_nombre) AS rol,UPPER(CONCAT(e.emp_nombres,' ',e.emp_ape_paterno,' ',e.emp_ape_materno)) AS nombre_completo,
        UPPER(CONCAT(e.emp_ape_paterno,' ',e.emp_ape_materno)) AS apellidos,UPPER(e.emp_nombres) AS nombres,
        l.loc_id,l.loc_nombre FROM remember_tokens rt
        JOIN usuario u ON u.usu_id=rt.usu_id AND u.usu_estado=1
        JOIN perfil p ON p.prf_id=rt.prf_id AND p.usu_id=u.usu_id AND p.rol_id=rt.rol_id AND p.prf_estado=1
        JOIN rol r ON r.rol_id=p.rol_id JOIN empleado e ON e.emp_id=u.emp_id
        JOIN `local` l ON l.loc_id=rt.loc_id AND l.loc_estado=1
        WHERE rt.selector=? AND rt.expires_at>NOW()");
    $q->execute([$parts[0]]); $r=$q->fetch(PDO::FETCH_ASSOC);
    if (!$r || !hash_equals($r['token_hash'],hash('sha256',$parts[1]))) { cancelarRecuerdoIFact($db); return false; }
    session_regenerate_id(true);
    foreach(['usu_id','usu_token','emp_id','prf_id','rol_id','nombre_completo','apellidos','nombres','loc_id','loc_nombre'] as $key) $_SESSION[$key.'_ifact']=$r[$key];
    $_SESSION['rol_nombre_ifact']=$r['rol'];
    $_SESSION['catalogo_csrf']=bin2hex(random_bytes(32));
    // La cookie vence a los 30 días del login; no se extiende silenciosamente.
    return true;
}
