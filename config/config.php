<?php
/**
 * config/config.php
 * Central settings. Edit the DB values to match your XAMPP setup.
 */

// ---- Database ----
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'inventory_db');
define('DB_USER', 'root');      // XAMPP default
define('DB_PASS', '');          // XAMPP default is empty

// ---- App ----
define('APP_NAME', 'SchoolSupply');
define('APP_SUBTITLE', 'Inventory System');
define('BASE_URL', '/InventorySystem');   // folder name inside htdocs (no trailing slash)
define('CURRENCY', '₱');   // PHP sign
define('ASSET_VER', '37');   // bump (2, 3, ...) whenever CSS changes to bust browser cache

// ---- Security ----
define('MAX_FAILED_ATTEMPTS', 5);     // FR-1.4
define('LOCKOUT_MINUTES', 15);        // configurable lock period
define('SESSION_TIMEOUT_MINUTES', 30); // FR-1.7 inactivity logout
define('FORCE_HTTPS', false);         // set true in production (NFR-3.4)

date_default_timezone_set('Asia/Manila');

// Show errors while developing (local); must be 0 in production
ini_set('display_errors', '1');
error_reporting(E_ALL);
