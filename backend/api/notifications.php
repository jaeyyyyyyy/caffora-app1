<?php
// backend/api/notifications.php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../config.php';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
  echo json_encode(['ok' => false, 'error' => 'DB error']);
  exit;
}
$conn->set_charset('utf8mb4');

/* ---------- UTIL ---------- */
function norm_role(string $r): string {
  $r = strtolower(trim($r));
  // alias → baku
  if ($r === 'employee' || $r === 'staff' || $r === 'pegawai') return 'karyawan';
  if ($r === 'pelanggan' || $r === 'customer') return 'customer';
  if ($r === 'admin') return 'admin';
  return $r ?: 'customer';
}
function jexit(array $payload) {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload);
  exit;
}
/** insert helper — userId = NULL untuk broadcast; role bisa NULL/‘customer’/‘karyawan’/‘admin’ */
function insert_notif(mysqli $conn, ?int $userId, ?string $role, string $message, string $link=''): bool {
  $status = 'unread';
  if ($userId !== null) {
    $stmt = $conn->prepare(
      "INSERT INTO notifications (user_id, role, message, status, link, created_at)
       VALUES (?, NULL, ?, ?, ?, NOW())"
    );
    $stmt->bind_param('isss', $userId, $message, $status, $link);
  } else {
    if ($role !== null) $role = norm_role($role);
    if ($role === null) {
      $stmt = $conn->prepare(
        "INSERT INTO notifications (user_id, role, message, status, link, created_at)
         VALUES (NULL, NULL, ?, ?, ?, NOW())"
      );
      $stmt->bind_param('sss', $message, $status, $link);
    } else {
      $stmt = $conn->prepare(
        "INSERT INTO notifications (user_id, role, message, status, link, created_at)
         VALUES (NULL, ?, ?, ?, ?, NOW())"
      );
      $stmt->bind_param('ssss', $role, $message, $status, $link);
    }
  }
  $ok = $stmt->execute();
  $stmt->close();
  return $ok;
}

/* ---------- SESSION USER ---------- */
$userId = (int)($_SESSION['user_id'] ?? 0);
$sessionRoleRaw = (string)($_SESSION['user_role'] ?? '');
$currentRoleRaw = trim($sessionRoleRaw);

/* fallback role dari DB bila kosong */
if ($userId > 0 && $currentRoleRaw === '') {
  if ($res = $conn->query("SELECT role FROM users WHERE id={$userId} LIMIT 1")) {
    $row = $res->fetch_assoc();
    $currentRoleRaw = (string)($row['role'] ?? '');
    $res->close();
  }
}
$currentRole = norm_role($currentRoleRaw);

/* ---------- ACTION ---------- */
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

/* ---------- BASE WHERE untuk pembaca ----------
   - khusus user: user_id = saya
   - broadcast: user_id IS NULL dan (role IS NULL atau role = role_saya)
------------------------------------------------- */
$roleEsc   = $conn->real_escape_string($currentRole);
$baseWhere = "(user_id = {$userId} OR (user_id IS NULL AND (role IS NULL OR role = '{$roleEsc}')))";

/* =========================
 * CREATE (ADMIN ONLY)
 * ========================= */
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  if (norm_role((string)($_SESSION['user_role'] ?? '')) !== 'admin') {
    jexit(['ok'=>false,'error'=>'Unauthorized']);
  }

  $targetType = $_POST['target_type'] ?? '';
  $targetRole = norm_role((string)($_POST['target_role'] ?? ''));
  $targetUser = (int)($_POST['target_user'] ?? 0);
  $message    = trim((string)($_POST['message'] ?? ''));
  $link       = trim((string)($_POST['link'] ?? ''));

  if ($message === '') jexit(['ok'=>false,'error'=>'Pesan wajib diisi']);

  $inserted = 0;

  if ($targetType === 'role') {
    if (!in_array($targetRole, ['customer','karyawan','admin'], true)) {
      jexit(['ok'=>false,'error'=>'Role target tidak valid']);
    }
    if (insert_notif($conn, null, $targetRole, $message, $link)) $inserted = 1;

  } elseif ($targetType === 'all') {
    if (insert_notif($conn, null, null, $message, $link)) $inserted = 1;

  } elseif ($targetType === 'user') {
    if ($targetUser < 1) jexit(['ok'=>false,'error'=>'User target tidak valid']);
    if (insert_notif($conn, $targetUser, null, $message, $link)) $inserted = 1;

  } else {
    jexit(['ok'=>false,'error'=>'Target type tidak valid']);
  }

  jexit(['ok'=>true,'inserted'=>$inserted,'message'=>'Sukses']);
}

