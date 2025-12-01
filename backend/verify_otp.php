<?php

// Pembuka file PHP

declare(strict_types=1);
// Aktifkan strict typing agar tipe data lebih ketat

require_once __DIR__.'/config.php';
// Load konfigurasi utama (DB, BASE_URL, dll.)

require_once __DIR__.'/mailer.php';
// Load mailer (butuh fungsi sendOtpMail)

@ini_set('display_errors', '0');
// Jangan tampilkan error mentah ke browser

@ini_set('log_errors', '1');
// Aktifkan pencatatan error ke log server

function want_json(): bool
{
    // Fungsi untuk deteksi apakah client mengharapkan respons JSON

    $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
    // Ambil header HTTP_ACCEPT jika ada

    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']);
    // Cek apakah request XHR (Ajax)

    $acceptJson = (
        isset($_SERVER['HTTP_ACCEPT']) &&
        str_contains(strtolower($acceptHeader), 'json')
    );
    // Cek apakah header Accept mengandung "json"

    return $isAjax || $acceptJson;
    // Jika salah satu true → dianggap mau JSON
}

function j($d, $s = 200)
{
    // Helper singkat untuk kirim JSON + status HTTP

    http_response_code($s);
    // Set HTTP status code

    header('Content-Type: application/json; charset=utf-8');
    // Set header konten JSON UTF-8

    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    // Encode array/data ke JSON tanpa escape unicode

    exit;
    // Hentikan eksekusi script setelah kirim respons
}

const COOLDOWN = 300;
// Batas minimal jarak waktu resend OTP (5 menit)

/* =========================================================
 *                     BLOK RESEND OTP (GET)
 * =======================================================*/
