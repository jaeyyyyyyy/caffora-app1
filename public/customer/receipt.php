<?php
// Lokasi file: public/customer/receipt.php

// Aktifkan strict typing
declare(strict_types=1);

// Mulai session jika belum aktif
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

// Import guard login (wajib customer)
require_once __DIR__ . '/../../backend/auth_guard.php';

// Pastikan hanya customer boleh akses
require_login(['customer']);

// Import config (koneksi DB & BASE_URL & fungsi h())
require_once __DIR__ . '/../../backend/config.php';

// Fungsi helper format Rupiah
function rp(float $n): string {
  return 'Rp ' . number_format($n, 0, ',', '.');
}

// Ambil order_id dari URL (?order=xx)
$orderId = (int)($_GET['order'] ?? 0);

// Ambil user_id dari session login
$userId  = (int)($_SESSION['user_id'] ?? 0);

// Siapkan variabel default
$ord   = null;
$items = [];
$inv   = null;

// Jika order_id dan user_id valid
if ($orderId && $userId) {

  // Ambil data order
  $sqlOrder = "
    SELECT *
    FROM orders
    WHERE id = ? AND user_id = ?
    LIMIT 1
  ";
  $stmt = $conn->prepare($sqlOrder);
  $stmt->bind_param('ii', $orderId, $userId);
  $stmt->execute();
  $ord = $stmt->get_result()?->fetch_assoc();
  $stmt->close();

  // Jika order ditemukan
  if ($ord) {

    // Ambil item-item pesanan berdasarkan order_id
    $sqlItems = "
      SELECT
        oi.qty,
        oi.price,
        m.name AS menu_name
      FROM order_items oi
      LEFT JOIN menu m ON m.id = oi.menu_id
      WHERE oi.order_id = ?
      ORDER BY oi.id
    ";

    // Siapkan statement untuk query item pesanan
    $stmt = $conn->prepare($sqlItems);

    // Bind parameter order_id ke statement
    $stmt->bind_param('i', $orderId);

    // Eksekusi query item ke database
    $stmt->execute();

    // Ambil result set dari statement
    $result = $stmt->get_result();

    // Jika hasil tidak null, ambil seluruh item
    $items = $result
      ? $result->fetch_all(MYSQLI_ASSOC)
      : []; // Jika null, fallback array kosong

    // Tutup statement setelah selesai digunakan
    $stmt->close();

    // Ambil invoice pesanan berdasarkan order_id
    $sqlInv = "
      SELECT amount, issued_at
      FROM invoices
      WHERE order_id = ?
      LIMIT 1
    ";

    // Siapkan statement untuk query invoice
    $stmt = $conn->prepare($sqlInv);

    // Bind parameter order_id ke query
    $stmt->bind_param('i', $orderId);

    // Eksekusi query ke database
    $stmt->execute();

    // Ambil hasil invoice sebagai associative array
    $inv = $stmt->get_result()?->fetch_assoc();

    // Tutup statement setelah selesai
    $stmt->close();
  } // end if ($ord)
}   // end if ($orderId && $userId)

// Guard akses jika order tidak ditemukan
if (!$ord) {
  http_response_code(403);
  echo 'Tidak boleh mengakses';
  exit;
}

