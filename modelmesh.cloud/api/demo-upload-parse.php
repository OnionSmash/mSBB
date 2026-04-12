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

if (!isset($_FILES['files'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No files uploaded']);
    exit;
}

require_once __DIR__ . '/demo-vector-lib.php';

function mmTypeFromName(string $name): string {
    $lower = strtolower(basename($name));
    if ($lower === 'dockerfile' || str_ends_with($lower, '.dockerfile')) {
        return 'code';
    }
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $map = [
        'pdf' => 'pdf', 'xlsx' => 'xlsx', 'xls' => 'xlsx',
        'docx' => 'docx', 'doc' => 'docx', 'csv' => 'csv',
        'png' => 'img', 'jpg' => 'img', 'jpeg' => 'img', 'tiff' => 'img',
        'txt' => 'docx', 'md' => 'docx', 'rtf' => 'docx',
        'js' => 'code', 'jsx' => 'code', 'ts' => 'code', 'tsx' => 'code',
        'py' => 'code', 'java' => 'code', 'c' => 'code', 'cpp' => 'code',
        'cs' => 'code', 'php' => 'code', 'go' => 'code', 'rs' => 'code',
        'rb' => 'code', 'swift' => 'code', 'kt' => 'code', 'html' => 'code',
        'css' => 'code', 'scss' => 'code', 'json' => 'code', 'yml' => 'code',
        'yaml' => 'code', 'sh' => 'code', 'sql' => 'code',
        'tf' => 'code', 'tfvars' => 'code', 'hcl' => 'code', 'toml' => 'code',
        'ini' => 'code', 'conf' => 'code', 'env' => 'code', 'properties' => 'code'
    ];
    return $map[$ext] ?? 'docx';
}

function mmReadPreview(string $tmpPath, string $filename): string {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $lower = strtolower(basename($filename));
    $textLike = ['txt','csv','json','md','log','xml','html','yml','yaml','sql','js','ts','py','php','tf','tfvars','hcl','toml','ini','conf','env','properties','dockerfile'];
    if ($lower === 'dockerfile' || str_ends_with($lower, '.dockerfile')) {
        $ext = 'dockerfile';
    }
    if (!in_array($ext, $textLike, true)) {
        return 'Uploaded binary file. Parsed metadata and index entry created for retrieval workflows.';
    }
    $raw = @file_get_contents($tmpPath);
    if ($raw === false || $raw === '') {
        return 'Uploaded text-like file with unreadable or empty contents.';
    }
    $raw = preg_replace('/\s+/u', ' ', $raw) ?? '';
    return mb_substr(trim($raw), 0, 280);
}

function mmIngestDocument(string $sessionId, string $docId, string $title, string $text, array $meta): int {
    $index = demoVectorLoadIndex($sessionId);
    if (!isset($index['items']) || !is_array($index['items'])) {
        $index['items'] = [];
    }
    $before = count($index['items']);
    $chunks = demoVectorChunkText($text);
    $chunkIndex = 0;
    foreach ($chunks as $chunkText) {
        $itemId = $docId . '_c' . $chunkIndex;
        $item = [
            'id' => $itemId,
            'doc_id' => $docId,
            'title' => mb_substr($title, 0, 180),
            'text' => mb_substr(demoVectorNormalizeText($chunkText), 0, 2000),
            'metadata' => $meta,
            'embedding' => demoVectorEmbedText($chunkText),
            'updated_at' => gmdate('c'),
        ];
        $replaced = false;
        foreach ($index['items'] as $i => $existing) {
            if (($existing['id'] ?? '') === $itemId) {
                $index['items'][$i] = $item;
                $replaced = true;
                break;
            }
        }
        if (!$replaced) {
            $index['items'][] = $item;
        }
        $chunkIndex++;
    }
    demoVectorSaveIndex($sessionId, $index);
    return max(0, count($index['items']) - $before);
}

$files = $_FILES['files'];
$names = is_array($files['name'] ?? null) ? $files['name'] : [];
$tmpNames = is_array($files['tmp_name'] ?? null) ? $files['tmp_name'] : [];
$sizes = is_array($files['size'] ?? null) ? $files['size'] : [];
$errors = is_array($files['error'] ?? null) ? $files['error'] : [];

$sessionId = session_id();
$items = [];
$accepted = 0;

for ($i = 0; $i < count($names); $i++) {
    $name = (string)($names[$i] ?? 'unknown-file');
    $tmp = (string)($tmpNames[$i] ?? '');
    $size = (int)($sizes[$i] ?? 0);
    $err = (int)($errors[$i] ?? UPLOAD_ERR_NO_FILE);

    if ($err !== UPLOAD_ERR_OK || $tmp === '' || !is_uploaded_file($tmp)) {
        $items[] = [
            'name' => $name,
            'size' => $size,
            'status' => 'error',
            'error' => 'Upload failed',
        ];
        continue;
    }

    $type = mmTypeFromName($name);
    $preview = mmReadPreview($tmp, $name);
    $field = 'Content';
    $docId = 'upload_' . time() . '_' . $i . '_' . bin2hex(random_bytes(3));
    $text = "File: {$name}\nType: {$type}\nField: {$field}\nPreview: {$preview}";
    $addedChunks = mmIngestDocument($sessionId, $docId, $name, $text, ['type' => $type, 'source' => 'upload']);

    $items[] = [
        'id' => $docId,
        'name' => $name,
        'size' => $size,
        'type' => $type,
        'field' => $field,
        'preview' => $preview,
        'chunks' => max(1, $addedChunks),
        'status' => 'indexed',
    ];
    $accepted++;
}

echo json_encode([
    'ok' => true,
    'accepted' => $accepted,
    'items' => $items,
]);
