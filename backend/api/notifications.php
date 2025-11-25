<?php
// ---------------------------------------------
// File  : backend/api/notifications.php
// Desc  : API sistem notifikasi Caffora
// ---------------------------------------------

declare(strict_types=1);              // Aktifkan strict type
session_start();                      // Mulai sesi untuk ambil user session

require_once __DIR__ . '/../config.php';  // Load konfigurasi database

// ---------------------------------------------
// KONEKSI DATABASE
// ---------------------------------------------
$conn = new mysqli(
  DB_HOST,                             // host DB
  DB_USER,                             // user DB
  DB_PASS,                             // password DB
  DB_NAME                              // nama DB
);

// Jika koneksi gagal, kirim JSON error lalu hentikan
if ($conn->connect_error) {
  echo json_encode([
    'ok'    => false,
    'error' => 'DB connect failed'
  ]);
  exit;
}

$conn->set_charset('utf8mb4');         // Pastikan charset UTF8 MB4 agar emoji aman

// ---------------------------------------------
// FUNGSI UTIL
// ---------------------------------------------

/**
 * norm_role()
 * Normalisasi role ke: admin, karyawan, customer.
 * Jika tidak cocok → default: customer.
 */
function norm_role(string $r): string {
  $r = strtolower(trim($r));          // Bersihkan role → lowercase + trim
  if ($r === 'admin')    return 'admin';
  if ($r === 'karyawan') return 'karyawan';
  if ($r === 'customer') return 'customer';
  return 'customer';                  // default fallback
}

/**
 * jexit()
 * Kembalikan JSON & hentikan eksekusi
 */
function jexit(array $arr): void {
  header('Content-Type: application/json; charset=utf-8');  // header JSON
  echo json_encode(
    $arr,
    JSON_UNESCAPED_SLASHES            // agar URL tidak di-escape
  );
  exit;                               // stop execution
}

/**
 * insert_notif()
 * Simpan notifikasi:
 * - user_id NULL → broadcast
 * - role NULL     → broadcast global (semua role)
 * - status default = unread
 */
function insert_notif(
  mysqli $db,                         // koneksi DB
  ?int $userId,                       // target user (null = broadcast)
  ?string $role,                      // role target (null = semua)
  string $message,                    // isi pesan
  string $link = ''                   // link optional
): bool {

  $role = $role ? norm_role($role) : null; // normalisasi role jika ada

  // Query insert menggunakan prepared statement
  $sql = "
    INSERT INTO notifications
      (user_id, role, message, status, link, created_at)
    VALUES
      (NULLIF(?,0), ?, ?, 'unread', ?, NOW())
  ";

  // Jika prepare gagal → return false
  if (!$stmt = $db->prepare($sql)) {
    return false;
  }

  $uid = $userId ?? 0;                // userId null → nilai bind = 0

  // Bind parameter: i s s s
  $stmt->bind_param(
    'isss',
    $uid,                             // user_id (NULLIF)
    $role,                            // role
    $message,                         // message
    $link                             // link
  );

  $ok = $stmt->execute();             // Eksekusi insert
  $stmt->close();                     // Tutup statement

  return $ok;                         // Return hasil eksekusi
}

// ---------------------------------------------
// SESSION USER
// ---------------------------------------------

// Ambil ID user dari session (jika tidak ada → 0)
$userId = (int)($_SESSION['user_id'] ?? 0);

// Ambil role user dari session
$userRoleRaw = (string)($_SESSION['user_role'] ?? '');

// Jika user sudah login tapi session role kosong → ambil dari DB
if ($userId && $userRoleRaw === '') {

  $q = "SELECT role FROM users WHERE id={$userId} LIMIT 1"; // Query ambil role

  if ($res = $conn->query($q)) {
    $row = $res->fetch_assoc();       // Ambil hasil
    $userRoleRaw = (string)($row['role'] ?? '');
    $res->close();                    // Tutup result
  }
}

// Normalisasi role user
$userRole = norm_role($userRoleRaw);

// ---------------------------------------------
// AMBIL PARAMETER action
// ---------------------------------------------
$action = $_GET['action']             // Ambil dari GET
        ?? ($_POST['action'] ?? '');  // Jika tidak ada → cek POST

// ---------------------------------------------
// BASE WHERE NOTIFIKASI
// ---------------------------------------------
// Semua role dapat notif dari:
// 1. user_id = saya (personal)
// 2. broadcast global → user_id NULL & role NULL
// 3. broadcast ke role saya
$roleEsc = $conn->real_escape_string($userRole);

