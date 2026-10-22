<?php
/**
 * Central API Gateway & Dispatcher
 * Handles all REST and polling endpoints for the MitID application.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/admin_sessions.php';
require_once __DIR__ . '/ws.php';

// Handle CORS preflight & headers
handleCors();

$config = require __DIR__ . '/../config/config.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Determine the clean route path
$route = '';
if (!empty($_GET['__route__'])) {
    $route = '/api/' . ltrim($_GET['__route__'], '/');
} else {
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH);
    
    // Find where '/api' starts
    $apiPos = strpos($path, '/api');
    if ($apiPos !== false) {
        $route = substr($path, $apiPos);
    } else {
        $route = '/' . ltrim($path, '/');
    }
}

// Remove trailing slash except for root
if (strlen($route) > 4 && substr($route, -1) === '/') {
    $route = rtrim($route, '/');
}

// Health check
if ($method === 'GET' && $route === '/api/health') {
    sendJson(['ok' => true]);
}

// Route handlers
try {
    if (handleClientAuth($method, $route)) {
        exit;
    }

    if (handleAdminAuth($method, $route, $config)) {
        exit;
    }

    if (handleAdminSessions($method, $route, $config)) {
        exit;
    }

    if (handleWsPolling($method, $route)) {
        exit;
    }

    // Endpoint not recognized
    sendError("Endpoint not found: {$method} {$route}", 404);
} catch (PDOException $e) {
    error_log("Database Error in API: " . $e->getMessage());
    sendError("Database error: " . $e->getMessage(), 500);
} catch (Throwable $t) {
    error_log("Internal Error in API: " . $t->getMessage());
    sendError("Server error: " . $t->getMessage(), 500);
}
