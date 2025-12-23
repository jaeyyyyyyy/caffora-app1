<?php
// ===================================================
// public/karyawan/stock.php
// ===================================================

// declare strict type
declare(strict_types=1);

// start session
session_start();

// load auth guard
require_once __DIR__ . '/../../backend/auth_guard.php';

// require login untuk role karyawan & admin
require_login(['karyawan','admin']);

// load config (BASE_URL, $conn, h())
require_once __DIR__ . '/../../backend/config.php';

// ===== data user utk topbar =====

// ambil user id dari session
$userId     = (int)($_SESSION['user_id'] ?? 0);

// ambil user name
$userName   = $_SESSION['user_name']  ?? 'Staff';

// ambil user email
$userEmail  = $_SESSION['user_email'] ?? '';

// ambil user avatar
$userAvatar = $_SESSION['user_avatar'] ?? '';

// inisial nama, dipakai jika avatar kosong
$initials   = strtoupper(substr($userName ?: 'U', 0, 2));

// url avatar
$avatarUrl = '';

// flag status avatar
$hasAvatar = false;

// jika avatar ada
if ($userAvatar) {

  // jika avatar url http
  if (str_starts_with($userAvatar, 'http')) {

    // langsung pakai url
    $avatarUrl = $userAvatar;

  } else {

    // jika bukan http, gabungkan base url + path
    $avatarUrl = rtrim(BASE_URL, '/') . (str_starts_with($userAvatar, '/') ? $userAvatar : '/' . $userAvatar);
  }

  // tandai avatar ada
  $hasAvatar = true;
}

// ===== helper =====

// format angka ke rupiah
function rupiah($n): string { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }

// ====== Actions: update status stok ======

// pesan status aksi
$msg = '';

// jika request POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // ambil id menu
  $id   = (int)($_POST['id'] ?? 0);

  // ambil status stok
  $stat = ($_POST['stock_status'] ?? '') === 'Sold Out' ? 'Sold Out' : 'Ready';

  // validasi id
  if ($id > 0) {

    // prepare query update
    $stmt = $conn->prepare("UPDATE menu SET stock_status=? WHERE id=?");

    // binding params
    $stmt->bind_param('si', $stat, $id);

    // execute
    $ok = $stmt->execute();

    // tutup statement
    $stmt->close();

    // set pesan
    $msg = $ok ? 'Status stok diperbarui.' : 'Gagal memperbarui status.';

  } else {

    // id tidak valid
    $msg = 'Data tidak valid.';
  }
}

// ====== Filter (GET) ======

// parameter pencarian
$q   = trim((string)($_GET['q'] ?? ''));

// list where conditions
$where = [];

// type parameter
$types = '';

// value parameters
$params = [];

// jika query pencarian tidak kosong
if ($q !== '') {

  // tambahkan kondisi like
  $where[] = '(name LIKE ? OR category LIKE ?)';

  // string wildcard
  $like = '%' . $q . '%';

  // append parameter
  $params[] = $like;
  $params[] = $like;

  // update mapping type
  $types .= 'ss';
}

// query select menu
$sql = 'SELECT * FROM menu';

// jika ada kondisi
if ($where) {

  // tambahkan where
  $sql .= ' WHERE ' . implode(' AND ', $where);
}

// order by terbaru
$sql .= ' ORDER BY created_at DESC';

// hasil menu
$menus = [];

// prepare query
$stmt = $conn->prepare($sql);

// jika berhasil prepare
if ($stmt) {

  // jika ada params
  if ($params) {

    // binding parameters
    $stmt->bind_param($types, ...$params);
  }

  // eksekusi query
  $stmt->execute();

  // ambil hasil
  $res = $stmt->get_result();

  // fetch semua data
  $menus = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

  // tutup statement
  $stmt->close();
}
?>

<!-- ===================================================== -->
<!-- Mulai dokumen HTML -->
<!-- ===================================================== -->
<!doctype html>

<!-- Bahasa dokumen -->
<html lang="id">

