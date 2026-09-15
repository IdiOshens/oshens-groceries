<?php
/**
 * JWT (JSON Web Token) Helper
 * Implements HMAC-SHA256 (HS256) JWT generation, verification, and decoding.
 */

require_once __DIR__ . '/response.php';

class JWT
{
    // JWT Secret Key - In production, this can also be loaded from environment variables
    private const SECRET_KEY = 'oshens_groceries_jwt_secret_key_2026_campus_app_secure!';
    private const ALGORITHM  = 'HS256';
    private const EXPIRY     = 86400; // 24 hours in seconds

    /**
     * Get the JWT secret key
     */
    public static function getSecretKey(): string
    {
        return getenv('JWT_SECRET') ?: self::SECRET_KEY;
    }

    /**
     * Generate a new JWT token
     *
     * @param int $userId The unique user ID
     * @param string $email User email
     * @param array $additionalClaims Additional payload data (username, hostel, phone, etc.)
     * @return string Signed JWT token string
     */
    public static function generateToken(int $userId, string $email, array $additionalClaims = []): string
    {
        $issuedAt  = time();
        $expireAt  = $issuedAt + self::EXPIRY;
        $serverName = $_SERVER['SERVER_NAME'] ?? 'oshens_groceries_api';

        // JWT Header
        $header = [
            'typ' => 'JWT',
            'alg' => self::ALGORITHM
        ];

        // JWT Payload
        $payload = array_merge([
            'iss'  => $serverName,
            'aud'  => 'oshens_mobile_app',
            'iat'  => $issuedAt,
            'nbf'  => $issuedAt,
            'exp'  => $expireAt,
            'sub'  => (string) $userId,
            'user' => [
                'user_id' => $userId,
                'email'   => $email,
            ]
        ], $additionalClaims);

        // Encode Header & Payload
        $base64UrlHeader  = self::base64UrlEncode(json_encode($header));
        $base64UrlPayload = self::base64UrlEncode(json_encode($payload));

        // Create HMAC SHA256 Signature
        $signature = hash_hmac('sha256', $base64UrlHeader . '.' . $base64UrlPayload, self::getSecretKey(), true);
        $base64UrlSignature = self::base64UrlEncode($signature);

        // Return JWT
        return $base64UrlHeader . '.' . $base64UrlPayload . '.' . $base64UrlSignature;
    }

    /**
     * Verify and validate a JWT token
     *
     * @param string $token The JWT token to verify
     * @return array|null Returns decoded payload array on success, or null on failure
     */
    public static function verifyToken(string $token): ?array
    {
        $tokenParts = explode('.', trim($token));

        if (count($tokenParts) !== 3) {
            return null;
        }

        [$base64UrlHeader, $base64UrlPayload, $base64UrlSignature] = $tokenParts;

        // Decode Header
        $header = json_decode(self::base64UrlDecode($base64UrlHeader), true);
        if (!$header || ($header['alg'] ?? '') !== self::ALGORITHM) {
            return null;
        }

        // Verify Signature
        $expectedSignature = hash_hmac('sha256', $base64UrlHeader . '.' . $base64UrlPayload, self::getSecretKey(), true);
        $providedSignature = self::base64UrlDecode($base64UrlSignature);

        if (!hash_equals($expectedSignature, $providedSignature)) {
            return null;
        }

        // Decode Payload
        $payload = json_decode(self::base64UrlDecode($base64UrlPayload), true);
        if (!$payload || !is_array($payload)) {
            return null;
        }

        $currentTime = time();

        // Check if token is expired
        if (isset($payload['exp']) && $payload['exp'] < $currentTime) {
            return null;
        }

        // Check Not-Before (nbf)
        if (isset($payload['nbf']) && $payload['nbf'] > $currentTime) {
            return null;
        }

        return $payload;
    }

    /**
     * Decode a JWT token without signature verification
     *
     * @param string $token
     * @return array|null
     */
    public static function decodeToken(string $token): ?array
    {
        $tokenParts = explode('.', trim($token));

        if (count($tokenParts) !== 3) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($tokenParts[1]), true);
        return is_array($payload) ? $payload : null;
    }

    /**
     * Base64Url encode helper
     */
    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64Url decode helper
     */
    public static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
