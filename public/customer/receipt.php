<?php
// File  : public/customer/receipt.php
// Descr : Halaman struk pesanan customer (download sebagai gambar)
// Note  : Mengikuti code standard (komentar di atas, kurung kurawal style #2)

declare(strict_types=1); // Mengaktifkan strict types untuk PHP

// Start session jika belum aktif
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

// Proteksi halaman: hanya role "customer" yang boleh akses
require_once __DIR__ . '/../../backend/auth_guard.php';
require_login(['customer']);

// Config berisi koneksi DB ($conn), BASE_URL, fungsi helper h()
require_once __DIR__ . '/../../backend/config.php';

/**
 * Format angka ke Rupiah.
 *
 * @param float $n  angka input
 * @return string   format "Rp 10.000"
 */
function rp(float $n): string
{
  // number_format: 0 desimal, koma sebagai pemisah desimal, titik pemisah ribuan
  return 'Rp ' . number_format($n, 0, ',', '.');
}

// ----------------------------------------------------------
// Ambil parameter & user id dari sesi
// ----------------------------------------------------------

// ID order dari query string ?order=
$orderId = (int)($_GET['order'] ?? 0);

// ID user yang sedang login dari session
$userId  = (int)($_SESSION['user_id'] ?? 0);

// ----------------------------------------------------------
// Ambil data pesanan + item + invoice
// ----------------------------------------------------------

// $ord  : data satu baris order
$ord   = null;

// $items: daftar item yang ada di order
$items = [];

// $inv  : data invoice terkait order (jika ada)
$inv   = null;

// Hanya query database jika orderId dan userId valid (>0)
if ($orderId && $userId) {
  // Ambil order utama (validasi sekaligus bahwa order milik user ini)
  $stmt = $conn->prepare(
    "SELECT * 
     FROM orders 
     WHERE id = ? 
       AND user_id = ? 
     LIMIT 1"
  );
  // Binding parameter: id order & id user (tipe integer)
  $stmt->bind_param('ii', $orderId, $userId);
  // Eksekusi query
  $stmt->execute();
  // Ambil hasil sebagai array asosiatif satu baris
  $ord = $stmt->get_result()?->fetch_assoc();
  // Tutup statement
  $stmt->close();

  // Jika order ditemukan, lanjut ambil item & invoice
  if ($ord) {
    // Ambil item order (join ke tabel menu untuk nama menu)
    $stmt = $conn->prepare(
      "SELECT 
         oi.qty, 
         oi.price, 
         m.name AS menu_name
       FROM order_items oi
       LEFT JOIN menu m ON m.id = oi.menu_id
       WHERE oi.order_id = ?
       ORDER BY oi.id"
    );
    // Binding id order
    $stmt->bind_param('i', $orderId);
    // Eksekusi query
    $stmt->execute();
    // Ambil semua hasil sebagai array asosiatif
    $items = $stmt->get_result()?->fetch_all(MYSQLI_ASSOC) ?? [];
    // Tutup statement
    $stmt->close();

    // Ambil invoice (jika ada, limit 1)
    $stmt = $conn->prepare(
      "SELECT 
         amount, 
         issued_at 
       FROM invoices 
       WHERE order_id = ? 
       LIMIT 1"
    );
    // Binding id order
    $stmt->bind_param('i', $orderId);
    // Eksekusi query
    $stmt->execute();
    // Ambil satu baris invoice
    $inv = $stmt->get_result()?->fetch_assoc();
    // Tutup statement
    $stmt->close();
  }
}

// ----------------------------------------------------------
// Guard akses: jika order tidak ditemukan / bukan milik user
// ----------------------------------------------------------
if (!$ord) {
  // Set HTTP status 403 Forbidden
  http_response_code(403);
  // Pesan teks sederhana
  echo 'Tidak boleh mengakses';
  // Hentikan eksekusi script
  exit;
}

/* =========================================================
   CASE 1: STRUK BELUM BISA DITAMPILKAN (BELUM LUNAS)
   ========================================================= */
