<?php
// Tagline halaman: Panel admin untuk memantau & mengelola pesanan Caffora secara realtime.

// public/admin/orders.php
declare(strict_types=1); // Aktifkan strict typing di PHP untuk mencegah tipe data sembarangan.
session_start(); // Mulai atau lanjutkan sesi PHP.

// Sertakan guard autentikasi untuk cek login + role.
require_once __DIR__ . '/../../backend/auth_guard.php';
// Wajib login sebagai admin saja untuk bisa akses halaman ini.
require_login(['admin']); // ADMIN SAJA
// Sertakan file konfigurasi global (BASE_URL, koneksi DB, dll).
require_once __DIR__ . '/../../backend/config.php';

// Ambil nama admin dari session, fallback ke 'Admin' jika tidak ada.
$adminName = $_SESSION['user_name'] ?? 'Admin';
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8"> <!-- Set encoding karakter ke UTF-8 -->
  <title>Orders — Admin Panel</title> <!-- Judul tab browser -->
  <meta name="viewport" content="width=device-width, initial-scale=1"> <!-- Responsive di mobile -->

  <!-- CSS Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <!-- Iconify untuk ikon tambahan (mdi, dsb) -->
  <script src="https://code.iconify.design/2/2.2.1/iconify.min.js"></script>

  <style>
    :root{
      --gold:#ffd54f;        /* Warna emas utama */
      --gold-soft:#f4d67a;   /* Warna emas lembut, untuk highlight */
      --brown:#4B3F36;       /* Warna coklat khas brand */
      --ink:#111827;         /* Warna teks utama (gelap) */
      --radius:18px;         /* Radius sudut default kartu */
      --sidebar-w:320px;     /* Lebar sidebar dalam piksel */
    }

    body{
      background:#FAFAFA;    /* Warna latar belakang halaman */
      color:var(--ink);      /* Warna teks default */
      font-family:Inter,system-ui,Segoe UI,Roboto,Arial; /* Font stack */
    }

    /* Sidebar */
    .sidebar{
      position:fixed;                         /* Sidebar fixed di layar */
      left:-320px;                            /* Awal posisi di luar layar kiri (disembunyikan) */
      top:0;
      bottom:0;
      width:var(--sidebar-w);                 /* Lebar sidebar pakai variabel root */
      background:#fff;                        /* Latar putih */
      border-right:1px solid rgba(0,0,0,.05); /* Garis tipis di sebelah kanan */
      transition:left .25s ease;              /* Animasi buka/tutup via perubahan left */
      z-index:1050;                           /* Di atas konten utama */
      padding:16px 18px;                      /* Padding dalam sidebar */
      overflow-y:auto;                        /* Scroll vertikal jika konten tinggi */
    }
    .sidebar.show{ left:0; }                  /* Saat diberi class show, sidebar masuk ke layar */

    .sidebar-head{
      display:flex;                           /* Flex container untuk header sidebar */
      align-items:center;                     /* Vertikal tengah */
      justify-content:space-between;          /* Jarak kiri-kanan maksimal */
      gap:10px;                               /* Jarak antar elemen */
      margin-bottom:10px;                     /* Spasi bawah header */
    }
    .sidebar-inner-toggle,
    .sidebar-close-btn{
      background:transparent;                 /* Tombol tanpa background */
      border:0;                               /* Hilangkan border */
      width:40px;                             /* Lebar tombol */
      height:36px;                            /* Tinggi tombol */
      display:grid;                           /* Gunakan grid untuk center ikon */
      place-items:center;                     /* Posisikan isi di tengah */
    }
    .hamb-icon{
      width:24px;                             /* Lebar ikon hamburger */
      height:20px;                            /* Tinggi area ikon */
      display:flex;                           /* Gunakan flex untuk susun garis */
      flex-direction:column;                  /* Susun garis vertikal */
      justify-content:space-between;          /* Sebar garis ke atas & bawah */
      gap:4px;                                /* Jarak antar garis */
    }
    .hamb-icon span{
      height:2px;                             /* Ketebalan garis */
      background:var(--brown);                /* Warna garis */
      border-radius:99px;                     /* Ujung garis bulat */
    }

    .sidebar .nav-link{
      display:flex;                           /* Layout horizontal ikon + teks */
      align-items:center;                     /* Tengah vertikal */
      gap:12px;                               /* Jarak ikon dan teks */
      padding:12px 14px;                      /* Padding dalam tiap menu */
      border-radius:16px;                     /* Sudut membulat */
      font-weight:600;                        /* Teks menu agak tebal */
      color:#111;                             /* Warna teks menu */
      text-decoration:none;                   /* Hilangkan garis bawah link */
      background:transparent;                 /* Tanpa background default */
      user-select:none;                       /* Teks menu tidak bisa diseleksi */
    }
    .sidebar .nav-link:hover{
      background:rgba(255,213,79,0.25);       /* Warna latar saat hover (emas soft transparan) */
    }
    .sidebar hr{
      border-color:rgba(0,0,0,.05);           /* Warna garis pembatas di sidebar */
      opacity:1;                              /* Opasitas penuh */
    }

    .backdrop-mobile{ display:none; }         /* Backdrop default disembunyikan */
    .backdrop-mobile.active{
      display:block;                          /* Tampilkan backdrop saat active */
      position:fixed;                         /* Tetap menutupi viewport */
      inset:0;                                /* Top,right,bottom,left = 0 full layar */
      background:rgba(0,0,0,.35);            /* Overlay gelap transparan */
      z-index:1040;                           /* Tepat di bawah sidebar */
    }

    .content{
      margin-left:0;                          /* Konten tidak digeser (mobile) */
      padding:16px 14px 40px;                 /* Padding dalam area konten */
    }

    .topbar{
      display:flex;                           /* Bar atas pakai flex */
      align-items:center;                     /* Tengah vertikal */
      gap:12px;                               /* Jarak antar elemen topbar */
      margin-bottom:16px;                     /* Spasi bawah topbar */
    }

    .btn-menu{
      background:transparent;                 /* Tombol menu tanpa background */
      border:0;                               /* Tanpa border */
      width:40px;                             /* Lebar tombol */
      height:38px;                            /* Tinggi tombol */
      display:grid;                           /* Grid untuk center ikon hamburger */
      place-items:center;                     /* Posisi tengah */
    }

    .search-box{
      position:relative;                      /* Untuk posisi ikon search absolute */
      flex:1 1 auto;                          /* Biarkan search box melebar */
      min-width:0;                            /* Min width 0 agar bisa shrink */
    }
    .search-input{
      height:46px;                            /* Tinggi input */
      width:100%;                             /* Lebar penuh container */
      border-radius:9999px;                   /* Membulat seperti pill */
      padding-left:16px;                      /* Padding kiri teks */
      padding-right:44px;                     /* Ruang kanan untuk ikon search */
      border:1px solid #e5e7eb;               /* Border abu-abu muda */
      background:#fff;                        /* Latar putih */
      outline:none;                           /* Hilangkan outline default */
    }
    .search-input:focus{
      border-color:var(--gold-soft);          /* Border jadi emas lembut saat fokus */
    }
    .search-icon{
      position:absolute;                      /* Posisikan ikon di dalam input */
      right:16px;                             /* Jarak dari kanan */
      top:50%;                                /* Di tengah vertical */
      transform:translateY(-50%);             /* Koreksi posisi vertical */
      font-size:1.1rem;                       /* Besar ikon */
      color:var(--brown);                     /* Warna ikon */
      cursor:pointer;                         /* Pointer saat dihover */
    }

    .top-actions{
      display:flex;                           /* Kontainer ikon notifikasi & akun */
      gap:14px;                               /* Jarak antar ikon */
    }
    .icon-btn{
      width:38px;                             /* Ukuran tombol ikon */
      height:38px;
      border-radius:999px;                    /* Bentuk bulat */
      display:flex;                           /* Flex untuk penempatan ikon */
      align-items:center;                     /* Tengah vertikal */
      justify-content:center;                 /* Tengah horizontal */
      color:var(--brown);                     /* Warna default ikon */
      text-decoration:none;                   /* Hilangkan garis bawah link */
      position:relative;                      /* Untuk posisi notif-dot di dalamnya */
    }
    .notif-dot{
      position:absolute;                      /* Titik notif mengambang di pojok */
      top:3px;
      right:3px;
      width:8px;                              /* Diameter titik notif */
      height:8px;
      border-radius:999px;                    /* Bentuk bulat */
      background:#4b3f36;                     /* Warna coklat gelap */
      box-shadow:0 0 0 1.5px #fff;            /* Outline putih tipis */
    }
    .d-none{ display:none !important; }       /* Utility class untuk sembunyikan elemen */

    .cardx{
      background:#fff;                        /* Background putih kartu */
      border:1px solid #f7d78d;               /* Border warna emas lembut */
      border-radius:var(--radius);            /* Sudut bulat sesuai variabel */
      padding:18px 22px;                      /* Padding dalam kartu */
    }

    /* ===== Tabel ===== */
    .table{
      margin-bottom:0;                        /* Hilangkan margin bawah default tabel */
    }
    .table thead th,
    .table td{
      vertical-align:middle;                  /* Isi sel vertikal di tengah */
      white-space:nowrap;                     /* Jangan bungkus teks ke baris baru */
      text-align:center;                      /* Teks tengah secara horizontal */
      padding:0.8rem 1.1rem;                  /* Padding sel */
    }
    .table thead th{
      background:#fffbe6;                     /* Background header tabel kuning muda */
      font-weight:600;                        /* Teks header lebih tebal */
    }

    .col-invoice{ min-width:170px; text-align:left; } /* Kolom invoice minimum lebar & rata kiri */
    .col-name   { min-width:120px; text-align:left; } /* Kolom nama minimum lebar & rata kiri */
    .col-total  { min-width:130px; }                  /* Kolom total punya lebar minimum */
    .col-status { min-width:130px; }                  /* Kolom status lebar minimum */
    .col-method { min-width:125px; }                  /* Kolom metode lebar minimum */
    .col-actions{ min-width:260px; }                  /* Kolom aksi agak lebar */

    .col-total{
      font-variant-numeric:tabular-nums;      /* Angka lebih rapi (digit monospaced) */
    }

    /* Aksi: 2 baris (Mulai+Lunas, Batal+Struk) */
    .actions-cell{
      display:flex;                           /* Flex untuk cell aksi */
      flex-direction:column;                  /* Susun baris aksi vertikal */
      align-items:center;                     /* Tengah secara horizontal */
      gap:4px;                                /* Jarak antar baris aksi */
    }
    .actions-row{
      display:flex;                           /* Flex untuk tiap baris tombol aksi */
      flex-wrap:wrap;                         /* Izinkan melipat ke baris berikut jika sempit */
      justify-content:center;                 /* Tengah horizontal */
      gap:6px;                                /* Jarak antar tombol aksi */
    }
    .actions-row .btn{
      border-radius:12px;                     /* Tombol aksi dengan sudut membulat */
      padding:.28rem .9rem;                   /* Padding kecil pada tombol */
      font-size:.8rem;                        /* Ukuran teks tombol lebih kecil */
    }
    .actions-row a.btn{
      display:inline-flex;                    /* Link tombol struk jadi inline-flex */
      align-items:center;                     /* Ikon dan teks rata tengah vertikal */
      gap:4px;                                /* Jarak ikon dan teks */
    }

    @media (min-width:992px){
      .content{
        padding:20px 26px 50px;               /* Padding konten lebih lapang di layar besar */
      }
      .search-box{ max-width:1100px; }        /* Batas maksimal lebar search di desktop */
    }

    /* ===== Modal konfirmasi batal ala Caffora ===== */
    #modalCancel .modal-dialog{
      max-width:420px;                        /* Batasi lebar modal pembatalan */
    }
    #modalCancel .modal-content{
      border-radius:24px;                     /* Sudut modal membulat */
      border:none;                            /* Hilangkan border default */
      box-shadow:0 20px 60px rgba(15,23,42,.35); /* Bayangan dalam untuk modal */
      padding:4px 2px 6px;                    /* Padding luar konten modal */
    }
    #modalCancel .modal-header{
      border-bottom:0;                        /* Tanpa garis bawah header */
      padding:18px 22px 6px;                  /* Padding header modal */
    }
    #modalCancel .modal-title{
      font-weight:700;                        /* Judul modal tebal */
      color:var(--brown);                     /* Warna judul coklat */
    }
    #modalCancel .modal-body{
      padding:6px 22px 18px;                  /* Padding area isi modal */
      font-size:.95rem;                       /* Ukuran teks isi sedikit kecil */
    }
    #modalCancel .modal-footer{
      border-top:0;                           /* Hilangkan garis atas footer modal */
      padding:4px 22px 18px;                  /* Padding footer */
      gap:10px;                               /* Jarak antar tombol footer */
    }
    #modalCancel .btn{
      border-radius:999px;                    /* Tombol modal berbentuk pill */
      font-weight:600;                        /* Teks tombol agak tebal */
      padding:.48rem 1.3rem;                  /* Padding tombol modal */
    }
    #modalCancel .btn-light{
      background:#fff7e0;                     /* Warna tombol batal (tidak) */
      border-color:#fff7e0;                   /* Warna border sama dengan background */
      color:var(--brown);                     /* Warna teks coklat */
    }
    #modalCancel .btn-light:hover{
      background:#ffefc2;                     /* Hover state sedikit lebih gelap */
      border-color:#ffefc2;                   /* Border ikut menyesuaikan */
    }
    #modalCancel .btn-danger{
      background:#ef4444;                     /* Warna tombol konfirmasi batal merah */
      border-color:#ef4444;                   /* Border merah */
    }
    #modalCancel .btn-danger:hover{
      background:#dc2626;                     /* Warna merah lebih gelap saat hover */
      border-color:#dc2626;                   /* Border ikut gelap */
    }
    .modal-backdrop.show{
      background:rgba(15,23,42,.55);         /* Background overlay modal agak gelap */
    }
  </style>
