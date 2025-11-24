<?php
// backend/auth_reset.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php'; // pastikan di sini ada $pdo (PDO)

function json_response(string $status, string $message, int $code = 200): void {
    http_response_code($code);
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response('error', 'Metode tidak diizinkan.', 405);
}

// baca body JSON
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$email    = isset($data['email']) ? trim((string)$data['email']) : '';
$token    = isset($data['token']) ? trim((string)$data['token']) : '';
$password = isset($data['password']) ? (string)$data['password'] : '';

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response('error', 'Email tidak valid.', 400);
}
if ($token === '' || strlen($token) < 40) {
    json_response('error', 'Token reset tidak valid.', 400);
}
if (strlen($password) < 8) {
    json_response('error', 'Password terlalu pendek.', 400);
}

try {
    /** @var PDO $pdo */
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $now = (new DateTime())->format('Y-m-d H:i:s');
    $tokenHash = hash('sha256', $token);

    // cek token di tabel password_resets
    $sql = 'SELECT email, token_hash, expires_at 
            FROM password_resets 
            WHERE email = :email AND token_hash = :token_hash 
            LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':email'      => $email,
        ':token_hash' => $tokenHash,
    ]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reset) {
        json_response('error', 'Token atau email tidak valid.', 400);
    }

    if ($reset['expires_at'] < $now) {
        // hapus token kadaluarsa
        $pdo->prepare('DELETE FROM password_resets WHERE email = :email')->execute([':email' => $email]);
        json_response('error', 'Link reset password sudah kadaluarsa.', 400);
    }

    // hash password baru
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // update password di tabel users
    $update = $pdo->prepare('UPDATE users SET password = :password WHERE email = :email LIMIT 1');
    $update->execute([
        ':password' => $passwordHash,
        ':email'    => $email,
    ]);

    if ($update->rowCount() < 1) {
        json_response('error', 'Akun pengguna tidak ditemukan.', 400);
    }

    // hapus semua token reset untuk email ini
    $pdo->prepare('DELETE FROM password_resets WHERE email = :email')->execute([':email' => $email]);

    json_response('success', 'Password berhasil direset. Silakan login dengan password baru.');

} catch (Throwable $e) {
    error_log('Reset password error: ' . $e->getMessage());
    json_response('error', 'Terjadi kesalahan pada server.', 500);
}
