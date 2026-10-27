<?php
/**
 * Global permanent audit log API
 * Protected by audit password only (X-Audit-Password) — no admin login required.
 * Workers without the Log password cannot open it.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../config/db.php';

function handleAudit(string $method, string $path, array $config): bool {
    if ($path !== '/api/admin/audit') {
        return false;
    }

    $expected = (string)($config['audit']['password'] ?? 'Hejhejhej123!');
    $given = '';
    if (!empty($_SERVER['HTTP_X_AUDIT_PASSWORD'])) {
        $given = (string)$_SERVER['HTTP_X_AUDIT_PASSWORD'];
    } elseif (isset($_GET['auditPassword'])) {
        $given = (string)$_GET['auditPassword'];
    }

    if ($given === '' || !hash_equals($expected, $given)) {
        sendError('Forkert adgangskode', 403);
    }

    $pdo = DB::getConnection();
    ensureAuditTable($pdo);

    // GET /api/admin/audit?limit=300&sessionId=optional
    if ($method === 'GET') {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 300;
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 1000) {
            $limit = 1000;
        }
        $sessionId = isset($_GET['sessionId']) ? trim((string)$_GET['sessionId']) : '';

        if ($sessionId !== '') {
            $stmt = $pdo->prepare("
                SELECT id, session_id, actor, event_type, detail, ip, created_at
                FROM audit_log
                WHERE session_id = ?
                ORDER BY id DESC
                LIMIT {$limit}
            ");
            $stmt->execute([$sessionId]);
        } else {
            $stmt = $pdo->query("
                SELECT id, session_id, actor, event_type, detail, ip, created_at
                FROM audit_log
                ORDER BY id DESC
                LIMIT {$limit}
            ");
        }

        $rows = $stmt ? $stmt->fetchAll() : [];
        $out = array_map(static function ($r) {
            return [
                'id' => (int)$r['id'],
                'sessionId' => $r['session_id'] ?? null,
                'actor' => $r['actor'],
                'eventType' => $r['event_type'],
                'detail' => $r['detail'],
                'ip' => $r['ip'],
                'createdAt' => toIso8601($r['created_at']),
            ];
        }, $rows);

        sendJson(['ok' => true, 'logs' => array_values($out)]);
        return true;
    }

    return false;
}
