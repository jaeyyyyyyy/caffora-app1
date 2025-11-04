<?php
// backend/api/orders_cancel.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once __DIR__ . '/../config.php';

$mysqli = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_errno) {
  echo json_encode(['ok'=>false,'error'=>'DB connect failed: '.$mysqli->connect_error], JSON_UNESCAPED_SLASHES);
  exit;
}
$mysqli->set_charset('utf8mb4');

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') bad('Use POST', 405);

$order_id = (int)($_POST['order_id'] ?? 0);
$reason   = trim((string)($_POST['reason'] ?? ''));
if ($order_id <= 0) bad('Missing order_id');
if ($reason === '') $reason = 'Pembatalan pesanan (belum bayar)';

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

// Hanya boleh dibatalkan jika pembayaran masih pending
if ((string)$order['payment_status'] !== 'pending') {
  bad('Pembatalan hanya untuk pesanan yang belum dibayar.');
}
// Tidak boleh batalkan jika sudah selesai/cancel
if (in_array((string)$order['order_status'], ['completed','cancelled'], true)) {
  bad('Pesanan tidak dapat dibatalkan.');
}

$invoice  = (string)$order['invoice_no'];
$userId   = $order['user_id'] !== null ? (int)$order['user_id'] : null;
$custName = (string)$order['customer_name'];
$method   = (string)$order['payment_method'];

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
  $by = (int)($_SESSION['user_id'] ?? 0);
  $stmt->bind_param('sii', $reason, $by, $order_id);
  if (!$stmt->execute()) throw new Exception('Update orders failed: '.$stmt->error);
  $stmt->close();

  // 2) Sinkron payments -> failed & nol
  $note = 'cancelled: '.$reason.' | from orders #'.$invoice;

  // ada baris payment?
  $stmt = $mysqli->prepare("SELECT id FROM payments WHERE order_id=? LIMIT 1");
  $stmt->bind_param('i', $order_id);
  $stmt->execute();
  $hasPay = (bool)$stmt->get_result()->fetch_assoc();
  $stmt->close();

  if ($hasPay) {
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
  } else {
    $stmt = $mysqli->prepare("
      INSERT INTO payments (order_id, method, status, amount_gross, discount, tax, shipping, amount_net, paid_at, note)
      VALUES (?, ?, 'failed', 0, 0, 0, 0, 0, NULL, ?)
    ");
    $stmt->bind_param('iss', $order_id, $method, $note);
    if (!$stmt->execute()) throw new Exception('Insert payments failed: '.$stmt->error);
    $stmt->close();
  }

  // 3) Notifikasi: customer + karyawan + admin
  if ($userId) {
    $linkCus = '/caffora-app1/public/customer/history.php?invoice='.$invoice;
    create_notification($mysqli, $userId, 'customer', 'Pesanan kamu dibatalkan ('.$invoice.'). Alasan: '.$reason, $linkCus);
  }
  $msgK = 'Pesanan dibatalkan: '.$custName.' ('.$invoice.') — '.$reason;
  create_notification($mysqli, null, 'karyawan', $msgK, '/caffora-app1/public/karyawan/orders.php');
  create_notification($mysqli, null, 'admin',    '[ADMIN] '.$msgK, '/caffora-app1/public/admin/orders.php');

  $mysqli->commit();
  out(['ok'=>true]);
} catch (Throwable $e) {
  $mysqli->rollback();
  bad($e->getMessage(), 500);
}
