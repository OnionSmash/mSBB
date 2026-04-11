<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && (($_GET['health'] ?? '') === '1')) {
    $token = getenv('CF_AIG_TOKEN') ?: (getenv('CF_AI_GATEWAY_API_TOKEN') ?: '');
    $gatewayUrl = getenv('CF_AI_GATEWAY_URL') ?: '';

    $placeholderUrl = ($gatewayUrl === '' || str_contains($gatewayUrl, 'YOUR_ACCOUNT_TAG') || str_contains($gatewayUrl, 'YOUR_GATEWAY_SLUG'));
    $configured = ($token !== '' && !$placeholderUrl);

    http_response_code($configured ? 200 : 503);
    echo json_encode([
        'ok' => $configured,
        'service' => 'cloudflare-ai-gateway',
        'configured' => $configured,
        'gatewayUrl' => $gatewayUrl,
        'missing' => array_values(array_filter([
            $token === '' ? 'CF_AI_GATEWAY_API_TOKEN' : null,
            $placeholderUrl ? 'CF_AI_GATEWAY_URL' : null,
        ])),
        'exampleCurl' => "curl -X POST https://" . ($_SERVER['HTTP_HOST'] ?? 'example.com') . "/api/ai-gateway-test.php -H 'Content-Type: application/json' -d '{\"prompt\":\"Say hello from AI Gateway\"}'"
    ]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';
if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== $host) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid origin']);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw ?: '', true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON payload']);
    exit;
}

$prompt = trim((string)($input['prompt'] ?? 'Say hello from Cloudflare AI Gateway test endpoint.'));
$model = trim((string)($input['model'] ?? (getenv('CF_AI_GATEWAY_MODEL') ?: 'dynamic/mm_route')));

$token = getenv('CF_AIG_TOKEN') ?: (getenv('CF_AI_GATEWAY_API_TOKEN') ?: '');
$gatewayUrl = trim((string)(getenv('CF_AI_GATEWAY_URL') ?: ''));

if ($token === '') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'CF_AIG_TOKEN or CF_AI_GATEWAY_API_TOKEN is not configured']);
    exit;
}

if ($gatewayUrl === '' || str_contains($gatewayUrl, 'YOUR_ACCOUNT_TAG') || str_contains($gatewayUrl, 'YOUR_GATEWAY_SLUG')) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'CF_AI_GATEWAY_URL is not configured',
        'hint' => 'Set CF_AI_GATEWAY_URL to your gateway base URL, e.g. https://gateway.ai.cloudflare.com/v1/<account_tag>/<gateway_slug>/openai'
    ]);
    exit;
}

$endpoint = rtrim($gatewayUrl, '/');
if (!str_ends_with($endpoint, '/chat/completions')) {
    $endpoint .= '/chat/completions';
}

$payload = [
    'model' => $model,
    'messages' => [
        ['role' => 'user', 'content' => $prompt]
    ],
    'max_tokens' => 120,
    'temperature' => 0.2,
];

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 25,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token,
    ],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
]);

$responseBody = curl_exec($ch);
$curlError = curl_error($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($responseBody === false) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'Upstream request failed', 'detail' => $curlError]);
    exit;
}

$responseJson = json_decode($responseBody, true);
if (!is_array($responseJson)) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'Invalid upstream response', 'status' => $statusCode]);
    exit;
}

if ($statusCode < 200 || $statusCode >= 300) {
    http_response_code($statusCode >= 400 ? $statusCode : 502);
    echo json_encode([
        'ok' => false,
        'error' => 'AI Gateway request failed',
        'status' => $statusCode,
        'upstream' => $responseJson,
    ]);
    exit;
}

$reply = '';
if (isset($responseJson['choices'][0]['message']['content']) && is_string($responseJson['choices'][0]['message']['content'])) {
    $reply = $responseJson['choices'][0]['message']['content'];
}

http_response_code(200);
echo json_encode([
    'ok' => true,
    'model' => $model,
    'reply' => $reply,
    'upstream' => $responseJson,
]);
