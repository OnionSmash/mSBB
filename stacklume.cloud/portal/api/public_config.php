<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/env.php';

// Public-safe values only. Anything truly secret stays out of this response.
header('Content-Type: application/json');
header('Cache-Control: public, max-age=300');

echo json_encode([
    'turnstile_sitekey' => sc_env('TURNSTILE_SITE_KEY') ?: null,
]);