<head>
  <!-- Charset dokumen -->
  <meta charset="utf-8">

  <!-- Judul halaman -->
  <title>Menu Stock — Karyawan Desk</title>

  <!-- Responsif untuk mobile -->
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Load CSS Bootstrap -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
  >

  <!-- Load Bootstrap Icons -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    rel="stylesheet"
  >

  <!-- Iconify library untuk ikon tambahan -->
  <script src="https://code.iconify.design/2/2.2.1/iconify.min.js"></script>


 <style>
    :root{                                      /* variabel global CSS */
      --gold:#ffd54f;                           /* warna emas utama */
      --gold-200:#ffe883;                       /* varian emas lebih terang */
      --gold-soft:#f4d67a;                      /* emas lembut untuk accent */
      --ink:#111827;                            /* warna teks utama (gelap) */
      --muted:#6b7280;                          /* warna teks sekunder */
      --brown:#4B3F36;                          /* warna coklat brand Caffora */
      --radius:18px;                            /* radius border default card */
      --sidebar-w:320px;                        /* lebar sidebar */
    }

    body{                                       /* gaya dasar body */
      background:#FAFAFA;                       /* warna latar abu terang */
      color:var(--ink);                         /* warna teks default */
      font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial; /* stack font */
    }

    /* ===== Sidebar ===== */
    .sidebar{                                   /* wrapper sidebar kiri */
      position:fixed;                           /* posisi fixed layar */
      left:-320px;                              /* awalnya tersembunyi di kiri */
      top:0;                                    /* menempel atas */
      bottom:0;                                 /* menempel bawah */
      width:var(--sidebar-w);                   /* gunakan lebar dari variabel */
      background:#fff;                          /* latar putih */
      border-right:1px solid rgba(0,0,0,.04);   /* garis tipis di kanan */
      transition:left .25s ease;                /* animasi slide in/out */
      z-index:1050;                             /* di atas konten utama */
      padding:14px 18px 18px;                   /* padding dalam sidebar */
      overflow-y:auto;                          /* scroll bila konten tinggi */
    }

    .sidebar.show{ left:0; }                    /* ketika show, geser masuk */

    .sidebar-head{                              /* header sidebar (logo + close) */
      display:flex;                             /* gunakan flexbox */
      align-items:center;                       /* vertikal tengah */
      justify-content:space-between;            /* spasi antar elemen */
      gap:10px;                                 /* jarak antar child */
      margin-bottom:10px;                       /* jarak bawah header */
    }

    .sidebar-inner-toggle,                      /* tombol toggle di dalam sidebar */
    .sidebar-close-btn{                         /* tombol close sidebar */
      background:transparent;                   /* tanpa background */
      border:0;                                 /* tanpa border */
      width:40px;                               /* ukuran tombol */
      height:36px;                              /* ukuran tombol */
      display:grid;                             /* gunakan grid */
      place-items:center;                       /* icon di tengah */
    }

    .hamb-icon{                                 /* ikon hamburger (garis 3) */
      width:24px;                               /* lebar ikon */
      height:20px;                              /* tinggi ikon */
      display:flex;                             /* gunakan flex */
      flex-direction:column;                    /* susun vertikal */
      justify-content:space-between;            /* beri jarak antar garis */
      gap:4px;                                  /* jarak antar span */
    }

    .hamb-icon span{                            /* garis dalam ikon hamburger */
      height:2px;                               /* tinggi garis */
      background:var(--brown);                  /* warna garis coklat */
      border-radius:99px;                       /* ujung garis tumpul */
    }

    .sidebar .nav-link{                         /* link menu di sidebar */
      display:flex;                             /* icon + text sejajar */
      align-items:center;                       /* vertikal tengah */
      gap:12px;                                 /* jarak icon-text */
      padding:12px 14px;                        /* padding dalam item */
      border-radius:16px;                       /* sudut membulat */
      color:#111;                               /* warna teks hitam */
      font-weight:600;                          /* teks agak tebal */
      text-decoration:none;                     /* hilangkan underline */
      background:transparent;                   /* latar transparan default */
      user-select:none;                         /* teks tidak bisa di-select */
    }

    .sidebar .nav-link:hover,                   /* efek hover nav-link */
    .sidebar .nav-link:focus,                   /* efek focus nav-link */
    .sidebar .nav-link:active{                  /* efek active nav-link */
      background:rgba(255,213,79,0.25);         /* latar kuning lembut */
      color:#111;                               /* warna teks tetap hitam */
      outline:none;                             /* tanpa outline default */
      box-shadow:none;                          /* tanpa shadow tambahan */
    }

    .sidebar hr{                                /* garis pemisah di sidebar */
      border-color:rgba(0,0,0,.05);             /* warna garis tipis */
      opacity:1;                                /* pastikan terlihat */
    }

    /* ===== CONTENT ===== */
    .content{                                   /* wrapper konten utama */
      margin-left:0;                            /* tanpa margin kiri (mobile) */
      padding:16px 14px 40px;                   /* padding konten */
    }

    /* ===== TOPBAR ===== */
    .topbar{                                    /* bar atas (search + actions) */
      display:flex;                             /* gunakan flexbox */
      align-items:center;                       /* vertikal tengah */
      gap:12px;                                 /* jarak antar elemen */
      margin-bottom:16px;                       /* jarak ke bawah */
    }

    .btn-menu{                                  /* tombol buka sidebar (burger) */
      background:transparent;                   /* tanpa latar */
      border:0;                                 /* tanpa border */
      width:40px;                               /* lebar tombol */
      height:38px;                              /* tinggi tombol */
      display:grid;                             /* pakai grid */
      place-items:center;                       /* icon di tengah */
      flex:0 0 auto;                            /* tidak ikut melar */
    }

    /* ===== SEARCH ===== */
    .search-box{                                /* wrapper input search */
      position:relative;                        /* untuk icon & dropdown */
      flex:1 1 auto;                            /* mengambil ruang sisa */
      min-width:0;                              /* izinkan mengecil fleksibel */
    }

    .search-input{                              /* input teks search */
      height:46px;                              /* tinggi input */
      width:100%;                               /* full lebar parent */
      border-radius:9999px;                     /* kapsul bulat penuh */
      padding-left:16px;                        /* padding kiri */
      padding-right:44px;                       /* ruang untuk icon kanan */
      border:1px solid #e5e7eb;                 /* border abu tipis */
      background:#fff;                          /* latar putih */
      outline:none !important;                  /* hilangkan outline browser */
      transition:border-color .1s ease;         /* transisi warna border */
    }

    .search-input:focus{                        /* saat search focus */
      border-color:var(--gold-soft) !important; /* border kuning lembut */
      background:#fff;                          /* latar tetap putih */
      box-shadow:none !important;               /* tanpa shadow default */
    }

    .search-icon{                               /* icon search di dalam input */
      position:absolute;                        /* pos absolute dalam box */
      right:16px;                               /* jarak kanan */
      top:50%;                                  /* posisi vertical tengah */
      transform:translateY(-50%);               /* beneran di tengah */
      font-size:1.1rem;                         /* ukuran icon */
      color:var(--brown);                       /* warna coklat */
      cursor:pointer;                           /* berubah jadi pointer */
    }

    /* dropdown hasil */
    .search-suggest{                            /* dropdown suggestion search */
      position:absolute;                        /* di bawah input */
      top:100%;                                 /* tepat di bawah input */
      left:0;                                   /* rata kiri */
      margin-top:6px;                           /* sedikit jarak ke input */
      background:#fff;                          /* latar putih */
      border:1px solid rgba(247,215,141,.8);    /* border kuning lembut */
      border-radius:16px;                       /* sudut membulat */
      box-shadow:0 12px 28px rgba(0,0,0,.08);   /* shadow lembut */
      width:100%;                               /* lebar mengikuti input */
      max-height:280px;                         /* tinggi maksimum */
      overflow-y:auto;                          /* scroll jika tinggi */
      display:none;                             /* default tersembunyi */
      z-index:40;                               /* di atas konten biasa */
    }

    .search-suggest.visible{                    /* saat dropdown aktif */
      display:block;                            /* tampilkan */
    }

    .search-suggest .item{                      /* item dalam suggestion */
      padding:10px 14px 6px;                    /* padding dalam item */
      border-bottom:1px solid rgba(0,0,0,.03);  /* garis pemisah */
      cursor:pointer;                           /* kursor pointer */
    }

    .search-suggest .item:last-child{           /* item terakhir di dropdown */
      border-bottom:0;                          /* tanpa border bawah */
    }

    .search-suggest .item:hover{                /* efek hover item */
      background:#fffbea;                       /* latar kuning lembut */
    }

    .search-suggest .item small{                /* teks kecil di suggestion */
      display:block;                            /* tampil blok */
      color:#6b7280;                            /* warna abu muted */
      font-size:.74rem;                         /* ukuran kecil */
      margin-top:2px;                           /* jarak atas kecil */
    }

    .search-empty{                              /* teks ketika hasil kosong */
      padding:12px 14px;                        /* padding dalam */
      color:#6b7280;                            /* warna abu */
      font-size:.8rem;                          /* ukuran kecil */
    }

    .top-actions{                               /* wrapper ikon di kanan topbar */
      display:flex;                             /* gunakan flex */
      align-items:center;                       /* vertikal tengah */
      gap:14px;                                 /* jarak antar ikon */
      flex:0 0 auto;                            /* tidak melar */
    }

    .icon-btn{                                  /* tombol ikon umum (bell, dsb) */
      width:38px;                               /* lebar tombol */
      height:38px;                              /* tinggi tombol */
      border-radius:999px;                      /* bentuk kapsul */
      display:flex;                             /* flex */
      align-items:center;                       /* vertikal tengah */
      justify-content:center;                   /* horizontal tengah */
      color:var(--brown);                       /* warna ikon coklat */
      text-decoration:none;                     /* tanpa underline */
      background:transparent;                   /* latar transparan */
    }

    /* notif dot */
    #btnBell{                                   /* wrapper bell notif */
      position:relative;                        /* posisi untuk dot */
    }

    #badgeNotif.notif-dot{                      /* titik notif pada bell */
      position:absolute;                        /* absolute di dalam bell */
      top:3px;                                  /* posisi dari atas */
      right:5px;                                /* posisi dari kanan */
      width:8px;                                /* lebar dot */
      height:8px;                               /* tinggi dot */
      background:#4b3f36;                       /* warna coklat gelap */
      border-radius:50%;                        /* bentuk bulat */
      display:inline-block;                     /* tampil inline-block */
      box-shadow:0 0 0 1.5px #fff;             /* border putih tipis */
    }

    #badgeNotif.d-none{                         /* saat dot disembunyikan */
      display:none !important;                  /* paksa tidak tampil */
    }

    @media (max-width: 600px){                  /* aturan khusus layar kecil */
      #badgeNotif.notif-dot{                    /* titik notif di mobile */
        width:10px;                             /* dot sedikit lebih besar */
        height:10px;                            /* tinggi dot lebih besar */
        top:4px;                                /* geser sedikit ke bawah */
        right:3px;                              /* geser sedikit ke kiri */
      }
    }

    .avatar{                                    /* avatar user di topbar */
      width:44px;                               /* lebar avatar */
      height:44px;                              /* tinggi avatar */
      border-radius:50%;                        /* bentuk lingkaran */
      background:var(--gold);                   /* latar emas */
      display:grid;                             /* gunakan grid */
      place-items:center;                       /* isi di tengah */
      font-weight:800;                          /* teks tebal */
      color:#111;                               /* warna teks hitam */
      border:2px solid #fff;                    /* border putih */
      background-size:cover;                    /* gambar cover penuh */
      background-position:center;               /* gambar di tengah */
    }

    /* backdrop mobile */
    .backdrop-mobile{                           /* backdrop default */
      display:none;                             /* sembunyikan */
    }

    .backdrop-mobile.active{                    /* saat backdrop aktif */
      display:block;                            /* tampilkan */
      position:fixed;                           /* fixed seluruh layar */
      inset:0;                                  /* isi full layar */
      background:rgba(0,0,0,.35);               /* overlay hitam transparan */
      z-index:1040;                             /* di bawah sidebar (1050) */
    }

    /* ===== TABLE CARD ===== */
    .cardx{                                     /* wrapper card tabel stok */
      background:#fff;                          /* latar putih */
      border:1px solid #f7d78d;                 /* border kuning lembut */
      border-radius:var(--radius);              /* radius default */
      padding:16px;                             /* padding dalam card */
    }

    .table{                                     /* tabel stok menu */
      width:100%;                               /* lebar penuh card */
      border-collapse:separate;                 /* biar padding enak */
      border-spacing:0;                         /* hilangkan celah sel */
    }

    .table thead th{                            /* header tabel */
      background:#fffdf0;                       /* latar kuning sangat muda */
      border-bottom:0;                          /* tanpa border bawah */
      text-align:center;                        /* judul kolom rata tengah */
      vertical-align:middle;                    /* tengah secara vertikal */
      padding-top:12px;                         /* padding atas */
      padding-bottom:12px;                      /* padding bawah */
    }

    .table tbody td{                            /* sel isi tabel */
      text-align:center;                        /* isi rata tengah */
      vertical-align:middle;                    /* tengah vertikal */
      white-space:nowrap;                       /* cegah pecah baris aneh */
      padding-top:12px;                         /* padding atas */
      padding-bottom:12px;                      /* padding bawah */
    }

    .table th:nth-child(1),
    .table td:nth-child(1){                     /* kolom ID */
      width:70px;                               /* lebar cukup lega */
    }

    .table th:nth-child(2),
    .table td:nth-child(2){                     /* kolom Gambar */
      width:90px;                               /* lebar untuk thumb */
    }

    .table th:nth-child(3),
    .table td:nth-child(3){                     /* kolom Nama */
      min-width:180px;                          /* agak lebar untuk nama */
    }

    .table th:nth-child(4),
    .table td:nth-child(4){                     /* kolom Kategori */
      min-width:120px;                          /* lebar kategori */
    }

    .table th:nth-child(5),
    .table td:nth-child(5){                     /* kolom Harga */
      min-width:130px;                          /* lebar harga */
    }

    .table th:nth-child(6),
    .table td:nth-child(6){                     /* kolom Status */
      width:120px;                              /* lebar untuk badge */
    }

    .table th:nth-child(7),
    .table td:nth-child(7){                     /* kolom Aksi */
      width:90px;                               /* lebar tombol */
    }

    .thumb{                                     /* thumbnail gambar menu */
      width:48px;                               /* lebar gambar */
      height:48px;                              /* tinggi gambar */
      object-fit:cover;                         /* crop proporsional */
      border:1px solid #e5e7eb;                 /* border abu tipis */
      border-radius:12px;                       /* sudut membulat */
      background:#fff;                          /* latar putih */
    }

    @media (min-width:992px){                   /* aturan desktop (lg) */
      .content{                                 /* konten di layar besar */
        padding-left:28px !important;           /* padding kiri lebih lega */
        padding-right:28px !important;          /* padding kanan lebih lega */
        padding-top:20px;                       /* padding atas */
        padding-bottom:60px;                    /* padding bawah lebih besar */
      }

      .search-box{                              /* batasi lebar search */
        max-width:1100px !important;            /* mengikuti dashboard */
      }
    }

    @media (min-width:1200px){                  /* aturan layar extra besar */
      .content{                                 /* konten di >1200px */
        padding-left:26px !important;           /* padding kiri sedikit rapat */
        padding-right:26px !important;          /* padding kanan sedikit rapat */
      }
    }
