<?php
// includes/middleware.php
// -------------------------------------------------------------
// Middleware simples para proteger rutas de admin/aprobaciones.
// Intenta detectar el usuario actual desde la sesión.
// -------------------------------------------------------------
declare(strict_types=1);

/**
 * Devuelve el arreglo del usuario autenticado desde sesión (o null).
 * Ajusta aquí si tu sesión guarda el usuario en otra clave.
 */
function current_user_from_session(): ?array
{
    // intentos comunes:
    if (!empty($_SESSION['auth_user']) && is_array($_SESSION['auth_user'])) {
        return $_SESSION['auth_user'];
    }
    if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
        return $_SESSION['user'];
    }
    return null;
}

/**
 * Verifica si el usuario actual es admin o soporte (ADM/SOP).
 */
function is_admin_user(?array $user): bool
{
    if (!$user) return false;
    $user_type = strtoupper((string)($user['user_type'] ?? ''));
    return in_array($user_type, ['ADM','SOP'], true);
}

/**
 * Requiere usuario admin; si no, responde 403 con mensaje simple.
 */
function require_admin_or_403(): void
{
    $u = current_user_from_session();
    if (!is_admin_user($u)) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo '<h1>403 Forbidden</h1><p>You do not have permission to access this page.</p>';
        exit;
    }
}
