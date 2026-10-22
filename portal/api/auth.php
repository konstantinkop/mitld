<?php
/**
 * Client Auth Endpoints
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../config/db.php';

function handleClientAuth(string $method, string $path): bool {
    try {
        $pdo = DB::getConnection();
    } catch (Throwable $dbErr) {
        sendError("Database connection failed: " . $dbErr->getMessage(), 500);
    }

    // POST /api/auth/init
    if ($method === 'POST' && $path === '/api/auth/init') {
        $input = getJsonInput();
        $userId = isset($input['userId']) ? trim((string)$input['userId']) : '';
        if ($userId === '') {
            sendError('userId required', 400);
        }

        $sessionId = bin2hex(random_bytes(32));
        $verificationCode = (string)random_int(100000, 999999);

        $stmt = $pdo->prepare("INSERT INTO sessions (session_id, user_id, verification_code) VALUES (?, ?, ?)");
        $stmt->execute([$sessionId, $userId, $verificationCode]);

        $logStmt = $pdo->prepare("INSERT INTO auth_log (session_id, event_type, detail) VALUES (?, 'session_created', ?)");
        $logStmt->execute([$sessionId, "User {$userId} initiated login"]);

        sendJson(['sessionId' => $sessionId, 'userId' => $userId]);
        return true;
    }

    // GET /api/auth/session/{sessionId}
    if ($method === 'GET' && preg_match('#^/api/auth/session/([^/]+)$#', $path, $matches)) {
        $sessionId = $matches[1];
        $stmt = $pdo->prepare("SELECT * FROM sessions WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch();

        if (!$session) {
            sendError('Not found', 404);
        }

        sendJson(formatSession($session, false));
        return true;
    }

    // POST /api/auth/verify/{sessionId}
    if ($method === 'POST' && preg_match('#^/api/auth/verify/([^/]+)$#', $path, $matches)) {
        $sessionId = $matches[1];
        
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("UPDATE sessions SET status = 'verified', verified_at = ?, updated_at = ? WHERE session_id = ? AND status = 'pending'");
        $stmt->execute([$now, $now, $sessionId]);

        if ($stmt->rowCount() === 0) {
            sendError('Not found or already verified', 400);
        }

        $logStmt = $pdo->prepare("INSERT INTO auth_log (session_id, event_type, detail) VALUES (?, 'verified', 'QR code scanned')");
        $logStmt->execute([$sessionId]);

        // Push real-time messages to both admin and user
        $msgAdmin = json_encode(['type' => 'user-verified']);
        $msgUser = json_encode(['type' => 'verified']);
        $pdo->prepare("INSERT INTO ws_messages (session_id, sender_role, target_role, message) VALUES (?, 'user', 'admin', ?)")->execute([$sessionId, $msgAdmin]);
        $pdo->prepare("INSERT INTO ws_messages (session_id, sender_role, target_role, message) VALUES (?, 'user', 'user', ?)")->execute([$sessionId, $msgUser]);

        sendJson(['status' => 'verified']);
        return true;
    }

    return false;
}
