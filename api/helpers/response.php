<?php
/**
 * JSON Response Helper
 * Provides standard JSON response formatting for Oshens Groceries REST API
 */

if (!function_exists('jsonResponse')) {
    /**
     * Send a standardized JSON response and exit
     *
     * @param int $status HTTP status code
     * @param string $message Descriptive message
     * @param mixed $data Payload data (optional)
     * @param mixed $errors Validation or error details (optional)
     * @return void
     */
    function jsonResponse(int $status, string $message, $data = null, $errors = null): void
    {
        // Clear any previous output buffering to ensure clean JSON
        if (ob_get_length()) {
            ob_clean();
        }

        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');

        $response = [
            'status'  => $status,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}

if (!function_exists('sendSuccess')) {
    /**
     * Send a 200 OK or custom success response
     */
    function sendSuccess(string $message = 'Success', $data = null, int $status = 200): void
    {
        jsonResponse($status, $message, $data);
    }
}

if (!function_exists('sendCreated')) {
    /**
     * Send a 201 Created response
     */
    function sendCreated(string $message = 'Resource created successfully', $data = null): void
    {
        jsonResponse(201, $message, $data);
    }
}

if (!function_exists('sendError')) {
    /**
     * Send a client error response (default 400 Bad Request)
     */
    function sendError(string $message = 'Bad Request', int $status = 400, $errors = null): void
    {
        jsonResponse($status, $message, null, $errors);
    }
}

if (!function_exists('sendNotFound')) {
    /**
     * Send a 404 Not Found response
     */
    function sendNotFound(string $message = 'Endpoint or resource not found'): void
    {
        jsonResponse(404, $message);
    }
}

if (!function_exists('sendUnauthorized')) {
    /**
     * Send a 401 Unauthorized response
     */
    function sendUnauthorized(string $message = 'Unauthorized access'): void
    {
        jsonResponse(401, $message);
    }
}

if (!function_exists('sendForbidden')) {
    /**
     * Send a 403 Forbidden response
     */
    function sendForbidden(string $message = 'Access forbidden'): void
    {
        jsonResponse(403, $message);
    }
}

if (!function_exists('sendMethodNotAllowed')) {
    /**
     * Send a 405 Method Not Allowed response
     */
    function sendMethodNotAllowed(string $message = 'HTTP method not allowed'): void
    {
        jsonResponse(405, $message);
    }
}

if (!function_exists('sendServerError')) {
    /**
     * Send a 500 Internal Server Error response
     */
    function sendServerError(string $message = 'Internal Server Error', $errors = null): void
    {
        jsonResponse(500, $message, null, $errors);
    }
}
