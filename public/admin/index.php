<?php
// ===============================================                     // Tagline blok dokumentasi file
// File   : public/admin/index.php                                    // Lokasi file dalam struktur project
// Fungsi : Dashboard utama admin (KPI, grafik, status pesanan)       // Deskripsi singkat fungsi halaman
// Akses  : Hanya untuk user dengan role "admin"                      // Batasan akses: admin saja
// ===============================================

 // public/admin/index.php                                            // Catatan path relatif file
declare(strict_types=1);                                              // Aktifkan strict types di PHP (lebih aman soal tipe data)
session_start();                                                      // Mulai / lanjutkan sesi PHP

require_once __DIR__ . '/../../backend/config.php';                  // Muat konfigurasi global (DB, BASE_URL, dll)

/* ===== Guard role: hanya admin ===== */                            // Komentar bagian: proteksi akses khusus admin
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') { // Cek sudah login dan role harus 'admin'
  header('Location: ' . BASE_URL . '/public/login.html');            // Jika tidak, redirect ke halaman login
  exit;                                                              // Hentikan eksekusi script setelah redirect
}



/* ===== User info ===== */                                          // Bagian: ambil info user dari session
$user = [
  'id'    => (int)($_SESSION['user_id'] ?? 0),                       // ID user dari session (default 0)
  'name'  => (string)($_SESSION['user_name'] ?? ''),                 // Nama user dari session (default string kosong)
  'email' => (string)($_SESSION['user_email'] ?? ''),                // Email user dari session
  'role'  => (string)($_SESSION['user_role'] ?? ''),                 // Role user dari session
];
$userName  = htmlspecialchars($user['name'],  ENT_QUOTES, 'UTF-8');  // Escape nama untuk aman ditampilkan di HTML
$userEmail = htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8');  // Escape email untuk aman di HTML

/* ===== KPI ===== */                                                // Bagian: hitung angka KPI (statistik singkat)
$kpi = [
  'total_orders'     => 0,                                           // Total semua pesanan
  'orders_today'     => 0,                                           // Jumlah pesanan hari ini
  'menu_count'       => 0,                                           // Jumlah menu tersedia
  'active_customers' => 0,                                           // Jumlah pelanggan aktif
];

$res = $conn->query("SELECT COUNT(*) AS c FROM orders");             // Query hitung semua baris di tabel orders
$kpi['total_orders'] = (int)($res?->fetch_assoc()['c'] ?? 0);        // Ambil hasilnya, fallback 0 jika null

$today = (new DateTime('today'))->format('Y-m-d');                   // Ambil tanggal hari ini dalam format Y-m-d
$stmt  = $conn->prepare("SELECT COUNT(*) AS c FROM orders WHERE DATE(created_at)=?"); // Siapkan query pesanan per hari
$stmt->bind_param('s', $today);                                      // Bind parameter tanggal
$stmt->execute();                                                    // Jalankan query
$kpi['orders_today'] = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0); // Ambil jumlah pesanan hari ini
$stmt->close();                                                      // Tutup statement

$res = $conn->query("SELECT COUNT(*) AS c FROM menu");               // Query hitung jumlah menu
$kpi['menu_count'] = (int)($res?->fetch_assoc()['c'] ?? 0);          // Simpan hasil ke KPI menu_count

$res = $conn->query("SELECT COUNT(*) AS c FROM users WHERE status='active' AND role='customer'"); // Hitung pelanggan aktif
$kpi['active_customers'] = (int)($res?->fetch_assoc()['c'] ?? 0);    // Simpan hasil ke KPI active_customers

/* ===== Chart data: Revenue 7 hari terakhir ===== */                // Bagian: data untuk grafik revenue 7 hari
$labels  = [];                                                       // Array label tanggal
$revenue = [];                                                       // Array nilai revenue per tanggal
$map     = [];                                                       // Map sementara: tanggal => total

for ($i = 6; $i >= 0; $i--) {                                        // Loop mundur 6 s/d 0 hari (7 hari terakhir)
  $d = (new DateTime("today -$i day"))->format('Y-m-d');             // Hitung tanggal ke-i
  $labels[] = $d;                                                    // Tambahkan ke label untuk chart
  $map[$d]  = 0.0;                                                   // Inisialisasi nilai awal revenue 0
}

$sql = "
  SELECT DATE(created_at) AS d, SUM(total) AS s
  FROM orders
  WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
  GROUP BY DATE(created_at)
  ORDER BY d ASC
