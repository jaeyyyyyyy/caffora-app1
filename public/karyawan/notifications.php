<?php
// public/karyawan/notifications.php

// Mengaktifkan strict typing agar tipe variabel lebih ketat
declare(strict_types=1);

// Memulai atau melanjutkan session
session_start();

// Memuat konfigurasi (database, BASE_URL, dsb.)
require_once __DIR__ . '/../../backend/config.php';

// Melakukan pengecekan akses untuk memastikan hanya karyawan yang boleh masuk
if (
    ! isset($_SESSION['user_id'])             // Cek apakah user sudah login
    || (($_SESSION['user_role'] ?? '') !== 'karyawan')   // Cek apakah role user adalah karyawan
) {

    // Redirect ke halaman login jika tidak memenuhi persyaratan
    header('Location: ' . BASE_URL . '/public/login.html');

    // Hentikan eksekusi script
    exit;
}

// Ambil user ID dari session, cast ke integer
$userId = (int) ($_SESSION['user_id'] ?? 0);

// Alias BASE_URL untuk mempersingkat pemanggilan variabel
$BASE   = BASE_URL;

// Catatan: Jangan menandai notifikasi sebagai telah dibaca di sini
// Karena penandaan read dilakukan melalui JavaScript setelah halaman dimuat

// Mengambil daftar notifikasi (notifikasi pengguna & broadcast karyawan)
$sql = "
  SELECT id, message, status, created_at, link
  FROM notifications
  WHERE user_id = ?
     OR (user_id IS NULL AND (role IS NULL OR role = 'karyawan'))
  ORDER BY created_at DESC
  LIMIT 100
";

// Menyiapkan query agar aman dari SQL injection
$stmt = $conn->prepare($sql);

// Mengikat parameter user_id sebagai tipe integer
$stmt->bind_param('i', $userId);

// Mengeksekusi query
$stmt->execute();

// Mengambil hasil query
$res  = $stmt->get_result();

// Menyimpan hasil dalam bentuk array associative
$rows = $res->fetch_all(MYSQLI_ASSOC);

// Menutup statement untuk melepaskan resource
$stmt->close();

// Menutup blok PHP, lanjut proses ke HTML
?>

<!-- Deklarasi struktur dokumen HTML5 -->
<!doctype html>

<!-- Tag pembuka HTML dengan bahasa Indonesia -->
<html lang="id">

<!-- Awal blok head untuk metadata dokumen -->
<head>

  <!-- Encoding karakter UTF-8 untuk dukungan bahasa internasional -->
  <meta charset="utf-8">

  <!-- Judul halaman pada tab browser -->
  <title>Notifikasi — Karyawan</title>

  <!-- Viewport untuk tampilan responsif pada perangkat mobile -->
  <meta
    name="viewport"
    content="width=device-width,initial-scale=1"
  >

  <!-- Load Bootstrap Icons dari CDN untuk penggunaan ikon -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    rel="stylesheet"
  >

  
