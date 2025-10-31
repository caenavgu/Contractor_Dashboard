<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');

echo "SESSION DUMP\n===========\n";
var_export($_SESSION['user'] ?? null);
