<?php 
// ==== Lokasi file: public/karyawan/index.php ====

// ==== Mode strict typing untuk keamanan tipe data ====
declare(strict_types=1);

// ==== Mulai session untuk akses informasi user ====
session_start();

// ==== Import konfigurasi utama (DB, BASE_URL, dll) ====
require_once __DIR__ . '/../../backend/config.php';

// ==== Guard: hanya karyawan yang boleh akses halaman ini ====
if (!isset($_SESSION['user_id']) || (($_SESSION['user_role'] ?? '') !== 'karyawan')) {
  // ==== Redirect ke halaman login apabila tidak berhak ====
  header('Location: ' . BASE_URL . '/public/login.html');
  exit;
}

// ==== Inisiasi koneksi ke database ====
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// ==== Handler jika koneksi gagal ====
if ($conn->connect_error) {
  http_response_code(500);
  echo "DB error";
  exit;
}

// ==== Set charset agar mendukung UTF-8 ====
$conn->set_charset('utf8mb4');

// ==== Struktur data user dari session ====
$user = [
  'id'    => (int)($_SESSION['user_id'] ?? 0),       // ID user
  'name'  => (string)($_SESSION['user_name'] ?? ''), // Nama user
  'email' => (string)($_SESSION['user_email'] ?? ''),// Email user
  'role'  => (string)($_SESSION['user_role'] ?? ''), // Role user
];

// ==== Ambil inisial 2 huruf dari nama user ====
$initials  = strtoupper(substr($user['name'] ?: 'U', 0, 2));

// ==== Escape nama user untuk ditampilkan aman di HTML ====
$userName  = htmlspecialchars($user['name'],  ENT_QUOTES, 'UTF-8');

// ==== Escape email user untuk keamanan tampilan ====
$userEmail = htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8');

// ==== KPI singkat untuk dashboard ====
$kpi = ['total_orders'=>0,'orders_today'=>0,'menu_count'=>0,'active_customers'=>0];

// ==== Total semua pesanan ====
$res = $conn->query("SELECT COUNT(*) AS c FROM orders");
$kpi['total_orders'] = (int)($res?->fetch_assoc()['c'] ?? 0);

// ==== Hitung total pesanan hari ini ====
$today = (new DateTime('today'))->format('Y-m-d');
$stmt  = $conn->prepare("SELECT COUNT(*) AS c FROM orders WHERE DATE(created_at)=?");
$stmt->bind_param('s', $today);
$stmt->execute();
$kpi['orders_today'] = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

// ==== Hitung jumlah menu tersedia ====
$res = $conn->query("SELECT COUNT(*) AS c FROM menu");
$kpi['menu_count'] = (int)($res?->fetch_assoc()['c'] ?? 0);

