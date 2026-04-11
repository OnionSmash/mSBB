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

$index = demoVectorLoadIndex(session_id());
$items = is_array($index['items'] ?? null) ? $index['items'] : [];

if (!$items) {
    echo json_encode([
        'ok' => true,
        'results' => [],
        'count' => 0,
    ]);
    exit;
}

$queryVector = demoVectorEmbedText($query);
$scored = [];

foreach ($items as $item) {
    $embedding = is_array($item['embedding'] ?? null) ? $item['embedding'] : [];
    if (!$embedding) {
        continue;
    }

    $score = demoVectorCosine($queryVector, $embedding);
    $scored[] = [
        'id' => (string)($item['id'] ?? ''),
        'doc_id' => (string)($item['doc_id'] ?? ''),
        'title' => (string)($item['title'] ?? 'Untitled'),
        'text' => (string)($item['text'] ?? ''),
        'metadata' => is_array($item['metadata'] ?? null) ? $item['metadata'] : [],
        'score' => $score,
    ];
}

usort($scored, static fn ($a, $b) => $b['score'] <=> $a['score']);
$results = array_slice($scored, 0, $topK);

echo json_encode([
    'ok' => true,
    'results' => $results,
    'count' => count($results),
]);
