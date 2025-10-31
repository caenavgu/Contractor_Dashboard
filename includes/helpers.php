<?php
// includes/helpers.php
// -------------------------------------------------------------
// Utilidades generales de la app (helpers).
// - Detección robusta del base path público (XAMPP/Apache)
// - Generación de URLs internas sin duplicar prefijos
// - URL de assets (asset_url)
// - Redirecciones seguras
// - Sanitización de salida
// - Lectura de INI de configuración
// - CSRF helpers
// -------------------------------------------------------------
declare(strict_types=1);

/* ============================================================
   🔹 CONFIGURACIÓN DE BASE Y URLS
   ============================================================ */

/**
 * Devuelve la URL base absoluta del proyecto (según entorno actual).
 * Ejemplo: http://localhost/contractor.everwell-ac.com
 */
function base_url(string $path = ''): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base   = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    $url    = "{$scheme}://{$host}{$base}";
    return rtrim($url, '/') . $path;
}

/**
 * Genera una URL interna de ruta, normalizada con prefijo /public
 * Ejemplo: route_url('/sign-in') → /contractor.everwell-ac.com/public/sign-in
 */
function route_url(string $path): string
{
    $path = '/' . ltrim($path, '/');
    $base = $_SERVER['SCRIPT_NAME'] ?? '';
    $root = str_replace('/index.php', '', $base);
    return rtrim($root, '/') . $path;
}

/**
 * Redirige de forma segura a otra ruta interna.
 */
function redirect_to(string $path): void
{
    $target = route_url($path);
    header("Location: {$target}", true, 302);
    exit;
}

/**
 * Limpia una cadena para salida HTML segura.
 */
function sanitize_string(?string $str): string
{
    return htmlspecialchars((string)$str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ============================================================
   🔹 CSRF PROTECTION
   ============================================================ */

/**
 * Genera y mantiene un token CSRF en la sesión si no existe.
 */
function ensure_csrf_token(): void
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/**
 * Devuelve el token CSRF actual.
 */
function get_csrf_token(): string
{
    ensure_csrf_token();
    return (string)$_SESSION['csrf_token'];
}

/**
 * Valida un token CSRF recibido desde un formulario.
 */
function validate_csrf_token(?string $token): bool
{
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Requiere un token CSRF válido en POST, de lo contrario aborta.
 */
function require_post_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo '<h1>405 Method Not Allowed</h1><p>POST required.</p>';
        exit;
    }

    $token = $_POST['_csrf'] ?? '';
    if (!validate_csrf_token($token)) {
        http_response_code(400);
        echo '<h1>400 Bad Request</h1><p>Invalid CSRF token.</p>';
        exit;
    }
}

/* ============================================================
   🔹 ASSETS Y RUTAS PÚBLICAS
   ============================================================ */

/**
 * Devuelve la URL completa de un archivo dentro de /public/assets/
 * Ejemplo: asset_url('img/logo.png')
 */
function asset_url(string $path): string
{
    $path = ltrim($path, '/');
    return route_url("/assets/{$path}");
}

/* ============================================================
   🔹 LOGGING / DEBUG
   ============================================================ */

/**
 * Escribe un mensaje en el archivo error.log de /storage (si existe).
 */
function app_log(string $message): void
{
    $logFile = __DIR__ . '/../storage/error.log';
    $date = date('[Y-m-d H:i:s]');
    @file_put_contents($logFile, "{$date} {$message}\n", FILE_APPEND);
}

if (!function_exists('app_log')) {
    function app_log(string $msg, string $file = null): void {
        $file = $file ?: (BASE_PATH . '/storage/error.log');
        $dir  = dirname($file);
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $ts = date('Y-m-d H:i:s');
        @file_put_contents($file, "[$ts] $msg\n", FILE_APPEND);
    }
}

/* ============================================================
   🔹 CONFIG / INI HELPERS
   ============================================================ */

if (!function_exists('app_root_path')) {
    function app_root_path(): string {
        return BASE_PATH; // usamos la constante unificada
    }
}

if (!function_exists('config_path')) {
    function config_path(): string {
        return app_root_path() . DIRECTORY_SEPARATOR . 'config';
    }
}

if (!function_exists('app_env')) {
    function app_env(): string {
        $env = getenv('APP_ENV');
        $env = $env ? strtolower(trim($env)) : 'local';
        return in_array($env, ['production','prod'], true) ? 'production' : 'local';
    }
}

if (!function_exists('read_config_ini')) {
    function read_config_ini(): array {
        $cfgDir = config_path();
        $env    = app_env();

        $candidates = $env === 'production'
            ? [$cfgDir . '/app.production.ini', $cfgDir . '/app.local.ini']
            : [$cfgDir . '/app.local.ini'];
        $candidates[] = $cfgDir . '/app.example.ini';

        $file = null;
        foreach ($candidates as $cand) {
            if (is_file($cand)) { $file = $cand; break; }
        }
        if (!$file) {
            throw new RuntimeException('No configuration file found in /config.');
        }

        $data = parse_ini_file($file, true, INI_SCANNER_TYPED);
        if ($data === false) {
            throw new RuntimeException('Unable to parse configuration file: ' . $file);
        }

        $data['db']   = $data['db']   ?? [];
        $data['app']  = $data['app']  ?? [];
        $data['mail'] = $data['mail'] ?? [];

        // overrides opcionales por entorno
        $data['db']['host']    = getenv('DB_HOST')    ?: ($data['db']['host']    ?? 'localhost');
        $data['db']['port']    = (int)(getenv('DB_PORT') ?: ($data['db']['port'] ?? 3306));
        $data['db']['name']    = getenv('DB_NAME')    ?: ($data['db']['name']    ?? '');
        $data['db']['user']    = getenv('DB_USER')    ?: ($data['db']['user']    ?? '');
        $data['db']['pass']    = getenv('DB_PASS')    ?: ($data['db']['pass']    ?? '');
        $data['db']['charset'] = $data['db']['charset'] ?? 'utf8mb4';

        return $data;
    }
}