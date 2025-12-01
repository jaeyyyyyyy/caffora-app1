<?php  
// File: public/customer/faq.php (halaman FAQ khusus customer)

 // Aktifkan strict typing untuk memastikan tipe data lebih ketat
declare(strict_types=1);

 // Import file auth_guard.php untuk menangani pengecekan login & role
require_once __DIR__ . '/../../backend/auth_guard.php';

 // Wajib login dengan role "customer" sebelum boleh mengakses halaman ini
require_login(['customer']);
?>

<!-- Deklarasi dokumen HTML5 -->
<!DOCTYPE html>

<!-- Atur bahasa dokumen menjadi Bahasa Indonesia -->
<html lang="id">

<head>

  <!-- Set encoding karakter halaman ke UTF-8 -->
  <meta charset="utf-8" />

  <!-- Judul halaman yang tampil di tab browser -->
  <title>FAQ — Caffora</title>

  <!-- Pengaturan agar layout responsif di semua perangkat -->
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- Load font Poppins dari Google Fonts -->
  <link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap"
    rel="stylesheet"
  />

  <!-- Load CSS Bootstrap 5 dari CDN -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
  />

  <!-- Load Bootstrap Icons dari CDN -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    rel="stylesheet"
  />


  <style>
    :root{                                       /* Variabel tema global */
  --gold:#ffd54f;                                /* Warna emas brand */
  --ink:#222;                                    /* Warna teks utama */
  --brown:#4b3f36;                               /* Warna coklat khas Caffora */
  --bg:#fafbfc;                                  /* Background lembut */
  --line:#eceff3;                                /* Garis pemisah halus */
}                                                /* End :root */

/* global, sama seperti checkout */
* {                                              /* Style dasar semua elemen */
  font-family: Poppins, system-ui, -apple-system, "Segoe UI",
               Roboto, Arial, sans-serif;        /* Font default halaman */
  box-sizing: border-box;                        /* Hitung padding + border */
}

body {                                           /* Style body utama */
  background: var(--bg);                         /* Warna background */
  color: var(--ink);                             /* Warna teks */
  margin: 0;                                     /* Reset margin default */
}

/* ====== TOPBAR (copy dari checkout) ====== */
.topbar{                                         /* Bar atas */
  background:#fff;                               /* Latar putih */
  border-bottom:1px solid rgba(0,0,0,.05);       /* Border bawah tipis */
  position:sticky;                               /* Tetap di atas */
  top:0;                                         /* Posisi top 0 */
  z-index:20;                                    /* Di atas elemen lain */
}

.topbar-inner{                                   /* Isi topbar */
  max-width:1200px;                              /* Lebar maksimum */
  margin:0 auto;                                  /* Tengah horizontal */
  padding:12px 24px;                             /* Padding dalam */
  min-height:52px;                               /* Tinggi minimum */
  display:flex;                                   /* Flex container */
  align-items:center;                             /* Vertical center */
  gap:10px;                                      /* Jarak antar elemen */
}

.back-link{                                      /* Tombol kembali */
  display:inline-flex;                           /* Flex inline */
  align-items:center;                            /* Ikon + teks center */
  gap:10px;                                      /* Jarak ikon-teks */
  color:var(--brown);                            /* Warna teks */
  text-decoration:none;                          /* Hilangkan underline */
  border:0;                                      /* Tanpa border */
  background:transparent;                        /* Background transparan */
  padding:0;                                     /* Reset padding */
}

/* Paksa teks “Kembali” pakai system-ui (Inter) */
.back-link span{                                 /* Teks "Kembali" */
  font-family: system-ui, -apple-system, "Segoe UI",
               Roboto, Arial, sans-serif !important;  /* Paksa system-ui */
  font-size:1rem;                                 /* Ukuran font */
  font-weight:600;                                /* Ketebalan */
  line-height:1.3;                                /* Tinggi baris */
}

/* Ikon panah 18x18 sama seperti checkout */
.back-link .bi{                                  /* Ikon panah */
  width:18px;                                     /* Lebar ikon */
  height:18px;                                    /* Tinggi ikon */
  display:inline-flex;                            /* Flex center */
  align-items:center;                             
  justify-content:center;
  font-size:18px !important;                      /* Ukuran ikon */
  line-height:18px !important;                    /* Tinggi baris */
}

