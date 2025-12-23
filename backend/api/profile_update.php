<?php
// =======================================================
// backend/api/profile_update.php
// Endpoint API untuk update profil user (nama, HP, avatar, password)
// =======================================================

// Aktifkan strict typing PHP
declare(strict_types=1);

// Import guard autentikasi
require_once __DIR__ . '/../auth_guard.php';

// Import konfigurasi utama aplikasi
require_once __DIR__ . '/../config.php';

// Set response JSON
header('Content-Type: application/json; charset=utf-8');

// =======================================================
// AUTENTIKASI & IDENTITAS USER
// =======================================================

// Wajib login sebagai admin / karyawan / customer
$user = require_login(['admin', 'karyawan', 'customer']);

// Ambil ID user dari session
$uid  = (int)($user['id'] ?? 0);

// =======================================================
// VALIDASI KONEKSI DATABASE
// =======================================================

// Pastikan koneksi database tersedia dan valid
if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Koneksi database tidak tersedia.',
        'data'    => [],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Set charset database
$conn->set_charset('utf8mb4');

// =======================================================
// HELPER RESPONSE JSON
// =======================================================

/**
 * Helper respon JSON & langsung exit
 */
function res(bool $ok, string $msg, array $data = []): void
{
    echo json_encode(
        [
            'success' => $ok,
            'message' => $msg,
            'data'    => $data,
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

// =======================================================
// BASIC REQUEST HARDENING
// =======================================================

// Hanya izinkan metode POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    res(false, 'Metode tidak diizinkan.');
}

// Batasi ukuran payload maksimal ±3MB
if (!empty($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > (3 * 1024 * 1024)) {
    http_response_code(413);
    res(false, 'Payload terlalu besar.');
}

// Simple origin check untuk mengurangi risiko CSRF lintas domain
if (!empty($_SERVER['HTTP_ORIGIN'])) {
    $origin = $_SERVER['HTTP_ORIGIN'];

    // Ambil base URL aplikasi
    $baseUrl  = rtrim(BASE_URL, '/');

    // Normalisasi host origin aplikasi
    $baseHost = parse_url($baseUrl, PHP_URL_SCHEME) . '://' . parse_url($baseUrl, PHP_URL_HOST);

    // Tolak origin yang tidak sesuai
    if (stripos($origin, $baseHost) !== 0) {
        http_response_code(403);
        res(false, 'Origin tidak diizinkan.');
    }
}

// =======================================================
// WHITELIST FIELD POST
// =======================================================

// Daftar field POST yang diizinkan
$allowedPostKeys = ['name', 'phone', 'old_password', 'password'];

// Ambil semua key POST yang dikirim
$postedKeys      = array_keys($_POST);

// Jika POST tidak kosong, cek apakah ada field ilegal
if (!empty($postedKeys)) {
    $extraKeys = array_diff($postedKeys, $allowedPostKeys);
    if (!empty($extraKeys)) {
        res(false, 'Request mengandung field yang tidak diizinkan.');
    }
}

// =======================================================
// HELPER SANITASI NAMA
// =======================================================

/**
 * Sanitasi nama user
 */
function sanitize_name(string $name): string
{
    // Normalisasi spasi
    $name = preg_replace('/\s+/u', ' ', trim($name));

    // Jika kosong
    if ($name === '') {
        return '';
    }

    // Batasi panjang maksimal 100 karakter
    if (mb_strlen($name, 'UTF-8') > 100) {
        $name = mb_substr($name, 0, 100, 'UTF-8');
    }

    // Validasi karakter yang diperbolehkan
    if (!preg_match('/^[\p{L}\p{N}\s\.\,\-\_]+$/u', $name)) {
        res(false, 'Nama hanya boleh berisi huruf, angka, spasi, titik, koma, strip, dan underscore.');
    }

    return $name;
}

// =======================================================
// HELPER VALIDASI NOMOR HP
// =======================================================

/**
 * Validasi format nomor HP Indonesia
 */
function validate_phone(?string $phone): string
{
    // Trim input
    $phone = trim((string)$phone);

    // Jika kosong
    if ($phone === '') {
        return '';
    }

    // Validasi format 0xxxxxxxxx (9–14 digit)
    if (!preg_match('/^0\d{9,14}$/', $phone)) {
        res(false, 'Format nomor HP tidak valid.');
    }

    return $phone;
}

// =======================================================
// UPLOAD AVATAR (FOTO PROFIL)
// =======================================================

/**
 * Proses upload avatar user
 */
function upload_avatar(int $uid, mysqli $conn): ?string
{
    // Cek apakah file dikirim
    if (
        empty($_FILES['profile_picture']) ||
        !is_array($_FILES['profile_picture']) ||
        ($_FILES['profile_picture']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
    ) {
        return null;
    }

    // Ambil data file
    $file = $_FILES['profile_picture'];

    // Validasi error upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        res(false, 'Terjadi kesalahan saat upload file.');
    }

    // Validasi ukuran maksimal 2MB
    if (!empty($file['size']) && $file['size'] > (2 * 1024 * 1024)) {
        res(false, 'Ukuran file maksimal 2MB.');
    }

    // MIME type yang diperbolehkan
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
    ];

    // Validasi MIME type
    $mime = @mime_content_type($file['tmp_name']);
    if (!$mime || !isset($allowed[$mime])) {
        res(false, 'Format file harus JPG atau PNG.');
    }

    // Validasi apakah benar gambar
    if (!@getimagesize($file['tmp_name'])) {
        res(false, 'File bukan gambar yang valid.');
    }

    // Generate nama file aman
    $ext   = $allowed[$mime];
    $fname = 'avatar_' . $uid . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

    // Tentukan direktori upload
    $root       = dirname(__DIR__, 2);
    $uploadPath = $root . '/public/uploads/avatars';

    // Buat folder jika belum ada
    if (!is_dir($uploadPath)) {
        if (!@mkdir($uploadPath, 0775, true) && !is_dir($uploadPath)) {
            res(false, 'Gagal membuat direktori upload.');
        }
    }

    // Target path
    $target = $uploadPath . '/' . $fname;

    // Pastikan file berasal dari upload HTTP
    if (!is_uploaded_file($file['tmp_name'])) {
        res(false, 'Sumber file upload tidak valid.');
    }

    // Pindahkan file ke folder tujuan
    if (!@move_uploaded_file($file['tmp_name'], $target)) {
        res(false, 'Gagal menyimpan file upload. Cek izin folder.');
    }

    // Return path publik
    return '/public/uploads/avatars/' . $fname;
}

// =======================================================
// PROSES UPLOAD FOTO PROFIL
// =======================================================

// Jika ada file profile_picture
if (!empty($_FILES['profile_picture'])) {
    $path = upload_avatar($uid, $conn);

    if ($path === null) {
        res(false, 'Tidak ada file yang diupload.');
    }

    // Update avatar ke database
    $stmt = $conn->prepare('UPDATE users SET avatar = ? WHERE id = ? LIMIT 1');
    if (!$stmt) {
        res(false, 'Gagal menyiapkan query (avatar).');
    }
    $stmt->bind_param('si', $path, $uid);
    $stmt->execute();
    $stmt->close();

    // Update session
    $_SESSION['user_avatar'] = $path;

    res(true, 'Foto profil berhasil diperbarui.', [
        'profile_picture' => $path,
    ]);
}

// =======================================================
// UPDATE NAMA USER
// =======================================================

if (isset($_POST['name'])) {
    $name = sanitize_name((string)($_POST['name'] ?? ''));

    if ($name === '') {
        res(false, 'Nama tidak boleh kosong.');
    }

    $stmt = $conn->prepare('UPDATE users SET name = ? WHERE id = ? LIMIT 1');
    if (!$stmt) {
        res(false, 'Gagal menyiapkan query (nama).');
    }
    $stmt->bind_param('si', $name, $uid);
    $stmt->execute();
    $stmt->close();

    $_SESSION['user_name'] = $name;

    res(true, 'Nama berhasil diperbarui.', [
        'name' => $name,
    ]);
}

// =======================================================
// UPDATE NOMOR HP
// =======================================================

if (isset($_POST['phone'])) {
    $phone = validate_phone($_POST['phone'] ?? '');

    $stmt = $conn->prepare('UPDATE users SET phone = ? WHERE id = ? LIMIT 1');
    if (!$stmt) {
        res(false, 'Gagal menyiapkan query (nomor HP).');
    }
    $stmt->bind_param('si', $phone, $uid);
    $stmt->execute();
    $stmt->close();

    $_SESSION['user_phone'] = $phone;

    res(true, 'Nomor HP berhasil diperbarui.', [
        'phone' => $phone,
    ]);
}

// =======================================================
// GANTI PASSWORD
// =======================================================

if (isset($_POST['old_password'], $_POST['password'])) {
    $old = (string)$_POST['old_password'];
    $new = (string)$_POST['password'];

    if (mb_strlen($new, 'UTF-8') < 8) {
        res(false, 'Password minimal 8 karakter.');
    }

    $stmt = $conn->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
    if (!$stmt) {
        res(false, 'Gagal menyiapkan query (ambil password).');
    }
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $result = $stmt->get_result();
    $resPw  = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$resPw || empty($resPw['password'])) {
        res(false, 'User tidak ditemukan.');
    }

    $hash = $resPw['password'];

    if (!password_verify($old, $hash)) {
        res(false, 'Password lama salah.');
    }

    $newHash = password_hash($new, PASSWORD_DEFAULT);

    $stmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ? LIMIT 1');
    if (!$stmt) {
        res(false, 'Gagal menyiapkan query (update password).');
    }
    $stmt->bind_param('si', $newHash, $uid);
    $stmt->execute();
    $stmt->close();

    res(true, 'Password berhasil diperbarui.');
}

// =======================================================
// DEFAULT RESPONSE
// =======================================================

res(false, 'Tidak ada perubahan dikirim.');