";                                                                   // Query: total revenue per hari, 7 hari terakhir
$res = $conn->query($sql);                                           // Jalankan query
while ($row = $res?->fetch_assoc()) {                                // Loop setiap baris hasil
  $map[$row['d']] = (float)$row['s'];                                // Isi map: tanggal => jumlah revenue
}
foreach ($labels as $d) {                                            // Susun array revenue sesuai urutan labels
  $revenue[] = $map[$d];                                             // Masukkan nilai revenue per tanggal
}

/* ===== Distribusi status (Today) =====
   Ambil dari orders.order_status untuk HARI INI saja.
   Bucket final: new, processing, ready, completed, cancelled
============================================================= */      // Keterangan: chart donut status pesanan hari ini
$statusBuckets = [
  'new'        => 0,                                                 // Counter status 'new'
  'processing' => 0,                                                 // Counter status 'processing'
  'ready'      => 0,                                                 // Counter status 'ready'
  'completed'  => 0,                                                 // Counter status 'completed'
  'cancelled'  => 0,  // akan menampung cancelled/canceled/failed    // Counter gabungan status batal/failed
];

$stmt = $conn->prepare("
  SELECT LOWER(order_status) AS s, COUNT(*) AS c
  FROM orders
  WHERE DATE(created_at) = ?
  GROUP BY LOWER(order_status)
");                                                                  // Query: hitung jumlah masing-masing status hari ini
$stmt->bind_param('s', $today);                                      // Bind parameter tanggal hari ini
$stmt->execute();                                                    // Eksekusi query
$rst = $stmt->get_result();                                          // Ambil result set
while ($row = $rst?->fetch_assoc()) {                                // Loop hasil per status
  $s = (string)$row['s'];                                            // Ambil nama status dalam lowercase
  $c = (int)$row['c'];                                               // Ambil jumlah status tersebut

  if (isset($statusBuckets[$s])) {                                   // Jika status sudah ada di bucket
    $statusBuckets[$s] += $c;                                        // Tambah jumlah ke bucket sesuai status
  } else {
    // Normalisasi status lain yang bermakna "cancel"                 // Catatan: status lain yg mirip cancel digabung
    if (in_array($s, ['canceled','cancel','cancelled','failed','void','refunded'], true)) {
      $statusBuckets['cancelled'] += $c;                             // Gabungkan ke bucket 'cancelled'
    }
    // status lain diabaikan agar tidak mengubah layout/legenda       // Status lain tidak di-chart
  }
}
$stmt->close();                                                      // Tutup statement

// urutan data untuk chart donut                                      // Susun array sesuai label di JS
$distToday = [
  $statusBuckets['new'],                                             // Data chart: jumlah status 'new'
  $statusBuckets['processing'],                                      // Data chart: jumlah status 'processing'
  $statusBuckets['ready'],                                           // Data chart: jumlah status 'ready'
  $statusBuckets['completed'],                                       // Data chart: jumlah status 'completed'
  $statusBuckets['cancelled'],                                       // Data chart: jumlah status 'cancelled'
];
?>
<!doctype html>                                                       <!-- Deklarasi dokumen HTML5 -->
<html lang="id">                                                      <!-- Bahasa dokumen: Indonesia -->
<head>
  <meta charset="utf-8">                                              <!-- Set charset UTF-8 -->
  <meta name="viewport" content="width=device-width,initial-scale=1"> <!-- Supaya responsif di mobile -->
  <title>Admin Desk — Caffora</title>                                <!-- Judul tab browser -->

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"> <!-- CSS Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"> <!-- Ikon Bootstrap -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script> <!-- Library Chart.js -->
  <script src="https://code.iconify.design/2/2.2.1/iconify.min.js"></script>               <!-- Iconify untuk ikon tambahan -->
<style>
  :root{
    --gold:#ffd54f;                                                   /* Warna gold brand utama */
    --gold-200:#ffe883;                                               /* Gold versi lebih terang */
    --gold-soft:#f4d67a;                                              /* Gold lembut untuk border/focus */
    --ink:#111827;                                                    /* Warna teks utama (dark) */
    --muted:#6B7280;                                                  /* Warna teks sekunder */
    --brown:#4B3F36;                                                  /* Warna coklat khas brand */
    --radius:18px;                                                    /* Radius umum untuk card */
    --sidebar-w:320px;                                                /* Lebar sidebar */

    /* tambahan khusus finance */
    --gold-border:#f7d78d;                                            /* Warna border kartu KPI */
    --soft:#fff7d1;                                                   /* Background lembut */
    --hover:#ffefad;                                                  /* Warna hover pilihan */
    --btn-radius:14px;                                                /* Radius tombol */
    --input-border:#E8E2DA;                                           /* Warna border input */
  }

  *,:before,:after{ box-sizing:border-box; }                          /* Gunakan box-sizing border-box global */

  body{
    background:#FAFAFA;                                               /* Warna latar belakang halaman */
    color:var(--ink);                                                 /* Warna teks default */
    font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial;  /* Font utama */
    font-weight:500;                                                  /* Ketebalan default */
  }

  /* ===== Sidebar (SAMA DENGAN INDEX) ===== */
  .sidebar{
    position:fixed;                                                   /* Sidebar fixed di sisi kiri */
    left:-320px;                                                      /* Posisi awal di luar layar */
    top:0;
    bottom:0;
    width:var(--sidebar-w);                                           /* Lebar sidebar 320px */
    background:#fff;                                                  /* Latar putih */
    border-right:1px solid rgba(0,0,0,.04);                           /* Garis tipis sisi kanan */
    transition:left .25s ease;                                        /* Animasi slide in/out */
    z-index:1050;                                                     /* Di atas konten */
    padding:14px 18px 18px;                                           /* Padding dalam sidebar */
    overflow-y:auto;                                                  /* Scroll jika konten tinggi */
  }
  .sidebar.show{ left:0; }                                            /* Saat .show, sidebar masuk ke layar */

  .sidebar-head{
    display:flex;                                                     /* Baris header sidebar */
    align-items:center;                                               /* Tengahkan vertikal */
    justify-content:space-between;                                    /* Spasi antara tombol kiri dan kanan */
    gap:10px;                                                         /* Jarak antar elemen */
    margin-bottom:10px;                                               /* Jarak bawah header sidebar */
  }
  .sidebar-inner-toggle,
  .sidebar-close-btn{
    background:transparent;                                           /* Tombol tanpa background */
    border:0;                                                         /* Tanpa border */
    width:40px;
    height:36px;
    display:grid;
    place-items:center;                                               /* Pusatkan ikon di tombol */
  }

  .hamb-icon{
    width:24px;
    height:20px;
    display:flex;
    flex-direction:column;
    justify-content:space-between;
    gap:4px;                                                          /* Ikon hamburger (3 garis) */
  }
  .hamb-icon span{
    height:2px;
    background:var(--brown);                                          /* Warna garis hamburger */
    border-radius:99px;                                               /* Ujung garis membulat */
  }

  .sidebar .nav-link{
    display:flex; align-items:center; gap:12px;                       /* Navbar item: ikon dan teks sejajar */
    padding:12px 14px; border-radius:16px;                            /* Padding dan radius */
    color:#111; font-weight:600;                                      /* Warna dan ketebalan teks */
    text-decoration:none;
    background:transparent;
    user-select:none;                                                 /* Tidak bisa di-drag teks */
  }
  .sidebar .nav-link:hover,
  .sidebar .nav-link:focus,
  .sidebar .nav-link:active{
    background:rgba(255,213,79,0.25);                                 /* Warna hover kuning lembut */
    color:#111;
    outline:none;
    box-shadow:none;
  }
  .sidebar hr{ border-color:rgba(0,0,0,.05); opacity:1; }             /* Garis pemisah di sidebar */

  /* ===== CONTENT (SAMA) ===== */
  .content{ margin-left:0; padding:16px 14px 50px; }                  /* Konten utama, padding sekeliling */

  /* ===== TOPBAR (SAMA DENGAN INDEX) ===== */
  .topbar{
    display:flex;                                                     /* Container bar atas */
    align-items:center;                                               /* Tengah vertikal */
    gap:12px;                                                         /* Jarak antar elemen */
    margin-bottom:16px;                                               /* Jarak bawah topbar */
  }
  .btn-menu{
    background:transparent;                                           /* Tombol menu tanpa background */
    border:0;
    width:40px;
    height:38px;
    display:grid;
    place-items:center;
    flex:0 0 auto;                                                    /* Lebar tetap */
  }

  /* ===== SEARCH (SAMA DENGAN INDEX) ===== */
  .search-box{
    position:relative;                                                /* Container untuk input dan dropdown hasil */
    flex:1 1 auto;
    min-width:0;
  }
  .search-input{
    height:46px;
    width:100%;
    border-radius:9999px;                                             /* Input oval (pill) */
    padding-left:16px;
    padding-right:44px;
    border:1px solid #e5e7eb;
    background:#fff;
    outline:none !important;
    transition:border-color .12s ease;
  }
  .search-input:focus{
    border-color:var(--gold-soft) !important;                         /* Warna border saat fokus */
    background:#fff;
    box-shadow:none !important;
  }
  .search-icon{
    position:absolute;
    right:16px;
    top:50%;
    transform:translateY(-50%);
    font-size:1.1rem;
    color:var(--brown);
    cursor:pointer;                                                   /* Ikon kaca pembesar bisa diklik */
  }

  .search-suggest{
    position:absolute;
    top:100%;
    left:0;
    margin-top:6px;
    background:#fff;
    border:1px solid rgba(247,215,141,.8);
    border-radius:16px;
    box-shadow:0 12px 28px rgba(0,0,0,.08);
    width:100%;
    max-height:280px;
    overflow-y:auto;
    display:none;                                                     /* Dropdown sugesti default tersembunyi */
    z-index:40;
  }
  .search-suggest.visible{ display:block; }                           /* Tampilkan jika punya kelas .visible */
  .search-suggest .item{
    padding:10px 14px 6px;
    border-bottom:1px solid rgba(0,0,0,.03);
    cursor:pointer;
  }
  .search-suggest .item:last-child{ border-bottom:0; }
  .search-suggest .item:hover{ background:#fffbea; }
  .search-suggest .item small{
    display:block;
    color:#6b7280;
    font-size:.74rem;
    margin-top:2px;
  }
  .search-empty{
    padding:12px 14px;
    color:#6b7280;
    font-size:.8rem;                                                  /* Pesan "Tidak ada hasil" */
  }

  .top-actions{
    display:flex;
    align-items:center;
    gap:14px;
    flex:0 0 auto;                                                    /* Grup ikon kanan atas */
  }
  .icon-btn{
    width:38px;
    height:38px;
    border-radius:999px;
    display:flex;
    align-items:center;
    justify-content:center;
    color:var(--brown);
    text-decoration:none;
    background:transparent;
    outline:none;
  }
  .icon-btn:focus,
  .icon-btn:active{
    outline:none;
    box-shadow:none;
    color:var(--brown);
  }

  #btnBell{ position:relative; }                                      /* Tombol bell punya posisi relatif (untuk dot) */
  #badgeNotif.notif-dot{
    position:absolute;
    top:3px;
    right:5px;
    width:8px;
    height:8px;
    background:#4b3f36;
    border-radius:50%;
    display:inline-block;
    box-shadow:0 0 0 1.5px #fff;                                     /* Dot notifikasi di atas bell */
  }
  #badgeNotif.d-none{ display:none !important; }                      /* Sembunyikan dot jika tidak ada notif */

  /* ======= BACKDROP MOBILE (SAMA) ======= */
  .backdrop-mobile{ display:none; }                                   /* Backdrop default tidak kelihatan */
  .backdrop-mobile.active{
    display:block;
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.35);
    z-index:1040;                                                     /* Overlay gelap saat sidebar dibuka di mobile */
  }

  /* ====== KARTU / KPI / CHART (ASLI INDEX – sebagian dipakai) ===== */
  .kpi,.cardx{
    background:#fff;
    border:1px solid #f7d78d;
    border-radius:var(--radius);
    padding:18px;                                                     /* Style umum card KPI dan card chart */
  }

  /* ====== KHUSUS FINANCE: SUMMARY, RANGE, SELECT, DOWNLOAD ===== */
  .summary-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:18px;
  }
  .summary-card{
    background:#fff;
    border:1px solid var(--gold-border);
    border-radius:20px;
    padding:18px 20px;
    min-width:0;
  }
  .summary-card .label{
    color:#6b7280;
    font-weight:600;
  }
  .summary-card .value{
    margin-top:6px;
    font-size:2.05rem;
    font-weight:700;
    color:#0f172a;
    line-height:1;
  }

  .range-wrap{
    display:flex;
    gap:10px;
    align-items:center;
    flex-wrap:wrap;
    justify-content:flex-start;                                       /* Wrapper kontrol range (dipakai di beberapa page) */
  }

  .select-ghost{
    position:absolute !important;
    width:1px;
    height:1px;
    opacity:0;
    pointer-events:none;
    left:-9999px;
    top:auto;
    overflow:hidden;                                                  /* Select asli disembunyikan (aksesibilitas) */
  }

  .select-custom{
    position:relative;
    display:inline-block;
    max-width:100%;
  }
  .select-toggle{
    width:200px;
    max-width:100%;
    height:42px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:12px;
    padding:0 14px;
    cursor:pointer;
    user-select:none;
    outline:0;                                                        /* Tombol select custom */
  }
  .select-toggle:focus{ border-color:#ffd54f; }

  .select-caret{
    font-size:16px;
    color:#111;
  }

  .select-menu{
    position:absolute;
    top:46px;
    left:0;
    z-index:1060;
    background:#fff;
    border:1px solid rgba(247,215,141,.9);
    border-radius:14px;
    box-shadow:0 12px 28px rgba(0,0,0,.08);
    min-width:100%;
    display:none;
    padding:6px;
    max-height:280px;
    overflow:auto;
  }
  .select-menu.show{ display:block; }

  .select-item{
    padding:10px 12px;
    border-radius:10px;
    cursor:pointer;
    font-weight:600;
    color:#374151;
  }
  .select-item:hover{ background:var(--hover); }
  .select-item.active{ background:var(--soft); }

  .btn-download{
    background-color:var(--gold);
    color:var(--brown);
    border:0;
    border-radius:var(--btn-radius);
    font-family:Arial, Helvetica, sans-serif;
    font-weight:600;
    font-size:.9rem;
    padding:10px 18px;
    display:inline-flex;
    align-items:center;
    gap:8px;
    line-height:1;                                                    /* Tombol gaya khusus (export, dsb) */
  }
  .btn-download:hover{ filter:brightness(.97); }

  /* Chart */
  #revChart{
    width:100% !important;
    max-height:330px;                                                 /* Batas tinggi grafik revenue */
  }
  .chart-wrapper{
    min-height:220px;
    height:clamp(220px, 38vh, 360px);                                /* Tinggi dinamis untuk chart */
  }

  /* ====== RESPONSIVE (COPY DARI INDEX + FINANCE) ===== */
  @media (min-width: 992px){
    .content{ padding:20px 26px 50px; }                               /* Padding lebih lebar di layar besar */
    .search-box{ max-width:1100px; }                                  /* Batasi lebar search di desktop */
  }

  @media (min-width: 768px) and (max-width: 991.98px){
    .content{ padding:18px 16px 50px; }

    .summary-grid{
      grid-template-columns:1fr 1fr;
      gap:14px;
    }
    .summary-card{ padding:16px; }
    .summary-card .value{ font-size:1.8rem; }
    #revChart{ max-height:300px; }
    .chart-wrapper{ height:clamp(220px, 34vh, 320px); }               /* Penyesuaian tablet */
  }

  @media (max-width:575.98px){
    .content{ padding:16px 14px 70px; }                               /* Tambah padding bawah di mobile */

    .summary-grid{
      grid-template-columns:1fr;
      gap:12px;
    }
    .cardx{ padding:16px; }
    #revChart{ max-height:240px !important; }
    .chart-wrapper{ height:240px; }

    /* range + tombol di mobile */
    .range-wrap{
      display:grid;
      grid-template-columns:1fr 1fr;
      gap:8px;
    }
    .range-wrap .select-custom{
      width:100%;
      flex:unset;
    }
    #btnDownload{
      flex:unset;
      width:100%;
      height:44px;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap:8px;
      border:1px solid rgba(0,0,0,.06);
      border-radius:14px;
      padding:0 12px;
      font-weight:700;
    }
    #btnDownload svg{
      width:18px;
      height:18px;
    }
  }

  @media (max-width:360px){
    .top-actions{ gap:6px; }                                          /* Perkecil jarak ikon di layar sangat kecil */
    .search-box{ max-width:calc(100% - 128px); }                      /* Sesuaikan lebar search */
  }