// =========================================================
// CASE 1: STRUK BELUM BISA DITAMPILKAN (BELUM LUNAS)
// =========================================================
if ($ord['payment_status'] !== 'paid') {
?>

 <!-- Deklarasi bahwa dokumen menggunakan standar HTML5 -->
<!doctype html>

<!-- Set bahasa halaman ke Bahasa Indonesia -->
<html lang="id">

<!-- Awal elemen <head> untuk metadata & resource -->
<head>

  <!-- Set karakter encoding ke UTF-8 agar teks tampil dengan benar -->
  <meta charset="utf-8">

  <!-- Mengatur agar layout responsif mengikuti lebar perangkat -->
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Judul halaman yang tampil pada tab browser -->
  <title>Struk belum tersedia — Caffora</title>

  <!-- Import file Bootstrap Icons dari CDN untuk ikon bawaan -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>

<style>

  :root{                                  /* Variabel warna & layout global */
    --gold:#FFD54F;                       /* Warna emas */
    --brown:#4B3F36;                      /* Warna coklat brand */
    --line:#e5e7eb;                       /* Warna garis */
    --ink:#111827;                        /* Warna teks utama */
    --muted:#6b7280;                      /* Warna teks lembut */
    --bg-page:#fafafa;                    /* Latar belakang halaman */
    --bg-card:#ffffff;                    /* Latar belakang card */
  }

  *{                                      /* Global reset */
    box-sizing:border-box;                /* Box model konsisten */
    font-family:"Poppins", system-ui,     /* Font utama */
                 -apple-system, "Segoe UI",
                 Roboto, Arial, sans-serif;
  }

  html, body{                             /* Reset margin & color */
    margin:0;                             /* Hapus margin default */
    padding:0;                            /* Hapus padding default */
    background:var(--bg-page);            /* Background global */
    color:var(--ink);                     /* Teks utama */
    -webkit-font-smoothing:antialiased;   /* Haluskan font */
  }

  /* ======================= TOPBAR ======================= */

  .topbar{                                 /* Header atas halaman */
    position:sticky;                       /* Tetap di atas saat scroll */
    top:0;                                 /* Posisi atas */
    z-index:20;                            /* Prioritas layer */
    background:#fff;                       /* Warna putih */
    border-bottom:1px solid rgba(0,0,0,.04); /* Garis bawah tipis */
  }

  .topbar .inner{                          /* Container dalam topbar */
    max-width:1200px;                      /* Lebar maksimal */
    margin:0 auto;                         /* Tengah secara horizontal */
    padding:12px 16px;                     /* Ruang dalam */
    min-height:52px;                       /* Tinggi minimum */
    display:flex;                          /* Flexbox */
    align-items:center;                    /* Vertikal tengah */
    gap:10px;                              /* Jarak antar elemen */
    justify-content:space-between;         /* Spasi kiri–kanan */
  }

  .back-link{                              /* Tombol kembali */
    display:inline-flex;                   /* Inline + flex */
    align-items:center;                    /* Vertikal tengah */
    gap:10px;                              /* Jarak icon–teks */
    color:var(--brown);                    /* Warna coklat brand */
    text-decoration:none;                  /* Hilangkan underline */
    font-weight:600;                       /* Bold */
    font-size:16px;                        /* Ukuran teks */
    line-height:1.3;                       /* Line-height */
  }

  .back-link .bi{                          /* Icon panah */
    font-size:18px !important;             /* Ukuran icon */
    width:18px;                            /* Lebar icon */
    height:18px;                           /* Tinggi icon */
    line-height:1;                         /* Tinggi baris icon */
    display:inline-flex;                   /* Flex */
    align-items:center;                    /* Tengah vertikal */
    justify-content:center;                /* Tengah horizontal */
  }

  /* ======================= PAGE WRAPPER ======================= */

  .page-wrapper{                           /* Container konten */
    max-width:1200px;                      /* Lebar maksimal */
    margin:14px auto 40px;                 /* Atas 14px, bawah 40px */
    padding:0 16px;                        /* Padding kiri-kanan */
  }

  .status-card{                            /* Card status invoice */
    background:var(--bg-card);             /* Latar card */
    border-radius:14px;                    /* Sudut membulat */
    border:1px solid rgba(0,0,0,.03);      /* Border tipis */
    box-shadow:0 6px 18px rgba(0,0,0,.03); /* Bayangan lembut */
    padding:18px 16px 20px;                /* Ruang dalam */
  }

  .status-title{                           /* Judul status */
    margin:0 0 10px;                       /* Margin bawah */
    font-weight:600;                       /* Bold */
    font-size:1.05rem;                     /* Ukuran teks */
  }

  .status-desc{                            /* Deskripsi status */
    margin:0 0 10px;                       /* Jarak bawah */
    color:var(--muted);                    /* Teks warna soft */
  }

  /* ======================= RESPONSIVE ======================= */   /* Blok untuk tampilan tablet ke atas */

  @media (min-width:768px){                /* Aktif jika layar ≥ 768px */

    .topbar .inner{                        /* Topbar diberi padding lebih lebar */
      padding:12px 24px;                   /* Tambah ruang kiri-kanan */
    }

    .page-wrapper{                         /* Wrapper konten saat tablet/desktop */
      padding:0 24px;                      /* Lebih lega daripada mobile */
    }

    .status-card{                          /* Card status struk */
      padding:20px 20px 22px;              /* Padding sedikit lebih besar */
    }

  }                                        /* Penutup media query */

</style>                                   

  <!-- Penutup elemen <head> -->
</head>

  <!-- Awal elemen <body> -->
<body>

  <!-- Wrapper topbar bagian atas halaman -->
  <div class="topbar">

    <!-- Kontainer dalam topbar untuk alignment -->
    <div class="inner">

      <!-- Tombol kembali ke halaman history -->
      <a
        class="back-link"
        href="<?= h(BASE_URL) ?>/public/customer/history.php"
      >
        <!-- Ikon panah kembali -->
        <i class="bi bi-arrow-left"></i>

        <!-- Teks tombol kembali -->
        <span>Kembali</span>
      </a>

    </div>
    <!-- Akhir .inner -->
  </div>
  
  <!-- Akhir .topbar -->

  <!-- Wrapper utama konten halaman -->
  <div class="page-wrapper">

    <!-- Kartu status struk belum tersedia -->
    <section class="status-card">

      <!-- Judul informasi -->
      <h2 class="status-title">
        Struk belum tersedia
      </h2>

      <!-- Deskripsi kenapa struk belum muncul -->
      <p class="status-desc">
        Struk hanya dapat diunduh setelah pembayaran
        <b>lunas</b>.
      </p>

      <!-- Informasi nomor invoice -->
      <p class="status-invoice">
        Invoice:
        <b><?= h($ord['invoice_no']) ?></b>
      </p>

    </section>
    <!-- Akhir .status-card -->

  </div>
  <!-- Akhir .page-wrapper -->

  <!-- Penutup elemen <body> -->
</body>

  <!-- Penutup dokumen HTML -->
</html>

  <!-- Penutup blok CASE 1 (struk belum tersedia) -->
<?php
     // Hentikan seluruh eksekusi script (tidak lanjut ke struk lengkap)
  exit;
} ?>

