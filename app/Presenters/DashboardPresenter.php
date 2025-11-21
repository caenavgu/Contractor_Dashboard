<?php
// app/Presenters/DashboardPresenter.php
// -------------------------------------------------------------
// Presenter del dashboard de usuario:
// - Requiere sesión
// - Lista garantías del usuario con paginación y búsqueda
// - Permite buscar garantías de otros usuarios por serial + apellido
// -------------------------------------------------------------
declare(strict_types=1);

class DashboardPresenter
{
    public function __construct(
        private DashboardService $dashboard_service
    ) {}

    private function view_path(): string
    {
        return BASE_PATH . '/public/views/dashboard.php';
    }

    public function handle_get(): void
    {
        // 401 si no hay sesión; también asegura $_SESSION['user']
        $user_id = require_signed_in();

        $page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
        if ($page < 1) {
            $page = 1;
        }

        $search_query  = isset($_GET['q']) ? (string)$_GET['q'] : null;
        $ext_serial    = isset($_GET['ext_serial']) ? (string)$_GET['ext_serial'] : null;
        $ext_last_name = isset($_GET['ext_last_name']) ? (string)$_GET['ext_last_name'] : null;

        $dashboard_data = $this->dashboard_service->getDashboardData(
            $user_id,
            $search_query,
            $page
        );

        $external_warranty = null;
        $external_search_performed = false;

        if ($ext_serial !== null || $ext_last_name !== null) {
            $external_search_performed = true;
            $external_warranty = $this->dashboard_service->searchExternalWarranty(
                $user_id,
                $ext_serial,
                $ext_last_name
            );
        }

        // Variables que usará la vista
        $warranties  = $dashboard_data['warranties'];
        $pagination  = $dashboard_data['pagination'];
        $filters     = $dashboard_data['filters'];

        $view_file = $this->view_path();
        if (!is_file($view_file)) {
            http_response_code(500);
            echo '<h1>500 Internal Server Error</h1><p>View not found: ' . htmlspecialchars($view_file) . '</p>';
            exit;
        }

        require $view_file;
    }
}
