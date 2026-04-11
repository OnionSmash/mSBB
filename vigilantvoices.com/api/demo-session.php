<?php
declare(strict_types=1);

header('Content-Type: application/json');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

$authed = !empty($_SESSION['vv_auth']);
$user = $authed ? (string)($_SESSION['vv_user'] ?? 'vvadmin') : null;

echo json_encode([
    'ok' => true,
    'authenticated' => $authed,
    'user' => $user,
]);
