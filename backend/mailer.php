<?php

// Pembuka file PHP

// backend/mailer.php
// File konfigurasi PHPMailer untuk pengiriman OTP di Caffora

declare(strict_types=1);
// Aktifkan strict typing

/**
 * PHPMailer setup untuk domain caffora.my.id
 * SMTP 465 SSL, alamat sender verifikasi@caffora.my.id
 * Sudah termasuk anti spam headers, DKIM optional
 */

// ===== Autoload PHPMailer =====
if (is_file(__DIR__.'/../vendor/autoload.php')) {
    // Jika project pakai Composer, load autoload.php
    require_once __DIR__.'/../vendor/autoload.php';
} elseif (is_file(__DIR__.'/lib/phpmailer/src/PHPMailer.php')) {
    // Jika pakai library manual, load file PHPMailer secara manual
    require_once __DIR__.'/lib/phpmailer/src/Exception.php';
    require_once __DIR__.'/lib/phpmailer/src/PHPMailer.php';
    require_once __DIR__.'/lib/phpmailer/src/SMTP.php';
} else {
    // Jika kedua metode tidak ditemukan → error fatal
    throw new RuntimeException('PHPMailer tidak ditemukan. Install via Composer atau letakkan di backend/lib/phpmailer');
}

use PHPMailer\PHPMailer\Exception;
// Import class utama PHPMailer

use PHPMailer\PHPMailer\PHPMailer;

// Import class Exception dari PHPMailer

/** Konstanta mail domain */
const MAIL_DOMAIN = 'caffora.my.id';
// Domain email, digunakan untuk Message-ID & DKIM

const MAIL_HOST = 'mail.caffora.my.id';
// Host SMTP utama

const MAIL_PORT = 465;
// Port SMTP SSL (SMTPS)

const MAIL_ADDRESS = 'verifikasi@caffora.my.id';
// Alamat email pengirim (From + Sender)

const MAIL_NAME = 'Caffora';
// Nama brand pengirim email

const MAIL_USERNAME = 'verifikasi@caffora.my.id';
// Username login SMTP

const MAIL_PASSWORD = 'Caffora120202@';
// Password SMTP (harus cocok dengan akun hosting)

/** Optional: DKIM */
const DKIM_ENABLE = false;
// Toggle enable DKIM

const DKIM_SELECTOR = 'default';
// Selector DKIM default cPanel

const DKIM_DOMAIN = MAIL_DOMAIN;
// Domain DKIM sama dengan domain utama

const DKIM_PRIVATE_KEY_PATH = '/home/cafforam/dkim/private.key';
// Path private key DKIM (jika digunakan)

/** Optional: BASE URL untuk CTA di email */
const APP_BASE_URL = 'https://caffora.my.id';
// Base URL aplikasi

/**
 * Factory: bikin instance PHPMailer yang sudah dikonfigurasi
 */
function makeMailer(): PHPMailer
{
    // Buat instance PHPMailer
    $m = new PHPMailer(true);

    // Transport SMTP
    $m->isSMTP();
    $m->Host = MAIL_HOST;
    $m->SMTPAuth = true;
    $m->Username = MAIL_USERNAME;
    $m->Password = MAIL_PASSWORD;
    $m->Port = MAIL_PORT;
    $m->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    // Gunakan SMTPS (port 465)

    // Charset & encoding email
    $m->CharSet = 'UTF-8';
    $m->Encoding = 'base64';

    // Set alamat pengirim
    $m->setFrom(MAIL_ADDRESS, MAIL_NAME, false);
    // Envelope-from / return-path
    $m->Sender = MAIL_ADDRESS;

    // Header tambahan anti-auto-reply
    $m->addCustomHeader('X-Auto-Response-Suppress', 'All');
    $m->addCustomHeader('Auto-Submitted', 'no');

    // DKIM setup jika diaktifkan
    if (DKIM_ENABLE && is_readable(DKIM_PRIVATE_KEY_PATH)) {
        $m->DKIM_domain = DKIM_DOMAIN;
        $m->DKIM_private = DKIM_PRIVATE_KEY_PATH;
        $m->DKIM_selector = DKIM_SELECTOR;
        $m->DKIM_identity = MAIL_ADDRESS;
        $m->DKIM_passphrase = '';
    }

    // Timeout untuk shared hosting
    $m->Timeout = 20;

    return $m;
    // Kembalikan PHPMailer yang sudah siap
}

