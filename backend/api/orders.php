<?php
// backend/api/orders.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';

$mysqli = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_errno) {
  echo json_encode(['ok'=>false,'error'=>'DB connect failed: '.$mysqli->connect_error], JSON_UNESCAPED_SLASHES);
  exit;
}
$mysqli->set_charset('utf8mb4');

function out(array $arr): void {
  echo json_encode($arr, JSON_UNESCAPED_SLASHES);
  exit;
}
function bad(string $msg, int $code=400): void {
  http_response_code($code);
  out(['ok'=>false,'error'=>$msg]);
}

/**
 * simpan notif ke tabel notifications
 */
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

/**
 * CFR + tanggal + bulan (3 huruf) + kode jam + random
 */
function generateInvoiceNo(): string {
  $prefix   = 'CFR';
  $day      = strtoupper(date('d'));
  $mon      = strtoupper(date('M'));
  $hourCode = chr(65 + (int)date('G'));           // 0 -> A, 1 -> B, ...
  $rand     = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
  return $prefix.$day.$mon.$hourCode.$rand;
}

$ALLOWED_ORDER_STATUS   = ['new','processing','ready','completed','cancelled'];
$ALLOWED_PAYMENT_STATUS = ['pending','paid','failed','refunded','overdue'];
$ALLOWED_METHOD         = ['cash','bank_transfer','qris','ewallet'];
$ALLOWED_SERVICE        = ['dine_in','take_away'];
const TAX_RATE = 0.11; // 11%

$action = $_GET['action'] ?? 'list';

/* ======================================================
   LIST
====================================================== */
if ($action === 'list') {
  $q              = trim((string)($_GET['q'] ?? ''));
  $order_status   = trim((string)($_GET['order_status'] ?? ''));
  $payment_status = trim((string)($_GET['payment_status'] ?? ''));

  $where  = [];
  $types  = '';
  $params = [];

  if ($q !== '') {
    $where[] = '(invoice_no LIKE ? OR customer_name LIKE ?)';
    $like = '%'.$q.'%';
    $params[] = $like;
    $params[] = $like;
    $types    .= 'ss';
  }
  if ($order_status !== '') {
    if (!in_array($order_status, $ALLOWED_ORDER_STATUS, true)) bad('Invalid order_status');
    $where[]  = 'order_status = ?';
    $params[] = $order_status;
    $types    .= 's';
  }
  if ($payment_status !== '') {
    if (!in_array($payment_status, $ALLOWED_PAYMENT_STATUS, true)) bad('Invalid payment_status');
    $where[]  = 'payment_status = ?';
    $params[] = $payment_status;
    $types    .= 's';
  }

  $sql = "SELECT id,user_id,invoice_no,customer_name,service_type,table_no,
                 total,subtotal,tax_amount,grand_total,
                 order_status,payment_status,payment_method,
                 created_at,updated_at
          FROM orders";
  if ($where) $sql .= ' WHERE '.implode(' AND ', $where);
  $sql .= ' ORDER BY created_at DESC, id DESC';

  if ($params) {
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) bad('Prepare failed: '.$mysqli->error, 500);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
  } else {
    $res = $mysqli->query($sql);
    if (!$res) bad('Query failed: '.$mysqli->error, 500);
  }

  $items = [];
  while ($row = $res->fetch_assoc()) {
    $row['id']          = (int)$row['id'];
    $row['user_id']     = $row['user_id'] !== null ? (int)$row['user_id'] : null;
    $row['total']       = (float)$row['total'];
    $row['subtotal']    = (int)$row['subtotal'];
    $row['tax_amount']  = (int)$row['tax_amount'];
    $row['grand_total'] = (int)$row['grand_total'];
    $items[] = $row;
  }

  out(['ok'=>true,'items'=>$items]);
}