// Jika status pembayaran bukan "paid", tampilkan halaman info
if ($ord['payment_status'] !== 'paid') {
  ?>
  <!doctype html>
  <html lang="id">
  <head>
    <!-- Set karakter encoding -->
    <meta charset="utf-8">
    <!-- Viewport untuk mobile responsive -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Title tab browser -->
    <title>Struk belum tersedia — Caffora</title>

    <!-- Bootstrap Icons untuk ikon panah kembali -->
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
      rel="stylesheet"
    >

    <style>
      /* Variabel warna & style dasar */
      :root {
        --gold:    #FFD54F;
        --brown:   #4B3F36;
        --line:    #e5e7eb;
        --ink:     #111827;
        --muted:   #6b7280;
        --bg-page: #fafafa;
        --bg-card: #ffffff;
      }

      /* Reset dan font global */
      * {
        box-sizing: border-box;
        font-family:
          "Poppins",
          system-ui,
          -apple-system,
          "Segoe UI",
          Roboto,
          Arial,
          sans-serif;
      }

      /* Style untuk elemen html & body */
      html,
      body {
        margin: 0;
        padding: 0;
        background: var(--bg-page);
        color: var(--ink);
        -webkit-font-smoothing: antialiased;
      }

      /* ===== Topbar seragam ===== */
      .topbar {
        position: sticky;                    /* Menempel di atas saat scroll */
        top: 0;
        z-index: 20;                         /* Di atas konten lain */
        background: #fff;
        border-bottom: 1px solid rgba(0, 0, 0, .04);
      }

      .topbar .inner {
        max-width: 1200px;                   /* Lebar maksimal area topbar */
        margin: 0 auto;                      /* Ditengah secara horizontal */
        padding: 12px 16px;                  /* Padding dalam topbar */
        min-height: 52px;                    /* Tinggi minimum topbar */
        display: flex;                       /* Layout flex */
        align-items: center;                 /* Vertikal center */
        gap: 10px;                           /* Jarak antar elemen */
      }

      /* Kontainer utama konten status */
      .page-wrapper {
        max-width: 1200px;                   /* Batas lebar konten */
        margin: 14px auto 40px;              /* Atas 14px, bawah 40px, center */
        padding: 0 16px;                     /* Padding kiri-kanan */
      }

      /* Kartu status "struk belum tersedia" */
      .status-card {
        background: var(--bg-card);          /* Putih */
        border-radius: 14px;                 /* Sudut membulat */
        border: 1px solid rgba(0, 0, 0, .03);
        box-shadow: 0 6px 18px rgba(0, 0, 0, .03);
        padding: 18px 16px 20px;             /* Padding isi kartu */
      }

      /* Judul status */
      .status-title {
        margin: 0 0 10px;                    /* Margin bawah 10px */
        font-weight: 600;
        font-size: 1.05rem;
      }

      /* Deskripsi status */
      .status-desc {
        margin: 0 0 10px;
        color: var(--muted);
      }

      /* Responsive untuk layar >= 768px (tablet/desktop) */
      @media (min-width: 768px) {
        .topbar .inner {
          padding: 12px 24px;                /* Lebih lebar di desktop */
        }

        .page-wrapper {
          padding: 0 24px;                   /* Padding kanan kiri lebih besar */
        }

        .status-card {
          padding: 20px 20px 22px;           /* Sedikit lebih lapang */
        }
      }
    </style>
  </head>
  <body>

    <!-- Topbar dengan tombol kembali ke riwayat -->
    <div class="topbar">
      <div class="inner">
        <a
          class="back-link"
          href="<?= h(BASE_URL) ?>/public/customer/history.php"
        >
          <i class="bi bi-arrow-left"></i>
          <span>Kembali</span>
        </a>
      </div>
    </div>

    <!-- Konten kartu status -->
    <div class="page-wrapper">
      <section class="status-card">
        <h2 class="status-title">Struk belum tersedia</h2>

        <p class="status-desc">
          Struk hanya dapat diunduh setelah pembayaran <b>lunas</b>.
        </p>

        <p class="status-invoice">
          Invoice: <b><?= h($ord['invoice_no']) ?></b>
        </p>
      </section>
    </div>

  </body>
  </html>
  <?php
  // Hentikan script setelah menampilkan halaman case 1
  exit;
}

/* =========================================================
   CASE 2: LUNAS → STRUK LENGKAP
   ========================================================= */

/*
 * Siapkan angka supaya aman kalau backend lama belum punya
 * kolom subtotal / tax_amount / grand_total.
 */

