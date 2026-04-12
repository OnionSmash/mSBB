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

if (empty($_SESSION['vv_auth'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/demo-vector-lib.php';

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '{}', true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON payload']);
    exit;
}

$query = trim((string)($payload['query'] ?? ''));
$topK = (int)($payload['top_k'] ?? 4);
$topK = max(1, min(8, $topK));

if ($query === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing query']);
    exit;
}

$results = demoVectorSearch(session_id(), $query, $topK);

echo json_encode([
    'ok' => true,
    'results' => $results,
    'count' => count($results),
]);