/* ======================================================
   CREATE (checkout)
====================================================== */
if ($action === 'create') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') bad('Use POST', 405);

  $data = json_decode(file_get_contents('php://input'), true);
  if (!is_array($data)) bad('Invalid JSON body');

  $customer_name = trim((string)($data['customer_name'] ?? ''));
  $service_type  = (string)($data['service_type'] ?? '');
  $table_no      = trim((string)($data['table_no'] ?? ''));
  $pay_method    = (string)($data['payment_method'] ?? '');
  $pay_status    = (string)($data['payment_status'] ?? 'pending');
  $items         = $data['items'] ?? [];

  if ($customer_name === '') bad('Nama customer wajib');
  if (!in_array($service_type, $ALLOWED_SERVICE, true)) bad('Invalid service_type');

  if ($pay_method === '') $pay_method = 'cash';
  if (!in_array($pay_method, $ALLOWED_METHOD, true)) bad('Invalid payment_method');

  if (!in_array($pay_status, $ALLOWED_PAYMENT_STATUS, true)) bad('Invalid payment_status');
  if (!is_array($items) || !count($items)) bad('Items kosong');

  // hitung harga
  $subtotal = 0;
  foreach ($items as $it) {
    $qty   = (int)($it['qty'] ?? 0);
    $price = (float)($it['price'] ?? 0);
    if ($qty <= 0 || $price < 0) continue;
    $subtotal += $qty * $price;
  }
  if ($subtotal <= 0) bad('Total invalid');

  $tax_amount   = (int) round($subtotal * TAX_RATE);
  $grand_total  = $subtotal + $tax_amount;
  $total_legacy = $grand_total;

  // value buat payments
  $amount_gross = $grand_total;
  $amount_net   = $grand_total;
  $paid_at      = ($pay_status === 'paid') ? date('Y-m-d H:i:s') : null;

  $invoice_no   = generateInvoiceNo();
  $user_id      = $_SESSION['user_id'] ?? null;

  if ($service_type === 'take_away') $table_no = '';

  $mysqli->begin_transaction();
  try {
    // 1. simpan orders
    $stmt = $mysqli->prepare("
      INSERT INTO orders
        (user_id, invoice_no, customer_name, service_type, table_no,
         total, order_status, payment_status, payment_method,
         created_at, updated_at, subtotal, tax_amount, grand_total)
      VALUES
        (?, ?, ?, ?, ?, ?, 'new', ?, ?, NOW(), NOW(), ?, ?, ?)
    ");
    if (!$stmt) throw new Exception('Prepare(order) failed: '.$mysqli->error);

    $stmt->bind_param(
      'issssdssiii',
      $user_id,
      $invoice_no,
      $customer_name,
      $service_type,
      $table_no,
      $total_legacy,
      $pay_status,
      $pay_method,
      $subtotal,
      $tax_amount,
      $grand_total
    );
    if (!$stmt->execute()) throw new Exception('Insert order failed: '.$stmt->error);
    $order_id = (int)$stmt->insert_id;
    $stmt->close();

    // 2. simpan detail item
    $stmtItem = $mysqli->prepare("
      INSERT INTO order_items (order_id, menu_id, qty, price, discount, cogs_unit)
      VALUES (?, ?, ?, ?, 0.00, NULL)
    ");
    if (!$stmtItem) throw new Exception('Prepare(item) failed: '.$mysqli->error);

    foreach ($items as $it) {
      $menu_id = (int)($it['menu_id'] ?? $it['id'] ?? 0);
      $qty     = (int)($it['qty'] ?? 0);
      $price   = (float)($it['price'] ?? 0);
      if ($menu_id <= 0 || $qty <= 0) continue;
      $stmtItem->bind_param('iiid', $order_id, $menu_id, $qty, $price);
      if (!$stmtItem->execute()) throw new Exception('Insert item failed: '.$stmtItem->error);
    }
    $stmtItem->close();

    // 3. invoices (kalau dipakai)
    $stmtInv = $mysqli->prepare("INSERT INTO invoices (order_id, amount) VALUES (?, ?)");
    if (!$stmtInv) throw new Exception('Prepare(invoice) failed: '.$mysqli->error);
    $stmtInv->bind_param('id', $order_id, $grand_total);
    if (!$stmtInv->execute()) throw new Exception('Insert invoice failed: '.$stmtInv->error);
    $stmtInv->close();

    // 4. payments (AUTO)
    $stmtPay = $mysqli->prepare("
      INSERT INTO payments
        (order_id, method, status, amount_gross, discount, tax, shipping, amount_net, paid_at, note)
      VALUES
        (?, ?, ?, ?, 0, ?, 0, ?, ?, ?)
      ON DUPLICATE KEY UPDATE
        method       = VALUES(method),
        status       = VALUES(status),
        amount_gross = VALUES(amount_gross),
        tax          = VALUES(tax),
        amount_net   = VALUES(amount_net),
        paid_at      = VALUES(paid_at),
        note         = VALUES(note)
    ");
    if (!$stmtPay) throw new Exception('Prepare(payment) failed: '.$mysqli->error);

    $note = 'auto import from orders #'.$invoice_no;

    // 8 placeholder -> 'issdddss'
    $stmtPay->bind_param(
      'issdddss',
      $order_id,
      $pay_method,
      $pay_status,
      $amount_gross,
      $tax_amount,
      $amount_net,
      $paid_at,
      $note
    );
    if (!$stmtPay->execute()) throw new Exception('Insert payment failed: '.$stmtPay->error);
    $stmtPay->close();

    // 5. notif
    if ($user_id) {
      $msg  = 'Pesanan kamu sudah diterima. Invoice: '.$invoice_no;
      $link = '/caffora-app1/public/customer/history.php?invoice='.$invoice_no;
      create_notification($mysqli, (int)$user_id, 'customer', $msg, $link);
    }
    $msgStaff  = 'Pesanan baru dari '.$customer_name.' total Rp '.number_format($grand_total,0,',','.').' ('.$invoice_no.')';
    $linkStaff = '/caffora-app1/public/karyawan/orders.php';
    create_notification($mysqli, null, 'karyawan', $msgStaff, $linkStaff);

    $mysqli->commit();
    out([
      'ok'             => true,
      'id'             => $order_id,
      'invoice_no'     => $invoice_no,
      'subtotal'       => $subtotal,
      'tax_amount'     => $tax_amount,
      'grand_total'    => $grand_total,
      'payment_method' => $pay_method
    ]);
  } catch (Throwable $e) {
    $mysqli->rollback();
    bad($e->getMessage(), 500);
  }
}

/* ======================================================
   UPDATE (dipakai karyawan: ready, completed, cancelled, bayar)
====================================================== */
if ($action === 'update') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') bad('Use POST', 405);
  $data = json_decode(file_get_contents('php://input'), true);
  if (!is_array($data)) bad('Invalid JSON');

  $id = (int)($data['id'] ?? 0);
  if ($id <= 0) bad('Missing id');

  // ambil order lama
  $stmt = $mysqli->prepare("
    SELECT id, user_id, invoice_no, customer_name,
           grand_total, tax_amount, payment_method, payment_status, order_status
    FROM orders
    WHERE id = ?
    LIMIT 1
  ");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $res   = $stmt->get_result();
  $order = $res->fetch_assoc();
  $stmt->close();
  if (!$order) bad('Order not found', 404);

  $oldOrderStatus = $order['order_status'];
  $oldPayStatus   = $order['payment_status'];
  $userIdOrder    = $order['user_id'] !== null ? (int)$order['user_id'] : null;
  $invoiceNo      = (string)$order['invoice_no'];
  $customerName   = (string)$order['customer_name'];

  $fields = [];
  $params = [];
  $types  = '';

  $newOrderStatus = null;
  $newPayStatus   = null;
  $newPayMethod   = null;

  if (isset($data['order_status'])) {
    $val = (string)$data['order_status'];
    if (!in_array($val, $ALLOWED_ORDER_STATUS, true)) bad('Invalid order_status');
    $fields[] = "order_status = ?";
    $params[] = $val;
    $types   .= 's';
    $newOrderStatus = $val;
  }

  if (isset($data['payment_status'])) {
    $val = (string)$data['payment_status'];
    if (!in_array($val, $ALLOWED_PAYMENT_STATUS, true)) bad('Invalid payment_status');
    $fields[] = "payment_status = ?";
    $params[] = $val;
    $types   .= 's';
    $newPayStatus = $val;
  }

  if (array_key_exists('payment_method', $data)) {
    $val = $data['payment_method'];
    if ($val !== null && $val !== '') {
      if (!in_array($val, $ALLOWED_METHOD, true)) bad('Invalid payment_method');
      $fields[] = "payment_method = ?";
      $params[] = $val;
      $types   .= 's';
      $newPayMethod = $val;
    } else {
      $fields[] = "payment_method = NULL";
    }
  }

  if (!$fields) bad('No fields to update');

  // update orders
  $sql = "UPDATE orders SET ".implode(', ', $fields).", updated_at = NOW() WHERE id = ?";
  $params[] = $id;
  $types   .= 'i';

  $stmt = $mysqli->prepare($sql);
  if (!$stmt) bad('Prepare failed: '.$mysqli->error, 500);
  $stmt->bind_param($types, ...$params);
  if (!$stmt->execute()) bad('Update failed: '.$stmt->error, 500);
  $stmt->close();

  // nilai buat sinkron ke payments
  $grand = (float)$order['grand_total'];
  $tax   = (float)$order['tax_amount'];
  $pm    = $newPayMethod ?? $order['payment_method'] ?? 'cash';
  $ps    = $newPayStatus ?? $order['payment_status'] ?? 'pending';
  $os    = $newOrderStatus ?? $order['order_status'];

  // cek sudah ada payment?
  $stmt = $mysqli->prepare("SELECT id, status FROM payments WHERE order_id = ? LIMIT 1");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $resPay = $stmt->get_result();
  $payRow = $resPay->fetch_assoc();
  $stmt->close();

  /* ----------------------------
     JIKA PESANAN DIBATALKAN
  -----------------------------*/
  if ($os === 'cancelled') {
    $wasPaid    = ($oldPayStatus === 'paid' || $ps === 'paid');
    $newPayStat = $wasPaid ? 'refunded' : 'failed';
    $noteCancel = 'auto cancel from orders #'.$invoiceNo;

    if ($payRow) {
      if ($wasPaid) {
        // sudah bayar → refund, biarkan nominal
        $stmt = $mysqli->prepare("
          UPDATE payments
          SET status = ?, paid_at = NOW(), note = ?, method = ?
          WHERE order_id = ?
        ");
        $stmt->bind_param('sssi', $newPayStat, $noteCancel, $pm, $id);
      } else {
        // belum bayar → failed, nolkan angka
        $stmt = $mysqli->prepare("
          UPDATE payments
          SET status = ?, amount_gross = 0, discount = 0, tax = 0,
              shipping = 0, amount_net = 0, paid_at = NULL, note = ?, method = ?
          WHERE order_id = ?
        ");
        $stmt->bind_param('sssi', $newPayStat, $noteCancel, $pm, $id);
      }
      $stmt->execute();
      $stmt->close();
    } else {
      if ($wasPaid) {
        $stmt = $mysqli->prepare("
          INSERT INTO payments
            (order_id, method, status, amount_gross, discount, tax, shipping, amount_net, paid_at, note)
          VALUES
            (?, ?, 'refunded', ?, 0, ?, 0, ?, NOW(), ?)
        ");
        $stmt->bind_param('isdddss', $id, $pm, $grand, $tax, $grand, $noteCancel);
      } else {
        $stmt = $mysqli->prepare("
          INSERT INTO payments
            (order_id, method, status, amount_gross, discount, tax, shipping, amount_net, paid_at, note)
          VALUES
            (?, ?, 'failed', 0, 0, 0, 0, 0, NULL, ?)
        ");
        $stmt->bind_param('iss', $id, $pm, $noteCancel);
      }
      $stmt->execute();
      $stmt->close();
    }
  }
  /* ----------------------------
     JIKA BUKAN DIBATALKAN
     → di sini kasus kamu (id 78)
  -----------------------------*/
  else {
    if ($payRow) {
      // update baris payment yang sudah ada
      $stmt = $mysqli->prepare("
        UPDATE payments
        SET method = ?,
            status = ?,
            amount_gross = ?,
            tax = ?,
            amount_net = ?,
            paid_at = CASE WHEN ? = 'paid' THEN NOW() ELSE paid_at END,
            note = CONCAT('auto resync from orders #', ?)
        WHERE order_id = ?
      ");
      $stmt->bind_param(
        'ssdddssi',
        $pm,
        $ps,
        $grand,
        $tax,
        $grand,
        $ps,
        $invoiceNo,
        $id
      );
      $stmt->execute();
      $stmt->close();
    } else {
      // belum ada baris payment → bikin
      $stmt = $mysqli->prepare("
        INSERT INTO payments
          (order_id, method, status, amount_gross, discount, tax, shipping, amount_net, paid_at, note)
        VALUES
          (?, ?, ?, ?, 0, ?, 0, ?, CASE WHEN ? = 'paid' THEN NOW() ELSE NULL END,
           CONCAT('auto resync from orders #', ?))
      ");
      $stmt->bind_param(
        'issdddsss',
        $id,
        $pm,
        $ps,
        $grand,
        $tax,
        $grand,
        $ps,
        $invoiceNo
      );
      $stmt->execute();
      $stmt->close();
    }
  }

  // notif customer kalau status pesanan berubah
  if ($userIdOrder && $newOrderStatus !== null && $newOrderStatus !== $oldOrderStatus) {
    $historyLink = '/caffora-app1/public/customer/history.php?invoice='.$invoiceNo;
    if ($newOrderStatus === 'ready') {
      $msg = 'Pesanan kamu sudah ready selamat menikmati. ('.$invoiceNo.')';
      create_notification($mysqli, $userIdOrder, 'customer', $msg, $historyLink);
    } elseif ($newOrderStatus === 'completed') {
      $msg = 'Pesanan kamu sudah selesai ('.$invoiceNo.'). Terima kasih sudah pesan di Caffora.';
      create_notification($mysqli, $userIdOrder, 'customer', $msg, $historyLink);
    } elseif ($newOrderStatus === 'cancelled') {
      $msg = 'Pesanan kamu dibatalkan ('.$invoiceNo.').';
      create_notification($mysqli, $userIdOrder, 'customer', $msg, $historyLink);
    }
  }

  out(['ok'=>true]);
}

/* ======================================================
   DEFAULT
====================================================== */
bad('Invalid action', 404);
