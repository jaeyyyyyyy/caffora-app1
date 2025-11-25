<?php
// Pembuka file PHP

// backend/send_otp.php
// File backend untuk mengirim ulang / mengirim OTP verifikasi

declare(strict_types=1);
// Aktifkan strict typing agar tipe data lebih ketat

@ini_set('display_errors','0');
// Matikan tampilan error ke browser (supaya respons JSON rapi)

@ini_set('log_errors','1');
// Aktifkan pencatatan error ke log server

require_once __DIR__ . '/config.php';
// Import konfigurasi utama (BASE_URL, $conn, dsb.)

require_once __DIR__ . '/mailer.php';
// Import mailer, wajib ada fungsi sendOtpMail()

// Jika session belum aktif, mulai session
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
// Session dipakai untuk cooldown OTP per user

header('Content-Type: application/json; charset=utf-8');
// Semua respons dari file ini berupa JSON UTF-8

const RESEND_COOLDOWN = 300;
// Batas minimal jeda resend OTP: 300 detik (5 menit)

// ===== Helpers =====
function json_out(array $data, int $code = 200): void {
  // Helper untuk kirim JSON + set HTTP status

  http_response_code($code);
  // Set kode status HTTP

  echo json_encode(
    $data,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
  );
  // Encode array ke JSON, tidak escape unicode & slash

  exit;
  // Hentikan eksekusi script setelah kirim respons
}

function cooldown_key(string $email): string {
  // Buat key unik untuk cooldown di session berdasarkan email

  return 'otp_resend_ts_' . md5(strtolower($email));
  // Gunakan md5(lowercase email) supaya konsisten dan aman
}

// ===== Ambil email dari query / session =====
$email = trim((string)($_GET['email'] ?? ''));
// Ambil email dari query string (?email=)

if ($email === '' && !empty($_SESSION['pending_email'])) {
  // Jika belum ada di query, coba ambil dari session pending_email

  $email = (string)$_SESSION['pending_email'];
  // Pakai email pending dari session
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  // Kalau tetap kosong atau format email tidak valid → error

  json_out(
    ['ok' => false, 'error' => 'invalid_email'],
    400
  );
  // Kirim JSON error HTTP 400
}

// ===== Cek koneksi DB =====
if (!isset($conn) || !($conn instanceof mysqli)) {
  // Pastikan koneksi MySQLi siap digunakan

  error_log('SEND_OTP: $conn tidak siap');
  // Log error jika koneksi tidak siap

  json_out(
    ['ok' => false, 'error' => 'server'],
    500
  );
  // Kirim error server (500)
}

@$conn->set_charset('utf8mb4');
// Set charset koneksi database ke utf8mb4

// ===== Rate limit per session + email =====
$key = cooldown_key($email);
// Key session untuk menyimpan timestamp resend terakhir

$now = time();
// Waktu saat ini (detik sejak epoch)

if (
  isset($_SESSION[$key]) &&
  ($now - (int)$_SESSION[$key]) < RESEND_COOLDOWN
) {
  // Jika sudah pernah kirim dan belum lewat cooldown

  $wait = RESEND_COOLDOWN - ($now - (int)$_SESSION[$key]);
  // Hitung berapa detik lagi boleh request OTP

  json_out(
    [
      'ok'    => false,
      'error' => 'cooldown',
      'wait'  => $wait,
    ],
    429
  );
  // Kirim error 429 Too Many Requests + waktu tunggu
}

// ===== Ambil user pending =====
$stmt = $conn->prepare(
  'SELECT id, name, status, otp, otp_expires_at 
   FROM users 
   WHERE email=? 
   LIMIT 1'
);
// Siapkan query untuk ambil data user berdasarkan email

$stmt->bind_param('s', $email);
// Bind parameter email (tipe string)

$stmt->execute();
// Jalankan query

$res  = $stmt->get_result();
// Ambil hasil query

$user = $res ? $res->fetch_assoc() : null;
// Ambil satu baris data user, atau null jika tidak ada

