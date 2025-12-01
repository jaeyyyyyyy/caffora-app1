<?php

// Pembuka file PHP
// /backend/auth_register.php
// Keterangan lokasi file

declare(strict_types=1);
// Aktifkan strict typing

require_once __DIR__.'/config.php';
// Import configurasi, DB, BASE_URL

require_once __DIR__.'/mailer.php';
// Import fungsi mailer (pengiriman OTP)

@ini_set('display_errors', '0');
// Matikan tampilan error ke browser

@ini_set('log_errors', '1');
// Aktifkan logging error ke file log

function json_out(array $d, int $s = 200): void
{
    // Fungsi helper untuk output JSON dan exit

    http_response_code($s);
    // Set status HTTP

    header('Content-Type: application/json; charset=UTF-8');
    // Set header JSON UTF-8

    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    // Encode data JSON tanpa escape unicode

    exit;
    // Hentikan eksekusi script
}

$isAjax = (
    // Deteksi apakah request adalah AJAX / JSON

    (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
     // Cek apakah header X-Requested-With ada

     strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
     // Cek apakah bernilai "xmlhttprequest"

    ||
    // Atau

    (isset($_SERVER['HTTP_ACCEPT']) &&
     // Cek header Accept

     str_contains(strtolower($_SERVER['HTTP_ACCEPT']), 'json'))
    // Jika Accept berisi JSON
);

// Ambil nama dari POST
$name = trim((string) ($_POST['name'] ?? ''));
// Trim & tipe string

$email = trim((string) ($_POST['email'] ?? ''));
// Ambil email dari POST dan trim

$pass = (string) ($_POST['password'] ?? '');
// Ambil password sebagai string

/* ===== Validasi dasar (selaras frontend) ===== */
// Bagian validasi form register

if (! preg_match('/^[A-Za-z]{5,}$/', $name)) {
    // Validasi nama menggunakan regex minimal 5 huruf

    $payload = [
        'ok' => false,
        'field' => 'name',
        'code' => 'username_invalid',
        'message' => 'Minimal 5 huruf (A–Z).',
    ];
    // Payload error untuk frontend

    $isAjax ? json_out($payload, 422) : header('Location: /public/register.html');
    // Jika AJAX → JSON | Jika tidak → Redirect

    exit;
    // Stop eksekusi
}

if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
    // Validasi email menggunakan filter bawaan PHP

    $payload = [
        'ok' => false,
        'field' => 'email',
        'code' => 'email_invalid',
        'message' => 'Format email tidak valid.',
    ];
    // Payload error invalid email

    $isAjax ? json_out($payload, 422) : header('Location: /public/register.html');
    // AJAX atau redirect

    exit;
    // Stop
}

if (strlen($pass) < 6) {
    // Validasi minimal panjang password 6 karakter

    $payload = [
        'ok' => false,
        'field' => 'password',
        'code' => 'password_short',
        'message' => 'Minimal 6 karakter.',
    ];
    // Payload error password pendek

    $isAjax ? json_out($payload, 422) : header('Location: /public/register.html');
    // Kirim error atau redirect

    exit;
    // Stop eksekusi
}

/* ===== Tolak jika email sudah ada di users ===== */
// Cek apakah email sudah terdaftar

if ($stmt = $conn->prepare('SELECT 1 FROM users WHERE email=? LIMIT 1')) {
    // Siapkan query cek email

    $stmt->bind_param('s', $email);
    // Bind parameter email

    $stmt->execute();
    // Eksekusi query

    $stmt->store_result();
    // Simpan hasil untuk cek jumlah baris

    if ($stmt->num_rows > 0) {
        // Jika email sudah ada

        $stmt->close();
        // Tutup statement

        $payload = [
            'ok' => false,
            'field' => 'email',
            'code' => 'email_exists',
            'message' => 'Email tersebut sudah terdaftar. Silakan login.',
        ];
        // Payload email duplikat

        $isAjax ? json_out($payload, 409) : header('Location: /public/login.html');
        // Kirim JSON atau redirect

        exit;
        // Stop eksekusi
    }

    $stmt->close();
    // Tutup statement

} else {
    // Jika prepare gagal

    $isAjax
      ? json_out(['ok' => false, 'code' => 'server_prepare_fail', 'message' => 'Gangguan server.'], 500)
      : header('Location: /public/register.html');
    // Error 500 server

    exit;
}