</style>

</head>
<body>


<div id="backdrop" class="backdrop-mobile"></div>                     <!-- Elemen overlay gelap untuk mobile -->
      <!-- sidebar -->
<aside class="sidebar" id="sideNav">                                  <!-- Sidebar navigasi admin -->
  <div class="sidebar-head">                                          <!-- Header sidebar (tombol close) -->
    <button class="sidebar-inner-toggle" id="toggleSidebarInside" aria-label="Tutup menu"></button> <!-- Tombol close kecil -->
    <button class="sidebar-close-btn" id="closeSidebar" aria-label="Tutup menu"> <!-- Tombol close dengan ikon X -->
      <i class="bi bi-x-lg"></i>
    </button>
  </div>

  <nav class="nav flex-column gap-2" id="sidebar-nav">                <!-- Daftar menu navigasi vertikal -->
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/index.php">
      <i class="bi bi-house-door"></i> Dashboard                       <!-- Menu Dashboard -->
    </a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/orders.php">
      <i class="bi bi-receipt"></i> Orders                            <!-- Menu Orders -->
    </a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/catalog.php">
      <i class="bi bi-box-seam"></i> Catalog                          <!-- Menu Catalog -->
    </a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/users.php">
      <i class="bi bi-people"></i> Users                              <!-- Menu Users -->
    </a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/finance.php">
      <i class="bi bi-cash-coin"></i> Finance                         <!-- Menu Finance -->
    </a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/notifications_send.php">
      <i class="bi bi-megaphone"></i> Notifications                   <!-- Menu kirim notifikasi -->
    </a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/audit.php">
      <i class="bi bi-shield-check"></i> Audit Log                    <!-- Menu Audit Log -->
    </a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/settings.php">
      <i class="bi bi-gear"></i> Settings                             <!-- Menu Settings -->
    </a>

    <hr>                                                              <!-- Garis pemisah -->

    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/help.php">
      <i class="bi bi-question-circle"></i> Help Center               <!-- Menu Help Center -->
    </a>

    <a class="nav-link" href="<?= BASE_URL ?>/backend/logout.php">
      <i class="bi bi-box-arrow-right"></i> Logout                    <!-- Menu Logout -->
    </a>
  </nav>
