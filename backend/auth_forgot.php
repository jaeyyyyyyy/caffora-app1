<?php

// backend/auth_forgot.php

// Aktifkan strict typing untuk meningkatkan keamanan tipe data
declare(strict_types=1);

// Set header agar respons dikirim dalam format JSON dengan UTF-8
header(
    'Content-Type: application/json; charset=utf-8'
);

// Load konfigurasi dasar aplikasi (konstanta, dsb.)
require_once __DIR__.'/config.php';

// Load koneksi database (mendefinisikan variabel $pdo)
require_once __DIR__.'/db.php';

// Load fungsi mailer (dipakai untuk mengirim email OTP)
require_once __DIR__.'/mailer.php';

// Load helper umum (misal: helper generate OTP, fungsi utility, dll.)
require_once __DIR__.'/helpers.php';

/**
 * Fungsi pembantu untuk mengirim respons JSON dan langsung mengakhiri eksekusi.
 */
function json_response(
    string $status,   // status respons, misalnya: "success" atau "error"
    string $message,  // pesan yang akan dikirim ke client
    int $code = 200   // HTTP status code (default 200 / OK)
): void {
    // Set HTTP status code sesuai parameter
    http_response_code($code);

    // Encode data ke JSON dan kirim ke client
    echo json_encode(
        [
            'status' => $status,   // isi field "status"
            'message' => $message,  // isi field "message"
        ]
    );

    // Hentikan eksekusi script setelah respons dikirim
    exit;
}

// Pastikan request yang masuk menggunakan metode POST saja
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Jika bukan POST, kirim error "Metode tidak diizinkan" (405)
    json_response(
        'error',
        'Metode tidak diizinkan.',
        405
    );
}

// Ambil body mentah dari request (raw JSON)
$raw = file_get_contents('php://input');

// Decode JSON menjadi array asosiatif
$data = json_decode(
    $raw,
    true
);

// Ambil field email dari body, jika tidak ada buat jadi string kosong
$email = isset($data['email'])
  ? trim((string) $data['email'])  // trim spasi dan pastikan jadi string
  : '';                            // jika tidak ada, isi dengan string kosong

// Validasi dasar: email tidak boleh kosong dan harus format email valid
if (
    $email === '' ||
    ! filter_var($email, FILTER_VALIDATE_EMAIL)
) {
    // Jika tidak valid, kirim error 400 (bad request)
    json_response(
        'error',
        'Format email tidak valid.',
        400
    );
}

try {
    /** @var PDO $pdo */
    // Set agar PDO melempar exception ketika terjadi error
    $pdo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    // ----------------------------------------
    // Cek apakah user dengan email tsb aktif
    // ----------------------------------------
    $stmt = $pdo->prepare(
        '
        SELECT id, name, email
        FROM users
        WHERE email = :email AND status = "active"
        LIMIT 1
    '
    );

    // Eksekusi query dengan parameter email
    $stmt->execute(
        [
            ':email' => $email,
        ]
    );

    // Ambil satu baris user (jika ada)
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Jika user tidak ditemukan / tidak aktif
    if (! $user) {
        // Jangan bocorkan apakah email ada atau tidak di sistem
        json_response(
            'success',
            'Jika email terdaftar, kode OTP reset password akan dikirim ke inbox Anda.'
        );
    }

    // ----------------------------------------
    // Generate OTP 6 digit dan waktu kadaluarsa
    // ----------------------------------------

    // Buat kode OTP acak 6 digit (100000 - 999999)
    $otp = random_int(
        100000,
        999999
    );

    // Tentukan waktu kadaluarsa OTP (10 menit dari sekarang)
    $expiresAt = (new DateTime('+10 minutes'))
        ->format('Y-m-d H:i:s');

    // ----------------------------------------
    // Simpan OTP ke dalam tabel users
    // ----------------------------------------
    $update = $pdo->prepare(
        '
        UPDATE users
        SET otp = :otp,
            otp_expires_at = :expires_at
        WHERE id = :id
        LIMIT 1
    '
    );

    // Eksekusi update dengan binding parameter
    $update->execute(
        [
            ':otp' => (string) $otp,  // simpan OTP sebagai string
            ':expires_at' => $expiresAt,     // simpan waktu kadaluarsa
            ':id' => $user['id'],    // id user yang akan di-update
        ]
    );

    // ----------------------------------------
    // Kirim email yang berisi OTP ke user
    // ----------------------------------------

    // Pastikan fungsi mailer tersedia
    if (function_exists('sendOtpResetPasswordMail')) {
        // Panggil fungsi untuk mengirim email OTP reset password
        sendOtpResetPasswordMail(
            $user['email'],          // alamat email tujuan
            $user['name'] ?? '',     // nama penerima (bisa kosong)
            (string) $otp,           // kode OTP 6 digit
            $expiresAt               // waktu kadaluarsa OTP
        );
    } else {
        // Jika fungsi mailer tidak ada, fallback: log OTP ke error_log (untuk debug)
        error_log(
            "OTP reset password untuk {$email}: {$otp}"
        );
    }

    // Kirim respons sukses ke client
    json_response(
        'success',
        'Kode OTP reset password telah dikirim ke email Anda.'
    );

} catch (Throwable $e) {
    // Jika terjadi error (DB / internal), catat ke error_log
    error_log(
        'Forgot password error: '.$e->getMessage()
    );

    // Kirim respons error umum ke client (tanpa rincian teknis)
    json_response(
        'error',
        'Terjadi kesalahan pada server.',
        500
    );
}