</head>
<body>

<div id="backdrop" class="backdrop-mobile"></div> <!-- Elemen overlay untuk menutup sidebar di mobile -->

<!-- sidebar -->
<aside class="sidebar" id="sideNav">
  <div class="sidebar-head">
    <button class="sidebar-inner-toggle" id="toggleSidebarInside" aria-label="Tutup menu"></button> <!-- Tombol tutup di dalam sidebar -->
    <button class="sidebar-close-btn" id="closeSidebar" aria-label="Tutup menu">
      <i class="bi bi-x-lg"></i> <!-- Ikon X Bootstrap untuk tutup -->
    </button>
  </div>

  <nav class="nav flex-column gap-2" id="sidebar-nav">
    <!-- gunakan URL “clean” -->
    <!-- Link ke dashboard admin -->
    <a class="nav-link" href="<?= BASE_URL ?>/admin"><i class="bi bi-house-door"></i> Dashboard</a>
    <!-- Link ke halaman orders -->
    <a class="nav-link" href="<?= BASE_URL ?>/admin/orders"><i class="bi bi-receipt"></i> Orders</a>
    <!-- Link ke catalog produk -->
    <a class="nav-link" href="<?= BASE_URL ?>/admin/catalog"><i class="bi bi-box-seam"></i> Catalog</a>
    <!-- Link ke manajemen user -->
    <a class="nav-link" href="<?= BASE_URL ?>/admin/users"><i class="bi bi-people"></i> Users</a>
    <!-- Link ke finance -->
    <a class="nav-link" href="<?= BASE_URL ?>/admin/finance"><i class="bi bi-cash-coin"></i> Finance</a>
    <!-- Link ke notifications -->
    <a class="nav-link" href="<?= BASE_URL ?>/admin/notifications"><i class="bi bi-megaphone"></i> Notifications</a>
    <!-- Link ke audit log -->
    <a class="nav-link" href="<?= BASE_URL ?>/admin/audit"><i class="bi bi-shield-check"></i> Audit Log</a>
    <!-- Link ke settings -->
    <a class="nav-link" href="<?= BASE_URL ?>/admin/settings"><i class="bi bi-gear"></i> Settings</a>
    <hr> <!-- Garis pemisah menu utama dan lain-lain -->
    <!-- Link ke help center -->
    <a class="nav-link" href="<?= BASE_URL ?>/admin/help"><i class="bi bi-question-circle"></i> Help Center</a>
    <!-- Link logout -->
    <a class="nav-link" href="<?= BASE_URL ?>/backend/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
  </nav>