</aside>


<main class="content">                                                <!-- Kontainer utama isi dashboard -->
  <div class="topbar">                                                <!-- Baris atas: tombol menu + search + ikon -->
    <button class="btn-menu" id="openSidebar" aria-label="Buka menu">
      <div class="hamb-icon"><span></span><span></span><span></span></div> <!-- Ikon hamburger -->
    </button>

    <div class="search-box">                                          <!-- Kotak pencarian global admin -->
      <input class="search-input" id="searchInput" placeholder="Search..." autocomplete="off" /> <!-- Input search -->
      <i class="bi bi-search search-icon" id="searchIcon"></i>        <!-- Ikon kaca pembesar -->
      <div class="search-suggest" id="searchSuggest"></div>           <!-- Container suggestion hasil search -->
    </div>

    <div class="top-actions">                                         <!-- Ikon kanan atas (notif & akun) -->
      <a id="btnBell" class="icon-btn position-relative text-decoration-none" href="<?= BASE_URL ?>/public/admin/notifications.php" aria-label="Notifikasi">
        <span class="iconify" data-icon="mdi:bell-outline" data-width="24" data-height="24"></span> <!-- Ikon lonceng -->
        <span id="badgeNotif" class="notif-dot d-none"></span>        <!-- Dot kecil jika ada notif baru -->
      </a>
      <a class="icon-btn text-decoration-none" href="<?= BASE_URL ?>/public/admin/settings.php" aria-label="Akun">
        <span class="iconify" data-icon="mdi:account-circle-outline" data-width="28" data-height="28"></span> <!-- Ikon akun -->
      </a>
    </div>
  </div>

  <h2 class="fw-bold mb-3">Dashboard Admin</h2>                       <!-- Judul halaman dashboard -->

  <div class="row g-3 mb-4">                                          <!-- Baris KPI 4 kartu -->
    <div class="col-12 col-md-6 col-lg-3">
      <div class="kpi d-flex align-items-center gap-3">
        <div class="ico"><i class="bi bi-list-ul"></i></div>          <!-- Ikon KPI pertama -->
        <div>
          <div class="text-muted small">Total Pesanan</div>           <!-- Label KPI -->
          <div class="fs-4 fw-bold"><?= number_format($kpi['total_orders']) ?></div> <!-- Nilai KPI total pesanan -->
        </div>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-3">
      <div class="kpi d-flex align-items-center gap-3">
        <div class="ico"><i class="bi bi-calendar2-day"></i></div>    <!-- Ikon KPI kedua -->
        <div>
          <div class="text-muted small">Pesanan Hari Ini</div>        <!-- Label KPI -->
          <div class="fs-4 fw-bold"><?= number_format($kpi['orders_today']) ?></div> <!-- Nilai KPI pesanan hari ini -->
        </div>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-3">
      <div class="kpi d-flex align-items-center gap-3">
        <div class="ico"><i class="bi bi-box"></i></div>              <!-- Ikon KPI ketiga -->
        <div>
          <div class="text-muted small">Menu Tersedia</div>           <!-- Label KPI -->
          <div class="fs-4 fw-bold"><?= number_format($kpi['menu_count']) ?></div> <!-- Nilai KPI jumlah menu -->
        </div>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-3">
      <div class="kpi d-flex align-items-center gap-3">
        <div class="ico"><i class="bi bi-people"></i></div>           <!-- Ikon KPI keempat -->
        <div>
          <div class="text-muted small">Pelanggan Aktif</div>         <!-- Label KPI -->
          <div class="fs-4 fw-bold"><?= number_format($kpi['active_customers']) ?></div> <!-- Nilai KPI pelanggan aktif -->
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 charts-row align-items-stretch">                <!-- Baris berisi 2 chart (line & donut) -->
    <div class="col-12 col-xl-7 d-flex">
      <div class="cardx chart-wrap w-100">
        <h6 class="fw-bold mb-2">Revenue 7 Hari Terakhir</h6>         <!-- Judul chart revenue -->
        <div class="chart-body">
          <canvas id="revChart"></canvas>                             <!-- Canvas Chart.js untuk line chart -->
        </div>
      </div>
    </div>
    <div class="col-12 col-xl-5 d-flex">
      <div class="cardx chart-wrap w-100">
        <h6 class="fw-bold mb-2">Distribusi Status Pesanan (Hari Ini)</h6> <!-- Judul chart donut -->
        <div class="chart-body donut-holder">
          <canvas id="distChart"></canvas>                            <!-- Canvas Chart.js untuk donut chart -->
        </div>
      </div>
    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script> <!-- JS Bootstrap bundle -->
