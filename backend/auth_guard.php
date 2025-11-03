<?php
// backend/auth_guard.php
declare(strict_types=1);

require_once __DIR__ . '/config.php'; // BASE_URL, $conn, redirect()

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * Normalisasi nama role dari DB / session jadi 3 saja:
 * - admin
 * - karyawan
 * - customer  (default)
 */
function cf_normalize_role(?string $raw): string
{
    $r = strtolower(trim($raw ?? ''));

    // semua nama admin yang mungkin kamu pakai
    if (in_array($r, ['admin', 'administrator', 'superadmin', 'admin_master', 'owner'], true)) {
        return 'admin';
    }

    // semua nama karyawan/staff yang mungkin kamu pakai
    if (in_array($r, ['karyawan', 'staff', 'kasir', 'barista', 'pegawai'], true)) {
        return 'karyawan';
    }

    // sisanya anggap customer
    return 'customer';
}

/**
 * Pastikan user sudah login (opsional: role tertentu).
 * @param array $allowedRoles contoh ['customer'] atau ['admin','karyawan']
 * @return array data user (id,name,email,status,role)
 */
function require_login(array $allowedRoles = []) : array {
    global $conn;

    // 1) belum login → lempar ke login
    if (empty($_SESSION['user_id'])) {
        // pakai BASE_URL biar gak relatif
        redirect(rtrim(BASE_URL, '/') . '/public/login.html?err=' . urlencode('Silakan login dulu.'));
    }

    $userId = (int) $_SESSION['user_id'];

    // 2) ambil user dari DB
    $stmt = $conn->prepare('SELECT id, name, email, status, role, avatar, phone FROM users WHERE id=? LIMIT 1');
    if (!$stmt) {
        // boleh juga redirect ke error page
        die('Database prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $currentUser = $res->fetch_assoc();
    $stmt->close();

    // 3) kalau user gak ketemu → paksa logout
    if (!$currentUser) {
      session_unset();
      session_destroy();
      redirect(rtrim(BASE_URL, '/') . '/public/login.html?err=' . urlencode('Sesi berakhir. Silakan login ulang.'));
    }

    // 4) kalau status belum active → ke verifikasi
    if (($currentUser['status'] ?? 'pending') !== 'active') {
        redirect(rtrim(BASE_URL, '/') . '/public/verify_otp.html?email=' . urlencode($currentUser['email']));
    }

    // 5) NORMALISASI ROLE
    $normRole = cf_normalize_role($currentUser['role'] ?? 'customer');

    // simpan balik ke session (biar konsisten di JS juga)
    $_SESSION['user_name']   = $currentUser['name'];
    $_SESSION['user_email']  = $currentUser['email'];
    $_SESSION['user_role']   = $normRole;
    $_SESSION['user_phone']  = $currentUser['phone'] ?? '';
    $_SESSION['user_avatar'] = $currentUser['avatar'] ?? '';

    // 6) kalau halaman ini minta role tertentu → cek
    if ($allowedRoles) {
        // allowed juga kita normalisasi
        $allowed = array_map('cf_normalize_role', $allowedRoles);

        if (!in_array($normRole, $allowed, true)) {
            // ROLE TIDAK DIIZINKAN → arahkan ke dashboard sesuai role aktual
            $base = rtrim(BASE_URL, '/');

            if ($normRole === 'admin') {
                redirect($base . '/public/admin/index.php');
            } elseif ($normRole === 'karyawan') {
                redirect($base . '/public/karyawan/index.php');
            } else {
                redirect($base . '/public/customer/index.php');
            }
        }
    }

    // jangan lupa kembalikan user (kalau mau dipakai di file pemanggil)
    // tambahkan role yang sudah dinormalisasi
    $currentUser['role'] = $normRole;
    return $currentUser;
}

/**
 * helper buat halaman yang cuma boleh 1 role
 */
function require_role(string $role) : array {
    return require_login([$role]);
}
