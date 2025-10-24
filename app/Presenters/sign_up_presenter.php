<?php
// app/Presenters/sign_up_presenter.php
// -------------------------------------------------------------
// Presenter: orquesta validaciones client/server y llama al servicio.
// -------------------------------------------------------------
declare(strict_types=1);

class SignUpPresenter
{
    public function __construct(private SignUpService $service) {}

    public function handle_get(): array
    {
        ensure_csrf_token();
        return [
            'values' => [],
            'field_errors' => [],
            'general_error' => ''
        ];
    }

    public function handle_post(): array
    {
        $csrf = $_POST['_csrf'] ?? '';
        $view = ['values'=>$_POST, 'field_errors'=>[], 'general_error'=>''];

        if (!validate_csrf_token($csrf)) {
            $view['general_error'] = 'Unexpected error, please retry.';
            return $view;
        }

        // Llamar al service con _FILES
        $result = $this->service->register($_POST, $_FILES);

        if (!$result['ok']) {
            $view['field_errors'] = $result['errors'] ?? [];
            return $view;
        }

        // Mostrar página que pide verificar email
        $view['success_message'] = 'Check your email to verify your account. After verification your profile will be reviewed.';
        return $view;
    }
}
