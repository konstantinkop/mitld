<?php
/**
 * Pure PHP JWT (JSON Web Token) Implementation
 * Standard HS256 HMAC-SHA256 Token Provider
 * Zero external dependencies. Fully compatible with Node.js jsonwebtoken library.
 */

class JWT {
    public static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): string {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4 === 0 ? strlen($data) : strlen($data) + (4 - strlen($data) % 4), '=', STR_PAD_RIGHT));
    }

    public static function encode(array $payload, string $secret, int $expiry = 86400): string {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        
        $now = time();
        if (!isset($payload['iat'])) {
            $payload['iat'] = $now;
        }
        if (!isset($payload['exp']) && $expiry > 0) {
            $payload['exp'] = $now + $expiry;
        }

        $base64Header = self::base64UrlEncode($header);
        $base64Payload = self::base64UrlEncode(json_encode($payload));
        $signature = hash_hmac('sha256', "{$base64Header}.{$base64Payload}", $secret, true);
        $base64Signature = self::base64UrlEncode($signature);

        return "{$base64Header}.{$base64Payload}.{$base64Signature}";
    }

    public static function decode(string $token, string $secret): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$base64Header, $base64Payload, $base64Signature] = $parts;

        // Verify header
        $header = json_decode(self::base64UrlDecode($base64Header), true);
        if (!$header || ($header['alg'] ?? '') !== 'HS256') {
            return null;
        }

        // Verify signature
        $expectedSignature = hash_hmac('sha256', "{$base64Header}.{$base64Payload}", $secret, true);
        $actualSignature = self::base64UrlDecode($base64Signature);

        if (!hash_equals($expectedSignature, $actualSignature)) {
            return null;
        }

        // Verify payload and expiry
        $payload = json_decode(self::base64UrlDecode($base64Payload), true);
        if (!$payload) {
            return null;
        }

        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null; // Token expired
        }

        return $payload;
    }

    public static function getBearerToken(): ?string {
        $headers = '';
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
        } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        } elseif (!empty($_SERVER['Authorization'])) {
            $headers = trim($_SERVER['Authorization']);
        } elseif (function_exists('getallheaders')) {
            $all = getallheaders();
            foreach ($all as $k => $v) {
                if (strcasecmp($k, 'Authorization') === 0) {
                    $headers = trim($v);
                    break;
                }
            }
        } elseif (function_exists('apache_request_headers')) {
            $all = apache_request_headers();
            foreach ($all as $k => $v) {
                if (strcasecmp($k, 'Authorization') === 0) {
                    $headers = trim($v);
                    break;
                }
            }
        }

        if (!empty($headers) && preg_match('/Bearer\s+(\S+)/i', $headers, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public static function requireAuth(string $secret): array {
        $token = self::getBearerToken();
        if (!$token) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'No token']);
            exit;
        }

        $payload = self::decode($token, $secret);
        if (!$payload) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Invalid token']);
            exit;
        }

        return $payload;
    }
}