<style>                                           

  :root{                                          /* root var start */
    --gold:   #FFD54F;                            /* warna emas */
    --page:   #FFFDF8;                            /* warna background */
    --ink:    #111827;                            /* warna teks utama */
    --muted:  #6b7280;                            /* warna teks sekunder */
    --unread: #FFEBA6;                            /* warna highlight unread */
  }                                               /* root var end */

  *{                                              /* global reset */
    box-sizing: border-box;                       /* hitung border dlm elemen */
    font-family: Poppins, system-ui,              /* font utama */
                 -apple-system, "Segoe UI",
                 Roboto, Arial, sans-serif;
  }                                               /* reset end */

  body{                                           /* body style start */
    background: var(--page);                      /* background halaman */
    color: var(--ink);                            /* warna teks */
    margin: 0;                                    /* remove margin default */
  }                                               /* body style end */

     .topbar{                                 /* Topbar navigasi */
    position:      sticky;                 /* Tetap di atas */
    top:           0;                      /* Y=0 */
    z-index:       50;                     /* Di depan */
    background:    #fff;                   /* Latar putih */
    border-bottom: 1px solid #efefef;      /* Garis bawah */
  }                                        /* End .topbar */

  .topbar .inner{                          /* Isi topbar */
    max-width:   1200px;                   /* Lebar maksimum */
    margin:      0 auto;                   /* Tengah */
    padding:     12px 16px;                /* Ruang dalam */
    min-height:  52px;                     /* Tinggi minimal */
    display:     flex;                     /* Flexbox */
    align-items: center;                   /* Vertikal center */
    gap:         8px;                      /* Jarak elemen */
  }                                        /* End .inner */

  .back-link{                              /* Tombol kembali */
    display:        inline-flex;           /* Flex inline */
    align-items:    center;                /* Center vertikal */
    gap:            10px;                  /* Jarak ikon-teks */
    color:          var(--ink);            /* Warna teks */
    text-decoration:none;                  /* Hilangkan underline */
    font-weight:    600;                   /* Semi-bold */
    font-size:      1rem;                  /* Ukuran teks */
  }                                        /* End .back-link */

  .back-link .chev{                        /* Ikon panah */
    font-size:   18px;                     /* Ukuran ikon */
    line-height: 1;                        /* Tinggi baris */
  }                                        /* End .chev */

  .page{                                   /* Wrapper konten */
    max-width: 1200px;                     /* Lebar max */
    margin:    0 auto;                     /* Tengah */
    padding:   12px 16px 56px;             /* Padding */
  }                                        /* End .page */

  /* ====================== NOTIF CARD=========== */
  .notif-card{                                    /* kartu notifikasi */
    background: #fff;                             /* warna kartu */
    border-radius: 18px;                          /* border radius */
    padding: 16px 20px 14px;                      /* padding dalam */
    transition: box-shadow .15s ease;             /* animasi hover */
    cursor: pointer;                              /* dapat diklik */
    box-shadow: 0 1px 2px rgba(0,0,0,0.02);        /* bayangan awal */
    border: 1px solid rgba(0,0,0,.015);           /* border halus */
  }                                               /* notif-card end */

  .notif-card + .notif-card{                      /* spacing antar kartu */
    margin-top: 14px;                             /* jarak vertikal */
  }                                               /* end */

  .notif-card:hover{                              /* efek hover */
    box-shadow: 0 4px 16px rgba(0,0,0,0.04);       /* bayangan lebih besar */
  }                                               /* hover end */

  .notif-unread{                                  /* highlight unread */
    background: var(--unread);                    /* warna kuning */
    border-color: rgba(255,213,79,.3);            /* border soft */
  }                                               /* unread end */

  .notif-msg{                                     /* teks pesan */
    font-size: .95rem;                            /* ukuran teks */
    line-height: 1.6;                             /* spasi */
  }                                               /* msg end */

  .notif-time{                                    /* waktu notif */
    font-size: .8rem;                             /* kecil */
    color: var(--muted);                          /* abu soft */
    margin-top: 4px;                              /* jarak atas */
  }                                               /* time end */

  .empty-box{                                     /* card kosong */
    background: #fff;                             /* background */
    border-radius: 18px;                          /* sudut */
    border: 1px dashed rgba(0,0,0,.04);           /* style border */
    text-align: center;                           /* rata tengah */
    padding: 40px 24px;                           /* ruang dalam */
    color: var(--muted);                          /* warna teks */
  }                                               /* empty-box end */

  /* =================RESPONSIVE================= */
  @media (max-width: 720px){                     /* breakpoint mobile */
    .topbar .inner{ padding: 12px 16px; }         /* pengecilan topbar */
    .page{ padding: 12px 16px 40px; }             /* wrapper lebih kecil */
    .notif-card{                                  /* card responsive */
      border-radius: 14px;                        /* radius kecil */
      padding: 14px 14px 12px;                    /* padding kecil */
    }                                             /* notif end */
  }                                               /* media end */

</style>                                        

  
        <!-- Penutup bagian head -->
</head>

        <!-- Mulai elemen body halaman -->