$stmt->close();
// Tutup statement untuk membebaskan resource

if (!$user) {
  // Jika user tidak ditemukan di database

  json_out(
    ['ok' => false, 'error' => 'not_found'],
    404
  );
  // Kirim error 404 (email tidak terdaftar)
}

if (($user['status'] ?? '') !== 'pending') {
  // Jika status user bukan "pending" (sudah aktif atau status lain)

  json_out(
    ['ok' => false, 'error' => 'already_active'],
    409
  );
  // Kirim error 409 (konflik: akun sudah aktif)
}

// ===== Putuskan pakai OTP lama atau buat baru =====
$needNew  = false;
// Flag apakah perlu buat OTP baru

$otp      = (string)($user['otp'] ?? '');
// OTP yang tersimpan di kolom users.otp (jika ada)

$expiresS = (string)($user['otp_expires_at'] ?? '');
// Waktu kadaluarsa OTP yang tersimpan (string)

// Jika ada string waktu expire, buat objek DateTime, jika tidak null
$expiresAt = $expiresS
  ? new DateTime($expiresS)
  : null;
// DateTime expire atau null jika kosong

if (
  $otp === '' ||           // belum ada OTP
  !$expiresAt ||           // tidak punya tanggal kadaluarsa
  (new DateTime()) > $expiresAt  // sudah lewat waktu kadaluarsa
) {
  $needNew = true;
  // Maka kita perlu generate OTP baru
}

if ($needNew) {
  // Generate OTP baru jika diperlukan

  $otp = str_pad(
    (string)random_int(0, 999999),
    6,
    '0',
    STR_PAD_LEFT
  );
  // Buat OTP 6 digit dengan leading zero

  $newExpires = (new DateTime('+5 minutes'))
    ->format('Y-m-d H:i:s');
  // Set waktu kadaluarsa baru: 5 menit dari sekarang

  $stmt = $conn->prepare(
    'UPDATE users 
     SET otp=?, otp_expires_at=? 
     WHERE id=?'
  );
  // Siapkan query update OTP dan expire

  if (!$stmt) {
    // Jika prepare gagal

    error_log(
      'SEND_OTP: prepare update gagal: ' . $conn->error
    );
    // Log error detail

    json_out(
      ['ok' => false, 'error' => 'server'],
      500
    );
    // Kirim error server
  }

  $stmt->bind_param(
    'ssi',
    $otp,
    $newExpires,
    $user['id']
  );
  // Bind parameter: otp, expire, dan id user

  if (!$stmt->execute()) {
    // Jika gagal eksekusi update

    error_log(
      'SEND_OTP: execute update gagal: ' . $conn->error
    );
    // Log error

    $stmt->close();
    // Tutup statement

    json_out(
      ['ok' => false, 'error' => 'server'],
      500
    );
    // Kirim error server
  }

  $stmt->close();
  // Tutup statement jika sukses
}

// ===== Kirim email =====
$name = (string)($user['name'] ?? '');
// Ambil nama user (bisa kosong)

$sent = false;
// Flag status pengiriman email

try {
  $sent = sendOtpMail($email, $name, $otp);
  // Coba kirim email OTP ke user
} catch (Throwable $e) {
  // Jika terjadi exception saat kirim email

  error_log(
    'SEND_OTP: mailer exception: ' . $e->getMessage()
  );
  // Log pesan error mailer
}

if (!$sent) {
  // Jika pengiriman email gagal

  json_out(
    ['ok' => false, 'error' => 'mail_failed'],
    500
  );
  // Beritahu client bahwa pengiriman email gagal
}

// set timestamp cooldown setelah kirim sukses
$_SESSION[$key] = $now;
// Simpan waktu terakhir kirim OTP ke session (untuk rate limit)

// Sukses
json_out(
  ['ok' => true, 'message' => 'sent']
);
// Kirim respons sukses ke client dalam bentuk JSON
