<?php
// includes/middleware.php
// -------------------------------------------------------------
// Middlewares y guards de acceso.
// - current_user() e is_admin_user()
// - require_signed_in() y require_admin_guard()
// -------------------------------------------------------------
declare(strict_types=1);

/** Devuelve el usuario actual desde la sesión (o null si no hay). */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/** ¿El usuario actual es admin? Acepta 'ADM' y 'ADMIN'. */
function is_admin_user(?array $u): bool
{
    if (!$u) return false;
    $t = strtoupper(trim((string)($u['user_type'] ?? '')));
    return ($t === 'ADM' || $t === 'ADMIN');
}

/** Guard para rutas que requieren sesión. */
function require_signed_in(): ?string
{
    $u = current_user();
    if (!$u) {
        http_response_code(401);
        echo '<h1>401 Unauthorized</h1><p>Please sign in.</p>';
        return null;
    }
    return (string)($u['user_id'] ?? '');
}

/** Guard para rutas que requieren rol admin. */
function require_admin_guard(): ?string
{
    $u = current_user();
    if (!is_admin_user($u)) {
        http_response_code(403);
        echo '<h1>403 Forbidden</h1><p>You do not have permission to access this page.</p>';
        return null;
    }
    return (string)($u['user_id'] ?? '');
}


