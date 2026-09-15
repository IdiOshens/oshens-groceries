<?php
/**
 * Auth Middleware
 * Validates JWT Bearer tokens from incoming HTTP Authorization headers
 */

require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/jwt.php';
require_once __DIR__ . '/../config/database.php';

class AuthMiddleware
{
    /**
     * Extract the Bearer token from the incoming request headers
     */
    public static function getBearerToken(): ?string
    {
        $header = null;

        // Check common server variables for Authorization header
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $header = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            $header = $requestHeaders['Authorization'] ?? ($requestHeaders['authorization'] ?? null);
        } elseif (function_exists('getallheaders')) {
            $requestHeaders = getallheaders();
            $header = $requestHeaders['Authorization'] ?? ($requestHeaders['authorization'] ?? null);
        }

        if (empty($header)) {
            return null;
        }

        // Check for Bearer format: "Bearer <token>"
        if (preg_match('/Bearer\s(\S+)/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Authenticate the request. Halts and returns 401 Unauthorized if invalid.
     *
     * @return array Returns authenticated user data
     */
    public static function authenticate(): array
    {
        $token = self::getBearerToken();

        if (!$token) {
            sendUnauthorized('Authorization token is missing. Please provide a Bearer token in the Authorization header.');
        }

        $payload = JWT::verifyToken($token);

        if (!$payload) {
            sendUnauthorized('Token is invalid or has expired. Please login again.');
        }

        $userId = $payload['sub'] ?? ($payload['user']['user_id'] ?? null);

        if (!$userId) {
            sendUnauthorized('Invalid token structure: missing user identifier.');
        }

        // Fetch fresh user record from database
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("
                SELECT user_id, username, email, phone, hostel_name, created_at, updated_at
                FROM users
                WHERE user_id = ?
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user) {
                sendUnauthorized('User account associated with this token no longer exists.');
            }

            return $user;
        } catch (PDOException $e) {
            sendServerError('Database error while verifying credentials: ' . $e->getMessage());
        } catch (Throwable $e) {
            sendServerError('Authentication error: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Middleware handler for router pipeline
     */
    public static function handle(array &$request): array
    {
        $user = self::authenticate();
        $request['user'] = $user;
        return $user;
    }
}
