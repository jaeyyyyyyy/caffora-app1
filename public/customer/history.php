<?php 
// Lokasi file: public/customer/history.php

// Aktifkan strict typing untuk keamanan tipe data
declare(strict_types=1);

// Mulai session jika belum aktif
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Import guard otentikasi untuk memastikan user adalah customer
require_once __DIR__ . '/../../backend/auth_guard.php';

// Wajib login sebagai customer sebelum mengakses halaman ini
require_login(['customer']);

// Import konfigurasi backend (koneksi database, BASE_URL, fungsi h())
require_once __DIR__ . '/../../backend/config.php'; // $conn, BASE_URL, h()

// Ambil user_id dari session
$userId = (int)($_SESSION['user_id'] ?? 0);

// Fungsi helper untuk format Rupiah
function rupiah(float $n): string {
  return 'Rp ' . number_format($n, 0, ',', '.');
}

// ============================================
// Ambil daftar orders milik user
// ============================================

// Siapkan array default untuk order
$orders = [];

// Jika user sah (punya ID)
if ($userId > 0) {

  // Query: ambil semua order berdasarkan user_id
  $sql  = "SELECT id, invoice_no, customer_name, service_type, table_no,
                  total, order_status, payment_status, payment_method, created_at
           FROM orders WHERE user_id = ? ORDER BY created_at DESC, id DESC";

  // Siapkan statement
  $stmt = $conn->prepare($sql);

  // Bind parameter user_id
  $stmt->bind_param('i', $userId);

  // Eksekusi query
  $stmt->execute();

  // Ambil hasil dalam bentuk associative array
  $orders = $stmt->get_result()?->fetch_all(MYSQLI_ASSOC) ?? [];

  // Tutup statement
  $stmt->close();
}

// ============================================
// Ambil item & invoice untuk semua order (bulk)
// ============================================

// Kumpulkan semua order_id dari list orders
$orderIds     = array_column($orders, 'id');

// Siapkan array penampung
$itemsByOrder = [];
$invByOrder   = [];

// Jika terdapat order ID
if ($orderIds) {

  // Buat placeholder untuk query IN (?, ?, ?, ...)
  $place = implode(',', array_fill(0, count($orderIds), '?'));

  // Buat tipe binding, misal 'iii' dll.
  $types = str_repeat('i', count($orderIds));

  // --------------------------------------------
  // Ambil semua item per order
  // --------------------------------------------

  $sqlI  = "SELECT oi.order_id, oi.menu_id, oi.qty, oi.price,
                   m.name AS menu_name, m.image AS menu_image
            FROM order_items oi
            LEFT JOIN menu m ON m.id=oi.menu_id
            WHERE oi.order_id IN ($place)
            ORDER BY oi.order_id, oi.id";

  // Siapkan statement pengambilan item
  $stmtI = $conn->prepare($sqlI);

  // Bind semua order_id
  $stmtI->bind_param($types, ...$orderIds);

  // Eksekusi query
  $stmtI->execute();

  // Ambil hasil
  $resI = $stmtI->get_result();

  // Loop setiap item dan masukkan ke array berdasarkan order_id
  while ($row = $resI->fetch_assoc()) {
    $itemsByOrder[(int)$row['order_id']][] = $row;
  }

  // Tutup statement item
  $stmtI->close();

  // --------------------------------------------
  // Ambil semua invoice per order
  // --------------------------------------------

  $sqlV  = "SELECT order_id, amount, issued_at
            FROM invoices
            WHERE order_id IN ($place)";

  // Siapkan statement invoice
  $stmtV = $conn->prepare($sqlV);

  // Bind semua order_id
  $stmtV->bind_param($types, ...$orderIds);

  // Eksekusi query
  $stmtV->execute();

  // Ambil hasil invoice
  $resV = $stmtV->get_result();

  // Loop setiap invoice dan masukkan berdasarkan order_id
  while ($row = $resV->fetch_assoc()) {
    $invByOrder[(int)$row['order_id']] = $row;
  }

  // Tutup statement invoice
  $stmtV->close();
}
?>

<!-- Deklarasi dokumen HTML5 -->
<!doctype html>

<!-- Set bahasa dokumen ke Bahasa Indonesia -->
<html lang="id">

