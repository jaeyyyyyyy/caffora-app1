<?php
// Pembuka file PHP

// backend/db.php
// File helper untuk koneksi database dengan PDO

declare(strict_types=1);
// Aktifkan strict typing

require_once __DIR__ . '/config.php';
// Import config (berisi konstanta DB_HOST, DB_NAME, dll.)

if (!function_exists('db')) {
// Hanya definisikan fungsi db() jika belum ada

    function db(): PDO {
    // Fungsi global untuk mendapatkan instance PDO (singleton sederhana)

        static $pdo;
        // Static variabel untuk menyimpan koneksi PDO

        if ($pdo instanceof PDO) return $pdo;
        // Jika sudah pernah dibuat → langsung pakai yang lama

        $host = defined('DB_HOST') ? DB_HOST : 'localhost';
        // Host database, ambil dari konstanta DB_HOST, fallback localhost

        $name = defined('DB_NAME') ? DB_NAME : 'cafforam_db'; // hosting DB
        // Nama database, fallback 'cafforam_db' jika konstanta belum ada

        $user = defined('DB_USER') ? DB_USER : 'cafforam_dhyuncode';
        // Username database, fallback user hosting default

        $pass = defined('DB_PASS') ? DB_PASS : 'Uroh120202';
        // Password database, fallback password hosting default

        $port = getenv('DB_PORT') ?: '3306';
        // Port database, ambil dari env DB_PORT atau default 3306

        $dsn  = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        // DSN koneksi MySQL PDO dengan charset utf8mb4

        $opt  = [
        // Opsi konfigurasi PDO

            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // Jika error, PDO akan melempar exception

            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Hasil fetch default berupa array asosiatif

            PDO::ATTR_EMULATE_PREPARES   => false,
            // Gunakan prepared statement native, bukan emulasi
        ];

        try {
            $pdo = new PDO($dsn, $user, $pass, $opt);
            // Membuat instance PDO baru dengan DSN, user, pass, dan opsi
        } catch (PDOException $e) {
            die("DB Error (PDO): " . $e->getMessage());
            // Jika gagal koneksi → hentikan script dan tampilkan pesan
        }

        return $pdo;
        // Kembalikan instance PDO yang sudah dibuat
    }
    // Akhir fungsi db()
}
// Akhir blok if !function_exists('db')
