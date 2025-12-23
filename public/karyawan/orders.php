<?php
// Tagline: Halaman "Daftar Pesanan" untuk karyawan — tampilkan list order, update status/pembayaran, batalkan pesanan, plus badge notifikasi real-time.

// public/karyawan/orders.php                                // Lokasi file untuk halaman orders karyawan
declare(strict_types=1);                                     // Aktifkan strict types di PHP
session_start();                                             // Mulai/melanjutkan sesi PHP

require_once __DIR__ . '/../../backend/auth_guard.php';      // Include helper proteksi autentikasi
require_login(['karyawan','admin']);                         // Wajib login sebagai karyawan atau admin
require_once __DIR__ . '/../../backend/config.php';          // Load konfigurasi global (BASE_URL, DB, dll)

$userName = $_SESSION['user_name'] ?? 'Staff';               // Ambil nama user dari session atau fallback "Staff"
$userRole = $_SESSION['user_role'] ?? 'karyawan'; // kirim ke JS  // Ambil role user dari session atau default "karyawan"
?>
<!doctype html>                                              
<html lang="id">                                             
<head>
  <meta charset="utf-8">                                     
  <title>Orders — Karyawan Desk</title>                      
  <meta name="viewport" content="width=device-width, initial-scale=1"> <!-- Responsif di mobile -->

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"> <!-- CSS Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"> <!-- Bootstrap Icons -->
  <script src="https://code.iconify.design/2/2.2.1/iconify.min.js"></script> <!-- Iconify untuk ikon tambahan -->

  <style>
    :root{
      --gold:#ffd54f;                                        /* Warna emas utama */
      --gold-soft:#f4d67a;                                   /* Warna emas lembut (hover/border) */
      --brown:#4B3F36;                                       /* Warna coklat brand */
      --ink:#111827;                                         /* Warna teks utama gelap */
      --radius:18px;                                         /* Default border radius kartu */
      --sidebar-w:320px;                                     /* Lebar sidebar */
    }

    body{
      background:#FAFAFA;                                    /* Latar belakang abu terang */
      color:var(--ink);                                      /* Warna teks */
      font-family:Inter,system-ui,Segoe UI,Roboto,Arial;     /* Font stack utama */
    }

    /* Sidebar */
    .sidebar{
      position:fixed;                                        /* Sidebar menempel di viewport */
      left:-320px;                                           /* Awal tersembunyi di kiri */
      top:0;
      bottom:0;
      width:var(--sidebar-w);                                /* Lebar sidebar sesuai variabel */
      background:#fff;                                       /* Background putih */
      border-right:1px solid rgba(0,0,0,.04);                /* Garis batas kanan tipis */
      transition:left .25s ease;                             /* Animasi slide kiri-kanan */
      z-index:1050;                                          /* Di atas konten utama */
      padding:14px 18px 18px;                                /* Padding dalam sidebar */
      overflow-y:auto;                                       /* Scroll jika kontennya panjang */
    }
    .sidebar.show{ left:0; }                                 /* Saat class show, sidebar muncul dari kiri */

    .sidebar-head{
      display:flex;                                          /* Atur head sidebar dengan flex */
      align-items:center;
      justify-content:space-between;
      gap:10px;
      margin-bottom:10px;
    }
    .sidebar-inner-toggle,
    .sidebar-close-btn{
      background:transparent;                                /* Tombol tanpa background */
      border:0;                                              /* Tanpa border */
      width:40px;
      height:36px;
      display:grid;                                          /* Grid untuk center ikon */
      place-items:center;
    }
    .hamb-icon{
      width:24px;
      height:20px;
      display:flex;
      flex-direction:column;
      justify-content:space-between;
      gap:4px;
    }
    .hamb-icon span{
      height:2px;
      background:var(--brown);                               /* Warna garis hamburger */
      border-radius:99px;
    }

    .sidebar .nav-link{
      display:flex;                                          /* Item menu sidebar horizontal */
      align-items:center;
      gap:12px;
      padding:12px 14px;                                     /* Padding link */
      border-radius:16px;                                    /* Sudut membulat */
      color:#111;
      font-weight:600;                                       /* Teks lebih tebal */
      text-decoration:none;                                  /* Hilangkan underline */
      background:transparent;
      user-select:none;                                      /* Tidak bisa diseleksi */
    }
    .sidebar .nav-link:hover,
    .sidebar .nav-link:focus,
    .sidebar .nav-link:active{
      background:rgba(255,213,79,0.25);                      /* Highlight kuning lembut saat hover/fokus */
      color:#111;
      outline:none;
      box-shadow:none;
    }

    .sidebar hr{
      border-color:rgba(0,0,0,.05);                          /* Garis pemisah menu */
      opacity:1;
    }

    .backdrop-mobile{ display:none; }                        /* Overlay untuk mobile dalam keadaan default */
    .backdrop-mobile.active{
      display:block;
      position:fixed;
      inset:0;
      background:rgba(0,0,0,.35);                            /* Overlay hitam transparan */
      z-index:1040;
    }

    .content{
      margin-left:0;                                         /* Konten tanpa offset sidebar (mobile) */
      padding:16px 14px 40px;                                /* Padding area konten */
    }

    .topbar{
      display:flex;
      align-items:center;
      gap:12px;
      margin-bottom:16px;                                    /* Jarak bawah topbar */
    }

    .btn-menu{
      background:transparent;                                /* Tombol hamburger transparan */
      border:0;
      width:40px;
      height:38px;
      display:grid;
      place-items:center;
    }

    .search-box{
      position:relative;
      flex:1 1 auto;
      min-width:0;
    }
    .search-input{
      height:46px;
      width:100%;
      border-radius:9999px;                                  /* Input berbentuk pill */
      padding-left:16px;
      padding-right:44px;                                    /* Ruang untuk ikon search */
      border:1px solid #e5e7eb;
      background:#fff;
      outline:none !important;
      transition:border-color .12s ease;                     /* Transisi border saat fokus */
      box-shadow:none !important;
    }
    .search-input:focus{
      border-color:var(--gold-soft) !important;              /* Border kuning lembut saat fokus */
      background:#fff;
    }
    .search-icon{
      position:absolute;
      right:16px;
      top:50%;
      transform:translateY(-50%);                            /* Pusatkan ikon di input */
      font-size:1.1rem;
      color:var(--brown);
      cursor:pointer;
    }

    .top-actions{
      display:flex;
      align-items:center;
      gap:14px;
      flex:0 0 auto;
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
    }

    #badgeNotif.notif-dot{
      position:absolute;
      top:3px;
      right:4px;
      width:8px;
      height:8px;
      background:#4b3f36;                                    /* Titik notif warna coklat gelap */
      border-radius:50%;
      box-shadow:0 0 0 1.5px #fff;                           /* Outline putih tipis */
    }
    #badgeNotif.d-none{ display:none !important; }           /* Sembunyikan notif-dot jika tidak ada notif */

    .cardx{
      background:#fff;
      border:1px solid #f7d78d;                              /* Border kuning muda */
      border-radius:var(--radius);
      padding:18px 22px;
    }

    /* ===== Tabel ===== */
    .table{
      margin-bottom:0;                                       /* Hilangkan margin bawah tabel */
    }
    .table thead th,
    .table td{
      vertical-align:middle;
      white-space:nowrap;                                    /* Jangan wrap teks di cell */
      text-align:center;
      padding:0.8rem 1.1rem;
    }
    .table thead th{
      background:#fffbe6;                                    /* Header tabel latar kuning lembut */
      font-weight:600;
    }

    .col-invoice{ min-width:170px; text-align:left; }        /* Kolom invoice lebih lebar, rata kiri */
    .col-name   { min-width:120px; text-align:left; }        /* Kolom nama */
    .col-total  { min-width:130px; }                         /* Kolom total */
    .col-status { min-width:130px; }                         /* Kolom status */
    .col-method { min-width:125px; }                         /* Kolom metode */
    .col-actions{ min-width:260px; }                         /* Kolom aksi agar tombol muat */

    .col-total{
      font-variant-numeric:tabular-nums;                     /* Angka rata (tabular) agar sejajar */
    }

    /* Layout aksi: 2 baris (atas: Mulai+Lunas, bawah: Batal+Struk) */
    .actions-cell{
      display:flex;
      flex-direction:column;
      align-items:center;
      gap:4px;
    }
    .actions-row{
      display:flex;
      flex-wrap:wrap;
      justify-content:center;
      gap:6px;
    }
    .actions-row .btn{
      border-radius:12px;
      padding:.28rem .9rem;
      font-size:.8rem;
    }
    .actions-row a.btn{
      display:inline-flex;
      align-items:center;
      gap:4px;
    }

    @media (min-width:992px){
      .content{
        padding:20px 26px 50px;                              /* Lebih lega di layar besar */
      }
      .search-box{ max-width:1100px; }                       /* Batasi lebar search di desktop */
    }

    /* Modal Batalkan (tema Caffora) */
    #modalCancel .modal-dialog{
      max-width:480px;                                       /* Lebar maksimum modal batal */
    }
    #modalCancel .modal-content{
      border-radius:24px;
      border:none;
      box-shadow:0 20px 60px rgba(15,23,42,.35);             /* Bayangan pekat untuk modal */
      padding:4px 2px 6px;
    }
    #modalCancel .modal-header{
      border-bottom:0;
      padding:18px 22px 6px;
    }
    #modalCancel .modal-title{
      font-weight:700;
      color:var(--brown);
    }
    #modalCancel .btn-close{
      box-shadow:none !important;
    }
    #modalCancel .modal-body{
      padding:6px 22px 18px;
    }
    #modalCancel .modal-footer{
      border-top:0;
      padding:4px 22px 18px;
      gap:10px;
    }
    #modalCancel .alert{
      border-radius:16px;
      background:#fff9e6;
      border-color:#ffe7aa;
      color:var(--brown);
      font-size:.9rem;
    }
    #modalCancel .form-select,
    #modalCancel textarea{
      border-radius:14px;
      border-color:#e5e7eb;
      box-shadow:none !important;
    }
    #modalCancel .form-select:focus,
    #modalCancel textarea:focus{
      border-color:var(--gold-soft);
    }
    #modalCancel .small{
      font-size:.78rem;
    }
    #modalCancel .btn{
      border-radius:999px;
      font-weight:600;
      padding:.48rem 1.3rem;
    }
    #modalCancel .btn-light{
      background:#fff7e0;
      border-color:#fff7e0;
      color:var(--brown);
    }
    #modalCancel .btn-light:hover{
      background:#ffefc2;
      border-color:#ffefc2;
    }
    #modalCancel .btn-danger{
      background:#ef4444;
      border-color:#ef4444;
    }
    #modalCancel .btn-danger:hover{
      background:#dc2626;
      border-color:#dc2626;
    }

    .modal-backdrop.show{
      background:rgba(15,23,42,.55);                         /* Overlay belakang modal lebih gelap */
    }
  </style>
