<?php
/**
 * Admin Authentication Endpoints
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/../config/db.php';

function handleAdminAuth(string $method, string $path, array $config): bool {
    $pdo = DB::getConnection();
    $jwtSecret = $config['jwt']['secret'] ?? 'mitid_admin_secret_key_2026';
    $defaultAdminEmail = strtolower($config['admin']['default_email'] ?? 'admin@admin');
    $defaultAdminPass = $config['admin']['default_password'] ?? 'Admin123@@';

    // POST /api/admin/auth/login
    if ($method === 'POST' && $path === '/api/admin/auth/login') {
        $input = getJsonInput();
        $email = isset($input['email']) ? strtolower(trim((string)$input['email'])) : '';
        $password = isset($input['password']) ? (string)$input['password'] : '';

        if ($email === '' || $password === '') {
            sendError('Email and password required', 400);
        }

        // Find admin by email
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE LOWER(email) = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if (!$admin) {
            // Check if matching default admin credentials
            if ($email === $defaultAdminEmail && $password === $defaultAdminPass) {
                $hash = password_hash($defaultAdminPass, PASSWORD_BCRYPT);
                $ins = $pdo->prepare("INSERT INTO admins (email, password_hash) VALUES (?, ?)");
                $ins->execute([$defaultAdminEmail, $hash]);
                $token = JWT::encode(['email' => $defaultAdminEmail, 'role' => 'admin'], $jwtSecret, $config['jwt']['expiry'] ?? 86400);
                logEvent($pdo, null, 'admin_login', "Admin login: {$defaultAdminEmail}", 'admin');
                sendJson(['token' => $token, 'email' => $defaultAdminEmail]);
            } else {
                logEvent($pdo, null, 'admin_login_failed', "Failed login: {$email}", 'admin');
                sendError('Invalid credentials', 401);
            }
        }

        if (!password_verify($password, $admin['password_hash'])) {
            logEvent($pdo, null, 'admin_login_failed', "Failed login: {$email}", 'admin');
            sendError('Invalid credentials', 401);
        }

        $token = JWT::encode(['email' => $admin['email'], 'role' => 'admin'], $jwtSecret, $config['jwt']['expiry'] ?? 86400);
        logEvent($pdo, null, 'admin_login', "Admin login: {$admin['email']}", 'admin');
        sendJson(['token' => $token, 'email' => $admin['email']]);
        return true;
    }

    // GET /api/admin/auth/verify
    if ($method === 'GET' && $path === '/api/admin/auth/verify') {
        $payload = JWT::requireAuth($jwtSecret);
        sendJson(['valid' => true]);
        return true;
    }

    // POST /api/admin/auth/change-password
    if ($method === 'POST' && $path === '/api/admin/auth/change-password') {
        $adminUser = JWT::requireAuth($jwtSecret);
        $input = getJsonInput();

        $currentPassword = isset($input['currentPassword']) ? (string)$input['currentPassword'] : '';
        $newPassword = isset($input['newPassword']) ? (string)$input['newPassword'] : '';

        if ($currentPassword === '' || $newPassword === '') {
            sendError('Both passwords required', 400);
        }
        if (strlen($newPassword) < 4) {
            sendError('New password must be at least 4 characters', 400);
        }

        $adminEmail = $adminUser['email'] ?? $defaultAdminEmail;
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE LOWER(email) = ?");
        $stmt->execute([strtolower($adminEmail)]);
        $admin = $stmt->fetch();

        if (!$admin) {
            sendError('Admin not found', 404);
        }

        if (!password_verify($currentPassword, $admin['password_hash'])) {
            sendError('Current password is incorrect', 401);
        }

        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $upd = $pdo->prepare("UPDATE admins SET password_hash = ? WHERE id = ?");
        $upd->execute([$newHash, $admin['id']]);

        sendJson(['ok' => true, 'message' => 'Password updated successfully']);
        return true;
    }

    return false;
}
