<?php
// public/admin/finance.php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../../backend/config.php';

/* ===== Guard role: admin ===== */
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
  header('Location: ' . BASE_URL . '/public/login.html');
  exit;
}

$userName  = htmlspecialchars($_SESSION['user_name']  ?? '', ENT_QUOTES, 'UTF-8');
$userEmail = htmlspecialchars($_SESSION['user_email'] ?? '', ENT_QUOTES, 'UTF-8');

/* ============================================================
   RENTANG TANGGAL
   ============================================================ */
$range     = $_GET['range'] ?? '7d';
$today     = new DateTime('today');
$endDate   = clone $today;
$startDate = clone $today;

if ($range === 'today') {
  // today 00:00:00 - 23:59:59
} elseif ($range === '7d') {
  $startDate->modify('-6 day');
} elseif ($range === '30d') {
  $startDate->modify('-29 day');
} elseif ($range === 'custom') {
  $from = $_GET['from'] ?? '';
  $to   = $_GET['to']   ?? '';
  $tmpStart = $from ? DateTime::createFromFormat('Y-m-d', $from) : null;
  $tmpEnd   = $to   ? DateTime::createFromFormat('Y-m-d', $to)   : null;
  if ($tmpStart && $tmpEnd) {
    $startDate = $tmpStart;
    $endDate   = $tmpEnd;
  } else {
    $range     = '7d';
    $startDate = (clone $today)->modify('-6 day');
  }
} else {
  $range = '7d';
  $startDate->modify('-6 day');
}

$startStr = $startDate->format('Y-m-d 00:00:00');
$endStr   = $endDate->format('Y-m-d 23:59:59');

/* ============================================================
   EXPORT CSV (ikut periode aktif) — LENGKAP
   ============================================================ */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  $file = sprintf('finance_%s-%s.csv', $startDate->format('Ymd'), $endDate->format('Ymd'));

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="'.$file.'"');

  $out = fopen('php://output', 'w');

  // BOM UTF-8 agar Excel aman
  fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

  fputcsv($out, [
    'Order ID','Invoice','Waktu','Customer',
    'Order Items (nama x qty)','Total (IDR)',
    'Payment Method','Service Type','Order Status','Payment Status'
  ]);

  $sqlCsv = "
    SELECT
      o.id AS order_id,
      o.invoice_no,
      DATE_FORMAT(o.created_at, '%Y-%m-%d %H:%i') AS waktu,
      COALESCE(NULLIF(o.customer_name,''), u.name, 'Guest') AS customer,
      GROUP_CONCAT(CONCAT(m.name,' x',oi.qty) ORDER BY oi.id SEPARATOR '; ') AS items,
      COALESCE(NULLIF(o.grand_total,0), o.total) AS total_idr,
      o.payment_method,
      o.service_type,
      o.order_status,
      o.payment_status
    FROM orders o
    LEFT JOIN users u        ON u.id = o.user_id
    LEFT JOIN order_items oi ON oi.order_id = o.id
    LEFT JOIN menu m         ON m.id = oi.menu_id
    WHERE o.created_at BETWEEN ? AND ?
    GROUP BY o.id, o.invoice_no, o.created_at, u.name,
             o.total, o.grand_total, o.payment_method, o.service_type,
             o.order_status, o.payment_status
    ORDER BY o.created_at DESC
  ";
  $stmtCsv = $conn->prepare($sqlCsv);
  $stmtCsv->bind_param('ss', $startStr, $endStr);
  $stmtCsv->execute();
  $resCsv = $stmtCsv->get_result();

  while ($r = $resCsv->fetch_assoc()) {
    fputcsv($out, [
      $r['order_id'],
      $r['invoice_no'],
      $r['waktu'],
      $r['customer'],
      $r['items'] ?? '',
      (int)$r['total_idr'],
      $r['payment_method'],
      $r['service_type'],
      $r['order_status'],
      $r['payment_status']
    ]);
  }

  $stmtCsv->close();
  fclose($out);
  exit;
}

/* ============================================================
   REVENUE HARIAN (paid) — ikut periode
   ============================================================ */
$labels = [];
$map    = [];

$period = new DatePeriod(
  $startDate,
  new DateInterval('P1D'),
  (clone $endDate)->modify('+1 day')
);

foreach ($period as $d) {
  $key       = $d->format('Y-m-d');
  $labels[]  = $key;
  $map[$key] = 0.0;
}

$stmt = $conn->prepare("
  SELECT DATE(created_at) AS d, SUM(total) AS s
  FROM orders
  WHERE created_at BETWEEN ? AND ?
    AND payment_status = 'paid'
  GROUP BY DATE(created_at)
  ORDER BY d ASC
");
$stmt->bind_param('ss', $startStr, $endStr);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
  $map[$row['d']] = (float)$row['s'];
}
$stmt->close();

$revenue = [];
foreach ($labels as $d) {
  $revenue[] = $map[$d] ?? 0;
}
$totalRevenue = array_sum($revenue);

