<?php 
// Tagline: Halaman struk thermal 58mm khusus karyawan Caffora dengan guard "belum lunas" dan layout siap print.

// public/karyawan/receipt.php                                  // Lokasi file untuk struk karyawan
declare(strict_types=1);                                        // Aktifkan strict typing di PHP
if (session_status() !== PHP_SESSION_ACTIVE) session_start();   // Pastikan session sudah aktif (mulai kalau belum)

require_once __DIR__ . '/../../backend/auth_guard.php';         // Include helper autentikasi/otorisasi
// ⬇⬇⬇ REVISI: karyawan saja
require_login(['karyawan']);                                    // Batasi akses hanya untuk role "karyawan"
require_once __DIR__ . '/../../backend/config.php'; // $conn, BASE_URL, h()  // Ambil config (koneksi DB, konstanta, helper h())

function rp(float $n): string { return 'Rp ' . number_format($n, 0, ',', '.'); } // Helper format rupiah untuk angka float

$orderId = (int)($_GET['order'] ?? 0);                          // Ambil parameter order dari query string, cast ke integer

/* --- ambil data pesanan + item + invoice --- */
$ord   = null;                                                  // Variabel penampung data order utama
$items = [];                                                    // Variabel penampung daftar item order
$inv   = null;                                                  // Variabel penampung data invoice (jika ada)

