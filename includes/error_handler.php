<?php
// includes/error_handler.php
// Placeholder de manejo de errores centralizado (implementación completa se agregará luego).
declare(strict_types=1);

// NOTA: En producción se desactivará display_errors y se registrará a storage/logs/error.log.
// Aquí solo dejamos los ganchos vacíos para no ejecutar lógica aún.

// Ejemplo de rutas (a completar dinámicamente desde config):
// $logs_path = __DIR__ . '/../storage/logs';
// $log_file = $logs_path . '/error.log';

// set_exception_handler(function (Throwable $e) use ($log_file) {
//     // Registrar en log y devolver respuesta genérica segura.
// });

// set_error_handler(function ($severity, $message, $file, $line) {
//     // Convertir a excepción o registrar.
// });

// register_shutdown_function(function () {
//     // Capturar errores fatales.
// });