<!-- ========================================================= -->
<!-- CASE 2: STATUS LUNAS → TAMPILKAN STRUK LENGKAP -->
<!-- ========================================================= -->
<?php

  /* Siapkan nilai subtotal.
     Jika backend lama belum memiliki kolom subtotal,
     fallback menggunakan total pesanan. */
  $subtotal =
    isset($ord['subtotal'])
      ? (float)$ord['subtotal']
      : (float)($ord['total'] ?? 0);

  /* Siapkan nilai pajak (tax_amount).
     Jika tidak tersedia di backend lama, gunakan 0. */
  $taxAmount =
    isset($ord['tax_amount'])
      ? (float)$ord['tax_amount']
      : 0.0;

  /* Siapkan grand total.
     Jika kolom tidak tersedia, gunakan subtotal + pajak. */
  $grandTotal =
    isset($ord['grand_total'])
      ? (float)$ord['grand_total']
      : ($subtotal + $taxAmount);

?>

  <!-- Deklarasi tipe dokumen HTML5 -->
<!doctype html>

  <!-- Tag pembungkus HTML, dengan bahasa Indonesia -->
<html lang="id">

  <!-- Awal elemen <head> -->
<head>

  <!-- Set encoding karakter menjadi UTF-8 -->
  <meta charset="utf-8">

  <!-- Judul halaman: Struk + nomor invoice -->
  <title>
    Struk — <?= h($ord['invoice_no']) ?> | Caffora
  </title>

  <!-- Agar tampilan responsif di semua perangkat -->
  <meta
    name="viewport"
    content="width=device-width, initial-scale=1"
  >

  <!-- Load Bootstrap Icons dari CDN -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    rel="stylesheet"
  >

