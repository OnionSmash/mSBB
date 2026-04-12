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
    echo json_encode([
        'ok' => false,
        'error' => 'Unauthorized',
    ]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'error' => 'Method not allowed',
    ]);
    exit;
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody ?: '{}', true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Invalid JSON payload',
    ]);
    exit;
}

$repoUrl = trim((string)($payload['repo_url'] ?? ''));
$branch = trim((string)($payload['branch'] ?? ''));

if (!preg_match('#^https://github\.com/([A-Za-z0-9_.-]+)/([A-Za-z0-9_.-]+?)(?:\.git)?/?$#', $repoUrl, $m)) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Only public GitHub HTTPS URLs are allowed (https://github.com/owner/repo).',
    ]);
    exit;
}

if ($branch !== '' && !preg_match('/^[A-Za-z0-9._\/-]{1,120}$/', $branch)) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Invalid branch format.',
    ]);
    exit;
}

$owner = $m[1];
$repo = $m[2];
$cleanRepoUrl = "https://github.com/{$owner}/{$repo}.git";

$gitPath = trim((string)shell_exec('command -v git 2>/dev/null'));
if ($gitPath === '') {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'git is not available on the server.',
    ]);
    exit;
}

$baseTmp = realpath(__DIR__ . '/..');
if ($baseTmp === false) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Unable to resolve application base path.',
    ]);
    exit;
}

$sandboxRoot = $baseTmp . '/tmp/repo-ingest';
if (!is_dir($sandboxRoot) && !mkdir($sandboxRoot, 0750, true) && !is_dir($sandboxRoot)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Failed to create ingest sandbox.',
    ]);
    exit;
}

$jobId = 'job_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
$cloneDir = $sandboxRoot . '/' . $jobId;
if (!mkdir($cloneDir, 0750, true) && !is_dir($cloneDir)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Failed to initialize ingest workspace.',
    ]);
    exit;
}

$targetDir = $cloneDir . '/repo';
$cloneCmd = $gitPath . ' clone --depth 1 --filter=blob:none --no-tags ';
if ($branch !== '') {
    $cloneCmd .= '--branch ' . escapeshellarg($branch) . ' --single-branch ';
}
$cloneCmd .= escapeshellarg($cleanRepoUrl) . ' ' . escapeshellarg($targetDir) . ' 2>&1';

$output = [];
$exitCode = 0;
exec($cloneCmd, $output, $exitCode);

if ($exitCode !== 0) {
    rrmdir($cloneDir);
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Clone failed. Verify the repository URL and branch.',
        'details' => trim(implode("\n", $output)),
    ]);
    exit;
}

$gitRefCmd = 'cd ' . escapeshellarg($targetDir) . ' && ' . $gitPath . ' rev-parse --abbrev-ref HEAD 2>/dev/null';
$resolvedBranch = trim((string)shell_exec($gitRefCmd));
if ($resolvedBranch === '' || $resolvedBranch === 'HEAD') {
    $resolvedBranch = $branch !== '' ? $branch : 'default';
}

$maxFiles = 60;
/** Skip huge blobs when sampling the repo for the demo (saves disk + keeps ingest fast). */
$maxFileBytes = 512 * 1024;
$files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($targetDir, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile()) {
        continue;
    }

    $absPath = $fileInfo->getPathname();
    $relPath = ltrim(str_replace($targetDir, '', $absPath), DIRECTORY_SEPARATOR);
    $relPathNormalized = str_replace('\\', '/', $relPath);

    if (!isIndexableRepoFile($relPathNormalized)) {
        continue;
    }

    $byteSize = max(0, (int)$fileInfo->getSize());
    if ($byteSize > $maxFileBytes) {
        continue;
    }

    $files[] = [
        'name' => $relPathNormalized,
        'size' => $byteSize,
    ];

    if (count($files) >= $maxFiles) {
        break;
    }
}

rrmdir($cloneDir);

if (!$files) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'No indexable files were found in the repository.',
    ]);
    exit;
}

echo json_encode([
    'ok' => true,
    'repo' => [
        'owner' => $owner,
        'name' => $repo,
        'branch' => $resolvedBranch,
    ],
    'files' => $files,
    'file_count' => count($files),
    'limit' => $maxFiles,
    'max_file_bytes' => $maxFileBytes,
]);

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $items = scandir($dir);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_link($path) || is_file($path)) {
            @unlink($path);
            continue;
        }

        if (is_dir($path)) {
            rrmdir($path);
        }
    }

    @rmdir($dir);
}

function isIndexableRepoFile(string $path): bool
{
    $path = trim($path);
    if ($path === '') {
        return false;
    }

    // Allow any tracked file extension in the repository for demo indexing.
    // Keep minimal path-safety filtering only.
    if (str_contains($path, '..')) {
        return false;
    }

    return true;
}
