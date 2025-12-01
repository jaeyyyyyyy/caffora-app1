<?php
// =============================================================
// public/customer/notifications.php
// Halaman Notifikasi Customer
// - Menampilkan daftar notifikasi untuk user tertentu
// - Notifikasi bisa berasal dari broadcast (role = customer)
// - Notifikasi unread diberi highlight kuning lembut
// =============================================================

declare(strict_types=1); // Aktifkan strict types (type checking lebih ketat)
session_start();        // Mulai atau lanjutkan sesi PHP untuk akses $_SESSION

// Wajib login sebagai customer
require_once __DIR__.'/../../backend/auth_guard.php'; // Import guard otentikasi
require_login(['customer']);                            // Batasi akses untuk role "customer" saja

// Import konfigurasi database (variabel $db_host, $db_user, dll)
require_once __DIR__.'/../../backend/config.php';

// =============================================================
// Koneksi database (mysqli manual karena file lain juga pakai)
// =============================================================
// Buat object koneksi mysqli dengan parameter dari config.php
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Jika koneksi gagal, berhenti dan tampilkan pesan
if ($conn->connect_error) {
    exit('Koneksi gagal: '.$conn->connect_error);
}

// Set karakter koneksi ke utf8mb4 (mendukung emoji, dsb.)
$conn->set_charset('utf8mb4');

// Ambil user_id dari session, cast ke integer (hindari string)
$userId = (int) ($_SESSION['user_id'] ?? 0);

// =============================================================
// Ambil notifikasi
// - Notif untuk user tertentu (user_id = user saat ini)
// - Notif broadcast untuk role customer:
//     user_id IS NULL dan (role IS NULL atau role = 'customer')
// - Urutkan dari yang terbaru ke lama (created_at DESC)
// - Batasi 100 baris (LIMIT 100)
// =============================================================
$sql = "
  SELECT id, user_id, role, message, status, created_at, link
  FROM notifications
  WHERE user_id = ?
     OR (user_id IS NULL AND (role IS NULL OR role = 'customer'))
  ORDER BY created_at DESC
  LIMIT 100
";

// Siapkan prepared statement dengan query di atas
$stmt = $conn->prepare($sql);

// Binding parameter: 'i' = integer (user_id)
$stmt->bind_param('i', $userId);

// Eksekusi query
$stmt->execute();

// Ambil hasil eksekusi dalam bentuk mysqli_result
$res = $stmt->get_result();

// Simpan semua baris hasil ke dalam array $notifs
$notifs = [];
while ($row = $res->fetch_assoc()) {
    $notifs[] = $row; // Tambahkan tiap baris (assoc array) ke array notifs
}

// Tutup statement (resource tidak dipakai lagi)
$stmt->close();