</style>


<!-- Tutup bagian head -->
</head>

<!-- Mulai body halaman -->
<body>

<!-- backdrop overlay untuk mobile -->
<div id="backdrop" class="backdrop-mobile"></div>

<!-- ===== SIDEBAR ===== -->
<!-- Sidebar navigasi utama karyawan -->
<aside class="sidebar" id="sideNav">
  <!-- Header sidebar: tombol toggle & close -->
  <div class="sidebar-head">
    <!-- Tombol toggle di dalam sidebar -->
    <button class="sidebar-inner-toggle" id="toggleSidebarInside" aria-label="Tutup menu"></button>
    <!-- Tombol close sidebar (ikon X) -->
    <button class="sidebar-close-btn" id="closeSidebar" aria-label="Tutup menu">
      <i class="bi bi-x-lg"></i>
    </button>
  </div>

  <!-- Navigasi link di dalam sidebar -->
  <nav class="nav flex-column gap-2" id="sidebar-nav">
    <!-- Link ke dashboard karyawan -->
    <a class="nav-link" href="<?= BASE_URL ?>/public/karyawan/index.php"><i class="bi bi-house-door"></i> Dashboard</a>
    <!-- Link ke halaman orders -->
    <a class="nav-link" href="<?= BASE_URL ?>/public/karyawan/orders.php"><i class="bi bi-receipt"></i> Orders</a>
    <!-- Link ke halaman stock menu -->
    <a class="nav-link" href="<?= BASE_URL ?>/public/karyawan/stock.php"><i class="bi bi-box-seam"></i> Menu Stock</a>
    <!-- Link ke halaman pengaturan akun -->
    <a class="nav-link" href="<?= BASE_URL ?>/public/karyawan/settings.php"><i class="bi bi-gear"></i> Settings</a>
    <!-- Garis pemisah menu -->
    <hr>
    <!-- Link ke help center (placeholder) -->
    <a class="nav-link" href="#"><i class="bi bi-question-circle"></i> Help Center</a>
    <!-- Link logout karyawan -->
    <a class="nav-link" href="<?= BASE_URL ?>/backend/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
  </nav>