<!-- Awal elemen head -->
<head>

  <!-- Set encoding karakter ke UTF-8 -->
  <meta charset="utf-8">

  <!-- Judul halaman yang tampil di tab browser -->
  <title>Riwayat Pesanan — Caffora</title>

  <!-- Responsif di semua perangkat, lebar mengikuti viewport -->
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Import CSS Bootstrap v5.3.3 dari CDN -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Import Bootstrap Icons untuk ikon-ikon bawaan -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Awal blok CSS internal untuk styling halaman history -->
  <style>
  :root {
    --ink: #2b2b2b;           /* warna teks utama */
    --muted: #6b7280;         /* warna teks sekunder */
    --line: #e5e7eb;          /* warna garis border */
    --bg: #ffffff;            /* warna background */
    --chip-bg: #f9fafb;       /* background chip default */
    --radius-card: 16px;      /* sudut kartu order */
    --radius-chip: 10px;      /* sudut chip status */
  }

  /* ===================================================== */
  /* GLOBAL                                                 */
  /* ===================================================== */
  * {
    font-family: Poppins, system-ui, -apple-system, "Segoe UI",
                 Roboto, Arial, sans-serif;                 /* font stack */
    box-sizing: border-box;                                  /* box model */
  }

  body {
    background: var(--bg);                                   /* bg putih */
    color: var(--ink);                                       /* teks utama */
    -webkit-font-smoothing: antialiased;                     /* smooth font */
  }

  /* ===================================================== */
  /* TOPBAR                                                 */
  /* ===================================================== */
  .topbar {
    background: #fff;                                        /* bg putih */
    border-bottom: 1px solid rgba(0,0,0,.05);               /* garis tipis */
    position: sticky;                                        /* menempel saat scroll */
    top: 0;                                                  /* posisi atas */
    z-index: 20;                                             /* layer atas */
  }

  .topbar-inner {
    max-width: 1200px;                                       /* lebar maksimum */
    margin: 0 auto;                                          /* center */
    padding: 12px 24px;                                      /* padding dalam */
    min-height: 52px;                                        /* tinggi minimum */
    display: flex;                                           /* flex layout */
    align-items: center;                                     /* center vertikal */
    gap: 10px;                                               /* jarak antar item */
  }

  .back-link {
    display: inline-flex;                                    /* flex inline */
    align-items: center;                                     /* center vertikal */
    gap: 10px;                                               /* jarak ikon-teks */
    color: var(--ink);                                       /* warna teks */
    text-decoration: none;                                   /* tanpa underline */
    border: 0;                                               /* tanpa border */
    background: transparent;                                 /* bg transparan */
    padding: 0;                                              /* nol padding */
  }

  .back-link span {
    font-family: system-ui, -apple-system, "Segoe UI",
                 Roboto, Arial, sans-serif !important;        /* paksa system font */
    font-size: 1rem;                                          /* 16px */
    font-weight: 600;                                         /* semi-bold */
    line-height: 1.3;                                         /* tinggi baris */
  }

  .back-link .bi {
    width: 18px;                                              /* ikon 18px */
    height: 18px;                                             /* ikon 18px */
    display: inline-flex;                                     /* inline flex */
    align-items: center;                                      /* center */
    justify-content: center;                                  /* center */
    font-size: 18px !important;                               /* paksa size */
    line-height: 18px !important;                             /* tinggi baris */
  }

  /* ===================================================== */
  /* PAGE WRAPPER                                           */
  /* ===================================================== */
  .page {
    max-width: 1200px;                                       /* batas lebar */
    margin: 20px auto 32px;                                  /* margin kiri-kanan auto */
    padding: 0 24px;                                         /* padding horizontal */
  }

  /* ===================================================== */
  /* ORDER CARD                                             */
  /* ===================================================== */
  .order-card {
    border: 1px solid var(--line);                           /* border tipis */
    border-radius: var(--radius-card);                       /* sudut kartu */
    background: #fff;                                        /* bg putih */
    overflow: hidden;                                        /* hilangkan overflow */
    box-shadow: 0 8px 20px rgba(0,0,0,.03);                  /* shadow lembut */
  }

  .order-card + .order-card {
    margin-top: 16px;                                        /* jarak antar card */
  }

  /* ===================================================== */
  /* HEADER CARD                                            */
  /* ===================================================== */
  .order-head {
    display: flex;                                           /* layout flex */
    flex-wrap: wrap;                                         /* bisa pindah baris */
    justify-content: space-between;                          /* kiri & kanan */
    align-items: flex-start;                                 /* posisi awal */
    row-gap: 6px;                                            /* jarak antar baris */
    padding: 16px 16px 12px;                                 /* padding */
  }

  .head-left {
    font-size: .95rem;                                       /* ukuran teks */
    line-height: 1.3;                                        /* tinggi baris */
    font-weight: 600;                                        /* bold */
    color: var(--ink);                                       /* warna teks */
  }

  .head-right {
    display: flex;                                           /* flex */
    align-items: center;                                     /* center vertikal */
    gap: 6px;                                                /* jarak */
    font-size: .8rem;                                        /* teks kecil */
    line-height: 1.2;                                        /* rapat */
    color: var(--muted);                                     /* abu-abu */
    font-weight: 500;                                        /* medium */
  }

  /* ===================================================== */
  /* CHIPS (STATUS)                                         */
  /* ===================================================== */
  .chips {
    display: flex;                                           /* flex */
    flex-wrap: wrap;                                         /* wrap */
    gap: 8px;                                                /* jarak antar chip */
    padding: 12px 16px;                                      /* padding */
    background: #fafafa;                                     /* abu muda */
  }

  .chip {
    background: var(--chip-bg);                              /* chip bg */
    border: 1px solid var(--line);                           /* border tipis */
    border-radius: var(--radius-chip);                       /* sudut bulat */
    padding: 6px 10px;                                       /* padding */
    font-size: .75rem;                                       /* teks kecil */
    font-weight: 600;                                        /* bold */
    display: inline-flex;                                    /* inline flex */
    align-items: center;                                     /* center */
    gap: 6px;                                                /* jarak ikon-teks */
    white-space: nowrap;                                     /* single line */
  }

  .chip--order.pending,
  .chip--pay.pending {
    background: #fff7ed;                                     /* kuning soft */
    border-color: #fde68a;                                   /* kuning border */
    color: #92400e;                                          /* teks coklat */
  }

  .chip--order.completed,
  .chip--pay.paid {
    background: #ecfdf5;                                     /* hijau soft */
    border-color: #bbf7d0;                                   /* hijau border */
    color: #065f46;                                          /* hijau teks */
  }

  .chip--order.cancelled,
  .chip--pay.failed {
    background: #fee2e2;                                     /* merah soft */
    border-color: #fecaca;                                   /* merah border */
    color: #991b1b;                                          /* merah teks */
  }

  /* ===================================================== */
  /* ITEMS LIST                                             */
  /* ===================================================== */
  .items-block {
    padding: 12px 16px 0;                                    /* padding */
  }

  .item-row {
    display: flex;                                           /* flex row */
    align-items: flex-start;                                 /* align top */
    justify-content: space-between;                          /* kiri-kanan */
    gap: 12px;                                               /* jarak */
    padding: 12px 0;                                         /* padding baris */
    border-bottom: 1px dashed var(--line);                   /* garis putus-putus */
  }

  .item-left {
    display: flex;                                           /* flex */
    gap: 12px;                                               /* jarak */
    flex: 1;                                                 /* isi sisa ruang */
    min-width: 0;                                            /* teks bisa wrap */
  }

  .thumb {
    width: 52px;                                             /* width thumb */
    height: 52px;                                            /* height thumb */
    border-radius: 10px;                                     /* sudut bulat */
    background: #fff;                                        /* bg putih */
    border: 1px solid var(--line);                           /* border tipis */
    object-fit: cover;                                       /* crop gambar */
    flex-shrink: 0;                                          /* jangan mengecil */
  }

  .item-meta {
    min-width: 0;                                            /* support ellipsis */
  }

  .item-name {
    font-weight: 600;                                        /* tebal */
    font-size: .9rem;                                        /* ukuran teks */
    color: var(--ink);                                       /* warna teks */
    word-break: break-word;                                  /* pecah kata */
  }

  .item-sub {
    font-size: .8rem;                                        /* kecil */
    color: var(--muted);                                     /* abu */
  }

  .item-line-total {
    font-weight: 600;                                        /* tebal */
    font-size: .9rem;                                        /* ukuran teks */
    color: var(--ink);                                       /* warna teks */
    text-align: right;                                       /* rata kanan */
    min-width: 70px;                                         /* batas minimal */
    white-space: nowrap;                                     /* 1 baris */
  }

  /* ===================================================== */
  /* TOGGLE (SHOW/HIDE ITEMS)                              */
  /* ===================================================== */
  .toggle-wrap {
    padding: 12px 0;                                         /* padding vertikal */
  }

  .toggle-btn {
    width: 100%;                                             /* full width */
    border: 1px solid var(--line);                           /* border tipis */
    background: #fff;                                        /* bg putih */
    border-radius: 999px;                                    /* pill button */
    padding: 8px 12px;                                       /* padding */
    font-weight: 600;                                        /* tebal */
    font-size: .8rem;                                        /* kecil */
    display: flex;                                           /* flex */
    align-items: center;                                     /* tengah */
    justify-content: center;                                 /* center */
    gap: 6px;                                                /* jarak */
  }

  .toggle-btn:hover {
    background: #f9fafb;                                     /* hover abu */
  }

  /* ===================================================== */
  /* FOOTER ORDER CARD                                      */
  /* ===================================================== */
  .order-foot {
    padding: 14px 16px 16px;                                 /* padding */
    background: #fff;                                        /* bg putih */
  }

  .inv-summary {
    font-size: .8rem;                                        /* kecil */
    color: var(--muted);                                     /* abu */
    margin-bottom: 12px;                                     /* jarak bawah */
  }

  .inv-summary strong {
    color: var(--ink);                                       /* warna teks utama */
  }

  .foot-bottom {
    display: flex;                                           /* flex */
    flex-wrap: wrap;                                         /* cekatan */
    align-items: center;                                     /* tengah */
    justify-content: space-between;                          /* kiri-kanan */
    row-gap: 10px;                                           /* jarak baris */
  }

  .btn-receipt {
    border: 1px solid var(--line);                           /* border */
    background: #fff;                                        /* bg putih */
    border-radius: 999px;                                    /* pill */
    padding: 7px 12px;                                       /* padding */
    font-weight: 600;                                        /* tebal */
    font-size: .8rem;                                        /* kecil */
    display: inline-flex;                                    /* flex */
    align-items: center;                                     /* tengah */
    gap: 6px;                                                /* jarak */
    text-decoration: none;                                   /* hilang underline */
    color: var(--ink);                                       /* warna teks */
  }

  .btn-receipt:hover {
    background: #f9fafb;                                     /* hover abu */
  }

  .total-amount {
    font-weight: 600;                                        /* tebal */
    font-size: 1rem;                                         /* ukuran normal */
    white-space: nowrap;                                     /* satu baris */
  }

  /* ===================================================== */
  /* MOBILE RESPONSIVE                                      */
  /* ===================================================== */
  @media (max-width: 600px) {
    .topbar-inner,
    .page {
      padding: 0 16px;                                       /* padding lebih sempit */
    }
  }