</head>
<body>

<div id="backdrop" class="backdrop-mobile"></div>            <!-- Overlay hitam untuk menutup sidebar di mobile -->

<aside class="sidebar" id="sideNav">                          <!-- Sidebar navigasi karyawan -->
  <div class="sidebar-head">
    <button class="sidebar-inner-toggle" id="toggleSidebarInside" aria-label="Tutup menu"></button> <!-- Tombol tutup di dalam sidebar -->
    <button class="sidebar-close-btn" id="closeSidebar" aria-label="Tutup menu">                     <!-- Tombol X untuk menutup sidebar -->
      <i class="bi bi-x-lg"></i>
    </button>
  </div>
  <nav class="nav flex-column gap-2" id="sidebar-nav">        <!-- Daftar link navigasi vertikal -->
    <a class="nav-link" href="<?= BASE_URL ?>/public/karyawan/index.php">
      <i class="bi bi-house-door"></i> Dashboard              <!-- Menu ke dashboard karyawan -->
    </a>
    <a class="nav-link active" href="<?= BASE_URL ?>/public/karyawan/orders.php">
      <i class="bi bi-receipt"></i> Orders                    <!-- Menu aktif: Orders -->
    </a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/karyawan/stock.php">
      <i class="bi bi-box-seam"></i> Menu Stock               <!-- Menu stok / inventory -->
    </a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/karyawan/settings.php">
      <i class="bi bi-gear"></i> Settings                     <!-- Menu pengaturan akun -->
    </a>
    <hr>
    <a class="nav-link" href="<?= BASE_URL ?>/public/karyawan/help.php">
      <i class="bi bi-question-circle"></i> Help Center       <!-- Menu bantuan -->
    </a>
    <a class="nav-link" href="<?= BASE_URL ?>/backend/logout.php">
      <i class="bi bi-box-arrow-right"></i> Logout            <!-- Logout karyawan -->
    </a>
  </nav>
