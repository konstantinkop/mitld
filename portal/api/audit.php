<?php
/**
 * Global permanent audit log API
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/../config/db.php';

function handleAudit(string $method, string $path, array $config): bool {
    if ($path !== '/api/admin/audit') {
        return false;
    }

    $jwtSecret = $config['jwt']['secret'] ?? 'mitid_admin_secret_key_2026';
    JWT::requireAuth($jwtSecret);

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
