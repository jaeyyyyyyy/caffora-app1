<?php
// Pembuka file PHP

// backend/api/profile_update.php
// API untuk update profil user (nama, avatar, hp, password)

declare(strict_types=1);
// Aktifkan strict typing

require_once __DIR__ . '/../auth_guard.php';
// Import auth_guard untuk cek login & role

require_once __DIR__ . '/../config.php';
// Import config & koneksi database

$user = require_login(['admin','karyawan','customer']);
// Pastikan user login dan role valid

$uid  = (int)($user['id'] ?? 0);
// Ambil ID user dari session (dipaksa integer)

header('Content-Type: application/json; charset=utf-8');
// Semua respons akan berupa JSON

function res($ok, $msg, $data = [])
{
    // Helper untuk kirim JSON standar

    echo json_encode([
        'success' => $ok,
        'message' => $msg,
        'data'    => $data
    ], JSON_UNESCAPED_UNICODE);
    // Encode JSON tanpa escape unicode

    exit;
    // Hentikan skrip setelah kirim JSON
}


/**
 * ======================================================
 *                  UPLOAD AVATAR
 * ======================================================
 */
function upload_avatar(int $uid): ?string
{
    // Cek apakah file dikirim & tidak error
    if (
        empty($_FILES['profile_picture']) ||
        $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK
    ) {
        return null;
        // Jika tidak ada file, return null
    }

    $file = $_FILES['profile_picture'];
    // Objek file upload

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png'
    ];
    // Daftar MIME type yang diperbolehkan

    $mime = @mime_content_type($file['tmp_name']);
    // Baca MIME file secara aman

    if (!isset($allowed[$mime])) {
        // Jika format tidak didukung
        res(false, "Format file harus JPG/PNG");
    }

    $ext = $allowed[$mime];
    // Tentukan ekstensi file sesuai MIME

    $fname = "avatar_{$uid}_" . time() . "." . $ext;
    // Buat nama file unik berdasarkan timestamp & user ID

    $root = dirname(__DIR__, 2);
    // Naik 2 folder dari backend/api → ROOT project

    $uploadPath = $root . "/public/uploads/avatars";
    // Folder tempat penyimpanan avatar

    if (!is_dir($uploadPath)) {
        // Buat folder jika belum ada
        @mkdir($uploadPath, 0775, true);
    }

    $target = $uploadPath . "/" . $fname;
    // Path lengkap file avatar baru

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        // Jika gagal dipindahkan
        res(false, "Gagal upload file. Cek izin folder.");
    }

    // Path yang disimpan ke DB & diakses lewat browser
    return "/public/uploads/avatars/" . $fname;
}


/**
 * ======================================================
 *              PROSES UPLOAD FOTO PROFILE
 * ======================================================
 */
if (!empty($_FILES['profile_picture'])) {

    $path = upload_avatar($uid);
    // Jalankan fungsi upload avatar

    $stmt = $conn->prepare(
        "UPDATE users SET avatar=? WHERE id=? LIMIT 1"
    );
    // Query update kolom avatar

    $stmt->bind_param("si", $path, $uid);
    // Bind parameter avatar path & user id

    $stmt->execute();
    // Jalankan query update avatar

    $stmt->close();
    // Tutup statement

    $_SESSION['user_avatar'] = $path;
    // Update session agar avatar baru langsung tampil

    res(true, "Foto profil berhasil diperbarui", [
        "profile_picture" => $path
    ]);
    // Kirim respons sukses
}


/**
 * ======================================================
 *                    UPDATE NAMA
 * ======================================================
 */
if (isset($_POST['name'])) {

    $name = trim($_POST['name']);
    // Ambil & trim nama baru

    if ($name === "") {
        // Validasi tidak boleh kosong
        res(false, "Nama tidak boleh kosong");
    }

    $stmt = $conn->prepare(
        "UPDATE users SET name=? WHERE id=? LIMIT 1"
    );
    // Query update nama

    $stmt->bind_param("si", $name, $uid);
    // Bind nama & user id

    $stmt->execute();
    // Jalankan update

    $stmt->close();
    // Tutup statement

    $_SESSION['user_name'] = $name;
    // Update session dengan nama baru

    res(true, "Nama berhasil diperbarui", [
        "name" => $name
    ]);
    // Kirim respons sukses
}


/**
 * ======================================================
 *                 UPDATE NOMOR TELEPON
 * ======================================================
 */
if (isset($_POST['phone'])) {

    $phone = trim($_POST['phone']);
    // Ambil nomor HP & trim spasi

    if (
        $phone !== "" &&
        !preg_match('/^0\d{9,14}$/', $phone)
    ) {
        // Validasi: hanya format Indonesia yang valid
        res(false, "Format nomor HP tidak valid");
    }

    $stmt = $conn->prepare(
        "UPDATE users SET phone=? WHERE id=? LIMIT 1"
    );
    // Query update nomor HP

    $stmt->bind_param("si", $phone, $uid);
    // Bind phone & user id

    $stmt->execute();
    // Eksekusi query

    $stmt->close();
    // Tutup statement

    $_SESSION['user_phone'] = $phone;
    // Update session untuk nomor HP

    res(true, "Nomor HP berhasil diperbarui", [
        "phone" => $phone
    ]);
    // Kirim respons sukses
}


/**
 * ======================================================
 *                 GANTI PASSWORD
 * ======================================================
 */
if (
    isset($_POST['old_password']) &&
    isset($_POST['password'])
) {
    $old = $_POST['old_password'];
    // Password lama

    $new = $_POST['password'];
    // Password baru

    if (strlen($new) < 6) {
        // Validasi minimal panjang 6
        res(false, "Password minimal 6 karakter");
    }

    // Ambil password hash lama dari DB
    $stmt = $conn->prepare(
        "SELECT password FROM users WHERE id=? LIMIT 1"
    );
    $stmt->bind_param("i", $uid);
    $stmt->execute();

    $resPw = $stmt->get_result()->fetch_assoc();
    // Ambil hasil query password lama

    $stmt->close();

    if (!$resPw) {
        // Jika user tidak ada
        res(false, "User tidak ditemukan");
    }

    $hash = $resPw['password'];
    // Password hash lama dari DB

    if (!password_verify($old, $hash)) {
        // Jika password lama salah
        res(false, "Password lama salah");
    }

    $newHash = password_hash($new, PASSWORD_DEFAULT);
    // Hash password baru

    $stmt = $conn->prepare(
        "UPDATE users SET password=? WHERE id=? LIMIT 1"
    );
    $stmt->bind_param("si", $newHash, $uid);
    $stmt->execute();
    $stmt->close();

    res(true, "Password berhasil diperbarui");
    // Respons sukses
}


/**
 * ======================================================
 *                DEFAULT: TIDAK ADA UPDATE
 * ======================================================
 */
res(false, "Tidak ada perubahan dikirim.");
// Jika tidak ada aksi yang cocok