</aside>

<!-- Content -->
<main class="content">
  <div class="topbar">
    <!-- Tombol untuk membuka sidebar (mobile) -->
    <button class="btn-menu" id="openSidebar" aria-label="Buka menu">
      <div class="hamb-icon"><span></span><span></span><span></span></div> <!-- Ikon hamburger -->
    </button>

    <div class="search-box">
      <!-- Input pencarian orders -->
      <input class="search-input" id="searchInput" placeholder="Search..." autocomplete="off" />
      <!-- Ikon pencarian yang juga bisa dipakai klik -->
      <i class="bi bi-search search-icon" id="searchIcon"></i>
      <!-- Container suggestion (belum dipakai di script) -->
      <div class="search-suggest" id="searchSuggest"></div>
    </div>

    <div class="top-actions">
      <!-- Tombol menuju halaman notifikasi admin -->
      <a id="btnBell" class="icon-btn text-decoration-none" href="<?= BASE_URL ?>/admin/notifications" aria-label="Notifikasi">
        <span class="iconify" data-icon="mdi:bell-outline" data-width="24" data-height="24"></span> <!-- Ikon bell dari Iconify -->
        <span id="badgeNotif" class="notif-dot d-none"></span> <!-- Titik notif (tampil kalau ada unread) -->
      </a>
      <!-- Tombol menuju halaman pengaturan/akun admin -->
      <a class="icon-btn text-decoration-none" href="<?= BASE_URL ?>/admin/settings" aria-label="Akun">
        <span class="iconify" data-icon="mdi:account-circle-outline" data-width="28" data-height="28"></span> <!-- Ikon akun -->
      </a>
    </div>
  </div>

  <h2 class="fw-bold mb-3">Daftar Pesanan</h2> <!-- Judul utama halaman orders -->
  <!-- Tagline UI: kalimat singkat menjelaskan fungsi halaman -->
  <p class="text-muted mb-3">Kelola semua pesanan Caffora secara cepat, rapi, dan realtime langsung dari satu panel.</p>

  <div class="cardx">
    <div class="table-responsive">
      <!-- Tabel utama daftar pesanan -->
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th class="col-invoice">Invoice</th>       <!-- Kolom nomor invoice -->
            <th class="col-name">Nama</th>             <!-- Kolom nama pelanggan -->
            <th class="col-total">Total</th>           <!-- Kolom total pembayaran -->
            <th class="col-status">Pesanan</th>        <!-- Kolom status pesanan -->
            <th class="col-status">Pembayaran</th>     <!-- Kolom status pembayaran -->
            <th class="col-method">Metode</th>         <!-- Kolom metode pembayaran -->
            <th class="col-actions">Aksi</th>          <!-- Kolom tombol aksi -->
          </tr>
        </thead>
        <tbody id="rows">
          <!-- Baris default saat data belum dimuat -->
          <tr><td colspan="7" class="text-center text-muted py-4">Memuat...</td></tr>
        </tbody>
      </table>
    </div>
  </div>