<script>

  // Fungsi untuk memuat file JavaScript eksternal secara dinamis
  function loadScript(src){

    // Mengembalikan Promise agar pemanggilan bisa pakai await
    return new Promise((res, rej) => {

      // Buat elemen <script> baru
      const s = document.createElement('script');

      // Set URL sumber file yang akan dimuat
      s.src = src;

      // Izinkan pemuatan async (tidak blok render)
      s.async = true;

      // Izinkan akses dari CDN (cross-origin)
      s.crossOrigin = 'anonymous';

      // Jika file berhasil dimuat → resolve
      s.onload = res;

      // Jika gagal dimuat → reject dengan pesan error
      s.onerror = () =>
        rej(
          new Error('Gagal memuat ' + src)
        );

      // Tambahkan elemen <script> ke <head> agar mulai memuat
      document.head.appendChild(s);
    });
  }

  // Pastikan library html2canvas tersedia sebelum dipakai
  async function ensureHtml2Canvas(){

    // Jika html2canvas sudah ada di window → tidak perlu load lagi
    if (window.html2canvas) return;

    try{
      // Coba memuat html2canvas dari CDN jsDelivr
      await loadScript(
        'https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js'
      );

    } catch(e){

      // Jika gagal memuat dari jsDelivr → fallback ke CDN unpkg
      await loadScript(
        'https://unpkg.com/html2canvas@1.4.1/dist/html2canvas.min.js'
      );
    }
  }