</aside>

<main class="content">                                       <!-- Kontainer utama isi halaman -->
  <div class="topbar">
    <button class="btn-menu" id="openSidebar" aria-label="Buka menu"> <!-- Tombol hamburger buka sidebar -->
      <div class="hamb-icon"><span></span><span></span><span></span></div>
    </button>

    <div class="search-box">                                  <!-- Box pencarian pesanan -->
      <input
        class="search-input"
        id="searchInput"
        placeholder="Search..."
        autocomplete="off"
      />
      <i class="bi bi-search search-icon" id="searchIcon"></i> <!-- Ikon search clickable -->
    </div>

    <div class="top-actions">                                 <!-- Aksi kanan atas (notifikasi & akun) -->
      <a
        id="btnBell"
        class="icon-btn position-relative text-decoration-none"
        href="<?= BASE_URL ?>/public/karyawan/notifications.php"
        aria-label="Notifikasi"
      >
        <span
          class="iconify"
          data-icon="mdi:bell-outline"
          data-width="24"
          data-height="24"
        ></span>
        <span id="badgeNotif" class="notif-dot d-none"></span> <!-- Titik kecil indikator notif belum dibaca -->
      </a>
      <a
        class="icon-btn text-decoration-none"
        href="<?= BASE_URL ?>/public/karyawan/settings.php"
        aria-label="Akun"
      >
        <span
          class="iconify"
          data-icon="mdi:account-circle-outline"
          data-width="28"
          data-height="28"
        ></span>
      </a>
    </div>
  </div>

  <h2 class="fw-bold mb-3">Daftar Pesanan</h2>                <!-- Judul halaman list pesanan -->

  <div class="cardx">                                         <!-- Card pembungkus tabel pesanan -->
    <div class="table-responsive">                            <!-- Tabel bisa discroll horizontal di mobile -->
      <table class="table align-middle">
        <thead>
          <tr>
            <th class="col-invoice">Invoice</th>              <!-- Kolom nomor invoice -->
            <th class="col-name">Nama</th>                    <!-- Nama pelanggan -->
            <th class="col-total">Total</th>                  <!-- Total tagihan -->
            <th class="col-status">Pesanan</th>               <!-- Status pesanan -->
            <th class="col-status">Pembayaran</th>            <!-- Status pembayaran -->
            <th class="col-method">Metode</th>                <!-- Metode pembayaran -->
            <th class="col-actions">Aksi</th>                 <!-- Tombol aksi -->
          </tr>
        </thead>
        <tbody id="rows">                                     <!-- Body tabel akan diisi via JS -->
          <tr>
            <td colspan="7" class="text-center text-muted py-4">Memuat...</td> <!-- Placeholder saat loading -->
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</main>

