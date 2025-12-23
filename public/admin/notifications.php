<?php
// ===============================================================                         // Header penjelasan singkat file
// Tagline: "Satu halaman ringkas untuk membaca semua notifikasi admin."                  // Tagline utama halaman notifikasi admin
// File   : public/admin/notifications.php                                                // Lokasi file dalam struktur project
// Fungsi : Menampilkan daftar notifikasi untuk admin (broadcast & personal)             // Deskripsi fungsi utama
// Akses  : Hanya pengguna dengan role "admin"                                            // Batasan hak akses
// ===============================================================

// public/admin/notifications.php                                                         // Path relatif file (informasi saja)
declare(strict_types=1);                                                                  // Aktifkan strict type checking pada PHP
session_start();                                                                          // Mulai atau lanjutkan sesi PHP untuk akses $_SESSION

require_once __DIR__ . '/../../backend/config.php';                                      // Load konfigurasi global (DB, BASE_URL, dll)

// Guard: hanya admin                                                                      // Komentar: blok validasi hanya admin
if (!isset($_SESSION['user_id']) || (($_SESSION['user_role'] ?? '') !== 'admin')) {       // Cek: jika belum login atau role bukan admin
  header('Location: ' . BASE_URL . '/public/login.html');                                 // Redirect ke halaman login
  exit;                                                                                   // Hentikan eksekusi script
}

$conn   = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);                                 // Buat koneksi baru ke database dengan mysqli
if ($conn->connect_error) {                                                               // Jika koneksi gagal
  http_response_code(500);                                                                // Set status HTTP 500 (server error)
  echo "DB error";                                                                        // Tampilkan pesan error sederhana
  exit;                                                                                   // Hentikan script
}
$conn->set_charset('utf8mb4');                                                            // Set charset koneksi ke UTF-8 mb4 (emoji aman)

$userId = (int)($_SESSION['user_id'] ?? 0);                                               // Ambil ID user dari session sebagai integer
$BASE   = BASE_URL;                                                                       // Simpan BASE_URL ke variabel lokal untuk dipakai di HTML

/*
  Ambil daftar notif untuk admin:
  - khusus admin (user_id = admin)
  - broadcast umum (role IS NULL)
  - broadcast role admin (role='admin')
*/                                                                                        // Komentar multi-line penjelasan query notifikasi
$sql = "
  SELECT id, message, status, created_at, link
  FROM notifications
  WHERE user_id = ?
     OR (user_id IS NULL AND (role IS NULL OR role='admin'))
  ORDER BY created_at DESC
  LIMIT 100