/* count paid & avg */
$stmt2 = $conn->prepare("
  SELECT COUNT(*) AS c
  FROM orders
  WHERE created_at BETWEEN ? AND ?
    AND payment_status='paid'
");
$stmt2->bind_param('ss', $startStr, $endStr);
$stmt2->execute();
$row2 = $stmt2->get_result()->fetch_assoc();
$stmt2->close();

$ordersPaidCount = (int)($row2['c'] ?? 0);
$avgOrder        = $ordersPaidCount ? ($totalRevenue / $ordersPaidCount) : 0;

/* ============================================================
   RINGKASAN 4 KARTU (ikut periode)
   ============================================================ */
$statusSummary = ['paid' => 0, 'pending' => 0, 'cancel' => 0, 'done' => 0];

$qs = $conn->prepare("
  SELECT 
    SUM(CASE WHEN payment_status='paid'    THEN 1 ELSE 0 END) AS cnt_paid,
    SUM(CASE WHEN payment_status='pending' THEN 1 ELSE 0 END) AS cnt_pending,
    SUM(
      CASE 
        WHEN LOWER(order_status) IN ('cancelled','canceled','cancel','returned','return','failed')
          OR payment_status='failed'
      THEN 1 ELSE 0 END
    ) AS cnt_cancel,
    SUM(CASE WHEN LOWER(order_status)='completed' THEN 1 ELSE 0 END) AS cnt_done
  FROM orders
  WHERE created_at BETWEEN ? AND ?
");
$qs->bind_param('ss', $startStr, $endStr);
$qs->execute();
$qr = $qs->get_result()->fetch_assoc();
$qs->close();

$statusSummary['paid']    = (int)($qr['cnt_paid']    ?? 0);
$statusSummary['pending'] = (int)($qr['cnt_pending'] ?? 0);
$statusSummary['cancel']  = (int)($qr['cnt_cancel']  ?? 0);
$statusSummary['done']    = (int)($qr['cnt_done']    ?? 0);

/* ============================================================
   TOP MENU TERLARIS — ikut periode
   ============================================================ */
$topMenus = [];

$sqlTop = "
  SELECT 
    m.id, m.name, m.image,
    COALESCE(SUM(oi.qty),0) AS sold_qty,
    COALESCE(SUM(oi.qty * IFNULL(oi.price, m.price)),0) AS sold_amount
  FROM order_items oi
  INNER JOIN orders o ON o.id = oi.order_id
  INNER JOIN menu   m ON m.id = oi.menu_id
  WHERE o.created_at BETWEEN ? AND ?
    AND o.payment_status = 'paid'
  GROUP BY m.id, m.name, m.image
  ORDER BY sold_qty DESC, sold_amount DESC
  LIMIT 6
";
$stmt3 = $conn->prepare($sqlTop);
$stmt3->bind_param('ss', $startStr, $endStr);
$stmt3->execute();
$resTop = $stmt3->get_result();

while ($row = $resTop->fetch_assoc()) {
  $imgRaw           = (string)($row['image'] ?? '');
  $row['img_url']   = $imgRaw
    ? BASE_URL . '/public/' . ltrim($imgRaw, '/')
    : BASE_URL . '/public/assets/img/menu-placeholder.png';
  $topMenus[]       = $row;
}
$stmt3->close();

/* ============================================================
   DISTRIBUSI STATUS
   ============================================================ */
$dist = [
  'new'        => ['orders' => 0, 'qty' => 0],
  'processing' => ['orders' => 0, 'qty' => 0],
  'ready'      => ['orders' => 0, 'qty' => 0],
  'completed'  => ['orders' => 0, 'qty' => 0],
  'cancelled'  => ['orders' => 0, 'qty' => 0],
];

$stmtDist = $conn->prepare("
  SELECT 
    CASE 
      WHEN LOWER(o.order_status) IN ('cancelled','canceled','cancel','return','returned','failed') 
        THEN 'cancelled'
      ELSE LOWER(o.order_status)
    END AS st,
    COUNT(DISTINCT o.id)    AS orders_cnt,
    COALESCE(SUM(oi.qty),0) AS items_qty
  FROM orders o
  LEFT JOIN order_items oi ON oi.order_id = o.id
  WHERE o.created_at BETWEEN ? AND ?
    AND (
      LOWER(o.order_status) IN ('new','processing','ready','completed')
      OR LOWER(o.order_status) IN ('cancelled','canceled','cancel','return','returned','failed')
    )
  GROUP BY st
");
$stmtDist->bind_param('ss', $startStr, $endStr);
$stmtDist->execute();
$resDist = $stmtDist->get_result();

while ($r = $resDist->fetch_assoc()) {
  $k = $r['st'];
  if (isset($dist[$k])) {
    $dist[$k]['orders'] = (int)($r['orders_cnt'] ?? 0);
    $dist[$k]['qty']    = (int)($r['items_qty']  ?? 0);
  }
}
$stmtDist->close();

$distLabels = ['New','Processing','Ready','Completed','Cancelled'];
$distOrders = [
  $dist['new']['orders'],
  $dist['processing']['orders'],
  $dist['ready']['orders'],
  $dist['completed']['orders'],
  $dist['cancelled']['orders'],
];

/* ============================================================
   TRANSAKSI TERBARU — pakai orders.customer_name
   ============================================================ */
$latestTx = [];

$stmt4 = $conn->prepare("
  SELECT 
    o.id,
    o.total,
    o.payment_status,
    o.created_at,
    COALESCE(NULLIF(o.customer_name,''), u.name, 'Guest') AS customer_name
  FROM orders o
  LEFT JOIN users u ON u.id = o.user_id
  WHERE o.created_at BETWEEN ? AND ?
  ORDER BY o.created_at DESC
  LIMIT 6
");
$stmt4->bind_param('ss', $startStr, $endStr);
$stmt4->execute();
$res4 = $stmt4->get_result();

while ($row = $res4->fetch_assoc()) {
  $latestTx[] = $row;
}
$stmt4->close();
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Finance — Caffora</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script src="https://code.iconify.design/2/2.2.1/iconify.min.js"></script>

  <style>
   :root{                                      /* Root CSS variable */
    --gold:#FFD54F;                         /* Warna emas utama */
    --gold-border:#f7d78d;                  /* Border emas */
    --brown:#4B3F36;                        /* Warna coklat utama */
    --ink:#0f172a;                          /* Warna teks utama */
    --radius:18px;                          /* Radius global */
    --sidebar-w:320px;                      /* Lebar sidebar */
    --soft:#fff7d1;                         /* Warna latar lembut */
    --hover:#ffefad;                        /* Warna hover */
    --btn-radius:14px;                      /* Radius tombol */
    --input-border:#E8E2DA;                 /* Border input */
}

*,:before,:after{ box-sizing:border-box; } /* Box sizing global */

body{                                       /* Body halaman */
    background:#FAFAFA;                     /* Background utama */
    color:var(--ink);                       /* Warna teks */
    font-family:Inter,system-ui,Segoe UI,Roboto,Arial; /* Font utama */
    font-weight:500;                        /* Ketebalan font */
}

/* ===== Sidebar ===== */
.sidebar{                                   /* Container sidebar */
    position:fixed;                         /* Posisi fixed */
    left:-320px;                            /* Sidebar tersembunyi */
    top:0;                                  /* Posisi atas */
    bottom:0;                               /* Posisi bawah */
    width:var(--sidebar-w);                 /* Lebar sidebar */
    background:#fff;                        /* Warna sidebar */
    border-right:1px solid rgba(0,0,0,.05); /* Border kanan */
    transition:left .25s ease;              /* Animasi geser */
    z-index:1050;                           /* Layer prioritas */
    padding:16px 18px;                      /* Padding */
    overflow-y:auto;                        /* Scroll vertikal */
}
.sidebar.show{ left:0; }                    /* Sidebar tampil */

.sidebar-head{                              /* Header sidebar */
    display:flex;                           /* Flex layout */
    align-items:center;                     /* Tengah vertikal */
    justify-content:space-between;          /* Spasi antar item */
    gap:10px;                               /* Jarak item */
    margin-bottom:10px;                     /* Spasi bawah */
}

.sidebar-inner-toggle,
.sidebar-close-btn{                         /* Tombol sidebar */
    background:transparent;                 /* Tanpa background */
    border:0;                               /* Tanpa border */
    width:40px;                             /* Lebar tombol */
    height:36px;                            /* Tinggi tombol */
    display:grid;                           /* Grid */
    place-items:center;                     /* Tengah konten */
}

.hamb-icon{                                 /* Icon hamburger */
    width:24px;                             /* Lebar icon */
    height:20px;                            /* Tinggi icon */
    display:flex;                           /* Flex */
    flex-direction:column;                  /* Vertikal */
    justify-content:space-between;          /* Jarak bar */
    gap:4px;                                /* Jarak antar bar */
}
.hamb-icon span{                            /* Bar hamburger */
    height:2px;                             /* Tinggi bar */
    background:var(--brown);                /* Warna bar */
    border-radius:99px;                     /* Ujung bulat */
}

.sidebar .nav-link{                         /* Link menu */
    display:flex;                           /* Flex */
    align-items:center;                     /* Tengah vertikal */
    gap:12px;                               /* Jarak icon-teks */
    padding:12px 14px;                      /* Padding */
    border-radius:16px;                     /* Radius */
    font-weight:600;                        /* Tebal teks */
    color:#111;                             /* Warna teks */
    text-decoration:none;                   /* Hilangkan underline */
}
.sidebar .nav-link:hover{
    background:rgba(255,213,79,0.25);       /* Hover menu */
}
.sidebar hr{
    border-color:rgba(0,0,0,.05);           /* Warna garis */
    opacity:1;                              /* Opacity */
}

/* Backdrop */
.backdrop-mobile{                           /* Backdrop mobile */
    position:fixed;                         /* Fixed */
    inset:0;                                /* Full layar */
    background:rgba(0,0,0,.25);             /* Background gelap */
    z-index:1040;                           /* Layer */
    display:none;                           /* Tersembunyi */
}
.backdrop-mobile.active{ display:block; }  /* Backdrop aktif */

/* ===== content ===== */
.content{ margin-left:0; padding:16px 14px 40px; } /* Konten utama */

/* ===== topbar ===== */
.topbar{                                    /* Topbar */
    display:flex;                           /* Flex */
    align-items:center;                     /* Tengah vertikal */
    gap:12px;                               /* Jarak item */
    margin-bottom:16px;                     /* Spasi bawah */
}
.btn-menu{                                  /* Tombol menu */
    background:transparent;                 /* Transparan */
    border:0;                               /* Tanpa border */
    width:40px;height:38px;                 /* Ukuran */
    display:grid;place-items:center;        /* Tengah */
    flex:0 0 auto;                          /* Ukuran tetap */
}

/* ===== SEARCH ===== */
.search-box{ position:relative; flex:1 1 auto; min-width:0; } /* Wrapper search */
.search-input{
    height:46px; width:100%;                /* Ukuran input */
    border-radius:9999px;                   /* Rounded */
    padding-left:16px; padding-right:44px;  /* Padding */
    border:1px solid #e5e7eb; background:#fff; /* Border & bg */
    outline:none !important; transition:border-color .12s ease; /* Fokus */
}
.search-input:focus{
    border-color:var(--gold-soft) !important; /* Warna fokus */
    background:#fff;                         /* Background */
    box-shadow:none !important;              /* Hilangkan shadow */
}
.search-icon{
    position:absolute; right:16px; top:50%; /* Posisi icon */
    transform:translateY(-50%);              /* Tengah vertikal */
    font-size:1.1rem; color:var(--brown); cursor:pointer; /* Style */
}
.search-suggest{
    position:absolute; top:100%; left:0;    /* Posisi dropdown */
    margin-top:6px; background:#fff;         /* Background */
    border:1px solid rgba(247,215,141,.8);   /* Border */
    border-radius:16px;                      /* Radius */
    box-shadow:0 12px 28px rgba(0,0,0,.08);  /* Shadow */
    width:100%; max-height:280px;            /* Ukuran */
    overflow-y:auto; display:none; z-index:40; /* Scroll & layer */
}
.search-suggest.visible{ display:block; }   /* Tampil */
.search-suggest .item{
    padding:10px 14px 6px;                   /* Padding item */
    border-bottom:1px solid rgba(0,0,0,.03); /* Border bawah */
    cursor:pointer;                          /* Pointer */
}
.search-suggest .item:last-child{ border-bottom:0; } /* Item terakhir */
.search-suggest .item:hover{ background:#fffbea; }   /* Hover */
.search-suggest .item small{
    display:block; color:#6b7280; font-size:.74rem; margin-top:2px; /* Subteks */
}
.search-empty{
    padding:12px 14px; color:#6b7280; font-size:.8rem; /* Empty state */
}
   
    .top-actions{ display:flex; align-items:center; gap:14px; flex:0 0 auto; } /* Wrapper aksi topbar */

.icon-btn{                                                               /* Tombol icon */
  width:38px; height:38px; border-radius:999px;                           /* Ukuran & bentuk */
  display:flex; align-items:center; justify-content:center;               /* Tengah konten */
  color:var(--brown); text-decoration:none; background:transparent;        /* Warna & bg */
  outline:none;                                                            /* Hilangkan outline */
}
.icon-btn:focus,.icon-btn:active{ outline:none; box-shadow:none; color:var(--brown); } /* Fokus & aktif */

#btnBell{ position:relative; }                                             /* Wrapper lonceng */

#badgeNotif.notif-dot{                                                      /* Titik notifikasi */
  position:absolute; top:3px; right:5px;                                   /* Posisi badge */
  width:8px; height:8px;                                                    /* Ukuran */
  background:#4b3f36;                                                       /* Warna badge */
  border-radius:50%;                                                        /* Bulat */
  display:inline-block;                                                     /* Inline */
  box-shadow:0 0 0 1.5px #fff;                                               /* Outline putih */
}
#badgeNotif.d-none{ display:none !important; }                              /* Sembunyikan badge */

/* Cards */
.cardx{                                                                     /* Card utama */
  background:#fff;                                                          /* Background */
  border:1px solid var(--gold-border);                                      /* Border */
  border-radius:var(--radius);                                               /* Radius */
  padding:18px;                                                             /* Padding */
  min-width:0;                                                              /* Cegah overflow */
}

.kpi .value{                                                                /* Nilai KPI */
  font-size:2rem;                                                           /* Ukuran font */
  font-weight:700;                                                          /* Tebal */
  letter-spacing:.2px;                                                      /* Spasi huruf */
}

.summary-grid{                                                              /* Grid ringkasan */
  display:grid;                                                             /* Grid */
  grid-template-columns:1fr 1fr;                                             /* Dua kolom */
  gap:18px;                                                                 /* Jarak grid */
}

.summary-card{                                                              /* Card ringkasan */
  background:#fff;                                                          /* Background */
  border:1px solid var(--gold-border);                                      /* Border */
  border-radius:20px;                                                       /* Radius */
  padding:18px 20px;                                                        /* Padding */
  min-width:0;                                                              /* Cegah overflow */
}

.summary-card .label{                                                       /* Label ringkasan */
  color:#6b7280;                                                            /* Warna teks */
  font-weight:600;                                                          /* Tebal */
}

.summary-card .value{                                                       /* Nilai ringkasan */
  margin-top:6px;                                                           /* Spasi atas */
  font-size:2.05rem;                                                        /* Ukuran font */
  font-weight:700;                                                          /* Tebal */
  color:#0f172a;                                                            /* Warna teks */
  line-height:1;                                                            /* Tinggi baris */
}

.top-menu-item{                                                             /* Item menu atas */
  display:flex;                                                             /* Flex */
  align-items:center;                                                       /* Tengah vertikal */
  gap:14px;                                                                 /* Jarak item */
  padding:12px 10px;                                                        /* Padding */
  border-bottom:1px solid rgba(17,24,39,.05);                                /* Border bawah */
}
.top-menu-item:last-child{ border-bottom:0; }                               /* Item terakhir */

.top-menu-thumb{                                                            /* Thumbnail menu */
  width:46px;                                                               /* Lebar */
  height:46px;                                                              /* Tinggi */
  border-radius:16px;                                                       /* Radius */
  overflow:hidden;                                                          /* Potong overflow */
  background:#f3f4f6;                                                       /* Background */
  flex:0 0 auto;                                                            /* Ukuran tetap */
}
.top-menu-thumb img{                                                        /* Gambar thumbnail */
  width:100%;                                                               /* Lebar penuh */
  height:100%;                                                              /* Tinggi penuh */
  object-fit:cover;                                                         /* Crop proporsional */
  display:block;                                                            /* Block */
}

.top-menu-name{                                                             /* Nama menu */
  font-weight:600;                                                          /* Tebal */
  white-space:nowrap;                                                       /* Satu baris */
  overflow:hidden;                                                          /* Sembunyikan overflow */
  text-overflow:ellipsis;                                                    /* Ellipsis */
  max-width:100%;                                                           /* Lebar maksimal */
}

.top-menu-sub{                                                              /* Subjudul menu */
  font-size:.78rem;                                                         /* Ukuran font */
  color:#6b7280;                                                            /* Warna teks */
  white-space:nowrap;                                                       /* Satu baris */
}

/* Range + Download */
.range-wrap{                                                                /* Wrapper range & tombol */
  display:flex;                                                             /* Flex */
  gap:10px;                                                                 /* Jarak */
  align-items:center;                                                       /* Tengah vertikal */
  flex-wrap:wrap;                                                           /* Bungkus */
  justify-content:flex-start;                                                /* Rata kiri */
}

.select-ghost{                                                              /* Select tersembunyi */
  position:absolute !important;                                              /* Absolut */
  width:1px;                                                                /* Lebar kecil */
  height:1px;                                                               /* Tinggi kecil */
  opacity:0;                                                                /* Transparan */
  pointer-events:none;                                                      /* Non-aktif */
  left:-9999px;                                                             /* Geser jauh */
  top:auto;                                                                 /* Posisi default */
  overflow:hidden;                                                          /* Sembunyikan */
}

.select-custom{                                                             /* Wrapper custom select */
  position:relative;                                                        /* Relatif */
  display:inline-block;                                                     /* Inline block */
  max-width:100%;                                                           /* Lebar maksimal */
}

.select-toggle{                                                             /* Tombol select */
  width:200px;                                                              /* Lebar */
  max-width:100%;                                                           /* Responsif */
  height:42px;                                                              /* Tinggi */
  display:flex;                                                             /* Flex */
  align-items:center;                                                       /* Tengah vertikal */
  justify-content:space-between;                                            /* Spasi kiri-kanan */
  gap:10px;                                                                 /* Jarak */
  background:#fff;                                                          /* Background */
  border:1px solid #e5e7eb;                                                  /* Border */
  border-radius:12px;                                                       /* Radius */
  padding:0 14px;                                                           /* Padding */
  cursor:pointer;                                                           /* Pointer */
  user-select:none;                                                         /* Non-select */
  outline:0;                                                                /* Hilangkan outline */
}
.select-toggle:focus{ border-color:#ffd54f; }                               /* Fokus select */

.select-caret{                                                              /* Icon caret */
  font-size:16px;                                                           /* Ukuran */
  color:#111;                                                               /* Warna */
}

.select-menu{                                                               /* Menu dropdown */
  position:absolute;                                                        /* Absolut */
  top:46px;                                                                 /* Posisi bawah toggle */
  left:0;                                                                   /* Rata kiri */
  z-index:1060;                                                             /* Layer */
  background:#fff;                                                          /* Background */
  border:1px solid rgba(247,215,141,.9);                                     /* Border */
  border-radius:14px;                                                       /* Radius */
  box-shadow:0 12px 28px rgba(0,0,0,.08);                                    /* Shadow */
  min-width:100%;                                                           /* Lebar minimal */
  display:none;                                                             /* Tersembunyi */
  padding:6px;                                                              /* Padding */
  max-height:280px;                                                         /* Tinggi maksimum */
  overflow:auto;                                                            /* Scroll */
}
.select-menu.show{ display:block; }                                         /* Menu tampil */

.select-item{                                                               /* Item select */
  padding:10px 12px;                                                        /* Padding */
  border-radius:10px;                                                       /* Radius */
  cursor:pointer;                                                           /* Pointer */
  font-weight:600;                                                          /* Tebal */
  color:#374151;                                                            /* Warna teks */
}
.select-item:hover{ background:var(--hover); }                              /* Hover item */
.select-item.active{ background:var(--soft); }                              /* Item aktif */

.btn-download{                                                              /* Tombol download */
  background-color:var(--gold);                                             /* Background */
  color:var(--brown);                                                       /* Warna teks */
  border:0;                                                                 /* Tanpa border */
  border-radius:var(--btn-radius);                                          /* Radius */
  font-family:Arial, Helvetica, sans-serif;                                 /* Font */
  font-weight:600;                                                          /* Tebal */
  font-size:.9rem;                                                          /* Ukuran font */
  padding:10px 18px;                                                        /* Padding */
  display:inline-flex;                                                      /* Inline flex */
  align-items:center;                                                       /* Tengah vertikal */
  gap:8px;                                                                  /* Jarak icon-teks */
  line-height:1;                                                            /* Tinggi baris */
}
.btn-download:hover{ filter:brightness(.97); }                              /* Hover tombol */

/* Chart */
#revChart{                                  /* Chart revenue */
  width:100% !important;                    /* Lebar penuh */
  max-height:330px;                         /* Tinggi maksimal */
}

.chart-wrapper{                             /* Wrapper chart */
  min-height:220px;                         /* Tinggi minimum */
  height:clamp(220px, 38vh, 360px);         /* Tinggi responsif */
}

/* ====== Responsive ====== */
@media (min-width:992px){                  /* Desktop besar */
  .content{
    padding:20px 26px 50px;                 /* Padding konten */
  }
  .search-box{ max-width:1100px; }          /* Lebar maksimal search */
}

@media (min-width:768px) and (max-width:991.98px){ /* Tablet */
  .content{
    padding:18px 16px 60px;                 /* Padding tablet */
  }
  .summary-grid{
    grid-template-columns:1fr 1fr;          /* Dua kolom */
    gap:14px;                               /* Jarak grid */
  }
  .summary-card{ padding:16px; }            /* Padding card */
  .summary-card .value{ font-size:1.8rem; } /* Ukuran nilai */
  #revChart{ max-height:300px; }             /* Tinggi chart */
  .chart-wrapper{ height:clamp(220px, 34vh, 320px); } /* Tinggi chart tablet */
}

.search-box{ min-width:0; }                 /* Cegah overflow search */

@media (max-width:575.98px){                /* Mobile */
  .content{
    padding:16px 14px 70px;                 /* Padding mobile */
  }

  .summary-grid{
    grid-template-columns:1fr;              /* Satu kolom */
    gap:12px;                               /* Jarak grid */
  }
  .cardx{ padding:16px; }                   /* Padding card */
  #revChart{ max-height:240px !important; } /* Tinggi chart mobile */
  .chart-wrapper{ height:240px; }            /* Tinggi wrapper */

  .topbar{
    padding:8px 0;                          /* Padding topbar */
    gap:8px;                                /* Jarak item */
  }
  .btn-menu{
    width:36px;                             /* Lebar tombol */
    height:34px;                            /* Tinggi tombol */
    flex:0 0 36px;                          /* Ukuran tetap */
    margin-left:-2px;                      /* Geser kiri */
    order:1;                                /* Urutan */
  }
  .top-actions{
    order:3;                                /* Urutan */
    flex:0 0 auto;                          /* Ukuran tetap */
    gap:8px;                                /* Jarak */
  }
  .icon-btn{
    width:34px;                             /* Lebar icon */
    height:34px;                            /* Tinggi icon */
  }
  .search-box{
    order:2;                                /* Urutan */
    flex:1 1 auto;                          /* Fleksibel */
    max-width:clamp(140px, calc(100% - 120px), 100%); /* Lebar adaptif */
  }
  .search-input{ height:40px; }             /* Tinggi input */

  /* Range + Download mobile: grid 2 kolom, konsisten, tanpa duplikat blok */
  .range-wrap{
    display:grid;                           /* Grid */
    grid-template-columns:1fr 1fr;          /* Dua kolom */
    gap:8px;                                /* Jarak */
  }
  .range-wrap .select-custom{
    width:100%;                             /* Lebar penuh */
    flex:unset;                             /* Reset flex */
  }
  #btnDownload{
    flex:unset;                             /* Reset flex */
    width:100%;                             /* Lebar penuh */
    height:44px;                            /* Tinggi tombol */
    display:inline-flex;                    /* Inline flex */
    align-items:center;                     /* Tengah vertikal */
    justify-content:center;                 /* Tengah horizontal */
    gap:8px;                                /* Jarak */
    border:1px solid rgba(0,0,0,.06);       /* Border */
    border-radius:14px;                     /* Radius */
    padding:0 12px;                         /* Padding */
    font-weight:700;                        /* Tebal */
  }
  #btnDownload svg{
    width:18px;                             /* Ukuran icon */
    height:18px;                            /* Tinggi icon */
  }
}
  </style>
</head>
<body>


<div id="backdrop" class="backdrop-mobile"></div>
      <!-- sidebar -->
<aside class="sidebar" id="sideNav">
  <div class="sidebar-head">
    <button class="sidebar-inner-toggle" id="toggleSidebarInside" aria-label="Tutup menu"></button>
    <button class="sidebar-close-btn" id="closeSidebar" aria-label="Tutup menu">
      <i class="bi bi-x-lg"></i>
    </button>
  </div>

  <nav class="nav flex-column gap-2" id="sidebar-nav">
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/index.php">
      <i class="bi bi-house-door"></i> Dashboard
    </a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/orders.php">
      <i class="bi bi-receipt"></i> Orders
    </a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/catalog.php">
      <i class="bi bi-box-seam"></i> Catalog
    </a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/users.php">
      <i class="bi bi-people"></i> Users
    </a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/finance.php">
      <i class="bi bi-cash-coin"></i> Finance
    </a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/notifications_send.php">
      <i class="bi bi-megaphone"></i> Kirim Notifikasi
    </a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/audit.php">
      <i class="bi bi-shield-check"></i> Audit Log
    </a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/settings.php">
      <i class="bi bi-gear"></i> Settings
    </a>

    <hr>

    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/help.php">
      <i class="bi bi-question-circle"></i> Help Center
    </a>

    <a class="nav-link" href="<?= BASE_URL ?>/backend/logout.php">
      <i class="bi bi-box-arrow-right"></i> Logout
    </a>
  </nav>
</aside>


<main class="content">
  <div class="topbar">
    <button class="btn-menu" id="openSidebar" aria-label="Buka menu">
      <div class="hamb-icon"><span></span><span></span><span></span></div>
    </button>

    <div class="search-box">
      <input class="search-input" id="searchInput" placeholder="Search..." autocomplete="off" />
      <i class="bi bi-search search-icon" id="searchIcon"></i>
      <div class="search-suggest" id="searchSuggest"></div>
    </div>

    <div class="top-actions">
      <a id="btnBell" class="icon-btn position-relative text-decoration-none" href="<?= BASE_URL ?>/public/admin/notifications.php" aria-label="Notifikasi">
        <span class="iconify" data-icon="mdi:bell-outline" data-width="24" data-height="24"></span>
        <span id="badgeNotif" class="notif-dot d-none"></span>
      </a>
      <a class="icon-btn text-decoration-none" href="<?= BASE_URL ?>/public/admin/settings.php" aria-label="Akun">
        <span class="iconify" data-icon="mdi:account-circle-outline" data-width="28" data-height="28"></span>
      </a>
    </div>
  </div>
 
 

  <h2 class="fw-bold mb-1">Finance</h2>

  <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
    <div>
      <div class="text-muted small mb-1">Periode ditampilkan</div>
      <div class="fw-semibold">
        <?= $startDate->format('d M Y') ?> – <?= $endDate->format('d M Y') ?>
      </div>
    </div>

    <!-- Custom Dropdown Periode + Download CSV -->
    <div class="range-wrap">
      <select
        id="rangeSelect"
        class="select-ghost"
        tabindex="-1"
        aria-hidden="true"
        hidden
      >
        <option value="today"  <?= $range === 'today' ? 'selected' : ''; ?>>Hari ini</option>
        <option value="7d"     <?= $range === '7d'    ? 'selected' : ''; ?>>7 hari</option>
        <option value="30d"    <?= $range === '30d'   ? 'selected' : ''; ?>>30 hari</option>
        <option value="custom" <?= $range === 'custom'? 'selected' : ''; ?>>Custom…</option>
      </select>

      <div class="select-custom" id="rangeCustom">
        <button
          type="button"
          class="select-toggle"
          id="rangeBtn"
          aria-haspopup="listbox"
          aria-expanded="false"
          tabindex="0"
        >
          <span id="rangeText">
            <?php
            echo $range === 'today'
              ? 'Hari ini'
              : ($range === '7d'
                ? '7 hari'
                : ($range === '30d' ? '30 hari' : 'Custom…'));
            ?>
          </span>
          <i class="bi bi-chevron-down select-caret"></i>
        </button>
        <div
          class="select-menu"
          id="rangeMenu"
          role="listbox"
          aria-labelledby="rangeBtn"
        >
          <div class="select-item<?= $range==='today'  ? ' active' : ''; ?>" data-value="today">Hari ini</div>
          <div class="select-item<?= $range==='7d'     ? ' active' : ''; ?>" data-value="7d">7 hari</div>
          <div class="select-item<?= $range==='30d'    ? ' active' : ''; ?>" data-value="30d">30 hari</div>
          <div class="select-item<?= $range==='custom' ? ' active' : ''; ?>" data-value="custom">Custom…</div>
        </div>
      </div>

      <!-- Button download CSV -->
      <button id="btnDownload" class="btn-download">
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" width="20" height="20">
          <path d="M12 3a1 1 0 011 1v8.586l2.293-2.293a1 1 0 111.414 1.414l-4.001 4a1 1 0 01-1.414 0l-4.001-4a1 1 0 111.414-1.414L11 12.586V4a1 1 0 011-1z"></path>
          <path d="M5 19a1 1 0 011-1h12a1 1 0 110 2H6a1 1 0 01-1-1z"></path>
        </svg>
        <span>Export CSV</span>
      </button>
    </div>
  </div>

  <!-- KPI -->
  <div class="row g-3 mb-3 kpi">
    <div class="col-12 col-md-4">
      <div class="cardx">
        <div class="text-muted small">Total Revenue</div>
        <div class="value">
          Rp <?= number_format($totalRevenue, 0, ',', '.') ?>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="cardx">
        <div class="text-muted small">Order Lunas</div>
        <div class="value">
          <?= number_format($ordersPaidCount, 0, ',', '.') ?>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="cardx">
        <div class="text-muted small">Rata-rata / Order</div>
        <div class="value">
          Rp <?= number_format($avgOrder, 0, ',', '.') ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Row utama -->
  <div class="row g-3 row-finance-main">
    <div class="col-12 col-lg-8 d-flex">
      <div class="cardx flex-fill">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="fw-bold mb-0">Revenue Periode Ini</h6>
        </div>
        <div class="chart-wrapper">
          <canvas id="revChart"></canvas>
        </div>
      </div>
    </div>
    <div class="col-12 col-lg-4 d-flex">
      <div class="cardx flex-fill">
        <h6 class="fw-bold mb-2">Top Menu Terlaris</h6>
        <?php if ($topMenus): ?>
          <?php foreach ($topMenus as $m): ?>
            <div class="top-menu-item">
              <div class="top-menu-thumb">
                <img
                  src="<?= htmlspecialchars($m['img_url'], ENT_QUOTES, 'UTF-8') ?>"
                  alt="<?= htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8') ?>"
                  onerror="this.src='<?= BASE_URL ?>/public/assets/img/menu-placeholder.png';"
                >
              </div>
              <div class="flex-grow-1">
                <div class="top-menu-name text-truncate-1">
                  <?= htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="top-menu-sub">
                  Rp <?= number_format((float)$m['sold_amount'], 0, ',', '.') ?>
                </div>
              </div>
              <div class="fw-semibold">
                x<?= (int)$m['sold_qty'] ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="text-muted small">
            Belum ada data penjualan di periode ini.
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Ringkasan + Donut -->
  <div class="row g-3 mt-1 mb-3 align-items-stretch">
    <div class="col-12 col-lg-6 d-flex">
      <div class="cardx flex-fill h-100">
        <h5 class="fw-bold mb-3">Ringkasan Status Order</h5>
        <div class="summary-grid">
          <div class="summary-card">
            <div class="label">Paid</div>
            <div class="value"><?= $statusSummary['paid'] ?></div>
          </div>
          <div class="summary-card">
            <div class="label">Pending</div>
            <div class="value"><?= $statusSummary['pending'] ?></div>
          </div>
          <div class="summary-card">
            <div class="label">Overdue / Cancel</div>
            <div class="value"><?= $statusSummary['cancel'] ?></div>
          </div>
          <div class="summary-card">
            <div class="label">Done (completed)</div>
            <div class="value"><?= $statusSummary['done'] ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12 col-lg-6 d-flex">
      <div class="cardx flex-fill h-100">
        <h6 class="fw-bold mb-2">Distribusi Status Pesanan</h6>
        <canvas id="statusDonut" style="max-height:360px;"></canvas>
        <div id="statusLegend" class="mt-2 small text-muted"></div>
      </div>
    </div>
  </div>

  <!-- Transaksi terbaru -->
  <div class="cardx mb-4">
    <h6 class="fw-bold mb-2">Transaksi Terbaru</h6>
    <?php if ($latestTx): ?>
      <?php foreach ($latestTx as $tx): ?>
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-light-subtle">
          <div>
            <div class="fw-semibold">
              #<?= (int)$tx['id'] ?> — <?= htmlspecialchars($tx['customer_name'] ?? 'Guest', ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div class="text-muted small">
              <?= date('d M Y H:i', strtotime($tx['created_at'])) ?>
            </div>
          </div>
          <div class="text-end">
            <div class="fw-semibold">
              Rp <?= number_format((float)$tx['total'], 0, ',', '.') ?>
            </div>
            <span class="badge rounded-pill <?= $tx['payment_status'] === 'paid'
              ? 'bg-success-subtle text-success'
              : 'bg-warning-subtle text-warning' ?>">
              <?= htmlspecialchars($tx['payment_status'], ENT_QUOTES, 'UTF-8') ?>
            </span>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="text-muted small">
        Belum ada transaksi di periode ini.
      </div>
    <?php endif; ?>
  </div>
</main>

<!-- Modal Custom Range -->
<div class="modal fade" id="modalCustom" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:460px">
    <form class="modal-content" id="customForm">
      <div class="modal-header">
        <h5 class="modal-title">Pilih rentang tanggal</h5>
        <button
          type="button"
          class="btn-close"
          data-bs-dismiss="modal"
          aria-label="Tutup"
        ></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Dari tanggal</label>
          <input type="date" name="from" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Sampai tanggal</label>
          <input type="date" name="to" class="form-control" required>
        </div>
      </div>
      <div class="modal-footer flex-wrap gap-2">
        <button
          type="button"
          class="btn btn-light"
          data-bs-dismiss="modal"
        >Batal</button>
        <button type="submit" class="btn-download">Terapkan</button>
      </div>
    </form>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>

// ===== Sidebar =====

// Ambil elemen sidebar
const sideNav  = document.getElementById('sideNav');

// Ambil elemen backdrop
const backdrop = document.getElementById('backdrop');

// Event buka sidebar
document.getElementById('openSidebar')
  ?.addEventListener('click', () => {

    // Tampilkan sidebar
    sideNav.classList.add('show');

    // Aktifkan backdrop
    backdrop.classList.add('active');
  });

// Event tutup sidebar (tombol close)
document.getElementById('closeSidebar')
  ?.addEventListener('click', () => {

    // Sembunyikan sidebar
    sideNav.classList.remove('show');

    // Nonaktifkan backdrop
    backdrop.classList.remove('active');
  });

// Event tutup sidebar dari dalam sidebar
document.getElementById('toggleSidebarInside')
  ?.addEventListener('click', () => {

    // Sembunyikan sidebar
    sideNav.classList.remove('show');

    // Nonaktifkan backdrop
    backdrop.classList.remove('active');
  });

// Event klik backdrop
backdrop?.addEventListener('click', () => {

  // Sembunyikan sidebar
  sideNav.classList.remove('show');

  // Nonaktifkan backdrop
  backdrop.classList.remove('active');
});

// ===== highlight sidebar active =====

// Loop semua link sidebar
document.querySelectorAll('#sidebar-nav .nav-link').forEach(a => {

  // Tambahkan event click
  a.addEventListener('click', function () {

    // Hapus class active dari semua link
    document.querySelectorAll('#sidebar-nav .nav-link')
      .forEach(l => l.classList.remove('active'));

    // Aktifkan link yang diklik
    this.classList.add('active');

    // Auto close sidebar di layar kecil
    if (window.innerWidth < 1200) {

      // Sembunyikan sidebar
      sideNav.classList.remove('show');

      // Nonaktifkan backdrop
      backdrop.classList.remove('active');
    }
  });
});

// ===== Search suggest (admin + fallback karyawan) =====

// Fungsi inisialisasi search
function attachSearch(inputEl, suggestEl){

  // Jika elemen tidak ada, hentikan
  if (!inputEl || !suggestEl) return;

  // Endpoint admin search
  const ADMIN_EP = "<?= BASE_URL ?>/backend/api/admin_search.php";

  // Endpoint karyawan search (fallback)
  const KARY_EP  = "<?= BASE_URL ?>/backend/api/karyawan_search.php";

  // Fungsi fetch hasil search
  async function fetchResults(q){

    try {
      const r = await fetch(ADMIN_EP + "?q=" + encodeURIComponent(q));
      if (r.ok) return await r.json();
    } catch(e){}

    try {
      const r2 = await fetch(KARY_EP + "?q=" + encodeURIComponent(q));
      if (r2.ok) return await r2.json();
    } catch(e){}

    return { ok:true, results:[] };
  }

  // Event input search
  inputEl.addEventListener('input', async function(){

    // Ambil keyword
    const q = this.value.trim();

    // Jika terlalu pendek, sembunyikan suggest
    if (q.length < 2){
      suggestEl.classList.remove('visible');
      suggestEl.innerHTML = '';
      return;
    }

    // Ambil data hasil
    const data = await fetchResults(q);

    // Normalisasi array
    const arr  = Array.isArray(data.results) ? data.results : [];

    // Jika tidak ada hasil
    if (!arr.length){
      suggestEl.innerHTML =
        '<div class="search-empty">Tidak ada hasil.</div>';
      suggestEl.classList.add('visible');
      return;
    }

    // Bangun HTML suggest
    let html = '';
    arr.forEach(r => {
      html += `
        <div class="item"
             data-type="${r.type}"
             data-key="${r.key}">
          ${r.label ?? ''}
          ${r.sub ? `<small>${r.sub}</small>` : ''}
        </div>`;
    });

    // Render suggest
    suggestEl.innerHTML = html;
    suggestEl.classList.add('visible');

    // Event klik item suggest
    suggestEl.querySelectorAll('.item').forEach(it => {
      it.addEventListener('click', () => {

        // Ambil tipe & key
        const type = it.dataset.type;
        const key  = it.dataset.key;

        // Default URL orders
        let url    = "<?= BASE_URL ?>/public/admin/orders.php?search=" +
                     encodeURIComponent(key);

        // Redirect sesuai tipe
        if (type === 'menu') {
          url = "<?= BASE_URL ?>/public/admin/catalog.php?search=" +
                encodeURIComponent(key);
        } else if (type === 'user') {
          url = "<?= BASE_URL ?>/public/admin/users.php?search=" +
                encodeURIComponent(key);
        }

        // Pindah halaman
        window.location = url;
      });
    });
  });

  // Tutup suggest jika klik di luar
  document.addEventListener('click', ev => {
    if (!suggestEl.contains(ev.target) && ev.target !== inputEl) {
      suggestEl.classList.remove('visible');
    }
  });

  // Fokus input saat klik icon search
  document.getElementById('searchIcon')
    ?.addEventListener('click', () => inputEl.focus());
}

// Pasang search handler
attachSearch(
  document.getElementById('searchInput'),
  document.getElementById('searchSuggest')
);

// ===== Notif badge =====

// Fungsi refresh badge notifikasi
async function refreshAdminNotifBadge(){

  // Ambil elemen badge
  const badge = document.getElementById('badgeNotif');

  // Jika tidak ada, hentikan
  if (!badge) return;

  try {

    // Fetch unread count
    const res = await fetch(
      "<?= BASE_URL ?>/backend/api/notifications.php?action=unread_count",
      { credentials:"same-origin" }
    );

    // Jika response tidak OK
    if (!res.ok) return;

    // Ambil data JSON
    const data  = await res.json();

    // Ambil jumlah
    const count = data.count ?? 0;

    // Toggle badge
    badge.classList.toggle('d-none', !(count > 0));

  } catch(e){}
}

// Panggil pertama kali
refreshAdminNotifBadge();

// Interval refresh 30 detik
setInterval(refreshAdminNotifBadge, 30000);

// ===== Custom Period Dropdown =====

// Ambil tombol range
const rangeBtn    = document.getElementById('rangeBtn');

// Ambil menu range
const rangeMenu   = document.getElementById('rangeMenu');

// Ambil teks range
const rangeText   = document.getElementById('rangeText');

// Inisialisasi modal custom
const customModal = new bootstrap.Modal(
  document.getElementById('modalCustom')
);

// Ambil form custom
const customForm  = document.getElementById('customForm');

// Fungsi apply range
function applyRange(v){

  // Jika custom, tampilkan modal
  if (v === 'custom'){
    customModal.show();
    return;
  }

  // Update URL parameter
  const url = new URL(window.location.href);
  url.searchParams.set('range', v);
  url.searchParams.delete('from');
  url.searchParams.delete('to');

  // Reload halaman
  window.location.href = url.toString();
}

// Toggle dropdown range
rangeBtn?.addEventListener('click', () => {

  // Toggle show
  const shown = rangeMenu.classList.toggle('show');

  // Update aria
  rangeBtn.setAttribute('aria-expanded', shown ? 'true' : 'false');
});

// Tutup dropdown saat klik luar
document.addEventListener('click', e => {
  if (!rangeMenu.contains(e.target) && e.target !== rangeBtn) {
    rangeMenu.classList.remove('show');
    rangeBtn.setAttribute('aria-expanded', 'false');
  }
});

// Tutup dropdown via ESC
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    rangeMenu.classList.remove('show');
    rangeBtn.setAttribute('aria-expanded', 'false');
  }
});

