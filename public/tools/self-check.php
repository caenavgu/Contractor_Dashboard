<?php
// public/tools/self-check.php
// -------------------------------------------------------------
// Diagnóstico rápido de rutas y vistas.
// Úsalo solo en local. Verifica:
// - APP_BASE_PATH detectado
// - route_url() / redirect_to()
// - existencia/lectura de vistas (sign-in, sign-up, approvals)
// - permisos de storage (logs y sessions)
// -------------------------------------------------------------
declare(strict_types=1);

require __DIR__ . '/../../includes/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');

function line($k, $v) { echo str_pad($k.':', 28) . $v . "\n"; }

echo "== Contractor App · Self Check ==\n\n";

line('SCRIPT_NAME', $_SERVER['SCRIPT_NAME'] ?? '(n/a)');
line('APP_BASE_PATH', APP_BASE_PATH);
line('route_url("/")', route_url('/'));
line('route_url("/sign-in")', route_url('/sign-in'));
line('route_url("/sign-up")', route_url('/sign-up'));
line('route_url("/approvals")', route_url('/approvals'));
echo "\n";

/* Vistas esperadas */
$views = [
    'sign-in'   => BASE_PATH . '/public/views/sign-in.php',
    'sign-up'   => BASE_PATH . '/public/views/sign-up.php',
    'approvals' => BASE_PATH . '/public/views/approvals.php',
];

echo "Views:\n";
foreach ($views as $name => $path) {
    $exists = is_file($path) ? 'YES' : 'NO';
    $read   = is_readable($path) ? 'YES' : 'NO';
    line("  $name exists", $exists);
    line("  $name readable", $read);
    line("  $name path", $path);
}
echo "\n";

/* Permisos de storage */
$log_dir = BASE_PATH . '/storage/logs';
$sess_dir = BASE_PATH . '/storage/sessions';

echo "Storage:\n";
line('logs dir exists', is_dir($log_dir) ? 'YES' : 'NO');
line('sessions dir exists', is_dir($sess_dir) ? 'YES' : 'NO');
line('error.log exists', is_file($log_dir.'/error.log') ? 'YES' : 'NO');
echo "\n";

/* Probar normalize/redirect sin ejecutar header */
echo "Routing sanity:\n";
$target = route_url('/sign-in');
line('normalize "/sign-in"', $target);

echo "\nOK. If any value looks wrong, fix helpers or file paths.\n";
