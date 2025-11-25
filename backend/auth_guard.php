<?php
// backend/auth_guard.php

// Aktifkan strict typing agar tipe data lebih ketat
declare(strict_types=1);

// Import file config (berisi BASE_URL, $conn, fungsi redirect(), dll.)
require_once __DIR__ . '/config.php'; // BASE_URL, $conn, redirect()

// Jika session belum aktif, maka mulai session baru
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start(); // start session
}

/**
 * Normalisasi role secara ketat hanya ke 3 nilai:
 * - admin
 * - karyawan
 * - customer
 * Selain itu otomatis dianggap "customer".
 */
function cf_role_strict(?string $raw): string
{
  // Ubah nilai role mentah ke huruf kecil + buang spasi di kiri/kanan
  $r = strtolower(
    trim($raw ?? '')
  );

  // Jika persis "admin" → kembalikan "admin"
  if ($r === 'admin') {
    return 'admin';
  }

  // Jika persis "karyawan" → kembalikan "karyawan"
  if ($r === 'karyawan') {
    return 'karyawan';
  }

  // Selain itu dianggap sebagai "customer"
  return 'customer';
}

/**
 * Pastikan user sudah login (boleh juga membatasi ke role tertentu).
 * @param array $allowedRoles contoh: ['customer'] atau ['admin','karyawan']
 * @return array data user (id,name,email,status,role,avatar,phone)
 */
function require_login(array $allowedRoles = []) : array
{
  // Gunakan koneksi database global
  global $conn;

  // 1) Jika belum login → arahkan ke halaman login
  if (empty($_SESSION['user_id'])) {
    // Redirect ke login dengan pesan error di query string
    redirect(
      '/public/login.html?err=' .
      urlencode('Silakan login dulu.')
    );
  }

  // Ambil user_id dari session dan paksa menjadi integer
  $userId = (int) $_SESSION['user_id'];

  // 2) Ambil data user dari database berdasarkan id
  $stmt = $conn->prepare(
    'SELECT id, name, email, status, role, avatar, phone 
     FROM users 
     WHERE id=? 
     LIMIT 1'
  );

  // Jika prepare gagal → kirim HTTP 500 lalu hentikan script
  if (!$stmt) {
    http_response_code(500);               // set status 500 internal server error
    exit('Database prepare failed.');      // pesan sederhana lalu exit
  }

  // Bind parameter id user ke statement
  $stmt->bind_param(
    'i',          // tipe parameter: integer
    $userId       // nilai parameter
  );

  // Jalankan query
  $stmt->execute();

  // Ambil hasil query dalam bentuk objek result
  $res = $stmt->get_result();

  // Ambil satu baris data user sebagai array asosiatif
  $currentUser = $res->fetch_assoc();

  // Tutup prepared statement
  $stmt->close();

  // 3) Jika user tidak ditemukan → hapus session dan paksa login ulang
  if (!$currentUser) {
    session_unset();   // hapus semua data session
    session_destroy(); // hancurkan session
    redirect(
      '/public/login.html?err=' .
      urlencode('Sesi berakhir. Silakan login ulang.')
    );
  }

  // 4) Jika status user belum "active" → arahkan ke halaman verifikasi OTP
  if (($currentUser['status'] ?? 'pending') !== 'active') {
    redirect(
      '/public/verify_otp.html?email=' .
      urlencode((string) $currentUser['email'])
    );
  }

  // 5) Normalisasi role user menjadi salah satu dari 3 role ketat
  $normRole = cf_role_strict(
    $currentUser['role'] ?? 'customer'
  );

  // Simpan kembali data penting ke session agar konsisten dipakai frontend
  $_SESSION['user_name']   = (string) $currentUser['name'];        // nama user
  $_SESSION['user_email']  = (string) $currentUser['email'];       // email user
  $_SESSION['user_role']   = $normRole;                            // role normal
  $_SESSION['user_phone']  = (string) ($currentUser['phone']  ?? '');   // no HP
  $_SESSION['user_avatar'] = (string) ($currentUser['avatar'] ?? '');   // avatar

  // 6) Jika fungsi dipanggil dengan batasan role tertentu
  if ($allowedRoles) {
    // Normalisasi semua role yang diizinkan ke 3 role ketat tadi
    $allowedStrict = array_map(
      'cf_role_strict',
      $allowedRoles
    );

    // Jika role user TIDAK terdapat di daftar role yang diizinkan
    if (!in_array($normRole, $allowedStrict, true)) {
      // Arahkan user ke dashboard sesuai role aktualnya
      if ($normRole === 'admin') {
        redirect('/public/admin/index.php');       // dashboard admin
      } elseif ($normRole === 'karyawan') {
        redirect('/public/karyawan/index.php');    // dashboard karyawan
      } else {
        redirect('/public/customer/index.php');    // dashboard customer
      }
    }
  }

  // Tambahkan field role yang sudah dinormalisasi ke array user
  $currentUser['role'] = $normRole;

  // Kembalikan seluruh data user ke pemanggil
  return $currentUser;
}

/**
 * Helper untuk halaman yang hanya boleh diakses satu role tertentu.
 */
function require_role(string $role) : array
{
  // Panggil require_login dengan array berisi satu role yang diizinkan
  return require_login([$role]);
}
