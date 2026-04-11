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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/demo-vector-lib.php';

$index = demoVectorLoadIndex(session_id());
$items = is_array($index['items'] ?? null) ? $index['items'] : [];

$docSet = [];
$lastUpdated = null;
foreach ($items as $item) {
    $docId = (string)($item['doc_id'] ?? '');
    if ($docId !== '') {
        $docSet[$docId] = true;
    }
    $updated = (string)($item['updated_at'] ?? '');
    if ($updated !== '' && ($lastUpdated === null || strcmp($updated, $lastUpdated) > 0)) {
        $lastUpdated = $updated;
    }
}

echo json_encode([
    'ok' => true,
    'documents' => count($docSet),
    'chunks' => count($items),
    'last_updated' => $lastUpdated,
]);
