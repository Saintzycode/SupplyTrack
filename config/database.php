<?php
/**
 * config/database.php
 * Returns a shared PDO connection (ERRMODE_EXCEPTION, prepared statements).
 */
require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            // Pin the connection collation to match the DB schema and views.
            // Without this, SET NAMES utf8mb4 uses MariaDB's default utf8mb4_general_ci,
            // while the views/table DDL use utf8mb4_unicode_ci -> error 1267
            // "Illegal mix of collations" when comparing literals against view columns.
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            // Keep MySQL time in sync with PHP (Asia/Manila)
            $pdo->exec("SET time_zone = '+08:00'");
        } catch (PDOException $e) {
            http_response_code(500);
            exit('Database connection failed. Check config/config.php and that MySQL is running.'
                . (ini_get('display_errors') ? '<br><small>' . htmlspecialchars($e->getMessage()) . '</small>' : ''));
        }
    }
    return $pdo;
}