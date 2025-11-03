<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/* ==== helpers ==== */
function is_json_request(): bool {
  $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
  return stripos((string)$ct, 'application/json') !== false;
}
function json_out(array $payload, int $code = 200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}
function set_js_cookie(string $name, string $value, int $ttl): void {
  $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
  setcookie($name, $value, [
    'expires'  => time() + $ttl,
    'path'     => '/',
    'secure'   => $secure,
    'httponly' => false,
    'samesite' => 'Lax',
  ]);
}

/* ==== method guard ==== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  if (is_json_request()) json_out(['status'=>'error','message'=>'Method not allowed'],405);
  redirect('/public/login.html');
}

/* ==== ambil input (form/json) ==== */
$identity=''; $password=''; $remember=false;

if (is_json_request()) {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true) ?: [];
  $identity = trim((string)($data['email'] ?? $data['identity'] ?? ''));
  $password = trim((string)($data['password'] ?? ''));
  $remember = (bool)($data['remember'] ?? false);
} else {
  $identity = trim((string)($_POST['identity'] ?? $_POST['email'] ?? ''));
  $password = trim((string)($_POST['password'] ?? ''));
  $remember = !empty($_POST['remember']);
}

if ($identity === '' || $password === '') {
  if (is_json_request()) json_out(['status'=>'error','message'=>'Email/Username dan password wajib diisi.'],400);
  redirect('/public/login.html?err=' . urlencode('Email/Username dan password wajib diisi.'));
}

/* ==== cari user (email lalu fallback nama) – ambil kolom role juga ==== */
$stmt = $conn->prepare('SELECT id, name, email, password, status, role FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $identity);
$stmt->execute();
$res  = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

if (!$user) {
  $stmt = $conn->prepare('SELECT id, name, email, password, status, role FROM users WHERE name = ? LIMIT 1');
  $stmt->bind_param('s', $identity);
  $stmt->execute();
  $res  = $stmt->get_result();
  $user = $res->fetch_assoc();
  $stmt->close();
}

if (!$user) {
  if (is_json_request()) json_out(['status'=>'error','message'=>'Akun tidak ditemukan.'],404);
  redirect('/public/login.html?err=' . urlencode('Akun tidak ditemukan.'));
}

/* ==== status aktif? ==== */
if (($user['status'] ?? 'pending') !== 'active') {
  if (is_json_request()) {
    json_out(['status'=>'need_verification','message'=>'Akun belum aktif. Silakan verifikasi OTP.','email'=>$user['email']]);
  } else {
    redirect('/public/verify_otp.html?email=' . urlencode($user['email']));
  }
}

/* ==== verifikasi password ==== */
if (!password_verify($password, $user['password'])) {
  if (is_json_request()) json_out(['status'=>'error','message'=>'Password salah.'],401);
  redirect('/public/login.html?err=' . urlencode('Password salah.'));
}

/* ==== set session + cookie ==== */
$_SESSION['user_id']    = (int)$user['id'];
$_SESSION['user_name']  = $user['name'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_role']  = $user['role'];  // PENTING

$ttl = $remember ? 60*60*24*7 : 60*60*24;
set_js_cookie('caffora_auth', '1', $ttl);
set_js_cookie('caffora_uid', (string)$user['id'], $ttl);

/* ==== redirect sesuai role ==== */
$role = strtolower((string)$user['role']);
if (is_json_request()) {
  json_out([
    'status' => 'success',
    'user'   => [
      'id'    => (int)$user['id'],
      'name'  => $user['name'],
      'email' => $user['email'],
      'role'  => $role,
    ],
    'redirect' => $role === 'admin'
      ? '/public/admin/index.php'
      : ($role === 'karyawan'
          ? '/public/karyawan/index.php'
          : '/public/customer/index.php')
  ]);
} else {
  if ($role === 'admin') {
    redirect('/public/admin/index.php');
  } elseif ($role === 'karyawan') {
    redirect('/public/karyawan/index.php');
  } else {
    redirect('/public/customer/index.php');
  }
}