<script>
/* ===== sidebar toggle ===== */                                      // Bagian: logika buka/tutup sidebar
const sideNav = document.getElementById('sideNav');                   // Ambil elemen sidebar
const backdrop = document.getElementById('backdrop');                 // Ambil elemen backdrop mobile
document.getElementById('openSidebar')?.addEventListener('click', () => { sideNav.classList.add('show'); backdrop.classList.add('active'); }); // Klik tombol menu → tampilkan sidebar + backdrop
document.getElementById('closeSidebar')?.addEventListener('click', () => { sideNav.classList.remove('show'); backdrop.classList.remove('active'); }); // Klik tombol X → sembunyikan sidebar
document.getElementById('toggleSidebarInside')?.addEventListener('click', () => { sideNav.classList.remove('show'); backdrop.classList.remove('active'); }); // Tombol dalam sidebar → tutup
backdrop?.addEventListener('click', () => { sideNav.classList.remove('show'); backdrop.classList.remove('active'); }); // Klik backdrop → tutup sidebar

/* ===== nav active ===== */                                          // Bagian: highlight link aktif di sidebar
document.querySelectorAll('#sidebar-nav .nav-link').forEach(a => {
  a.addEventListener('click', function(){
    document.querySelectorAll('#sidebar-nav .nav-link').forEach(l => l.classList.remove('active')); // Hapus active dari semua menu
    this.classList.add('active');                                   // Tambah kelas active pada menu yang diklik
    if (window.innerWidth < 1200) { sideNav.classList.remove('show'); backdrop.classList.remove('active'); } // Di layar kecil, tutup sidebar setelah klik
  });
});

