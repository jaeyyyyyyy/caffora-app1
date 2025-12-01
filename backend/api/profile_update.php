<?php
// backend/api/profile_update.php
declare(strict_types=1);

require_once __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

// Wajib login sebagai admin/karyawan/customer
$user = require_login(['admin', 'karyawan', 'customer']);
$uid  = (int)($user['id'] ?? 0);

// Pastikan koneksi DB tersedia
if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Koneksi database tidak tersedia.',
        'data'    => [],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$conn->set_charset('utf8mb4');

/**
 * Helper respon JSON & exit
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

/**
 * ==== BASIC REQUEST HARDENING ====
 */

// Hanya izinkan POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    res(false, 'Metode tidak diizinkan.');
}

// Batasi ukuran payload (sekitar 3MB)
if (!empty($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > (3 * 1024 * 1024)) {
    http_response_code(413);
    res(false, 'Payload terlalu besar.');
}

// Simple origin check untuk kurangi risiko CSRF via fetch dari domain lain
if (!empty($_SERVER['HTTP_ORIGIN'])) {
    $origin = $_SERVER['HTTP_ORIGIN'];

    $baseUrl  = rtrim(BASE_URL, '/');
    $baseHost = parse_url($baseUrl, PHP_URL_SCHEME) . '://' . parse_url($baseUrl, PHP_URL_HOST);

    if (stripos($origin, $baseHost) !== 0) {
        http_response_code(403);
        res(false, 'Origin tidak diizinkan.');
    }
}

/**
 * ==== WHITELIST FIELD POST ====
 * Hanya field berikut yang diizinkan:
 * - name
 * - phone
 * - old_password
 * - password
 *
 * Jika ada field lain (misal: role, is_admin, hacked, dsb) → request ditolak.
 */
$allowedPostKeys = ['name', 'phone', 'old_password', 'password'];
$postedKeys      = array_keys($_POST);

// kosong itu aman (misal upload avatar cuma kirim FILES)
if (!empty($postedKeys)) {
    $extraKeys = array_diff($postedKeys, $allowedPostKeys);
    if (!empty($extraKeys)) {
        // Bisa kamu log ke audit_log kalau mau
        res(false, 'Request mengandung field yang tidak diizinkan.');
    }
}

/**
 * Helper sanitasi nama:
 * - Normalisasi spasi
 * - Batasi panjang
 * - Hanya izinkan huruf, angka, spasi, titik, koma, strip, underscore
 *   → payload SQL / script aneh otomatis ditolak.
 */
function sanitize_name(string $name): string
{
    $name = preg_replace('/\s+/u', ' ', trim($name));

    if ($name === '') {
        return '';
    }

    if (mb_strlen($name, 'UTF-8') > 100) {
        $name = mb_substr($name, 0, 100, 'UTF-8');
    }

    // Hanya huruf, angka, spasi, titik, koma, strip, underscore
    if (!preg_match('/^[\p{L}\p{N}\s\.\,\-\_]+$/u', $name)) {
        res(false, 'Nama hanya boleh berisi huruf, angka, spasi, titik, koma, strip, dan underscore.');
    }

    return $name;
}

/**
 * Helper validasi nomor HP Indonesia
 */
function validate_phone(?string $phone): string
{
    $phone = trim((string)$phone);

    if ($phone === '') {
        return '';
    }

    if (!preg_match('/^0\d{9,14}$/', $phone)) {
        res(false, 'Format nomor HP tidak valid.');
    }

    return $phone;
}

/**
 * ========== UPLOAD AVATAR (FOTO PROFIL) ==========
 */
function upload_avatar(int $uid, mysqli $conn): ?string
{
    if (
        empty($_FILES['profile_picture']) ||
        !is_array($_FILES['profile_picture']) ||
        ($_FILES['profile_picture']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
    ) {
        return null;
    }

    $file = $_FILES['profile_picture'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        res(false, 'Terjadi kesalahan saat upload file.');
    }

    if (!empty($file['size']) && $file['size'] > (2 * 1024 * 1024)) {
        res(false, 'Ukuran file maksimal 2MB.');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
    ];

    $mime = @mime_content_type($file['tmp_name']);
    if (!$mime || !isset($allowed[$mime])) {
        res(false, 'Format file harus JPG atau PNG.');
    }

    if (!@getimagesize($file['tmp_name'])) {
        res(false, 'File bukan gambar yang valid.');
    }

    $ext   = $allowed[$mime];
    $fname = 'avatar_' . $uid . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

    $root       = dirname(__DIR__, 2);
    $uploadPath = $root . '/public/uploads/avatars';

    if (!is_dir($uploadPath)) {
        if (!@mkdir($uploadPath, 0775, true) && !is_dir($uploadPath)) {
            res(false, 'Gagal membuat direktori upload.');
        }
    }

    $target = $uploadPath . '/' . $fname;

    if (!is_uploaded_file($file['tmp_name'])) {
        res(false, 'Sumber file upload tidak valid.');
    }

    if (!@move_uploaded_file($file['tmp_name'], $target)) {
        res(false, 'Gagal menyimpan file upload. Cek izin folder.');
    }

    return '/public/uploads/avatars/' . $fname;
}

/**
 * ========== PROSES UPLOAD FOTO ==========
 */
if (!empty($_FILES['profile_picture'])) {
    $path = upload_avatar($uid, $conn);

    if ($path === null) {
        res(false, 'Tidak ada file yang diupload.');
    }

    $stmt = $conn->prepare('UPDATE users SET avatar = ? WHERE id = ? LIMIT 1');
    if (!$stmt) {
        res(false, 'Gagal menyiapkan query (avatar).');
    }
    $stmt->bind_param('si', $path, $uid);
    $stmt->execute();
    $stmt->close();

    $_SESSION['user_avatar'] = $path;

    res(true, 'Foto profil berhasil diperbarui.', [
        'profile_picture' => $path,
    ]);
}

/**
 * ========== UPDATE NAMA ==========
 */
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

/**
 * ========== UPDATE NOMOR HP ==========
 */
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

/**
 * ========== GANTI PASSWORD ==========
 */
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

/**
 * ========== DEFAULT ==========
 */
res(false, 'Tidak ada perubahan dikirim.');
