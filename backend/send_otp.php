<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';
session_start();

header('Content-Type: application/json');

$email = trim($_GET['email'] ?? '');
if ($email === '' && !empty($_SESSION['pending_email'])) {
    $email = $_SESSION['pending_email'];
}

if ($email === '') {
    echo json_encode(['ok' => false, 'error' => 'no_email']);
    exit;
}

// ambil user pending + otp
$stmt = $conn->prepare('SELECT name, otp FROM users WHERE email=? AND status="pending" LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->bind_result($name, $otp);
$found = $stmt->fetch();
$stmt->close();

if (!$found) {
    echo json_encode(['ok' => false, 'error' => 'not_found']);
    exit;
}

// kirim
$sent = sendOtpMail($email, $name, $otp);
echo json_encode(['ok' => $sent]);