/* ===== SEARCH (admin → fallback karyawan) ===== */                  // Bagian: search suggest, pakai API admin lalu fallback karyawan
function attachSearch(inputEl, suggestEl){
  if (!inputEl || !suggestEl) return;                                // Jika elemen tidak ada, hentikan fungsi
  const ADMIN_EP = "<?= BASE_URL ?>/backend/api/admin_search.php";   // Endpoint search untuk admin
  const KARY_EP  = "<?= BASE_URL ?>/backend/api/karyawan_search.php"; // Endpoint fallback untuk karyawan

  async function fetchResults(q){
    try {                                                            // Coba request ke endpoint admin
      const r = await fetch(ADMIN_EP + "?q=" + encodeURIComponent(q), { headers:{Accept:"application/json"} });
      if (r.ok) return await r.json();
    } catch(e){}
    try {                                                            // Jika gagal, coba ke endpoint karyawan
      const r2 = await fetch(KARY_EP + "?q=" + encodeURIComponent(q), { headers:{Accept:"application/json"} });
      if (r2.ok) return await r2.json();
    } catch(e){}
    return { ok:true, results:[] };                                  // Jika semua gagal, kembalikan hasil kosong
  }

  inputEl.addEventListener('input', async function(){
    const q = this.value.trim();                                     // Ambil value input, trim spasi
    if (q.length < 2){                                               // Minimal 2 karakter untuk mulai search
      suggestEl.classList.remove('visible');                         // Sembunyikan dropdown
      suggestEl.innerHTML='';                                        // Kosongkan isi
      return;
    }
    const data = await fetchResults(q);                              // Ambil hasil dari API
    const arr = Array.isArray(data.results) ? data.results : [];     // Pastikan results berupa array
    if (!arr.length){                                                // Jika tidak ada hasil
      suggestEl.innerHTML = '<div class="search-empty">Tidak ada hasil.</div>'; // Tampilkan pesan kosong
      suggestEl.classList.add('visible');                            // Tampilkan dropdown
      return;
    }
    let html='';                                                     // String builder HTML
    arr.forEach(r => {                                               // Loop setiap hasil
      html += `<div class="item" data-type="${r.type}" data-key="${r.key}">${r.label ?? ''}${r.sub ? `<small>${r.sub}</small>` : ''}</div>`; // Item hasil
    });
    suggestEl.innerHTML = html;                                      // Tampilkan hasil di dropdown
    suggestEl.classList.add('visible');                              // Buat dropdown terlihat
    suggestEl.querySelectorAll('.item').forEach(it => {
      it.addEventListener('click', () => {                           // Klik salah satu hasil
        const type = it.dataset.type;                                // Ambil tipe (order/menu/user)
        const key = it.dataset.key;                                  // Ambil key pencarian
        if (type === 'order')      window.location = "<?= BASE_URL ?>/public/admin/orders.php?search="  + encodeURIComponent(key);    // Redirect ke halaman orders
        else if (type === 'menu')  window.location = "<?= BASE_URL ?>/public/admin/catalog.php?search=" + encodeURIComponent(key);    // Redirect ke catalog
        else if (type === 'user')  window.location = "<?= BASE_URL ?>/public/admin/users.php?search="   + encodeURIComponent(key);    // Redirect ke users
        else                       window.location = "<?= BASE_URL ?>/public/admin/orders.php?search="  + encodeURIComponent(key);    // Default ke orders
      });
    });
  });

  document.addEventListener('click', (ev) => {                       // Klik di luar dropdown
    if (!suggestEl.contains(ev.target) && ev.target !== inputEl){
      suggestEl.classList.remove('visible');                         // Sembunyikan dropdown
    }
  });
  document.getElementById('searchIcon')?.addEventListener('click', () => inputEl.focus()); // Klik ikon kaca pembesar → fokus input
}
attachSearch(document.getElementById('searchInput'), document.getElementById('searchSuggest')); // Inisialisasi search pada input & dropdown