// ==== Hitung pendapatan hari ini ====
$stmt = $conn->prepare("
  SELECT COALESCE(SUM(total), 0) AS s
  FROM orders
  WHERE DATE(created_at) = ?
    AND LOWER(order_status) NOT IN (
      'cancelled','canceled','cancel',
      'failed','void','refunded'
    )
");
$stmt->bind_param('s', $today);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$kpi['income_today'] = (int)($row['s'] ?? 0);
$stmt->close();


// ==== Data untuk chart revenue 7 hari terakhir ====
$labels  = [];
$revenue = [];
$map     = [];

// ==== Generate label tanggal untuk 7 hari ====
for ($i=6; $i>=0; $i--) {
  $d = (new DateTime("today -$i day"))->format('Y-m-d');
  $labels[] = $d;
  $map[$d]  = 0.0;
}

// ==== Query revenue ====
$sql = "
  SELECT DATE(created_at) AS d, SUM(total) AS s
  FROM orders
  WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
  GROUP BY DATE(created_at)
  ORDER BY d ASC
";

// ==== Eksekusi query dan isi map ====
$res = $conn->query($sql);
while ($row = $res?->fetch_assoc()) {
  $map[$row['d']] = (float)$row['s'];
}

// ==== Buat array revenue final urut berdasarkan label ====
foreach ($labels as $d) $revenue[] = $map[$d];

// ==== Distribusi status order hari ini ====
$statusBuckets = [
  'new'        => 0,
  'processing' => 0,
  'ready'      => 0,
  'completed'  => 0,
  'cancelled'  => 0,
];

// ==== Query status distribusi ====
$stmt = $conn->prepare("
  SELECT LOWER(order_status) AS s, COUNT(*) AS c
  FROM orders
  WHERE DATE(created_at) = ?
  GROUP BY LOWER(order_status)
");
$stmt->bind_param('s', $today);
$stmt->execute();
$rst = $stmt->get_result();

// ==== Proses hasil distribusi status ====
while ($row = $rst?->fetch_assoc()) {
  $s = (string)$row['s'];   // Status
  $c = (int)$row['c'];      // Jumlah

  // ==== Jika status dikenali tambahkan ====
  if (isset($statusBuckets[$s])) {
    $statusBuckets[$s] += $c;

  // ==== Normalisasi status cancel/canceled ====
  } else {
    if (in_array($s, ['canceled','cancel','cancelled','failed','void','refunded'], true)) {
      $statusBuckets['cancelled'] += $c;
    }
  }
}
$stmt->close();

// ==== Data array final untuk chart status ====
$distToday = [
  $statusBuckets['new'],
  $statusBuckets['processing'],
  $statusBuckets['ready'],
  $statusBuckets['completed'],
  $statusBuckets['cancelled'],
];
?>

<!-- ==== Deklarasi tipe dokumen HTML5 ==== -->
<!doctype html>

<!-- ==== Mulai struktur HTML dengan bahasa Indonesia ==== -->
<html lang="id">

<!-- ==== Bagian HEAD (informasi global dokumen) ==== -->
<head>

  <!-- ==== Set karakter encoding UTF-8 ==== -->
  <meta charset="utf-8">

  <!-- ==== Atur viewport agar responsif di mobile ==== -->
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- ==== Judul halaman tab browser ==== -->
  <title>Karyawan Desk — Caffora</title>

  <!-- ==== Bootstrap CSS (CDN) untuk styling komponen ==== -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- ==== Bootstrap Icons untuk ikon vector ==== -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <!-- ==== Iconify untuk ikon tambahan ==== -->
  <script src="https://code.iconify.design/2/2.2.1/iconify.min.js"></script>

  <!-- ==== Chart.js untuk grafik statistik pada dashboard ==== -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<style>                                           /* start style */
    :root {                                       /* root variable */
      --gold:#ffd54f;                             /* warna gold utama */
      --gold-200:#ffe883;                         /* gold level 200 */
      --gold-soft:#f4d67a;                        /* gold soft highlight */
      --ink:#111827;                              /* warna teks utama */
      --muted:#6B7280;                            /* teks muted/secondary */
      --brown:#4B3F36;                            /* warna coklat */
      --radius:18px;                              /* border radius global */
      --sidebar-w:320px;                          /* width sidebar */
    }                                             /* end root */

    body {                                        /* gaya global body */
      background:#FAFAFA;                         /* bg abu soft */
      color:var(--ink);                           /* warna default teks */
      font-family:Inter,system-ui,-apple-system,  /* font family */
      Segoe UI,Roboto,Arial;                      /* font fallback */
    }                                             /* end body */

    .d-none {                                     /* util hide class */
      display:none !important;                    /* hide absolute */
    }                                             /* end .d-none */

    /* ===== Sidebar ===== */                     /* section sidebar */

    .sidebar {                                    /* wrapper sidebar */
      position:fixed;                             /* posisi fixed */
      left:-320px;                                /* hidden awal kiri */
      top:0;                                      /* top 0 */
      bottom:0;                                   /* full height */
      width:var(--sidebar-w);                     /* lebar sidebar */
      background:#fff;                            /* warna background */
      border-right:1px solid rgba(0,0,0,.04);     /* border kanan */
      transition:left .25s ease;                  /* animasi transition */
      z-index:1050;                               /* layer paling atas */
      padding:14px 18px 18px;                     /* padding dalam */
      overflow-y:auto;                            /* scroll auto */
    }                                             /* end sidebar */

    .sidebar.show { left:0; }                     /* state tampil */

    .sidebar-head {                               /* header sidebar */
      display:flex;                               /* flexbox */
      align-items:center;                         /* align center */
      justify-content:space-between;              /* space between */
      gap:10px;                                   /* jarak antar item */
      margin-bottom:10px;                         /* margin bawah */
    }                                             /* end sidebar-head */

    .sidebar-inner-toggle,                        /* tombol toggle */
    .sidebar-close-btn {                          /* tombol close */
      background:transparent;                     /* background kosong */
      border:0;                                   /* no-border */
      width:40px;                                 /* width btn */
      height:36px;                                /* height btn */
      display:grid;                               /* grid center */
      place-items:center;                         /* place center */
    }                                             /* end btn group */

    .hamb-icon {                                  /* wrapper icon */
      width:24px;                                 /* width */
      height:20px;                                /* height */
      display:flex;                               /* flexbox */
      flex-direction:column;                      /* kolom */
      justify-content:space-between;              /* space */
      gap:4px;                                    /* gap */
    }                                             /* end hamb-icon */

    .hamb-icon span {                             /* garis icon */
      height:2px;                                 /* height line */
      background:var(--brown);                    /* warna garis */
      border-radius:99px;                         /* rounded full */
    }                                             /* end span */

    .sidebar .nav-link {                          /* link sidebar */
      display:flex;                               /* flexbox */
      align-items:center;                         /* center align */
      gap:12px;                                   /* gap icon */
      padding:12px 14px;                          /* padding area */
      border-radius:16px;                         /* rounded */
      color:#111;                                 /* text color */
      font-weight:600;                            /* bold font */
      text-decoration:none;                       /* no underline */
      background:transparent;                     /* clear bg */
      user-select:none;                           /* no select */
    }                                             /* end nav-link */

    .sidebar .nav-link:hover,                     /* hover */
    .sidebar .nav-link:focus,                     /* focus */
    .sidebar .nav-link:active {                   /* active */
      background:rgba(255,213,79,0.25);           /* bg yellow soft */
      color:#111;                                 /* text black */
      outline:none;                               /* remove outline */
      box-shadow:none;                            /* remove shadow */
    }                                             /* end state hover */

    .sidebar hr {                                 /* line divider */
      border-color:rgba(0,0,0,.05);               /* warna garis */
      opacity:1;                                  /* opacity full */
    }                                             /* end hr */

    /* ===== content ===== */                     /* section content */
    .content {                                    /* main content */
      margin-left:0;                              /* no offset */
      padding:16px 14px 40px;                     /* padding area */
    }                                             /* end content */

    /* ===== topbar ===== */                      /* section topbar */
    .topbar {                                     /* wrapper topbar */
      display:flex;                               /* flexbox */
      align-items:center;                         /* center align */
      gap:12px;                                   /* spacing */
      margin-bottom:16px;                         /* margin bottom */
    }                                             /* end topbar */

    .btn-menu {                                   /* menu opener */
      background:transparent;                     /* clear bg */
      border:0;                                   /* no border */
      width:40px;                                 /* width */
      height:38px;                                /* height */
      display:grid;                               /* grid */
      place-items:center;                         /* center icon */
      flex:0 0 auto;                              /* no stretch */
    }                                             /* end btn menu */

    /* ===== SEARCH ===== */                      /* search section */
    .search-box {                                 /* wrapper */
      position:relative;                          /* relative */
      flex:1 1 auto;                              /* flexible */
      min-width:0;                                /* minimal width */
    }                                             /* end search-box */

    .search-input {                               /* input field */
      height:46px;                                /* height */
      width:100%;                                 /* full width */
      border-radius:9999px;                       /* pill shape */
      padding-left:16px;                          /* padding left */
      padding-right:44px;                         /* space for icon */
      border:1px solid #e5e7eb;                   /* border default */
      background:#fff;                            /* bg white */
      outline:none !important;                    /* remove outline */
      transition:border-color .12s ease;          /* smooth transition */
    }                                             /* end search-input */

    .search-input:focus {                         /* focus state */
      border-color:var(--gold-soft) !important;   /* gold border */
      background:#fff;                             /* keep white */
      box-shadow:none !important;                 /* remove shadow */
    }                                             /* end focus */

    .search-icon {                                /* search icon */
      position:absolute;                          /* positioned */
      right:16px;                                 /* right align */
      top:50%;                                    /* center vertical */
      transform:translateY(-50%);                 /* shift up half */
      font-size:1.1rem;                           /* icon size */
      color:var(--brown);                         /* color */
      cursor:pointer;                             /* clickable */
    }                                             /* end search-icon */

    .search-suggest {                             /* suggestion box */
      position:absolute;                          /* absolute */
      top:100%;                                   /* below input */
      left:0;                                     /* align left */
      margin-top:6px;                             /* margin */
      background:#fff;                            /* white bg */
      border:1px solid rgba(247,215,141,.8);      /* gold border */
      border-radius:16px;                         /* rounded */
      box-shadow:0 12px 28px rgba(0,0,0,.08);     /* shadow */
      width:100%;                                 /* full width */
      max-height:280px;                           /* limit height */
      overflow-y:auto;                            /* scroll */
      display:none;                               /* hidden default */
      z-index:40;                                 /* top layer */
    }                                             /* end suggest */

    .search-suggest.visible { display:block; }    /* state visible */

    .search-suggest .item {                       /* item suggestion */
      padding:10px 14px 6px;                      /* padding */
      border-bottom:1px solid rgba(0,0,0,.03);    /* divider */
      cursor:pointer;                             /* pointer */
    }                                             /* end item */

    .search-suggest .item:last-child {            /* last item */
      border-bottom:0;                            /* no divider */
    }                                             /* end last */

    .search-suggest .item:hover {                 /* hover item */
      background:#fffbea;                         /* yellow soft */
    }                                             /* end hover */

    .search-suggest .item small {                 /* label kecil */
      display:block;                              /* block */
      color:#6b7280;                              /* muted text */
      font-size:.74rem;                           /* size small */
      margin-top:2px;                             /* spacing */
    }                                             /* end small */

    .search-empty {                               /* empty result msg */
      padding:12px 14px;                          /* padding */
      color:#6b7280;                              /* muted */
      font-size:.8rem;                            /* text size */
    }                                             /* end empty */

    .top-actions {                                /* wrapper icons */
      display:flex;                               /* flexbox */
      align-items:center;                         /* align center */
      gap:14px;                                   /* spacing */
      flex:0 0 auto;                              /* no stretch */
    }                                             /* end top-actions */

    .icon-btn {                                   /* tombol icon */
      width:38px;                                 /* width */
      height:38px;                                /* height */
      border-radius:999px;                        /* circle */
      display:flex;                               /* flex */
      align-items:center;                         /* center */
      justify-content:center;                     /* center */
      color:var(--brown);                         /* text color */
      text-decoration:none;                       /* no underline */
      background:transparent;                     /* clear bg */
      outline:none;                               /* no outline */
    }                                             /* end icon-btn */

    .icon-btn:focus,                              /* focus */
    .icon-btn:active {                            /* active */
      outline:none;                               /* no outline */
      box-shadow:none;                            /* remove shadow */
      color:var(--brown);                         /* text color */
    }                                             /* end press */

    #btnBell { position:relative; }               /* bell wrapper */

    #badgeNotif.notif-dot {                       /* notif badge */
      position:absolute;                          /* absolute */
      top:3px;                                    /* top */
      right:5px;                                  /* right */
      width:8px;                                  /* size */
      height:8px;                                 /* height */
      background:#4b3f36;                         /* brown */
      border-radius:50%;                          /* circle */
      display:inline-block;                       /* display */
      box-shadow:0 0 0 1.5px #fff;                /* border white */
    }                                             /* end notif dot */

    #badgeNotif.d-none {
      display:none !important;
    }                                             /* hide badge */

    /* KPI & cards section */
    .kpi,
    .cardx {
      background:#fff;                            /* white bg */
      border:1px solid #f7d78d;                   /* border gold */
      border-radius:var(--radius);                /* rounded */
      padding:18px;                               /* padding */
    }                                             /* end card */

    .kpi .ico {                                   /* icon KPI */
      width:44px;                                 /* width */
      height:44px;                                /* height */
      border-radius:12px;                         /* rounded */
      background:var(--gold-200);                 /* gold color */
      display:grid;                               /* grid center */
      place-items:center;                         /* center */
    }                                             /* end ico */

    /* chart styling */                           /* section chart style start */
   .charts-row .cardx.chart-wrap {                /* wrapper chart card */
   height:100%;                                   /* full height */
   display:flex;                                  /* use flex layout */
   flex-direction:column;                         /* vertical children */
   gap:12px;                                      /* gap between elements */
  }                                               /* end chart-wrap */


  .charts-row .chart-body {                       /* body chart container */
   flex:1 1 auto;                                 /* flexible height */
   }                                              /* end chart-body */


   .charts-row canvas {                           /* canvas chart */
   width:100% !important;                         /* full width force */
   height:100% !important;                        /* full height force */
   max-height:300px;                              /* max visual height */
  }                                               /* end canvas chart */


  /* backdrop default */                         /* backdrop mobile section */
  .backdrop-mobile {                             /* backdrop element */
  display:none;                                  /* hidden by default */
}                                                /* end backdrop-mobile */