<!-- Modal Batalkan Pesanan -->
<div class="modal fade" id="modalCancel" tabindex="-1" aria-hidden="true"> <!-- Modal konfirmasi pembatalan -->
  <div class="modal-dialog modal-dialog-centered modal-md">
    <form class="modal-content" id="formCancel">             <!-- Form pembatalan pesanan -->
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Batalkan Pesanan</h5> <!-- Judul modal -->
        <button
          type="button"
          class="btn-close"
          data-bs-dismiss="modal"
          aria-label="Tutup"
        ></button>
      </div>

      <div class="modal-body">
        <input type="hidden" name="order_id" id="cancel_order_id"> <!-- Hidden ID pesanan yang dibatalkan -->

        <div class="alert alert-warning py-2 mb-3">
          Anda yakin ingin membatalkan pesanan
          <span id="cancel_invoice" class="fw-semibold"></span>? <!-- Tampilkan nomor invoice dinamis -->
        </div>

        <label class="form-label">
          Alasan pembatalan <span class="text-danger">*</span>    <!-- Label alasan wajib -->
        </label>
        <select
          class="form-select"
          name="reason_sel"
          id="cancel_reason_sel"
          required
        >
          <option value="">Pilih alasan…</option>                 <!-- Placeholder pilihan -->
          <option>Stok habis / tidak mencukupi</option>
          <option>Pelanggan tidak melanjutkan (belum bayar)</option>
          <option>Salah input pesanan</option>
          <option>Menu tidak tersedia hari ini</option>
          <option value="__custom__">Lainnya (tulis manual)</option> <!-- Pilihan untuk alasan custom -->
        </select>

        <div class="mt-2 d-none" id="cancel_reason_custom_wrap">  <!-- Textarea alasan custom, hidden by default -->
          <textarea
            class="form-control"
            id="cancel_reason_custom"
            rows="2"
            placeholder="Tulis alasan singkat"
          ></textarea>
        </div>

        <div class="small text-muted mt-2">
          Pembatalan hanya untuk pesanan
          <strong>belum dibayar</strong>. Setelah batal, pembayaran
          ditandai <em>failed</em>.                             <!-- Info aturan pembatalan -->
        </div>
      </div>

      <div class="modal-footer">
        <button
          class="btn btn-light"
          type="button"
          data-bs-dismiss="modal"
        >
          Tidak                                                   <!-- Tombol batal (tidak jadi membatalkan) -->
        </button>
        <button class="btn btn-danger" type="submit">
          Ya, Batalkan                                            <!-- Tombol submit pembatalan -->
        </button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script> <!-- JS Bootstrap bundle -->
