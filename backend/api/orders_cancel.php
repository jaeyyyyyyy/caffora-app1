<?php
// backend/api/orders_cancel.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once __DIR__ . '/../config.php';

/* ==== AUDIT HELPER LOADER (robust) ==== */
$__candidates = [
  __DIR__ . '/../lib/audit_helper.php',  // lokasi normal
  __DIR__ . '/lib/audit_helper.php',     // fallback jika ada di api/lib
];
foreach ($__candidates as $__p) {
  if (is_file($__p)) { require_once $__p; break; }
}
/* kompatibel jika helper bernama log_audit() */
if (!function_exists('audit_log') && function_exists('log_audit')) {
  function audit_log(mysqli $db, int $actorId, string $entity, int $entityId, string $action, string $fromVal = '', string $toVal = '', string $remark = ''): bool {
    return log_audit($db, $actorId, $entity, $entityId, $action, $fromVal, $toVal, $remark);
  }
}
/* shim terakhir supaya tidak fatal */
if (!function_exists('audit_log')) {
  function audit_log(mysqli $db, int $actorId, string $entity, int $entityId, string $action, string $fromVal = '', string $toVal = '', string $remark = ''): bool {
    error_log("[AUDIT_SHIM] audit_helper.php missing: $actorId $entity#$entityId $action ($remark)");
    return true;
  }
}

/* ==== DB ==== */
$mysqli = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_errno) {
  echo json_encode(['ok'=>false,'error'=>'DB connect failed: '.$mysqli->connect_error], JSON_UNESCAPED_SLASHES);
  exit;
}
$mysqli->set_charset('utf8mb4');

/* ==== Utils ==== */
function out(array $arr): void { echo json_encode($arr, JSON_UNESCAPED_SLASHES); exit; }
function bad(string $msg, int $code=400): void { http_response_code($code); out(['ok'=>false,'error'=>$msg]); }