<body>

        <!-- Awal elemen header halaman notifikasi -->
  <header class="topbar">

          <!-- Wrapper isi topbar -->
    <div class="inner">

            <!-- Tombol kembali menuju dashboard karyawan -->
      <a href="<?= htmlspecialchars($BASE) ?>/public/karyawan/index.php" class="back-link">

              <!-- Ikon panah sebagai simbol kembali -->
        <i class="bi bi-arrow-left chev"></i>

              <!-- Teks label tombol kembali -->
        <span>Kembali</span>

      </a>
            <!-- Penutup link tombol kembali -->

    </div>
          <!-- Penutup wrapper .inner -->

  </header>
        <!-- Penutup elemen header -->


  <!-- LIST NOTIF -->
  <main class="page">
    <?php if (! count($rows)) { ?>
      <div class="empty-box">Belum ada notifikasi.</div>
    <?php } else { ?>
      <?php foreach ($rows as $n) { ?>
        <?php
          // Ambil link asli dari DB (kalau ada)
          $link = $n['link'] ?? '';

          // Normalisasi link lama yang masih mengarah ke /public/... atau /caffora-app1/public/...
          if ($link) {
              $link = str_replace(
                  [
                      $BASE . '/caffora-app1/public/karyawan/orders.php',
                      $BASE . '/public/karyawan/orders.php',
                      '/caffora-app1/public/karyawan/orders.php',
                      '/public/karyawan/orders.php'
                  ],
                  $BASE . '/karyawan/orders',
                  $link
              );
          }

          // Fallback: kalau tidak ada link tapi pesannya mengandung "invoice",
          // arahkan ke halaman daftar pesanan karyawan yang baru.
          if (! $link && stripos($n['message'] ?? '', 'invoice') !== false) {
              $link = $BASE . '/karyawan/orders';
          }
        ?>
        <div
          class="notif-card <?= $n['status'] === 'unread' ? 'notif-unread' : '' ?>"
          <?php if ($link) { ?>
            data-link="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>"
          <?php } ?>
        >
          <div class="notif-msg">
            <?= htmlspecialchars($n['message'] ?? '', ENT_QUOTES, 'UTF-8') ?>
          </div>
          <div
            class="notif-time"
            data-time="<?= htmlspecialchars($n['created_at'], ENT_QUOTES, 'UTF-8') ?>"
          ></div>
        </div>
      <?php } ?>
    <?php } ?>
  </main>

<script>
    // Fungsi untuk memformat waktu menjadi format relatif (x menit lalu)
    function formatTimeAgo(dateStr){

      // Konversi string tanggal menjadi timestamp (millisecond)
      const t = new Date(dateStr).getTime();

      // Jika invalid, kembalikan teks asli
      if (isNaN(t)) return dateStr;

      // Hitung selisih waktu saat ini dengan waktu notif dalam detik
      const diff = (Date.now() - t) / 1000;

      // Kondisi selisih < 1 menit
      if (diff < 60)
        return Math.floor(diff) + " detik lalu";

      // Kondisi selisih < 1 jam
      if (diff < 3600)
        return Math.floor(diff / 60) + " menit lalu";

      // Kondisi selisih < 1 hari
      if (diff < 86400)
        return Math.floor(diff / 3600) + " jam lalu";

      // Kondisi selisih < 7 hari
      if (diff < 604800)
        return Math.floor(diff / 86400) + " hari lalu";

      // Jika lebih dari seminggu, tampilkan format tanggal normal
      return new Date(dateStr).toLocaleString("id-ID");
    }

    // Fungsi untuk memperbarui semua elemen waktu notifikasi
    function refreshTimes(){

      // Ambil semua elemen dengan class .notif-time dan atribut data-time
      document.querySelectorAll(".notif-time[data-time]").forEach(el => {

        // Isi teks dengan hasil formatTimeAgo()
        el.textContent = formatTimeAgo(el.dataset.time);
      });
    }

    // Panggil refreshTimes pertama kali saat halaman dimuat
    refreshTimes();

    // Update waktu setiap 10 detik agar tetap akurat
    setInterval(refreshTimes, 10_000);

    // Pasang event klik pada setiap kartu notifikasi yang memiliki data-link
    document.querySelectorAll(".notif-card[data-link]").forEach(card => {

      // Tutup aksi klik kartu
      card.addEventListener("click", () => {

        // Ambil nilai atribut data-link
        const link = card.getAttribute("data-link");

        // Jika ada, redirect browser ke halaman tujuan
        if (link)
          window.location.href = link;
      });
    });

    // Tandai semua notifikasi sebagai 'read' setelah halaman tampil
    fetch("<?= $BASE ?>/backend/api/notifications.php?action=mark_all_read&role=karyawan", {

      // Sertakan cookie hanya ke origin yang sama
      credentials: "same-origin"

    // Abaikan error supaya UI tetap stabil
    }).catch(() => {});
</script>

</body>
</html>
