<?php
// app/Presenters/SignUpPresenter.php
// -------------------------------------------------------------
// Presentador de Sign Up:
//  - GET  /sign-up          -> muestra formulario (con USA states)
//  - POST /sign-up          -> procesa registro y redirige a /sign-up-success
//  - GET  /verify-email?t=… -> verifica token y muestra resultado
// -------------------------------------------------------------
declare(strict_types=1);

class SignUpPresenter
{
    private SignUpService $service;
    private UsaStatesRepository $states_repo;

    public function __construct(SignUpService $service)
    {
        // Servicio inyectado
        $this->service = $service;

        // Repo de estados (usamos el PDO global que ya existe en bootstrap)
        /** @var PDO $pdo */
        global $pdo;
        $this->states_repo = new UsaStatesRepository($pdo);
    }

    /**
     * Muestra el formulario de registro.
     */
    public function handle_get(): void
    {
        // Lista de estados para el <select>
        $states = $this->states_repo->list_all(); // [['state_code'=>'FL','state_name'=>'FLORIDA'], ...]

        // Datos para la vista
        $view = [
            'title'  => 'Create your account',
            'states' => $states,
            'errors' => [],
            'old'    => [],
        ];

        require __DIR__ . '/../../public/views/sign-up.php';
    }

    /**
     * Procesa el registro del usuario.
     */
    public function handle_post(): void
    {
        // 1) CSRF
        $csrf = (string)($_POST['_csrf'] ?? '');
        if (!validate_csrf_token($csrf)) {
            http_response_code(400);
            echo '<h1>400 Bad Request</h1><p>Invalid CSRF token.</p>';
            return;
        }

        // 2) Validaciones mínimas en el front del presenter
        $errors = [];
        $email      = strtolower(trim((string)($_POST['email'] ?? '')));
        $password   = (string)($_POST['password'] ?? '');
        $password2  = (string)($_POST['password_confirm'] ?? '');
        $first_name = strtoupper(trim((string)($_POST['first_name'] ?? '')));
        $last_name  = strtoupper(trim((string)($_POST['last_name'] ?? '')));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email.';
        }
        if ($password === '' || strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }
        if ($password !== $password2) {
            $errors['password_confirm'] = 'Passwords do not match.';
        }
        if ($first_name === '') {
            $errors['first_name'] = 'First name is required.';
        }
        if ($last_name === '') {
            $errors['last_name'] = 'Last name is required.';
        }

        // Si hay errores, re-render con old inputs y estados
        if (!empty($errors)) {
            $states = $this->states_repo->list_all();
            $view = [
                'title'  => 'Create your account',
                'states' => $states,
                'errors' => $errors,
                'old'    => $_POST,
            ];
            require __DIR__ . '/../../public/views/sign-up.php';
            return;
        }

        // 3) Preparar payload para el servicio
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // Campos opcionales (normalizamos lo que ya usas en el servicio)
        $form = [
            'first_name'             => $first_name,
            'last_name'              => $last_name,
            'email'                  => $email,
            'password_hash'          => $password_hash,
            'phone_number'           => (string)($_POST['phone_number'] ?? ''),
            'certifying_organization'=> strtoupper(trim((string)($_POST['certifying_organization'] ?? ''))),
            'epa_certification_number'=> (string)($_POST['epa_certification_number'] ?? ''),
            // Asociación con contractor (si tu lógica lo maneja en el servicio, lo dejas tal cual)
            'is_associated'          => isset($_POST['is_associated']) ? 1 : 0,
            'cac_license_number'     => strtoupper(trim((string)($_POST['cac_license_number'] ?? ''))),
            'company_name'           => strtoupper(trim((string)($_POST['company_name'] ?? ''))),
            'company_phone'          => (string)($_POST['company_phone'] ?? ''),
            'company_email'          => (string)($_POST['company_email'] ?? ''),
            'company_website'        => (string)($_POST['company_website'] ?? ''),
            'address'                => strtoupper(trim((string)($_POST['address'] ?? ''))),
            'address_2'              => strtoupper(trim((string)($_POST['address_2'] ?? ''))),
            'city'                   => strtoupper(trim((string)($_POST['city'] ?? ''))),
            'state_code'             => strtoupper(trim((string)($_POST['state_code'] ?? ''))),
            'zip_code'               => (string)($_POST['zip_code'] ?? ''),
            // Tipo por defecto
            'user_type'              => 'TEC',
        ];

        // Archivos (si los usas dentro del servicio)
        $files = $_FILES ?? [];

        // 4) Llamar al servicio
        $result = $this->service->register($form, $files);

        if ($result['ok'] ?? false) {
            // Redirigir a /sign-up-success (ruteo centralizado se encarga)
            $to = (string)($result['redirect'] ?? '/sign-up-success');
            redirect_to($to);
            return;
        }

        // 5) Falló: re-render con errores y old
        $states = $this->states_repo->list_all();
        $view = [
            'title'  => 'Create your account',
            'states' => $states,
            'errors' => ['form' => (string)($result['error'] ?? 'Unexpected error')],
            'old'    => $_POST,
        ];
        require __DIR__ . '/../../public/views/sign-up.php';
    }

    /**
     * Verifica el email a partir del token (?t=...).
     */
    public function handle_verify(): void
    {
        // Tomar token desde la query string
        $token = (string)($_GET['t'] ?? '');

        // Llamar al servicio para verificar
        $res = $this->service->verify_email($token);

        // Preparar datos de vista
        $view = [
            'ok'      => (bool)($res['ok'] ?? false),
            'message' => (string)($res['message'] ?? ''),
        ];

        // Render de la vista verify-email.php
        require __DIR__ . '/../../public/views/verify-email.php';
    }
}