$baseWhere = "
  ( user_id = {$userId}
    OR (user_id IS NULL
        AND (role IS NULL OR role = '{$roleEsc}')
       )
  )
";
// =====================================================
// CREATE (khusus admin kirim manual)
// =====================================================
if (
  $action === 'create' &&             // Jika action = create
  $_SERVER['REQUEST_METHOD'] === 'POST' // dan method POST
) {

  // Normalisasi role dari session (harus admin)
  $sessionRole = norm_role(
    $_SESSION['user_role'] ?? ''
  );

  // Jika bukan admin → unauthorized
  if ($sessionRole !== 'admin') {
    jexit([
      'ok'    => false,
      'error' => 'Unauthorized'
    ]);
  }

  // Ambil target_type dari POST (all/role/user)
  $targetType = $_POST['target_type'] ?? '';

  // Ambil target_role lalu normalkan (jika ada)
  $targetRole = norm_role(
    (string)($_POST['target_role'] ?? '')
  );

  // Ambil target_user (ID user tujuan)
  $targetUser = (int)(
    $_POST['target_user'] ?? 0
  );

  // Pesan notifikasi (wajib diisi)
  $message = trim(
    (string)($_POST['message'] ?? '')
  );

  // Link opsional (misal ke detail pesanan)
  $link = trim(
    (string)($_POST['link'] ?? '')
  );

  // Validasi: pesan wajib diisi
  if ($message === '') {
    jexit([
      'ok'    => false,
      'error' => 'Pesan wajib diisi'
    ]);
  }

  // Target: berdasarkan role
  if ($targetType === 'role') {

    // Kirim notifikasi broadcast ke role tertentu
    insert_notif(
      $conn,
      null,          // null → broadcast (bukan user tertentu)
      $targetRole,   // role target
      $message,      // isi pesan
      $link          // link
    );

  // Target: semua role
  } elseif ($targetType === 'all') {

    // Broadcast global (role null)
    insert_notif(
      $conn,
      null,          // null → broadcast
      null,          // role null → semua role
      $message,      // isi pesan
      $link          // link
    );

  // Target: user tertentu
  } elseif (
    $targetType === 'user' &&
    $targetUser > 0
  ) {

    // Kirim notifikasi ke user tertentu
    insert_notif(
      $conn,
      $targetUser,   // user id tujuan
      null,          // role null (personal)
      $message,      // isi pesan
      $link          // link
    );

  } else {
    // Jika target tidak sesuai aturan
    jexit([
      'ok'    => false,
      'error' => 'Target tidak valid'
    ]);
  }

  // Jika berhasil, kirim respon sukses
  jexit([
    'ok'      => true,
    'message' => 'Notifikasi berhasil dikirim'
  ]);
}

// =====================================================
// UNREAD COUNT (badge untuk SEMUA role)
// =====================================================
if ($action === 'unread_count') {

  // Jika belum login, kembalikan count = 0
  if ($userId < 1) {
    jexit([
      'ok'    => true,
      'count' => 0
    ]);
  }

  // Query hitung notif dengan status unread
  $sql = "
    SELECT
      COUNT(*) AS c
    FROM
      notifications
    WHERE
      status = 'unread'
      AND (
        user_id = {$userId}
        OR (
          user_id IS NULL
          AND (
            role IS NULL
            OR role = '{$roleEsc}'
          )
        )
      )
  ";

  // Eksekusi query
  $res = $conn->query($sql);

  $count = 0;                          // Default count = 0

  // Jika query berhasil
  if ($res) {
    $row = $res->fetch_assoc();        // Ambil 1 baris hasil
    $count = (int)($row['c'] ?? 0);    // Ambil nilai count
    $res->close();                     // Tutup result set
  }

  // Kirim hasil jumlah unread
  jexit([
    'ok'    => true,
    'count' => $count
  ]);
}

// =====================================================
// LIST NOTIFIKASI
// =====================================================
if ($action === 'list') {

  // Query ambil daftar notifikasi (maks 100)
  $sql = "
    SELECT
      id,
      message,
      status,
      created_at,
      link
    FROM
      notifications
    WHERE
      {$baseWhere}
    ORDER BY
      created_at DESC
    LIMIT 100
  ";

  // Eksekusi query
  $res = $conn->query($sql);

  $items = [];                         // Array untuk menampung notifikasi

  // Jika query berhasil
  if ($res) {

    // Loop semua baris hasil query
    while ($row = $res->fetch_assoc()) {

      // Tambah ke array items dalam format rapi
      $items[] = [
        'id'         => (int)$row['id'],        // ID notifikasi
        'message'    => $row['message'],        // Isi pesan
        'status'     => $row['status'],         // Status: read/unread
        'is_read'    => $row['status'] === 'read', // Boolean sudah dibaca
        'link'       => $row['link'],           // Link tujuan (jika ada)
        'created_at' => $row['created_at']      // Waktu dibuat
      ];
    }

    $res->close();                     // Tutup result set
  }

  // Kembalikan daftar notifikasi
  jexit([
    'ok'    => true,
    'items' => $items
  ]);
}

