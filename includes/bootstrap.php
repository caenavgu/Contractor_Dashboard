<?php
// includes/bootstrap.php
// -------------------------------------------------------------
// Bootstrap global de la aplicación:
// - Constantes de paths
// - Carga de helpers y middleware
// - Configuración (INI) y entorno
// - Sesiones (compatibles con XAMPP/Windows)
// - Manejo de errores/log (en local: muestra en pantalla + log)
// - Conexión PDO
// - Carga de utilidades compartidas (mailer, db, etc.)
// -------------------------------------------------------------
declare(strict_types=1);

/* ---------- Constantes de paths (UNA SOLA: BASE_PATH) ---------- */
if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__ . '/..')); // raíz del proyecto
}

/* ---------- Sesión ---------- */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* ---------- Zona horaria por defecto (ajústala si necesitas) ---------- */
date_default_timezone_set('America/New_York');

/* ---------- Carga de helpers y utilidades ---------- */
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/error_handler.php'; // asume que define manejo de errores/exception
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/middleware.php';

/* ---------- Cargar configuración (desde /config/*.ini) ---------- */
$config = read_config_ini();  // viene de helpers.php (ya lo agregamos antes)

/* ---------- Validación mínima de la sección DB ---------- */
if (!isset($config['db']) || !is_array($config['db'])) {
    throw new RuntimeException('Config: sección [db] no encontrada.');
}
$required_keys = ['host','port','name','user','pass','charset'];
foreach ($required_keys as $k) {
    if (!array_key_exists($k, $config['db'])) {
        throw new RuntimeException("Config: clave db.{$k} faltante.");
    }
}

/* ---------- Inicializar PDO ---------- */
$pdo = get_pdo($config['db']); // queda disponible para index.php y presenters