";                                                                                        // Query: ambil maksimal 100 notifikasi relevan utk admin
$stmt = $conn->prepare($sql);                                                             // Siapkan prepared statement
$stmt->bind_param('i', $userId);                                                          // Bind parameter user_id (integer) ke query
$stmt->execute();                                                                         // Eksekusi query
$res  = $stmt->get_result();                                                              // Ambil hasil eksekusi query
$rows = $res->fetch_all(MYSQLI_ASSOC);                                                    // Ambil semua baris hasil sebagai array asosiatif
$stmt->close();                                                                           // Tutup statement
?>
<!doctype html>                                                                           <!-- Deklarasi dokumen HTML5 -->
<html lang="id">                                                                          <!-- Set bahasa dokumen: Indonesia -->
<head>
  <meta charset="utf-8">                                                                  <!-- Encoding karakter UTF-8 -->
  <title>Notifikasi — Admin</title>                                                       <!-- Judul halaman di tab browser -->
  <meta name="viewport" content="width=device-width,initial-scale=1">                     <!-- Supaya tampilan responsif di mobile -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"> <!-- Load ikon Bootstrap -->
  <style>
    :root{
      --gold:#FFD54F;                                                                     /* Warna utama emas */
      --page:#FFFDF8;                                                                     /* Warna dasar halaman (off-white) */
      --ink:#111827;                                                                      /* Warna teks utama */
      --muted:#6b7280;                                                                    /* Warna teks sekunder/abu */
      --unread:#FFEBA6; /* latar kuning lembut utk unread */                             /* Warna latar khusus notifikasi belum dibaca */
    }
    *{
      box-sizing:border-box;                                                              /* Gunakan box-sizing border-box di semua elemen */
      font-family:Poppins,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;     /* Font global halaman */
    }
    body{ background:var(--page); color:var(--ink); margin:0; }                           /* Style body: warna latar, warna teks, hilangkan margin */

    /* ===== TOPBAR (clean seperti settings / karyawan) ===== */
    .topbar{
      position:sticky; top:0; z-index:50;                                                 /* Topbar selalu menempel di atas saat scroll */
      background:#fff; border-bottom:1px solid #efefef;                                   /* Latar putih dengan garis bawah tipis */
    }
    .topbar .inner{
      max-width:100%;                                                                     /* Lebar inner full container */
      padding:12px 32px;                                                                  /* Padding kiri kanan topbar */
      display:flex; align-items:center; gap:8px; min-height:52px;                         /* Flex row, rata tengah, dan tinggi minimum */
    }
    .back-link{
      display:inline-flex; align-items:center; gap:10px;                                  /* Tombol kembali: ikon + teks sejajar */
      color:var(--ink); text-decoration:none; font-weight:600; font-size:1rem;           /* Style teks link kembali */
    }
    .back-link .chev{ font-size:18px; line-height:1; }                                    /* Ukuran ikon panah kembali */

    /* ===== WRAPPER ===== */
    .page{ padding:16px 32px 70px; }                                                      /* Wrapper konten utama dengan padding */

    /* ===== NOTIF CARD ===== */
    .notif-card{
      background:#fff; border-radius:18px;                                                /* Kartu notifikasi dengan sudut membulat */
      padding:16px 20px 14px;                                                             /* Padding dalam kartu */
      transition:box-shadow .15s ease;                                                    /* Transisi halus untuk bayangan saat hover */
      cursor:pointer;                                                                     /* Kartu bisa diklik */
      box-shadow:0 1px 2px rgba(0,0,0,0.02);                                              /* Bayangan halus default */
      border:1px solid rgba(0,0,0,.015);                                                  /* Border sangat tipis */
    }
    .notif-card + .notif-card{ margin-top:14px; }                                         /* Jarak vertikal antar kartu */
    .notif-card:hover{ box-shadow:0 4px 16px rgba(0,0,0,0.04); }                          /* Bayangan lebih kuat saat hover */
    .notif-unread{
      background:var(--unread);                                                           /* Latar kuning lembut untuk notif unread */
      border-color:rgba(255,213,79,.30);                                                  /* Border sedikit lebih jelas */
    }
    .notif-msg{ font-size:.95rem; line-height:1.6; }                                      /* Style teks pesan notifikasi */
    .notif-time{ font-size:.8rem; color:var(--muted); margin-top:4px; }                   /* Style teks waktu (x menit lalu) */
    .empty-box{
      background:#fff; border-radius:18px; border:1px dashed rgba(0,0,0,.04);             /* Kotak kosong saat belum ada notif */
      text-align:center; padding:40px 24px; color:var(--muted);                           /* Pesan "Belum ada notifikasi" di tengah */
    }

    @media (max-width: 720px){
      .topbar .inner{ padding:12px 16px; }                                                /* Kurangi padding topbar di layar kecil */
      .page{ padding:12px 16px 40px; }                                                    /* Sesuaikan padding konten di mobile */
      .notif-card{ border-radius:14px; padding:14px 14px 12px; }                           /* Kartu sedikit lebih kecil di mobile */
    }
  </style>
