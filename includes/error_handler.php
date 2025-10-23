<?php
// includes/error_handler.php
// -------------------------------------------------------------
// Manejo de errores: solo errores severos como excepción.
// Notices/Warnings se registran pero NO detienen la ejecución.
// -------------------------------------------------------------
declare(strict_types=1);

$log_file_path = __DIR__ . '/../storage/logs/error.log';
if (!is_dir(dirname($log_file_path))) {
    @mkdir(dirname($log_file_path), 0775, true);
}

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    // No interrumpir por avisos/advertencias, solo logear.
    if (in_array($severity, [E_NOTICE, E_USER_NOTICE, E_WARNING, E_USER_WARNING, E_DEPRECATED, E_USER_DEPRECATED], true)) {
        error_log(sprintf("[%s] PHP %s: %s in %s:%d\n",
            date('Y-m-d H:i:s'),
            match ($severity) {
                E_NOTICE, E_USER_NOTICE => 'Notice',
                E_WARNING, E_USER_WARNING => 'Warning',
                E_DEPRECATED, E_USER_DEPRECATED => 'Deprecated',
                default => 'Info'
            },
            $message, $file, $line
        ), 3, $GLOBALS['log_file_path'] ?? $log_file_path);
        return true; // manejado
    }

    // Errores serios -> excepción
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function (Throwable $e) use ($log_file_path): void {
    $log_message = sprintf("[%s] %s in %s:%d\nStack:\n%s\n\n",
        date('Y-m-d H:i:s'), $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString()
    );
    error_log($log_message, 3, $log_file_path);

    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>Unexpected Error</h1><p>Something went wrong. Please try again later.</p>';
});

register_shutdown_function(function () use ($log_file_path): void {
    $last = error_get_last();
    if ($last && in_array($last['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $msg = sprintf("[%s] Fatal: %s in %s:%d\n\n", date('Y-m-d H:i:s'), $last['message'], $last['file'], $last['line']);
        error_log($msg, 3, $log_file_path);
    }
});
