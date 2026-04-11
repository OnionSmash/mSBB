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

$documents = $payload['documents'] ?? null;
if (!is_array($documents) || !$documents) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No documents provided']);
    exit;
}

$sessionId = session_id();
$index = demoVectorLoadIndex($sessionId);

if (!isset($index['items']) || !is_array($index['items'])) {
    $index['items'] = [];
}

$added = 0;
$maxItems = 3000;

foreach ($documents as $doc) {
    if (!is_array($doc)) {
        continue;
    }

    $docId = trim((string)($doc['id'] ?? ''));
    $title = trim((string)($doc['title'] ?? 'Untitled Document'));
    $text = (string)($doc['text'] ?? '');
    $meta = is_array($doc['metadata'] ?? null) ? $doc['metadata'] : [];

    if ($docId === '' || trim($text) === '') {
        continue;
    }

    $chunks = demoVectorChunkText($text);
    $chunkIndex = 0;

    foreach ($chunks as $chunkText) {
        if (count($index['items']) >= $maxItems) {
            break 2;
        }

        $itemId = $docId . '_c' . $chunkIndex;

        $existingIdx = null;
        foreach ($index['items'] as $i => $existing) {
            if (($existing['id'] ?? '') === $itemId) {
                $existingIdx = $i;
                break;
            }
        }

        $item = [
            'id' => $itemId,
            'doc_id' => $docId,
            'title' => mb_substr($title, 0, 180),
            'text' => mb_substr(demoVectorNormalizeText($chunkText), 0, 2000),
            'metadata' => $meta,
            'embedding' => demoVectorEmbedText($chunkText),
            'updated_at' => gmdate('c'),
        ];

        if ($existingIdx !== null) {
            $index['items'][$existingIdx] = $item;
        } else {
            $index['items'][] = $item;
            $added++;
        }
        $chunkIndex++;
    }
}

demoVectorSaveIndex($sessionId, $index);

echo json_encode([
    'ok' => true,
    'added_chunks' => $added,
    'total_chunks' => count($index['items']),
]);