/* =========================
 * UNREAD COUNT (badge)
 * ========================= */
if ($action === 'unread_count') {
  $sql = "SELECT COUNT(*) AS c FROM notifications WHERE {$baseWhere} AND status='unread'";
  $res = $conn->query($sql);
  $row = $res ? $res->fetch_assoc() : ['c'=>0];
  jexit(['ok'=>true,'count'=>(int)($row['c'] ?? 0)]);
}

/* =========================
 * LIST
 * ========================= */
if ($action === 'list') {
  $sql = "SELECT id, message, status, created_at, link
          FROM notifications
          WHERE {$baseWhere}
          ORDER BY created_at DESC
          LIMIT 50";
  $res = $conn->query($sql);
  $items = [];
  while ($row = $res->fetch_assoc()) {
    $items[] = [
      'id'         => (int)$row['id'],
      'title'      => 'Notifikasi',
      'message'    => (string)$row['message'],
      'is_read'    => ($row['status'] === 'read') ? 1 : 0,
      'link'       => $row['link'],
      'created_at' => $row['created_at'],
    ];
  }
  jexit(['ok'=>true,'items'=>$items]);
}

/* =========================
 * MARK ALL READ
 * ========================= */
if ($action === 'mark_all_read') {
  $conn->query("UPDATE notifications SET status='read'
                WHERE {$baseWhere} AND status='unread'");
  jexit(['ok'=>true]);
}

/* =========================
 * MARK ONE READ
 * ========================= */
if ($action === 'mark_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = (int)($_POST['id'] ?? 0);
  if ($id > 0) {
    $conn->query("UPDATE notifications SET status='read'
                  WHERE id={$id} AND {$baseWhere}");
  }
  jexit(['ok'=>true]);
}

/* ==========================================================
 * OPSIONAL: SYSTEM TRIGGER — notifikasi orderan baru
 * Panggil via POST dari flow checkout setelah INSERT order.
 * Field: order_id, customer_id, customer_name, total (angka),
 *        staff_link, customer_link (opsional, bisa kosong)
 * ========================================================== */
if ($action === 'system_new_order' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $orderId      = (int)($_POST['order_id'] ?? 0);
  $customerId   = (int)($_POST['customer_id'] ?? 0);
  $customerName = trim((string)($_POST['customer_name'] ?? 'Customer'));
  $total        = (float)($_POST['total'] ?? 0);
  $staffLink    = trim((string)($_POST['staff_link'] ?? ''));
  $custLink     = trim((string)($_POST['customer_link'] ?? ''));

  if ($orderId < 1 || $customerId < 1) jexit(['ok'=>false,'error'=>'Param tidak lengkap']);

  // ke customer (personal)
  insert_notif($conn, $customerId, null,
    "Order #{$orderId} berhasil dibuat. Terima kasih, {$customerName}!",
    $custLink
  );

  // ke karyawan (broadcast)
  insert_notif($conn, null, 'karyawan',
    "Pesanan baru #{$orderId} dari {$customerName} (Rp " . number_format($total,0,',','.') . ")",
    $staffLink
  );

  // ke admin (broadcast)
  insert_notif($conn, null, 'admin',
    "Pesanan baru #{$orderId} dari {$customerName} (Rp " . number_format($total,0,',','.') . ")",
    $staffLink
  );

  jexit(['ok'=>true,'message'=>'Sukses']);
}

jexit(['ok'=>false,'error'=>'Invalid action']);