// Hitung subtotal (fallback ke total jika kolom tidak tersedia)
$subtotal   = isset($ord['subtotal'])
  ? (float)$ord['subtotal']
  : (float)($ord['total'] ?? 0);

// Nilai pajak 11% (0 jika kolom tidak ada)
$taxAmount  = isset($ord['tax_amount'])
  ? (float)$ord['tax_amount']
  : 0.0;

// Grand total (fallback subtotal + pajak)
$grandTotal = isset($ord['grand_total'])
  ? (float)$ord['grand_total']
  : ($subtotal + $taxAmount);
?>
<!doctype html>
<html lang="id">
<head>
  <!-- Meta charset & title -->
  <meta charset="utf-8">
  <title>Struk — <?= h($ord['invoice_no']) ?> | Caffora</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap Icons untuk ikon topbar -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    rel="stylesheet"
  >

  <script>
    // Muat script eksternal (html2canvas) secara dinamis menggunakan Promise
    function loadScript(src) {
      return new Promise((res, rej) => {
        // Buat elemen <script>
        const s = document.createElement('script');
        // Set sumber file JS
        s.src = src;
        // Pastikan non-blocking
        s.async = true;
        // Izinkan CORS
        s.crossOrigin = 'anonymous';
        // Jika berhasil dimuat
        s.onload = res;
        // Jika gagal dimuat
        s.onerror = () => rej(new Error('Gagal memuat ' + src));
        // Tambahkan ke <head>
        document.head.appendChild(s);
      });
    }

    // Pastikan html2canvas tersedia (CDN utama + fallback bila error)
    async function ensureHtml2Canvas() {
      // Jika sudah ada di window, langsung return
      if (window.html2canvas) {
        return;
      }

      try {
        // Coba load dari CDN jsDelivr
        await loadScript(
          'https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js'
        );
      } catch (e) {
        // Jika gagal, fallback ke unpkg
        await loadScript(
          'https://unpkg.com/html2canvas@1.4.1/dist/html2canvas.min.js'
        );
      }
    }
  </script>

  <style>
    /* Variabel warna dan style global */
    :root {
      --ink:      #111827;
      --muted:    #6b7280;
      --line:     #e5e7eb;
      --bg-page:  #fafafa;
      --bg-card:  #ffffff;
      --gold:     #FFD54F;
      --brown:    #4B3F36;
    }

    /* Global reset & font */
    * {
      font-family:
        Inter,
        system-ui,
        -apple-system,
        "Segoe UI",
        Roboto,
        Arial,
        sans-serif;
      box-sizing: border-box;
    }

    /* Style dasar untuk html & body */
    html,
    body {
      background: var(--bg-page);
      color: var(--ink);
      margin: 0;
      padding: 0;
      -webkit-font-smoothing: antialiased;
    }

    /* ===== TOPBAR SERAGAM ===== */
    .topbar {
      position: sticky;                  /* Menempel di atas saat scroll */
      top: 0;
      background: #fff;
      border-bottom: 1px solid rgba(0, 0, 0, .04);
      z-index: 20;                       /* Di atas konten lainnya */
    }

    .topbar .inner {
      max-width: 1200px;                 /* Lebar optimal konten */
      margin: 0 auto;                    /* Tengah horizontal */
      padding: 12px 16px;                /* Padding dalam topbar */
      min-height: 52px;                  /* Tinggi minimum */
      display: flex;                     /* Layout flex */
      align-items: center;               /* Vertikal tengah */
      gap: 10px;                         /* Jarak antar elemen */
      justify-content: space-between;    /* Kiri: back, kanan: download */
    }

    /* Tombol back di topbar */
    .back-link {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      color: var(--brown);
      text-decoration: none;
      font-weight: 600;
      font-size: 16px;
      line-height: 1.3;
    }

    /* Ikon panah di tombol back */
    .back-link .bi {
      font-size: 18px !important;
      width: 18px;
      height: 18px;
      line-height: 1;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    /* Tombol download (style tetap sesuai desain kamu) */
    .btn-download {
      background-color: var(--gold);
      color: var(--brown) !important;
      border: 0;
      border-radius: 12px;
      padding: 10px 14px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      text-decoration: none !important;
      box-shadow: none;
      cursor: pointer;
      white-space: nowrap;
    }

    /* Override font untuk isi tombol download */
    .btn-download,
    .btn-download * {
      font-family: Arial, sans-serif !important;
      font-weight: 600;
      font-size: 13.3px;
      line-height: 1.2;
      color: var(--brown) !important;
    }

    /* ===== KONTEN STRUK ===== */

    /* Wrapper utama struk di tengah halaman */
    .page-wrapper {
      max-width: 1200px;
      margin: 14px auto 40px;       /* Atas 14px, bawah 40px */
      padding: 0 16px;
      display: flex;
      justify-content: center;      /* Posisikan kertas struk di tengah */
    }

    /* "Kertas" struk yang akan di-screenshot */
    .paper {
      background: var(--bg-card);
      width: 100%;
      max-width: 320px;             /* Lebar struk seperti thermal */
      box-shadow: 0 2px 6px rgba(0, 0, 0, .05);
      padding: 18px 20px;
      border: 1px solid var(--line);
    }

    /* Gunakan font Arial di dalam kertas struk */
    .paper,
    .paper * {
      font-family: Arial, sans-serif !important;
      color: #4b5563 !important;
    }

    /* Nama brand di atas struk */
    .brand {
      font-weight: 600;
      font-size: 16px;
      margin-bottom: 4px;
    }

    /* Baris meta: invoice + tanggal + penerima */
    .meta {
      display: flex;
      justify-content: space-between;
      gap: 10px;
      font-size: .8rem;
      line-height: 1.4;
    }

    /* Bagian kanan meta rata kanan (Kepada:) */
    .to {
      text-align: right;
    }

    /* Garis putus-putus pemisah section */
    .rule {
      border-top: 2px dashed #aaa;
      margin: 10px 0;
      opacity: .8;
    }

    /* Header kolom "Item" dan "Subtotal" */
    .head-row {
      display: flex;
      justify-content: space-between;
      font-weight: 500;
      padding: 4px 0 6px;
      font-size: .8rem;
    }

    /* Satu baris item di daftar pesanan */
    .item {
      display: grid;
      grid-template-columns: 1fr auto; /* Kolom kiri isi, kolom kanan subtotal */
      gap: 12px;
      align-items: flex-start;
      padding: 8px 0;
    }

    /* Nama menu */
    .item-name {
      font-weight: 500;
      font-size: .85rem;
    }

    /* Detail Qty × Harga */
    .item-sub {
      font-size: .75rem;
      line-height: 1.4;
      margin-top: 1px;
    }

    /* Subtotal per item di sisi kanan */
    .item-subtotal {
      font-weight: 500;
      white-space: nowrap;
      font-size: .8rem;
      text-align: right;
    }

    /* Baris ringkasan subtotal / pajak / total */
    .row {
      display: flex;
      justify-content: space-between;
      padding: 6px 0;
      font-size: .8rem;
    }

    /* Baris total (dibuat lebih tebal dan ada border atas) */
    .row.total {
      font-weight: 600;
      font-size: .9rem;
      border-top: 2px dotted var(--line);
      margin-top: 4px;
      padding-top: 8px;
    }

    /* Baris status LUNAS + metode pembayaran */
    .status-line {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      gap: 8px 12px;
      padding-top: 8px;
      font-size: .8rem;
    }

    /* Badge "L U N A S" */
    .pill {
      padding: 0;
      border: none !important;
      background: transparent !important;
      font-weight: 700 !important;
      letter-spacing: .18rem;
      font-size: .75rem;
    }

    /* Catatan kaki di bawah struk */
    .footer-note {
      font-size: .7rem;
      line-height: 1.5;
    }

    /* Responsif untuk tablet/desktop */
    @media (min-width: 768px) {
      .topbar .inner,
      .page-wrapper {
        padding: 12px 24px;
      }

      .page-wrapper {
        margin: 16px auto 50px;
      }
    }

    /* Animasi spin untuk indikator loading tombol download */
    @keyframes spin {
      from { transform: rotate(0); }
      to   { transform: rotate(360deg); }
    }

    /* Lingkaran kecil berputar */
    .spin {
      width: 14px;
      height: 14px;
      border: 2px solid currentColor;
      border-right-color: transparent;
      border-radius: 50%;
      display: inline-block;
      animation: spin .7s linear infinite;
    }

    /* Style khusus saat di-print (CTRL+P) */
    @media print {
      .topbar {
        display: none !important;   /* Sembunyikan topbar ketika print */
      }

      body {
        background: #fff;
      }

      .page-wrapper {
        margin: 0;
        padding: 0;
      }

      .paper {
        box-shadow: none;
        border: 0;
        max-width: 100%;
      }
    }
  </style>
</head>
<body>

  <!-- Topbar dengan tombol kembali & download -->
  <div class="topbar">
    <div class="inner">
      <!-- Tombol kembali ke halaman riwayat -->
      <a
        class="back-link"
        href="<?= h(BASE_URL) ?>/public/customer/history.php"
      >
        <i class="bi bi-arrow-left chev"></i>
        <span>Kembali</span>
      </a>

      <!-- Tombol untuk mengunduh struk sebagai gambar -->
      <button id="btnDownload" class="btn-download">
        <!-- Ikon download berbasis SVG -->
        <svg viewBox="0 0 24 24" fill="currentColor">
          <path
            d="M12 3a1 1 0 011 1v8.586l2.293-2.293a1 1 0 111.414 1.414l-4.001 4a1 1 0 01-1.414 0l-4.001-4a1 1 0 111.414-1.414L11 12.586V4a1 1 0 011-1z"
          ></path>
          <path
            d="M5 19a1 1 0 011-1h12a1 1 0 110 2H6a1 1 0 01-1-1z"
          ></path>
        </svg>
        <span>Download</span>
      </button>
    </div>
  </div>

  <!-- Konten struk (yang nanti di-capture jadi gambar PNG) -->
  <div class="page-wrapper">
    <main class="paper" id="receiptContent">
      <!-- Nama brand di bagian atas struk -->
      <div class="brand">Caffora</div>

      <!-- Informasi invoice & penerima -->
      <div class="meta">
        <div>
          <!-- Nomor invoice -->
          <div>
            Invoice:
            <span style="font-weight:500">
              <?= h($ord['invoice_no']) ?>
            </span>
          </div>
          <!-- Tanggal dan jam order dibuat -->
          <div>
            Tanggal:
            <?= h(date('d M Y H:i', strtotime($ord['created_at']))) ?>
          </div>
        </div>

        <!-- Bagian "Kepada" di sisi kanan -->
        <div class="to">
          <div style="color:#9ca3af">Kepada:</div>
          <div style="font-weight:500">
            <?= h($ord['customer_name']) ?>
          </div>
          <div>
            <!-- Tipe layanan (Dine in / Take away) -->
            <?= h(
              $ord['service_type'] === 'dine_in'
                ? 'Dine In'
                : 'Take Away'
            ) ?>
            <!-- Nomor meja jika ada -->
            <?= $ord['table_no']
              ? ', Meja ' . h($ord['table_no'])
              : '' ?>
          </div>
        </div>
      </div>

      <!-- Garis putus-putus pemisah header & detail item -->
      <div class="rule"></div>

      <!-- Header kolom item & subtotal -->
      <div class="head-row">
        <div>Item</div>
        <div>Subtotal</div>
      </div>

      <!-- Daftar item pesanan -->
      <div class="items">
        <?php foreach ($items as $it): ?>
          <?php
          // Hitung subtotal per item (qty × price)
          $sub = (float)$it['qty'] * (float)$it['price'];
          ?>
          <div class="item">
            <div>
              <!-- Nama menu -->
              <div class="item-name">
                <?= h($it['menu_name'] ?? 'Menu') ?>
              </div>
              <!-- Detail jumlah & harga per item -->
              <div class="item-sub">
                Qty: <?= (int)$it['qty'] ?>
                × <?= rp((float)$it['price']) ?>
              </div>
            </div>
            <!-- Subtotal rupiah per item -->
            <div class="item-subtotal">
              <?= rp($sub) ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Ringkasan total sesuai halaman checkout -->
      <div class="row" style="margin-top:6px">
        <div>Subtotal</div>
        <!-- Subtotal seluruh item -->
        <div class="muted"><?= rp($subtotal) ?></div>
      </div>

      <div class="row">
        <div>Pajak 11%</div>
        <!-- Nilai pajak -->
        <div class="muted"><?= rp($taxAmount) ?></div>
      </div>

      <div class="row total">
        <div>Total</div>
        <!-- Grand total yang harus dibayar -->
        <div><?= rp($grandTotal) ?></div>
      </div>

      <!-- Status pembayaran dan metode bayar -->
      <div class="status-line">
        <!-- Badge LUNAS -->
        <div class="pill">L U N A S</div>

        <!-- Informasi metode pembayaran -->
        <div>
          Metode Pembayaran:
          <b>
            <?= h(
              strtoupper(
                str_replace('_', ' ', $ord['payment_method'] ?? '-')
              )
            ) ?>
          </b>
        </div>
      </div>

      <!-- Garis pemisah sebelum catatan -->
      <div class="rule"></div>

      <!-- Catatan invoice & info pajak -->
      <div class="footer-note">
        <?php if ($inv): ?>
          <!-- Nominal tagihan dari tabel invoices -->
          Tagihan:
          <b><?= rp((float)$inv['amount']) ?></b><br>
          <!-- Waktu invoice diterbitkan -->
          Diterbitkan:
          <?= h(date('d M Y H:i', strtotime($inv['issued_at']))) ?><br>
        <?php endif; ?>

        <!-- Catatan bawah: info PPN dan ucapan terima kasih -->
        * Harga sudah termasuk PPN 11%. Terima kasih telah berbelanja di
        <b>Caffora</b>.
      </div>
    </main>
  </div>

  <script>
    // IIFE (Immediately Invoked Function Expression)
    // untuk inisialisasi tombol download struk
    (async function () {
      // Ambil elemen tombol download
      const btn = document.getElementById('btnDownload');

      // Pastikan library html2canvas sudah siap dipakai
      await ensureHtml2Canvas();

      // Event handler saat tombol download diklik
      btn.addEventListener('click', async () => {
        // Jika tombol sedang disabled, abaikan klik
        if (btn.disabled) {
          return;
        }

        // Simpan isi HTML asli tombol (ikon + teks)
        const original = btn.innerHTML;
        // Disable tombol sementara
        btn.disabled = true;
        // Tampilkan indikator loading (spinner + teks)
        btn.innerHTML =
          '<span class="spin"></span> Mengunduh...';

        try {
          // Ambil node sumber struk yang akan di-capture
          const srcNode = document.getElementById('receiptContent');
          // Clone node agar styling terjaga di area hidden
          const clone = srcNode.cloneNode(true);

          // Wrapper off-screen agar tidak mengganggu layout
          const wrapper = document.createElement('div');
          wrapper.style.position = 'fixed';
          wrapper.style.left = '-10000px';   // Ditaruh jauh di kiri layar
          wrapper.style.background = '#fff';
          wrapper.style.width = '320px';     // Lebar struk fix 320px
          wrapper.appendChild(clone);
          document.body.appendChild(wrapper);

          // Render wrapper ke canvas dengan html2canvas
          const canvas = await html2canvas(wrapper, {
            background: '#fff',              // Background putih
            scale: 2,                        // Resolusi lebih tajam (2x)
            useCORS: true,                   // Izinkan load asset cross origin
            windowWidth: 320                 // Lebar viewport untuk capture
          });

          // Hapus wrapper dari DOM setelah selesai
          document.body.removeChild(wrapper);

          // Konversi canvas ke Blob PNG dan trigger download
          canvas.toBlob(function (blob) {
            // Jika gagal membuat blob
            if (!blob) {
              alert('Gagal membuat gambar');
              return;
            }

            // Buat URL objek sementara dari blob
            const url = URL.createObjectURL(blob);
            // Buat <a> untuk mengunduh file
            const a = document.createElement('a');
            a.href = url;
            // Nama file hasil download pakai nomor invoice
            a.download = 'Struk_<?= h($ord['invoice_no']) ?>.png';
            // Tambahkan ke body lalu trigger click
            document.body.appendChild(a);
            a.click();
            // Hapus elemen <a> setelah dipakai
            a.remove();

            // Hapus URL blob setelah 1 detik
            setTimeout(() => URL.revokeObjectURL(url), 1000);
          }, 'image/png', 1.0);
        } catch (err) {
          // Jika ada error saat proses capture atau download
          alert(
            'Checkout berhasil tapi unduh struk gagal: ' +
            (err?.message || err)
          );
        } finally {
          // Apapun yang terjadi, aktifkan kembali tombol
          btn.disabled = false;
          // Kembalikan isi tombol ke semula
          btn.innerHTML = original;
        }
      });
    })();
  </script>
</body>
</html>