// =====================================================
// MARK ALL READ
// =====================================================
if ($action === 'mark_all_read') {

  // Update semua notifikasi yang match baseWhere
  // dan masih unread → menjadi read
  $conn->query("
    UPDATE
      notifications
    SET
      status = 'read'
    WHERE
      {$baseWhere}
      AND status = 'unread'
  ");

  // Balas sukses (tanpa menghitung berapa yang berubah)
  jexit([
    'ok' => true
  ]);
}

// =====================================================
// MARK ONE READ
// =====================================================
if (
  $action === 'mark_read' &&          // Action = mark_read
  $_SERVER['REQUEST_METHOD'] === 'POST' // Harus via POST
) {

  // Ambil ID notifikasi dari POST
  $id = (int)($_POST['id'] ?? 0);

  // Jika id valid (> 0)
  if ($id > 0) {

    // Update 1 notifikasi: set status read
    // Hanya jika sesuai baseWhere (hak akses)
    $conn->query("
      UPDATE
        notifications
      SET
        status = 'read'
      WHERE
        id = {$id}
        AND {$baseWhere}
    ");
  }

  // Tetap balas ok walau tidak ada yang berubah
  jexit([
    'ok' => true
  ]);
}

// =====================================================
// SYSTEM NOTIF (auto dari orders)
// =====================================================
if (
  $action === 'system_new_order' &&   // Action khusus sistem
  $_SERVER['REQUEST_METHOD'] === 'POST' // Hanya via POST
) {

  // Ambil parameter dari POST
  $orderId = (int)(
    $_POST['order_id'] ?? 0
  );
  $customerId = (int)(
    $_POST['customer_id'] ?? 0
  );
  $customerName = trim(
    (string)(
      $_POST['customer_name'] ?? 'Customer'
    )
  );
  $total = (float)(
    $_POST['total'] ?? 0
  );
  $staffLink = trim(
    (string)(
      $_POST['staff_link'] ?? ''
    )
  );
  $custLink = trim(
    (string)(
      $_POST['customer_link'] ?? ''
    )
  );

  // Validasi: orderId wajib ada
  if ($orderId < 1) {
    jexit([
      'ok'    => false,
      'error' => 'Param tidak lengkap'
    ]);
  }

  // ------------------------------------------
  // Kirim notifikasi ke customer (personal)
  // ------------------------------------------
  if ($customerId > 0) {

    // Teks notifikasi untuk customer
    $msgCustomer = "Pesanan #{$orderId} berhasil "
                 . "dibuat. Terima kasih, "
                 . "{$customerName}!";

    insert_notif(
      $conn,
      $customerId,      // user_id customer
      'customer',       // role customer
      $msgCustomer,     // pesan
      $custLink         // link ke halaman customer
    );
  }

  // ------------------------------------------
  // Broadcast ke karyawan & admin
  // ------------------------------------------
  // Pesan ringkas untuk staff/admin dengan total
  $msg = "Pesanan baru #{$orderId} dari "
       . "{$customerName} (Rp "
       . number_format(
           $total,
           0,
           ',',
           '.'
         )
       . ")";

  // Notifikasi ke semua karyawan
  insert_notif(
    $conn,
    null,              // null → broadcast
    'karyawan',        // role karyawan
    $msg,              // pesan
    $staffLink         // link ke halaman staff
  );

  // Notifikasi ke semua admin
  insert_notif(
    $conn,
    null,              // null → broadcast
    'admin',           // role admin
    $msg,              // pesan
    $staffLink         // link ke halaman admin
  );

  // Balasan sukses untuk sistem
  jexit([
    'ok'      => true,
    'message' => 'System notif dikirim ke semua role'
  ]);
}

// =====================================================
// DEFAULT: INVALID ACTION
// =====================================================

// Jika tidak ada action yang cocok di atas
jexit([
  'ok'    => false,
  'error' => 'Invalid action'
]);
