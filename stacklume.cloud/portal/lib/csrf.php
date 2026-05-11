<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function sc_csrf_token(): string {
    sc_session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function sc_csrf_field(): string {
    $t = htmlspecialchars(sc_csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="_csrf" value="' . $t . '">';
}

function sc_csrf_check(): void {
    sc_session_start();
    $sent = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($sent) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        http_response_code(403);
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'CSRF check failed']);
        } else {
            echo '<h1>CSRF check failed</h1>';
        }
        exit;
    }
}

/** Lighter-weight CSRF check for JSON APIs that read the token from the body or header. */
function sc_csrf_check_json(?string $headerToken): void {
    sc_session_start();
    if (empty($_SESSION['csrf']) || !is_string($headerToken) || !hash_equals($_SESSION['csrf'], $headerToken)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'CSRF check failed']);
        exit;
    }
}
