<?php
// Pembuka file PHP

// backend/login.php
// File proses login user (email/username + password)

declare(strict_types=1);
// Aktifkan strict typing

@ini_set('display_errors','0');
// Matikan tampilan error agar output JSON tetap bersih

@ini_set('log_errors','1');
// Aktifkan error logging ke server

require_once __DIR__.'/config.php';
// Import konfigurasi + koneksi database

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
// Mulai session jika belum aktif

/* ---------- Utils ---------- */
if (!function_exists('is_json_request')) {
// Cek apakah request berupa JSON/XHR

  function is_json_request(): bool {
    // Ambil Content-Type dari berbagai sumber
    $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';

    // Jika Content-Type mengandung 'application/json' atau XHR
    return stripos((string)$ct,'application/json') !== false
        || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
  }
}

if (!function_exists('json_out')) {
// Fungsi helper untuk output JSON dengan exit

  function json_out(array $payload, int $code=200): void {
    http_response_code($code); 
    // Set status HTTP

    header('Content-Type: application/json; charset=utf-8');
    // Set header JSON

    echo json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    // Encode JSON tanpa escape slash/unicode

    exit;
    // Hentikan eksekusi
  }
}

/* ---------- Method guard ---------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
// Hanya izinkan metode POST

  if (is_json_request()) 
    json_out(['status'=>'error','message'=>'Method not allowed'],405);
  // Jika JSON → kirim error 405

  redirect('/login');
  // Jika form normal, redirect ke /login
}

/* ---------- Input ---------- */
$identity = trim((string)($_POST['identity'] ?? $_POST['email'] ?? ''));
// Username atau email dari POST biasa

$password = trim((string)($_POST['password'] ?? ''));
// Password dari POST biasa

$remember = !empty($_POST['remember']);
// Checkbox remember-me

if (is_json_request()) {
// Jika JSON request, override input dengan body JSON

  $raw  = file_get_contents('php://input') ?: '';
  // Ambil raw JSON

  $data = json_decode($raw,true) ?: [];
  // Decode JSON menjadi array

  $identity = trim((string)($data['email'] ?? $data['identity'] ?? $identity));
  // Ambil email/identity dari JSON

  $password = trim((string)($data['password'] ?? $password));
  // Password dari JSON

  $remember = (bool)($data['remember'] ?? $remember);
  // Remember-me dari JSON
}

if ($identity === '' || $password === '') {
// Jika input kosong

  if (is_json_request()) 
    json_out(['status'=>'error','message'=>'Email/Username dan password wajib diisi.'],400);
  // Error JSON

  $_SESSION['login_error'] = 'Email/Username dan password wajib diisi.';
  // Simpan pesan error untuk redirect

  redirect('/login');
  // Arahkan kembali ke login
}

/* ---------- DB ---------- */
if (!isset($conn) || !($conn instanceof mysqli)) {
// Cek koneksi MySQLi tersedia

  error_log('LOGIN: $conn tidak siap');
  // Log error

  if (is_json_request()) 
    json_out(['status'=>'error','message'=>'Server database tidak siap.'],500);

  $_SESSION['login_error'] = 'Server database tidak siap.';
  redirect('/login');
}

@mysqli_report(MYSQLI_REPORT_OFF);
// Matikan laporan error bawaan mysqli

@$conn->set_charset('utf8mb4');
// Set charset database

/* ---------- Query user ---------- */
$stmt = $conn->prepare(
  'SELECT id,name,email,password,status,role 
   FROM users 
   WHERE email=? OR name=? 
   LIMIT 1'
);
// Query user berdasarkan email atau username

if (!$stmt) {
// Jika prepare gagal

  error_log('LOGIN: prepare gagal: '.$conn->error);
  // Log error

  if (is_json_request()) 
    json_out(['status'=>'error','message'=>'Login sementara tidak tersedia.'],500);

  $_SESSION['login_error'] = 'Login sementara tidak tersedia.';
  redirect('/login');
}

$stmt->bind_param('ss',$identity,$identity);
// Bind parameter identity sebagai email atau nama

$stmt->execute();
// Jalankan query

$res  = $stmt->get_result();
// Ambil hasil

$user = $res? $res->fetch_assoc() : null;
// Ambil satu baris user

$stmt->close();
// Tutup statement

if (!$user) {
// Jika user tidak ditemukan

  if (is_json_request()) 
    json_out(['status'=>'error','message'=>'Akun tidak ditemukan.'],404);

  $_SESSION['login_error'] = 'Akun tidak ditemukan.';
  redirect('/login');
}

/* ---------- Status ---------- */
if (($user['status'] ?? 'pending') !== 'active') {
// Jika user belum aktif (belum verifikasi OTP)

  if (is_json_request()) {
    json_out([
      'status'=>'need_verification',
      'message'=>'Akun belum aktif. Silakan verifikasi OTP.',
      'email'=>(string)$user['email']
    ]);
  }

  redirect('/verify_otp?email='.urlencode((string)$user['email']));
  // Redirect ke halaman OTP
}

/* ---------- Password ---------- */
if (!password_verify($password,(string)$user['password'])) {
// Jika password salah

  if (is_json_request()) 
    json_out(['status'=>'error','message'=>'Password salah.'],401);

  $_SESSION['login_error'] = 'Password salah.';
  redirect('/login');
}

/* ---------- Session & cookie ---------- */
session_regenerate_id(true);
// Regenerasi session ID untuk keamanan

$_SESSION['user_id']    = (int)$user['id'];
// Simpan id user ke session

$_SESSION['user_name']  = (string)$user['name'];
// Simpan nama user

$_SESSION['user_email'] = (string)$user['email'];
// Simpan email user

$_SESSION['user_role']  = strtolower((string)$user['role']);
// Simpan role user lowercase

$ttl = $remember ? 60*60*24*7 : 60*60*24;
// Tentukan TTL cookie (1 hari atau 7 hari)

// cookie indikator ringan (opsional)
setcookie('caffora_auth','1',[
  'expires'=> time()+$ttl,
  'path'=>'/',
  'secure'=>true,
  'httponly'=>false,
  'samesite'=>'Lax'
]);
// Simpan cookie indikator login

setcookie('caffora_uid',(string)$user['id'],[
  'expires'=> time()+$ttl,
  'path'=>'/',
  'secure'=>true,
  'httponly'=>false,
  'samesite'=>'Lax'
]);
// Simpan cookie user ID

/* ---------- Redirect by role ---------- */
$role   = strtolower((string)$user['role']);
// Normalisasi role menjadi lowercase

$target = $role==='admin'
  ? '/admin'
  : ($role==='karyawan'
      ? '/karyawan'
      : '/customer');
// Tentukan halaman redirect sesuai role

if (is_json_request()) {
// Jika AJAX login

  json_out([
    'status'=>'success',
    'redirect'=>$target,
    'user'=>[
      'id'=>(int)$user['id'],
      'name'=>(string)$user['name'],
      'email'=>(string)$user['email'],
      'role'=>$role
    ]
  ]);
}
// Jika bukan JSON → redirect normal
redirect($target);
