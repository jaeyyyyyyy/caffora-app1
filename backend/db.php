<?php
// backend/db.php
declare(strict_types=1);

require_once __DIR__ . '/config.php'; // ambil DB_HOST, DB_USER, DB_PASS, DB_NAME

if (!function_exists('db')) {
    function db(): PDO {
        static $pdo;
        if ($pdo instanceof PDO) return $pdo;

        $host = defined('DB_HOST') ? DB_HOST : 'localhost';
        $name = defined('DB_NAME') ? DB_NAME : 'caffora_db';
        $user = defined('DB_USER') ? DB_USER : 'root';
        $pass = defined('DB_PASS') ? DB_PASS : '';
        $port = getenv('DB_PORT') ?: '3306';

        $dsn  = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        $opt  = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, $user, $pass, $opt);
        return $pdo;
    }
}