/**
 * Kirim email OTP (HTML dan plain-text)
 */
function sendOtpMail(string $email, string $name, string $otp): bool
{
    $mail = makeMailer();
    // Buat PHPMailer instance baru

    try {
        // Bersihkan penerima sebelumnya
        $mail->clearAllRecipients();

        // Tambahkan penerima
        $mail->addAddress($email, $name ?: $email);

        // Subject email
        $mail->Subject = 'Kode Verifikasi OTP Caffora: '.$otp;

        // Escape HTML
        $otpSafe = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');
        $nameEsc = htmlspecialchars($name ?: 'Pelanggan', ENT_QUOTES, 'UTF-8');

        // Konten HTML OTP
        $html = <<<HTML
<!doctype html>
<html lang="id">
  <meta charset="utf-8">
  <title>OTP Caffora</title>
  <div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#1f2937;line-height:1.6">
    <h2 style="margin:0 0 12px 0;color:#111827">Verifikasi Akun Caffora</h2>
    <p>Halo <strong>{$nameEsc}</strong>,</p>
    <p>Kode verifikasi Anda:</p>
    <p style="font-size:28px;letter-spacing:6px;margin:12px 0 18px 0"><strong>{$otpSafe}</strong></p>
    <p>Kode berlaku selama <strong>5 menit</strong>. Jangan bagikan OTP kepada siapa pun.</p>
    <hr style="border:0;border-top:1px solid #e5e7eb;margin:18px 0">
    <p style="font-size:12px;color:#6b7280">Jika Anda tidak meminta kode ini, abaikan email ini.</p>
  </div>
</html>
HTML;

        // Konten plaintext (fallback)
        $text =
          "Verifikasi Akun Caffora\n\n".
          "Halo {$name},\n".
          "Kode verifikasi Anda: {$otp}\n".
          'Kode berlaku 5 menit. Abaikan jika bukan Anda.';

        // Set email ke HTML
        $mail->isHTML(true);
        $mail->Body = $html;
        $mail->AltBody = $text;

        // Message-ID unik per email
        $mail->MessageID =
          sprintf('<%s.%s@%s>', bin2hex(random_bytes(8)), time(), MAIL_DOMAIN);

        // Reply-To ke noreply
        $mail->clearReplyTos();
        $mail->addReplyTo('noreply@'.MAIL_DOMAIN, MAIL_NAME.' No-Reply');

        // Kirim email
        return $mail->send();

    } catch (Exception $e) {
        // Jika terjadi error → log
        error_log('MAILER OTP ERR: '.$e->getMessage());

        return false;

    } finally {
        // Tutup koneksi SMTP jika masih hidup
        if (isset($mail) && $mail->SMTPKeepAlive) {
            $mail->smtpClose();
        }
    }
}

/**
 * Helper generik untuk email lain-lain
 */
function sendMailGeneric(
    string $to,
    string $subject,
    string $html,
    ?string $altText = null,
    string $toName = ''
): bool {

    $mail = makeMailer();
    // Buat instance PHPMailer baru

    try {
        // Set penerima
        $mail->addAddress($to, $toName ?: $to);

        // Set subject
        $mail->Subject = $subject;

        // HTML body
        $mail->isHTML(true);
        $mail->Body = $html;

        // AltBody jika tidak ada → strip HTML
        $mail->AltBody = $altText ?: strip_tags($html);

        // Message-ID unik
        $mail->MessageID =
          sprintf('<%s.%s@%s>', bin2hex(random_bytes(8)), time(), MAIL_DOMAIN);

        // Default reply-to
        $mail->addReplyTo(MAIL_ADDRESS, MAIL_NAME);

        // Kirim email
        return $mail->send();

    } catch (Exception $e) {
        // Log error
        error_log('MAILER GENERIC ERR: '.$e->getMessage());

        return false;

    } finally {
        // Tutup SMTP jika perlu
        if (isset($mail) && $mail->SMTPKeepAlive) {
            $mail->smtpClose();
        }
    }
}