// Pilih item range
rangeMenu.querySelectorAll('.select-item').forEach(it => {
  it.addEventListener('click', () => {

    // Reset active
    rangeMenu.querySelectorAll('.select-item')
      .forEach(x => x.classList.remove('active'));

    // Aktifkan item
    it.classList.add('active');

    // Set teks
    rangeText.textContent = it.textContent.trim();

    // Apply range
    applyRange(it.dataset.value);
  });
});

// Submit custom range
customForm?.addEventListener('submit', ev => {

  // Cegah submit default
  ev.preventDefault();

  // Ambil nilai tanggal
  const from = customForm.elements['from'].value;
  const to   = customForm.elements['to'].value;

  // Validasi
  if (!from || !to) return;

  // Update URL
  const url = new URL(window.location.href);
  url.searchParams.set('range', 'custom');
  url.searchParams.set('from', from);
  url.searchParams.set('to', to);

  // Reload halaman
  window.location.href = url.toString();
});

// ===== Download CSV =====

// Event klik download
document.getElementById('btnDownload')
  ?.addEventListener('click', e => {

    // Cegah default
    e.preventDefault();

    // Update URL export
    const url = new URL(window.location.href);
    url.searchParams.set('export', 'csv');

    // Redirect
    window.location.href = url.toString();
  });