</script>

  <style>
    :root{                                     /* Variabel warna & style global */
      --ink:#111827;                           /* Warna teks utama */
      --muted:#6b7280;                         /* Warna teks sekunder */
      --line:#e5e7eb;                          /* Warna garis pembatas */
      --bg-page:#fafafa;                       /* Warna background halaman */
      --bg-card:#ffffff;                       /* Warna background card */
      --gold:#FFD54F;                          /* Warna emas brand */
      --brown:#4B3F36;                         /* Warna coklat brand */
    }

    *{                                          /* Reset dasar semua elemen */
      font-family: Inter, system-ui,            /* Gunakan Inter sebagai default */
                   -apple-system, "Segoe UI", 
                   Roboto, Arial, sans-serif;
      box-sizing:border-box;                    /* Pastikan padding masuk ke width */
    }

    html,body{                                  /* Style global dokumen */
      background:var(--bg-page);                /* Background lembut */
      color:var(--ink);                         /* Warna teks utama */
      margin:0;                                 /* Reset margin */
      padding:0;                                /* Reset padding */
      -webkit-font-smoothing:antialiased;       /* Haluskan font */
    }

    /* ===== TOPBAR SERAGAM ===== */
    .topbar{                                    /* Wrapper topbar (atas halaman) */
      position:sticky;                          /* Menempel saat scroll */
      top:0;                                    /* Posisi di atas */
      background:#fff;                          /* Warna putih */
      border-bottom:1px solid rgba(0,0,0,.04);  /* Garis pembatas tipis */
      z-index:20;                               /* Di atas konten */
    }

    .topbar .inner{                             /* Kontainer dalam topbar */
      max-width:1200px;                         /* Lebar maksimum */
      margin:0 auto;                            /* Center horizontal */
      padding:12px 16px;                        /* Padding responsif */
      min-height:52px;                          /* Tinggi minimum */
      display:flex;                             /* Flex layout */
      align-items:center;                       /* Vertikal tengah */
      gap:10px;                                 /* Jarak antar elemen */
      justify-content:space-between;            /* Posisi kiri & kanan */
    }

    .back-link {                                /* Link tombol kembali */
      display:inline-flex;                      /* Flex inline */
      align-items:center;                       /* Ikon + teks sejajar */
      gap:10px;                                 /* Jarak ikon-teks */
      color:var(--brown);                       /* Warna brand */
      text-decoration:none;                     /* Hilangkan garis bawah */
      font-weight:600;                          /* Teks lebih tebal */
      font-size:16px;                           /* Ukuran font */
      line-height:1.3;                          /* Tinggi baris */
    }

    .back-link .bi {                            /* Ikon panah */
      font-size:18px !important;                /* Besar ikon */
      width:18px;                               /* Lebar */
      height:18px;                              /* Tinggi */
      line-height:1;                            /* Sesuaikan */
      display:inline-flex;                      /* Flex center */
      align-items:center;                       /* Center */
      justify-content:center;                   /* Center */
    }

    /* tombol download */
    .btn-download {                             /* Tombol download struk */
      background-color:var(--gold);             /* Warna emas brand */
      color:var(--brown) !important;            /* Warna teks */
      border:0;                                 /* Tanpa border */
      border-radius:12px;                       /* Sudut membulat */
      padding:10px 14px;                        /* Ruang dalam */
      display:inline-flex;                      /* Flex */
      align-items:center;                       /* Tengah vertikal */
      justify-content:center;                   /* Tengah horizontal */
      gap:8px;                                  /* Jarak ikon-teks */
      text-decoration:none !important;          /* Hilangkan garis bawah */
      box-shadow:none;                          /* Tanpa shadow */
      cursor:pointer;                           /* Tangan pointer */
      white-space:nowrap;                       /* Tidak turun baris */
    }

    .btn-download,
    .btn-download * {                           /* Font tombol diseragamkan */
      font-family:Arial, sans-serif !important; /* Pakai Arial */
      font-weight:600;                          /* Teks tebal */
      font-size:13.3px;                         /* Ukuran font */
      line-height:1.2;                          /* Line height */
      color:var(--brown) !important;            /* Warna teks */
    }

    .btn-download svg{                          /* Ikon SVG */
      width:18px;                               /* Lebar ikon */
      height:18px;                              /* Tinggi ikon */
      display:block;                            /* Mencegah sedikit offset */
    }

    /* ===== KONTEN STRUK ===== */
    .page-wrapper {                             /* Bungkus halaman */
      max-width:1200px;                         /* Lebar maksimum */
      margin:14px auto 40px;                    /* Margin atas-bawah */
      padding:0 16px;                           /* Padding kiri-kanan */
      display:flex;                             /* Flex */
      justify-content:center;                   /* Tengah */
    }

    .paper {                                    /* Kertas struk */
      background:var(--bg-card);                /* Background putih */
      width:100%;                               /* Isi lebar */
      max-width:320px;                          /* Lebar maksimum */
      box-shadow:0 2px 6px rgba(0,0,0,.05);     /* Shadow lembut */
      padding:18px 20px;                        /* Ruang dalam */
      border:1px solid var(--line);             /* Border halus */
    }

    .paper,
    .paper * {                                  /* Font seragam untuk struk */
      font-family:Arial, sans-serif !important; /* Arial */
      color:#4b5563 !important;                 /* Warna teks */
    }

    .brand {                                     /* Nama brand */
      font-weight:600;                           /* Teks tebal */
      font-size:16px;                            /* Ukuran font */
      margin-bottom:4px;                         /* Jarak bawah */
    }

    .meta {                                      /* Baris info */
      display:flex;                              /* Flex */
      justify-content:space-between;             /* Kiri-kanan */
      gap:10px;                                  /* Jarak */
      font-size:.8rem;                           /* Ukuran kecil */
      line-height:1.4;                           /* Line height */
    }

    .to { text-align:right; }                    /* Align kanan */

    .rule {                                      /* Garis putus-putus */
      border-top:2px dashed #aaa;                /* Gaya garis */
      margin:10px 0;                             /* Margin */
      opacity:.8;                                /* Opacity */
    }

    .head-row {                                  /* Judul kolom item */
      display:flex;                              /* Flex */
      justify-content:space-between;             /* Space between */
      font-weight:500;                           /* Teks tebal */
      padding:4px 0 6px;                         /* Padding */
      font-size:.8rem;                           /* Ukuran kecil */
    }

    .item {                                      /* Baris item */
      display:grid;                              /* Grid layout */
      grid-template-columns:1fr auto;            /* Nama - harga */
      gap:12px;                                  /* Jarak */
      align-items:flex-start;                    /* Align atas */
      padding:8px 0;                             /* Padding */
    }
   
        .item-name {                                 /* Nama item */
      font-weight: 500;                          /* Teks tebal sedang */
      font-size: .85rem;                         /* Ukuran sedikit kecil */
    }

    .item-sub {                                   /* Sub info item */
      font-size: .75rem;                          /* Ukuran lebih kecil */
      line-height: 1.4;                           /* Tinggi baris nyaman */
      margin-top: 1px;                            /* Jarak kecil dari nama */
    }

    .item-subtotal {                              /* Harga total per item */
      font-weight: 500;                           /* Teks tebal sedang */
      white-space: nowrap;                        /* Tidak pindah baris */
      font-size: .8rem;                           /* Ukuran kecil */
      text-align: right;                          /* Format rata kanan */
    }

    .row {                                        /* Baris ringkasan */
      display: flex;                              /* Susunan horizontal */
      justify-content: space-between;             /* Label kiri, nilai kanan */
      padding: 6px 0;                             /* Jarak vertikal */
      font-size: .8rem;                           /* Ukuran teks */
    }

    .row.total {                                  /* Baris total akhir */
      font-weight: 600;                           /* Lebih tebal */
      font-size: .9rem;                           /* Sedikit lebih besar */
      border-top: 2px dotted var(--line);         /* Garis pemisah */
      margin-top: 4px;                            /* Jarak atas */
      padding-top: 8px;                           /* Ruang di atas teks */
    }

    .status-line {                                /* Baris status pesanan */
      display: flex;                              /* Grid fleksibel */
      flex-wrap: wrap;                            /* Baris bisa turun */
      justify-content: space-between;             /* Spasi merata */
      gap: 8px 12px;                              /* Jarak antar elemen */
      padding-top: 8px;                           /* Jarak ke atas */
      font-size: .8rem;                           /* Ukuran teks */
    }

    .pill {                                       /* Badge status (PAID/PNDG) */
      padding: 0;                                 /* Tanpa padding */
      border: none !important;                    /* Tidak ada border */
      background: transparent !important;         /* BG transparan */
      font-weight: 700 !important;                /* Tebal */
      letter-spacing: .18rem;                     /* Spasi antar huruf */
      font-size: .75rem;                          /* Ukuran kecil */
    }

    .footer-note {                                /* Catatan kecil footer */
      font-size: .7rem;                           /* Teks mini */
      line-height: 1.5;                           /* Tinggi baris rapi */
    }

    @media (min-width: 768px) {                   /* Tablet ke atas */
      .topbar .inner,                             /* Topbar padding */
      .page-wrapper {                             /* Wrapper padding */
        padding: 12px 24px;                       /* Lebih lega */
      }
      .page-wrapper {                             /* Wrapper layout */
        margin: 16px auto 50px;                   /* Jarak lebih longgar */
      }
    }

    @keyframes spin {                              /* Animasi loading */
      from { transform: rotate(0); }               /* Mulai dari 0° */
      to   { transform: rotate(360deg); }          /* Putar penuh */
    }

    .spin {                                        /* Elemen spinner */
      width: 14px;                                 /* Lebar */
      height: 14px;                                /* Tinggi */
      border: 2px solid currentColor;              /* Border spinner */
      border-right-color: transparent;             /* Bagian kosong */
      border-radius: 50%;                          /* Bentuk lingkaran */
      display: inline-block;                       /* Inline tapi block */
      animation: spin .7s linear infinite;         /* Animasi berulang */
    }

    @media print {                                 /* Mode cetak */
      .topbar { display: none !important; }        /* Hide topbar */
      body { background: #fff; }                   /* BG putih */
      .page-wrapper { margin: 0; padding: 0; }     /* Full page */
      .paper {                                     /* Kertas struk */
        box-shadow: none;                          /* Tanpa bayangan */
        border: 0;                                 /* Tanpa garis */
        max-width: 100%;                           /* Lebarkan penuh */
      }
    }
</style>

  <!-- Penutup elemen head -->
</head>

<!-- Awal elemen body -->
<body>

  <!-- TOPBAR halaman struk -->
  <div class="topbar">

    <!-- Wrapper dalam topbar -->
    <div class="inner">

      <!-- Tombol kembali menuju halaman riwayat -->
      <a
        class="back-link"
        href="<?= h(BASE_URL) ?>/public/customer/history.php"
      >
        <!-- Ikon panah -->
        <i class="bi bi-arrow-left chev"></i>

        <!-- Teks tombol kembali -->
        <span>Kembali</span>
      </a>

      <!-- Tombol download struk -->
      <button
        id="btnDownload"
        class="btn-download"
      >
        <!-- Ikon download SVG -->
        <svg
          viewBox="0 0 24 24"
          fill="currentColor"
          aria-hidden="true"
        >
          <path
            d="M12 3a1 1 0 011 1v8.586l2.293-2.293a1 1 0 
            111.414 1.414l-4.001 4a1 1 0 01-1.414 
            0l-4.001-4a1 1 0 111.414-1.414L11 
            12.586V4a1 1 0 011-1z"
          ></path>

          <path
            d="M5 19a1 1 0 011-1h12a1 1 0 
            110 2H6a1 1 0 01-1-1z"
          ></path>
        </svg>

        <!-- Label tombol download -->
        <span>Download</span>
      </button>

    </div>
    <!-- Akhir .inner -->
  </div>
  <!-- Akhir .topbar -->

  <!-- Wrapper besar konten struk -->
  <div class="page-wrapper">

    <!-- Kertas struk -->
    <main
      class="paper"
      id="receiptContent"
    >

      <!-- Nama brand -->
      <div class="brand">Caffora</div>

      <!-- Meta invoice dan penerima -->
      <div class="meta">

        <!-- Kolom kiri meta -->
        <div>
          <!-- Nomor invoice -->
          <div>
            Invoice:
            <span style="font-weight:500">
              <?= h($ord['invoice_no']) ?>
            </span>
          </div>

          <!-- Tanggal transaksi -->
          <div>
            Tanggal:
            <?= h(date('d M Y H:i',
              strtotime($ord['created_at']))
            ) ?>
          </div>
        </div>

        <!-- Kolom kanan: detail penerima -->
        <div class="to">

          <!-- Label "Kepada" -->
          <div style="color:#9ca3af">
            Kepada:
          </div>

          <!-- Nama customer -->
          <div style="font-weight:500">
            <?= h($ord['customer_name']) ?>
          </div>

          <!-- Tipe layanan + nomor meja -->
          <div>
            <?= h(
              $ord['service_type']==='dine_in'
              ? 'Dine In'
              : 'Take Away'
            ) ?>

            <?= $ord['table_no']
                ? ', Meja '.h($ord['table_no'])
                : ''
            ?>
          </div>
        </div>

      </div>
      <!-- Akhir .meta -->

      <!-- Garis pemisah -->
      <div class="rule"></div>

      <!-- Header tabel item -->
      <div class="head-row">
        <div>Item</div>
        <div>Subtotal</div>
      </div>

      <!-- Daftar item -->
      <div class="items">

        <!-- Loop item pesanan -->
        <?php foreach ($items as $it):
          $sub = (float)$it['qty'] *
                 (float)$it['price'];
        ?>
        
          <!-- Satu baris item -->
          <div class="item">

            <!-- Kolom kiri item -->
            <div>

              <!-- Nama item -->
              <div class="item-name">
                <?= h($it['menu_name'] ?? 'Menu') ?>
              </div>

              <!-- Detail qty × harga -->
              <div class="item-sub">
                Qty: <?= (int)$it['qty'] ?>
                × <?= rp((float)$it['price']) ?>
              </div>
            </div>

            <!-- Kolom kanan total item -->
            <div class="item-subtotal">
              <?= rp($sub) ?>
            </div>

          </div>
          <!-- Akhir .item -->

        <?php endforeach; ?>

      </div>
      <!-- Akhir .items -->

      <!-- Ringkasan total -->
      <div
        class="row"
        style="margin-top:6px"
      >
        <div>Subtotal</div>
        <div class="muted">
          <?= rp($subtotal) ?>
        </div>
      </div>

      <div class="row">
        <div>Pajak 11%</div>
        <div class="muted">
          <?= rp($taxAmount) ?>
        </div>
      </div>

      <div class="row total">
        <div>Total</div>
        <div>
          <?= rp($grandTotal) ?>
        </div>
      </div>

      <!-- Status pembayaran -->
      <div class="status-line">

        <!-- Badge LUNAS -->
        <div class="pill">
          L U N A S
        </div>

        <!-- Metode bayar -->
        <div>
          Metode Pembayaran:
          <b>
          <?= h(
            strtoupper(
             str_replace('_',' ',
               $ord['payment_method'] ?? '-'
             )
            )
          ) ?>
          </b>
        </div>

      </div>

      <!-- Garis pemisah -->
      <div class="rule"></div>

      <!-- Catatan kaki -->
      <div class="footer-note">

        <?php if ($inv): ?>

          Tagihan:
          <b><?= rp((float)$inv['amount']) ?></b><br>

          Diterbitkan:
          <?= h(
            date('d M Y H:i',
              strtotime($inv['issued_at'])
            )
          ) ?><br>

        <?php endif; ?>

        * Harga sudah termasuk PPN 11%.
        Terima kasih telah berbelanja
        di <b>Caffora</b>.

        <!-- Akhir footer note -->
      </div>
     <!-- Akhir .paper -->
    </main>
  <!-- Akhir .page-wrapper -->
  </div>
  
  <script>
  // Jalankan fungsi async segera (IIFE)
  (async function(){

    // Ambil tombol download
    const btn = document.getElementById('btnDownload');

    // Pastikan html2canvas sudah dimuat
    await ensureHtml2Canvas();

    // Event ketika tombol download diklik
    btn.addEventListener('click', async () => {

      // Jika tombol sedang disabled, hentikan
      if (btn.disabled) return;

      // Simpan isi tombol asli
      const original = btn.innerHTML;

      // Disable tombol + tampilkan spinner
      btn.disabled = true;
      btn.innerHTML = '<span class="spin"></span> Mengunduh...';

      try {
        // Ambil elemen struk yang akan dirender
        const srcNode = document.getElementById('receiptContent');

        // Clone elemen agar tidak mengganggu tampilan asli
        const clone = srcNode.cloneNode(true);

        // Bungkus clone di container khusus
        const wrapper = document.createElement('div');

        // Posisi wrapper di luar layar
        wrapper.style.position = 'fixed';
        wrapper.style.left = '-10000px';
        wrapper.style.background = '#fff';
        wrapper.style.width = '320px';

        // Masukkan clone ke wrapper
        wrapper.appendChild(clone);

        // Tambahkan wrapper ke body
        document.body.appendChild(wrapper);

        // Render wrapper menjadi canvas
        const canvas = await html2canvas(wrapper, {
          background: '#fff',
          scale: 2,
          useCORS: true,
          windowWidth: 320
        });

        // Hapus wrapper setelah render selesai
        document.body.removeChild(wrapper);

        // Konversi canvas ke blob (PNG)
        canvas.toBlob(function(blob){

          // Jika blob gagal dibuat
          if (!blob) {
            alert('Gagal membuat gambar');
            return;
          }

          // Buat URL sementara untuk file PNG
          const url = URL.createObjectURL(blob);

          // Buat elemen <a> untuk trigger download
          const a = document.createElement('a');
          a.href = url;
          a.download = 'Struk_<?= h($ord["invoice_no"]) ?>.png';

          // Tambahkan ke body lalu klik
          document.body.appendChild(a);
          a.click();

          // Hapus elemen link
          a.remove();

          // Hapus URL blob setelah 1 detik
          setTimeout(() => URL.revokeObjectURL(url), 1000);

        }, 'image/png', 1.0);

      } catch (err) {

        // Notifikasi jika proses gagal
        alert(
          'Checkout berhasil tapi unduh struk gagal: ' +
          (err?.message || err)
        );

      } finally {

        // Restore tombol ke kondisi awal
        btn.disabled = false;
        btn.innerHTML = original;
      }

    }); // Penutup event click

  })(); // Penutup IIFE
</script>
 </body>
    </html>