.backdrop-mobile.active {                        /* active when sidebar open */
  display:block;                                 /* show overlay */
  position:fixed;                                /* fixed overlay */
  inset:0;                                       /* cover full screen */
  background:rgba(0,0,0,.35);                    /* dim color */
  z-index:1040;                                  /* above content */
}                                                /* end active backdrop */

    

    @media (min-width: 992px) {                  /* media desktop start */
      .content { padding:20px 26px 50px; }       /* padding desktop */
      .search-box { max-width:1100px; }          /* max width search desktop */
}                                                /* media desktop end */


  @media (min-width: 768px) and                   /* media tablet start */
  (max-width: 991.98px) {                         /* tablet breakpoint limit */
       .content { padding:18px 16px 50px; }       /* padding tablet */

      .charts-row {                               /* wrapper charts tablet */
        display:grid;                             /* use grid */
        grid-template-columns:minmax(0,1.05fr)    /* column layout chart 1 */
                                 minmax(0,0.95fr);/* column layout chart 2 */
        gap:14px;                                 /* space grid */
        align-items:stretch;                      /* stretch alignment */
      }                                           /* end charts-row tablet */

      .charts-row > [class^="col-"],              /* responsive rule 1 */
      .charts-row > [class*=" col-"] {            /* responsive rule 2 */
        width:100%;                               /* full width */
        flex:0 0 auto;                            /* fixed flex */
      }                                           /* end column tablet */

      .charts-row .cardx.chart-wrap {             /* card wrapper chart */
        padding:14px;                             /* padding tablet */
      }                                           /* end chart-wrap tablet */

      .charts-row canvas {                        /* canvas chart tablet */
        max-height:240px !important;              /* bigger limit */
      }                                           /* end canvas tablet */
 }                                                /* end tablet media */


  @media (min-width: 992px) and                   /* large tablet start */
  (max-width: 1199.98px) {                        /* breakpoint max */
       .charts-row {                              /* wrapper charts layout */
        display:grid;                             /* use grid layout */
        grid-template-columns:minmax(0,0.6fr)     /* chart big left */
                             minmax(0,0.4fr);     /* chart small right */
        gap:16px;                                 /* grid gap */
        align-items:stretch;                      /* stretch align */
      }                                           /* end charts layout */

      .charts-row > [class^="col-"],              /* same column matching */
      .charts-row > [class*=" col-"] {            /* responsive col */
        width:100%;                               /* full width */
        flex:0 0 auto;                            /* no growth */
      }                                           /* end responsive column */

      .charts-row .cardx.chart-wrap {             /* card wrapper */
        padding:16px 16px 14px;                   /* padding LTR */
        height:100%;                              /* full height */
      }                                           /* end cardwrap */

      .charts-row canvas {                        /* canvas chart */
        max-height:300px !important;              /* bigger chart height */
   }                                              /* end canvas */
 }                                                /* end large tablet */