</main>

<!-- Modal konfirmasi batal + alasan -->
<div class="modal fade" id="modalCancel" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <!-- Branding judul modal -->
        <h6 class="modal-title">Caffora</h6>
        <!-- Tombol X untuk menutup modal -->
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <!-- Input hidden untuk menyimpan id order yang akan dibatalkan -->
        <input type="hidden" id="cancel_order_id">
        <p class="mb-1">Batalkan pesanan ini?</p> <!-- Pertanyaan konfirmasi -->
        <p class="text-muted small mb-2">
          Invoice: <span id="cancel_invoice" class="fw-semibold"></span> <!-- Tempat menampilkan invoice terkait -->
        </p>

        <!-- Tambahan: alasan pembatalan -->
        <label class="form-label mt-2">
          Alasan pembatalan <span class="text-danger">*</span> <!-- Label + tanda wajib -->
        </label>
        <select class="form-select" id="cancel_reason_sel" required>
          <option value="">Pilih alasan…</option>                          <!-- Opsi default kosong -->
          <option>Stok habis / tidak mencukupi</option>                    <!-- Opsi alasan stok -->
          <option>Pelanggan tidak melanjutkan (belum bayar)</option>       <!-- Opsi alasan pelanggan batal -->
          <option>Salah input pesanan</option>                             <!-- Opsi alasan salah input -->
          <option>Menu tidak tersedia hari ini</option>                    <!-- Opsi alasan menu unavailable -->
          <option value="__custom__">Lainnya (tulis manual)</option>       <!-- Opsi alasan custom -->
        </select>

        <div class="mt-2 d-none" id="cancel_reason_custom_wrap">
          <!-- Textarea untuk alasan custom (muncul saat pilih __custom__) -->
          <textarea
            class="form-control"
            id="cancel_reason_custom"
            rows="2"
            placeholder="Tulis alasan singkat"
          ></textarea>
        </div>

        <div class="small text-muted mt-2">
          <!-- Catatan aturan pembatalan -->
          Pembatalan hanya untuk pesanan <strong>belum dibayar</strong>.
          Setelah dibatalkan, pembayaran akan ditandai <em>failed</em>
          dan aksi di baris ini akan dinonaktifkan.
        </div>
      </div>
      <div class="modal-footer">
        <!-- Tombol batal (tidak jadi batalkan) -->
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tidak</button>
        <!-- Tombol konfirmasi pembatalan -->
        <button type="button" class="btn btn-danger" id="btnCancelConfirm">Ya, Batalkan</button>
      </div>
    </div>
  </div>
