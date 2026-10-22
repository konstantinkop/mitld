<?php
/**
 * API Response and CORS Helpers
 */

date_default_timezone_set('UTC');

function handleCors(): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
    header("Access-Control-Allow-Origin: {$origin}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Max-Age: 86400');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

function sendJson($data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function sendError(string $message, int $statusCode = 400): void {
    sendJson(['error' => $message], $statusCode);
}

function getJsonInput(): array {
    $raw = file_get_contents('php://input');
    if (empty($raw) && (php_sapi_name() === 'cli' || defined('STDIN'))) {
        $raw = @stream_get_contents(STDIN);
    }
    if (empty($raw)) {
        return !empty($_POST) ? $_POST : [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : (!empty($_POST) ? $_POST : []);
}

/**
 * Format datetime string to UTC ISO 8601 string (matching Node.js Date serialization: YYYY-MM-DDTHH:mm:ss.000Z)
 */
function toIso8601(?string $dateStr): ?string {
    if (!$dateStr) {
        return null;
    }
    if (strpos($dateStr, 'T') !== false && substr($dateStr, -1) === 'Z') {
        return $dateStr;
    }
    $time = strtotime($dateStr);
    if ($time === false) {
        return $dateStr;
    }
    return gmdate('Y-m-d\TH:i:s.000\Z', $time);
}

/**
 * Format session record for JSON output matching Node.js structure
 * Single session returns 10 keys; list endpoint returns 12 keys (with logCount & verifiedAt)
 */
function formatSession(array $s, bool $isList = false): array {
    $res = [
        'sessionId' => (string)$s['session_id'],
        'userId' => (string)$s['user_id'],
        'status' => (string)$s['status'],
        'qrVisible' => (bool)$s['qr_visible'],
        'qrImage' => $s['qr_image'] ?? null,
        'smsActive' => (bool)$s['sms_active'],
        'otpActive' => (bool)$s['otp_active'],
        'kodeActive' => (bool)$s['kode_active'],
        'verificationCode' => $s['verification_code'] ?? null,
        'createdAt' => toIso8601($s['created_at']),
    ];
    if ($isList) {
        $res['logCount'] = (int)($s['log_count'] ?? 0);
        $res['verifiedAt'] = toIso8601($s['verified_at'] ?? null);
    }
    return $res;
}