if (
    $_SERVER['REQUEST_METHOD'] === 'GET' &&
    isset($_GET['resend'])
) {
    // Jika request GET dan ada parameter "resend", berarti minta kirim ulang OTP

    $emailRaw = $_GET['email'] ?? '';
    // Ambil email mentah dari query string

    $email = trim($emailRaw);
    // Trim spasi di kiri/kanan email

    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Jika format email tidak valid

        if (want_json()) {
            // Jika client mengharapkan JSON

            j(
                ['ok' => false, 'error' => 'invalid_email'],
                400
            );
            // Kirim JSON error 400: invalid_email
        } else {
            // Kalau bukan JSON, redirect ke halaman verify_otp dengan error

            header('Location:/verify_otp?err=invalid');
            // Redirect ke halaman verifikasi OTP

            exit;
            // Hentikan script
        }
    }

    $stmt = $conn->prepare(
        'SELECT id, name, otp, otp_expires_at, otp_sent_at
     FROM pre_registrations
     WHERE email = ?
     LIMIT 1'
    );
    // Siapkan query untuk mengambil data pre-registrasi dari email

    $stmt->bind_param('s', $email);
    // Bind parameter email ke query (tipe string)

    $stmt->execute();
    // Eksekusi query

    $result = $stmt->get_result();
    // Ambil objek hasil query

    $pre = $result->fetch_assoc();
    // Ambil satu baris data (array asosiatif)

    $stmt->close();
    // Tutup statement untuk membebaskan resource

    if (! $pre) {
        // Jika tidak ditemukan data pre_registrations untuk email ini

        if (want_json()) {
            // Response mode JSON

            j(
                ['ok' => false, 'error' => 'not_found'],
                404
            );
            // Kirim JSON error 404: tidak ditemukan
        } else {
            // Mode redirect HTML

            header('Location:/register?msg=start_over');
            // Arahkan user untuk mulai daftar ulang

            exit;
            // Hentikan script
        }
    }

    $now = time();
    // Waktu saat ini dalam detik epoch

    $otpSentAtRaw = $pre['otp_sent_at'] ?? '';
    // Timestamp OTP terakhir dikirim (string atau null)

    $sentTs = ! empty($otpSentAtRaw)
      ? strtotime((string) $otpSentAtRaw)
      : 0;
    // Konversi ke detik epoch jika ada, jika tidak → 0

    if (
        $sentTs &&
        ($now - $sentTs) < COOLDOWN
    ) {
        // Jika sudah pernah kirim, dan selisih waktu kurang dari cooldown

        $diff = $now - $sentTs;
        // Hitung selisih waktu

        $wait = COOLDOWN - $diff;
        // Hitung sisa detik yang harus menunggu

        if (want_json()) {
            // Mode JSON

            j(
                [
                    'ok' => false,
                    'error' => 'cooldown',
                    'wait' => $wait,
                ],
                429
            );
            // Kirim JSON error 429 + waktu tunggu
        } else {
            // Mode redirect HTML

            $url =
              '/verify_otp?email='.
              urlencode($email).
              '&err=cooldown';
            // Susun URL redirect dengan email + error cooldown

            header('Location:'.$url);
            // Redirect ke halaman verifikasi OTP

            exit;
            // Hentikan script
        }
    }

    // ==== Tentukan apakah OTP lama masih valid atau perlu OTP baru ====
    $otp = (string) ($pre['otp'] ?? '');
    // Ambil OTP yang tersimpan

    $valid = false;
    // Flag apakah OTP lama masih valid

    $expRaw = $pre['otp_expires_at'] ?? '';
    // Ambil string waktu kadaluarsa OTP

    if (! empty($expRaw)) {
        // Hanya cek jika ada nilai kadaluarsa

        try {
            $nowDt = new DateTime;
            // Waktu sekarang versi DateTime

            $expDt = new DateTime($expRaw);
            // Waktu kadaluarsa OTP

            $valid = ($nowDt < $expDt);
            // OTP valid jika sekarang masih sebelum expires_at
        } catch (Throwable $e) {
            // Jika parsing DateTime gagal, anggap tidak valid

            $valid = false;
            // Set valid ke false
        }
    }

    if (
        ! $valid ||
        $otp === ''
    ) {
        // Jika OTP lama tidak valid atau kosong → generate OTP baru

        $randNumber = random_int(0, 999999);
        // Angka random antara 0 dan 999999

        $otp = str_pad(
            (string) $randNumber,
            6,
            '0',
            STR_PAD_LEFT
        );
        // Ubah ke string 6 digit, dengan leading zero bila perlu

        $newExpDt = new DateTime('+5 minutes');
        // Buat waktu kadaluarsa baru 5 menit dari sekarang

        $newExp = $newExpDt->format('Y-m-d H:i:s');
        // Format datetime ke string untuk simpan di DB

        $stmt = $conn->prepare(
            'UPDATE pre_registrations
       SET otp = ?, otp_expires_at = ?
       WHERE id = ?'
        );
        // Siapkan query update OTP baru dan expired baru

        $stmt->bind_param(
            'ssi',
            $otp,
            $newExp,
            $pre['id']
        );
        // Bind OTP, expires_at, dan id pre-registrasi

        $stmt->execute();
        // Eksekusi update

        $stmt->close();
        // Tutup statement
    }

    // ==== Kirim OTP via email ====
    $nameRaw = $pre['name'] ?? '';
    // Ambil nama dari pre-registrations (bisa kosong)

    $name = (string) $nameRaw;
    // Pastikan nama sebagai string

    $mailOk = sendOtpMail($email, $name, $otp);
    // Panggil fungsi mailer untuk kirim OTP

    if (! $mailOk) {
        // Jika pengiriman email gagal

        if (want_json()) {
            // Mode JSON

            j(
                ['ok' => false, 'error' => 'mail_failed'],
                502
            );
            // Kirim error 502 (Bad Gateway / mail gagal)
        } else {
            // Mode redirect

            $url =
              '/verify_otp?email='.
              urlencode($email).
              '&err=mail';
            // URL redirect dengan error mail_failed

            header('Location:'.$url);
            // Redirect ke halaman verify_otp

            exit;
            // Stop script
        }
    }

    $sentAtDt = new DateTime;
    // Waktu saat email berhasil dikirim

    $sentAt = $sentAtDt->format('Y-m-d H:i:s');
    // Format timestamp terkirim

    $stmt = $conn->prepare(
        'UPDATE pre_registrations
     SET otp_sent_at = ?
     WHERE id = ?'
    );
    // Update kolom otp_sent_at dengan waktu sekarang

    $stmt->bind_param(
        'si',
        $sentAt,
        $pre['id']
    );
    // Bind waktu dan id pre_registrations

    $stmt->execute();
    // Eksekusi update

    $stmt->close();
    // Tutup statement

    if (want_json()) {
        // Mode JSON

        j(['ok' => true]);
        // Kirim respons sukses sederhana
    } else {
        // Mode redirect HTML

        $url =
          '/verify_otp?email='.
          urlencode($email).
          '&msg=resent';
        // URL redirect dengan pesan OTP dikirim ulang

        header('Location:'.$url);
        // Redirect ke halaman verify_otp

        exit;
        // Hentikan eksekusi
    }
}

/* =========================================================
 *                     BLOK VERIFY OTP (POST)
 * =======================================================*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Hanya metode POST yang diizinkan untuk verifikasi OTP

    if (want_json()) {
        // Mode JSON

        j(
            ['ok' => false, 'error' => 'method'],
            405
        );
        // Kirim error 405 (method not allowed)
    } else {
        // Mode redirect

        header('Location:/verify_otp');
        // Kembalikan ke halaman verify_otp

        exit;
        // Stop script
    }
}

$emailRawPost = $_POST['email'] ?? '';
// Ambil email dari form POST

$otpRawPost = $_POST['otp'] ?? '';
// Ambil OTP dari form POST

$email = trim($emailRawPost);
//
