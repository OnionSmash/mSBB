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

function demoVectorDbDsn(): ?string
{
    $dsn = demoVectorEnv('DEMO_VECTOR_PG_DSN');
    if (is_string($dsn) && trim($dsn) !== '') {
        return trim($dsn);
    }

    $host = demoVectorEnv('DEMO_VECTOR_PG_HOST') ?: '127.0.0.1';
    $port = demoVectorEnv('DEMO_VECTOR_PG_PORT') ?: '5432';
    $db = demoVectorEnv('DEMO_VECTOR_PG_DB') ?: '';
    if ($db === '') {
        return null;
    }
    return 'pgsql:host=' . $host . ';port=' . $port . ';dbname=' . $db;
}

function demoVectorDbUser(): string
{
    return (string)(demoVectorEnv('DEMO_VECTOR_PG_USER') ?: '');
}

function demoVectorDbPass(): string
{
    return (string)(demoVectorEnv('DEMO_VECTOR_PG_PASS') ?: '');
}

function demoVectorEnv(string $key): ?string
{
    $serverValue = $_SERVER[$key] ?? null;
    if (is_string($serverValue) && trim($serverValue) !== '') {
        return trim($serverValue);
    }
    $envValue = getenv($key);
    if (is_string($envValue) && trim($envValue) !== '') {
        return trim($envValue);
    }
    static $fileDefaults = null;
    if ($fileDefaults === null) {
        $fileDefaults = [];
        $secretsPath = '/etc/demo-rag-db-secrets';
        if (is_file($secretsPath)) {
            $lines = file($secretsPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (is_array($lines)) {
                $raw = [];
                foreach ($lines as $line) {
                    $parts = explode('=', (string)$line, 2);
                    if (count($parts) !== 2) {
                        continue;
                    }
                    $raw[trim($parts[0])] = trim($parts[1]);
                }
                $isVigilant = str_contains(__DIR__, 'vigilantvoices.com');
                $prefix = $isVigilant ? 'VV_' : 'MM_';
                $mapped = [
                    'DEMO_VECTOR_PG_DB' => $raw[$prefix . 'DB'] ?? null,
                    'DEMO_VECTOR_PG_USER' => $raw[$prefix . 'USER'] ?? null,
                    'DEMO_VECTOR_PG_PASS' => $raw[$prefix . 'PASS'] ?? null,
                    'DEMO_VECTOR_PG_HOST' => 'localhost',
                    'DEMO_VECTOR_PG_PORT' => '5432',
                ];
                foreach ($mapped as $mk => $mv) {
                    if (is_string($mv) && $mv !== '') {
                        $fileDefaults[$mk] = $mv;
                    }
                }
            }
        }
    }
    $fallback = $fileDefaults[$key] ?? null;
    if (is_string($fallback) && trim($fallback) !== '') {
        return trim($fallback);
    }
    return null;
}

function demoVectorPdo(): ?PDO
{
    static $pdo = false;
    if ($pdo !== false) {
        return $pdo instanceof PDO ? $pdo : null;
    }

    if (!extension_loaded('pdo_pgsql')) {
        $pdo = null;
        return null;
    }

    $dsn = demoVectorDbDsn();
    if ($dsn === null) {
        $pdo = null;
        return null;
    }

    try {
        $conn = new PDO($dsn, demoVectorDbUser(), demoVectorDbPass(), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $conn->exec("SET TIME ZONE 'UTC'");
        $pdo = $conn;
        return $conn;
    } catch (Throwable $e) {
        $pdo = null;
        return null;
    }
}

function demoVectorPgvectorLiteral(array $embedding): string
{
    $vals = array_map(static fn ($v) => (string)(float)$v, $embedding);
    return '[' . implode(',', $vals) . ']';
}

function demoVectorDbEnsureSchema(PDO $pdo): void
{
    static $ready = false;
    if ($ready) return;

    try {
        // Extension is installed during server bootstrap; app role may not have CREATE privilege.
        $pdo->exec('CREATE EXTENSION IF NOT EXISTS vector');
    } catch (Throwable $e) {
        // Safe to continue when extension already exists but role cannot create extensions.
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS demo_vector_chunks (
        session_id TEXT NOT NULL,
        id TEXT NOT NULL,
        doc_id TEXT NOT NULL,
        title TEXT NOT NULL,
        text TEXT NOT NULL,
        metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
        embedding VECTOR(96) NOT NULL,
        updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
        PRIMARY KEY (session_id, id)
    )");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_demo_vector_session ON demo_vector_chunks(session_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_demo_vector_doc ON demo_vector_chunks(session_id, doc_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_demo_vector_embedding_hnsw ON demo_vector_chunks USING hnsw (embedding vector_cosine_ops)');

    $ready = true;
}

function demoVectorLoadIndex(string $sessionId): array
{
    $pdo = demoVectorPdo();
    if ($pdo instanceof PDO) {
        try {
            demoVectorDbEnsureSchema($pdo);
            $stmt = $pdo->prepare('SELECT id, doc_id, title, text, metadata, updated_at FROM demo_vector_chunks WHERE session_id = :sid ORDER BY updated_at DESC');
            $stmt->execute([':sid' => $sessionId]);
            $rows = $stmt->fetchAll();
            $items = array_map(static function (array $r): array {
                $meta = [];
                if (isset($r['metadata'])) {
                    $decoded = json_decode((string)$r['metadata'], true);
                    if (is_array($decoded)) $meta = $decoded;
                }
                return [
                    'id' => (string)$r['id'],
                    'doc_id' => (string)$r['doc_id'],
                    'title' => (string)$r['title'],
                    'text' => (string)$r['text'],
                    'metadata' => $meta,
                    'updated_at' => (string)$r['updated_at'],
                ];
            }, $rows ?: []);
            return ['items' => $items];
        } catch (Throwable $e) {
            // fallback below
        }
    }

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
    $items = is_array($index['items'] ?? null) ? $index['items'] : [];

    $pdo = demoVectorPdo();
    if ($pdo instanceof PDO) {
        try {
            demoVectorDbEnsureSchema($pdo);
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM demo_vector_chunks WHERE session_id = :sid')->execute([':sid' => $sessionId]);
            $ins = $pdo->prepare('INSERT INTO demo_vector_chunks (session_id,id,doc_id,title,text,metadata,embedding,updated_at)
                VALUES (:sid,:id,:doc_id,:title,:text,:meta,CAST(:emb AS vector),:updated_at)');
            foreach ($items as $item) {
                if (!is_array($item)) continue;
                $emb = is_array($item['embedding'] ?? null) ? $item['embedding'] : demoVectorEmbedText((string)($item['text'] ?? ''));
                $ins->execute([
                    ':sid' => $sessionId,
                    ':id' => (string)($item['id'] ?? ''),
                    ':doc_id' => (string)($item['doc_id'] ?? ''),
                    ':title' => mb_substr((string)($item['title'] ?? 'Untitled'), 0, 180),
                    ':text' => mb_substr((string)($item['text'] ?? ''), 0, 2000),
                    ':meta' => json_encode(is_array($item['metadata'] ?? null) ? $item['metadata'] : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ':emb' => demoVectorPgvectorLiteral($emb),
                    ':updated_at' => (string)($item['updated_at'] ?? gmdate('c')),
                ]);
            }
            $pdo->commit();
            return;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            // fallback below
        }
    }

    $path = demoVectorIndexPath($sessionId);
    $encoded = json_encode($index, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        throw new RuntimeException('Failed to encode vector index.');
    }
    if (file_put_contents($path, $encoded, LOCK_EX) === false) {
        throw new RuntimeException('Failed to persist vector index.');
    }
}

function demoVectorClearStore(string $sessionId): void
{
    $pdo = demoVectorPdo();
    if ($pdo instanceof PDO) {
        try {
            demoVectorDbEnsureSchema($pdo);
            $pdo->prepare('DELETE FROM demo_vector_chunks WHERE session_id = :sid')->execute([':sid' => $sessionId]);
            return;
        } catch (Throwable $e) {
            // fallback below
        }
    }
    $path = demoVectorIndexPath($sessionId);
    if (is_file($path)) {
        @unlink($path);
    }
}

function demoVectorSearch(string $sessionId, string $query, int $topK): array
{
    $topK = max(1, min(8, $topK));
    $pdo = demoVectorPdo();
    if ($pdo instanceof PDO) {
        try {
            demoVectorDbEnsureSchema($pdo);
            $qv = demoVectorPgvectorLiteral(demoVectorEmbedText($query));
            $sql = 'SELECT id, doc_id, title, text, metadata, (1 - (embedding <=> CAST(:qvec AS vector))) AS score
                    FROM demo_vector_chunks
                    WHERE session_id = :sid
                    ORDER BY embedding <=> CAST(:qvec AS vector)
                    LIMIT ' . $topK;
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':sid' => $sessionId, ':qvec' => $qv]);
            $rows = $stmt->fetchAll();
            return array_map(static function (array $r): array {
                $meta = [];
                if (isset($r['metadata'])) {
                    $decoded = json_decode((string)$r['metadata'], true);
                    if (is_array($decoded)) $meta = $decoded;
                }
                return [
                    'id' => (string)$r['id'],
                    'doc_id' => (string)$r['doc_id'],
                    'title' => (string)$r['title'],
                    'text' => (string)$r['text'],
                    'metadata' => $meta,
                    'score' => (float)($r['score'] ?? 0),
                ];
            }, $rows ?: []);
        } catch (Throwable $e) {
            // fallback below
        }
    }

    $index = demoVectorLoadIndex($sessionId);
    $items = is_array($index['items'] ?? null) ? $index['items'] : [];
    if (!$items) return [];

    $qv = demoVectorEmbedText($query);
    $scored = [];
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $emb = is_array($item['embedding'] ?? null) ? $item['embedding'] : demoVectorEmbedText((string)($item['text'] ?? ''));
        $scored[] = [
            'id' => (string)($item['id'] ?? ''),
            'doc_id' => (string)($item['doc_id'] ?? ''),
            'title' => (string)($item['title'] ?? 'Untitled'),
            'text' => (string)($item['text'] ?? ''),
            'metadata' => is_array($item['metadata'] ?? null) ? $item['metadata'] : [],
            'score' => demoVectorCosine($qv, $emb),
        ];
    }
    usort($scored, static fn ($a, $b) => $b['score'] <=> $a['score']);
    return array_slice($scored, 0, $topK);
}

function demoVectorStatus(string $sessionId): array
{
    $pdo = demoVectorPdo();
    if ($pdo instanceof PDO) {
        try {
            demoVectorDbEnsureSchema($pdo);
            $stmt = $pdo->prepare('SELECT COUNT(*)::int AS chunks, COUNT(DISTINCT doc_id)::int AS documents, MAX(updated_at) AS last_updated FROM demo_vector_chunks WHERE session_id = :sid');
            $stmt->execute([':sid' => $sessionId]);
            $row = $stmt->fetch();
            return [
                'documents' => (int)($row['documents'] ?? 0),
                'chunks' => (int)($row['chunks'] ?? 0),
                'last_updated' => isset($row['last_updated']) ? (string)$row['last_updated'] : null,
            ];
        } catch (Throwable $e) {
            // fallback below
        }
    }

    $index = demoVectorLoadIndex($sessionId);
    $items = is_array($index['items'] ?? null) ? $index['items'] : [];
    $docSet = [];
    $lastUpdated = null;
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $docId = (string)($item['doc_id'] ?? '');
        if ($docId !== '') $docSet[$docId] = true;
        $updated = (string)($item['updated_at'] ?? '');
        if ($updated !== '' && ($lastUpdated === null || strcmp($updated, $lastUpdated) > 0)) {
            $lastUpdated = $updated;
        }
    }
    return ['documents' => count($docSet), 'chunks' => count($items), 'last_updated' => $lastUpdated];
}

function demoVectorBackendMode(): string
{
    $pdo = demoVectorPdo();
    if (!($pdo instanceof PDO)) {
        return 'json_fallback';
    }

    try {
        demoVectorDbEnsureSchema($pdo);
        return 'pgvector';
    } catch (Throwable $e) {
        return 'json_fallback';
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