</style>                                    

  <!-- Menutup bagian head -->
</head> <!-- akhir tag head -->

<!-- Membuka body dan beri id top sebagai anchor scroll -->
<body id="top"> <!-- body start -->

<!-- Overlay gelap untuk backdrop sidebar di mobile -->
<div
  id="backdrop"
  class="backdrop-mobile"
></div>

<!-- Wrapper utama untuk sidebar navigasi -->
<aside
  class="sidebar"
  id="sideNav"
>

  <!-- Header bagian atas sidebar -->
  <div class="sidebar-head">

    <!-- Tombol toggle sidebar dari dalam sidebar -->
    <button
      class="sidebar-inner-toggle"
      id="toggleSidebarInside"
      aria-label="Tutup menu"
    ></button>

    <!-- Tombol untuk menutup sidebar (ikon X) -->
    <button
      class="sidebar-close-btn"
      id="closeSidebar"
      aria-label="Tutup menu"
    >
      <!-- Ikon X Bootstrap Icons -->
      <i class="bi bi-x-lg"></i>
    </button>
  </div>

  <!-- Navigasi utama yang berisi list menu sidebar -->
  <nav
    class="nav flex-column gap-2"
    id="sidebar-nav"
  >

    <!-- Item menu: Dashboard karyawan -->
    <a
      class="nav-link"
      href="<?= BASE_URL ?>/public/karyawan/index.php"
    >
      <!-- Ikon home dashboard -->
      <i class="bi bi-house-door"></i>
      <!-- Teks menu dashboard -->
      Dashboard
    </a>

    <!-- Item menu: Halaman orders -->
    <a
      class="nav-link"
      href="<?= BASE_URL ?>/public/karyawan/orders.php"
    >
      <!-- Ikon struk / receipt -->
      <i class="bi bi-receipt"></i>
      <!-- Teks menu orders -->
      Orders
    </a>

    <!-- Item menu: Stok menu / produk -->
    <a
      class="nav-link"
      href="<?= BASE_URL ?>/public/karyawan/stock.php"
    >
      <!-- Ikon box / paket -->
      <i class="bi bi-box-seam"></i>
      <!-- Teks menu stok -->
      Menu Stock
    </a>

    <!-- Item menu: Pengaturan akun karyawan -->
    <a
      class="nav-link"
      href="<?= BASE_URL ?>/public/karyawan/settings.php"
    >
      <!-- Ikon gear / settings -->
      <i class="bi bi-gear"></i>
      <!-- Teks menu settings -->
      Settings
    </a>

    <!-- Garis pemisah antara menu utama dan lainnya -->
    <hr>

    <!-- Item menu: Bantuan / help center -->
    <a
      class="nav-link"
      href="<?= BASE_URL ?>/public/karyawan/help.php"
    >
      <!-- Ikon tanda tanya -->
      <i class="bi bi-question-circle"></i>
      <!-- Teks menu help center -->
      Help Center
    </a>

    <!-- Item menu: Logout karyawan -->
    <a
      class="nav-link"
      href="<?= BASE_URL ?>/backend/logout.php"
    >
      <!-- Ikon keluar / box-arrow-right -->
      <i class="bi bi-box-arrow-right"></i>
      <!-- Teks menu logout -->
      Logout
    </a>

  </nav>