if ($orderId) {                                                 // Jika ada ID order valid
  // karyawan boleh lihat semua order
  $stmt = $conn->prepare("SELECT * FROM orders WHERE id=? LIMIT 1"); // Siapkan query ambil satu order
  $stmt->bind_param('i', $orderId);                             // Bind parameter ID order
  $stmt->execute();                                             // Eksekusi query
  $ord = $stmt->get_result()?->fetch_assoc();                   // Ambil hasil sebagai array asosiatif
  $stmt->close();                                               // Tutup statement

  if ($ord) {                                                   // Jika order ditemukan
    // item
    $stmt = $conn->prepare("
      SELECT oi.qty, oi.price, m.name AS menu_name
      FROM order_items oi
      LEFT JOIN menu m ON m.id=oi.menu_id
      WHERE oi.order_id=? ORDER BY oi.id
    ");                                                         // Query ambil item order + nama menu
    $stmt->bind_param('i', $orderId);                           // Bind ID order
    $stmt->execute();                                           // Eksekusi query item
    $items = $stmt->get_result()?->fetch_all(MYSQLI_ASSOC) ?? [];// Ambil semua item sebagai array asosiatif
    $stmt->close();                                             // Tutup statement item

    // invoice
    $stmt = $conn->prepare("SELECT amount, issued_at FROM invoices WHERE order_id=? LIMIT 1"); // Query ambil informasi invoice
    $stmt->bind_param('i', $orderId);                          // Bind ID order
    $stmt->execute();                                          // Eksekusi query invoice
    $inv = $stmt->get_result()?->fetch_assoc();                // Ambil satu baris invoice (jika ada)
    $stmt->close();                                            // Tutup statement invoice
  }
}

/* --- guard akses --- */
if (!$ord) {                                                    // Jika order tidak ditemukan
  http_response_code(404);                                      // Set HTTP status 404
  echo 'Order tidak ditemukan';                                 // Tampilkan pesan sederhana
  exit;                                                         // Hentikan script
}

/* siapkan angka supaya aman kalau backend lama belum punya kolom pajak */
$subtotal    = isset($ord['subtotal'])     ? (float)$ord['subtotal']     : (float)($ord['total'] ?? 0); // Subtotal: pakai kolom baru atau fallback total lama
$taxAmount   = isset($ord['tax_amount'])   ? (float)$ord['tax_amount']   : 0.0;                         // Pajak: pakai kolom baru atau 0
$grandTotal  = isset($ord['grand_total'])  ? (float)$ord['grand_total']  : ($subtotal + $taxAmount);    // Grand total: pakai kolom baru atau subtotal + pajak

/* =========================================================
   CASE 1: BELUM LUNAS
   ========================================================= */
if ($ord['payment_status'] !== 'paid') {                        // Jika status pembayaran bukan "paid"
  ?>
  <!doctype html>                                               <!-- Dokumen HTML5 untuk tampilan "struk belum tersedia" -->
  <html lang="id">                                              <!-- Bahasa dokumen Indonesia -->
  <head>
    <meta charset="utf-8">                                      <!-- Encoding UTF-8 -->
    <meta name="viewport" content="width=device-width, initial-scale=1"> <!-- Responsive viewport -->
    <title>Struk belum tersedia — Caffora</title>               <!-- Judul tab halaman -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/> <!-- Bootstrap Icons -->
    <style>
      :root{
        --gold:#FFD54F;                                         /* Warna emas brand */
        --brown:#4B3F36;                                       /* Warna coklat brand */
        --ink:#111827;                                         /* Warna teks utama */
        --muted:#6b7280;                                       /* Warna teks sekunder */
        --bg-page:#fafafa;                                     /* Latar belakang halaman */
        --bg-card:#ffffff;                                     /* Latar belakang kartu */
      }
      *{
        box-sizing:border-box;                                 /* Gunakan border-box untuk layout */
        font-family: "Poppins", system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; /* Font utama */
      }
      html, body {
        margin:0;                                              /* Hilangkan margin default */
        padding:0;                                             /* Hilangkan padding default */
        background:var(--bg-page);                             /* Background abu lembut */
        color:var(--ink);                                      /* Warna teks */
      }
      .topbar{
        position:sticky;                                       /* Menempel di atas saat scroll */
        top:0;
        z-index:20;
        background:#fff;                                       /* Background putih untuk top bar */
        border-bottom:1px solid rgba(0,0,0,.04);               /* Garis bawah tipis */
      }
      .topbar .inner{
        max-width:1200px;                                      /* Lebar maksimal kontainer atas */
        margin:0 auto;                                         /* Tengah secara horizontal */
        padding:12px 16px;                                     /* Padding horizontal kecil */
        min-height:52px;                                       /* Tinggi minimal bar */
        display:flex;                                          /* Flex untuk align item */
        align-items:center;                                    /* Center vertikal */
        gap:10px;
      }
      .back-link{
        display:inline-flex;                                   /* Tampilkan arrow + teks sejajar */
        align-items:center;
        gap:8px;
        color:var(--ink);
        text-decoration:none;                                  /* Hilangkan underline */
        font-weight:600;                                       /* Tebalkan teks */
        font-size:.95rem;
      }
      .page-wrapper{
        max-width:1200px;                                      /* Lebar konten tengah */
        margin:14px auto 40px;                                 /* Margin atas/bawah + center */
        padding:0 16px;                                        /* Padding samping */
      }
      .status-card{
        background:var(--bg-card);                             /* Kartu putih */
        border-radius:14px;                                    /* Sudut kartu melengkung */
        border:1px solid rgba(0,0,0,.03);                      /* Border tipis halus */
        box-shadow:0 6px 18px rgba(0,0,0,.03);                 /* Bayangan lembut */
        padding:18px 16px 20px;                                /* Spasi internal kartu */
      }
      @media (min-width:768px){
        .topbar .inner{ padding:12px 24px; }                   /* Sedikit lebih lebar di tablet/desktop */
        .page-wrapper{ padding:0 24px; }                       /* Tambah padding samping */
      }
    </style>
  </head>
  <body>
    <div class="topbar">                                       <!-- Bar atas dengan tombol kembali -->
      <div class="inner">
        <a class="back-link" href="<?= h(BASE_URL) ?>/public/karyawan/orders.php">
          <i class="bi bi-arrow-left"></i>                     <!-- Ikon panah kembali -->
          <span>Kembali</span>                                 <!-- Teks link kembali -->
        </a>
      </div>
    </div>

    <div class="page-wrapper">                                 <!-- Pembungkus konten halaman -->
      <section class="status-card">                            <!-- Kartu status struk belum tersedia -->
        <h2 style="margin:0 0 10px;font-weight:600;">Struk belum tersedia</h2> <!-- Judul status -->
        <p style="color:var(--muted);margin:0 0 6px">
          Struk hanya bisa dicetak kalau pembayaran <b>lunas</b>. <!-- Info bahwa hanya order lunas yang bisa cetak -->
        </p>
        <p style="margin:0;">
          Invoice: <b><?= h($ord['invoice_no']) ?></b>          <!-- Tampilkan nomor invoice untuk referensi -->
        </p>
      </section>
    </div>
  </body>
  </html>
  <?php
  exit;                                                        // Hentikan eksekusi, jangan render struk lengkap
}

/* =========================================================
   CASE 2: LUNAS → STRUK LENGKAP
   ========================================================= */
?>
<!doctype html>                                                <!-- Dokumen HTML5 untuk struk lengkap -->
<html lang="id">                                               <!-- Bahasa Indonesia -->
<head>
  <meta charset="utf-8">                                       <!-- Encoding UTF-8 -->
  <title>Struk — <?= h($ord['invoice_no']) ?> | Caffora</title> <!-- Judul tab termasuk nomor invoice -->
  <meta name="viewport" content="width=device-width, initial-scale=1"> <!-- Responsive viewport -->

  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"> <!-- Bootstrap Icons -->

  <style>
    :root{
      --ink:#111827;                                           /* Warna teks utama */
      --muted:#6b7280;                                         /* Warna teks sekunder */
      --line:#e5e7eb;                                          /* Warna garis/border lembut */
      --bg-page:#fafafa;                                      /* Background halaman */
      --bg-card:#ffffff;                                      /* Background kertas struk */
      --gold:#FFD54F;                                         /* Warna tombol print */
      --brown:#4B3F36;                                        /* Warna teks tombol/brand */
    }
    *{
      font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; /* Font default halaman */
      box-sizing:border-box;                                /* Pastikan layout pakai border-box */
    }
    html,body{
      background:var(--bg-page);                            /* Latar belakang abu lembut */
      color:var(--ink);                                     /* Warna teks */
      margin:0;
      padding:0;
    }
    .topbar{
      position:sticky;                                      /* Bar atas nempel saat scroll */
      top:0;
      background:#fff;                                      /* Background putih */
      border-bottom:1px solid rgba(0,0,0,.04);              /* Garis bawah tipis */
      z-index:20;                                           /* Di atas konten lain */
    }
    .topbar .inner{
      max-width:1200px;                                     /* Lebar maksimum kontainer bar */
      margin:0 auto;                                        /* Tengah */
      padding:12px 16px;                                    /* Padding kiri-kanan */
      min-height:52px;                                      /* Tinggi minimum bar */
      display:flex;                                         /* Flex untuk layout kanan-kiri */
      align-items:center;                                   /* Center vertikal */
      gap:10px;
      justify-content:space-between;                        /* Kembali di kiri, print di kanan */
    }
    .back-link{
      display:inline-flex;                                  /* Icon + teks sebaris */
      align-items:center;
      gap:8px;
      color:var(--ink);
      text-decoration:none;                                 /* Hilangkan underline */
      font-weight:600;
      font-size:.95rem;
      line-height:1.3;
      font-family: Arial, Helvetica, sans-serif !important; /* Pakai Arial untuk konsisten */
    }
    .btn-print {
      background-color: var(--gold);                        /* Tombol print warna emas */
      color: var(--brown) !important;                       /* Teks coklat gelap */
      border: 0;
      border-radius: 12px;                                  /* Sudut agak bulat */
      padding: 10px 14px;                                   /* Spasi di dalam tombol */
      display: inline-flex;                                 /* Icon + teks horizontal */
      align-items: center;
      justify-content: center;
      gap: 8px;
      text-decoration: none !important;                     /* Hilangkan underline jika jadi link */
      box-shadow: none;
      cursor: pointer;                                      /* Pointer saat hover */
      white-space: nowrap;                                  /* Jangan pecah baris */
    }
    .btn-print,
    .btn-print * {
      font-family: Arial, sans-serif !important;            /* Font tombol dan isi Arial */
      font-weight: 600;
      font-size: 13.3px;
      line-height: 1.2;
      color: var(--brown) !important;                       /* Warna teks tetap coklat */
    }
    .page-wrapper{
      max-width: 1200px;                                   /* Lebar maksimum area tengah */
      margin: 14px auto 40px;                              /* Margin atas kecil, bawah agak besar */
      padding: 0 16px;                                     /* Padding kiri-kanan */
      display:flex;
      justify-content:center;                              /* Kertas struk ditengah */
    }
    .paper{
      background: var(--bg-card);                          /* Warna kertas putih */
      width: 100%;
      max-width: 320px;                                    /* Lebar maksimum struk (simulasi 58mm+margin) */
      box-shadow: 0 2px 6px rgba(0,0,0,.05);               /* Bayangan lembut */
      padding: 18px 20px;                                  /* Spasi isi kertas */
      border: 1px solid var(--line);                       /* Garis pinggir halus */
    }
    .paper,
    .paper * {
      font-family: Arial, sans-serif !important;           /* Isi struk pakai font sederhana */
      color: #4b5563 !important;                           /* Teks abu gelap */
    }
    .brand{ font-weight: 600; font-size: 16px; margin-bottom: 4px; }  /* Nama brand di atas struk */
    .meta{
      display:flex;                                       /* Info invoice & customer sejajar */
      justify-content:space-between;
      gap:10px;
      font-size:.8rem;
      line-height:1.4;
    }
    .to{ text-align:right; }                               /* Info customer di kanan */
    .rule{
      border-top: 2px dashed #aaa;                         /* Garis putus-putus pemisah */
      margin: 10px 0;
      opacity: .8;
    }
    .head-row{
      display:flex;                                       /* Header kolom item/subtotal */
      justify-content:space-between;
      font-weight:500;
      padding:4px 0 6px;
      font-size:.8rem;
    }
    .item{
      display:grid;                                       /* Layout item: nama di kiri, subtotal di kanan */
      grid-template-columns:1fr auto;
      gap:12px;
      align-items:flex-start;
      padding:8px 0;
    }
    .item-name{ font-weight:500; font-size:.85rem; }       /* Nama menu sedikit lebih tebal */
    .item-sub{ font-size:.75rem; line-height:1.4; margin-top:1px; } /* Baris info qty × harga */
    .item-subtotal{ font-weight:500; white-space:nowrap; font-size:.8rem; text-align:right; } /* Subtotal per item */
    .row{
      display:flex;                                       /* Baris ringkasan subtotal/pajak/total */
      justify-content:space-between;
      padding:6px 0;
      font-size:.8rem;
    }
    .row.total{
      font-weight:600;
      font-size:.9rem;
      border-top:2px dotted var(--line);                  /* Garis titik-titik sebelum total */
      margin-top:4px;
      padding-top:8px;
    }
    .status-line{
      display:flex;
      flex-wrap:wrap;                                     /* Status lunas + metode pembayaran */
      justify-content:space-between;
      gap:8px 12px;
      padding-top:8px;
      font-size:.8rem;
    }
    .pill{
      padding:0;
      border:none !important;
      background:transparent !important;
      font-weight:700 !important;
      letter-spacing:.18rem;                              /* Spasi huruf besar "L U N A S" */
      font-size:.75rem;
    }
    .footer-note{
      font-size:.7rem;                                    /* Catatan kecil di bawah */
      line-height:1.5;
    }
    @media (min-width:768px){
      .topbar .inner,
      .page-wrapper{ padding:12px 24px; }                 /* Tambah padding di layar besar */
      .page-wrapper{ margin:16px auto 50px; }             /* Sedikit ubah margin atas/bawah */
    }

    /* PRINT 58mm DENGAN MARGIN */
    @media print {
      @page {
        size: 58mm auto;                                  /* Set ukuran halaman cetak 58mm */
        margin: 3mm;                                      /* Margin kecil di sekeliling */
      }
      html, body {
        width: 58mm;                                      /* Lebar halaman sama dengan roll kertas */
        background: #fff !important;
        margin: 0;
        padding: 0;
      }
      .topbar { display: none !important; }               /* Sembunyikan bar atas saat print */
      .page-wrapper {
        margin: 0;
        padding: 0;
        display: block;
        width: 58mm;
      }
      .paper {
        width: calc(58mm - 6mm);                          /* Sesuaikan lebar kertas dengan margin */
        max-width: calc(58mm - 6mm);
        border: 0;
        box-shadow: none;
        padding: 6px 6px 10px;                            /* Padding cetak versi mini */
      }
      .brand { font-size: 13px; }                         /* Perkecil font untuk print */
      .meta { font-size: 10px; }
      .head-row { font-size: 10px; }
      .item-name { font-size: 10px; }
      .item-sub { font-size: 9px; }
      .item-subtotal { font-size: 10px; }
      .row { font-size: 10px; }
      .row.total { font-size: 11px; }
      .footer-note { font-size: 9px; }
      body::before,
      body::after {
        display: none !important;                         /* Pastikan pseudo-element tidak ikut tercetak */
      }
    }
  </style>
</head>
<body>

  <div class="topbar">                                      <!-- Bar atas berisi tombol kembali dan print -->
    <div class="inner">
      <a class="back-link" href="<?= h(BASE_URL) ?>/public/karyawan/orders.php">
        <i class="bi bi-arrow-left"></i>                    <!-- Ikon kembali -->
        <span>Kembali</span>                                <!-- Teks link kembali ke daftar orders -->
      </a>
      <button id="btnPrint" class="btn-print" type="button"> <!-- Tombol untuk memicu window.print() -->
        <svg viewBox="0 0 24 24" fill="currentColor">       <!-- Ikon printer sederhana (inline SVG) -->
          <path d="M6 9V3h12v6h1a3 3 0 013 3v4h-4v4H6v-4H2v-4a3 3 0 013-3h1zm2 0h8V5H8zm8 10v-4H8v4zM6 13h2v2H6z"></path>
        </svg>
        <span>Print</span>                                  <!-- Label tombol Print -->
      </button>
    </div>
  </div>

  <div class="page-wrapper">                                <!-- Wrapper untuk memposisikan struk di tengah -->
    <main class="paper" id="receiptContent">                <!-- Elemen utama struk (kertas) -->
      <div class="brand">Caffora</div>                      <!-- Nama brand di atas struk -->
      <div class="meta">                                    <!-- Info invoice & customer -->
        <div>
          <div>Invoice: <span style="font-weight:500"><?= h($ord['invoice_no']) ?></span></div> <!-- Nomor invoice -->
          <div>Tanggal: <?= h(date('d M Y H:i', strtotime($ord['created_at']))) ?></div>         <!-- Tanggal & jam order -->
        </div>
        <div class="to">
          <div style="color:#9ca3af">Customer:</div>        <!-- Label "Customer" abu-abu -->
          <div style="font-weight:500"><?= h($ord['customer_name']) ?></div>                     <!-- Nama customer -->
          <div>
            <?= h($ord['service_type']==='dine_in' ? 'Dine In' : 'Take Away') ?>                 <!-- Jenis layanan Dine In / Take Away -->
            <?= $ord['table_no'] ? ', Meja '.h($ord['table_no']) : '' ?>                         <!-- Nomor meja jika ada -->
          </div>
        </div>
      </div>

      <div class="rule"></div>                              <!-- Garis putus-putus pemisah header dan item -->

      <div class="head-row">
        <div>Item</div>                                     <!-- Header kolom kiri: nama item -->
        <div>Subtotal</div>                                 <!-- Header kolom kanan: subtotal per item -->
      </div>

      <div class="items">                                   <!-- Daftar item pesanan -->
        <?php foreach($items as $it):
          $sub = (float)$it['qty'] * (float)$it['price']; ?> <!-- Hitung subtotal per item: qty × price -->
          <div class="item">
            <div>
              <div class="item-name"><?= h($it['menu_name'] ?? 'Menu') ?></div>                  <!-- Nama menu (fallback "Menu") -->
              <div class="item-sub">
                Qty: <?= (int)$it['qty'] ?> × <?= rp((float)$it['price']) ?>                     <!-- Tampilkan qty × harga satuan -->
              </div>
            </div>
            <div class="item-subtotal"><?= rp($sub) ?></div>                                     <!-- Subtotal item dalam rupiah -->
          </div>
        <?php endforeach; ?>
      </div>

      <!-- pakai subtotal & pajak baru -->
      <div class="row" style="margin-top:6px">
        <div>Subtotal</div>                                 <!-- Label subtotal -->
        <div class="muted"><?= rp($subtotal) ?></div>       <!-- Nilai subtotal (sebelum pajak) -->
      </div>
      <div class="row">
        <div>Pajak 11%</div>                                 <!-- Label pajak 11% -->
        <div class="muted"><?= rp($taxAmount) ?></div>       <!-- Nilai pajak yang dipotong -->
      </div>
      <div class="row total">
        <div>Total</div>                                     <!-- Label total akhir -->
        <div><?= rp($grandTotal) ?></div>                    <!-- Grand total (subtotal + pajak) -->
      </div>

      <div class="status-line">
        <div class="pill">L U N A S</div>                    <!-- Badge teks besar "LUNAS" -->
        <div>
          Metode Pembayaran:
          <b><?= h(strtoupper(str_replace('_',' ',$ord['payment_method'] ?? '-'))) ?></b> <!-- Metode bayar dalam huruf besar -->
        </div>
      </div>

      <div class="rule"></div>                              <!-- Garis putus-putus sebelum catatan footer -->

      <div class="footer-note">
        <?php if ($inv): ?>                                  <!-- Jika data invoice tersedia -->
          Tagihan: <b><?= rp((float)$inv['amount']) ?></b><br>                                   <!-- Nominal tagihan invoice -->
          Diterbitkan: <?= h(date('d M Y H:i', strtotime($inv['issued_at']))) ?><br>             <!-- Waktu invoice diterbitkan -->
        <?php endif; ?>
        * Harga sudah termasuk PPN 11%. Terima kasih sudah belanja di <b>Caffora</b>.            <!-- Catatan bahwa harga termasuk PPN -->
      </div>
    </main>
  </div>

  <script>
    document.getElementById('btnPrint')?.addEventListener('click', function () { // Saat tombol print diklik
      window.print();                                         // Panggil dialog print browser
    });
  </script>
</body>
</html>