/* ===== Bersihkan pre_registrations lama ===== */
// Opsional tapi bagus → hapus data OTP sebelumnya

if ($stmt = $conn->prepare('DELETE FROM pre_registrations WHERE email=?')) {
    // Prepare delete

    $stmt->bind_param('s', $email);
    // Bind email

    $stmt->execute();
    // Hapus data

    $stmt->close();
    // Tutup statement
}

/* ===== Buat OTP & simpan ke pre_registrations ===== */
// Generate OTP dan simpan

$otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
// OTP 6 digit selalu (000000 – 999999)

$exp = (new DateTime('+5 minutes'))->format('Y-m-d H:i:s');
// Waktu kadaluarsa OTP

$hash = password_hash($pass, PASSWORD_DEFAULT);
// Hash password untuk disimpan sementara sebelum verifikasi

if ($stmt = $conn->prepare('INSERT INTO pre_registrations (name,email,password_hash,otp,otp_expires_at,otp_sent_at) VALUES (?,?,?,?,?,NULL)')) {
    // Prepare INSERT data pre-registrasi

    $stmt->bind_param('sssss', $name, $email, $hash, $otp, $exp);
    // Bind parameter insert

    if (! $stmt->execute()) {
        // Jika gagal simpan

        $stmt->close();
        // Tutup

        $payload = ['ok' => false, 'code' => 'persist_error', 'message' => 'Gagal menyimpan data.'];
        // Payload error

        $isAjax ? json_out($payload, 500) : header('Location: /public/register.html');
        // JSON error atau redirect

        exit;
    }

    $stmt->close();
    // Tutup statement

} else {
    // Gagal prepare INSERT

    $isAjax
      ? json_out(['ok' => false, 'code' => 'server_prepare_fail', 'message' => 'Gangguan server.'], 500)
      : header('Location: /public/register.html');

    exit;
}

/* ===== Kirim email OTP ===== */
// Mengirim email OTP kepada user

if (! sendOtpMail($email, $name, $otp)) {
    // Jika pengiriman email gagal

    if ($stmt = $conn->prepare('DELETE FROM pre_registrations WHERE email=?')) {
        // Hapus data pre-registrasi karena OTP gagal dikirim

        $stmt->bind_param('s', $email);
        // Bind email

        $stmt->execute();
        // Execute delete

        $stmt->close();
        // Tutup statement
    }

    $payload = ['ok' => false, 'code' => 'mail_fail', 'message' => 'Gagal mengirim OTP.'];
    // Payload gagal kirim email

    $isAjax ? json_out($payload, 502) : header('Location: /public/register.html');
    // JSON error atau redirect

    exit;
}

/* ===== Tandai waktu terkirim ===== */
// Update waktu OTP dikirim

$sentAt = (new DateTime)->format('Y-m-d H:i:s');
// Waktu terkirim

if ($stmt = $conn->prepare('UPDATE pre_registrations SET otp_sent_at=? WHERE email=?')) {
    // Prepare update timestamp

    $stmt->bind_param('ss', $sentAt, $email);
    // Bind timestamp + email

    $stmt->execute();
    // Eksekusi update

    $stmt->close();
    // Tutup statement
}

/* ===== Redirect JSON ===== */
// Redirect ke halaman verifikasi OTP

$redir = '/public/verify_otp.html?email='.urlencode($email);
// URL verify_otp

$isAjax
  ? json_out(['ok' => true, 'redirect' => $redir])
  // Jika AJAX → JSON redirect

  : header('Location: '.$redir);
// Jika bukan AJAX → redirect HTML biasa

exit;
// Akhiri script