// ===== Charts =====

// Ambil label chart
const labels  = <?= json_encode($labels) ?>;

// Ambil data revenue
const revenue = <?= json_encode($revenue) ?>;

// Inisialisasi chart revenue
new Chart(document.getElementById('revChart'), {

  // Tipe line chart
  type: 'line',

  // Data chart
  data: {
    labels,
    datasets: [{
      label: 'Revenue',
      data: revenue,
      tension: .35,
      fill: true,
      borderColor: '#ffd54f',
      backgroundColor: 'rgba(255,213,79,.18)',
      pointRadius: 4,
      pointBackgroundColor: '#ffd54f',
      pointBorderColor: '#ffd54f'
    }]
  },

  // Opsi chart
  options: {
    maintainAspectRatio: false,
    responsive: true,
    plugins: {
      legend: { display: false }
    },
    scales: {
      y: {
        ticks: {
          callback: v => new Intl.NumberFormat(
            'id-ID',
            {
              style: 'currency',
              currency: 'IDR',
              maximumFractionDigits: 0
            }
          ).format(v)
        },
        grid: { color: 'rgba(17,24,39,.06)' }
      },
      x: {
        grid: { display: false }
      }
    }
  }
});

// ===== Donut distribusi status =====

// Label donut
const dLabels = <?= json_encode($distLabels) ?>;

// Data donut
const dOrders = <?= json_encode($distOrders) ?>;

// Inisialisasi donut chart
new Chart(document.getElementById('statusDonut'), {

  // Tipe doughnut
  type: 'doughnut',

  // Data chart
  data: {
    labels: dLabels,
    datasets: [{
      data: dOrders,
      backgroundColor: [
        '#ffe761',
        '#eae3c0',
        '#facf43',
        '#fdeb9e',
        '#edde3b'
      ]
    }]
  },

  // Opsi chart
  options: {
    responsive: true,
    cutout: '60%',
    plugins: {
      legend: { position: 'bottom' }
    }
  }
});

// ===== Legend sederhana =====

// IIFE render legend
(function () {

  // Ambil elemen legend
  const el = document.getElementById('statusLegend');

  // Jika tidak ada, hentikan
  if (!el) return;

  // Bangun isi legend
  const parts = dLabels.map((lb, i) =>
    `${lb}: <b>${dOrders[i] ?? 0}</b>`
  );

  // Render ke DOM
  el.innerHTML = parts.join(' &nbsp;&nbsp;•&nbsp;&nbsp; ');
})();
</script>

</body>
</html>
