<?php
/**
 * Database Configuration for REST API
 * Reuses the existing connection from includes/db.php
 */

require_once __DIR__ . '/../helpers/response.php';

class Database
{
    private static ?PDO $pdo = null;

    /**
     * Get the PDO database connection instance
     *
     * @return PDO
     */
    public static function getConnection(): PDO
    {
        if (self::$pdo === null) {
            global $pdo;

            if ($pdo instanceof PDO) {
                self::$pdo = $pdo;
                return self::$pdo;
            }

            $dbFile = __DIR__ . '/../../includes/db.php';

            if (!file_exists($dbFile)) {
                sendServerError('Database configuration file not found.');
            }

            try {
                // Include the existing database connection file
                require_once $dbFile;

                /** @var PDO|null $instance */
                $instance = $pdo ?? ($GLOBALS['pdo'] ?? null);

                if (!($instance instanceof PDO)) {
                    // Fallback to direct PDO initialization if not set in scope
                    $servername = "localhost";
                    $username = "root";
                    $password = "";
                    $dbname = "oshens_gloceries";

                    $instance = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
                    $instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                }

                self::$pdo = $instance;
            } catch (PDOException $e) {
                sendServerError('Database connection error: ' . $e->getMessage());
            } catch (Throwable $e) {
                sendServerError('Database initialization error: ' . $e->getMessage());
            }
        }

        return self::$pdo;
    }
}
