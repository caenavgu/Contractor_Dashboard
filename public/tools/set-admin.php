<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';
if (APP_ENV !== 'local') { http_response_code(403); exit('Forbidden'); }
$_SESSION['user'] = [
  'user_id'   => 'UDEV001',
  'email'     => 'admin@local.test',
  'user_type' => 'ADM', // códigos válidos: 'ADM','TEC','SOP','CON'
];
session_regenerate_id(true);
header('Content-Type: text/plain; charset=utf-8');
echo "OK: admin session set.\n";
