<?php
declare(strict_types=1);
final class ConexionCentral {
    private static ?array $env=null;
    private static function env(): array {
        if(self::$env!==null)return self::$env;
        $file=dirname(__DIR__).'/.env'; $values=is_file($file)?parse_ini_file($file,false,INI_SCANNER_RAW):false;
        if(!is_array($values))throw new RuntimeException('La configuración del sistema no está disponible.');
        return self::$env=$values;
    }
    public static function conectar(): PDO {
        $e=self::env(); $host=trim((string)($e['FACTURACION_CENTRAL_DB_HOST']??'')); $db=trim((string)($e['FACTURACION_CENTRAL_DB_DATABASE']??'')); $user=(string)($e['FACTURACION_CENTRAL_DB_USERNAME']??''); $pass=(string)($e['FACTURACION_CENTRAL_DB_PASSWORD']??''); $port=trim((string)($e['FACTURACION_CENTRAL_DB_PORT']??'3306'));
        if($host===''||$db===''||$user==='')throw new RuntimeException('La conexión central aún no está configurada.');
        return new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>8]);
    }
}
