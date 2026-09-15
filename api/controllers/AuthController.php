<?php
/**
 * Auth Controller
 * Handles user registration, login, token verification, logout, and profile
 */

require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/jwt.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';

class AuthController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    /**
     * User Login Endpoint
     * POST /api/auth/login
     *
     * @param array $data Request payload containing 'email' (or 'username') and 'password'
     */
    public function login(array $data): void
    {
        $identifier = trim($data['email'] ?? ($data['username'] ?? ''));
        $password   = $data['password'] ?? '';

        $errors = [];
        if (empty($identifier)) {
            $errors['email'] = 'Email or username is required.';
        }
        if (empty($password)) {
            $errors['password'] = 'Password is required.';
        }

        if (!empty($errors)) {
            sendError('Validation failed', 400, $errors);
        }

        try {
            // Find user by email or username
            $stmt = $this->pdo->prepare("
                SELECT user_id, username, email, phone, hostel_name, password, created_at
                FROM users
                WHERE email = ? OR username = ?
                LIMIT 1
            ");
            $stmt->execute([$identifier, $identifier]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password'])) {
                sendUnauthorized('Invalid email/username or password.');
            }

            // Remove password hash from memory
            unset($user['password']);

            // Generate JWT Token
            $token = JWT::generateToken((int) $user['user_id'], $user['email'], [
                'username'    => $user['username'],
                'phone'       => $user['phone'],
                'hostel_name' => $user['hostel_name']
            ]);

            sendSuccess('Login successful', [
                'token'      => $token,
                'token_type' => 'Bearer',
                'expires_in' => 86400,
                'user'       => $user
            ]);
        } catch (PDOException $e) {
            sendServerError('Database error during login: ' . $e->getMessage());
        }
    }

    /**
     * User Registration Endpoint
     * POST /api/auth/register
     *
     * @param array $data Request payload with username, email, password, phone, hostel_name
     */
    public function register(array $data): void
    {
        $username   = trim($data['username'] ?? '');
        $email      = trim($data['email'] ?? '');
        $password   = $data['password'] ?? '';
        $phone      = trim($data['phone'] ?? '');
        $hostelName = trim($data['hostel_name'] ?? '');

        $errors = [];

        // Validation
        if (empty($username)) {
            $errors['username'] = 'Username is required.';
        } elseif (strlen($username) < 3 || strlen($username) > 50) {
            $errors['username'] = 'Username must be between 3 and 50 characters.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors['username'] = 'Username can only contain alphanumeric characters and underscores.';
        }

        if (empty($email)) {
            $errors['email'] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please provide a valid email address.';
        }

        if (empty($password)) {
            $errors['password'] = 'Password is required.';
        } elseif (strlen($password) < 6) {
            $errors['password'] = 'Password must be at least 6 characters long.';
        }

        if (empty($phone)) {
            $errors['phone'] = 'Phone number is required.';
        }

        if (empty($hostelName)) {
            $errors['hostel_name'] = 'Hostel name is required for campus deliveries.';
        }

        if (!empty($errors)) {
            sendError('Validation failed', 400, $errors);
        }

        try {
            // Check if username or email already exists
            $checkStmt = $this->pdo->prepare("
                SELECT user_id, username, email 
                FROM users 
                WHERE username = ? OR email = ? 
                LIMIT 1
            ");
            $checkStmt->execute([$username, $email]);
            $existing = $checkStmt->fetch();

            if ($existing) {
                if (strcasecmp($existing['username'], $username) === 0) {
                    $errors['username'] = 'This username is already taken.';
                }
                if (strcasecmp($existing['email'], $email) === 0) {
                    $errors['email'] = 'An account with this email already exists.';
                }
                sendError('Account already exists with this username or email.', 400, $errors);
            }

            // Hash password securely
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

            // Insert new user
            $insertStmt = $this->pdo->prepare("
                INSERT INTO users (username, email, phone, hostel_name, password)
                VALUES (?, ?, ?, ?, ?)
            ");
            $insertStmt->execute([$username, $email, $phone, $hostelName, $hashedPassword]);

            $userId = (int) $this->pdo->lastInsertId();

            $newUser = [
                'user_id'     => $userId,
                'username'    => $username,
                'email'       => $email,
                'phone'       => $phone,
                'hostel_name' => $hostelName,
            ];

            // Generate JWT Token
            $token = JWT::generateToken($userId, $email, [
                'username'    => $username,
                'phone'       => $phone,
                'hostel_name' => $hostelName
            ]);

            sendCreated('User registered successfully', [
                'token'      => $token,
                'token_type' => 'Bearer',
                'expires_in' => 86400,
                'user'       => $newUser
            ]);
        } catch (PDOException $e) {
            sendServerError('Database error during registration: ' . $e->getMessage());
        }
    }

    /**
     * Verify Token Endpoint
     * GET /api/auth/verify
     *
     * @param string|null $token Optional token override
     */
    public function verify(?string $token = null): void
    {
        $token = $token ?: AuthMiddleware::getBearerToken();

        if (!$token) {
            sendUnauthorized('Token not provided. Pass Bearer token in Authorization header.');
        }

        $payload = JWT::verifyToken($token);

        if (!$payload) {
            sendUnauthorized('Token is invalid or has expired.');
        }

        $userId = $payload['sub'] ?? ($payload['user']['user_id'] ?? null);

        try {
            $stmt = $this->pdo->prepare("
                SELECT user_id, username, email, phone, hostel_name, created_at, updated_at
                FROM users
                WHERE user_id = ?
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user) {
                sendUnauthorized('User account no longer exists.');
            }

            sendSuccess('Token is valid', [
                'valid' => true,
                'user'  => $user
            ]);
        } catch (PDOException $e) {
            sendServerError('Database error verifying token: ' . $e->getMessage());
        }
    }

    /**
     * User Logout Endpoint
     * POST /api/auth/logout
     */
    public function logout(): void
    {
        sendSuccess('Logged out successfully. Please remove the token from your device.');
    }

    /**
     * Current User Profile Endpoint (Protected)
     * GET /api/auth/profile
     *
     * @param array|null $user Authenticated user data from middleware
     */
    public function profile(?array $user = null): void
    {
        if ($user === null) {
            $user = AuthMiddleware::authenticate();
        }

        sendSuccess('Profile retrieved successfully', [
            'user' => $user
        ]);
    }
}
