<?php
require_once __DIR__.'/conexion.php';
require_once __DIR__.'/MySQLSessionHandler.php';
require_once __DIR__.'/remember_me.php';
$pdo=(new conexion())->con();
if (session_status()===PHP_SESSION_NONE) {
    // No compartir PHPSESSID con otros sistemas del mismo servidor.
    session_name('IFACTSESSID');
    ini_set('session.gc_maxlifetime','10800');
    ini_set('session.serialize_handler','php');
    session_set_cookie_params(['lifetime'=>10800,'path'=>rutaCookieIFact(),'httponly'=>true,
        'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','samesite'=>'Lax']);
    session_set_save_handler(new MySQLSessionHandler($pdo,10800),true);
    session_start();
}
// Renovar la cookie en cada solicitud: tres horas desde la última actividad.
if (session_status()===PHP_SESSION_ACTIVE && !headers_sent()) {
    setcookie(session_name(),session_id(),['expires'=>time()+10800,'path'=>session_get_cookie_params()['path'],
        'httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','samesite'=>'Lax']);
}
if (empty($_SESSION['usu_id_ifact']) && !empty($_COOKIE['ifact_remember_me'])) {
    try { restaurarRecuerdoIFact($pdo,(string)$_COOKIE['ifact_remember_me']); }
    catch (Throwable $e) { error_log('Error al restaurar acceso recordado: '.$e->getMessage()); }
}

// Token común para todas las operaciones autenticadas que modifican datos.
// Los controladores pueden conservar sus tokens específicos; este control es
// una segunda barrera uniforme para que una web externa no pueda reutilizar la
// sesión del usuario y enviar operaciones en su nombre.
if (!empty($_SESSION['usu_id_ifact']) && empty($_SESSION['csrf_ifact'])) {
    $_SESSION['csrf_ifact'] = bin2hex(random_bytes(32));
}

if (!empty($_SESSION['usu_id_ifact']) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $csrfRecibido = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf_ifact'] ?? '');
    $csrfSesion = (string)($_SESSION['csrf_ifact'] ?? '');
    if ($csrfSesion === '' || $csrfRecibido === '' || !hash_equals($csrfSesion, $csrfRecibido)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'estado' => 'error',
            'mensaje' => 'La sesión de seguridad venció. Actualiza la página e inténtalo nuevamente.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