</aside>

<!-- ===== CONTENT ===== -->
<!-- Konten utama halaman -->
<main class="content">

  <!-- TOPBAR -->
  <!-- Bar atas: tombol menu, search, ikon notif & profil -->
  <div class="topbar">
    <!-- Tombol buka sidebar (ikon hamburger) -->
    <button class="btn-menu" id="openSidebar" aria-label="Buka menu">
      <!-- Ikon hamburger 3 garis -->
      <div class="hamb-icon">
        <span></span><span></span><span></span>
      </div>
    </button>

    <!-- Kotak search stok menu -->
    <div class="search-box">
      <!-- Input pencarian stok -->
      <input
        class="search-input"
        id="searchInput"
        placeholder="Search..."
        value="<?= htmlspecialchars($q, ENT_QUOTES) ?>"
        autocomplete="off"
      />
      <!-- Ikon search di sisi kanan input -->
      <i class="bi bi-search search-icon" id="searchIcon"></i>
    </div>

    <!-- Aksi di sisi kanan topbar (notif + profil) -->
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
        <span id="badgeNotif" class="notif-dot d-none"></span>
      </a>

      <!-- Profil (ikon seperti customer) -->
      <a
        href="<?= BASE_URL ?>/public/karyawan/settings.php"
        class="icon-btn text-decoration-none"
        aria-label="Akun"
      >
        <!-- Ikon akun lingkaran -->
        <span
          class="iconify"
          data-icon="mdi:account-circle-outline"
          data-width="28"
          data-height="28"
        ></span>
      </a>
    </div>
  </div>

  <!-- Judul halaman stok menu -->
  <h2 class="fw-bold mb-3">Stok Menu</h2>

  <!-- Alert pesan hasil update stok (jika ada) -->
  <?php if ($msg): ?>
    <div class="alert alert-warning py-2"><?= h($msg) ?></div>
  <?php endif; ?>

  <!-- TABEL -->
  <!-- Card pembungkus tabel stok -->
  <div class="cardx">
    <!-- Wrapper agar tabel responsif (scroll horizontal) -->
    <div class="table-responsive">
      <!-- Tabel data stok menu -->
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th>ID</th>
            <th>Gambar</th>
            <th>Nama</th>
            <th>Kategori</th>
            <th>Harga</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
        <!-- Jika tidak ada data menu -->
        <?php if (!$menus): ?>
          <tr>
            <td colspan="7" class="text-center text-muted py-4">Tidak ada data.</td>
          </tr>
        <!-- Jika ada data, looping tiap menu -->
        <?php else: foreach ($menus as $m): ?>
          <tr>
            <!-- Kolom ID menu -->
            <td><?= (int)$m['id'] ?></td>

            <!-- Kolom gambar menu -->
            <td>
              <?php if (!empty($m['image'])): ?>
                <img
                  class="thumb"
                  src="<?= h(BASE_URL . '/public/' . ltrim($m['image'],'/')) ?>"
                  alt=""
                >
              <?php endif; ?>
            </td>

            <!-- Kolom nama menu -->
            <td><?= h($m['name']) ?></td>

            <!-- Kolom kategori menu -->
            <td><?= h($m['category']) ?></td>

            <!-- Kolom harga menu -->
            <td><?= rupiah($m['price']) ?></td>

            <!-- Kolom status stok (badge Ready / Sold Out) -->
            <td>
              <?= ($m['stock_status'] === 'Ready')
                ? '<span class="badge text-bg-success">Ready</span>'
                : '<span class="badge text-bg-danger">Sold Out</span>' ?>
            </td>

            <!-- Kolom aksi ubah status stok -->
            <td>
              <!-- Form kecil untuk toggle status stok -->
              <form class="d-inline" method="post">
                <!-- Hidden: id menu -->
                <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                <!-- Hidden: nilai status baru (kebalikan dari sekarang) -->
                <input
                  type="hidden"
                  name="stock_status"
                  value="<?= $m['stock_status']==='Ready' ? 'Sold Out' : 'Ready' ?>"
                >
                <!-- Jika sekarang Ready, tombol untuk set Sold Out -->
                <?php if ($m['stock_status']==='Ready'): ?>
                  <button class="btn btn-sm btn-outline-danger" type="submit">
                    <i class="bi bi-x-circle me-1"></i>
                  </button>
                <!-- Jika sekarang Sold Out, tombol untuk set Ready -->
                <?php else: ?>
                  <button class="btn btn-sm btn-outline-success" type="submit">
                    <i class="bi bi-check2-circle me-1"></i> 
                <?php endif; ?>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>