<script>
/* =============================
   ANTI-XSS ESCAPE HELPER
   ============================= */
function escHtml(value){                                      // Helper untuk escape teks agar aman dari XSS
  const str = String(value ?? '');                            // Pastikan nilai jadi string (fallback kosong)
  return str
    .replace(/&/g, '&amp;')                                   // Ganti & dengan &amp;
    .replace(/</g, '&lt;')                                    // Ganti < dengan &lt;
    .replace(/>/g, '&gt;')                                    // Ganti > dengan &gt;
    .replace(/"/g, '&quot;')                                  // Ganti " dengan &quot;
    .replace(/'/g, '&#39;');                                  // Ganti ' dengan &#39;
}

const USER_ROLE = <?= json_encode($userRole, JSON_UNESCAPED_SLASHES) ?>; // Role user dari PHP dikirim ke JS
const BASE      = "<?= rtrim(BASE_URL, '/') ?>";             // BASE_URL tanpa slash di akhir
const ORIGIN    = location.origin.replace(/^http:\/\//i, "https://"); // Normalisasi origin ke https (kalau http)

/* Endpoint clean + fallback legacy */
const API_ORDERS_CLEAN  = ORIGIN + "/api/orders";            // Endpoint clean untuk list/update orders
const API_ORDERS_LEGACY = ORIGIN + "/backend/api/orders.php"; // Endpoint legacy untuk orders
const API_NOTIF_CLEAN   = ORIGIN + "/api/notifications?action=unread_count"; // Endpoint clean jumlah notif belum dibaca
const API_NOTIF_LEGACY  = ORIGIN + "/backend/api/notifications.php?action=unread_count"; // Endpoint legacy notif

const $rows   = document.getElementById('rows');              // Element tbody tempat render baris pesanan
const $search = document.getElementById('searchInput');       // Input pencarian orders

let cancelModal, cancelOrderInput, cancelInvoiceSpan;         // Variabel untuk komponen modal batal
let selCancel, wrapCustom, cancelReasonCustom;                // Elemen select + wrapper alasan custom + textarea custom

/* Sidebar */
const sideNav  = document.getElementById('sideNav');          // Element sideba