</aside> <!-- akhir sidebar -->

<!-- Area utama konten halaman dashboard -->
<main class="content">

  <!-- Bar atas yang berisi tombol menu, search, dan ikon akun -->
  <div class="topbar">

    <!-- Tombol untuk membuka sidebar (di topbar) -->
    <button
      class="btn-menu"
      id="openSidebar"
      aria-label="Buka menu"
    >
      <!-- Ikon hamburger (tiga garis) -->
      <div class="hamb-icon">
        <!-- Garis pertama hamburger -->
        <span></span>
        <!-- Garis kedua hamburger -->
        <span></span>
        <!-- Garis ketiga hamburger -->
        <span></span>
      </div>
    </button>

    <!-- Wrapper untuk komponen pencarian -->
    <div class="search-box">

      <!-- Input teks untuk fitur search -->
      <input
        class="search-input"
        id="searchInput"
        placeholder="Search..."
        autocomplete="off"
      />

      <!-- Ikon search di sisi kanan input -->
      <i
        class="bi bi-search search-icon"
        id="searchIcon"
      ></i>

      <!-- Kontainer dropdown suggestion hasil search -->
      <div
        class="search-suggest"
        id="searchSuggest"
      ></div>

    </div>

    <!-- Kumpulan tombol aksi di sisi kanan topbar -->
    <div class="top-actions">

      <!-- Tombol menuju halaman notifikasi -->
      <a
        id="btnBell"
        class="icon-btn position-relative text-decoration-none"
        href="<?= BASE_URL ?>/public/karyawan/notifications.php"
        aria-label="Notifikasi"
      >
        <!-- Ikon lonceng notifikasi -->
        <span
          class="iconify"
          data-icon="mdi:bell-outline"
          data-width="24"
          data-height="24"
        ></span>

        <!-- Titik kecil penanda ada notif baru -->
        <span
          id="badgeNotif"
          class="notif-dot d-none"
        ></span>
      </a>

      <!-- Tombol menuju halaman pengaturan / profile akun -->
      <a
        href="<?= BASE_URL ?>/public/karyawan/settings.php"
        class="icon-btn text-decoration-none"
        aria-label="Akun"
      >
        <!-- Ikon avatar akun -->
        <span
          class="iconify"
          data-icon="mdi:account-circle-outline"
          data-width="28"
          data-height="28"
        ></span>
      </a>

    </div>
  </div>

  <!-- Judul utama halaman dashboard karyawan -->
  <h2 class="fw-bold mb-3">
    Dashboard Karyawan
  </h2>

  <!-- Grid KPI utama dashboard (4 kolom responsif) -->
  <div class="row g-3 mb-4">

    <!-- Kolom KPI: Total semua pesanan -->
    <div class="col-12 col-md-6 col-lg-3">
      <!-- Card KPI dengan ikon dan nilai -->
      <div class="kpi d-flex align-items-center gap-3">

        <!-- Lingkaran ikon untuk KPI -->
        <div class="ico">
          <!-- Ikon list (representasi pesanan) -->
          <i class="bi bi-list-ul"></i>
        </div>

        <!-- Teks label dan angka KPI -->
        <div>
          <!-- Label deskriptif KPI -->
          <div class="text-muted small">
            Total Pesanan
          </div>
          <!-- Nilai angka KPI total pesanan -->
          <div class="fs-4 fw-bold">
            <?= number_format($kpi['total_orders']) ?>
          </div>
        </div>

      </div>
    </div>

    <!-- Kolom KPI: Total pesanan hari ini -->
    <div class="col-12 col-md-6 col-lg-3">
      <!-- Card KPI pesanan hari ini -->
      <div class="kpi d-flex align-items-center gap-3">

        <!-- Ikon kalender hari ini -->
        <div class="ico">
          <i class="bi bi-calendar2-day"></i>
        </div>

        <!-- Teks label dan nilai -->
        <div>
          <!-- Label KPI pesanan hari ini -->
          <div class="text-muted small">
            Pesanan Hari Ini
          </div>
          <!-- Nilai angka pesanan hari ini -->
          <div class="fs-4 fw-bold">
            <?= number_format($kpi['orders_today']) ?>
          </div>
        </div>

      </div>
    </div>

    <!-- Kolom KPI: Jumlah menu tersedia -->
    <div class="col-12 col-md-6 col-lg-3">
      <!-- Card KPI menu tersedia -->
      <div class="kpi d-flex align-items-center gap-3">

        <!-- Ikon box untuk menu -->
        <div class="ico">
          <i class="bi bi-box"></i>
        </div>

        <!-- Teks label dan nilai -->
        <div>
          <!-- Label jumlah menu -->
          <div class="text-muted small">
            Menu Tersedia
          </div>
          <!-- Nilai angka menu -->
          <div class="fs-4 fw-bold">
            <?= number_format($kpi['menu_count']) ?>
          </div>
        </div>

      </div>
    </div>

  <div class="col-12 col-md-6 col-lg-3">
  <div class="kpi d-flex align-items-center gap-3">
    <div class="ico">
      <i class="bi bi-cash-coin"></i>
    </div>
    <div>
      <div class="text-muted small">
        Pendapatan Hari Ini
      </div>
      <div class="fs-4 fw-bold">
        Rp <?= number_format($kpi['income_today'], 0, ',', '.') ?>
      </div>
    </div>
  </div>
