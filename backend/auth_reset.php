<?php

// Pembuka file PHP

// backend/auth_reset.php
// Lokasi file backend untuk proses reset OTP & password

declare(strict_types=1);
// Aktifkan strict typing

header('Content-Type: application/json; charset=utf-8');
// Set header JSON UTF-8

// jangan tampilkan warning/error mentah ke browser (biar JSON tetap rapi)
ini_set('display_errors', '0');
// Matikan tampilan error

error_reporting(E_ALL);
// Tetap log semua error

require_once __DIR__.'/db.php';
// Import koneksi database (fungsi db())

function json_response(bool $ok, string $message, array $extra = []): void
// Fungsi helper untuk respons JSON & exit
{
    http_response_code($ok ? 200 : 400);
    // Jika ok = true → HTTP 200, jika tidak → 400

    echo json_encode(
        array_merge([
            'status' => $ok ? 'success' : 'error',
            'message' => $message,
        ], $extra),
        JSON_UNESCAPED_UNICODE
    );
    // Encode data JSON dengan unicode aman

    exit;
    // Hentikan eksekusi script
}

// baca body
$raw = file_get_contents('php://input');
// Ambil raw input JSON

$data = json_decode($raw, true);
// Decode JSON ke array

if (! is_array($data)) {
    json_response(false, 'Payload tidak valid.');
    // Jika payload bukan array → error
}

$action = $data['action'] ?? '';
// Ambil action (verify/reset)

$email = trim((string) ($data['email'] ?? ''));
// Ambil email

$otp = trim((string) ($data['otp'] ?? ''));
// Ambil kode OTP dari input

if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(false, 'Email tidak valid.');
    // Validasi email
}

if ($otp === '' || ! ctype_digit($otp)) {
    json_response(false, 'Kode OTP tidak valid.');
    // Validasi OTP hanya angka
}

try {
    $pdo = db();
    // Koneksi DB via fungsi db()
} catch (Throwable $e) {
    json_response(false, 'Gagal koneksi ke database.');
    // Jika gagal koneksi → error
}

// cari user
$stmt = $pdo->prepare('SELECT id, email FROM users WHERE email = :email LIMIT 1');
// Prepare ambil user berdasarkan email

$stmt->execute([':email' => $email]);
// Eksekusi query

$user = $stmt->fetch(PDO::FETCH_ASSOC);
// Ambil hasil

if (! $user) {
    json_response(false, 'Email tidak terdaftar.');
    // Jika user tidak ditemukan → error
}

$userId = (int) $user['id'];
// Ambil ID user

$now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
// Timestamp saat ini

// ====== fungsi cek OTP (password_resets atau users.otp) ======
$validOtp = false;
// Status apakah OTP valid

$resetRow = null;
// Data baris password_resets jika ada

// 1) cek di tabel password_resets (jika memang digunakan)
try {
    $stmt = $pdo->prepare(
        'SELECT * FROM password_resets
         WHERE user_id = :uid
           AND selector = :selector
           AND used = 0
           AND expires_at >= :now
         ORDER BY id DESC
         LIMIT 1'
    );
    // Query cek OTP di tabel password_resets

    $stmt->execute([
        ':uid' => $userId,
        ':selector' => $otp,
        ':now' => $now,
    ]);
    // Eksekusi dengan parameter

    $resetRow = $stmt->fetch(PDO::FETCH_ASSOC);
    // Ambil baris

    if ($resetRow) {
        $validOtp = true;
        // Jika ketemu maka OTP valid
    }
} catch (Throwable $e) {
    // kalau tabel tidak ada / error, abaikan dan lanjut cek users.otp
}

// 2) cek di kolom users.otp kalau belum ketemu
if (! $validOtp) {
    try {
        $stmt = $pdo->prepare(
            'SELECT otp, otp_expires_at 
             FROM users 
             WHERE id = :uid 
             LIMIT 1'
        );
        // Query cek OTP di tabel users

        $stmt->execute([':uid' => $userId]);
        // Eksekusi

        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        // Ambil data OTP user

        if ($u && $u['otp'] === $otp && $u['otp_expires_at'] !== null) {
            // Cocokkan OTP

            if ($u['otp_expires_at'] >= $now) {
                $validOtp = true;
                // OTP valid dan belum expired
            }
        }
    } catch (Throwable $e) {
        // kalau kolom tidak ada, dibiarkan saja
    }
}

if (! $validOtp) {
    json_response(false, 'Kode OTP salah atau sudah kedaluwarsa.');
    // Jika OTP tidak valid → error
}

// ====== kalau cuma verifikasi ======
if ($action === 'verify_otp') {
    json_response(true, 'Kode OTP valid. Silakan buat kata sandi baru.');
    // Hanya verifikasi OTP tanpa reset password
}

// ====== aksi reset password ======
if ($action === 'reset_password') {

    $password = (string) ($data['password'] ?? '');
    // Password baru

    $passwordConfirm = (string) ($data['password_confirm'] ?? '');
    // Konfirmasi password baru

    if (mb_strlen($password) < 8) {
        json_response(false, 'Password minimal 8 karakter.');
        // Validasi panjang password
    }

    if ($password !== $passwordConfirm) {
        json_response(false, 'Konfirmasi password tidak cocok.');
        // Validasi konfirmasi password
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    // Hash password baru

    try {
        $pdo->beginTransaction();
        // Mulai transaksi DB

        // update password & bersihkan otp di tabel users (kalau ada)
        $stmt = $pdo->prepare(
            'UPDATE users
             SET password = :pwd,
                 otp = NULL,
                 otp_expires_at = NULL,
                 updated_at = NOW()
             WHERE id = :uid'
        );
        // Query update password

        $stmt->execute([
            ':pwd' => $hash,
            ':uid' => $userId,
        ]);
        // Eksekusi update

        // tandai password_resets sebagai used jika barisnya ada
        if ($resetRow && isset($resetRow['id'])) {
            $stmt = $pdo->prepare(
                'UPDATE password_resets 
                 SET used = 1 
                 WHERE id = :id'
            );
            // Query update flag used

            $stmt->execute([':id' => $resetRow['id']]);
            // Eksekusi
        }

        $pdo->commit();
        // Commit perubahan
    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
            // Rollback jika terjadi kesalahan
        }

        json_response(false, 'Gagal menyimpan password baru.');
        // Error penyimpanan password
    }

    json_response(true, 'Kata sandi berhasil diubah. Silakan login kembali.');
    // Success reset password
}

json_response(false, 'Aksi tidak dikenali.');
// Action selain verify/reset dianggap invalid
