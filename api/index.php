<?php
/**
 * Main Entry Point for Oshens Groceries REST API
 * Handles CORS headers, global error handling, and route dispatching
 */

// Output buffering
ob_start();

// -------------------------------------------------------------
// 1. CORS & Response Headers
// -------------------------------------------------------------
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');
header('Access-Control-Max-Age: 86400');
header('Content-Type: application/json; charset=UTF-8');

// Handle HTTP OPTIONS Preflight request for mobile apps & web clients
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// -------------------------------------------------------------
// 2. Dependencies
// -------------------------------------------------------------
require_once __DIR__ . '/helpers/response.php';
require_once __DIR__ . '/config/database.php';

// -------------------------------------------------------------
// 3. Global Exception and Error Handlers (Always return JSON)
// -------------------------------------------------------------
set_exception_handler(function (Throwable $exception) {
    sendServerError('An unexpected error occurred: ' . $exception->getMessage(), [
        'file' => basename($exception->getFile()),
        'line' => $exception->getLine()
    ]);
});

set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) {
    // Don't report suppressed errors (@)
    if (!(error_reporting() & $errno)) {
        return false;
    }
    sendServerError("Server warning/error [{$errno}]: {$errstr}", [
        'file' => basename($errfile),
        'line' => $errline
    ]);
});

// -------------------------------------------------------------
// 4. Route Dispatch
// -------------------------------------------------------------
try {
    /** @var Router $router */
    $router = require_once __DIR__ . '/routes/api.php';

    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $requestUri    = $_SERVER['REQUEST_URI'] ?? '/';

    $router->dispatch($requestMethod, $requestUri);
} catch (Throwable $e) {
    sendServerError($e->getMessage());
}