</div>

 <!-- JS bundle Bootstrap (termasuk Popper) -->
 <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const ORIGIN  = location.origin.replace(/^http:\/\//i, "https://"); // Paksa origin jadi https (jika http diganti https).
const BASE    = "<?= rtrim(BASE_URL, '/') ?>";                      // BASE_URL dari PHP, dipastikan tanpa slash di belakang.

/* ==== HELPER ANTI-XSS (ESCAPE HTML) ==== */
function escHtml(value){
  const str = String(value ?? '');        // Pastikan value jadi string, default kosong jika null/undefined.
  return str
    .replace(/&/g, '&amp;')              // Ganti & dengan entitas HTML &amp;.
    .replace(/</g, '&lt;')               // Ganti < dengan &lt;.
    .replace(/>/g, '&gt;')               // Ganti > dengan &gt;.
    .replace(/"/g, '&quot;')             // Ganti " dengan &quot;.
    .replace(/'/g, '&#39;');             // Ganti ' dengan &#39;.
}

/* Route bersih + fallback ke legacy */
// Endpoint API orders versi "clean route"
const API_ORDERS_CLEAN   = ORIGIN + "/api/orders";
// Endpoint API orders versi legacy (file PHP)
const API_ORDERS_LEGACY  = ORIGIN + "/backend/api/orders.php";
// Endpoint API notifikasi unread count versi clean
const API_NOTIF_CLEAN    = ORIGIN + "/api/notifications?action=unread_count";
// Endpoint API notifikasi versi legacy
const API_NOTIF_LEGACY   = ORIGIN + "/backend/api/notifications.php?action=unread_count";

// Referensi elemen tbody untuk baris orders
const $rows   = document.getElementById('rows');
// Referensi input pencarian
const $search = document.getElementById('searchInput');

// Variabel global untuk elemen-elemen modal cancel
let cancelModal,
    cancelOrderInput,
    cancelInvoiceSpan,
    cancelReasonSel,
    cancelReasonCustomWrap,
    cancelReasonCustom;

/* ===== Sidebar ===== */
// Ambil elemen sidebar
const sideNav  = document.getElementById('sideNav');
// Ambil elemen backdrop overlay
const backdrop = document.getElementById('backdrop');

// Event klik tombol pembuka sidebar
document.getElementById('openSidebar')
  ?.addEventListener('click', () => {
    sideNav.classList.add('show');     // Tambah class show untuk munculkan sidebar
    backdrop.classList.add('active');  // Aktifkan backdrop
  });

// Event klik tombol X (close) di header sidebar
document.getElementById('closeSidebar')
  ?.addEventListener('click', () => {
    sideNav.classList.remove('show');  // Hilangkan class show untuk sembunyikan sidebar
    backdrop.classList.remove('active'); // Nonaktifkan backdrop
  });

// Event klik tombol toggle di dalam sidebar
document.getElementById('toggleSidebarInside')
  ?.addEventListener('click', () => {
    sideNav.classList.remove('show');   // Tutup sidebar
    backdrop.classList.remove('active'); // Nonaktifkan backdrop
  });

// Klik pada backdrop juga menutup sidebar
backdrop?.addEventListener('click', () => {
  sideNav.classList.remove('show');     // Tutup sidebar
  backdrop.classList.remove('active');  // Hilangkan backdrop
});

/* ===== Notif badge ===== */
async function refreshAdminNotifBadge(){
  const badge = document.getElementById('badgeNotif'); // Ambil elemen titik notif
  if (!badge) return;                                  // Jika tidak ada, hentikan fungsi

  let url = API_NOTIF_CLEAN;                          // Mulai dengan endpoint clean
  try {
    // Panggil API notif unread via endpoint clean
    let res = await fetch(url, { credentials:"same-origin", cache:"no-store" });
    if (!res.ok) {                                    // Jika gagal:
      url = API_NOTIF_LEGACY;                         // Ganti ke endpoint legacy
      res = await fetch(url, { credentials:"same-origin", cache:"no-store" });
    }
    if (!res.ok) return;                              // Jika tetap gagal, keluar tanpa ubah tampilan

    const data  = await res.json();                   // Parse JSON respons
    const count = Number(data?.count ?? 0);           // Ambil nilai count unread
    // Tampilkan atau sembunyikan titik notif sesuai jumlah unread
    badge.classList.toggle('d-none', !(count > 0));
  } catch(e) {
    // silent: error jaringan diabaikan supaya UI tidak error
  }
}

/* ===== Helpers ===== */
// Format angka ke Rupiah (tanpa desimal) dengan locale id-ID
const rp = n => 'Rp ' + Number(n||0).toLocaleString('id-ID');

// Generate badge status pesanan
function badgeOrder(os){
  // Mapping status pesanan ke warna badge Bootstrap
  const map = {
    new:'secondary',
    processing:'primary',
    ready:'warning',
    completed:'success',
    cancelled:'dark'
  };
  const safeStatus = escHtml(os || '-'); // Escape teks status
  return `
    <span class="badge text-bg-${map[os]||'secondary'} text-capitalize">
      ${safeStatus}
    </span>`; // Kembalikan HTML badge
}

// Generate badge status pembayaran
function badgePay(ps){
  // Mapping status pembayaran ke warna badge
  const map = {
    pending:'warning',
    paid:'success',
    failed:'danger',
    refunded:'info',
    overdue:'secondary'
  };
  const safeStatus = escHtml(ps || '-'); // Escape teks status
  return `
    <span class="badge text-bg-${map[ps]||'secondary'} text-capitalize">
      ${safeStatus}
    </span>`; // Kembalikan HTML badge
}

/**
 * Tombol progres pesanan:
 * - disabledProgress = true → tombol tidak bisa diklik.
 * - kalau status sudah completed/cancelled → tampil tombol Selesai nonaktif.
 */
function nextButtons(os, disabledProgress){
  // Urutan status pesanan yang valid untuk progres
  const order = ['new','processing','ready','completed'];
  // Cari index status saat ini
  const idx   = order.indexOf(os);

  // Jika status tidak dikenal / sudah completed / cancelled → tampilkan tombol Selesai nonaktif
  if (idx === -1 || os === 'completed' || os === 'cancelled') {
    return '<button class="btn btn-sm btn-outline-secondary" disabled>Selesai</button>';
  }

  // Status berikutnya berdasarkan urutan array
  const next     = order[idx+1];
  // Pemetaan label tombol untuk setiap status target
  const labelMap = { processing:'Mulai', ready:'Siap', completed:'Selesai' };

  // Tambah class disabled jika progress dikunci
  const extraClass   = disabledProgress ? ' disabled' : '';
  // Tambah atribut disabled & aria-disabled bila dikunci
  const disabledAttr = disabledProgress ? ' disabled aria-disabled="true"' : '';

  // Return HTML tombol "next" dengan data-act dan data-val
  return `
    <button
      class="btn btn-sm btn-outline-primary${extraClass}"
      data-act="next"
      data-val="${escHtml(next)}"${disabledAttr}
    >
      ${escHtml(labelMap[next] || '→')}
    </button>`;
}

/* ===== request helper ===== */
async function requestJSON(url, opts){
  const res = await fetch(url, opts);                    // Panggil fetch ke URL tertentu
  const ct  = res.headers.get("content-type") || "";     // Ambil header content-type
  if (!ct.includes("application/json")) {                // Jika bukan JSON:
    throw new Error("non-json:"+res.status);             // Lempar error
  }
  const js = await res.json();                           // Parse body JSON
  return { ok: res.ok, json: js };                       // Kembalikan status ok + data JSON
}

/* ===== Load Orders ===== */
async function loadOrders(q=''){
  try{
    // Buat query string untuk action list
    const params = new URLSearchParams({ action:'list' });
    if (q.trim()) params.set('q', q.trim());             // Jika ada pencarian, tambahkan parameter q

    let url = API_ORDERS_CLEAN + "?" + params.toString(); // Mulai dengan endpoint clean
    let res, data;

    try{
      // Coba request ke endpoint clean
      res  = await requestJSON(url, { credentials:'same-origin', cache:'no-store' });
      data = res.json;
    }catch{
      // Jika gagal, fallback ke endpoint legacy
      url  = API_ORDERS_LEGACY + "?" + params.toString();
      res  = await requestJSON(url, { credentials:'same-origin', cache:'no-store' });
      data = res.json;
    }

    // Jika response tidak ok atau data error dari server
    if (!res.ok || !data || (data.ok === false)) {
      $rows.innerHTML =
        '<tr><td colspan="7" class="text-danger text-center py-4">' +
        escHtml(data?.error || 'Gagal memuat') +
        '</td></tr>';                                    // Tampilkan pesan gagal memuat
      return;
    }

    // Ambil daftar items, pastikan berbentuk array
    const items = Array.isArray(data?.items) ? data.items : [];
    if (!items.length){
      // Jika tidak ada data, tampilkan pesan kosong
      $rows.innerHTML =
        '<tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data.</td></tr>';
      return;
    }

    // Render setiap item ke baris tabel
    $rows.innerHTML = items.map(it => {
      const safeId          = Number(it.id);                        // ID order, di-cast numeric
      const safeInvoice     = escHtml(it.invoice_no || '-');       // Invoice untuk tampilan
      const safeInvoiceAttr = escHtml(it.invoice_no || '');        // Invoice untuk atribut data
      const safeName        = escHtml(it.customer_name || '-');    // Nama customer
      const safeMethod      = escHtml(it.payment_method || '-');   // Metode pembayaran
      const safePayStatus   = escHtml(it.payment_status || '');    // Status pembayaran
      const safeOrderStatus = escHtml(it.order_status || '');      // Status pesanan
      const hrefReceipt     = BASE + '/public/admin/receipt.php?order=' + safeId; // URL struk

      // jika sudah dibatalkan / failed → semua aksi dimatikan
      const disabledAll      = it.order_status === 'cancelled' || it.payment_status === 'failed';
      const disabledStrk     = it.payment_status !== 'paid' || disabledAll;           // Struk hanya saat paid & belum disabledAll

      // Batal & Lunas hanya boleh saat pembayaran masih pending
      const disabledBatal    = it.payment_status !== 'pending' || disabledAll;        // Tombol batal dikunci selain pending
      const disabledLunas    = it.payment_status !== 'pending' || disabledAll;        // Tombol lunas dikunci selain pending

      // Progres (Mulai/Siap/Selesai) dikunci selama pembayaran masih pending
      const disabledProgress = it.payment_status === 'pending' || disabledAll;        // Progres dilock jika pending atau sudah nonaktif

      // Template baris tabel untuk satu order
      return `
        <tr
          data-id="${safeId}"
          data-pay="${safePayStatus}"
          data-ost="${safeOrderStatus}"
          data-invoice="${safeInvoiceAttr}"
        >
          <td class="col-invoice fw-semibold">${safeInvoice}</td>
          <td class="col-name">${safeName}</td>
          <td class="col-total">${rp(it.total)}</td>
          <td class="col-status">${badgeOrder(it.order_status)}</td>
          <td class="col-status">${badgePay(it.payment_status)}</td>
          <td class="col-method">${safeMethod}</td>
          <td class="col-actions">
            <div class="actions-cell">
              <div class="actions-row">
                ${nextButtons(it.order_status, disabledProgress)}
                <button
                  class="btn btn-sm btn-outline-success ${disabledLunas ? 'disabled' : ''}"
                  data-act="pay"
                  data-val="paid"
                  ${disabledLunas ? 'aria-disabled="true"' : ''}
                >
                  Lunas
                </button>
              </div>
              <div class="actions-row">
                <button
                  class="btn btn-sm btn-outline-danger ${disabledBatal ? 'disabled' : ''}"
                  data-act="cancel"
                  ${disabledBatal ? 'aria-disabled="true"' : ''}
                >
                  Batal
                </button>
                <a
                  class="btn btn-sm btn-outline-dark ${disabledStrk ? 'disabled' : ''}"
                  ${disabledStrk ? 'aria-disabled="true"' : ''}
                  href="${disabledStrk ? '#' : hrefReceipt}"
                >
                  <i class="bi bi-printer"></i>
                  Struk
                </a>
              </div>
            </div>
          </td>
        </tr>`;
    }).join('');                                         // Gabungkan semua baris menjadi satu string HTML

    bindRowActions();                                    // Setelah render, pasang event listener untuk tombol aksi
  }catch(e){
    console.error(e);                                    // Log error ke console
    $rows.innerHTML =
      '<tr><td colspan="7" class="text-danger text-center py-4">Gagal memuat.</td></tr>'; // Pesan general error
  }
}

/* ===== Kirim update ke API ===== */
async function sendUpdate(payload){
  const headers = { 'Content-Type':'application/json' }; // Header untuk body JSON
  let ok = false;                                        // Flag keberhasilan

  try{
    // Coba kirim update ke endpoint clean
    let r = await fetch(
      API_ORDERS_CLEAN + '?action=update',
      {
        method:'POST',
        headers,
        credentials:'same-origin',
        body:JSON.stringify(payload)                     // Body berupa JSON payload
      }
    );
    ok = r.ok;
    if (!ok) throw 0;                                    // Jika gagal, lempar supaya masuk ke catch
  }catch{
    // Fallback ke endpoint legacy jika clean gagal
    let r = await fetch(
      API_ORDERS_LEGACY + '?action=update',
      {
        method:'POST',
        headers,
        credentials:'same-origin',
        body:JSON.stringify(payload)                     // Body JSON sama
      }
    );
    ok = r.ok;
  }
  return ok;                                             // Kembalikan status sukses/gagal
}

/* ===== Aksi pada tombol di tabel ===== */
function bindRowActions(){
  // Seleksi semua elemen yang punya atribut data-act (tombol aksi)
  $rows.querySelectorAll('[data-act]').forEach(btn => {
    // Tambahkan event klik ke masing-masing tombol
    btn.addEventListener('click', async ev => {
      ev.preventDefault();                               // Cegah default (misal submit / link)

      // kalau tombol sudah disabled secara visual, jangan lakukan apa-apa
      if (btn.classList.contains('disabled')) return;

      const tr  = btn.closest('tr');                     // Cari baris <tr> terdekat
      const id  = tr?.dataset.id;                        // Ambil id order dari data-id
      if (!id) return;                                   // Jika tidak ada id, hentikan

      const act     = btn.dataset.act;                   // Jenis aksi (next/pay/cancel)
      const payStat = tr.dataset.pay || '';              // Status pembayaran saat ini
      const payload = { id: Number(id) };                // Payload dasar berisi id order

      if (act === 'next'){
        // Progres hanya boleh kalau tidak pending (sudah dibayar)
        if (payStat === 'pending') return;               // Jika pending, jangan proses
        payload.order_status = btn.dataset.val;          // Set order_status baru sesuai data-val
        const ok = await sendUpdate(payload);            // Kirim update ke server
        if (ok) loadOrders($search.value);               // Reload daftar dengan filter pencarian saat ini
        return;
      }

      if (act === 'pay'){
        // Lunas hanya boleh dari status pembayaran pending
        if (payStat !== 'pending') return;               // Jika bukan pending, batal
        payload.payment_status = btn.dataset.val;        // Set payment_status baru (paid)
        const ok = await sendUpdate(payload);            // Kirim update
        if (ok) loadOrders($search.value);               // Reload daftar orders
        return;
      }

      if (act === 'cancel'){
        // Batal hanya boleh dari pembayaran pending
        if (payStat !== 'pending') return;               // Selain pending, tidak boleh batalkan

        // Simpan ID order yang akan dibatalkan ke input hidden
        cancelOrderInput.value        = id;
        // Tampilkan invoice yang sedang diproses di modal
        cancelInvoiceSpan.textContent = tr.dataset.invoice || '';
        // Reset pilihan dan input alasan
        cancelReasonSel.value         = '';
        cancelReasonCustom.value      = '';
        // Sembunyikan textarea alasan custom saat awal
        cancelReasonCustomWrap.classList.add('d-none');
        // Tampilkan modal pembatalan
        cancelModal.show();
      }
    });
  });
}

/* ===== Konfirmasi batal dari modal (kirim ke orders_cancel.php) ===== */
async function handleConfirmCancel(e){
  e?.preventDefault?.();                            // Cegah default event bila ada

  const id  = cancelOrderInput.value;               // Ambil ID order dari input hidden
  const opt = cancelReasonSel.value;                // Ambil opsi alasan dipilih
  const cus = cancelReasonCustom.value.trim();      // Ambil isi alasan custom
  // Tentukan alasan final: jika pilih custom, pakai teks custom (atau fallback "Alasan lain")
  const reason = (opt === '__custom__') ? (cus || 'Alasan lain') : opt;

  if (!id || !reason){
    alert('Lengkapi alasan pembatalan.');           // Validasi minimal id & alasan tidak boleh kosong
    return;
  }

  const btn = document.getElementById('btnCancelConfirm'); // Referensi tombol konfirmasi di modal
  btn.disabled   = true;                            // Disable tombol agar tidak double submit
  btn.textContent = 'Memproses...';                 // Ganti label tombol saat proses

  try{
    // Kirim request POST ke orders_cancel.php
    const res = await fetch(BASE + '/backend/api/orders_cancel.php', {
      method:'POST',
      credentials:'same-origin',                    // Kirim cookie sesi
      headers:{
        'Accept':'application/json',                // Expect JSON response
        'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8' // Body form-encoded
      },
      body: new URLSearchParams({ order_id:id, reason }) // Kirim order_id + reason
    });

    const raw = await res.text();                   // Ambil raw text respons
    let js; try{ js = JSON.parse(raw); }catch{ js = null; } // Coba parse JSON, jika gagal js = null

    if (!res.ok || !js || js.ok !== true){
      console.error('orders_cancel.php response:', res.status, raw); // Log error ke console
      alert((js && js.error) ? js.error : 'Gagal membatalkan pesanan.'); // Tampilkan pesan gagal
      return;
    }

    cancelModal.hide();                             // Tutup modal jika berhasil
    await loadOrders($search.value);                // Reload data orders dengan filter sekarang
  }catch(err){
    console.error(err);                             // Log error jaringan
    alert('Terjadi masalah jaringan.');             // Alert masalah koneksi
  }finally{
    btn.disabled   = false;                         // Aktifkan kembali tombol
    btn.textContent = 'Ya, Batalkan';               // Kembalikan label tombol
  }
}

/* ===== Search + init ===== */
// Event input pada kolom search → reload data dengan query baru
$search.addEventListener('input', () => loadOrders($search.value));
// Klik ikon search juga memicu reload data
document
  .getElementById('searchIcon')
  .addEventListener('click', () => loadOrders($search.value));

// Event saat DOM sudah siap
document.addEventListener('DOMContentLoaded', () => {
  // Inisialisasi instance Bootstrap Modal untuk modalCancel
  cancelModal             = new bootstrap.Modal(document.getElementById('modalCancel'));
  // Ambil referensi elemen-elemen dalam modal
  cancelOrderInput        = document.getElementById('cancel_order_id');
  cancelInvoiceSpan       = document.getElementById('cancel_invoice');
  cancelReasonSel         = document.getElementById('cancel_reason_sel');
  cancelReasonCustomWrap  = document.getElementById('cancel_reason_custom_wrap');
  cancelReasonCustom      = document.getElementById('cancel_reason_custom');

  // Jika elemen select alasan ada
  if (cancelReasonSel){
    // Saat opsi alasan berubah
    cancelReasonSel.addEventListener('change', () => {
      // Tampilkan / sembunyikan textarea custom tergantung nilai select
      cancelReasonCustomWrap.classList.toggle(
        'd-none',
        cancelReasonSel.value !== '__custom__'
      );
    });
  }

  // Event klik tombol "Ya, Batalkan" pada modal
  document.getElementById('btnCancelConfirm')
    .addEventListener('click', handleConfirmCancel);

  // Load awal daftar orders tanpa filter
  loadOrders();
  // Refresh badge notifikasi admin
  refreshAdminNotifBadge();
});
</script>


</body>
</html>
