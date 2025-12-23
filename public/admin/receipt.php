<?php
// Tagline halaman: Cetak struk pembayaran Caffora khusus admin, lengkap dan siap print thermal.

// public/admin/receipt.php
declare(strict_types=1); // Aktifkan strict typing supaya tipe data lebih ketat.
if (session_status() !== PHP_SESSION_ACTIVE) session_start(); // Pastikan sesi sudah aktif, kalau belum maka mulai sesi baru.

require_once __DIR__ . '/../../backend/auth_guard.php'; // Load guard autentikasi untuk batasi akses halaman.
require_login(['admin']); // ← ADMIN SAJA // Hanya user dengan role admin yang boleh akses struk ini.
require_once __DIR__ . '/../../backend/config.php'; // Load konfigurasi global (DB, BASE_URL, helper, dll).

// Helper untuk format angka rupiah (tanpa desimal) dengan pemisah ribuan Indonesia.
function rp(float $n): string { return 'Rp ' . number_format($n, 0, ',', '.'); }

// Ambil parameter order dari query string dan paksa jadi integer.
$orderId = (int)($_GET['order'] ?? 0);

// Variabel penampung detail order, item, dan invoice.
$ord = null;
$items = [];
$inv = null;

// Jika ada ID order yang valid (bukan 0).
if ($orderId) {
  // Siapkan statement untuk ambil satu baris order berdasarkan id.
  $stmt = $conn->prepare("SELECT * FROM orders WHERE id=? LIMIT 1");
  $stmt->bind_param('i', $orderId); // Bind parameter id sebagai integer.
  $stmt->execute(); // Eksekusi query.
  $ord = $stmt->get_result()?->fetch_assoc(); // Ambil hasil sebagai array asosiatif (bisa null).
  $stmt->close(); // Tutup statement.

  // Jika order ditemukan, lanjut ambil item dan invoice-nya.
  if ($ord) {
    // Query item pesanan dengan join ke tabel menu untuk ambil nama menu.
    $stmt = $conn->prepare("
      SELECT oi.qty, oi.price, m.name AS menu_name
      FROM order_items oi
      LEFT JOIN menu m ON m.id=oi.menu_id
      WHERE oi.order_id=? ORDER BY oi.id
    ");
    $stmt->bind_param('i', $orderId); // Bind order_id untuk filter item.
    $stmt->execute(); // Eksekusi query item.
    $items = $stmt->get_result()?->fetch_all(MYSQLI_ASSOC) ?? []; // Ambil semua item sebagai array asosiatif.
    $stmt->close(); // Tutup statement.

    // Query invoice terkait order untuk ambil jumlah tagihan dan waktu terbit.
    $stmt = $conn->prepare("SELECT amount, issued_at FROM invoices WHERE order_id=? LIMIT 1");
    $stmt->bind_param('i', $orderId); // Bind order_id ke query invoice.
    $stmt->execute(); // Eksekusi query invoice.
    $inv = $stmt->get_result()?->fetch_assoc(); // Ambil satu baris invoice (bisa null).
    $stmt->close(); // Tutup statement.
  }
}

// Jika order tidak ditemukan sama sekali, kirim 404 dan stop.
if (!$ord) {
  http_response_code(404); // Set status HTTP jadi 404.
  echo 'Order tidak ditemukan'; // Tampilkan pesan singkat.
  exit; // Hentikan eksekusi script.
}

// Hitung subtotal, pajak, dan grand total dengan fallback jika kolom tertentu kosong.
$subtotal   = isset($ord['subtotal'])    ? (float)$ord['subtotal']    : (float)($ord['total'] ?? 0);
$taxAmount  = isset($ord['tax_amount'])  ? (float)$ord['tax_amount']  : 0.0;
$grandTotal = isset($ord['grand_total']) ? (float)$ord['grand_total'] : ($subtotal + $taxAmount);

// Jika status pembayaran belum 'paid', tampilkan halaman info bahwa struk belum tersedia.
if ($ord['payment_status'] !== 'paid') {
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8"> <!-- Set encoding karakter ke UTF-8 -->
  <meta name="viewport" content="width=device-width,initial-scale=1"> <!-- Responsive layout di mobile -->
  <title>Struk belum tersedia — Caffora</title> <!-- Judul tab saat struk belum bisa dicetak -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/> <!-- Bootstrap Icons -->
  <style>
    :root{
      --ink:#111827;--muted:#6b7280;--bg:#fafafa; /* Variabel warna utama, muted, dan background */
    }
    *{box-sizing:border-box;font-family:Poppins,Arial,sans-serif;} /* Semua elemen pakai box-sizing border-box dan font Poppins */
    body{margin:0;background:var(--bg);color:var(--ink);} /* Reset margin body dan set warna teks & background */
    .topbar{position:sticky;top:0;background:#fff;border-bottom:1px solid rgba(0,0,0,.04);} /* Bar atas lengket di atas saat scroll */
    .inner{max-width:1200px;margin:0 auto;padding:12px 16px;display:flex;gap:10px;align-items:center;} /* Wrapper konten topbar */
    .back-link{display:inline-flex;gap:8px;align-items:center;text-decoration:none;color:var(--ink);font-weight:600;font-family:Arial,Helvetica,sans-serif !important;} /* Gaya link kembali dengan ikon dan teks */
    .page{max-width:1200px;margin:14px auto 40px;padding:0 16px;} /* Wrapper utama konten halaman */
    .card{background:#fff;border-radius:14px;padding:18px 16px;} /* Kartu putih dengan sudut membulat untuk pesan */
  </style>
</head>
<body>
  <div class="topbar">
    <div class="inner">
      <!-- Link kembali ke halaman daftar orders admin -->
      <a class="back-link" href="<?= h(BASE_URL) ?>/public/admin/orders.php"><i class="bi bi-arrow-left"></i><span>Kembali</span></a>
    </div>
  </div>
  <div class="page">
    <div class="card">
      <!-- Judul informasi bahwa struk belum tersedia -->
      <h2 style="margin:0 0 10px;font-weight:600;">Struk belum tersedia</h2>
      <!-- Penjelasan singkat syarat struk bisa dicetak -->
      <p style="margin:0 0 8px;color:var(--muted);">Struk bisa dicetak setelah pembayaran <b>lunas</b>.</p>
      <!-- Menampilkan nomor invoice yang terkait order -->
      <p style="margin:0;">Invoice: <b><?= h($ord['invoice_no']) ?></b></p>
    </div>
  </div>
</body>
</html>
<?php
  exit; // Hentikan script setelah tampilkan halaman info struk belum tersedia.
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8"> <!-- Encoding karakter -->
  <title>Struk — <?= h($ord['invoice_no']) ?> | Caffora</title> <!-- Judul tab dengan nomor invoice -->
  <meta name="viewport" content="width=device-width,initial-scale=1"> <!-- Layout responsive -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"> <!-- Bootstrap Icons -->
  <style>
    :root{
      --ink:#111827;--muted:#6b7280;--line:#e5e7eb; /* Warna teks, teks muted, dan garis */
      --bg:#fafafa;--card:#fff;--gold:#FFD54F;--brown:#4B3F36; /* Warna latar, kartu, dan warna brand */
    }
    *{box-sizing:border-box;font-family:Inter,system-ui,Arial,sans-serif;} /* Atur box-sizing dan font global */
    body{margin:0;background:var(--bg);color:var(--ink);} /* Reset margin dan set warna dasar body */
    .topbar{position:sticky;top:0;background:#fff;border-bottom:1px solid rgba(0,0,0,.04);z-index:20;} /* Bar atas yang tetap di atas saat scroll */
    .topbar .inner{max-width:1200px;margin:0 auto;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;gap:10px;} /* Wrapper isi topbar (back + tombol print) */
    .back-link{display:inline-flex;align-items:center;gap:8px;text-decoration:none;color:var(--ink);font-weight:600;font-size:.95rem;font-family:Arial,Helvetica,sans-serif !important;} /* Gaya link kembali */
    .btn-print{background:var(--gold);color:var(--brown) !important;border:0;border-radius:12px;padding:10px 14px;display:inline-flex;gap:8px;align-items:center;cursor:pointer;font-family:Arial,Helvetica,sans-serif !important;font-weight:600;} /* Tombol print dengan warna brand */
    .page{max-width:1200px;margin:14px auto 40px;padding:0 16px;display:flex;justify-content:center;} /* Area utama halaman yang memusatkan kertas struk */
    .paper{background:var(--card);width:100%;max-width:320px;border:1px solid var(--line);padding:18px 20px;box-shadow:0 2px 6px rgba(0,0,0,.05);} /* Card yang mensimulasikan kertas struk */
    .paper, .paper *{font-family:Arial,Helvetica,sans-serif !important;color:#4b5563 !important;} /* Override font dan warna di dalam kertas */
    .brand{font-weight:600;font-size:16px;margin-bottom:4px;} /* Nama brand di header struk */
    .meta{display:flex;justify-content:space-between;gap:10px;font-size:.8rem;} /* Baris meta info (invoice, tanggal, customer) */
    .to{text-align:right;} /* Info customer rata kanan */
    .rule{border-top:2px dashed #aaa;margin:10px 0;opacity:.8;} /* Garis putus-putus pemisah section */
    .head-row{display:flex;justify-content:space-between;font-weight:500;font-size:.8rem;padding:4px 0 6px;} /* Header kolom item dan subtotal */
    .item{display:grid;grid-template-columns:1fr auto;gap:12px;padding:8px 0;} /* Baris item struk: info kiri, subtotal kanan */
    .item-name{font-weight:500;font-size:.85rem;} /* Nama menu di struk */
    .item-sub{font-size:.75rem;margin-top:1px;} /* Info qty dan harga per item */
    .item-subtotal{text-align:right;font-weight:500;font-size:.8rem;} /* Total per item (subtotal) di kanan */
    .row{display:flex;justify-content:space-between;padding:6px 0;font-size:.8rem;} /* Baris ringkasan subtotal/pajak/total */
    .row.total{border-top:2px dotted var(--line);margin-top:4px;padding-top:8px;font-weight:600;font-size:.9rem;} /* Baris total akhir dengan garis atas */
    .status-line{display:flex;justify-content:space-between;gap:12px;padding-top:8px;font-size:.8rem;} /* Baris status LUNAS + metode bayar */
    .pill{background:transparent !important;border:none !important;font-weight:700 !important;letter-spacing:.18rem;font-size:.75rem;} /* Tampilan teks L U N A S seperti pill */
    .footer-note{font-size:.7rem;line-height:1.5;} /* Catatan kecil di bawah struk */
    @media print{
      @page{size:58mm auto;margin:3mm;} /* Atur ukuran halaman cetak ke 58mm (thermal) dan margin kecil */
      html,body{width:58mm;background:#fff !important;margin:0;padding:0;} /* Sesuaikan lebar HTML/body untuk print thermal */
      .topbar{display:none !important;} /* Sembunyikan topbar saat mode print */
      .page{margin:0;padding:0;display:block;width:58mm;} /* Sesuaikan wrapper untuk lebar kertas */
      .paper{width:calc(58mm - 6mm);max-width:calc(58mm - 6mm);border:0;box-shadow:none;padding:6px 6px 10px;} /* Kertas tanpa border & shadow saat print */
      .brand{font-size:13px;} /* Kecilkan font di semua elemen saat print */
      .meta{font-size:10px;}
      .head-row{font-size:10px;}
      .item-name{font-size:10px;}
      .item-sub{font-size:9px;}
      .item-subtotal{font-size:10px;}
      .row{font-size:10px;}
      .row.total{font-size:11px;}
      .footer-note{font-size:9px;}
    }
  </style>
</head>
<body>
  <div class="topbar">
    <div class="inner">
      <!-- Link kembali ke halaman orders admin -->
      <a class="back-link" href="<?= h(BASE_URL) ?>/public/admin/orders.php">
        <i class="bi bi-arrow-left"></i><span>Kembali</span>
      </a>
      <!-- Tombol untuk memicu dialog print browser -->
      <button id="btnPrint" class="btn-print" type="button">
        <i class="bi bi-printer"></i><span>Print</span>
      </button>
    </div>
  </div>

  <div class="page">
    <main class="paper">
      <!-- Nama brand di header struk -->
      <div class="brand">Caffora</div>
      <!-- Area metadata struk: invoice, tanggal, dan customer -->
      <div class="meta">
        <div>
          <!-- Menampilkan nomor invoice -->
          <div>Invoice: <span style="font-weight:500"><?= h($ord['invoice_no']) ?></span></div>
          <!-- Menampilkan tanggal dibuatnya order -->
          <div>Tanggal: <?= h(date('d M Y H:i', strtotime($ord['created_at']))) ?></div>
        </div>
        <div class="to">
          <!-- Label customer -->
          <div style="color:#9ca3af">Customer:</div>
          <!-- Nama customer yang memesan -->
          <div style="font-weight:500"><?= h($ord['customer_name']) ?></div>
          <div>
            <!-- Info jenis layanan (dine in / take away) -->
            <?= h($ord['service_type']==='dine_in' ? 'Dine In' : 'Take Away') ?>
            <!-- Tambahan nomor meja jika ada -->
            <?= $ord['table_no'] ? ', Meja '.h($ord['table_no']) : '' ?>
          </div>
        </div>
      </div>

      <!-- Garis pemisah antara header dan list item -->
      <div class="rule"></div>

      <!-- Header kolom item dan subtotal -->
      <div class="head-row">
        <div>Item</div>
        <div>Subtotal</div>
      </div>

      <!-- Daftar item pesanan -->
      <div class="items">
        <?php foreach($items as $it):
          $sub = (float)$it['qty'] * (float)$it['price']; ?> <!-- Hitung subtotal per item: qty x price -->
          <div class="item">
            <div>
              <!-- Nama menu (fallback "Menu" kalau null) -->
              <div class="item-name"><?= h($it['menu_name'] ?? 'Menu') ?></div>
              <!-- Informasi qty dan harga satuan -->
              <div class="item-sub">
                Qty: <?= (int)$it['qty'] ?> × <?= rp((float)$it['price']) ?>
              </div>
            </div>
            <!-- Subtotal rupiah untuk item ini -->
            <div class="item-subtotal"><?= rp($sub) ?></div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Ringkasan subtotal -->
      <div class="row" style="margin-top:6px">
        <div>Subtotal</div>
        <div class="muted"><?= rp($subtotal) ?></div>
      </div>
      <!-- Ringkasan pajak -->
      <div class="row">
        <div>Pajak 11%</div>
        <div class="muted"><?= rp($taxAmount) ?></div>
      </div>
      <!-- Total akhir setelah pajak -->
      <div class="row total">
        <div>Total</div>
        <div><?= rp($grandTotal) ?></div>
      </div>

      <!-- Baris status pembayaran dan metode pembayaran -->
      <div class="status-line">
        <!-- Label LUNAS ditulis renggang -->
        <div class="pill">L U N A S</div>
        <!-- Menampilkan metode pembayaran dalam huruf besar dan spasi alih-alih underscore -->
        <div>Metode Pembayaran: <b><?= h(strtoupper(str_replace('_',' ',$ord['payment_method'] ?? '-'))) ?></b></div>
      </div>

      <!-- Garis pemisah sebelum catatan -->
      <div class="rule"></div>
      <!-- Catatan footer terkait invoice dan informasi tambahan -->
      <div class="footer-note">
        <?php if ($inv): ?>
          <!-- Informasi jumlah tagihan dari tabel invoices -->
          Tagihan: <b><?= rp((float)$inv['amount']) ?></b><br>
          <!-- Waktu invoice diterbitkan -->
          Diterbitkan: <?= h(date('d M Y H:i', strtotime($inv['issued_at']))) ?><br>
        <?php endif; ?>
        <!-- Catatan bahwa harga sudah termasuk PPN dan ucapan terima kasih -->
        * Harga sudah termasuk PPN 11%. Terima kasih sudah belanja di <b>Caffora</b>.
      </div>
    </main>
  </div>

  <script>
    // Saat tombol print diklik, panggil window.print() untuk membuka dialog print browser.
    document.getElementById('btnPrint')?.addEventListener('click', () => window.print());
  </script>
</body>
</html>
