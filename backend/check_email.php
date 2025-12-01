<?php

// Pembuka file PHP

// /backend/check_email.php
// File backend untuk mengecek apakah email sudah terdaftar

declare(strict_types=1);
// Aktifkan strict typing

require_once __DIR__.'/config.php';
// Import konfigurasi (koneksi $conn)

header('Content-Type: application/json; charset=utf-8');
// Set header respons sebagai JSON UTF-8

// terima email via GET/POST
$email = trim((string) ($_GET['email'] ?? $_POST['email'] ?? ''));
// Ambil email dari GET atau POST lalu trim

$exists = false;
// Default: email dianggap belum ada

if (
    $email !== '' &&
    // Pastikan email tidak kosong

    filter_var($email, FILTER_VALIDATE_EMAIL) &&
    // Validasi format email

    isset($conn) &&
    $conn instanceof mysqli
    // Pastikan koneksi database valid (mysqli)
) {

    @$conn->set_charset('utf8mb4');
    // Set charset UTF-8 MB4 (ignore warning dengan @)

    // 1) cek di tabel users
    if ($stmt = $conn->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1')) {
        // Prepare query cek email di users

        $stmt->bind_param('s', $email);
        // Bind parameter

        $stmt->execute();
        // Jalankan query

        $stmt->store_result();
        // Simpan hasil agar bisa cek jumlah baris

        $exists = $stmt->num_rows > 0;
        // Jika ada baris → email sudah ada

        $stmt->close();
        // Tutup statement
    }

    // 2) cek di pre_registrations jika belum ditemukan di users
    if (
        ! $exists &&
        // Hanya cek jika belum ditemukan di users

        ($stmt = $conn->prepare(
            'SELECT 1 
       FROM pre_registrations 
       WHERE email = ? 
         AND (otp_expires_at IS NULL OR otp_expires_at > NOW()) 
       LIMIT 1'
        ))
    ) {
        // Query cek email di pre_registrations yang masih valid

        $stmt->bind_param('s', $email);
        // Bind email

        $stmt->execute();
        // Eksekusi query

        $stmt->store_result();
        // Ambil jumlah baris

        $exists = $stmt->num_rows > 0;
        // Jika ada → email sedang proses registrasi

        $stmt->close();
        // Tutup statement
    }
}

// Output JSON
echo json_encode(
    ['exists' => $exists],
    JSON_UNESCAPED_UNICODE
);
// Kirim hasil: exists = true/false dalam format JSON
