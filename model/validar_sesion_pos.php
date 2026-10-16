<?php

require_once __DIR__ . '/conexion.php';

/**
 * Exige una sesión completa para las pantallas del punto de venta.
 * Una sesión antigua puede conservar el rol sin tener una tienda válida.
 */
function validarSesionPos(): void
{
    $rolId = (int)($_SESSION['rol_id_ifact'] ?? 0);
    $locId = (int)($_SESSION['loc_id_ifact'] ?? 0);
    $localValido = false;

    if ($rolId > 0 && $locId > 0) {
        try {
            $pdo = (new conexion())->con();
            $stmt = $pdo->prepare(
                'SELECT loc_id,loc_nombre FROM `local` WHERE loc_id=:loc_id AND loc_estado=1'
            );
            $stmt->execute([':loc_id' => $locId]);
            $local = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($local) {
                $_SESSION['loc_nombre_ifact'] = $local['loc_nombre'];
                $localValido = true;
            }
        } catch (Throwable $e) {
            error_log('validarSesionPos: ' . $e->getMessage());
            // Un fallo de consulta no equivale a una sesión inválida.
            if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
            http_response_code(503);
            exit('No se pudo verificar la tienda en este momento. Vuelve a intentar.');
        }
    }

    if ($rolId > 0 && $localValido) {
        // La vista solo necesita leer la sesión desde este punto. Liberar el
        // bloqueo permite que otras solicitudes del mismo usuario continúen.
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        return;
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();

    header('Location: ../view/login.php?sesion_pos=invalida');
    exit;
}

