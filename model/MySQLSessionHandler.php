<?php

class MySQLSessionHandler implements SessionHandlerInterface
{
    private PDO $pdo;
    private int $ttl; // tiempo de vida en segundos
    private ?string $lockName = null;

    public function __construct(PDO $pdo, int $ttl = 10800) // 3 horas
    {
        $this->pdo = $pdo;
        $this->ttl = $ttl;
    }

    public function open(string $savePath, string $sessionName): bool
    {
        return true; // no necesitamos abrir nada
    }

    public function close(): bool
    {
        $this->releaseLock();
        return true;
    }

    private function releaseLock(): void
    {
        if ($this->lockName !== null) {
            $stmt = $this->pdo->prepare('SELECT RELEASE_LOCK(?)');
            $stmt->execute([$this->lockName]);
            $this->lockName = null;
        }
    }

    public function read(string $id): string
    {
        // Serializar lectura/escritura: dos AJAX no deben sobrescribirse.
        $this->releaseLock();
        $database = (string)$this->pdo->query('SELECT DATABASE()')->fetchColumn();
        $name = 'ifact_session_' . substr(hash('sha256', $database . ':' . $id), 0, 48);
        $lock = $this->pdo->prepare('SELECT GET_LOCK(?, 30)');
        $lock->execute([$name]);
        if ((int)$lock->fetchColumn() !== 1) {
            throw new RuntimeException('No se pudo bloquear la sesión para su lectura.');
        }
        $this->lockName = $name;
        $sql = "SELECT data 
                FROM sessions 
                WHERE id = :id 
                  AND session_status = 1
                  AND last_activity >= :time";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id'   => $id,
            ':time' => time() - $this->ttl
        ]);

        $data = $stmt->fetchColumn();
        if ($data!==false) {
            // Mantener actividad incluso si PHP no vuelve a escribir los datos.
            $touch=$this->pdo->prepare('UPDATE sessions SET last_activity=? WHERE id=? AND session_status=1');
            $touch->execute([time(),$id]);
        }
        return $data !== false ? (string)$data : '';
    }

    public function write(string $id, string $data): bool
    {

    

        //Datos de sesion no validos, no guardar en BD  -Gerson
        /*
        // Extraer valores de la sesión serializada
        $usu_id = $this->extractValue($data, 'usu_id_setlistone');
        $prf_id = $this->extractValue($data, 'prf_id_setlistone');
        $rol_id = $this->extractValue($data, 'rol_id_setlistone');
        */

        // Extractvalue con el mismo nombre valor de la funcion obtener_datos_usuario  --Gerson

        $usu_id = $this->extractValue($data, 'usu_id_ifact');   
        $prf_id = $this->extractValue($data, 'prf_id_ifact');   
        $rol_id = $this->extractValue($data, 'rol_id_ifact');   

        // Determinar el estado según si hay datos de usuario  -Gerson
        $session_status = ($usu_id && $prf_id && $rol_id) ? 1 : 0;   

        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $raw_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $user_agent = $this->parseUserAgent($raw_user_agent);

        $sql = "INSERT INTO sessions 
                    (id, data, last_activity, usu_id, prf_id, rol_id, fecha_login, ip_address, user_agent, session_status, created_at, updated_at) 
                VALUES 
                    (:id, :data, :time, :usu_id, :prf_id, :rol_id, NOW(), :ip, :agent, :session_status, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                    data = IF(
                        sessions.session_status = 0 AND sessions.logout_reason LIKE 'admin_forced%',
                        sessions.data,
                        VALUES(data)
                    ),
                    last_activity = VALUES(last_activity),
                    usu_id = VALUES(usu_id),
                    prf_id = VALUES(prf_id),
                    rol_id = VALUES(rol_id),
                    ip_address = VALUES(ip_address),
                    user_agent = VALUES(user_agent),
                    session_status = IF(
                        sessions.session_status = 0 AND sessions.logout_reason LIKE 'admin_forced%',
                        0,
                        VALUES(session_status)
                    ),
                    updated_at = NOW()";

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            ':id'     => $id,
            ':data'   => $data,
            ':time'   => time(),
            ':usu_id' => $usu_id,
            ':prf_id' => $prf_id,
            ':rol_id' => $rol_id,
            ':ip'     => $ip_address,
            ':agent'  => $user_agent,
            ':session_status' => $session_status
        ]);
    }

    public function destroy(string $id): bool
    {
        $sql = "UPDATE sessions 
                SET fecha_logout = NOW(), 
                    session_status = 0,
                    logout_reason = 'manual' 
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);

        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        $sql = "DELETE FROM sessions WHERE last_activity < :time";
        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':time' => time() - $this->ttl
        ]);

        return $stmt->rowCount(); // PHP espera int|false
    }

    // =========================
    // MÉTODOS AUXILIARES
    // =========================

    private function extractValue(string $data, string $key): ?string
    {
       /*
        if (preg_match('/' . preg_quote($key, '/') . '\|s:\d+:"([^"]+)"/', $data, $matches)) {
            return $matches[1];
        }
        return null;
       */

          
         // Intentar extraer string (s:)
    if (preg_match('/' . preg_quote($key, '/') . '\|s:\d+:"([^"]+)"/', $data, $matches)) {
        return $matches[1];
    }
    
    // Intentar extraer integer (i:)
    if (preg_match('/' . preg_quote($key, '/') . '\|i:(\d+);/', $data, $matches)) {
        return $matches[1];
    }
    
    return null;




    }

    private function parseUserAgent(?string $ua): ?string
    {
        if (!$ua) {
            return null;
        }

        $os = 'Desconocido';
        $browser = 'Desconocido';

        // Sistema operativo
        if (preg_match('/Windows NT 10\.0/i', $ua)) $os = 'Windows 10';
        elseif (preg_match('/Windows NT 11\.0/i', $ua)) $os = 'Windows 11';
        elseif (preg_match('/Windows NT 6\.3/i', $ua)) $os = 'Windows 8.1';
        elseif (preg_match('/Windows NT 6\.1/i', $ua)) $os = 'Windows 7';
        elseif (preg_match('/Mac OS X/i', $ua)) $os = 'Mac OS X';
        elseif (preg_match('/Linux/i', $ua)) $os = 'Linux';
        elseif (preg_match('/Android/i', $ua)) $os = 'Android';
        elseif (preg_match('/iPhone|iPad/i', $ua)) $os = 'iOS';

        // Navegador
        if (preg_match('/Edg\/([0-9\.]+)/i', $ua, $m)) {
            $browser = 'Edge ' . $m[1];
        } elseif (preg_match('/Chrome\/([0-9\.]+)/i', $ua, $m)) {
            $browser = 'Chrome ' . $m[1];
        } elseif (preg_match('/Firefox\/([0-9\.]+)/i', $ua, $m)) {
            $browser = 'Firefox ' . $m[1];
        } elseif (preg_match('/Version\/([0-9\.]+)/i', $ua, $m) && preg_match('/Safari/i', $ua)) {
            $browser = 'Safari ' . $m[1];
        }

        return $os . ' · ' . $browser;
    }
}
