<?php
// backend/mailer.php
declare(strict_types=1);

// ===== Autoload PHPMailer =====
if (is_file(__DIR__ . '/../vendor/autoload.php')) {
  require_once __DIR__ . '/../vendor/autoload.php';
} elseif (is_file(__DIR__ . '/lib/phpmailer/src/PHPMailer.php')) {
  require_once __DIR__ . '/lib/phpmailer/src/Exception.php';
  require_once __DIR__ . '/lib/phpmailer/src/PHPMailer.php';
  require_once __DIR__ . '/lib/phpmailer/src/SMTP.php';
} else {
  throw new RuntimeException('PHPMailer tidak ditemukan. Install via Composer atau letakkan di backend/lib/phpmailer');
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/** Konstanta mail domain */
const MAIL_DOMAIN   = 'caffora.my.id';
const MAIL_HOST     = 'mail.caffora.my.id';
const MAIL_PORT     = 465;                  // SSL
const MAIL_ADDRESS  = 'verifikasi@caffora.my.id';
const MAIL_NAME     = 'Caffora';
const MAIL_USERNAME = 'verifikasi@caffora.my.id';
const MAIL_PASSWORD = 'Caffora120202@';


const DKIM_ENABLE   = false; 
const DKIM_SELECTOR = 'default';      
const DKIM_DOMAIN   = MAIL_DOMAIN;

const DKIM_PRIVATE_KEY_PATH = '/home/cafforam/dkim/private.key';

const APP_BASE_URL  = 'https://caffora.my.id'; 

function makeMailer(): PHPMailer {
  $m = new PHPMailer(true);
  // Transport
  $m->isSMTP();
  $m->Host       = MAIL_HOST;
  $m->SMTPAuth   = true;
  $m->Username   = MAIL_USERNAME;
  $m->Password   = MAIL_PASSWORD;
  $m->Port       = MAIL_PORT;g
  $m->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL/TLS (465)


  $m->CharSet    = 'UTF-8';
  $m->Encoding   = 'base64';

  $m->setFrom(MAIL_ADDRESS, MAIL_NAME, false);
  $m->Sender     = MAIL_ADDRESS; 

  $m->addCustomHeader('X-Auto-Response-Suppress', 'All');
  $m->addCustomHeader('Auto-Submitted', 'no');
 
  if (DKIM_ENABLE && is_readable(DKIM_PRIVATE_KEY_PATH)) {
    $m->DKIM_domain     = DKIM_DOMAIN;
    $m->DKIM_private    = DKIM_PRIVATE_KEY_PATH;
    $m->DKIM_selector   = DKIM_SELECTOR;
    $m->DKIM_identify   = MAIL_ADDRESS;
    $m->DKIM_passphrase = ''; 
  }

  $m->Timeout = 20;

  return $m;
}


function sendOtpMail(string $email, string $name, string $otp): bool {
  $mail = makeMailer();
  try {

    $mail->clearAllRecipients();
    $mail->addAddress($email, $name ?: $email);

    
    $mail->Subject = 'Kode Verifikasi OTP Caffora: ' . $otp;

    $otpSafe = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');
    $nameEsc = htmlspecialchars($name ?: 'Pelanggan', ENT_QUOTES, 'UTF-8');

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

    $text = "Verifikasi Akun Caffora\n\n" .
            "Halo {$name},\n" .
            "Kode verifikasi Anda: {$otp}\n" .
            "Kode berlaku 5 menit. Abaikan jika bukan Anda.";

    $mail->isHTML(true);
    $mail->Body    = $html;
    $mail->AltBody = $text;

    $mail->MessageID = sprintf('<%s.%s@%s>', bin2hex(random_bytes(8)), time(), MAIL_DOMAIN);


    $mail->clearReplyTos();
    $mail->addReplyTo('noreply@' . MAIL_DOMAIN, MAIL_NAME . ' No-Reply');


    return $mail->send();
  } catch (Exception $e) {
    error_log('MAILER OTP ERR: ' . $e->getMessage());
    return false;
  } finally {
   
    if (isset($mail) && $mail->SMTPKeepAlive) {
      $mail->smtpClose();
    }
  }
}

function sendMailGeneric(string $to, string $subject, string $html, ?string $altText = null, string $toName = ''): bool {
  $mail = makeMailer();
  try {
    $mail->addAddress($to, $toName ?: $to);
    $mail->Subject = $subject;
    $mail->isHTML(true);
    $mail->Body    = $html;
    $mail->AltBody = $altText ?: strip_tags($html);
    $mail->MessageID = sprintf('<%s.%s@%s>', bin2hex(random_bytes(8)), time(), MAIL_DOMAIN);
    $mail->addReplyTo(MAIL_ADDRESS, MAIL_NAME);
    return $mail->send();
  } catch (Exception $e) {
    error_log('MAILER GENERIC ERR: ' . $e->getMessage());
    return false;
  } finally {
    if (isset($mail) && $mail->SMTPKeepAlive) {
      $mail->smtpClose();
    }
  }
}
