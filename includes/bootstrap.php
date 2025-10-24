<?php
// includes/bootstrap.php
// -------------------------------------------------------------
// Bootstrap del proyecto: configuración, errores, sesión y DI básica
// -------------------------------------------------------------
declare(strict_types=1);

// 1) Handler de errores
require __DIR__ . '/error_handler.php';

// 2) Helpers (necesarios para read_config_ini)
require __DIR__ . '/helpers.php';
require __DIR__ . '/mailer.php';

// 3) Cargar configuración (PRIORIDAD: LOCAL -> PRODUCTION -> EXAMPLE)
$config_files = [
    __DIR__ . '/../config/app.local.ini',       // primero local
    __DIR__ . '/../config/app.production.ini',  // luego producción
    __DIR__ . '/../config/app.example.ini',     // fallback
];
$app_config = read_config_ini($config_files);

// 4) Exponer config a helpers (para base_url/asset_url)
$GLOBALS['app_config'] = $app_config;

// 5) Constantes de rutas (snake_case)
define('APP_ENV',           $app_config['app_env'] ?? 'local');
define('BASE_PATH',         rtrim(($app_config['base_path'] ?? dirname(__DIR__)), '/\\'));
define('PUBLIC_PATH',       rtrim(($app_config['public_path'] ?? BASE_PATH . '/public'), '/\\'));
define('STORAGE_PATH',      rtrim(($app_config['storage_path'] ?? BASE_PATH . '/storage'), '/\\'));
define('CERTIFICATES_PATH', rtrim(($app_config['certificates_path'] ?? STORAGE_PATH . '/certificates'), '/\\'));
define('FILE_TRANSFER_PATH',rtrim(($app_config['file_transfer_path'] ?? STORAGE_PATH . '/file-transfer'), '/\\'));
define('UPLOADS_PATH',      rtrim(($app_config['uploads_path'] ?? STORAGE_PATH . '/uploads'), '/\\'));
define('LOGS_PATH',         rtrim(($app_config['logs_path'] ?? STORAGE_PATH . '/logs'), '/\\'));
define('TEMP_PATH',         rtrim(($app_config['temp_path'] ?? STORAGE_PATH . '/temp'), '/\\'));

// 6) Zona horaria y multibyte
date_default_timezone_set($app_config['timezone'] ?? 'America/New_York');
mb_internal_encoding('UTF-8');

// 7) Header de seguridad mínimo
header('X-Content-Type-Options: nosniff');

// 8) Sesión segura
session_name('app_session');
session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Lax',
]);

// 9) PDO
require __DIR__ . '/db.php';
$pdo = get_pdo($app_config);

// 10) Cargar capas (Repos/Services/Presenters)
require_once __DIR__ . '/../app/Repositories/user_repository.php';
require_once __DIR__ . '/../app/Repositories/session_repository.php';
require_once __DIR__ . '/../app/Repositories/audit_log_repository.php';
require_once __DIR__ . '/../app/Repositories/contractor_repository.php';
require_once __DIR__ . '/../app/Repositories/contractor_staging_repository.php';

require_once __DIR__ . '/../app/Services/auth_service.php';
require_once __DIR__ . '/../app/Services/sign_up_service.php';

require_once __DIR__ . '/../app/Presenters/sign_in_presenter.php';
require_once __DIR__ . '/../app/Presenters/sign_up_presenter.php';
