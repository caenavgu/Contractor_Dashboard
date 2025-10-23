<?php
// includes/helpers.php
// -------------------------------------------------------------
// Helpers reutilizables: config, sanitización, URL, CSRF y rate-limit
// -------------------------------------------------------------
declare(strict_types=1);

// ---------- Config ----------
function read_config_ini(array $candidates): array
{
    foreach ($candidates as $file) {
        if (is_file($file)) {
            $data = parse_ini_file($file, true, INI_SCANNER_TYPED);
            // Aceptamos tanto con sección [default] como plano
            return isset($data['default']) && is_array($data['default']) ? $data['default'] : $data;
        }
    }
    return [];
}

// ---------- Sanitización ----------
function sanitize_string(string $raw_value): string
{
    return htmlspecialchars($raw_value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ---------- Tokens & red ----------
function generate_token(int $bytes_length = 32): string
{
    return bin2hex(random_bytes($bytes_length));
}

function get_client_ip(): string
{
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (strpos($ip, ',') !== false) {
        $ip = trim(explode(',', $ip)[0]);
    }
    return $ip;
}

// ---------- URL helpers (requieren $GLOBALS['app_config']) ----------
function base_url(string $path = ''): string
{
    $cfg_base = $GLOBALS['app_config']['base_url'] ?? '';
    $cfg_base = rtrim($cfg_base, '/');
    $path     = '/' . ltrim($path, '/');
    return $cfg_base . $path;
}
function asset_url(string $path): string
{
    return base_url('assets/' . ltrim($path, '/'));
}
function route_url(string $path): string
{
    return base_url(ltrim($path, '/'));
}

// ---------- CSRF ----------
function ensure_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generate_token(32);
    }
    return $_SESSION['csrf_token'];
}
function validate_csrf_token(?string $token): bool
{
    return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ---------- Rate Limit (file-based) ----------
function rate_limit_key(string $prefix, string $id): string
{
    $hash = hash('sha256', $prefix . '|' . $id);
    $dir  = defined('TEMP_PATH') ? TEMP_PATH : sys_get_temp_dir();
    return $dir . "/rl_{$hash}.json";
}

/**
 * Verifica e incrementa el rate limit.
 * Devuelve [bool $allowed, int $remaining_seconds]
 */
function rate_limit_check_and_touch(string $prefix, string $id, int $max_attempts, int $window_minutes, int $lockout_minutes): array
{
    $file = rate_limit_key($prefix, $id);
    $now = time();
    $data = ['attempts' => [], 'locked_until' => 0];

    if (is_file($file)) {
        $json = @file_get_contents($file);
        if ($json !== false) {
            $tmp = json_decode($json, true);
            if (is_array($tmp)) $data = array_merge($data, $tmp);
        }
    }

    // Bloqueado
    if ($data['locked_until'] > $now) {
        return [false, $data['locked_until'] - $now];
    }

    // Limpiar ventana
    $window_start = $now - ($window_minutes * 60);
    $data['attempts'] = array_values(array_filter($data['attempts'], fn($ts) => $ts >= $window_start));

    // Añadir intento
    $data['attempts'][] = $now;

    // Exceso -> lock
    if (count($data['attempts']) > $max_attempts) {
        $data['locked_until'] = $now + ($lockout_minutes * 60);
        @file_put_contents($file, json_encode($data), LOCK_EX);
        return [false, $data['locked_until'] - $now];
    }

    @file_put_contents($file, json_encode($data), LOCK_EX);
    return [true, 0];
}

function rate_limit_reset(string $prefix, string $id): void
{
    $file = rate_limit_key($prefix, $id);
    if (is_file($file)) @unlink($file);
}