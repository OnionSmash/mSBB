<?php
declare(strict_types=1);

function demoVectorDataDir(): string
{
    $base = realpath(__DIR__ . '/..');
    if ($base === false) {
        throw new RuntimeException('Unable to resolve base path.');
    }

    $dir = $base . '/tmp/vector-store';
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to initialize vector store directory.');
    }

    return $dir;
}

function demoVectorIndexPath(string $sessionId): string
{
    return demoVectorDataDir() . '/index_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $sessionId) . '.json';
}

function demoVectorLoadIndex(string $sessionId): array
{
    $path = demoVectorIndexPath($sessionId);
    if (!is_file($path)) {
        return ['items' => []];
    }

    $json = file_get_contents($path);
    if ($json === false || $json === '') {
        return ['items' => []];
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded) || !isset($decoded['items']) || !is_array($decoded['items'])) {
        return ['items' => []];
    }

    return $decoded;
}

function demoVectorSaveIndex(string $sessionId, array $index): void
{
    $path = demoVectorIndexPath($sessionId);
    $encoded = json_encode($index, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        throw new RuntimeException('Failed to encode vector index.');
    }
    if (file_put_contents($path, $encoded, LOCK_EX) === false) {
        throw new RuntimeException('Failed to persist vector index.');
    }
}

function demoVectorNormalizeText(string $text): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    return mb_substr($text, 0, 12000);
}

function demoVectorEmbedText(string $text, int $dimension = 96): array
{
    $clean = mb_strtolower(demoVectorNormalizeText($text));
    $tokens = preg_split('/[^\p{L}\p{N}_-]+/u', $clean, -1, PREG_SPLIT_NO_EMPTY);
    $vector = array_fill(0, $dimension, 0.0);

    if (!$tokens) {
        return $vector;
    }

    foreach ($tokens as $token) {
        $hash = crc32($token);
        $idx = (int)($hash % $dimension);
        $sign = (($hash >> 1) & 1) === 0 ? 1.0 : -1.0;
        $weight = 1.0 + (strlen($token) / 24.0);
        $vector[$idx] += $sign * $weight;
    }

    $norm = 0.0;
    foreach ($vector as $v) {
        $norm += $v * $v;
    }
    $norm = sqrt($norm);
    if ($norm <= 0.0000001) {
        return $vector;
    }

    foreach ($vector as $i => $v) {
        $vector[$i] = $v / $norm;
    }

    return $vector;
}

function demoVectorCosine(array $a, array $b): float
{
    $n = min(count($a), count($b));
    if ($n === 0) {
        return 0.0;
    }

    $dot = 0.0;
    $na = 0.0;
    $nb = 0.0;
    for ($i = 0; $i < $n; $i++) {
        $av = (float)$a[$i];
        $bv = (float)$b[$i];
        $dot += $av * $bv;
        $na += $av * $av;
        $nb += $bv * $bv;
    }

    if ($na <= 0.0 || $nb <= 0.0) {
        return 0.0;
    }

    return $dot / (sqrt($na) * sqrt($nb));
}

function demoVectorChunkText(string $text, int $chunkSize = 700, int $overlap = 120): array
{
    $text = demoVectorNormalizeText($text);
    if ($text === '') {
        return [];
    }

    $len = mb_strlen($text);
    if ($len <= $chunkSize) {
        return [$text];
    }

    $chunks = [];
    $start = 0;
    while ($start < $len) {
        $slice = mb_substr($text, $start, $chunkSize);
        $chunks[] = $slice;
        $start += max(1, $chunkSize - $overlap);
    }

    return array_values(array_filter($chunks, static fn ($c) => trim((string)$c) !== ''));
}