</head>
<body>
  <!-- HEADER -->
  <header class="topbar">                                                                  <!-- Bagian header/topbar -->
    <div class="inner">                                                                    <!-- Kontainer dalam topbar -->
      <a href="<?= htmlspecialchars($BASE) ?>/public/admin/index.php" class="back-link">   <!-- Link kembali ke dashboard admin -->
        <i class="bi bi-arrow-left chev"></i>                                              <!-- Ikon panah kembali -->
        <span>Kembali</span>                                                               <!-- Teks "Kembali" -->
      </a>
    </div>
  </header>

  <!-- LIST NOTIF -->
  <main class="page">                                                                      <!-- Kontainer utama daftar notifikasi -->
    <?php if (!count($rows)): ?>                                                          <!-- Jika tidak ada notifikasi -->
      <div class="empty-box">Belum ada notifikasi.</div>                                  <!-- Tampilkan pesan kosong -->
    <?php else: ?>                                                                        <!-- Jika ada notifikasi -->
      <?php foreach ($rows as $n): ?>                                                     <!-- Loop setiap notifikasi -->
        <?php
          // fallback link: jika pesan terkait order/invoice, arahkan ke halaman orders admin // Komentar: logika fallback link
          $link = $n['link'] ?? '';                                                        // Ambil link dari database jika ada
          $msg  = $n['message'] ?? '';                                                     // Ambil pesan notifikasi
          if (!$link && (stripos($msg, 'pesanan') !== false || stripos($msg, 'invoice') !== false)) { // Jika tidak ada link tapi isi pesan menyebut pesanan/invoice
            $link = $BASE . '/public/admin/orders.php';                                    // Gunakan link default ke halaman orders
          }
        ?>
        <div
          class="notif-card <?= $n['status'] === 'unread' ? 'notif-unread' : '' ?>"        // Tambahkan kelas notif-unread jika status 'unread'
          <?php if ($link): ?> data-link="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?> <!-- Jika ada link, simpan di atribut data-link -->
        >
          <div class="notif-msg">
            <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?>                             <!-- Tampilkan pesan notifikasi dengan escape aman -->
          </div>
          <div class="notif-time" data-time="<?= htmlspecialchars($n['created_at'], ENT_QUOTES, 'UTF-8') ?>"></div> <!-- Tempat teks waktu relatif (x menit lalu) -->
        </div>
      <?php endforeach; ?>                                                                 <!-- Akhir loop notifikasi -->
    <?php endif; ?>                                                                        <!-- Akhir blok if notif kosong -->
  </main>

  <script>
    const BASE = '<?= $BASE ?>';                                                           // Konstanta JS untuk base URL (dari PHP)

    // format waktu "x menit lalu"                                                         // Komentar: fungsi untuk format waktu relatif
    function formatTimeAgo(dateStr){
      const t = new Date(dateStr).getTime();                                               // Konversi string waktu ke timestamp (ms)
      if (isNaN(t)) return dateStr;                                                        // Jika gagal parse, kembalikan string asli
      const diff = (Date.now() - t)/1000;                                                  // Hitung selisih detik dari sekarang
      if (diff < 60)   return Math.floor(diff) + " detik lalu";                            // < 1 menit → detik lalu
      if (diff < 3600) return Math.floor(diff/60) + " menit lalu";                         // < 1 jam → menit lalu
      if (diff < 86400) return Math.floor(diff/3600) + " jam lalu";                        // < 1 hari → jam lalu
      if (diff < 604800) return Math.floor(diff/86400) + " hari lalu";                     // < 7 hari → hari lalu
      return new Date(dateStr).toLocaleString("id-ID");                                    // Lebih lama → tampilkan tanggal lengkap lokal ID
    }
    function refreshTimes(){
      document.querySelectorAll(".notif-time[data-time]").forEach(el=>{                    // Loop semua elemen yang punya data-time
        el.textContent = formatTimeAgo(el.dataset.time);                                   // Update teks dengan formatTimeAgo
      });
    }
    refreshTimes();                                                                        // Panggil sekali saat halaman pertama kali load
    setInterval(refreshTimes, 10_000);                                                     // Refresh teks waktu setiap 10 detik

    // klik kartu → buka link (jika ada)                                                   // Komentar: handler klik kartu notifikasi
    document.querySelectorAll(".notif-card[data-link]").forEach(card=>{                    // Seleksi semua kartu yang punya data-link
      card.addEventListener("click", ()=>{                                                 // Tambah event klik
        const link = card.getAttribute("data-link");                                       // Ambil nilai link dari atribut
        if (link) location.href = link;                                                    // Jika ada, redirect ke URL tersebut
      });
    });

    // setelah halaman tampil, tandai semua dibaca untuk role=admin                        // Komentar: mark all read setelah halaman dilihat
    fetch(`${BASE}/backend/api/notifications.php?action=mark_all_read&role=admin`, {       // Panggil endpoint mark_all_read utk admin
      credentials: "same-origin"                                                           // Sertakan cookie session
    }).catch(()=>{});                                                                      // Abaikan error (tidak ganggu UI)
  </script>
</body>
</html>
