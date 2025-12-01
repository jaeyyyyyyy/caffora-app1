<?php

// Pembuka file PHP

// backend/auth_status.php
// File untuk mengecek status login user (dipakai oleh frontend)

declare(strict_types=1);
// Aktifkan strict typing agar tipe data lebih ketat

session_start();
// Mulai atau lanjutkan sesi agar bisa baca $_SESSION

header('Content-Type: application/json');
// Set header respons sebagai JSON

$logged = isset($_SESSION['user_id']);
// Cek apakah user sudah login (ada user_id di session)

$role = $_SESSION['user_role'] ?? null;
// Ambil role user dari session (jika tidak ada → null)

echo json_encode([
    'logged_in' => $logged,
    // Kirim status login: true/false

    'role' => $role,
    // Kirim role user: admin/karyawan/customer/null
]);
// Encode array ke JSON dan kirim ke client
