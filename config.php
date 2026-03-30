<?php
// config.php - Configuración central de CyberPlan

define('DB_HOST', 'localhost');
define('DB_NAME', 'cyberplan');
define('DB_USER', 'cyberplan_usr');
define('DB_PASS', 'ikm169uhn');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'CyberPlan');
define('APP_VERSION', '1.0.0');
define('APP_COMPANY', 'AUNOR - Aleatica');

// Colores Aleatica brand
define('BRAND_GREEN',  '#72BF44');
define('BRAND_ORANGE', '#F99B1C');
define('BRAND_BLUE',   '#00BBE7');

// Meses en español
define('MESES', [
    1=>'Ene', 2=>'Feb', 3=>'Mar', 4=>'Abr',
    5=>'May', 6=>'Jun', 7=>'Jul', 8=>'Ago',
    9=>'Set', 10=>'Oct', 11=>'Nov', 12=>'Dic'
]);

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}

function jsonResponse(mixed $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
