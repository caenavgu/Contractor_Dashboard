<?php
// app/Presenters/SignUpPresenter.php
// -------------------------------------------------------------
// Presentador de Sign Up:
//  - GET  /sign-up          -> muestra formulario (con USA states)
//  - POST /sign-up          -> procesa registro y redirige a /sign-up-success
//  - GET  /verify-email?t=… -> verifica token y muestra resultado
// Con diagnóstico: logs, detección de post_max_size excedido,
// y manejo robusto de errores para mostrar mensaje en la vista.
// -------------------------------------------------------------
declare(strict_types=1);

class SignUpPresenter
{
    private SignUpService $service;
    private UsaStatesRepository $states_repo;

    public function __construct(SignUpService $service)
    {
        $this->service = $service;

        /** @var PDO $pdo */
        global $pdo;
        $this->states_repo = new UsaStatesRepository($pdo);
    }

    /** Muestra el formulario de registro. */
    public function handle_get(): void
    {
        ensure_csrf_token();

        try {
            $states = $this->states_repo->list_all();
        } catch (\Throwable $e) {
            app_log('SignUpPresenter::handle_get list_all() error: ' . $e->getMessage());
            $states = [];
        }

        $view = [
            'states' => $states,
            'error'  => null,
        ];

        require BASE_PATH . '/public/views/sign-up.php';
    }

    /** Procesa el registro del usuario. */
    public function handle_post(): void
    {
        ensure_csrf_token();

        // --- LOG DE ENTRADA (para depuración) ---
        $cl = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        app_log("SignUpPresenter::POST hit; CONTENT_LENGTH={$cl}; FILES=" . json_encode(array_keys($_FILES ?? [])));

        // 0) Caso típico: post_max_size excedido -> $_POST y $_FILES llegan vacíos.
        //    Si hay CONTENT_LENGTH > 0 y ambos arrays vienen vacíos, avisamos explícito.
        if ($cl > 0 && empty($_POST) && empty($_FILES)) {
            $msg = 'The submitted form exceeded server limits (post_max_size/upload_max_filesize). Reduce file size or increase PHP limits.';
            app_log("SignUpPresenter::POST payload discarded by PHP limits.");
            $states = $this->safe_states();
            $view = ['states' => $states, 'error' => $msg];
            require BASE_PATH . '/public/views/sign-up.php';
            return;
        }

        // 1) CSRF
        $csrf = (string)($_POST['_csrf'] ?? '');
        if (!validate_csrf_token($csrf)) {
            http_response_code(400);
            echo '<h1>400 Bad Request</h1><p>Invalid CSRF token.</p>';
            return;
        }

        try {
            // 2) Validaciones mínimas en el presenter (coinciden con tu <form>)
            $email       = strtolower(trim((string)($_POST['email'] ?? '')));
            $password    = (string)($_POST['password'] ?? '');
            $password2   = (string)($_POST['confirm_password'] ?? '');
            $first_name  = strtoupper(trim((string)($_POST['first_name'] ?? '')));
            $last_name   = strtoupper(trim((string)($_POST['last_name'] ?? '')));
            $phone       = (string)($_POST['phone_number'] ?? '');
            $org         = strtoupper(trim((string)($_POST['certifying_organization'] ?? '')));
            $epa_number  = (string)($_POST['epa_certification_number'] ?? '');

            $has_contractor = !empty($_POST['has_contractor']);

            $err = null;
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $err = 'Invalid email.';
            } elseif ($password === '' || strlen($password) < 8) {
                $err = 'Password must be at least 8 characters.';
            } elseif ($password !== $password2) {
                $err = 'Passwords do not match.';
            } elseif ($first_name === '' || $last_name === '') {
                $err = 'First and last name are required.';
            } elseif ($phone === '') {
                $err = 'Phone number is required.';
            } elseif ($org === '') {
                $err = 'Certifying organization is required.';
            } elseif ($epa_number === '') {
                $err = 'EPA certification number is required.';
            } elseif (empty($_FILES['epa_photo']) || (int)($_FILES['epa_photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $err = 'EPA certification photo is required.';
            }

            if ($err !== null) {
                app_log("SignUpPresenter::validation error -> {$err}");
                $states = $this->safe_states();
                $view = ['states' => $states, 'error' => $err];
                require BASE_PATH . '/public/views/sign-up.php';
                return;
            }

            // 3) Preparar payload para el service
            $form = [
                'first_name'               => $first_name,
                'last_name'                => $last_name,
                'email'                    => $email,
                'password'                 => $password,
                'confirm_password'         => $password2,
                'phone_number'             => $phone,
                'certifying_organization'  => $org,
                'epa_certification_number' => strtoupper($epa_number),

                'has_contractor'           => $has_contractor ? 1 : 0,
                'cac_license_number'       => strtoupper(trim((string)($_POST['cac_license_number'] ?? ''))),
                'company_name'             => strtoupper(trim((string)($_POST['company_name'] ?? ''))),
                'company_phone'            => (string)($_POST['company_phone'] ?? ''),
                'company_email'            => (string)($_POST['company_email'] ?? ''),
                'company_website'          => (string)($_POST['company_website'] ?? ''),
                'address'                  => strtoupper(trim((string)($_POST['address'] ?? ''))),
                'address_2'                => strtoupper(trim((string)($_POST['address_2'] ?? ''))),
                'city'                     => strtoupper(trim((string)($_POST['city'] ?? ''))),
                'state_code'               => strtoupper(trim((string)($_POST['state_code'] ?? ''))),
                'zip_code'                 => (string)($_POST['zip_code'] ?? ''),
                'user_type'                => 'TEC',
            ];

            $files = $_FILES ?? [];

            // 4) Lógica de registro
            $result = $this->service->register($form, $files);

            if (!empty($result['ok'])) {
                $to = (string)($result['redirect'] ?? '/sign-up-success');
                app_log("SignUpPresenter::register ok; redirect={$to}");
                redirect_to($to);
                return;
            }

            $errMsg = (string)($result['error'] ?? 'Unexpected error');
            app_log("SignUpPresenter::register error -> {$errMsg}");

            // 5) Falló → re-render con mensaje
            $states = $this->safe_states();
            $view = ['states' => $states, 'error' => $errMsg];
            require BASE_PATH . '/public/views/sign-up.php';
        } catch (\Throwable $e) {
            app_log('SignUpPresenter::handle_post fatal: ' . $e->getMessage());
            $states = $this->safe_states();
            $view = ['states' => $states, 'error' => 'Registration failed. Please try again.'];
            require BASE_PATH . '/public/views/sign-up.php';
        }
    }

    /** Verifica el email a partir del token (?t=...). */
    public function handle_verify(): void
    {
        $token = (string)($_GET['t'] ?? '');

        $res = $this->service->verify_email($token);

        $view = [
            'ok'      => (bool)($res['ok'] ?? false),
            'message' => (string)($res['message'] ?? ''),
        ];

        require BASE_PATH . '/public/views/verify-email.php';
    }

    /** Carga segura de estados con log si falla. */
    private function safe_states(): array
    {
        try {
            return $this->states_repo->list_all();
        } catch (\Throwable $e) {
            app_log('SignUpPresenter::safe_states error: ' . $e->getMessage());
            return [];
        }
    }
}