// Base URL aplikasi (DIHARDCODE: sesuaikan dengan folder project di server)
// Contoh: jika project di http://localhost/caffora-app1, maka BASE = /caffora-app1
$BASE = '/caffora-app1';
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8"> <!-- Encoding UTF-8 -->
  <title>Notifikasi — Caffora</title> <!-- Judul tab browser -->
  <meta name="viewport" content="width=device-width,initial-scale=1"> <!-- Responsif di mobile -->

  <!-- Icon Bootstrap (untuk ikon panah kembali, dll.) -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    rel="stylesheet"
  >

  <style>
    :root{
      /* Variabel warna utama aplikasi */
      --gold:#FFD54F;       /* Kuning emas untuk highlight / aksen */
      --brown:#4B3F36;      /* Coklat brand Caffora */
      --page:#FFFDF8;       /* Warna dasar halaman */
      --ink:#111827;        /* Warna teks utama (hitam kebiruan) */
      --muted:#6b7280;      /* Warna teks sekunder (abu-abu) */
      --line:rgba(0,0,0,.03); /* Warna garis halus */
    }

    *{
      box-sizing:border-box; /* Padding + border masuk perhitungan width/height */
      font-family:Poppins,system-ui,-apple-system,
                   "Segoe UI",Roboto,Arial,sans-serif; /* Font utama halaman */
    }

    body{
      background:var(--page); /* Background halaman lembut */
      color:var(--ink);       /* Warna teks default */
      margin:0;               /* Hilangkan margin default body */
    }

    /* ================= TOPBAR ================= */
    .topbar{
      position:sticky;             /* Menempel di atas saat scroll */
      top:0;                       /* Posisi top 0 */
      z-index:50;                  /* Di atas elemen lain */
      background:#fff;             /* Background putih topbar */
      border-bottom:1px solid #efefef; /* Garis bawah tipis */
    }

    .topbar .inner{
      max-width:1200px;            /* Batas lebar konten topbar */
      margin:0 auto;               /* Center horizontal */
      padding:12px 24px;           /* Ruang dalam kiri-kanan dan atas-bawah */
      min-height:52px;             /* Tinggi minimum topbar */
      display:flex;                /* Flex untuk menyusun isi */
      align-items:center;          /* Center vertikal */
      gap:8px;                     /* Jarak antar elemen */
    }

    .back-link{
      display:inline-flex;         /* Link tampil sebagai inline-flex */
      align-items:center;          /* Center vertikal ikon dan teks */
      gap:10px;                    /* Jarak ikon-panah dan teks */
      color:var(--ink);            /* Warna teks link */
      text-decoration:none;        /* Hilangkan underline */
      font-weight:600;             /* Tebal (semi-bold) */
      font-size:1rem;              /* 16px */
      line-height:1.3;             /* Jarak baris */
    }

    .back-link .chev{
      font-size:18px;              /* Ukuran ikon panah kiri */
      line-height:1;               /* Line-height kecil agar rapat */
    }

    /* ================= WRAPPER ================= */
    .page{
      max-width:1200px;            /* Batas lebar konten utama */
      margin:0 auto;               /* Center di tengah */
      padding:14px 24px 56px;      /* Padding (atas, samping, bawah) */
    }

    /* ================= CARD NOTIF ================= */
    .notif-card{
      background:#fff;                     /* Card putih */
      border:1px solid rgba(75,63,54,.04); /* Border sangat tipis */
      border-radius:18px;                  /* Sudut membulat */
      padding:16px 20px 14px;              /* Ruang dalam card */
      display:flex;                        /* Flex untuk konten di dalamnya */
      gap:14px;                            /* Jarak antar elemen di dalam card */
      cursor:pointer;                      /* Kursor tangan saat hover */
      transition:box-shadow .15s ease;     /* Animasi halus untuk shadow */
      box-shadow:0 1px 2px rgba(0,0,0,0.02); /* Shadow sangat lembut */
    }

    /* Jarak antar card: card kedua dan seterusnya punya margin-top */
    .notif-card + .notif-card{
      margin-top:12px;
    }

    /* Efek hover: sedikit naik/shadow lebih besar */
    .notif-card:hover{
      box-shadow:0 4px 16px rgba(0,0,0,0.04);
    }

    /* Notifikasi belum dibaca: background kuning lembut */
    .notif-unread{
      background:#FFF3C4;                 /* Kuning pastel */
      border-color:rgba(255,213,79,.35);  /* Border kuning sedikit lebih kuat */
    }

    .notif-body{
      flex:1; /* Isi notifikasi penuhi ruang card */
    }

    .notif-msg{
      font-size:.95rem;       /* Ukuran teks pesan */
      line-height:1.6;        /* Jarak antar baris pesan */
      color:#111827;          /* Teks utama notifikasi */
    }

    .notif-time{
      font-size:.8rem;        /* Ukuran teks waktu */
      color:var(--muted);     /* Warna abu-abu lembut */
      margin-top:4px;         /* Jarak di bawah pesan */
    }

    /* Tampilan jika tidak ada notifikasi sama sekali */
    .empty-box{
      background:#fff;                          /* Card putih */
      border-radius:18px;                       /* Sudut membulat */
      border:1px dashed rgba(0,0,0,.04);        /* Border garis putus-putus */
      text-align:center;                        /* Teks di tengah */
      padding:40px 24px;                        /* Ruang dalam card kosong */
      color:var(--muted);                       /* Warna teks abu lembut */
    }

    /* ================= MOBILE ================= */
    @media (max-width:700px){
      .topbar .inner{
        max-width:100%;         /* Di mobile, pakai lebar penuh */
        padding:12px 16px;      /* Kurangi padding samping */
      }

      .page{
        max-width:100%;         /* Konten utama juga lebar penuh */
        padding:12px 16px 8px;  /* Padding lebih hemat di bawah */
      }

      .notif-card{
        border-radius:14px;     /* Radius card sedikit lebih kecil */
        padding:14px 14px 12px; /* Padding card diperkecil */
      }
    }
  </style>
</head>