/* ===== Layout ===== */
.wrap{                                            /* Pembungkus utama konten */
  max-width:1200px;                               /* Lebar maksimum */
  margin:10px auto 32px;                          /* Margin atas/bawah */
  padding:0 16px;                                 /* Padding samping */
}

/* ===== FAQ ===== */
.faq-list{                                        /* Container list FAQ */
  background:#fff;                                /* Latar putih */
  border-radius:0px;                              /* Sudut kotak */
  overflow:hidden;                                /* Hilangkan overflow */
  box-shadow:0 1px 3px rgba(0,0,0,.02);           /* Shadow tipis */
}

.faq-item{ border-bottom:1px solid var(--line); } /* Garis pemisah FAQ */

.faq-item:last-child{ border-bottom:0; }          /* Hilangkan di baris akhir */

.faq-q{                                           /* Tombol pembuka FAQ */
  width:100%;                                     /* Lebar penuh */
  text-align:left;                                /* Teks kiri */
  background:transparent;                         /* Tanpa latar */
  border:0;                                       /* Tanpa border */
  padding:12px 10px;                              /* Padding dalam */
  display:flex;                                   /* Susunan flex */
  align-items:center;                             
  justify-content:space-between;                  /* Teks kiri, ikon kanan */
  font-weight:500;                                /* Ketebalan */
  font-size:.95rem;                               /* Ukuran teks */
  color:var(--ink);                               /* Warna teks */
  transition:background .15s ease;                /* Hover lembut */
}

