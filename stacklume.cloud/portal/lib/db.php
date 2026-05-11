<?php
declare(strict_types=1);

/**
 * Stack Compli — DB connector.
 * Loads /etc/stackcompli.env (root:www-data 0640) and returns a PDO singleton.
 */

function sc_load_env(): array {
    static $env = null;
    if ($env !== null) return $env;
    $env = [];
    $path = '/etc/stackcompli.env';
    if (!is_readable($path)) {
        throw new RuntimeException('stackcompli env file unreadable');
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line === '' || $line[0] === '#') continue;
        [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
        $env[trim($k)] = trim($v);
    }
    return $env;
}

function sc_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $e = sc_load_env();
    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        $e['STACKCOMPLI_DB_HOST'] ?? '127.0.0.1',
        $e['STACKCOMPLI_DB_PORT'] ?? '5432',
        $e['STACKCOMPLI_DB_NAME'] ?? 'stackcompli'
    );
    $pdo = new PDO($dsn, $e['STACKCOMPLI_DB_USER'], $e['STACKCOMPLI_DB_PASS'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

function sc_audit(string $action, array $meta = [], ?int $userId = null, ?int $orgId = null): void {
    try {
        $stmt = sc_db()->prepare(
            'INSERT INTO audit_log (org_id, actor_user_id, action, target_type, target_id, metadata, ip, user_agent)
             VALUES (:org, :user, :action, :ttype, :tid, :meta, :ip, :ua)'
        );
        $stmt->execute([
            ':org'    => $orgId,
            ':user'   => $userId,
            ':action' => $action,
            ':ttype'  => $meta['target_type'] ?? null,
            ':tid'    => $meta['target_id'] ?? null,
            ':meta'   => json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ':ip'     => $_SERVER['REMOTE_ADDR'] ?? null,
            ':ua'     => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 1000),
        ]);
    } catch (Throwable $e) {
        error_log('sc_audit failed: ' . $e->getMessage());
    }
}
