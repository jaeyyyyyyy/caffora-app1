<?php
// =======================================
//  FAQ Halaman Customer
//  - Wajib login (role: customer)
//  - Menampilkan pertanyaan yang sering ditanyakan
// =======================================

declare(strict_types=1); // Aktifkan strict types di PHP

// Import guard otentikasi
require_once __DIR__ . '/../../backend/auth_guard.php';
// Batasi akses hanya untuk role "customer"
require_login(['customer']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8" /> <!-- Encoding dokumen -->
  <title>FAQ — Caffora</title> <!-- Judul tab browser -->
  <meta name="viewport" content="width=device-width, initial-scale=1" /> <!-- Responsif di mobile -->

  <!-- Fonts & Framework -->
  <!-- Poppins untuk font utama -->
  <link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap"
    rel="stylesheet"
  />
  <!-- Bootstrap CSS -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
  />
  <!-- Bootstrap Icons -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    rel="stylesheet"
  />

  <style>
    :root{
      /* Warna brand & utilitas */
      --gold:#ffd54f;   /* Kuning emas */
      --ink:#222;       /* Teks utama */
      --brown:#4b3f36;  /* Coklat khas Caffora */
      --bg:#fafbfc;     /* Background halaman */
      --line:#eceff3;   /* Warna garis pemisah */
    }

    /* ============================
       GLOBAL STYLE
       ============================ */
    *{
      /* Font utama Poppins, fallback ke system fonts */
      font-family:"Poppins",
                  system-ui,
                  -apple-system,
                  Segoe UI,
                  Roboto, Arial, sans-serif !important;
      box-sizing:border-box; /* Border-box untuk kontrol layout */
    }

    html,body{
      background:var(--bg); /* Latar belakang lembut */
      color:var(--ink);     /* Warna teks utama */
      margin:0;             /* Hilangkan margin default */
    }

    /* ============================
       TOPBAR
       ============================ */
    .topbar{
      position:sticky;                  /* Menempel di atas saat scroll */
      top:0;
      z-index:20;                       /* Di atas konten lain */
      background:#fff;                  /* Background putih */
      border-bottom:1px solid rgba(0,0,0,.04); /* Garis bawah tipis */
    }

    .topbar .inner{
      max-width:1200px;                 /* Batas lebar konten topbar */
      margin:0 auto;                    /* Center horizontal */
      padding:12px 16px;                /* Ruang dalam */
      min-height:52px;                  /* Tinggi minimum topbar */
      display:flex;                     /* Flex untuk susunan isi */
      align-items:center;               /* Vertikal center */
      gap:10px;                         /* Jarak antar elemen */
    }

    /* tombol kembali */
    .back-link{
      display:inline-flex;              /* Button sebagai inline-flex */
      align-items:center;               /* Center ikon + teks */
      gap:8px;                          /* Jarak ikon & teks */
      background:transparent;           /* Tanpa background */
      border:0;                         /* Tanpa border */
      color:var(--brown);               /* Warna teks/ikon */
      font-weight:600;                  /* Tebal */
      font-size:16px;                   /* Ukuran teks */
      line-height:1.3;
      cursor:pointer;                   /* Kursor tangan */
    }

    .back-link i{
      font-size:18px !important;        /* Ukuran ikon panah */
      width:18px;
      height:18px;
      display:inline-flex;
      justify-content:center;
      align-items:center;
    }

    /* ============================
       LAYOUT WRAPPER
       ============================ */
    .wrap{
      max-width:1200px;     /* Batas lebar konten utama */
      margin:10px auto 32px;/* Margin atas & bawah */
      padding:0 16px;       /* Padding kiri-kanan */
    }

    /* ============================
       FAQ LIST
       ============================ */
    .faq-list{
      background:#fff;                    /* Card putih */
      border-radius:0;                    /* Tanpa radius (full width) */
      overflow:hidden;                    /* Sembunyikan konten di luar */
      box-shadow:0 1px 3px rgba(0,0,0,.02); /* Shadow sangat lembut */
    }

    .faq-item{
      border-bottom:1px solid var(--line); /* Garis pemisah antar FAQ */
    }
    .faq-item:last-child{
      border-bottom:0;                    /* Item terakhir tanpa garis bawah */
    }

    /* tombol pertanyaan */
    .faq-q{
      width:100%;                         /* Tombol selebar container */
      text-align:left;                    /* Teks rata kiri */
      background:transparent;             /* Tanpa background default */
      border:0;                           /* Tanpa border default button */
      padding:12px 10px;                  /* Ruang dalam */
      display:flex;                       /* Flex: teks kiri, ikon kanan */
      align-items:center;
      justify-content:space-between;
      font-weight:500;                    /* Semi-bold */
      font-size:.95rem;                   /* Ukuran teks pertanyaan */
      color:var(--ink);                   /* Warna teks */
      transition:background .15s ease;    /* Animasi hover halus */
    }

    .faq-q:hover{
      background:#fffbea;                 /* BG kekuningan saat hover */
    }

    /* Saat collapse terbuka, ikon chevron diputar 180 derajat */
    .faq-q[aria-expanded="true"] .chev{
      transform:rotate(180deg);
    }

    /* jawaban */
    .faq-a{
      padding:0 10px 12px;                /* Padding samping & bawah jawaban */
      font-size:.9rem;                    /* Ukuran teks jawaban */
      color:var(--ink);
      line-height:1.56;                   /* Line-height agar nyaman dibaca */
    }

    /* ============================
       KONTAK SECTION
       ============================ */
    .contact{
      margin-top:14px;                      /* Jarak dari FAQ list */
      background:#fff;                      /* Card putih */
      border-radius:14px;                   /* Sudut membulat */
      padding:4px 10px;                     /* Ruang dalam */
      box-shadow:0 1px 3px rgba(0,0,0,.02); /* Shadow lembut */
    }

    .c-row{
      display:grid;                         /* Grid layout: ikon - teks - chevron */
      grid-template-columns:24px 1fr 16px;  /* Kolom: ikon, teks, ikon kecil */
      align-items:center;                   /* Vertikal center */
      gap:10px;                             /* Jarak antar kolom */
      padding:10px 2px;                     /* Ruang dalam tiap baris kontak */
      text-decoration:none;                 /* Hilangkan underline link */
      color:var(--ink);                     /* Warna teks kontak */
      font-weight:500;
      font-size:.9rem;
      border:none;                          /* Tanpa border */
    }

    .c-row .bi{
      font-size:1.05rem;                    /* Ukuran ikon kontak */
      color:var(--brown);                   /* Warna ikon utama */
    }

    .c-row .chev{
      color:#a3a3a3;                        /* Warna ikon chevron kanan */
    }

    /* Baris kontak statis (tanpa chevron), misal jam operasional */
    .c-row.static{
      grid-template-columns:24px 1fr;       /* Hanya 2 kolom: ikon + teks */
    }

    /* ============================
       RESPONSIVE
       ============================ */
    @media (max-width:576px){
      .topbar .inner,
      .wrap{
        padding:12px 14px;                 /* Kurangi padding di layar kecil */
      }

      .wrap{
        margin:8px auto 28px;              /* Margin sedikit diperkecil */
      }

      .faq-q{
        font-size:.88rem;                  /* Kecilkan teks pertanyaan */
      }

      .faq-a{
        font-size:.86rem;                  /* Kecilkan teks jawaban */
      }

      .c-row{
        font-size:.88rem;
        padding:8px 0;                     /* Padding baris kontak diperkecil */
      }
    }
  </style>
</head>

<body>

  <!-- ============================
       TOP BAR
       ============================ -->
  <div class="topbar">
    <div class="inner">
      <!-- tombol kembali (pakai history.back) -->
      <button
        class="back-link"
        onclick="history.back()"  <!-- Kembali ke halaman sebelumnya di history browser -->
        aria-label="Kembali"
      >
        <i class="bi bi-arrow-left"></i> <!-- Ikon panah kiri -->
        <span>Kembali</span>             <!-- Teks tombol -->
      </button>
    </div>
  </div>

  <!-- ============================
       MAIN CONTENT
       ============================ -->
  <main class="wrap">

    <!-- ============================
         FAQ LIST
         ============================ -->
    <div class="faq-list" id="faq">

      <!-- FAQ 1 -->
      <div class="faq-item">
        <button
          class="faq-q"
          data-bs-toggle="collapse"  <!-- Aktifkan fitur collapse Bootstrap -->
          data-bs-target="#a1"       <!-- Target div jawaban dengan id a1 -->
          aria-expanded="false"      <!-- Status awal: tertutup -->
          aria-controls="a1"
        >
          <span>Bagaimana cara melakukan pemesanan?</span>
          <i class="bi bi-chevron-down chev"></i> <!-- Ikon panah bawah -->
        </button>

        <!-- Jawaban FAQ 1 (collapse) -->
        <div id="a1" class="collapse">
          <div class="faq-a">
            Pilih menu di halaman
            <a href="index.php#menuSection">Product</a>,
            lalu tambahkan ke keranjang.  
            Kemudian lanjutkan proses checkout.
          </div>
        </div>
      </div>

      <!-- FAQ 2 -->
      <div class="faq-item">
        <button
          class="faq-q"
          data-bs-toggle="collapse"
          data-bs-target="#a2"
          aria-expanded="false"
          aria-controls="a2"
        >
          <span>Apakah tersedia layanan pengantaran?</span>
          <i class="bi bi-chevron-down chev"></i>
        </button>

        <!-- Jawaban FAQ 2 -->
        <div id="a2" class="collapse">
          <div class="faq-a">
            Saat ini, layanan kami adalah
            <strong>Dine-In</strong>
            dan
            <strong>Take-Away</strong>.
          </div>
        </div>
      </div>

      <!-- FAQ 3 -->
      <div class="faq-item">
        <button
          class="faq-q"
          data-bs-toggle="collapse"
          data-bs-target="#a3"
          aria-expanded="false"
          aria-controls="a3"
        >
          <span>Di mana melihat status pesanan?</span>
          <i class="bi bi-chevron-down chev"></i>
        </button>

        <!-- Jawaban FAQ 3 -->
        <div id="a3" class="collapse">
          <div class="faq-a">
            Semua pesanan aktif dan riwayat tersedia di
            <a href="history.php">Riwayat Pesanan</a>.
          </div>
        </div>
      </div>

      <!-- FAQ 4 -->
      <div class="faq-item">
        <button
          class="faq-q"
          data-bs-toggle="collapse"
          data-bs-target="#a4"
          aria-expanded="false"
          aria-controls="a4"
        >
          <span>Bagaimana mengubah profil atau password?</span>
          <i class="bi bi-chevron-down chev"></i>
        </button>

        <!-- Jawaban FAQ 4 -->
        <div id="a4" class="collapse">
          <div class="faq-a">
            Kamu bisa mengubah nama, nomor HP, foto,
            dan password di halaman
            <a href="profile.php">Profil</a>.
          </div>
        </div>
      </div>

    </div>

    <!-- ============================
         CONTACT SECTION
         ============================ -->
    <div class="contact">

      <!-- WhatsApp -->
      <a
        class="c-row"
        href="https://wa.me/628782302337"     <!-- Link langsung chat WhatsApp -->
        target="_blank"                       <!-- Buka di tab baru -->
        rel="noopener"                        <!-- Keamanan (pisah context) -->
        aria-label="WhatsApp Caffora"
      >
        <i class="bi bi-whatsapp"></i>        <!-- Ikon WhatsApp -->
        <span>WhatsApp</span>                 <!-- Teks label -->
        <i class="bi bi-chevron-right chev"></i> <!-- Chevron kanan -->
      </a>

      <!-- Email -->
      <a
        class="c-row"
        href="mailto:cafforaproject@gmail.com"  <!-- Buka aplikasi email default -->
        aria-label="Email Caffora"
      >
        <i class="bi bi-envelope"></i>          <!-- Ikon email -->
        <span>Email</span>
        <i class="bi bi-chevron-right chev"></i>
      </a>

      <!-- Maps -->
      <a
        class="c-row"
        href="https://maps.app.goo.gl/Fznq9KLusdNXH2RE7"  <!-- Link ke Google Maps -->
        target="_blank"
        rel="noopener"
        aria-label="Lokasi Google Maps"
      >
        <i class="bi bi-geo-alt"></i>          <!-- Ikon lokasi -->
        <span>Jl. Kopi No. 123, Purwokerto</span> <!-- Alamat singkat -->
        <i class="bi bi-chevron-right chev"></i>
      </a>

      <!-- Jam Operasional -->
      <div class="c-row static" role="group">
        <i class="bi bi-clock"></i>            <!-- Ikon jam -->
        <span>08.00 – 22.00</span>            <!-- Jam buka/tutup -->
      </div>
    </div>
  </main>
  <!-- Bootstrap JS bundle (untuk fitur collapse FAQ) -->
  <script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
  ></script>
</body>
</html>
