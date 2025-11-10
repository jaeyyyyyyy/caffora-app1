<?php
// backend/register.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
// kalau mau pakai helper password_hash dsb sudah oke

// hanya mau terima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/public/register.html');
    exit;
}

// ambil data
$name     = trim($_POST['name'] ?? '');
$email    = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');
$confirm  = trim($_POST['confirm_password'] ?? '');

// validasi sangat basic
if ($name === '' || $email === '' || $password === '' || $password !== $confirm) {
    // bisa diarahkan balik ke register kalau mau
    header('Location: ' . BASE_URL . '/public/register.html?err=invalid');
    exit;
}

// cek email sudah ada?
$stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    // email sudah ada → suruh login
    header('Location: ' . BASE_URL . '/public/login.html?msg=exists');
    exit;
}
$stmt->close();

// generate OTP (disimpan di DB saja)
$otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires = (new DateTime('+5 minutes'))->format('Y-m-d H:i:s');

// hash password
$hash = password_hash($password, PASSWORD_DEFAULT);

// simpan user status pending
$stmt = $conn->prepare('
    INSERT INTO users (name, email, password, status, otp, otp_expires_at)
    VALUES (?, ?, ?, "pending", ?, ?)
');
$stmt->bind_param('sssss', $name, $email, $hash, $otp, $expires);
$stmt->execute();
$stmt->close();

// simpan juga di session supaya verify_otp bisa pakai
$_SESSION['pending_email'] = $email;

// ❗❗ PENTING: JANGAN kirim email di sini.
// BIARKAN /public/verify_otp.html yang memanggil ../backend/send_otp.php

// langsung redirect cepat
header('Location: ' . BASE_URL . '/public/verify_otp.html?email=' . urlencode($email));
exit;
