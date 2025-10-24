<?php
// includes/error_handler.php
declare(strict_types=1);

// Registro a archivo (si no existe la carpeta crea)
$log_file_path = __DIR__ . '/../storage/logs/error.log';
if (!is_dir(dirname($log_file_path))) {
    @mkdir(dirname($log_file_path), 0775, true);
}

// Detectar entorno (si bootstrap aún no definió APP_ENV usa config ini)
$app_env = $GLOBALS['app_config']['app_env'] ?? (getenv('APP_ENV') ?: 'local');

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    global $log_file_path;
    // Loggear avisos / warnings pero no interrumpir
    if (in_array($severity, [E_NOTICE, E_USER_NOTICE, E_WARNING, E_USER_WARNING, E_DEPRECATED, E_USER_DEPRECATED], true)) {
        error_log(sprintf("[%s] PHP %s: %s in %s:%d\n", date('Y-m-d H:i:s'),
            ($severity===E_WARNING||$severity===E_USER_WARNING) ? 'Warning' : 'Notice',
            $message, $file, $line
        ), 3, $log_file_path);
        return true;
    }
    // Errores fatales como excepción
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function (Throwable $e) use ($log_file_path, $app_env): void {
    $log_message = sprintf("[%s] %s in %s:%d\nStack:\n%s\n\n",
        date('Y-m-d H:i:s'), $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString()
    );
    error_log($log_message, 3, $log_file_path);

    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');

    // MOSTRAR DEBUG SOLO EN LOCAL
    if (($app_env ?? 'local') === 'local') {
        echo '<h1>Unexpected Error (DEBUG)</h1>';
        echo '<p><strong>Message:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . ' : ' . $e->getLine() . '</p>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    } else {
        echo '<h1>Unexpected Error</h1><p>Something went wrong. Please try again later.</p>';
    }
});

register_shutdown_function(function () use ($log_file_path) {
    $last = error_get_last();
    if ($last && in_array($last['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $msg = sprintf("[%s] Fatal: %s in %s:%d\n\n", date('Y-m-d H:i:s'), $last['message'], $last['file'], $last['line']);
        error_log($msg, 3, $log_file_path);
    }
});