.faq-q:hover{ background:#fffbea; }               /* Hover kuning lembut */

.faq-q[aria-expanded="true"] .chev{              
  transform:rotate(180deg);                       /* Putar ikon saat dibuka */
}

.faq-a{                                           /* Jawaban FAQ */
  padding:0 10px 12px;                            /* Padding internal */
  color:var(--ink);                               /* Warna teks */
  font-size:.9rem;                                /* Ukuran */
  line-height:1.56;                               /* Jarak antar baris */
}

/* ===== Kontak ===== */
.contact{                                         /* Card kontak */
  margin-top:14px;                                /* Jarak atas */
  background:#fff;                                /* Background putih */
  border-radius:14px;                             /* Sudut membulat */
  padding:4px 10px;                               /* Padding */
  box-shadow:0 1px 3px rgba(0,0,0,.02);           /* Shadow lembut */
}

.c-row{                                           /* Baris satu kontak */
  display:grid;                                   /* Grid layout */
  grid-template-columns:24px 1fr 16px;            /* Ikon - teks - chevron */
  align-items:center;                             /* Rata tengah */
  gap:10px;                                       /* Jarak elemen */
  padding:10px 2px;                               /* Padding baris */
  text-decoration:none;                           /* Tanpa underline */
  color:var(--ink);                               /* Warna teks */
  font-weight:500;                                /* Teks tebal */
  font-size:.9rem;                                /* Ukuran font */
  border:none;                                    /* Tanpa border */
}

.c-row .bi{ font-size:1.05rem; color:var(--brown); } /* Ikon kontak */

.c-row .chev{ color:#a3a3a3; }                    /* Ikon panah */

.c-row.static{ grid-template-columns:24px 1fr; }  /* Tanpa chevron */

/* ===== Responsive ===== */
@media (max-width:576px){
  .topbar-inner,
  .wrap{
    max-width:100%;                               /* Lebar penuh */
    padding:12px 14px;                            /* Padding mobile */
  }
  .wrap{ margin:8px auto 28px; }                  /* Margin mobile */
  .faq-q{ font-size:.88rem; }                     /* Teks pertanyaan kecil */
  .faq-a{ font-size:.86rem; }                     /* Teks jawaban kecil */
  .c-row{ font-size:.88rem; padding:8px 0; }      /* Row kontak kecil */
}
</style>
<!-- Penutup tag head -->
</head>

<!-- Mulai body halaman -->
<body>

  <!-- Wrapper TOP BAR (struktur sama dengan checkout) -->
  <div class="topbar">

    <!-- Kontainer dalam topbar -->
    <div class="topbar-inner">

      <!-- Tombol kembali menggunakan history.back() -->
      <a class="back-link" href="javascript:history.back()" aria-label="Kembali">
        <i class="bi bi-arrow-left"></i>
        <span>Kembali</span>
      </a>

    </div>
  </div>

  <!-- Kontainer utama halaman FAQ -->
  <main class="wrap">

    <!-- Daftar FAQ -->
    <div class="faq-list" id="faq">

      <!-- Satu item FAQ: Pertanyaan + Jawaban -->
      <div class="faq-item">

        <!-- Tombol pembuka/penutup jawaban -->
        <button class="faq-q" data-bs-toggle="collapse" data-bs-target="#a1"
                aria-expanded="false" aria-controls="a1">
          <span>Bagaimana cara melakukan pemesanan?</span>
          <i class="bi bi-chevron-down chev"></i>
        </button>

        <!-- Wrapper jawaban FAQ -->
        <div id="a1" class="collapse">
          <div class="faq-a">
            Pilih menu di halaman <a href="index.php#menuSection">Product</a>,
            lalu tambah ke keranjang. Lanjutkan checkout di halaman keranjang.
          </div>
        </div>

      </div>

      <!-- Item FAQ kedua -->
      <div class="faq-item">

        <!-- Tombol pertanyaan -->
        <button class="faq-q" data-bs-toggle="collapse" data-bs-target="#a2"
                aria-expanded="false" aria-controls="a2">
          <span>Apakah tersedia layanan pengantaran?</span>
          <i class="bi bi-chevron-down chev"></i>
        </button>

        <!-- Jawaban FAQ -->
        <div id="a2" class="collapse">
          <div class="faq-a">
            Saat ini layanan kami hanya <strong>Dine-In</strong> dan
            <strong>Take-Away</strong>.
          </div>
        </div>

      </div>

      <!-- Item FAQ ketiga -->
      <div class="faq-item">

        <!-- Tombol pertanyaan -->
        <button class="faq-q" data-bs-toggle="collapse" data-bs-target="#a3"
                aria-expanded="false" aria-controls="a3">
          <span>Di mana saya bisa melihat status pesanan?</span>
          <i class="bi bi-chevron-down chev"></i>
        </button>

        <!-- Jawaban FAQ -->
        <div id="a3" class="collapse">
          <div class="faq-a">
            Semua pesanan aktif & riwayat bisa kamu lihat di
            <a href="history.php">Riwayat Pesanan</a>.
          </div>
        </div>

      </div>

      <!-- Item FAQ keempat -->
      <div class="faq-item">

        <!-- Tombol pertanyaan -->
        <button class="faq-q" data-bs-toggle="collapse" data-bs-target="#a4"
                aria-expanded="false" aria-controls="a4">
          <span>Bagaimana cara mengubah profil atau password?</span>
          <i class="bi bi-chevron-down chev"></i>
        </button>

        <!-- Jawaban FAQ -->
        <div id="a4" class="collapse">
          <div class="faq-a">
            Kamu bisa mengubah nama, nomor HP, foto, dan password di halaman
            <a href="profile.php">Profil</a>.
          </div>
        </div>

      </div>

    </div>

    <!-- Kontainer informasi kontak -->
    <div class="contact">

      <!-- Link WhatsApp -->
      <a class="c-row" href="https://wa.me/628782302337" target="_blank"
         rel="noopener" aria-label="WhatsApp Caffora">
        <i class="bi bi-whatsapp"></i>
        <span>WhatsApp</span>
        <i class="bi bi-chevron-right chev"></i>
      </a>

      <!-- Link Email -->
      <a class="c-row" href="mailto:cafforaproject@gmail.com"
         aria-label="Kirim Email ke Caffora">
        <i class="bi bi-envelope"></i>
        <span>Email</span>
        <i class="bi bi-chevron-right chev"></i>
      </a>

      <!-- Link Lokasi Maps -->
      <a class="c-row" href="https://maps.app.goo.gl/Fznq9KLusdNXH2RE7"
         target="_blank" rel="noopener" aria-label="Buka lokasi di Google Maps">
        <i class="bi bi-geo-alt"></i>
        <span>Jl. Kopi No. 123, Purwokerto</span>
        <i class="bi bi-chevron-right chev"></i>
      </a>

      <!-- Row statis jam operasional -->
      <div class="c-row static" role="group" aria-label="Jam operasional">
        <i class="bi bi-clock"></i>
        <span>08.00–22.00</span>
      </div>

    </div>
  </main>

  <!-- Load Bootstrap JS bundle (termasuk Collapse FAQ) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

<!-- Penutup HTML -->
</html>

