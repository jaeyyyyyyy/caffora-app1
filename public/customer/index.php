<?php
// Menandai file ini berada di folder public/customer dengan nama index.php
// public/customer/index.php

// Mengaktifkan strict types untuk PHP (tipe harus sesuai deklarasi)
declare(strict_types=1);

// Memanggil file auth_guard.php untuk proteksi autentikasi
require_once __DIR__ . '/../../backend/auth_guard.php';

// Wajib login dengan role "customer" sebelum boleh akses halaman ini
require_login(['customer']);

// Mengambil nama user dari sesi, default "Customer" jika tidak ada
$name = $_SESSION['user_name'] ?? 'Customer';

// Mengambil email user dari sesi, default string kosong jika tidak ada
$email = $_SESSION['user_email'] ?? '';

// Mengambil ID user dari sesi lalu paksa menjadi integer, default 0 jika tidak ada
$userId = (int)($_SESSION['user_id'] ?? 0);

// Mengambil role user dari sesi, default 'customer' jika tidak ada
$role = $_SESSION['user_role'] ?? 'customer';
?>

<!DOCTYPE html>
<!-- Deklarasi tipe dokumen HTML5 -->

<html lang="id">
  <!-- Tag root HTML dengan bahasa Indonesia -->

  <head>
    <!-- Bagian kepala dokumen (metadata, link CSS, dll) -->

    <meta charset="utf-8" />
    <!-- Set karakter encoding menjadi UTF-8 -->

    <title>Caffora Caffe</title>
    <!-- Judul halaman yang tampil di tab browser -->

    <meta
      name="viewport"
      content="width=device-width, initial-scale=1"
    />
    <!-- Pengaturan responsive untuk mobile (lebar mengikuti device) -->

    <!-- Fonts -->
    <!-- Memuat font Playfair Display dan Poppins dari Google Fonts -->
    <link
      href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600&display=swap"
      rel="stylesheet"
    />

    <!-- Bootstrap & Icons -->
    <!-- Memuat CSS Bootstrap dari CDN -->
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet"
    />
    <!-- Memuat Bootstrap Icons dari CDN -->
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
      rel="stylesheet"
    />

    <!-- Global CSS -->
    <!-- Memuat stylesheet global proyek -->
    <link
      href="../assets/css/style.css"
      rel="stylesheet"
    />

    <style>
      /* Awal style inline khusus halaman ini */

      :root {
        /* Variabel CSS global untuk warna utama */
        --gold: #ffd54f;          /* warna emas utama */
        --gold-200: #ffe883;      /* variasi emas lebih terang */
        --gold-soft: #f6d472;     /* warna garis fokus (input fokus) */
        --tan: #daa85c;           /* warna krem/tan */
        --brown: #4b3f36;         /* warna cokelat utama teks */
        --footer: #876f45;        /* warna background footer */
        --bg-soft: #fffdf8;       /* warna latar belakang lembut */
      }

      html,
      body {
        /* Mengatur background dan font untuk seluruh halaman */
        background: var(--bg-soft);                     /* latar belakang lembut */
        font-family: "Poppins", system-ui, -apple-system,
          "Segoe UI", Roboto, Arial, sans-serif;        /* urutan fallback font */
        overflow-x: hidden;                             /* sembunyikan scroll horizontal */
      }

      body {
        margin: 0;          /* hilangkan margin default browser */
        padding-top: 0;     /* hilangkan padding atas (tidak ada gap bawah navbar) */
      }

      /* ================= NAVBAR ================= */

      .navbar {
        background: #fff !important;          /* navbar putih bersih */
        z-index: 1030;                        /* pastikan di atas konten lain */
        border-bottom: none;                  /* hilangkan border bawah */
        box-shadow: none !important;          /* hilangkan bayangan (super flat) */
      }

      .navbar .container {
        align-items: center !important;       /* vertikal align item di tengah */
      }

      .navbar .nav-link {
        color: var(--brown);                  /* warna link cokelat */
        font-weight: 500;                     /* ketebalan medium */
        opacity: 0.9;                         /* sedikit transparan */
        transition: opacity 0.15s,            /* animasi perubahan opacity */
          color 0.15s;                        /* animasi perubahan warna */
      }

      .navbar .nav-link:hover {
        opacity: 1;                           /* penuh saat hover */
        color: var(--tan);                    /* warna berubah ke tan */
      }

      .navbar .nav-link.active {
        color: var(--brown);                  /* warna tetap cokelat */
        font-weight: 700;                     /* ketebalan bold untuk aktif */
        opacity: 1;                           /* tidak transparan */
      }

      .right-actions {
        display: flex;                        /* gunakan flexbox */
        align-items: center !important;       /* sejajarkan item vertikal */
        justify-content: flex-end !important; /* posisikan ke kanan */
        gap: 12px;                            /* jarak antar elemen */
      }

      .icon-btn {
        background: transparent;              /* latar belakang transparan */
        border: 0;                            /* tanpa border */
        padding: 0;                           /* tanpa padding */
        margin: 0;                            /* tanpa margin */
        line-height: 1;                       /* tinggi baris normal */
        color: var(--brown);                  /* warna ikon cokelat */
        display: flex;                        /* jadikan flex container */
        align-items: center;                  /* posisikan ikon tengah vertikal */
        justify-content: center;              /* posisikan ikon tengah horizontal */
      }

      .icon-btn:hover,
      .icon-btn:focus,
      .icon-btn:active {
        transform: none !important;           /* tidak ada transform saat hover/focus */
        transition: none !important;          /* hilangkan animasi default */
        box-shadow: none !important;          /* tanpa bayangan */
        outline: none !important;             /* hilangkan outline fokus */
      }

      #cartBtn {
        border: none !important;              /* hilangkan border pada tombol cart */
        background: transparent !important;   /* background transparan */
        border-radius: 0 !important;          /* tidak bulat */
        padding: 0 !important;                /* tanpa padding */
        text-decoration: none !important;     /* hilangkan garis bawah link */
        color: var(--brown) !important;       /* warna teks cokelat */
        box-shadow: none !important;          /* tanpa bayangan */
        outline: none !important;             /* tanpa outline */
      }

      #cartBtn,
      #cartBtn:hover,
      #cartBtn:focus,
      #cartBtn:active {
        position: relative !important;        /* posisi relatif untuk badge */
        top: 0 !important;                    /* tidak digeser vertikal */
        transform: none !important;           /* tanpa transform */
        transition: none !important;          /* tanpa animasi transisi */
        box-shadow: none !important;          /* tanpa bayangan */
        outline: none !important;             /* tanpa outline */
        color: var(--brown) !important;       /* pastikan warna cokelat */
        background: transparent !important;   /* pastikan transparan */
        border: 0 !important;                 /* tanpa border */
      }

      #cartBtn .iconify,
      #cartBtn .iconify:hover,
      #cartBtn .iconify:focus,
      #cartBtn .iconify:active {
        transform: none !important;           /* ikon tidak bergerak */
        transition: none !important;          /* tanpa animasi */
        color: var(--brown) !important;       /* tetap cokelat */
      }

      /* notif dot */

      #btnBell {
        position: relative;                   /* untuk posisi titik notif */
      }

      #badgeNotif.notif-dot {
        position: absolute;                   /* posisi absolut dalam btnBell */
        top: -2px;                            /* geser ke atas */
        right: -3px;                          /* geser ke kanan */
        width: 8px;                           /* lebar titik kecil */
        height: 8px;                          /* tinggi titik kecil */
        background: #4b3f36;                  /* warna cokelat gelap */
        border-radius: 50%;                   /* bentuk lingkaran */
        display: inline-block;                /* tampil sebagai inline-block */
        box-shadow: 0 0 0 1.5px #fff;         /* lingkaran putih di luar titik */
      }

      #badgeNotif.d-none {
        display: none !important;             /* sembunyikan badge jika d-none */
      }

      @media (max-width: 600px) {
        /* Style khusus layar kecil (max-width 600px) */

        #badgeNotif.notif-dot {
          width: 10px;                        /* titik notif sedikit lebih besar */
          height: 10px;                       /* tinggi titik notif */
          top: -3px;                          /* posisi sedikit naik */
          right: -5px;                        /* posisi sedikit ke kanan */
        }
      }

      /* dropdown profil */

      .dropdown-menu {
        border-radius: 12px;                  /* sudut membulat menu dropdown */
        border-color: #eee;                   /* warna border abu light */
        padding: 6px 0;                       /* padding vertikal menu */
        font-family: "Poppins", system-ui,    /* font family untuk item dropdown */
          -apple-system, "Segoe UI", Roboto,
          Arial, sans-serif;
        background: #fffdf8;                  /* warna latar dropdown */
      }

      .dropdown-menu .dropdown-item {
        font-size: 1.05rem;                   /* ukuran font item dropdown */
        font-weight: 600;                     /* font agak tebal */
        color: var(--brown);                  /* warna teks cokelat */
        padding: 0.6rem 0.9rem;               /* ruang dalam item */
        display: flex;                        /* susunan fleksibel (ikon + text) */
        align-items: center;                  /* sejajar secara vertikal */
        gap: 0.5rem;                          /* jarak antara ikon dan teks */
        transition: none !important;          /* hapus transisi default */
        background: #fffdf8 !important;       /* latar sesuai background global */
      }

      .dropdown-menu .dropdown-item:hover,
      .dropdown-menu .dropdown-item:focus {
        background: rgba(255, 213, 79, 0.16) !important; /* highlight hover */
        color: var(--brown) !important;                  /* warna tetap cokelat */
        outline: none !important;                        /* tanpa outline */
        box-shadow: none !important;                     /* tanpa shadow */
      }

      .dropdown-menu .dropdown-item:active,
      .dropdown-menu .dropdown-item:focus-visible {
        background: rgba(255, 213, 79, 0.16) !important; /* sama saat aktif */
        color: var(--brown) !important;                  /* warna cokelat */
        outline: none !important;                        /* tanpa outline */
        box-shadow: none !important;                     /* tanpa shadow */
      }

      .btn-brand {
        background: var(--gold);                /* warna tombol brand emas */
        color: var(--brown);                    /* teks cokelat */
        border: 0;                              /* tanpa border */
        border-radius: 9999px;                  /* bentuk pill/bulat panjang */
        font-weight: 600;                       /* font tebal */
        padding: 8px 14px;                      /* padding dalam tombol */
        font-size: 0.9rem;                      /* ukuran font kecil */
        line-height: 1.2;                       /* tinggi baris padat */
        text-decoration: none !important;       /* tanpa garis bawah saat link */
      }

      .btn-brand:hover,
      .btn-brand:focus {
        background: var(--gold-200);            /* warna lebih terang saat hover */
        color: var(--brown);                    /* warna teks tetap cokelat */
        box-shadow: 0 0 0 0.16rem
          rgba(255, 213, 79, 0.35);            /* ring emas lembut di luar tombol */
      }

      /* ===== OFFCANVAS (mobile) ===== */

      .custom-offcanvas {
        width: 72%;                             /* lebar offcanvas 72% layar */
        max-width: 300px;                       /* maksimal lebar 300px */
        border-left: 1px solid
          rgba(0, 0, 0, 0.06);                  /* garis tipis di kiri */
      }

      @media (max-width: 360px) {
        /* Style khusus layar sangat kecil (max-width 360px) */

        .custom-offcanvas {
          width: 70%;                           /* sedikit diperkecil */
          max-width: 280px;                     /* batas maksimal lebih kecil */
        }
      }

      .offcanvas-user {
        color: var(--brown) !important;         /* warna teks cokelat */
        text-decoration: none !important;       /* tanpa garis bawah */
        display: inline-block;                  /* tampil sebagai blok inline */
        margin: 2px 0 12px;                     /* margin atas tipis, bawah lebih besar */
        font-weight: 600;                       /* teks utama bold */
      }

      .offcanvas-user .small {
        font-weight: 400;                       /* teks kecil normal weight */
        opacity: 0.8;                           /* sedikit transparan */
      }

      #mobileNav .nav-link {
        color: var(--brown) !important;         /* warna link cokelat */
        font-weight: 600;                       /* font agak tebal */
        opacity: 0.9;                           /* sedikit transparan */
        padding: 0.6rem 0;                      /* padding vertikal */
        display: flex;                          /* susunan icon + teks */
        align-items: center;                    /* sejajarkan vertikal */
        gap: 0.5rem;                            /* jarak icon dan teks */
        transition: color 0.25s ease,
          font-weight 0.25s ease;              /* animasi perubahan warna/tebal */
      }

      #mobileNav .nav-link:hover {
        color: var(--tan) !important;           /* warna berubah ke tan saat hover */
        opacity: 1 !important;                  /* tidak transparan saat hover */
      }

      #mobileNav .nav-link.active {
        color: var(--brown) !important;         /* warna tetap cokelat saat aktif */
        font-weight: 700 !important;            /* tebalkan saat aktif */
        opacity: 1 !important;                  /* full opacity */
      }

      @media (max-width: 991.98px) {
        /* Style untuk layar di bawah breakpoint lg Bootstrap */

        .navbar .icon-btn.d-lg-none {
          display: flex;                        /* tampilkan tombol hanya di mobile */
          align-items: center;                  /* sejajarkan ikon vertikal */
          justify-content: center;              /* sejajarkan ikon horizontal */
          padding: 0 !important;                /* tanpa padding */
          margin: 0 !important;                 /* tanpa margin */
          line-height: 1;                       /* tinggi baris normal */
          position: relative;                   /* posisi relatif */
          top: 1px;                             /* geser sedikit ke bawah */
        }

        .navbar .icon-btn.d-lg-none i {
          font-size: 30px;                      /* ukuran ikon hamburger besar */
          color: var(--brown) !important;       /* warna ikon cokelat */
          transition: none !important;          /* tanpa transisi */
        }

        .icon-btn,
        .icon-btn i.bi-list {
          transition: none !important;          /* tanpa animasi */
          transform: none !important;           /* tanpa transform */
          color: var(--brown) !important;       /* warna cokelat */
        }

        .icon-btn:hover,
        .icon-btn:active,
        .icon-btn:focus,
        .icon-btn:hover i.bi-list,
        .icon-btn:active i.bi-list,
        .icon-btn:focus i.bi-list {
          transform: none !important;           /* tidak bergerak */
          color: var(--brown) !important;       /* warna tetap cokelat */
          opacity: 1 !important;                /* opasitas penuh */
          box-shadow: none !important;          /* tanpa shadow */
          outline: none !important;             /* tanpa outline fokus */
        }
      }

      /* ===== HERO DESKTOP  ===== */

      .hero .container {
        padding: 56px 0;                        /* sama seperti definisi sebelumnya */
      }

      .hero h1 {
        font-family: "Playfair Display", serif; /* font heading hero */
        font-weight: 700;                       /* tebal */
      }

      section.hero,
      #hero {
        background-image: url("/public/assets/img/hero.jpg"); /* gambar hero */
        background-size: cover;                               /* isi penuh */
        background-position: center;                          /* posisikan tengah */
        background-repeat: no-repeat;                         /* tidak mengulang */
      }

      /* ===== HERO MOBILE ===== */

      @media (max-width: 600px) {
        /* Style hero khusus mobile */

        section.hero,
        #hero {
          margin-top: 60px;                   /* geser turun agar tidak tertutup navbar */
          position: relative;                 /* posisi relatif untuk ::before */
          display: flex;                      /* gunakan flexbox */
          align-items: center;                /* konten di tengah vertikal */
          justify-content: center;            /* konten di tengah horizontal */
          text-align: center;                 /* teks rata tengah */
          aspect-ratio: 16 / 9;               /* perbandingan seperti video */
          overflow: hidden;                   /* sembunyikan bagian yang meluber */
        }

        section.hero::before,
        #hero::before {
          content: "";                        /* pseudo-element kosong */
          position: absolute;                 /* menutup seluruh area hero */
          inset: 0;                           /* top/right/bottom/left = 0 */
          background-image: url("/public/assets/img/hero.jpg"); /* gambar hero */
          background-size: cover;             /* isi penuh area */
          background-position: center;        /* posisi tengah */
          background-repeat: no-repeat;       /* tidak diulang */
          z-index: -1;                        /* di belakang konten hero */
        }

        section.hero .container,
        #hero .container {
          position: relative;                 /* kontainer di atas pseudo-element */
          z-index: 2;                         /* di depan background */
          padding: 0 1rem;                    /* padding horizontal kecil */
        }

        section.hero h1,
        #hero h1 {
          font-size: 1.3rem;                  /* ukuran judul lebih kecil di mobile */
          line-height: 1.2;                   /* tinggi baris rapat */
          margin-bottom: 0.5rem;              /* jarak bawah heading */
        }

        section.hero p,
        #hero p {
          font-size: 0.9rem;                  /* ukuran paragraf lebih kecil */
          line-height: 1.4;                   /* tinggi baris sedikit lebih longgar */
          margin: 0;                          /* hilangkan margin default */
        }
      }

      /* ===================== Anchor offset ===================== */

      #hero,
      #menuSection,
      #about {
        scroll-margin-top: 80px;              /* jarak atas ketika discroll ke anchor */
      }

      /* ================= MENU / KATALOG ================= */

      #menuSection {
        background: transparent;              /* latar menu transparan */
      }

      .search-box {
        position: relative;                   /* untuk posisi tombol search */
        width: 100%;                          /* lebar penuh */
      }

      .search-input {
        height: 46px;                         /* tinggi input */
        border-radius: 9999px;                /* bentuk pill */
        padding-left: 16px;                   /* padding kiri */
        padding-right: 44px;                  /* padding kanan agar muat ikon search */
        border: 1px solid #e5e7eb;            /* border abu-abu muda */
        outline: none !important;             /* hilangkan outline default */
        background: #fff;                     /* latar putih */
        transition: border-color 0.1s ease;   /* animasi perubahan border */
      }

      .search-input:focus,
      .search-input:focus-visible {
        outline: none !important;             /* tetap tanpa outline */
        border-color: var(--gold-soft) !important; /* border kuning lembut saat fokus */
        box-shadow: none !important;          /* tanpa shadow fokus */
        background: #fff;                     /* latar tetap putih */
      }

      .btn-search {
        position: absolute;                   /* posisi absolut dalam search-box */
        right: 6px;                           /* geser dari kanan */
        top: 50%;                             /* posisikan di tengah vertikal */
        transform: translateY(-50%);          /* benar-benar tengah vertikal */
        height: 34px;                         /* tinggi tombol */
        width: 34px;                          /* lebar tombol */
        display: flex;                        /* flexbox untuk icon */
        align-items: center;                  /* sejajarkan vertikal */
        justify-content: center;              /* sejajarkan horizontal */
        border: 0;                            /* tanpa border */
        border-radius: 50%;                   /* bentuk lingkaran */
        background: transparent;              /* latar transparan */
        color: var(--brown);                  /* warna ikon cokelat */
      }

      .btn-filter {
        background: var(--gold);              /* background tombol filter emas */
        color: var(--brown);                  /* teks cokelat */
        border: 0;                            /* tanpa border */
        border-radius: 12px;                  /* sudut membulat */
        font-weight: 500;                     /* font medium */
        height: 46px;                         /* tinggi tombol */
        width: 46px;                          /* lebar tombol */
        display: inline-flex;                 /* inline-flex untuk ikon */
        align-items: center;                  /* sejajarkan ikon vertikal */
        justify-content: center;              /* sejajarkan ikon horizontal */
        transition: none;                     /* tanpa animasi transisi */
      }

      #filterMenu.dropdown-menu {
        border-radius: 5px;                   /* sudut dropdown filter */
        border: 1px solid
          rgba(0, 0, 0, 0.06);                /* border tipis abu */
        background: #fffef8;                  /* latar krem terang */
        min-width: 220px;                     /* lebar minimum dropdown */
        max-width: 320px;                     /* lebar maksimum dropdown */
        padding: 6px 0;                       /* padding vertikal */
        font-family: "Poppins", system-ui,    /* font family dropdown filter */
          -apple-system, "Segoe UI", Roboto,
          Arial, sans-serif;
        box-shadow: 0 12px 24px               /* bayangan lembut dropdown */
          rgba(0, 0, 0, 0.08);
        transform-origin: top right;          /* titik referensi animasi */
        transition: opacity 0.15s ease,
          transform 0.15s ease;               /* animasi buka/tutup */
      }

      #filterMenu .dropdown-item {
        font-size: 0.9rem;                    /* font ukuran kecil */
        font-weight: 600;                     /* agak tebal */
        color: var(--brown);                  /* warna cokelat */
        padding: 9px 14px;                    /* padding item dropdown */
      }

      #filterMenu .dropdown-item:hover,
      #filterMenu .dropdown-item:focus {
        background: rgba(255, 213, 79, 0.25); /* highlight kuning saat hover */
        color: var(--brown);                  /* teks tetap cokelat */
      }

      .dropdown.ms-2 {
        overflow: visible !important;         /* pastikan dropdown tidak terpotong */
      }

      @media (max-width: 600px) {
        /* Style filter + search untuk mobile */

        .btn-filter {
          width: 38px !important;             /* tombol filter lebih kecil */
          height: 38px !important;            /* tinggi tombol lebih kecil */
          border-radius: 10px !important;     /* radius sedikit lebih kecil */
          font-size: 0.95rem !important;      /* font sedikit lebih kecil */
          padding: 0 !important;              /* tanpa padding ekstra */
          box-shadow: 0 2px 6px
            rgba(0, 0, 0, 0.08);              /* sedikit bayangan */
        }

        .btn-filter .iconify,
        .btn-filter i {
          width: 22px !important;             /* ukuran ikon lebih kecil */
          height: 22px !important;            /* tinggi ikon lebih kecil */
        }

        .search-input {
          height: 40px !important;            /* tinggi input lebih pendek */
          font-size: 0.9rem !important;       /* font lebih kecil */
          padding: 8px 38px 8px 14px
            !important;                       /* padding sesuai ikon kecil */
          border-radius: 9999px !important;   /* pill penuh */
        }

        #filterMenu .dropdown-item {
          padding: 8px 14px !important;       /* padding sedikit dikurangi */
          font-size: 0.85rem !important;      /* font lebih kecil lagi */
          white-space: nowrap !important;     /* teks tidak dipatahkan */
        }

        #filterMenu.dropdown-menu {
          max-height: none !important;        /* hilangkan batas tinggi */
          overflow-y: visible !important;     /* biarkan konten keliatan penuh */
        }
      }

      .card-menu,
      .menu-card,
      .product-card,
      .card.item,
      .card.product {
        width: 100% !important;               /* kartu isi lebar kolom penuh */
        border-radius: 18px !important;       /* sudut kartu membulat */
        padding: 12px !important;             /* padding dalam kartu */
        background: #fff;                     /* latar putih */
        position: relative;                   /* untuk posisi tombol add absolute */
        overflow: hidden;                     /* konten yang meluber disembunyikan */
        box-shadow: 0 8px 16px                /* bayangan lembut kartu */
          rgba(0, 0, 0, 0.06);
      }

      .card-menu img,
      .menu-card img,
      .product-card img,
      .card.item img,
      .card.product img {
        display: block;                       /* gambar sebagai block */
        width: 90% !important;                /* lebar gambar 90% dari kartu */
        height: auto !important;              /* tinggi otomatis proporsional */
        margin: 0 auto 8px !important;        /* center dan beri jarak bawah */
        object-fit: contain !important;       /* tampilkan gambar utuh */
      }

      .card-menu .menu-name,
      .menu-card .menu-name,
      .product-card .menu-name,
      .card.item .menu-name,
      .card.product .menu-name,
      .card-menu h5,
      .menu-card h5,
      .product-card h5,
      .card.item h5,
      .card.product h5 {
        font-size: 0.92rem !important;        /* ukuran teks nama menu */
        line-height: 1.25 !important;         /* tinggi baris nama menu */
        font-weight: 600;                     /* font agak tebal */
        margin: 2px 0 0 !important;           /* margin kecil atas */
        color: var(--brown);                  /* warna teks cokelat */
      }

      .card-menu .btn-add,
      .menu-card .btn-add,
      .product-card .btn-add,
      .card.item .btn-add,
      .card.product .btn-add,
      .card-menu .add-btn,
      .menu-card .add-btn,
      .product-card .add-btn,
      .card.item .add-btn,
      .card.product .add-btn,
      .card-menu .btn-add-to-cart,
      .menu-card .btn-add-to-cart,
      .product-card .btn-add-to-cart,
      .card.item .btn-add-to-cart,
      .card.product .btn-add-to-cart {
        position: absolute !important;        /* tombol add absolute di dalam kartu */
        right: 10px !important;               /* posisi kanan bawah */
        bottom: 10px !important;              /* jarak dari bawah */
        width: 32px !important;               /* lebar tombol */
        height: 32px !important;              /* tinggi tombol */
        line-height: 32px !important;         /* tinggi baris seukuran tombol */
        border-radius: 50% !important;        /* tombol bundar */
        border: none !important;              /* tanpa border */
        background: var(--gold) !important;   /* latar tombol emas */
        color: var(--brown) !important;       /* ikon/tanda plus cokelat */
        font-weight: 600;                     /* font tebal */
        font-size: 1rem !important;           /* ukuran ikon plus */
        display: inline-flex !important;      /* inline-flex untuk centering */
        align-items: center;                  /* sejajarkan vertikal */
        justify-content: center;              /* sejajarkan horizontal */
        box-shadow: 0 4px 8px                 /* bayangan lembut tombol */
          rgba(0, 0, 0, 0.08);
        cursor: pointer;                      /* cursor pointer */
      }

      @media (max-width: 600px) {
        /* Style kartu di mobile */

        .card-menu,
        .menu-card,
        .product-card,
        .card.item,
        .card.product {
          box-shadow: 0 4px 10px              /* sedikit beda bayangan di mobile */
            rgba(0, 0, 0, 0.06);
        }

        .card-menu .btn-add,
        .menu-card .btn-add,
        .product-card .btn-add,
        .card.item .btn-add,
        .card.product .btn-add {
          width: 25px !important;             /* ukuran tombol add lebih kecil */
          height: 25px !important;            /* tinggi tombol add lebih kecil */
          line-height: 25px !important;       /* tinggi baris menyesuaikan */
          font-size: 1rem !important;         /* ukuran ikon tetap */
        }
      }

      /* ================= ABOUT ================= */

      #about {
        background: transparent;              /* latar section about transparan */
      }

      #about .about-title {
        font-family: "Playfair Display", serif;          /* font judul about */
        font-weight: 700;                                /* tebal */
        font-size: clamp(26px, 3vw, 36px);               /* responsif dengan clamp */
        line-height: 1.25;                               /* tinggi baris */
        color: #111;                                     /* warna teks gelap */
        margin-bottom: 10px;                             /* jarak bawah judul */
      }

      #about .about-desc {
        color: #666;                                     /* warna teks abu */
        font-size: clamp(14px, 1.5vw, 16px);             /* ukuran fleksibel */
        margin-bottom: 16px;                             /* jarak bawah paragraf */
        max-width: 54ch;                                 /* batas lebar deskripsi */
      }

      .btn-view {
        background-color: var(--gold);                   /* background tombol view */
        color: var(--brown) !important;                  /* teks cokelat */
        border: 0;                                       /* tanpa border */
        border-radius: 12px;                             /* sudut membulat */
        font-family: Arial, sans-serif;                  /* font tombol */
        font-weight: 600;                                /* tebal */
        font-size: 13.3px;                               /* ukuran font kecil */
        line-height: 1.2;                                /* tinggi baris rapat */
        padding: 10px 14px;                              /* padding tombol */
        display: inline-flex;                            /* flex untuk isi */
        align-items: center;                             /* sejajarkan vertikal */
        justify-content: center;                         /* sejajarkan horizontal */
        gap: 8px;                                        /* jarak jika ada ikon */
        text-decoration: none !important;                /* tanpa garis bawah */
        box-shadow: none;                                /* tanpa shadow */
        cursor: pointer;                                 /* cursor pointer */
        white-space: nowrap;                             /* teks tidak dibungkus */
      }

      .about-img-wrap {
        text-align: center;                              /* center gambar di kolom */
      }

      .about-img {
        width: 70%;                                      /* lebar gambar 70% */
        max-width: 70%;                                  /* batas maksimal 70% */
        border-radius: 24px;                             /* sudut membulat */
        object-fit: cover;                               /* isi area, bisa terpotong */
        box-shadow: 0 10px 22px                          /* bayangan lembut gambar */
          rgba(0, 0, 0, 0.08);
      }

      @media (max-width: 992px) {
        /* Style about untuk tablet kebawah */

        .about-img {
          max-width: 75%;                                /* gambar sedikit lebih besar */
        }
      }

      @media (max-width: 576px) {
        /* Style about + hero untuk layar kecil (sm) */

        .hero .container {
          padding: 40px 0;                               /* padding hero kecil */
        }

        .about-img {
          max-width: 60%;                                /* gambar lebih kecil */
          border-radius: 20px;                           /* sudut sedikit lebih kecil */
        }

        #about .about-title {
          font-size: 24px;                               /* judul lebih kecil */
        }
      }

      @media (max-width: 576px) {
        /* Style tambahan khusus mobile untuk section about */

        #about {
          padding-top: 1.25rem !important;               /* padding atas kecil */
          padding-bottom: 0 !important;                  /* hilangkan padding bawah */
          background: transparent;                       /* latar transparan */
          position: relative;                            /* untuk z-index */
          z-index: 1;                                    /* di atas hero */
          margin-bottom: 0 !important;                   /* hilangkan margin bawah */
        }

        #about .container {
          padding-left: 0 !important;                    /* hilangkan padding kiri */
          padding-right: 0 !important;                   /* hilangkan padding kanan */
        }

        #about .row {
          --bs-gutter-x: 0;                              /* hilangkan gutter horizontal */
          --bs-gutter-y: 0;                              /* hilangkan gutter vertikal */
        }

        #about .about-img {
          width: 100% !important;                        /* lebar penuh */
          max-width: 100% !important;                    /* tidak dibatasi */
          height: auto;                                  /* tinggi otomatis */
          display: block;                                /* tampil block */
          border-radius: 0 !important;                   /* hilangkan radius */
          box-shadow: none !important;                   /* hilangkan shadow */
          margin-top: 30px;                              /* beri jarak atas */
          margin-bottom: 0 !important;                   /* tanpa jarak bawah */
        }

        #about .about-title {
          margin: 0 1rem 0.5rem;                         /* margin samping dan bawah */
        }

        #about .about-desc {
          margin: 0 1rem 0.75rem;                        /* margin untuk paragraf */
        }

        #about .btn-view {
          margin: 0 1rem 1rem;                           /* margin tombol */
        }
      }

      /* ================= FOOTER ================= */

      footer {
        background: var(--footer);                       /* latar footer cokelat */
        color: var(--gold-200);                          /* teks keemasan */
        padding-top: 36px;                               /* padding atas */
        padding-bottom: 20px;                            /* padding bawah */
        position: relative;                              /* posisi relatif */
        z-index: 2;                                      /* di atas konten tertentu */
      }

      footer h5,
      footer h6 {
        color: var(--gold-200);                          /* warna judul footer */
        font-weight: 700;                                /* tebal */
      }

      footer a {
        color: var(--gold-200);                          /* warna link footer */
        text-decoration: none;                           /* tanpa garis bawah */
      }

      footer a:hover,
      footer a:focus {
        color: var(--gold);                              /* warna emas saat hover */
        text-decoration: underline;                      /* garis bawah saat hover */
      }

      footer .footer-icons a {
        color: var(--gold-200);                          /* warna ikon sosmed */
        font-size: 20px;                                 /* ukuran ikon */
        margin-right: 12px;                              /* jarak antar ikon */
      }

      footer .border-secondary-subtle {
        border-color: rgba(255, 255, 255, 0.18)
          !important;                                    /* warna garis pemisah */
      }

      @media (max-width: 600px) {
        /* Style footer untuk mobile */

        footer {
          width: 100vw;                                  /* lebar penuh viewport */
          margin-left: calc(-1 * (100vw - 100%) / 2);    /* trik agar full bleed */
          margin-top: -30px;                             /* overlap sedikit ke atas */
          padding: 28px 18px 20px;                       /* padding sekitar footer */
        }

        footer .container {
          padding: 0 !important;                         /* hilangkan padding sisi */
        }

        footer .row.g-4 {
          --bs-gutter-y: 0.75rem;                        /* jarak vertikal antar kolom */
          --bs-gutter-x: 0;                              /* tanpa jarak horizontal */
        }

        footer .footer-icons a {
          font-size: 1.1rem;                             /* ikon sedikit lebih besar */
          margin-right: 10px;                            /* jarak antar ikon */
        }
      }

      .toast-mini {
        position: fixed !important;                      /* posisi fixed di layar */
        left: 50% !important;                            /* di tengah horizontal */
        bottom: 16px !important;                         /* jarak dari bawah */
        transform: translateX(-50%) !important;          /* center secara tepat */
        width: 380px !important;                         /* lebar maksimum toast */
        max-width: calc(100vw - 32px) !important;        /* tidak melebihi lebar layar */
        z-index: 1080 !important;                        /* di atas elemen lain */
        border-radius: 12px !important;                  /* sudut membulat toast */
      }

      @media (max-width: 600px) {
        /* Toast mini di mobile */

        .toast-mini {
          bottom: 1rem !important;                       /* jarak bawah sedikit berbeda */
          left: 50% !important;                          /* tetap di tengah */
          right: auto !important;                        /* hilangkan right */
          transform: translateX(-50%) !important;        /* center horizontal */
          width: calc(100vw - 24px) !important;          /* lebar hampir penuh */
          max-width: calc(100vw - 24px) !important;      /* batasi max width */
          border-radius: 14px !important;                /* radius sedikit lebih besar */
        }

        .toast-mini .toast-body {
          font-size: 0.8rem !important;                  /* teks toast lebih kecil */
        }
      }

      .dropdown-menu .dropdown-item,
      .dropdown-menu .dropdown-item:hover,
      .dropdown-menu .dropdown-item:focus,
      .dropdown-menu .dropdown-item:active {
        background: transparent !important;              /* paksa background transparan */
        box-shadow: none !important;                     /* tanpa shadow */
        outline: none !important;                        /* tanpa outline */
      }
    </style>

    <!-- Inject identitas user untuk app.js (HARUS sebelum app.js) -->
    <script>
      // Membuat objek global di window untuk menyimpan data user ke JS
      window.__CAFFORA_USER__ = {
        // ID user dalam bentuk angka (dari PHP)
        id: <?= $userId ?>,
        // Nama user dalam bentuk string (json_encode agar aman karakter spesial)
        name: <?= json_encode($name, JSON_UNESCAPED_UNICODE) ?>,
        // Email user dalam bentuk string
        email: <?= json_encode($email, JSON_UNESCAPED_UNICODE) ?>,
        // Role user (misalnya customer)
        role: <?= json_encode($role, JSON_UNESCAPED_UNICODE) ?>
      };
    </script>
  </head>

  <body>
    <!-- Awal body dokumen -->

    <!-- ================= NAVBAR ================= -->
    <nav class="navbar fixed-top">
      <!-- Navbar posisi fixed di atas -->

      <div
        class="container d-flex align-items-center justify-content-between"
      >
        <!-- Container navbar: brand, menu, dan icon kanan -->

        <!-- Brand -->
        <a
          class="navbar-brand d-flex align-items-center gap-2"
          href="#hero"
        >
          <!-- Link brand ke section hero -->

          <i
            class="bi bi-cup-hot-fill text-warning"
          ></i>
          <!-- Ikon cangkir kopi pada brand -->

          <span
            class="brand-brown fw-bold"
            style="color: var(--brown)"
          >
            <!-- Teks brand Caffora dengan warna cokelat -->

            Caffora
          </span>
        </a>

        <!-- Nav links (desktop) -->
        <ul
          class="navbar-nav d-none d-lg-flex flex-row gap-3"
          id="mainNav"
        >
          <!-- Menu navigasi utama, hanya tampil di layar besar -->

          <li class="nav-item">
            <!-- Item menu Home -->

            <a
              class="nav-link"
              href="#hero"
            >
              <!-- Link ke section hero -->

              Home
            </a>
          </li>

          <li class="nav-item">
            <!-- Item menu Product -->

            <a
              class="nav-link"
              href="#menuSection"
            >
              <!-- Link ke section menu -->

              Product
            </a>
          </li>

          <li class="nav-item">
            <!-- Item menu Tentang Kami -->

            <a
              class="nav-link"
              href="#about"
            >
              <!-- Link ke section about -->

              Tentang Kami
            </a>
          </li>
        </ul>

        <!-- Right icons -->
        <div
          class="right-actions d-flex align-items-center gap-3"
        >
          <!-- Aksi di sisi kanan navbar: cart, notifikasi, profil, hamburger -->

          <!-- Cart -->
          <a
            class="icon-btn position-relative me-lg-1"
            id="cartBtn"
            aria-label="Buka keranjang"
            href="/public/customer/cart.php"
          >
            <!-- Tombol menuju halaman keranjang -->

            <span
              class="iconify"
              data-icon="mdi:cart-outline"
              data-width="24"
              data-height="24"
            ></span>
            <!-- Ikon keranjang menggunakan Iconify -->

            <span
              class="badge-cart badge rounded-pill bg-danger"
              id="cartBadge"
              style="display: none"
            >
              <!-- Badge jumlah item keranjang (disembunyikan jika 0) -->

              0
            </span>
          </a>

          <!-- Notifikasi -->
          <div class="dropdown">
            <!-- Container dropdown notifikasi (saat ini link langsung) -->

            <a
              id="btnBell"
              class="icon-btn position-relative text-decoration-none"
              href="/public/customer/notifications.php"
              aria-label="Notifikasi"
            >
              <!-- Tombol menuju halaman notifikasi -->

              <span
                class="iconify"
                data-icon="mdi:bell-outline"
                data-width="24"
                data-height="24"
              ></span>
              <!-- Ikon lonceng notifikasi -->

              <span
                id="badgeNotif"
                class="notif-dot d-none"
              ></span>
              <!-- Titik kecil indikator notif baru (disembunyikan d-none) -->
            </a>
          </div>

          <!-- Profil (desktop) -->
          <div class="dropdown d-none d-lg-block">
            <!-- Dropdown profil hanya muncul di desktop -->

            <button
              class="icon-btn"
              data-bs-toggle="dropdown"
              aria-expanded="false"
              aria-label="Akun"
            >
              <!-- Tombol pembuka dropdown profil -->

              <span
                class="iconify"
                data-icon="mdi:account-circle-outline"
                data-width="28"
                data-height="28"
              ></span>
              <!-- Ikon profil user -->
            </button>

            <ul
              class="dropdown-menu dropdown-menu-end shadow"
              style="min-width: 260px"
            >
              <!-- Menu dropdown profil, muncul mengarah ke kanan -->

              <li class="px-3 py-2">
                <!-- Bagian info singkat user -->

                <div class="small text-muted">
                  <!-- Label teks kecil -->

                  Masuk sebagai
                </div>

                <div class="fw-semibold">
                  <!-- Menampilkan nama user -->

                  <?= htmlspecialchars($name) ?>
                </div>

                <div
                  class="small text-muted text-truncate"
                  style="max-width: 220px"
                >
                  <!-- Menampilkan email user, dipotong bila kepanjangan -->

                  <?= htmlspecialchars($email) ?>
                </div>
              </li>

              <li>
                <hr class="dropdown-divider" />
                <!-- Garis pemisah dalam dropdown -->
              </li>

              <li>
                <a
                  class="dropdown-item"
                  href="profile.php"
                >
                  <!-- Link ke halaman pengaturan profil -->

                  <i class="bi bi-gear"></i>
                  <!-- Ikon gear -->

                  Pengaturan
                </a>
              </li>

              <li>
                <a
                  class="dropdown-item"
                  href="faq.php"
                >
                  <!-- Link ke halaman FAQ -->

                  <i class="bi bi-question-circle"></i>
                  <!-- Ikon tanda tanya -->

                  FAQ
                </a>
              </li>

              <li>
                <a
                  class="dropdown-item"
                  href="history.php"
                >
                  <!-- Link ke riwayat pesanan -->

                  <i class="bi bi-receipt"></i>
                  <!-- Ikon struk -->

                  Riwayat Pesanan
                </a>
              </li>

              <li>
                <hr class="dropdown-divider" />
                <!-- Garis pemisah sebelum logout -->
              </li>

              <li>
                <!-- FIX LOGOUT (desktop): diarahkan via JS -->
                <a
                  class="dropdown-item text-danger"
                  href="../login.html"
                  data-logout
                >
                  <!-- Link logout yang akan di-intersep JS -->

                  <span
                    class="iconify"
                    data-icon="mdi:logout"
                  ></span>
                  <!-- Ikon logout -->

                  Log out
                </a>
              </li>
            </ul>
          </div>

          <!-- Hamburger (mobile) -->
          <button
            class="icon-btn d-lg-none"
            type="button"
            data-bs-toggle="offcanvas"
            data-bs-target="#mobileNav"
            aria-controls="mobileNav"
            aria-label="Buka menu"
          >
            <!-- Tombol hamburger untuk membuka offcanvas mobile -->

            <i
              class="bi bi-list"
              style="font-size: 30px"
            ></i>
            <!-- Ikon list (hamburger) -->
          </button>
        </div>
      </div>
    </nav>

    <!-- ================= OFFCANVAS (mobile) ================= -->
    <div
      class="offcanvas offcanvas-end custom-offcanvas"
      tabindex="-1"
      id="mobileNav"
      aria-labelledby="mobileNavLabel"
    >
      <!-- Panel offcanvas menu untuk mobile -->

      <div
        class="offcanvas-header border-bottom d-flex justify-content-between align-items-center"
      >
        <!-- Header offcanvas berisi judul dan area close (di ikon) -->

        <h5
          class="offcanvas-title fw-semibold m-0"
          id="mobileNavLabel"
        >
          <!-- Judul offcanvas (ikon list) -->

          <i
            class="bi bi-list"
            style="font-size: 2rem"
          ></i>
          <!-- Ikon list di header offcanvas (juga berfungsi untuk close via JS) -->
        </h5>
      </div>

      <div
        class="offcanvas-body d-flex flex-column gap-2"
      >
        <!-- Isi utama offcanvas (user info + nav link) -->

        <div class="offcanvas-user">
          <!-- Info user singkat di offcanvas -->

          <div class="small">
            <!-- Label kecil -->

            Masuk sebagai
          </div>

          <div class="fw-semibold">
            <!-- Menampilkan nama user -->

            <?= htmlspecialchars($name) ?>
          </div>

          <div
            class="small text-truncate"
            style="max-width: 240px"
          >
            <!-- Menampilkan email user dengan truncation -->

            <?= htmlspecialchars($email) ?>
          </div>
        </div>

        <ul
          class="nav flex-column mt-1"
          id="mobileNavList"
        >
          <!-- Daftar-menu vertikal di offcanvas -->

          <li class="nav-item">
            <!-- Menu pengaturan profil -->

            <a
              class="nav-link"
              href="profile.php"
            >
              <!-- Link ke halaman profile -->

              <i class="bi bi-gear"></i>
              <!-- Ikon gear -->

              Pengaturan
            </a>
          </li>

          <li class="nav-item">
            <!-- Menu FAQ -->

            <a
              class="nav-link"
              href="faq.php"
            >
              <!-- Link ke halaman FAQ -->

              <i class="bi bi-question-circle"></i>
              <!-- Ikon tanda tanya -->

              FAQ
            </a>
          </li>

          <li class="nav-item">
            <!-- Menu riwayat pesanan -->

            <a
              class="nav-link"
              href="history.php"
            >
              <!-- Link ke riwayat pesanan -->

              <i class="bi bi-receipt"></i>
              <!-- Ikon struk -->

              Riwayat pesanan
            </a>
          </li>

          <li>
            <hr />
            <!-- Garis pemisah sebelum tombol logout -->
          </li>

          <!-- FIX LOGOUT (mobile): diarahkan via JS -->
          <li class="nav-item">
            <!-- Menu logout mobile -->

            <a
              class="nav-link text-danger"
              href="../login.html"
              data-logout
            >
              <!-- Link logout yang akan diproses JS -->

              <span
                class="iconify"
                data-icon="mdi:logout"
                data-width="22"
                data-height="22"
              ></span>
              <!-- Ikon logout -->

              Log out
            </a>
          </li>
        </ul>
      </div>
    </div>

    <!-- ================= HERO ================= -->
    <section
      class="hero"
      id="hero"
    >
      <!-- Section hero utama di beranda -->

      <div class="container">
        <!-- Container untuk teks hero -->

        <h1>
          <!-- Judul hero -->

          Selamat Datang di Caffora
        </h1>

        <p>
          <!-- Deskripsi singkat hero -->

          Ngopi santai, kerja produktif, dan dessert favorit—semua
          dalam satu ruang.
        </p>
      </div>
    </section>

    <!-- ================= MENU / KATALOG ================= -->
    <main
      id="menuSection"
      class="container py-5"
    >
      <!-- Section utama untuk katalog/menu -->

      <h2
        class="mb-3 text-center"
        style="
          color: var(--brown);
          font-family: 'Playfair Display', serif;
          font-weight: 700;
        "
      >
        <!-- Judul section Menu Kami -->

        Menu Kami
      </h2>

      <p
        class="text-center text-muted mb-4"
      >
        <!-- Deskripsi singkat di bawah judul menu -->

        Cari menu favoritmu lalu tambahkan ke keranjang.
      </p>

      <!-- Search + Filter -->
      <div
        class="row gy-3 gx-2 mb-3 justify-content-center"
      >
        <!-- Baris yang berisi search bar dan tombol filter -->

        <div
          class="col-12 col-md-7 d-flex align-items-center"
        >
          <!-- Kolom search input + tombol filter -->

          <div class="search-box flex-grow-1">
            <!-- Container input search -->

            <input
              id="q"
              class="form-control search-input"
              placeholder="Search..."
            />
            <!-- Input teks untuk pencarian menu -->

            <button
              id="btnCari"
              class="btn-search"
              aria-label="Cari"
            >
              <!-- Tombol cari dengan ikon -->

              <i class="bi bi-search"></i>
              <!-- Ikon search -->
            </button>
          </div>

          <div class="dropdown ms-2">
            <!-- Dropdown filter menu -->

            <button
              class="btn-filter"
              id="filterBtn"
              data-bs-toggle="dropdown"
              data-bs-display="static"
              aria-expanded="false"
              aria-label="Filter"
            >
              <!-- Tombol filter dengan ikon -->

              <span
                class="iconify"
                data-icon="mdi:filter-variant"
                data-width="22"
                data-height="22"
              ></span>
              <!-- Ikon filter variant -->
            </button>

            <ul
              class="dropdown-menu dropdown-menu-end shadow"
              aria-labelledby="filterBtn"
              id="filterMenu"
            >
              <!-- Menu dropdown pilihan filter -->

              <li>
                <a
                  class="dropdown-item"
                  href="#"
                  data-filter="cheap"
                >
                  <!-- Filter termurah ke termahal -->

                  Termurah - Termahal
                </a>
              </li>

              <li>
                <a
                  class="dropdown-item"
                  href="#"
                  data-filter="expensive"
                >
                  <!-- Filter termahal ke termurah -->

                  Termahal - Termurah
                </a>
              </li>

              <li>
                <a
                  class="dropdown-item"
                  href="#"
                  data-filter="pastry"
                >
                  <!-- Filter kategori pastry -->

                  Pastry
                </a>
              </li>

              <li>
                <a
                  class="dropdown-item"
                  href="#"
                  data-filter="drink"
                >
                  <!-- Filter kategori minuman -->

                  Drink
                </a>
              </li>

              <li>
                <a
                  class="dropdown-item"
                  href="#"
                  data-filter="food"
                >
                  <!-- Filter kategori makanan -->

                  Food
                </a>
              </li>

              <li>
                <a
                  class="dropdown-item"
                  href="#"
                  data-filter="all"
                >
                  <!-- Filter untuk menampilkan semua menu -->

                  Tampilkan Semua
                </a>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <div
        id="list"
        class="row g-4"
      >
        <!-- Container list kartu menu (akan diisi via JS) -->
      </div>
    </main>

    <!-- ================= ABOUT ================= -->
    <section
      id="about"
      class="py-5"
    >
      <!-- Section tentang Caffora -->

      <div class="container">
        <!-- Container isi section about -->

        <div
          class="row g-4 align-items-center"
        >
          <!-- Baris dengan kolom teks dan gambar -->

          <div class="col-lg-6">
            <!-- Kolom kiri: teks about -->

            <h2 class="about-title">
              <!-- Judul utama about -->

              Caffora tempat Nyaman<br />
              untuk Setiap Momen
            </h2>

            <p class="about-desc">
              <!-- Deskripsi singkat tentang suasana Caffora -->

              Nikmati pengalaman ngopi di tempat yang tenang dan
              nyaman. Ruang yang minimalis dengan sentuhan hangat ini
              siap menemani kamu untuk bekerja, bersantai, atau
              sekadar melepas penat.
            </p>

            <a
              href="#menuSection"
              class="btn-view"
            >
              <!-- Tombol untuk scroll ke menu -->

              View more
            </a>
          </div>

          <div
            class="col-lg-6 about-img-wrap"
          >
            <!-- Kolom kanan: gambar about -->

            <img
              src="../assets/img/cafforaf.jpg"
              alt="Ruang Caffora yang nyaman"
              class="about-img"
            />
            <!-- Gambar ruangan Caffora -->
          </div>
        </div>
      </div>
    </section>

    <!-- ================= FOOTER ================= -->
    <footer>
      <!-- Bagian footer situs -->

      <div class="container">
        <!-- Container isi footer -->

        <div
          class="row g-4 align-items-start"
        >
          <!-- Baris utama footer dengan tiga kolom -->

          <div class="col-md-4 mb-4 mb-md-0">
            <!-- Kolom pertama: brand dan sosmed -->

            <h5>
              <!-- Judul Caffora di footer -->

              Caffora
            </h5>

            <p>
              <!-- Teks pendek tentang jenis layanan -->

              Coffee <br />
              Dessert <br />
              Space
            </p>

            <div class="footer-icons">
              <!-- Deretan ikon sosial media -->

              <a href="#">
                <!-- Link Instagram -->

                <i class="bi bi-instagram"></i>
                <!-- Ikon Instagram -->
              </a>

              <a
                href="https://twitter.com/cafforacafe"
                aria-label="Twitter"
              >
                <!-- Link ke akun Twitter/X -->

                <i class="bi bi-twitter-x"></i>
                <!-- Ikon Twitter/X -->
              </a>

              <a href="#">
                <!-- Link WhatsApp (placeholder) -->

                <i class="bi bi-whatsapp"></i>
                <!-- Ikon WhatsApp -->
              </a>

              <a href="#">
                <!-- Link TikTok (placeholder) -->

                <i class="bi bi-tiktok"></i>
                <!-- Ikon TikTok -->
              </a>
            </div>
          </div>

          <div
            class="col-md-4 mb-4 mb-md-0"
          >
            <!-- Kolom kedua: menu navigasi footer -->

            <h6>
              <!-- Judul kecil kolom menu -->

              Menu
            </h6>

            <ul
              class="list-unstyled m-0"
            >
              <!-- Daftar menu tanpa bullet -->

              <li>
                <a href="#menuSection">
                  <!-- Link ke section menu -->

                  Semua Menu
                </a>
              </li>

              <li>
                <a href="#about">
                  <!-- Link ke section about -->

                  Tentang Kami
                </a>
              </li>
            </ul>
          </div>

          <div class="col-md-4">
            <!-- Kolom ketiga: kontak -->

            <h6>
              <!-- Judul kecil kolom kontak -->

              Kontak
            </h6>

            <p>
              <!-- Alamat dengan link ke Google Maps -->

              <a
                href="https://maps.app.goo.gl/Fznq9KLusdNXH2RE7"
              >
                Jl. D.I. Panjaitan No. 128
              </a>
            </p>

            <p>
              <!-- Nomor WhatsApp -->

              <a href="https://wa.me/6287823023379">
                0878-2302-3379
              </a>
            </p>

            <p>
              <!-- Email Caffora -->

              <a href="mailto:Helpcaffora@gmail.com">
                Helpcaffora@gmail.com
              </a>
            </p>
          </div>
        </div>

        <hr
          class="border-secondary-subtle my-4"
        />
        <!-- Garis pembatas footer atas dan bawah -->

        <div
          class="d-flex flex-wrap justify-content-between align-items-center small"
        >
          <!-- Baris bawah footer: copyright + link Terms -->

          <div>
            <!-- Teks hak cipta -->

            © 2025 Caffora — All rights reserved
          </div>

          <div class="d-flex gap-3">
            <!-- Link Terms / Privacy / Policy -->

            <a href="#">
              <!-- Link Terms (placeholder) -->

              Terms
            </a>

            <a href="#">
              <!-- Link Privacy (placeholder) -->

              Privacy
            </a>

            <a href="#">
              <!-- Link Policy (placeholder) -->

              Policy
            </a>
          </div>
        </div>
      </div>
    </footer>

    <!-- ================= TOAST LOGIN (tidak dipakai lagi, tapi dibiarkan) ================= -->
    <div
      class="toast align-items-center text-bg-dark border-0 toast-mini"
      role="alert"
      aria-live="assertive"
      aria-atomic="true"
      id="loginToast"
    >
      <!-- Komponen toast bootstrap untuk info login (diblock lewat JS) -->

      <div class="d-flex">
        <!-- Konten dalam toast (teks + tombol tutup) -->

        <div class="toast-body">
          <!-- Isi pesan toast -->

          Silakan login terlebih dahulu untuk menambahkan ke
          keranjang.
        </div>

        <button
          type="button"
          class="btn-close btn-close-white me-2 m-auto"
          data-bs-dismiss="toast"
          aria-label="Close"
        >
          <!-- Tombol close toast -->
        </button>
      </div>
    </div>

    <!-- Tidak ada lagi #cartToast di sini -->

    <!-- JS -->
    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    ></script>
    <!-- Memuat JS bundle Bootstrap (termasuk Popper) -->

    <script
      src="https://code.iconify.design/3/3.1.1/iconify.min.js"
    ></script>
    <!-- Memuat library Iconify untuk ikon -->

    <script
      src="../assets/js/app.js?v=1013"
      defer
    ></script>
    <!-- Memuat skrip app.js utama (defer agar jalan setelah HTML siap) -->

    <!-- MATIKAN SEMUA ALERT BROWSER DI HALAMAN INI -->
    <script>
      // Fungsi IIFE untuk override window.alert
      (function () {
        // Override fungsi alert global
        window.alert = function () {
          // console.log('alert diblokir:', arguments[0]);
          // Komen di atas bisa diaktifkan kalau ingin debugging
          return; // Tidak melakukan apa-apa saat alert dipanggil
        };
      })();
    </script>

    <!-- NAVBAR ACTIVE -->
    <script>
      // Fungsi IIFE untuk mengatur class active pada navbar
      (function () {
        // Ambil element ul navbar utama dengan id mainNav
        const nav = document.getElementById("mainNav");

        // Jika navbar ada
        if (nav) {
          // Ambil semua link dengan class .nav-link di navbar
          nav.querySelectorAll(".nav-link").forEach((a) => {
            // Tambahkan event click ke setiap link
            a.addEventListener("click", function () {
              // Hilangkan class active di semua link
              nav
                .querySelectorAll(".nav-link")
                .forEach((x) =>
                  x.classList.remove("active")
                );

              // Tambahkan class active ke link yang diklik
              this.classList.add("active");
            });
          });
        }

        // Ambil semua link nav di dalam offcanvas mobile
        const mobileLinks = document.querySelectorAll(
          "#mobileNav .nav-link"
        );

        // Loop tiap link mobile
        mobileLinks.forEach((link) => {
          // Tambahkan event click
          link.addEventListener("click", function () {
            // Hilangkan class active di semua link mobile
            mobileLinks.forEach((l) =>
              l.classList.remove("active")
            );

            // Tambahkan active ke link yang diklik
            this.classList.add("active");
          });
        });
      })();
    </script>

    <!-- BADGE KERANJANG (sinkron per-akun dari app.js) -->
    <script>
      // Menyesuaikan badge cart saat halaman selesai dimuat
      document.addEventListener(
        "DOMContentLoaded",
        function () {
          // Cek apakah fungsi updateCartBadge tersedia
          if (
            typeof window.updateCartBadge ===
            "function"
          ) {
            // Panggil fungsi untuk update badge
            window.updateCartBadge();
          }
        }
      );
    </script>

    <!-- PATCH IMAGE CART -->
    <script>
      // IIFE untuk patch image di data cart pada localStorage
      (function () {
        // Konstanta base path project
        const PROJECT_BASE = "/caffora-app1";

        // Base path ke folder public
        const PUBLIC_BASE = PROJECT_BASE + "/public";

        // Folder default untuk uploads gambar menu
        const UPLOADS_DIR = "/uploads/menu/";

        // Key localStorage untuk cart Caffora
        const KEY = "caffora_cart";

        // Fungsi untuk mengubah URL relatif menjadi absolut
        function toAbs(url) {
          // Jika URL kosong, return string kosong
          if (!url) return "";

          // Jika sudah absolut (diawali http/https), langsung kembalikan
          if (/^https?:\/\//i.test(url)) return url;

          // Jika sudah mengandung PUBLIC_BASE di awal, kembalikan apa adanya
          if (url.indexOf(PUBLIC_BASE) === 0)
            return url;

          // Jika diawali "/uploads/", gabung dengan PUBLIC_BASE
          if (url.indexOf("/uploads/") === 0)
            return PUBLIC_BASE + url;

          // Jika diawali "uploads/", tambahkan "/" di depan folder
          if (url.indexOf("uploads/") === 0)
            return PUBLIC_BASE + "/" + url;

          // Jika tidak diawali slash, anggap nama file di dir uploads/menu
          if (url[0] !== "/")
            return (
              PUBLIC_BASE +
              UPLOADS_DIR +
              url
            );

          // Jika sudah diawali slash, gabungkan dengan PUBLIC_BASE
          return PUBLIC_BASE + url;
        }

        // Fungsi membaca data cart dari localStorage
        function readCart() {
          try {
            // Parse JSON dari localStorage, fallback ke array kosong
            return JSON.parse(
              localStorage.getItem(KEY) || "[]"
            );
          } catch (e) {
            // Jika error parse, kembalikan array kosong
            return [];
          }
        }

        // Fungsi menulis data cart ke localStorage
        function writeCart(c) {
          // Simpan versi string JSON dari array cart
          localStorage.setItem(
            KEY,
            JSON.stringify(c)
          );
        }

        // Fungsi mempatch image_url item cart berdasarkan id
        function patchItemImage(id, candidate) {
          // Baca data cart yang ada
          const cart = readCart();

          // Loop semua item cart
          for (let i = 0; i < cart.length; i++) {
            // Bandingkan id item dengan id yang dikirim (pakai string)
            if (
              String(cart[i].id) ===
              String(id)
            ) {
              // Ambil image_url yang sudah ada (fallback ke field image)
              const currentImage =
                cart[i].image_url ||
                cart[i].image ||
                "";

              // Jika belum ada image atau masih placeholder
              if (
                !currentImage ||
                /placeholder/i.test(
                  currentImage
                )
              ) {
                // Pakai candidate jika ada, atau currentImage
                let src =
                  candidate || currentImage;

                // Jika sama sekali tidak ada src, keluar loop
                if (!src) break;

                // Normalisasi path (backslash ke slash) dan buat absolut
                cart[i].image_url = toAbs(
                  src.replace(/\\/g, "/")
                );

                // Simpan hasil perubahan cart
                writeCart(cart);
              }

              // Sudah ketemu itemnya, hentikan loop
              break;
            }
          }
        }

        // Selektor tombol-tombol yang memicu add to cart
        const SELECTORS = [
          ".btn-add",
          "[data-add-to-cart]",
          "[data-action='add']",
          "button.add-to-cart"
        ];

        // Listen click di seluruh dokumen (capturing)
        document.addEventListener(
          "click",
          function (ev) {
            // Variabel penampung tombol yang terdeteksi
            let btn = null;

            // Loop semua selector yang memungkinkan
            for (
              let i = 0;
              i < SELECTORS.length;
              i++
            ) {
              // Cari elemen terdekat dari target yang sesuai selector
              const t =
                ev.target.closest(
                  SELECTORS[i]
                );

              // Jika ketemu, simpan ke btn dan break
              if (t) {
                btn = t;
                break;
              }
            }

            // Jika tidak menemukan tombol apapun, hentikan handler
            if (!btn) return;

            // Ambil id menu dari attribute data-id
            const id =
              btn.getAttribute("data-id") ||
              btn.dataset.id ||
              "";

            // Ambil URL gambar kandidat dari data-img
            let imgCandidate =
              btn.getAttribute("data-img") ||
              btn.dataset.img ||
              "";

            // Jika belum ada kandidat gambar
            if (!imgCandidate) {
              // Cari kartu menu terdekat (card-menu / card / product / item)
              const card = btn.closest(
                ".card-menu, .card, .product, .item"
              );

              // Cari element img di dalam kartu
              const imgEl = card
                ? card.querySelector("img")
                : null;

              // Jika ada img, ambil attribute src-nya
              if (imgEl) {
                imgCandidate =
                  imgEl.getAttribute("src") ||
                  "";
              }
            }

            // Gunakan setTimeout kecil agar cart sudah diproses app.js
            setTimeout(function () {
              // Panggil patchItemImage untuk update image_url di cart
              patchItemImage(id, imgCandidate);
            }, 60);
          },
          true // gunakan capturing
        );
      })();
    </script>

    <!-- OFFCANVAS -->
    <script>
      // Inisialisasi bootstrap Offcanvas dan handle close dari icon list
      document.addEventListener(
        "DOMContentLoaded",
        function () {
          // Ambil element offcanvas berdasarkan id
          const offcanvasEl =
            document.getElementById(
              "mobileNav"
            );

          // Jika offcanvas tidak ditemukan, hentikan
          if (!offcanvasEl) return;

          // Buat instance Offcanvas dari element
          const bsOffcanvas =
            new bootstrap.Offcanvas(
              offcanvasEl
            );

          // Cari ikon list di header offcanvas
          const toggleBtnInside =
            offcanvasEl.querySelector(
              ".offcanvas-header .bi-list"
            );

          // Jika ikon list ditemukan
          if (toggleBtnInside) {
            // Ubah cursor menjadi pointer
            toggleBtnInside.style.cursor =
              "pointer";

            // Tambahkan event click untuk menutup offcanvas
            toggleBtnInside.addEventListener(
              "click",
              () => {
        
                bsOffcanvas.hide();
              }
            );
          }
        }
      );
    </script>

    <!-- Notif badge -->
    <script>
      // IIFE untuk mengatur tampilan badge notif
      (function () {
        // Ambil elemen badge notif berdasarkan id
        const badge =
          document.getElementById(
            "badgeNotif"
          );

        // Jika tidak ada badge, hentikan
        if (!badge) return;

        // Fungsi async untuk refresh jumlah unread notif
        async function refreshNotifBadge() {
          try {
            // Kirim request ke endpoint unread_count
            const res = await fetch(
              "/backend/api/notifications.php?action=unread_count",
              {
                credentials:
                  "same-origin", // sertakan cookies
                cache: "no-store" // jangan cache hasil
              }
            );

            // Parse response ke JSON
            const js = await res.json();

            // Jika struktur JSON valid dan ok === true
            if (js && js.ok) {
              // Jika jumlah unread > 0
              if (
                js.count &&
                js.count > 0
              ) {
                // Tampilkan badge notif
                badge.classList.remove(
                  "d-none"
                );
              } else {
                // Sembunyikan badge jika count 0
                badge.classList.add(
                  "d-none"
                );
              }
            }
          } catch (err) {
            // diamkan error (tidak mengganggu UX)
          }
        }

        // Panggil pertama kali saat script di-load
        refreshNotifBadge();

        // Jadwalkan refresh setiap 15 detik
        setInterval(
          refreshNotifBadge,
          15000
        );
      })();
    </script>

    <!-- Trigger show all -->
    <script>
      // IIFE untuk trigger filter "Tampilkan Semua" dari berbagai interaksi
      (function () {
        // Listener global click untuk link filter "all"
        document.addEventListener(
          "click",
          function (ev) {
            // Cari anchor di dalam #filterMenu dengan data-filter="all"
            const a =
              ev.target.closest(
                '#filterMenu [data-filter="all"]'
              );

            // Jika bukan klik di link "all", hentikan
            if (!a) return;

            // Mencegah aksi default (navigasi)
            ev.preventDefault();

            // Jika fungsi cafforaShowAll sudah tersedia
            if (
              typeof window.cafforaShowAll ===
              "function"
            ) {
              // Panggil fungsi untuk menampilkan semua produk
              window.cafforaShowAll();
            } else {
              // Jika belum tersedia, tunggu sampai DOMContentLoaded
              window.addEventListener(
                "DOMContentLoaded",
                function () {
                  // Pastikan fungsi sudah ada, lalu panggil
                  if (
                    typeof window.cafforaShowAll ===
                    "function"
                  ) {
                    window.cafforaShowAll();
                  }
                }
              );
            }
          },
          true // capturing mode
        );

        // Ambil semua link yang mengarah ke #menuSection
        document
          .querySelectorAll(
            'a[href="#menuSection"]'
          )
          .forEach(function (link) {
            // Tambahkan event click ke tiap link
            link.addEventListener(
              "click",
              function () {
                // Jika fungsi showAll sudah ada
                if (
                  typeof window.cafforaShowAll ===
                  "function"
                ) {
                  // Panggil cafforaShowAll dengan sedikit delay
                  setTimeout(
                    window.cafforaShowAll,
                    50
                  );
                }
              }
            );
          });
      })();
    </script>

    <!-- DISABLE TOAST CART: fungsi dikosongkan supaya tidak ada alert UI -->
    <script>
      // Override fungsi showCartToast agar tidak menampilkan toast apapun
      window.showCartToast = function () {
        // Fungsi kosong, tidak melakukan apa-apa
        return;
      };
    </script>

    <!-- KILLER PATCH: blokir toast "Silakan login" lama -->
    <script>
      // IIFE untuk mematikan toast login lama dan fungsi requireLogin
      (function () {
        // Listener global untuk event show.bs.toast
        document.addEventListener(
          "show.bs.toast",
          function (ev) {
            try {
              // Jika target toast adalah loginToast
              if (
                ev &&
                ev.target &&
                ev.target.id ===
                  "loginToast"
              ) {
                // Stop propagation event
                ev.stopImmediatePropagation();
                ev.stopPropagation();

                // Cegah default behavior jika ada
                if (
                  typeof ev.preventDefault ===
                  "function"
                ) {
                  ev.preventDefault();
                }
              }
            } catch (e) {
              // Abaikan error
            }
          },
          true // capturing
        );

        // Jika bootstrap.Toast sudah terdefinisi
        if (
          window.bootstrap &&
          window.bootstrap.Toast &&
          window.bootstrap.Toast
            .prototype
        ) {
          // Simpan fungsi show asli
          const _show =
            window.bootstrap.Toast
              .prototype.show;

          // Override prototype show
          window.bootstrap.Toast.prototype.show =
            function () {
              try {
                // Ambil element toast yang terkait instance ini
                const el =
                  this &&
                  this._element
                    ? this._element
                    : null;

                // Jika element adalah loginToast, jangan tampilkan
                if (
                  el &&
                  el.id ===
                    "loginToast"
                ) {
                  return; // stop
                }
              } catch (e) {
                // Abaikan error
              }

              // Panggil fungsi show asli untuk toast lain
              return _show.apply(
                this,
                arguments
              );
            };
        }

        // Ambil element loginToast di DOM
        const lt =
          document.getElementById(
            "loginToast"
          );

        // Jika ditemukan, paksa display none important
        if (lt) {
          lt.style.setProperty(
            "display",
            "none",
            "important"
          );
        }

        // Fungsi netral yang selalu mengembalikan true
        const neutral = function () {
          return true;
        };

        // Daftar nama fungsi login check yang ingin dinetralkan
        [
          "requireLogin",
          "needLogin",
          "mustLogin",
          "ensureLogin"
        ].forEach((fn) => {
          // Jika fungsi tersebut ada di window
          if (
            typeof window[fn] ===
            "function"
          ) {
            try {
              // Ganti dengan fungsi netral
              window[fn] = neutral;
            } catch (e) {
              // Abaikan error
            }
          }
        });
      })();
    </script>
      <!-- SAFE LOGOUT REDIRECT (FIX 404) -->
    <script>
      // IIFE untuk memastikan semua link dengan atribut data-logout
      // melakukan logout ke backend lalu redirect ke halaman login yang benar
      (function () {
        // String penanda lokasi "/public/" di dalam path URL
        const PUBLIC_SPLIT = "/public/";

        // Cari posisi substring "/public/" di pathname saat ini
        const idx = location.pathname.indexOf(PUBLIC_SPLIT);

        // Hitung PROJECT_BASE, yaitu bagian path sebelum "/public/"
        // Contoh: "/caffora-app1/public/customer/index.php" -> "/caffora-app1"
        const PROJECT_BASE =
          idx > -1 ? location.pathname.slice(0, idx) : "";

        // Tentukan URL halaman login berdasarkan PROJECT_BASE
        // Contoh: "/caffora-app1/public/login.html"
        const LOGIN_URL = PROJECT_BASE + "/public/login.html";

        // Tentukan URL endpoint logout di backend
        // Contoh: "/caffora-app1/backend/logout.php"
        const LOGOUT_URL = PROJECT_BASE + "/backend/logout.php";

        // Ambil semua elemen yang memiliki atribut data-logout
        document.querySelectorAll("[data-logout]").forEach((a) => {
          // Pastikan href-nya mengarah ke LOGIN_URL (untuk fallback jika JS mati)
          a.setAttribute("href", LOGIN_URL);

          // Tambahkan event listener saat link diklik
          a.addEventListener("click", function (e) {
            // Cegah perilaku default (navigasi langsung ke href)
            e.preventDefault();

            // Panggil endpoint logout di backend menggunakan fetch
            fetch(LOGOUT_URL, {
              credentials: "same-origin", // kirim cookie session
              cache: "no-store" // jangan cache respons
            }).finally(() => {
              // Setelah logout (baik sukses maupun gagal), redirect ke login
              window.location.replace(LOGIN_URL);
            });
          });
        });
      })();
    </script>
  </body>
</html>

