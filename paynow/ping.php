<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode([
    'ok'      => true,
    'php'     => PHP_VERSION,
    'curl'    => extension_loaded('curl'),
    'server'  => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
]);
