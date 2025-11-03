<?php
// backend/config.php
declare(strict_types=1);

// =============================
// URL dasar aplikasi
// =============================
define('BASE_URL', 'http://localhost/caffora-app1');

// =============================
// Koneksi MySQLi (utama, dipakai auth_guard dan frontend)
// =============================
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';       // sesuaikan XAMPP kamu
$db_name = 'caffora_db';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// =============================
// Tambahan supaya halaman lain tetap kompatibel
// =============================

// 1️⃣ Include helper agar fungsi h() tersedia di semua file
require_once __DIR__ . '/helpers.php';

// 2️⃣ Define konstanta DB agar API lain yang pakai konstanta tetap bisa konek
if (!defined('DB_HOST')) define('DB_HOST', $db_host);
if (!defined('DB_USER')) define('DB_USER', $db_user);
if (!defined('DB_PASS')) define('DB_PASS', $db_pass);
if (!defined('DB_NAME')) define('DB_NAME', $db_name);

// 3️⃣ Pastikan session sudah mulai (beberapa file asumsi pakai $_SESSION)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =============================
// Helper umum
// =============================

// redirect helper
if (!function_exists('redirect')) {
    function redirect(string $path): void {
        if (!str_starts_with($path, '/')) {
            $path = '/' . ltrim($path, '/');
        }
        header('Location: ' . BASE_URL . $path);
        exit;
    }
}

// JSON response helper (dipakai AJAX/OTP dll)
if (!function_exists('json_response')) {
    function json_response(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }
}