/* ===== BADGE NOTIF ===== */                                        // Bagian: refresh badge notifikasi
async function refreshAdminNotifBadge() {
  const badge = document.getElementById('badgeNotif'); if (!badge) return; // Ambil elemen badge, keluar jika tidak ada
  try {
    const res = await fetch("<?= BASE_URL ?>/backend/api/notifications.php?action=unread_count", { credentials:"same-origin" }); // Panggil API hitung notif unread
    if (!res.ok) return;
    const data = await res.json();                                   // Parse JSON
    const count = data.count ?? 0;                                   // Ambil nilai count
    if (count > 0) badge.classList.remove('d-none'); else badge.classList.add('d-none'); // Tampilkan / sembunyikan badge sesuai count
  } catch(err){}
}
refreshAdminNotifBadge(); setInterval(refreshAdminNotifBadge, 30000); // Panggil sekali saat load, lalu setiap 30 detik

/* ===== CHARTS ===== */                                             // Bagian: inisialisasi Chart.js
const labels  = <?= json_encode($labels) ?>;                         // Label tanggal untuk line chart (dari PHP)
const revenue = <?= json_encode($revenue) ?>;                        // Data revenue 7 hari (dari PHP)
const dist    = <?= json_encode($distToday) ?>;                      // Data distribusi status hari ini (dari PHP)

