<?php
// backend/auth_forgot.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php'; // tempat fungsi kirim email OTP
require_once __DIR__ . '/helpers.php'; // kalau di sini ada helper generate OTP

function json_response(string $status, string $message, int $code = 200): void {
    http_response_code($code);
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response('error', 'Metode tidak diizinkan.', 405);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$email = isset($data['email']) ? trim((string)$data['email']) : '';

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response('error', 'Format email tidak valid.', 400);
}

try {
    /** @var PDO $pdo */
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // cek user aktif
    $stmt = $pdo->prepare('
        SELECT id, name, email
        FROM users
        WHERE email = :email AND status = "active"
        LIMIT 1
    ');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // jangan bocorkan apakah email ada atau tidak
        json_response(
            'success',
            'Jika email terdaftar, kode OTP reset password akan dikirim ke inbox Anda.'
        );
    }

    // generate OTP 6 digit
    $otp = random_int(100000, 999999);
    $expiresAt = (new DateTime('+10 minutes'))->format('Y-m-d H:i:s');

    // simpan ke kolom otp & otp_expires_at di tabel users
    $update = $pdo->prepare('
        UPDATE users
        SET otp = :otp,
            otp_expires_at = :expires_at
        WHERE id = :id
        LIMIT 1
    ');
    $update->execute([
        ':otp'        => (string)$otp,
        ':expires_at' => $expiresAt,
        ':id'         => $user['id'],
    ]);

    // kirim email OTP
    if (function_exists('sendOtpResetPasswordMail')) {
        sendOtpResetPasswordMail($user['email'], $user['name'] ?? '', (string)$otp, $expiresAt);
    } else {
        // fallback: log ke error_log
        error_log("OTP reset password untuk {$email}: {$otp}");
    }

    json_response(
        'success',
        'Kode OTP reset password telah dikirim ke email Anda.'
    );

} catch (Throwable $e) {
    error_log('Forgot password error: ' . $e->getMessage());
    json_response('error', 'Terjadi kesalahan pada server.', 500);
}