</div>

      </div>
    </div>

  </div> <!-- akhir row KPI -->

  <!-- Wrapper row untuk dua chart: revenue & distribusi status -->
  <div class="row g-3 charts-row align-items-stretch">

    <!-- Kolom chart revenue 7 hari terakhir -->
    <div class="col-12 col-xl-7 d-flex">
      <!-- Card chart revenue -->
      <div class="cardx chart-wrap w-100">
        <!-- Judul chart revenue -->
        <h6 class="fw-bold mb-2">
          Revenue 7 Hari Terakhir
        </h6>
        <!-- Area body untuk canvas chart -->
        <div class="chart-body">
          <!-- Canvas Chart.js untuk revenue -->
          <canvas id="revChart"></canvas>
        </div>
      </div>
    </div>

    <!-- Kolom chart distribusi status hari ini -->
    <div class="col-12 col-xl-5 d-flex">
      <!-- Card chart distribusi status -->
      <div class="cardx chart-wrap w-100">
        <!-- Judul chart distribusi -->
        <h6 class="fw-bold mb-2">
          Distribusi Status Pesanan (Hari Ini)
        </h6>
        <!-- Area body untuk canvas chart -->
        <div class="chart-body">
          <!-- Canvas Chart.js untuk distribusi status -->
          <canvas id="distChart"></canvas>
        </div>
      </div>
    </div>

  </div> <!-- akhir row charts -->

</main> <!-- akhir main content -->

<!-- Load Bootstrap bundle (JS + Popper) -->
<script
  src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>

<!-- Script utama dashboard karyawan -->
<script>
// Simpan BASE_URL tanpa slash di akhir
const BASE = "<?= rtrim(BASE_URL,'/') ?>";


// ===============================
// == BADGE NOTIF (KARYAWAN) ====
// ===============================

// Definisi fungsi async untuk refresh badge notifikasi
async function refreshNotifBadge() {

  // Coba eksekusi blok request
  try {

    // Lakukan fetch ke endpoint unread_count notifikasi
    const r = await fetch(
      `${BASE}/backend/api/notifications.php?action=unread_count`,
      {
        // Kirim cookie/session ke server
        credentials: 'same-origin',

        // Minta respon dalam bentuk JSON
        headers: {
          'Accept': 'application/json'
        }
      }
    );

    // Parse hasil response menjadi objek JS
    const js = await r.json();

    // Ambil elemen DOM untuk badge notifikasi
    const dot = document.getElementById('badgeNotif');

    // Cek jika respon OK dan jumlah notif > 0
    if (
      js &&
      js.ok &&
      Number(js.count || 0) > 0
    ) {

      // Tampilkan badge dengan menghapus class d-none
      dot.classList.remove('d-none');

    // Jika tidak ada unread notif
    } else {

      // Sembunyikan badge dengan menambah class d-none
      dot.classList.add('d-none');
    }

  // Jika terjadi error (mis. jaringan)
  } catch (e) {

    // Biarkan saja, tidak perlu throw error ke UI
    // diamkan saja jika error jaringan
  }
}

// Panggil fungsi refreshNotifBadge sekali saat load halaman
refreshNotifBadge();

// Set interval untuk refresh badge tiap 30 detik
setInterval(
  refreshNotifBadge,
  30000
); // refresh tiap 30 detik


// ======================================
// == SIDEBAR: KLIK ITEM JADI AKTIF ====
// ======================================

