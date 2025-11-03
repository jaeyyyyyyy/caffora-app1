<?php
// backend/api/menu.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';

$q        = trim((string)($_GET['q'] ?? ''));
$category = strtolower(trim((string)($_GET['category'] ?? ''))); // food|pastry|drink
$status   = ($_GET['status'] ?? '') === 'Ready' ? 'Ready' : '';

$where = [];
$params = [];
$types  = '';

if ($status) { $where[] = 'stock_status = ?'; $params[] = $status; $types .= 's'; }
if ($q !== '') {
  $where[] = '(name LIKE ? OR category LIKE ?)';
  $like = '%'.$q.'%'; $params[]=$like; $params[]=$like; $types .= 'ss';
}
if (in_array($category, ['food','pastry','drink'], true)) {
  $where[] = 'LOWER(category) = ?'; $params[] = $category; $types .= 's';
}

$sql = 'SELECT id, name, category, image, price, stock_status, created_at FROM menu';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY created_at DESC';

$items = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
  if ($params) { $stmt->bind_param($types, ...$params); }
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) {
    $img = trim((string)($row['image'] ?? ''));
    if ($img === '') {
      $image_url = rtrim(BASE_URL,'/') . '/public/assets/img/placeholder-1x1.png';
    } elseif (preg_match('~^https?://~i', $img) || str_starts_with($img,'data:')) {
      $image_url = $img;
    } else {
      // simpan relatif dari /public → bangun URL absolut
      $image_url = rtrim(BASE_URL,'/') . '/public/' . ltrim($img,'/');
    }

    $items[] = [
      'id'         => (int)$row['id'],
      'name'       => (string)$row['name'],
      'category'   => strtolower((string)$row['category']),
      'price'      => number_format((float)$row['price'], 0, ',', '.'),
      'price_int'  => (int)round((float)$row['price']),
      'stock'      => (string)$row['stock_status'],
      'image_url'  => $image_url,
      'created_at' => (string)$row['created_at'],
    ];
  }
  $stmt->close();
}

echo json_encode(['ok'=>true, 'items'=>$items], JSON_UNESCAPED_SLASHES);