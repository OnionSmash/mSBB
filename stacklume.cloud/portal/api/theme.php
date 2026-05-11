<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/csrf.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// Must be logged in. Anonymous theme changes are localStorage-only.
$user = sc_current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

// CSRF — accept token from X-CSRF-Token header (preferred for fetch).
sc_csrf_check_json($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

$raw = file_get_contents('php://input');
$input = json_decode($raw ?: '', true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

$theme = (string)($input['theme'] ?? '');

// Whitelist must match the keys in /js/site.js themeColors.
$allowed = [
    'ocean-depths', 'sunset-boulevard', 'forest-canopy', 'modern-minimalist',
    'golden-hour', 'arctic-frost', 'desert-rose', 'tech-innovation',
    'botanical-garden', 'midnight-galaxy',
];

if (!in_array($theme, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown theme']);
    exit;
}

try {
    $stmt = sc_db()->prepare('UPDATE users SET theme = :t, updated_at = now() WHERE id = :id');
    $stmt->execute([':t' => $theme, ':id' => $user['id']]);
} catch (Throwable $e) {
    error_log('theme save failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not save preference']);
    exit;
}

echo json_encode(['ok' => true, 'theme' => $theme]);
