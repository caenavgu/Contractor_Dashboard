<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

// Crea instancias necesarias
$user_repo    = new UserRepository($pdo);
$contractor_repo = new ContractorRepository($pdo);
$staging_repo = new ContractorStagingRepository($pdo);
$audit_repo   = new AuditLogRepository($pdo);

// Services / Presenters
$sign_up_service = new SignUpService($user_repo, $contractor_repo, $staging_repo, $audit_repo);
$sign_up_presenter = new SignUpPresenter($sign_up_service);

// Sign-in dependencies (si ya los tienes)
$session_repo = new SessionRepository($pdo);
$auth_srv     = new AuthService($user_repo, $session_repo, $audit_repo);
$signin       = new SignInPresenter($auth_srv);

$uri_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

/* SIGN IN (existing) */
if ($uri_path === route_url('/sign-in') || $uri_path === '/sign-in') {
    $view = $_SERVER['REQUEST_METHOD'] === 'POST' ? $signin->handle_post($pdo) : $signin->handle_get();
    if (!empty($view['_redirect'])) {
        header('Location: ' . route_url($view['_redirect']), true, 302);
        exit;
    }
    require __DIR__ . '/views/sign_in.php';
    exit;
}

/* SIGN UP */
if ($uri_path === route_url('/sign-up') || $uri_path === '/sign-up') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $view = $sign_up_presenter->handle_post();
    } else {
        $view = $sign_up_presenter->handle_get();
    }
    require __DIR__ . '/views/sign_up.php';
    exit;
}

/* VERIFY EMAIL */
if ($uri_path === route_url('/verify-email') || $uri_path === '/verify-email') {
    $token = $_GET['token'] ?? '';
    if (empty($token)) {
        echo "Invalid token";
        exit;
    }
    $user = $user_repo->find_by_verification_token($token);
    if (!$user) {
        echo "Token invalid or expired.";
        exit;
    }
    // Marcar verificado
    $user_repo->mark_email_verified((int)$user['user_id']);
    // Enviar email "under review"
    $subject = 'Your profile is under review';
    $body = "<p>Dear " . htmlspecialchars($user['first_name'] ?? '') . ",</p>
             <p>Your profile has been verified and is now under review. This process can take 24-72 hours.</p>";
    send_mail($user['email'], $subject, $body);
    $audit_repo->add($user['user_id'], 'user', $user['user_id'], 'email_user', ['type'=>'under_review_sent']);
    echo "<p>Email verified. Your profile is under review. You will receive another email within 24-72 hours.</p>";
    exit;
}

/* APPROVALS (admin minimal) */
if ($uri_path === route_url('/approvals') || $uri_path === '/approvals') {
    // NOTE: aquí deberías validar que el actor es admin; por ahora es acceso abierto en MVP
    // Cargamos pending stagings y users waiting (email_verified_at != NULL and is_active=0)
    $pending_stagings = $staging_repo->find_pending();
    $sql = "SELECT user_id, email, first_name, last_name, email_verified_at, is_active FROM users WHERE email_verified_at IS NOT NULL AND is_active = 0 ORDER BY created_at DESC";
    $st = $pdo->query($sql);
    $pending_users = $st->fetchAll();

    require __DIR__ . '/views/approvals.php';
    exit;
}

/* APPROVAL ACTIONS: approve user (POST) */
if ($uri_path === route_url('/approvals/user/approve') && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $admin_id = 1; // TODO: obtener admin id real desde sesión
    $user_repo->activate_user($user_id, $admin_id);
    $audit_repo->add($admin_id, 'user', $user_id, 'user_approved', ['by'=>$admin_id]);
    // enviar correo
    $user = $pdo->prepare("SELECT email, first_name FROM users WHERE user_id = :id");
    $user->execute([':id'=>$user_id]); $u = $user->fetch();
    if ($u) {
        send_mail($u['email'], 'Your account has been approved', "<p>Dear " . htmlspecialchars($u['first_name'] ?? '') . ",</p><p>Your account has been approved.</p>");
    }
    header('Location: ' . route_url('/approvals'));
    exit;
}

/* STAGING MERGE / DISCARD minimal endpoints */
if ($uri_path === route_url('/approvals/staging/merge') && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $staging_id = (int)($_POST['staging_id'] ?? 0);
    $admin_id = 1;
    $staging = $staging_repo->find_by_id($staging_id);
    if (!$staging) { header('Location: ' . route_url('/approvals')); exit; }
    // Apply selected fields (from POST 'apply_fields' array)
    $apply = $_POST['apply_fields'] ?? [];
    $fields_to_apply = [];
    foreach ($apply as $field) {
        // map field input_* to contractor field name
        $map = [
            'input_company_name'=>'company_name',
            'input_address'=>'address',
            'input_address_2'=>'address_2',
            'input_city'=>'city',
            'input_state_code'=>'state_code',
            'input_zip_code'=>'zip_code',
            'input_company_phone'=>'company_phone',
            'input_company_email'=>'company_email',
            'input_company_website'=>'company_website'
        ];
        if (isset($map[$field])) {
            $fields_to_apply[$map[$field]] = $staging[$field];
        }
    }
    // update contractor
    if ($staging['existing_contractor_id']) {
        $contractor_id = (int)$staging['existing_contractor_id'];
        $contractor_repo->update_partial($contractor_id, $fields_to_apply);
        $staging_repo->mark_merged($staging_id, $admin_id);
        $audit_repo->add($admin_id, 'contractor', $contractor_id, 'contractor_merge_performed', ['staging_id'=>$staging_id, 'applied_fields'=>array_keys($fields_to_apply)]);
    }
    header('Location: ' . route_url('/approvals'));
    exit;
}

if ($uri_path === route_url('/approvals/staging/discard') && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $staging_id = (int)($_POST['staging_id'] ?? 0);
    $admin_id = 1;
    $staging_repo->mark_discarded($staging_id, $admin_id);
    $audit_repo->add($admin_id, 'contractor_staging', $staging_id, 'contractor_staging_discarded', []);
    header('Location: ' . route_url('/approvals'));
    exit;
}

/* DASHBOARD (placeholder) */
if ($uri_path === route_url('/dashboard') || $uri_path === '/dashboard') {
    require __DIR__ . '/views/dashboard.php';
    exit;
}

/* HOME fallback */
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Contractor App</title><meta name="viewport" content="width=device-width, initial-scale=1"><base href="<?= sanitize_string(base_url('/')) ?>"><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><div class="container py-5"><h1 class="mb-3">Contractor App</h1><p class="text-muted">Go to <a href="<?= route_url('/sign-in') ?>">Sign In</a> or <a href="<?= route_url('/sign-up') ?>">Sign Up</a>.</p></div></body></html>