// Ambil semua elemen link di sidebar nav
document
  .querySelectorAll('#sidebar-nav .nav-link')
  .forEach(function (a) {

    // Tambahkan event listener klik pada setiap link
    a.addEventListener('click', function () {

      // Loop lagi semua nav-link sidebar
      document
        .querySelectorAll('#sidebar-nav .nav-link')
        .forEach(function (l) {

          // Hapus class active dari semua nav-link
          l.classList.remove('active');
        });

      // Tambahkan class active ke link yang diklik
      this.classList.add('active');

      // Jika lebar layar kurang dari 1200px (mode mobile/tablet)
      if (window.innerWidth < 1200) {

        // Tutup sidebar dengan menghapus class show
        document
          .getElementById('sideNav')
          .classList.remove('show');

        // Hilangkan backdrop dengan menghapus class active
        document
          .getElementById('backdrop')
          .classList.remove('active');
      }
    });
  });


// ============================
// == TOGGLE SIDEBAR SHOW ====
// ============================

// Ambil elemen utama sidebar dari DOM
const sideNav = document.getElementById('sideNav');

// Ambil elemen backdrop atau buat elemen baru jika tidak ada
const backdrop =
  document.getElementById('backdrop') ||
  document.createElement('div');

// Tambahkan listener pada tombol openSidebar jika ada
document
  .getElementById('openSidebar')
  ?.addEventListener('click', () => {

    // Tambahkan class show ke sidebar
    sideNav.classList.add('show');

    // Tambahkan class active ke backdrop
    backdrop.classList.add('active');
  });

// Tambahkan listener pada tombol closeSidebar jika ada
document
  .getElementById('closeSidebar')
  ?.addEventListener('click', () => {

    // Hapus class show dari sidebar
    sideNav.classList.remove('show');

    // Hapus class active dari backdrop
    backdrop.classList.remove('active');
  });

// Tambahkan listener pada tombol toggleSidebarInside jika ada
document
  .getElementById('toggleSidebarInside')
  ?.addEventListener('click', () => {

    // Tutup sidebar dari dalam
    sideNav.classList.remove('show');

    // Nonaktifkan backdrop
    backdrop.classList.remove('active');
  });

// Tambahkan listener pada klik backdrop (jika method addEventListener ada)
backdrop.addEventListener?.('click', () => {

  // Tutup sidebar ketika backdrop diklik
  sideNav.classList.remove('show');

  // Nonaktifkan backdrop
  backdrop.classList.remove('active');
});


// =====================
// == FUNGSI SEARCH ====
// =====================

// Definisi fungsi untuk meng-attach logika search ke input & suggest box
function attachSearch(inputEl, suggestEl) {

  // Jika input atau suggest tidak ada, langsung return
  if (!inputEl || !suggestEl) return;

  // Endpoint API untuk mencari data karyawan / order / menu
  const ENDPOINT =
    `${BASE}/backend/api/karyawan_search.php`;

  // Tambahkan listener input pada kolom search
  inputEl.addEventListener(
    'input',
    async function () {

      // Ambil nilai yang diketik dan trim spasi
      const q = this.value.trim();

      // Jika panjang query kurang dari 2 karakter, sembunyikan suggestion
      if (q.length < 2) {

        // Hilangkan kelas visible dari suggestion
        suggestEl.classList.remove('visible');

        // Kosongkan isi suggestion
        suggestEl.innerHTML = '';

        // Hentikan eksekusi
        return;
      }

      // Coba lakukan fetch ke endpoint search
      try {

        // Kirim request ke endpoint dengan query q (di-encode)
        const res = await fetch(
          ENDPOINT + "?q=" + encodeURIComponent(q),
          {
            // Header meminta response JSON
            headers: {
              'Accept': 'application/json'
            }
          }
        );

        // Jika response tidak OK (status bukan 2xx), hentikan
        if (!res.ok) return;

        // Parse body response menjadi JSON
        const data = await res.json();

        // Ambil array results atau jadikan array kosong jika tidak valid
        const arr =
          Array.isArray(data.results)
            ? data.results
            : [];

        // Jika tidak ada hasil, tampilkan pesan "Tidak ada hasil."
        if (!arr.length) {

          // Isi suggestion dengan pesan kosong
          suggestEl.innerHTML =
            '<div class="search-empty">Tidak ada hasil.</div>';

          // Tampilkan suggestion
          suggestEl.classList.add('visible');

          // Stop eksekusi lanjutan
          return;
        }

        // Siapkan string HTML hasil
        let html = '';

        // Loop setiap hasil pencarian
        arr.forEach(r => {

          // Tambahkan blok item ke string HTML
          html += `
          <div class="item" data-type="${r.type}" data-key="${r.key}">
            ${r.label ?? ''}
            ${r.sub ? `<small>${r.sub}</small>` : ''}
          </div>`;
        });

        // Set innerHTML suggestion dengan daftar item
        suggestEl.innerHTML = html;

        // Tampilkan suggestion
        suggestEl.classList.add('visible');

        // Ambil semua item suggestion yang sudah ter-render
        suggestEl
          .querySelectorAll('.item')
          .forEach(it => {

            // Tambahkan listener click pada tiap item
            it.addEventListener('click', () => {

              // Ambil data-type dari atribut data-type
              const type = it.dataset.type;

              // Ambil data-key dari atribut data-key
              const key = it.dataset.key;

              // Jika type adalah 'order'
              if (type === 'order') {

                // Redirect ke halaman orders dengan parameter search
                window.location =
                  `${BASE}/public/karyawan/orders.php?search=` +
                  encodeURIComponent(key);

              // Jika type adalah 'menu'
              } else if (type === 'menu') {

                // Redirect ke halaman stock dengan parameter search
                window.location =
                  `${BASE}/public/karyawan/stock.php?search=` +
                  encodeURIComponent(key);
              }
            });
          });

      // Jika error di blok try (network failure dll)
      } catch (e) {
        // Tidak melakukan apa-apa, supaya UI tidak crash
      }
    }
  );

  // Tambahkan listener klik global untuk menutup suggest jika klik di luar
  document.addEventListener(
    'click',
    (ev) => {

      // Jika target klik bukan bagian dari suggest dan bukan input
      if (
        !suggestEl.contains(ev.target) &&
        ev.target !== inputEl
      ) {

        // Hilangkan kelas visible dari suggest
        suggestEl.classList.remove('visible');
      }
    }
  );

  // Tambahkan listener pada ikon search jika ada
  document
    .getElementById('searchIcon')
    ?.addEventListener('click', () => {

      // Fokuskan kursor ke input search
      inputEl.focus();
    });
}

