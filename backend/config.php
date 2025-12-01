<?php

// Pembuka file PHP

// backend/config.php
// File konfigurasi utama backend aplikasi Caffora

declare(strict_types=1);
// Aktifkan strict typing

/**
 * Konfigurasi inti:
 * - Deteksi prod vs lokal dari host
 * - BASE_URL dinamis (HTTPS-aware, proxy-aware)
 * - Koneksi MySQLi utf8mb4
 * - Session cookie secure
 * - Helper redirect() & json_response()
 */
@ini_set('display_errors', '0');
// Nonaktifkan error agar tidak tampil ke user

@ini_set('log_errors', '1');
// Aktifkan penulisan error ke log

/* =============================
 * Env & BASE_URL
 * ============================= */
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
// Ambil host saat ini (domain atau localhost)

$isProd = (stripos($host, 'caffora.my.id') !== false);
// Deteksi apakah sedang di server produksi

// deteksi HTTPS (termasuk jika di balik proxy/CDN)
function _is_https(): bool
{
    // Fungsi mengecek apakah request menggunakan HTTPS

    if (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        return true;
    }
    // Jika HTTPS=on => HTTPS aktif

    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        return true;
    }
    // Jika pakai proxy/CDN yang meneruskan header HTTPS

    return false;
    // Jika tidak memenuhi semuanya → bukan HTTPS
}

$scheme = _is_https() ? 'https' : 'http';
// Tentukan skema URL (http/https)

// BASE_URL: di prod selalu https://caffora.my.id
if ($isProd) {
    $baseUrl = 'https://caffora.my.id';
    // Pakai domain utama di hosting
} else {
    // lokal: sesuaikan subfolder proyek kamu (mis. /caffora-app1)
    $baseUrl = $scheme.'://'.$host.'/caffora-app1';
    // BASE_URL untuk environment lokal
}

if (! defined('BASE_URL')) {
    define('BASE_URL', rtrim($baseUrl, '/'));
    // Definisikan BASE_URL tanpa "/" di akhir
}

/* =============================
 * DB connection (MySQLi)
 * ============================= */
$db_host = 'localhost';
// Host database (biasanya localhost)

if ($isProd) {
    // === HOSTING (cPanel) ===
    $db_user = 'cafforam_dhyuncode'; // Username DB
    $db_pass = 'Uroh120202';          // Password DB
    $db_name = 'cafforam_db';         // Nama DB
} else {
    // === LOKAL (XAMPP) ===
    $db_user = 'root';      // user XAMPP
    $db_pass = '';          // password XAMPP default kosong
    $db_name = 'caffora_db'; // nama database lokal
}

// lempar exception untuk error mysqli (lebih mudah di-log)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
// Atur MySQLi agar melempar exception saat error

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    // Buat koneksi MySQLi baru

    $conn->set_charset('utf8mb4');
    // Set charset database ke utf8mb4
} catch (Throwable $e) {
    http_response_code(500);
    // Set status internal server error

    exit('Database connection failed.');
    // Jangan tampilkan detail error untuk keamanan
}

/* =============================
 * Kompatibilitas lama (opsional)
 * ============================= */
require_once __DIR__.'/helpers.php';
// Import file helper tambahan

if (! defined('DB_HOST')) {
    define('DB_HOST', $db_host);
}
// Define konstanta DB_HOST jika belum ada

if (! defined('DB_USER')) {
    define('DB_USER', $db_user);
}
// Define DB_USER

if (! defined('DB_PASS')) {
    define('DB_PASS', $db_pass);
}
// Define DB_PASS

if (! defined('DB_NAME')) {
    define('DB_NAME', $db_name);
}
// Define DB_NAME

/* =============================
 * Session (cookie secure)
 * ============================= */
if (session_status() === PHP_SESSION_NONE) {
    // Jika session belum dimulai

    // set cookie param aman sebelum session_start
    $cookieSecure = _is_https();
    // Gunakan secure cookie jika HTTPS

    session_set_cookie_params([
        'lifetime' => 0,      // session cookie (hapus jika browser ditutup)
        'path' => '/',    // berlaku untuk seluruh domain
        'domain' => '',     // default domain
        'secure' => $cookieSecure, // hanya terkirim via HTTPS
        'httponly' => true,   // cegah JS membaca cookie
        'samesite' => 'Lax',  // hindari CSRF dasar
    ]);

    session_start();
    // Mulai session dengan parameter aman
}

/* =============================
 * Helper: redirect()
 * ============================= */
if (! function_exists('redirect')) {
    function redirect(string $to): void
    {
        // Fungsi redirect aman

        // sanitasi sederhana: jangan izinkan skema tersisip di path
        if ($to !== '' && preg_match('~https?://~i', $to)) {
            // Jika URL absolut → redirect langsung
            header('Location: '.$to);
            exit;
        }

        $path = '/'.ltrim($to, '/');
        // Tambahkan slash di depan path relatif

        header('Location: '.BASE_URL.$path);
        // Redirect dengan BASE_URL

        exit;
        // Akhiri script
    }
}

/* =============================
 * Helper: json_response()
 * ============================= */
if (! function_exists('json_response')) {
    function json_response(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        // Set HTTP status

        header('Content-Type: application/json; charset=utf-8');
        // Set respons JSON

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        // Print JSON aman tanpa escape slash/unicode

        exit;
        // Stop script
    }
}