/* Line chart */
new Chart(document.getElementById('revChart'), {                     // Buat chart baru di canvas #revChart
  type: 'line',                                                      // Tipe chart: line
  data: {
    labels,                                                          // Label sumbu X: tanggal
    datasets: [{
      label: 'Revenue',                                              // Nama dataset
      data: revenue,                                                 // Nilai revenue per hari
      tension: .35,                                                  // Kelengkungan garis
      fill: true,                                                    // Area di bawah garis diisi warna
      borderColor: '#ffd54f',                                       // Warna garis utama
      backgroundColor: 'rgba(255,213,79,.18)',                       // Warna area bawah garis
      pointRadius: 4,                                                // Ukuran titik data
      pointBackgroundColor: '#ffd54f',                               // Warna titik
      pointBorderColor: '#ffd54f'                                    // Warna border titik
    }]
  },
  options: {
    maintainAspectRatio: false,                                      // Bebas tinggi/rasio sesuai container
    plugins: { legend: { display:false } },                          // Sembunyikan legend
    scales: {
      y: {
        ticks: {
          callback: v => new Intl.NumberFormat('id-ID', { style:'currency', currency:'IDR', maximumFractionDigits:0 }).format(v) // Format label Y ke Rupiah
        },
        grid: { color:'rgba(17,24,39,.06)' }                         // Warna garis grid sumbu Y
      },
      x: { grid: { display:false } }                                 // Sembunyikan grid sumbu X
    }
  }
});

/* Donut chart (Today) */
new Chart(document.getElementById('distChart'), {                    // Buat chart donut di canvas #distChart
  type: 'doughnut',                                                  // Tipe chart: donut
  data: {
    labels: ['New','Processing','Ready','Completed','Cancelled'],    // Label kategori status
    datasets: [{
      data: dist,                                                    // Data jumlah per status (dari PHP)
      borderWidth: 0,                                                // Tanpa border antar slice
       backgroundColor:['#ffe761','#eae3c0','#facf43','#fdeb9e','#edde3bff'] // Warna slice donut
    }]
  },
  options: {
    maintainAspectRatio: false,                                      // Biarkan tinggi fleksibel
    plugins: { legend: { position: 'bottom' } }                      // Legend di bawah chart
  }
});
</script>
</body>
</html>
