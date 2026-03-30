<?php
// ── Database Configuration ───────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'u386705044_newcab');       // apna MySQL username
define('DB_PASS', 'y&L00DhydsO>');           // apna MySQL password
define('DB_NAME', 'u386705044_cabb');
define('DB_CHARSET', 'utf8mb4');

// ── PDO Connection ───────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'DB Connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}
