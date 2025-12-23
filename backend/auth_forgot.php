<?php
// backend/auth_forgot.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method tidak diizinkan.']);
    exit;
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true) ?? [];

$email = isset($data['email']) ? trim((string)$data['email']) : '';

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Format email tidak valid.',
    ]);
    exit;
}

require_once __DIR__ . '/db.php';

try {
    $pdo = db();

    // Cari user berdasarkan email
    $stmt = $pdo->prepare('SELECT id, name, email FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Kalau email tidak terdaftar → tetap balas sukses supaya tidak bisa enumerate email
    if (!$user) {
        echo json_encode([
            'status'  => 'success',
            'message' => 'Jika email terdaftar, kode OTP reset password telah dikirim.',
        ]);
        exit;
    }

    // Generate OTP 6 digit dan expiry 15 menit
    $otp       = (string) random_int(100000, 999999);
    $expiresAt = (new DateTime('+15 minutes'))->format('Y-m-d H:i:s');

    $upd = $pdo->prepare(
        'UPDATE users
         SET otp = :otp, otp_expires_at = :exp
         WHERE id = :id'
    );
    $upd->execute([
        ':otp' => $otp,
        ':exp' => $expiresAt,
        ':id'  => (int) $user['id'],
    ]);

    // Kirim email OTP
    $subject = 'Kode OTP Reset Password Caffora';
    $bodyTxt =
        "Halo {$user['name']},\n\n" .
        "Berikut kode OTP untuk reset password akun Caffora Anda:\n\n" .
        $otp . "\n\n" .
        "Kode ini berlaku selama 15 menit. Jika Anda tidak merasa meminta reset password, abaikan email ini.\n\n" .
        "Salam,\nCaffora";

    $sent = false;

    // Coba pakai mailer kustom kalau ada
    @require_once __DIR__ . '/mailer.php';
    if (function_exists('sendMail')) {
       
        // fallback native mail()
        $headers =
            "From: no-reply@caffora.my.id\r\n" .
            "Content-Type: text/plain; charset=utf-8\r\n";
        $sent = @mail($user['email'], $subject, $bodyTxt, $headers);
    }

    echo json_encode([
        'status'  => 'success',
        'message' => $sent
            ? 'Kode OTP reset password telah dikirim ke email Anda.'
            : 'OTP berhasil dibuat, namun pengiriman email gagal. Silakan coba lagi beberapa saat.',
    ]);
} catch (Throwable $e) {
    // Simpan ke error_log kalau mau debug
    error_log('auth_forgot error: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Terjadi kesalahan pada server saat memproses permintaan reset password.',
    ]);
}
