<?php

// Toda fecha creada o comparada por la aplicación se interpreta en la zona
// horaria de Perú. Debe coincidir con MySQL y con el servidor de facturación.
date_default_timezone_set('America/Lima');

class conexion
{
    private static $env = null;

    private static function cargarEnv()
    {
        if (self::$env !== null) {
            return self::$env;
        }

        $envPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
        if (!is_file($envPath)) {
            throw new RuntimeException('Falta el archivo .env del proyecto');
        }

        $valores = parse_ini_file($envPath, false, INI_SCANNER_RAW);
        if ($valores === false) {
            throw new RuntimeException('No se pudo leer el archivo .env del proyecto');
        }

        self::$env = $valores;
        return self::$env;
    }

    private static function env($clave, $predeterminado = null)
    {
        $valorSistema = getenv($clave);
        if ($valorSistema !== false) {
            return $valorSistema;
        }

        $env = self::cargarEnv();
        if (array_key_exists($clave, $env)) {
            return $env[$clave];
        }

        if ($predeterminado !== null) {
            return $predeterminado;
        }

        throw new RuntimeException("Falta configurar {$clave} en .env");
    }

    public static function config($clave, $predeterminado = null)
    {
        return self::env($clave, $predeterminado);
    }

    public function con()
    {
        try {
            $dsn = 'mysql:host=' . self::env('DB_HOST')
                . ';port=' . self::env('DB_PORT', '3306')
                . ';dbname=' . self::env('DB_DATABASE')
                . ';charset=' . self::env('DB_CHARSET', 'utf8mb4');

            return new PDO($dsn, self::env('DB_USERNAME'), self::env('DB_PASSWORD', ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // El sistema heredado reutiliza parámetros nombrados en varias
                // sentencias; PDO MySQL requiere emulación para ese patrón.
                PDO::ATTR_EMULATE_PREPARES => true
            ]);
        } catch (PDOException $e) {
            die('Error de conexión: ' . $e->getMessage());
        }
    }
}