function create_notification(mysqli $db, ?int $userId, ?string $role, string $message, ?string $link = null): void {
  $stmt = $db->prepare("
    INSERT INTO notifications (user_id, role, message, status, created_at, link)
    VALUES (?, ?, ?, 'unread', NOW(), ?)
  ");
  if (!$stmt) return;
  $stmt->bind_param('isss', $userId, $role, $message, $link);
  $stmt->execute();
  $stmt->close();
}

/* ==== Guard ==== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') bad('Use POST', 405);

/* ==== Input ==== */
$order_id = (int)($_POST['order_id'] ?? 0);
$reason   = trim((string)($_POST['reason'] ?? ''));
if ($order_id <= 0) bad('Missing order_id');
if ($reason === '') $reason = 'Pembatalan pesanan (belum bayar)';

/* ==== Fetch order (for validation & audit from_val) ==== */
$stmt = $mysqli->prepare("
  SELECT id, user_id, invoice_no, customer_name, payment_status, order_status, payment_method,
         grand_total, tax_amount
  FROM orders
  WHERE id=? LIMIT 1
");
$stmt->bind_param('i', $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$order) bad('Order tidak ditemukan', 404);

/* ==== Validate ==== */
if ((string)$order['payment_status'] !== 'pending') {
  bad('Pembatalan hanya untuk pesanan yang belum dibayar.');
}
if (in_array((string)$order['order_status'], ['completed','cancelled'], true)) {
  bad('Pesanan tidak dapat dibatalkan.');
}

/* ==== Context ==== */
$invoice     = (string)$order['invoice_no'];
$customerId  = $order['user_id'] !== null ? (int)$order['user_id'] : null;
$custName    = (string)$order['customer_name'];
$method      = (string)$order['payment_method'];
$actorId     = (int)($_SESSION['user_id'] ?? 0); // yang membatalkan

$fromOrder   = json_encode([
  'order_status'   => (string)$order['order_status'],
  'payment_status' => (string)$order['payment_status']
], JSON_UNESCAPED_UNICODE);

/* ==== Transaction ==== */
$mysqli->begin_transaction();
try {
  // 1) Update orders -> cancelled + failed
  $stmt = $mysqli->prepare("
    UPDATE orders
    SET order_status='cancelled',
        payment_status='failed',
        cancel_reason=?,
        canceled_by_id=?,
        canceled_at=NOW(),
        updated_at=NOW()
    WHERE id=?
  ");
  $stmt->bind_param('sii', $reason, $actorId, $order_id);
  if (!$stmt->execute()) throw new Exception('Update orders failed: '.$stmt->error);
  $stmt->close();

  // AUDIT: cancel order
  $toOrder = json_encode([
    'order_status'   => 'cancelled',
    'payment_status' => 'failed',
    'reason'         => $reason,
    'invoice'        => $invoice
  ], JSON_UNESCAPED_UNICODE);
  audit_log($mysqli, $actorId, 'order', $order_id, 'cancel', $fromOrder, $toOrder, 'cancel via API');

  // 2) Sync payments -> failed & 0
  $note = 'cancelled: '.$reason.' | from order '.$invoice;

  $stmt = $mysqli->prepare("SELECT id, status FROM payments WHERE order_id=? LIMIT 1");
  $stmt->bind_param('i', $order_id);
  $stmt->execute();
  $payRow = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if ($payRow) {
    $prevPayStatus = (string)($payRow['status'] ?? '');
    $stmt = $mysqli->prepare("
      UPDATE payments
      SET status='failed',
          amount_gross=0, discount=0, tax=0, shipping=0, amount_net=0,
          paid_at=NULL, note=?, method=?, updated_at=NOW()
      WHERE order_id=?
    ");
    $stmt->bind_param('ssi', $note, $method, $order_id);
    if (!$stmt->execute()) throw new Exception('Update payments failed: '.$stmt->error);
    $stmt->close();

    // AUDIT: payment status
    audit_log($mysqli, $actorId, 'payment', $order_id, 'update_status',
      $prevPayStatus !== '' ? $prevPayStatus : 'unknown', 'failed', 'sync cancel');
  } else {
    $stmt = $mysqli->prepare("
      INSERT INTO payments (order_id, method, status, amount_gross, discount, tax, shipping, amount_net, paid_at, note)
      VALUES (?, ?, 'failed', 0, 0, 0, 0, 0, NULL, ?)
    ");
    $stmt->bind_param('iss', $order_id, $method, $note);
    if (!$stmt->execute()) throw new Exception('Insert payments failed: '.$stmt->error);
    $stmt->close();

    // AUDIT: create payment (failed)
    audit_log($mysqli, $actorId, 'payment', $order_id, 'create_failed', '', 'failed', 'insert payment on cancel');
  }

  // 3) Notifications
  if ($customerId) {
    $linkCus = '/caffora-app1/public/customer/history.php?invoice='.$invoice;
    create_notification($mysqli, $customerId, 'customer',
      'Pesanan kamu dibatalkan ('.$invoice.'). Alasan: '.$reason, $linkCus);
    audit_log($mysqli, $actorId, 'user', $customerId, 'send_notification', '', "order#{$order_id}", 'cancel → customer');
  }
  $msgK = 'Pesanan dibatalkan: '.$custName.' ('.$invoice.') — '.$reason;
  create_notification($mysqli, null, 'karyawan', $msgK, '/caffora-app1/public/karyawan/orders.php');
  audit_log($mysqli, $actorId, 'user', 0, 'send_notification', '', "order#{$order_id}", 'cancel → karyawan');

  create_notification($mysqli, null, 'admin', '[ADMIN] '.$msgK, '/caffora-app1/public/admin/orders.php');
  audit_log($mysqli, $actorId, 'user', 0, 'send_notification', '', "order#{$order_id}", 'cancel → admin');

  $mysqli->commit();
  out(['ok'=>true]);
} catch (Throwable $e) {
  $mysqli->rollback();
  bad($e->getMessage(), 500);
}
