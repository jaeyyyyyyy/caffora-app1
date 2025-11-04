<?php
// backend/checkout_place_order.php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_guard.php';
require_login(['customer']); // hanya customer yang boleh place order
header('Content-Type: application/json');

// ===== Audit helper (boleh diganti require lib/audit_helper.php) =====
if (!function_exists('audit_log')) {
  function audit_log(mysqli $db, int $actorId, string $entity, int $entityId, string $action, string $fromVal = '', string $toVal = '', string $remark = ''): bool {
    $sql = "INSERT INTO audit_logs (actor_id, entity, entity_id, action, from_val, to_val, remark)
            VALUES (?,?,?,?,?,?,?)";
    $stmt = $db->prepare($sql);
    if(!$stmt){ return false; }
    $stmt->bind_param('isissss', $actorId, $entity, $entityId, $action, $fromVal, $toVal, $remark);
    return $stmt->execute();
  }
}

try {
  $actorId        = (int)($_SESSION['user_id'] ?? 0);
  $customer_name  = trim($_POST['customer_name'] ?? '');
  $service_type   = trim($_POST['service_type'] ?? 'dine_in');   // dine_in|takeaway|delivery
  $table_no       = trim($_POST['table_no'] ?? '');
  $payment_method = trim($_POST['payment_method'] ?? 'cash');

  // Keranjang (session)
  $cart = $_SESSION['cart'] ?? [];
  if (!$cart) throw new Exception('Keranjang kosong.');

  // helper invoice
  $res = $conn->query("SELECT invoice_no FROM orders ORDER BY id DESC LIMIT 1");
  $last = $res?->fetch_assoc()['invoice_no'] ?? null;
  $n = 1; if ($last && preg_match('~(\d+)~',$last,$m)) $n = (int)$m[1]+1;
  $invoice_no = sprintf('INV-%03d', $n);

  $conn->begin_transaction();

  // hitung total dari DB (anti manipulasi)
  $grand = 0;
  $items = [];
  $stmtM = $conn->prepare("SELECT id,name,price FROM menu WHERE id=?");
  foreach ($cart as $c) {
    $menu_id = (int)($c['menu_id'] ?? 0);
    $qty     = max(1,(int)($c['qty'] ?? 1));
    $stmtM->bind_param('i',$menu_id);
    $stmtM->execute();
    $r = $stmtM->get_result()->fetch_assoc();
    if(!$r) throw new Exception("Menu $menu_id tidak ditemukan.");
    $price = (int)$r['price'];
    $sub   = $price*$qty;
    $grand += $sub;
    $items[] = ['menu_id'=>$menu_id,'qty'=>$qty,'price'=>$price,'subtotal'=>$sub,'name'=>$r['name']];
  }
  $stmtM->close();

  // simpan orders
  $order_status='new'; $payment_status='pending';
  $stmtO=$conn->prepare("INSERT INTO orders
    (invoice_no,customer_name,service_type,table_no,total,payment_method,order_status,payment_status,created_at)
    VALUES (?,?,?,?,?,?,?,?,NOW())");
  $stmtO->bind_param('ssssisss',$invoice_no,$customer_name,$service_type,$table_no,$grand,$payment_method,$order_status,$payment_status);
  if(!$stmtO->execute()) throw new Exception('Gagal menyimpan pesanan.');
  $order_id = $conn->insert_id;
  $stmtO->close();

  // simpan order_items
  $stmtI=$conn->prepare("INSERT INTO order_items (order_id,menu_id,qty,price,subtotal) VALUES (?,?,?,?,?)");
  foreach($items as $it){
    $stmtI->bind_param('iiiii',$order_id,$it['menu_id'],$it['qty'],$it['price'],$it['subtotal']);
    if(!$stmtI->execute()) throw new Exception('Gagal menyimpan item.');
  }
  $stmtI->close();

  // ===== AUDIT: order created (catat ringkas + detail seperlunya)
  $toVal = json_encode([
    'invoice'=>$invoice_no,
    'customer'=>$customer_name,
    'service_type'=>$service_type,
    'table_no'=>$table_no,
    'payment_method'=>$payment_method,
    'total'=>$grand,
    'items_count'=>count($items)
  ], JSON_UNESCAPED_UNICODE);

  audit_log($conn, $actorId, 'order', (int)$order_id, 'create', '', $toVal, 'place order');

  // (opsional) catat payment_status pending
  audit_log($conn, $actorId, 'payment', (int)$order_id, 'create_intent', '', 'pending', 'initial payment status');

  // kosongkan keranjang
  unset($_SESSION['cart']);

  $conn->commit();
  echo json_encode(['ok'=>true,'invoice'=>$invoice_no,'redirect'=>BASE_URL.'/public/customer/history.php']);
} catch(Throwable $e){
  if ($conn && $conn->errno === 0) { /* ignore */ } else { $conn->rollback(); }
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