<body>

  <!-- ================= HEADER ================= -->
  <div class="topbar">
    <div class="inner">
      <!-- Link kembali ke halaman index customer -->
      <a
        class="back-link"
        href="<?= htmlspecialchars($BASE) ?>/public/customer/index.php"
      >
        <!-- Ikon panah kiri dari Bootstrap Icons -->
        <i class="bi bi-arrow-left chev"></i>
        <!-- Teks "Kembali" -->
        <span>Kembali</span>
      </a>
    </div>
  </div>

  <!-- ================= LIST NOTIF ================= -->
  <main class="page">
    <?php if (! count($notifs)) { ?>

      <!-- Jika array notifs kosong → tampilkan box kosong -->
      <div class="empty-box">Belum ada notifikasi.</div>

    <?php } else { ?>

      <!-- Loop notifikasi jika ada data -->
      <?php foreach ($notifs as $n) { ?>
        <div
          <!-- Class dasar kartu notifikasi + class notif-unread jika status = 'unread' -->
          class="notif-card <?= $n['status'] === 'unread' ? 'notif-unread' : '' ?>"
          <?php if (! empty($n['link'])) { ?>
            <!-- Jika field link tidak kosong, simpan sebagai data-link (untuk JS redirect) -->
            data-link="<?= htmlspecialchars($n['link'], ENT_QUOTES, 'UTF-8') ?>"
          <?php } ?>
        >
          <div class="notif-body">
            <!-- Pesan notifikasi (diproteksi dengan htmlspecialchars) -->
            <div class="notif-msg">
              <?= htmlspecialchars($n['message'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            </div>

            <!-- Waktu notifikasi dalam atribut data-time (nanti diformat JS jadi "x menit lalu") -->
            <div
              class="notif-time"
              data-time="<?= htmlspecialchars($n['created_at'], ENT_QUOTES, 'UTF-8') ?>"
            ></div>
          </div>
        </div>
      <?php } ?>

    <?php } ?>
  </main>

  <script>
    // ============================================================
    // Fungsi helper: formatTimeAgo
    // Mengubah tanggal (dateStr) menjadi teks "x menit lalu", "x jam lalu", dll.
    // ============================================================
    function formatTimeAgo(dateStr){
      const t = new Date(dateStr).getTime(); // Ubah string waktu ke timestamp (ms)
      if (isNaN(t)) return dateStr;          // Jika tidak valid, kembalikan apa adanya

      const diff = (Date.now() - t)/1000;    // Selisih waktu sekarang - t (dalam detik)

      // Jika kurang dari 60 detik
      if (diff < 60)     return Math.floor(diff) + " detik lalu";
      // Jika kurang dari 1 jam
      if (diff < 3600)   return Math.floor(diff/60) + " menit lalu";
      // Jika kurang dari 1 hari
      if (diff < 86400)  return Math.floor(diff/3600) + " jam lalu";
      // Jika kurang dari 7 hari
      if (diff < 604800) return Math.floor(diff/86400) + " hari lalu";

      // Jika lebih dari 7 hari, tampilkan tanggal lengkap lokal (Indonesia)
      return new Date(dateStr).toLocaleString("id-ID");
    }

    // Update semua label waktu di halaman (class .notif-time)
    function refreshTimes(){
      document.querySelectorAll(".notif-time[data-time]").forEach(el=>{
        // Ganti isi teks dengan hasil formatTimeAgo dari atribut data-time
        el.textContent = formatTimeAgo(el.dataset.time);
      });
    }

    // Panggil sekali saat halaman load
    refreshTimes();
    // Lalu update lagi setiap 10 detik supaya "x detik/menit lalu" tetap akurat
    setInterval(refreshTimes, 10000); // update tiap 10 detik

    // Klik kartu notifikasi → jika ada atribut data-link, redirect ke URL tersebut
    document.querySelectorAll(".notif-card[data-link]").forEach(card=>{
      card.addEventListener("click", ()=>{
        const link = card.getAttribute("data-link"); // Ambil nilai data-link dari card
        if (link) window.location.href = link;       // Pindah halaman ke link
      });
    });

    // Tandai semua notif sebagai read (background kuning dihilangkan di server)
    // Dipanggil sekali saat halaman dibuka
    fetch("<?= $BASE ?>/backend/api/notifications.php?action=mark_all_read", {
      credentials: "same-origin" // Sertakan cookie/session (supaya tahu user mana)
    }).catch(()=>{});            // Jika error (misal offline), abaikan tanpa ganggu UI
  </script>

</body>
</html>
