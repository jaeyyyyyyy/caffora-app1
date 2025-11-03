<?php
// backend/check_email.php
header('Content-Type: application/json');

$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'caffora_db';

$email = isset($_GET['email']) ? trim($_GET['email']) : '';

if ($email === '') {
    echo json_encode(['exists' => false]);
    exit;
}

$mysqli = @new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_errno) {
    // kalau koneksi gagal yaudah jangan blok user
    echo json_encode(['exists' => false]);
    exit;
}

$stmt = $mysqli->prepare("SELECT 1 FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();

$exists = $stmt->num_rows > 0;

$stmt->close();
$mysqli->close();

echo json_encode(['exists' => $exists]);
