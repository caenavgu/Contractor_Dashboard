<?php
// public/index.php
// -------------------------------------------------------------
// Front controller: enruta peticiones a Presenters.
// - Requiere bootstrap (helpers/middleware/config/DB)
// - Instancia repos, servicios y presenters
// - Define rutas usando route_url() y redirect_to()
// -------------------------------------------------------------
declare(strict_types=1);

/* Bootstrap: helpers + middleware + config + DB + mailer */
require __DIR__ . '/../includes/bootstrap.php';

/* ---------- Carga de clases (nombres nuevos StudlyCaps) ---------- */
// Repos
require_once __DIR__ . '/../app/Repositories/UserRepository.php';
require_once __DIR__ . '/../app/Repositories/SessionRepository.php';
require_once __DIR__ . '/../app/Repositories/AuditLogRepository.php';
require_once __DIR__ . '/../app/Repositories/ContractorRepository.php';
require_once __DIR__ . '/../app/Repositories/ContractorStagingRepository.php';
require_once __DIR__ . '/../app/Repositories/UserDetailsRepository.php';   // ✅ añadido
require_once __DIR__ . '/../app/Repositories/UsaStatesRepository.php';

// Servicios
require_once __DIR__ . '/../app/Services/AuthService.php';
require_once __DIR__ . '/../app/Services/SignUpService.php';
require_once __DIR__ . '/../app/Services/ApprovalService.php';

// Presenters
require_once __DIR__ . '/../app/Presenters/SignInPresenter.php';
require_once __DIR__ . '/../app/Presenters/SignUpPresenter.php';
require_once __DIR__ . '/../app/Presenters/ApprovalsPresenter.php';

/* ---------- Instancias ---------- */
$user_repo        = new UserRepository($pdo);
$session_repo     = new SessionRepository($pdo);
$audit_repo       = new AuditLogRepository($pdo);
$contractor_repo  = new ContractorRepository($pdo);
$staging_repo     = new ContractorStagingRepository($pdo);
$user_details_repo = new UserDetailsRepository($pdo);   // ✅ añadido

// Servicios
$auth_service = new AuthService($user_repo, $session_repo, $audit_repo);

$sign_up_service = new SignUpService(    // ✅ orden correcto
    $pdo,                                // 1. PDO
    $user_repo,                          // 2. UserRepository
    $contractor_repo,                    // 3. ContractorRepository
    $user_details_repo,                  // 4. UserDetailsRepository
    $audit_repo                          // 5. AuditLogRepository
);

$approval_service = new ApprovalService(
    $pdo,
    $user_repo,
    $contractor_repo,
    $staging_repo,
    $audit_repo
);

// Presenters
$sign_in_presenter   = new SignInPresenter($auth_service);
$sign_up_presenter   = new SignUpPresenter($pdo);  // ✅ el presenter crea el service internamente
$approvals_presenter = new ApprovalsPresenter($pdo, $approval_service, $staging_repo, $contractor_repo);

/* ---------- Routing ---------- */
$uri_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

/* Rutas normalizadas */
$R_HOME            = route_url('/');
$R_SIGN_IN         = route_url('/sign-in');
$R_SIGN_UP         = route_url('/sign-up');
$R_SIGN_UP_SUCCESS = route_url('/sign-up-success');
$R_VERIFY_EMAIL    = route_url('/verify-email');
$R_APPROVALS       = route_url('/approvals');
$R_DASHBOARD       = route_url('/dashboard');

/* Home -> redirigir a sign-in */
if ($uri_path === $R_HOME || $uri_path === rtrim($R_HOME, '/') . '/index.php') {
    redirect_to('/sign-in');
    exit;
}

/* Sign in */
if ($uri_path === $R_SIGN_IN) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $sign_in_presenter->handle_post();
    } else {
        $sign_in_presenter->handle_get();
    }
    exit;
}

/* Sign up */
if ($uri_path === $R_SIGN_UP) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $sign_up_presenter->handle_post();
    } else {
        $sign_up_presenter->handle_get();
    }
    exit;
}

/* Sign up success (view simple) */
if ($uri_path === $R_SIGN_UP_SUCCESS) {
    require __DIR__ . '/views/sign-up-success.php';
    exit;
}

/* Verify email */
$R_VERIFY = route_url('/verify-email');
if ($uri_path === $R_VERIFY) {
    $sign_up_presenter->handle_verify();
    exit;
}

/* Approvals (solo admin) */
if ($uri_path === $R_APPROVALS) {
    $u = $_SESSION['user'] ?? null;
    $t = strtoupper(trim((string)($u['user_type'] ?? '')));
    if (!$u || !in_array($t, ['ADM', 'ADMIN'], true)) {
        http_response_code(403);
        echo '<h1>403 Forbidden</h1><p>You do not have permission to access this page.</p>';
        exit;
    }
    $admin_user_id = (string)$u['user_id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!validate_csrf_token((string)($_POST['_csrf'] ?? ''))) {
            http_response_code(400);
            echo '<h1>400 Bad Request</h1><p>Invalid CSRF token.</p>';
            exit;
        }
        $action = $_POST['action'] ?? '';
        $result = $approvals_presenter->handle_post($action, $_POST, $admin_user_id);
        $view   = array_merge($approvals_presenter->handle_get(), $result);
    } else {
        $view = $approvals_presenter->handle_get();
    }
    require __DIR__ . '/views/approvals.php';
    exit;
}

/* 404 por defecto */
http_response_code(404);
echo '<h1>404 Not Found</h1><p>Route not found: ' . sanitize_string($uri_path) . '</p>';