// Panggil fungsi attachSearch dengan input dan kontainer suggest
attachSearch(
  document.getElementById('searchInput'),
  document.getElementById('searchSuggest')
);


// =================
// == DATA CHART ==
// =================

// Ambil array label tanggal dari PHP (JSON encode)
const labels =
  <?= json_encode($labels, JSON_UNESCAPED_SLASHES) ?>;

// Ambil array nilai revenue dari PHP (JSON encode)
const revenue =
  <?= json_encode($revenue, JSON_UNESCAPED_SLASHES) ?>;

// Ambil array distribusi status hari ini dari PHP
const dist =
  <?= json_encode($distToday, JSON_UNESCAPED_SLASHES) ?>;

// Ambil nilai pendapatan hari ini dari PHP
// (sama seperti KPI "Pendapatan Hari Ini")
const incomeToday =
  <?= (int)($kpi['income_today'] ?? 0) ?>;


// =============================
// == CHART REVENUE (LINE) ====
// =============================

// Inisialisasi chart line untuk revenue
new Chart(
  document.getElementById('revChart'),
  {
    // Tipe chart line
    type: 'line',

    // Data yang akan digambar
    data: {
      // Label sumbu X (tanggal)
      labels: labels,

      // Dataset yang ditampilkan
      datasets: [
        {
          // Label legend dataset
          label: 'Revenue',

          // Nilai revenue per hari
          data: revenue,

          // Kelengkungan garis (0 = lurus)
          tension: 0.35,

          // Area di bawah garis diisi warna
          fill: true,

          // Warna garis utama
          borderColor: '#ffd54f',

          // Warna area fill di bawah garis
          backgroundColor: 'rgba(255,213,79,.18)',

          // Radius titik data
          pointRadius: 4,

          // Warna isi titik data
          pointBackgroundColor: '#ffd54f',

          // Warna border titik data
          pointBorderColor: '#ffd54f'
        }
      ]
    },

    // Opsi konfigurasi chart
    options: {
      // Jangan pakai rasio aspek default
      maintainAspectRatio: false,

      // Konfigurasi plugin Chart.js (legend, tooltip, dll)
      plugins: {
        legend: {
          // Sembunyikan legend
          display: false
        }
      },

      // Konfigurasi sumbu X dan Y
      scales: {
        // Sumbu Y (nilai rupiah)
        y: {
          // Pengaturan tampilan tick di sumbu Y
          ticks: {
            // Format nilai menjadi format Rupiah (IDR)
            callback: v =>
              new Intl.NumberFormat(
                'id-ID',
                {
                  style: 'currency',
                  currency: 'IDR',
                  maximumFractionDigits: 0
                }
              ).format(v)
          },

          // Warna grid garis horizontal
          grid: {
            color: 'rgba(17,24,39,.06)'
          }
        },

        // Sumbu X (tanggal)
        x: {
          // Matikan tampilan grid di sumbu X
          grid: {
            display: false
          }
        }
      }
    }
  }
);


// =================================
// == CHART DISTRIBUSI (DOUGHNUT) ==
// =================================

// Inisialisasi chart doughnut untuk distribusi status
new Chart(
  document.getElementById('distChart'),
  {
    // Tipe doughnut chart
    type: 'doughnut',

    // Data chart doughnut
    data: {
      // Label untuk tiap slice chart
      labels: [
        'New',
        'Processing',
        'Ready',
        'Completed',
        'Cancelled'
      ],

      // Dataset tunggal untuk distribusi
      datasets: [
        {
          // Array nilai untuk masing-masing status
          data: dist,

          // Lebar border antar slice
          borderWidth: 0,

          // Warna masing-masing slice
          backgroundColor: [
            '#ffe761',
            '#eae3c0',
            '#facf43',
            '#fdeb9e',
            '#edde3bff'
          ]
        }
      ]
    },

    // Opsi konfigurasi chart doughnut
    options: {
      // Jangan pakai rasio aspek default
      maintainAspectRatio: false,

      // Pengaturan plugin Chart.js
      plugins: {
        // Pengaturan legend di bagian bawah chart
        legend: {
          position: 'bottom'
        }
      }
    }
  }
);
</script>

</body>
</html>