<!-- Load JS Bootstrap -->
<script
  src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

<script>
  // =========================================================
  // Sidebar Toggle Handler (buka / tutup sidebar & backdrop)
  // =========================================================

  // ambil elemen sidebar
  const sideNav = document.getElementById('sideNav');

  // ambil elemen backdrop
  const backdrop = document.getElementById('backdrop');

  // tombol buka sidebar (ikon menu kiri atas)
  document
    .getElementById('openSidebar')
    ?.addEventListener('click', () => {
      sideNav.classList.add('show');
      backdrop.classList.add('active');
    });

  // tombol close di dalam sidebar (ikon X)
  document
    .getElementById('closeSidebar')
    ?.addEventListener('click', () => {
      sideNav.classList.remove('show');
      backdrop.classList.remove('active');
    });

  // tombol kecil toggle di dalam sidebar
  document
    .getElementById('toggleSidebarInside')
    ?.addEventListener('click', () => {
      sideNav.classList.remove('show');
      backdrop.classList.remove('active');
    });

  // klik backdrop menutup sidebar
  backdrop.addEventListener('click', () => {
    sideNav.classList.remove('show');
    backdrop.classList.remove('active');
  });


  // =========================================================
  // Search Handler (submit pencarian melalui icon & Enter)
  // =========================================================

  // input pencarian stok menu
  const searchInput = document.getElementById('searchInput');

  // ikon search
  const searchIcon = document.getElementById('searchIcon');

  // fungsi eksekusi search
  function goSearch() {
    const q = searchInput.value.trim();
    const url = new URL(window.location.href);

    // update parameter URL
    if (q) {
      url.searchParams.set('q', q);
    } else {
      url.searchParams.delete('q');
    }

    // redirect
    window.location.href = url.toString();
  }

  // klik ikon search
  searchIcon.addEventListener('click', goSearch);

  // tekan Enter memicu search
  searchInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      goSearch();
    }
  });


  // =========================================================
  // Notifikasi: Badge Unread (Realtime 30 detik sekali)
  // =========================================================

  // fungsi refresh badge notif
  async function refreshKaryawanNotifBadge() {
    const badge = document.getElementById('badgeNotif');

    if (!badge) return;

    try {
      // request jumlah notif belum dibaca
      const res = await fetch(
        "<?= BASE_URL ?>/backend/api/notifications.php?action=unread_count",
        { credentials: "same-origin" }
      );

      if (!res.ok) return;

      const data = await res.json();
      const count = data.count ?? 0;

      // tampilkan atau sembunyikan badge
      if (count > 0) {
        badge.classList.remove('d-none');
      } else {
        badge.classList.add('d-none');
      }

    } catch (err) {
      // optional: bisa tambahkan logging bila perlu
    }
  }

  // panggil pertama kali
  refreshKaryawanNotifBadge();

  // interval refresh setiap 30 detik
  setInterval(refreshKaryawanNotifBadge, 30000);
</script>

</body>
</html>