</style>

</head>

<body>

  <!-- Topbar -->
  <!-- Wrapper top bar navigasi untuk kembali ke halaman utama customer -->
  <div class="topbar">
    
    <!-- Kontainer dalam topbar dengan padding dan layout fleksibel -->
    <div class="topbar-inner">

      <!-- Tombol kembali, mengarah ke halaman index customer -->
      <a class="back-link" href="<?= h(BASE_URL) ?>/public/customer/index.php">
        
        <!-- Ikon panah kiri -->
        <i class="bi bi-arrow-left"></i>
        
        <!-- Teks Kembali -->
        <span>Kembali</span>
      </a>

    </div>
  </div>

  <!-- Konten utama halaman -->
  <main class="page">

    <!-- Jika tidak ada pesanan, tampilkan alert -->
    <?php if (!$orders): ?>
      
      <!-- Pesan belum ada pesanan -->
      <div class="alert alert-light border" role="alert" style="font-size:.9rem; line-height:1.4; color:var(--ink);">
        Belum ada pesanan.
      </div>

    <?php else: ?>

      <!-- Loop seluruh order -->
      <?php foreach ($orders as $ord):

        // ID order
        $oid   = (int)$ord['id'];

        // Ambil list item berdasarkan ID order
        $items = $itemsByOrder[$oid] ?? [];

        // Ambil invoice berdasarkan ID order
        $inv   = $invByOrder[$oid]   ?? null;

        // Ikon status pesanan berdasarkan status order
        $orderIcon = [
          'new'        => 'bi-plus-lg',
          'processing' => 'bi-hourglass-split',
          'ready'      => 'bi-clipboard-check',
          'completed'  => 'bi-check-circle',
          'cancelled'  => 'bi-x-circle',
          'pending'    => 'bi-hourglass-split'
        ][$ord['order_status']] ?? 'bi-receipt';

        // Ikon status pembayaran
        $payIcon = [
          'pending'  => 'bi-hourglass-split',
          'paid'     => 'bi-check-circle',
          'failed'   => 'bi-x-circle',
          'refunded' => 'bi-arrow-counterclockwise',
          'overdue'  => 'bi-exclamation-triangle'
        ][$ord['payment_status']] ?? 'bi-cash-coin';

        // Item pertama
        $first = $items[0] ?? null;

        // Hitung jumlah item lainnya
        $restCount = max(0, count($items) - 1);

        // Format waktu dibuat
        $createdStr = date('d M Y H:i', strtotime($ord['created_at']));

        // Jumlah tagihan invoice
        $invoiceAmount = $inv['amount'] ?? $ord['total'];
      ?>

      <!-- Satu kartu order -->
      <section class="order-card">

        <!-- HEADER -->
        <!-- Bagian header menampilkan invoice dan waktu dibuat -->
        <div class="order-head">

          <!-- Nomor invoice -->
          <div class="head-left">
            <?= h($ord['invoice_no']) ?>
          </div>

          <!-- Waktu pembuatan -->
          <div class="head-right">
            <i class="bi bi-clock"></i>
            <span><?= h($createdStr) ?></span>
          </div>
        </div>

        <!-- STATUS / META -->
        <!-- Bagian chip status pesanan dan pembayaran -->
        <div class="chips">

          <!-- Status pesanan -->
          <span class="chip chip--order <?= h($ord['order_status']) ?>">
            <i class="bi <?= $orderIcon ?>"></i>
            <span><?= h($ord['order_status']) ?></span>
          </span>

          <!-- Status pembayaran -->
          <span class="chip chip--pay <?= h($ord['payment_status']) ?>">
            <i class="bi <?= $payIcon ?>"></i>
            <span><?= h($ord['payment_status']) ?></span>
          </span>

          <!-- Metode pembayaran -->
          <?php if (!empty($ord['payment_method'])): ?>
            <span class="chip">
              <i class="bi bi-wallet2"></i>
              <span><?= h(strtoupper(str_replace('_',' ',$ord['payment_method']))) ?></span>
            </span>
          <?php endif; ?>

          <!-- Jenis layanan (Dine in / Take Away) -->
          <span class="chip">
            <i class="bi bi-shop"></i>
            <span><?= h($ord['service_type']==='dine_in'?'Dine In':'Take Away') ?></span>
          </span>

          <!-- Nomor meja (jika dine-in dan punya nomor meja) -->
          <?php if (!empty($ord['table_no'])): ?>
            <span class="chip">
              <i class="bi bi-upc-scan"></i>
              <span>Meja <?= h($ord['table_no']) ?></span>
            </span>
          <?php endif; ?>

        </div>

        <!-- ITEMS -->
        <!-- Daftar item dalam pesanan -->
        <div class="items-block">

          <!-- Item pertama -->
          <?php if ($first): ?>
          <div class="item-row">

            <!-- Bagian kiri item -->
            <div class="item-left">

              <!-- Thumbnail gambar item -->
              <?php if (!empty($first['menu_image'])): ?>
                <img class="thumb" src="<?= h(BASE_URL . '/public/' . ltrim($first['menu_image'],'/')) ?>" alt="">
              <?php else: ?>
                <div class="thumb d-flex align-items-center justify-content-center text-muted">—</div>
              <?php endif; ?>

              <!-- Info item -->
              <div class="item-meta">
                <div class="item-name">
                  <?= h($first['menu_name'] ?? 'Menu') ?>
                </div>
                <div class="item-sub">
                  Qty: <?= (int)$first['qty'] ?> × <?= rupiah((float)$first['price']) ?>
                </div>
              </div>
            </div>

            <!-- Total harga item -->
            <div class="item-line-total">
              <?= rupiah((float)$first['qty'] * (float)$first['price']) ?>
            </div>

          </div>
          <?php endif; ?>

          <!-- Item lainnya -->
          <?php if ($restCount > 0): ?>

            <!-- Collapse berisi item sisanya -->
            <div class="collapse" id="items-<?= $oid ?>">

              <!-- Loop item ke-2 dan seterusnya -->
              <?php for ($i=1; $i<count($items); $i++): $it = $items[$i]; ?>

                <!-- Baris item -->
                <div class="item-row">

                  <!-- Kiri: gambar + info -->
                  <div class="item-left">

                    <!-- Thumbnail -->
                    <?php if (!empty($it['menu_image'])): ?>
                      <img class="thumb" src="<?= h(BASE_URL . '/public/' . ltrim($it['menu_image'],'/')) ?>" alt="">
                    <?php else: ?>
                      <div class="thumb d-flex align-items-center justify-content-center text-muted">—</div>
                    <?php endif; ?>

                    <!-- Info item -->
                    <div class="item-meta">
                      <div class="item-name"><?= h($it['menu_name'] ?? 'Menu') ?></div>
                      <div class="item-sub">
                        Qty: <?= (int)$it['qty'] ?> × <?= rupiah((float)$it['price']) ?>
                      </div>
                    </div>

                  </div>

                  <!-- Total baris -->
                  <div class="item-line-total">
                    <?= rupiah((float)$it['qty'] * (float)$it['price']) ?>
                  </div>

                </div>
              <?php endfor; ?>

            </div>

            <!-- Tombol toggle show/hide item -->
            <div class="toggle-wrap">
              <button
                class="toggle-btn"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#items-<?= $oid ?>"
                aria-expanded="false"
                aria-controls="items-<?= $oid ?>">
                
                <i class="bi bi-chevron-down"></i>

                <span class="toggle-text-<?= $oid ?>">
                  Tampilkan <?= $restCount ?> item lainnya
                </span>
              </button>
            </div>

          <?php endif; ?>

        </div>

        <!-- FOOTER -->
        
        <!-- Footer menampilkan total dan tombol struk -->
        <div class="order-foot">

          <!-- Ringkasan invoice -->
          <div class="inv-summary">
            Tagihan <strong><?= rupiah((float)$invoiceAmount) ?></strong>
            <?php if ($ord['payment_status'] === 'paid'): ?>
              • <strong>Lunas</strong>
            <?php endif; ?>
          </div>

          <!-- Bagian bawah footer -->
          <div class="foot-bottom">

            <!-- Tombol untuk membuka struk -->
            <a
              class="btn-receipt"
              href="<?= h(BASE_URL) ?>/public/customer/receipt.php?order=<?= $oid ?>">
              <i class="bi bi-receipt"></i>
              <span>Struk</span>
            </a>

            <!-- Total keseluruhan -->
            <div class="total-amount">
              <?= rupiah((float)$ord['total']) ?>
            </div>
       
          <!-- Penutup bagian .foot-bottom -->
             </div> 
          
        <!-- Penutup bagian .order-foot -->
            </div>
            
          <!-- Penutup satu kartu order (satu pesanan) -->    
      </section>
      
      <!-- Penutup perulangan foreach untuk seluruh daftar pesanan -->
      <?php endforeach; ?>
      
       <!-- Penutup kondisi if: jika tidak ada order vs ada order -->
    <?php endif; ?>
    <!-- Penutup kondisi if: jika tidak ada order vs ada order -->

  </main>
  <!-- Penutup elemen utama halaman -->

    <!-- Import Bootstrap JS (dibutuhkan untuk fitur collapse) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>

  // Ambil semua tombol yang memiliki atribut data-bs-toggle="collapse"
  document.querySelectorAll('[data-bs-toggle="collapse"]').forEach((btn) => {

    // Cari elemen target collapse berdasarkan data-bs-target
    const target = document.querySelector(btn.dataset.bsTarget);

    // Jika target tidak ditemukan, hentikan untuk tombol ini
    if (!target) return;

    // Ambil order ID dari nama target (contoh: #items-5 → 5)
    const oid = btn.dataset.bsTarget.replace('#items-', '');

    // Ambil elemen teks toggle (label yang berubah)
    const textEl = document.querySelector('.toggle-text-' + oid);

    // Ambil ikon chevron pada tombol
    const iconEl = btn.querySelector('i');

    // Jika elemen teks atau ikon tidak ada, hentikan
    if (!textEl || !iconEl) return;

    // Event saat collapse terbuka
    target.addEventListener('show.bs.collapse', () => {
      // Ubah label menjadi "Sembunyikan rincian"
      textEl.textContent = 'Sembunyikan rincian';
      // Ganti ikon ke chevron-up
      iconEl.className   = 'bi bi-chevron-up';
    });

    // Event saat collapse tertutup
    target.addEventListener('hide.bs.collapse', () => {
      // Hitung jumlah item yang tampil di collapse
      const count = target.querySelectorAll('.item-row').length;

      // Ubah label kembali menjadi "Tampilkan X item lainnya"
      textEl.textContent = 'Tampilkan ' + count + ' item lainnya';

      // Ganti ikon ke chevron-down
      iconEl.className   = 'bi bi-chevron-down';
    });

  });

  // Penutup blok script custom
       </script>
 
      <!-- Penutup elemen body -->
     </body>

   <!-- Penutup dokumen HTML -->
</html>

