<?php
// public/customer/checkout.php
// Halaman checkout untuk customer (wajib login sebagai customer)

declare(strict_types=1); // Aktifkan strict types di PHP

// Import guard otentikasi
require_once __DIR__ . '/../../backend/auth_guard.php';
// pastikan hanya customer yang bisa akses halaman ini
require_login(['customer']); // pastikan hanya customer yang bisa akses

// Nama default di input diambil dari sesi login
$nameDefault = $_SESSION['user_name'] ?? '';      // nama user dari session
$userId      = (int)($_SESSION['user_id'] ?? 0);  // id user dari session (cast ke int)
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Checkout — Caffora</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap & Icons -->
  <!-- CSS Bootstrap 5 -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
  >
  <!-- Icon-ikon dari Bootstrap Icons -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    rel="stylesheet"
  >
 <style>
    :root { /* Selector root untuk definisi variabel global CSS */
      /* Variabel warna dan styling dasar */
      --yellow:       #FFD54F;  /* Kuning utama (brand Caffora) */
      --camel:        #DAA85C;  /* Warna camel / coklat muda */
      --brown:        #4B3F36;  /* Coklat tua untuk teks utama */
      --line:         #e5e7eb;  /* Warna garis pemisah yang lembut */
      --bg:           #fffdf8;  /* Background halaman warna krem */
      --gold:         #FFD54F;  /* Alias warna gold (sama dengan yellow) */
      --gold-200:     #FFE883;  /* Gold versi lebih terang */
      --gold-soft:    #F6D472;  /* Gold lembut untuk border/focus */
      --input-border: #E8E2DA;  /* Warna border input form */
    } /* Akhir blok :root */

    /* Global */
    * { /* Selector global untuk semua elemen */
      /* Font utama Poppins, fallback ke system font */
      font-family: Poppins, system-ui, -apple-system, "Segoe UI",
                   Roboto, Arial, sans-serif; /* Urutan fallback font */
      box-sizing: border-box; /* Border-box untuk kontrol layout lebih mudah */
    } /* Akhir selector * */

    body { /* Styling untuk elemen body */
      background: var(--bg);   /* Background krem lembut (pakai variabel --bg) */
      color: var(--brown);     /* Teks utama warna coklat (variabel --brown) */
      overflow-x: hidden;      /* Hindari scroll horizontal */
      margin: 0;               /* Hilangkan margin default body */
    } /* Akhir body */

    /* ====== TOPBAR ====== */
    .topbar { /* Container bar bagian atas halaman */
      background: #fff;                           /* Topbar putih */
      border-bottom: 1px solid rgba(0,0,0,.05);   /* Garis bawah tipis */
      position: sticky;                           /* Menempel di atas saat scroll */
      top: 0;                                     /* Nempel ke sisi atas viewport */
      z-index: 20;                                /* Di atas konten lain */
    } /* Akhir .topbar */

    .topbar-inner { /* Wrapper isi topbar (konten di dalam bar) */
      max-width: 1200px;       /* Lebar konten sama dengan page */
      margin: 0 auto;          /* Center di tengah secara horizontal */
      padding: 12px 24px;      /* Ruang dalam atas–bawah & kiri–kanan */
      min-height: 52px;        /* Tinggi minimum topbar */
      display: flex;           /* Susunan flex row */
      align-items: center;     /* Vertikal center isi (ikon + teks) */
      gap: 10px;               /* Jarak antara child (ikon dan teks) */
    } /* Akhir .topbar-inner */

    .back-link { /* Link “Kembali” di topbar */
      display: inline-flex;    /* Link dengan flex (ikon + teks) */
      align-items: center;     /* Vertikal center ikon dan teks */
      gap: 10px;               /* Jarak antara ikon dan teks */
      color: var(--brown);     /* Warna teks link (coklat brand) */
      text-decoration: none;   /* Hilangkan underline link */
      border: 0;               /* Tanpa border (kalau jadi <button>) */
      background: transparent; /* Background transparan */
      padding: 0;              /* Tanpa padding extra */
    } /* Akhir .back-link */

    .back-link span { /* Teks di dalam link kembali */
      /* Paksa font tombol kembali pakai system-ui (lebih simple) */
      font-family: system-ui, -apple-system, "Segoe UI",
                   Roboto, Arial, sans-serif !important; /* Override font ke system font */
      font-size: 1rem; /* 16px; ukuran teks */
      font-weight: 600; /* Tebal medium */
      line-height: 1.3; /* Jarak antar garis teks */
    } /* Akhir .back-link span */

    .back-link .bi { /* Ikon panah pada link kembali */
      width: 18px;               /* Lebar area ikon */
      height: 18px;              /* Tinggi area ikon */
      display: inline-flex;      /* Flex untuk center icon */
      align-items: center;       /* Vertikal center icon */
      justify-content: center;   /* Horizontal center icon */
      font-size: 18px !important;/* Ukuran font ikon (Bootstrap Icons) */
      line-height: 18px !important; /* Line-height disamakan dengan tinggi */
    } /* Akhir .back-link .bi */

    /* ====== LAYOUT CONTENT ====== */
    .page { /* Container utama konten halaman checkout */
      max-width: 1200px;           /* Lebar maksimal konten utama */
      margin: 0 auto 32px;         /* Nempel topbar, ada margin bawah 32px */
      padding: 0 24px;             /* Padding kiri-kanan konten */
    } /* Akhir .page */

    /* Ringkasan item (list item di atas form) */
    .item-row { /* Satu baris ringkasan item di checkout */
      display: flex;                             /* Susunan horizontal */
      align-items: center;                       /* Vertikal center */
      justify-content: space-between;            /* Jarak antara kiri & kanan */
      gap: 12px;                                 /* Jarak antar kolom */
      padding: 14px 0;                           /* Padding atas–bawah */
      border-bottom: 1px solid rgba(0,0,0,.06);  /* Garis pembatas antar item */
    } /* Akhir .item-row */

    .left-info { /* Bagian kiri baris item (gambar + nama) */
      display: flex;          /* Susunan horizontal */
      gap: 12px;              /* Jarak thumbnail dan teks */
      align-items: center;    /* Vertikal center */
      flex: 1;                /* Ambil sisa ruang yang ada */
      min-width: 0;           /* Supaya teks bisa ter-ellipsis */
    } /* Akhir .left-info */

    .thumb { /* Gambar thumbnail produk */
      width: 64px;            /* Lebar gambar 64px */
      height: 64px;           /* Tinggi gambar 64px */
      border-radius: 12px;    /* Thumbnail dengan sudut membulat */
      object-fit: cover;      /* Gambar menyesuaikan tanpa distorsi */
      background: #f3f3f3;    /* Background abu jika gambar gagal load */
      flex-shrink: 0;         /* Jangan mengecil saat ruang sempit */
    } /* Akhir .thumb */

    .name { /* Teks nama item (judul produk + qty) */
      font-weight: 600;           /* Teks tebal medium */
      font-size: 1rem;            /* Ukuran teks 16px */
      line-height: 1.3;           /* Line-height sedikit rapat */
      color: #2b2b2b;             /* Warna teks abu gelap */
      white-space: nowrap;        /* Teks satu baris saja */
      overflow: hidden;           /* Teks berlebih disembunyikan */
      text-overflow: ellipsis;    /* Tampilkan ... saat kepanjangan */
    } /* Akhir .name */

    .line-right { /* Kolom kanan baris item (harga total per item) */
      flex-shrink: 0;             /* Kolom harga tidak ikut menyempit */
      font-weight: 600;           /* Teks tebal */
      font-size: .95rem;          /* Sedikit lebih kecil dari nama */
      color: var(--brown);        /* Warna coklat brand */
      text-align: right;          /* Teks rata kanan */
      min-width: 90px;            /* Lebar minimum kolom harga */
    } /* Akhir .line-right */

    /* Total / subtotal */
    .tot-block { /* Blok ringkasan total (subtotal, pajak, total) */
      margin-top: 10px;                             /* Jarak atas dari list item */
      border-top: 2px dashed rgba(0,0,0,.05);       /* Garis putus-putus setelah list item */
      padding-top: 10px;                            /* Padding atas di dalam blok total */
    } /* Akhir .tot-block */

    .tot-line { /* Baris subtotal & pajak */
      display: flex;                     /* Susunan horizontal */
      justify-content: space-between;    /* Label di kiri, angka di kanan */
      align-items: center;               /* Vertikal center teks */
      margin-bottom: 6px;                /* Jarak antar baris */
      font-size: .93rem;                 /* Ukuran teks sedikit kecil */
      color: #4b3f36;                    /* Warna teks coklat medium */
    } /* Akhir .tot-line */

    .tot-line strong { /* Teks tebal di dalam baris subtotal/pajak */
      font-weight: 600; /* Tebal medium */
    } /* Akhir .tot-line strong */

    .tot-grand { /* Baris total akhir (grand total) */
      display: flex;                     /* Susunan horizontal */
      justify-content: space-between;    /* “Total” kiri, nominal kanan */
      align-items: center;               /* Vertikal center teks */
      margin-top: 6px;                   /* Jarak atas dari baris sebelumnya */
      font-weight: 700;                  /* Lebih tebal (grand total) */
      font-size: 1rem;                   /* Ukuran teks normal (16px) */
      color: #2b2b2b;                    /* Warna teks abu gelap */
    } /* Akhir .tot-grand */

    /* ====== FORM INPUT ====== */
    .form-label { /* Label untuk field form */
      font-weight: 600;        /* Teks label tebal */
      font-size: 1rem;         /* Ukuran teks 16px */
      line-height: 1.3;        /* Tinggi baris label */
      color: #2b2b2b;          /* Warna teks label abu gelap */
      margin-bottom: 6px;      /* Jarak antara label dan input */
    } /* Akhir .form-label */

    .form-control { /* Input teks bawaan Bootstrap yang dikustom */
      width: 100%;                   /* Lebar penuh */
      max-width: 100%;               /* Tidak melebihi lebar container */
      border-radius: 14px !important;/* Input membulat (override Bootstrap) */
      padding: 8px 14px;             /* Padding dalam input */
      font-size: .95rem;             /* Ukuran teks input */
      line-height: 1.3;              /* Line-height teks input */
      border: 1px solid var(--input-border); /* Warna border input */
      background-color: #fff;        /* Background putih */
      box-shadow: none;              /* Hilangkan shadow default */
      transition: border-color .12s ease; /* Animasi halus saat border berubah */
    } /* Akhir .form-control */

    .form-control:focus { /* State saat input fokus */
      border-color: var(--gold-soft) !important; /* Warna border kuning lembut */
      box-shadow: none !important;               /* Hilangkan shadow focus Bootstrap */
    } /* Akhir .form-control:focus */

    /* Custom select (Tipe layanan & Pembayaran) */
    .cf-select { /* Wrapper custom select Caffora */
      position: relative; /* Dibutuhkan untuk posisi dropdown list */
      width: 100%;        /* Lebar penuh */
    } /* Akhir .cf-select */

    .cf-select__trigger { /* Tombol pemicu dropdown custom select */
      width: 100%;                             /* Lebar penuh */
      background: #fff;                        /* Background putih */
      border: 1px solid var(--input-border);   /* Border sama seperti input biasa */
      border-radius: 14px;                     /* Sudut membulat */
      padding: 8px 38px 8px 14px;              /* Padding, beri ruang untuk ikon kanan */
      display: flex;                           /* Susunan horizontal */
      align-items: center;                     /* Vertikal center isi */
      justify-content: space-between;          /* Label kiri, ikon kanan */
      gap: 12px;                               /* Jarak label dan ikon */
      cursor: pointer;                         /* Tanda bisa diklik */
      transition: border-color .12s ease;      /* Transisi perubahan border */
    } /* Akhir .cf-select__trigger */

    .cf-select__trigger:focus-visible,
    .cf-select.is-open .cf-select__trigger { /* Saat fokus keyboard atau dropdown terbuka */
      border-color: var(--gold-soft); /* Border kuning saat fokus / open */
      outline: none;                  /* Hilangkan outline default */
    } /* Akhir state focus/open trigger */

    .cf-select__text { /* Teks label di custom select */
      font-size: .95rem;      /* Ukuran teks label */
      color: #2b2b2b;         /* Warna teks label */
      white-space: nowrap;    /* Satu baris saja */
      overflow: hidden;       /* Sembunyikan teks berlebih */
      text-overflow: ellipsis;/* Tambahkan ... jika kepanjangan */
    } /* Akhir .cf-select__text */

    .cf-select__icon { /* Ikon panah di custom select */
      flex: 0 0 auto;           /* Tidak melebar / mengecil */
      color: var(--brown);      /* Warna ikon */
      font-size: .9rem;         /* Ukuran ikon sedikit kecil */
    } /* Akhir .cf-select__icon */

    .cf-select__list { /* Dropdown list opsi custom select */
      position: absolute;                   /* Posisi relatif terhadap .cf-select */
      left: 0;                              /* Nempel ke kiri trigger */
      top: calc(100% + 6px);                /* Muncul sedikit di bawah trigger */
      width: 100%;                          /* Lebar sama dengan trigger */
      background: #fff;                     /* Background putih */
      border: 1px solid rgba(0,0,0,.02);    /* Border sangat tipis */
      border-radius: 14px;                  /* Sudut membulat */
      box-shadow: 0 16px 30px rgba(0,0,0,.09); /* Shadow lembut */
      overflow: hidden;                     /* Hilangkan overflow */
      z-index: 40;                          /* Di depan elemen lain */
      display: none;                        /* Hidden default */
      max-height: 260px;                    /* Tinggi maksimal dropdown */
      overflow-y: auto;                     /* Scroll vertikal jika tinggi lebih */
    } /* Akhir .cf-select__list */

    .cf-select.is-open .cf-select__list { /* Dropdown terlihat saat parent punya class is-open */
      display: block;               /* Tampil jika punya class is-open */
    } /* Akhir state open list */

    .cf-select__option { /* Satu baris opsi di dropdown */
      padding: 9px 14px;        /* Padding dalam opsi */
      font-size: .9rem;         /* Ukuran teks opsi */
      color: #413731;           /* Warna teks coklat gelap */
      cursor: pointer;          /* Cursor tangan (bisa diklik) */
      background: #fff;         /* Background putih */
    } /* Akhir .cf-select__option */

    .cf-select__option:hover { /* State hover saat mouse di atas opsi */
      background: #FFF2C9;          /* Hover kuning lembut */
    } /* Akhir .cf-select__option:hover */

    .cf-select__option.is-active { /* Opsi yang sedang terpilih */
      background: #FFEB9B;          /* Opsi terpilih warna kuning */
      font-weight: 600;             /* Teks lebih tebal */
    } /* Akhir .cf-select__option.is-active */

    /* Tombol utama konfirmasi */
    .btn-primary-cf { /* Tombol "Konfirmasi Pesanan" custom */
      background-color: var(--gold);         /* Kuning emas */
      color: var(--brown) !important;        /* Teks warna coklat, pakai !important */
      border: 0;                             /* Tanpa border */
      border-radius: 14px;                   /* Sudut membulat */
      font-family: Arial, Helvetica, sans-serif; /* Font tombol pakai Arial */
      font-weight: 600;                      /* Teks tombol tebal */
      font-size: .88rem;                     /* Ukuran teks sedikit kecil */
      padding: 10px 18px;                    /* Padding dalam tombol */
      display: inline-flex;                  /* Susunan flex agar bisa align center */
      align-items: center;                   /* Vertikal center isi */
      justify-content: center;               /* Horizontal center isi */
      gap: 8px;                              /* Jarak jika ada ikon di dalam tombol */
      white-space: nowrap;                   /* Teks tidak dibungkus ke baris baru */
      box-shadow: none;                      /* Hilangkan shadow default */
      cursor: pointer;                       /* Cursor tangan (bisa diklik) */
    } /* Akhir .btn-primary-cf */

    /* GRID FORM RESPONSIVE */
    .form-grid { /* Grid untuk form (nama, layanan, meja, pembayaran) */
      display: flex;          /* Mobile default: 1 kolom dengan flex column */
      flex-direction: column; /* Susunan vertikal di mobile */
      gap: 18px;              /* Jarak antar blok form (mobile) */
    } /* Akhir .form-grid */

    .field-block { /* Wrapper untuk tiap blok field (nama, tipe, meja, pembayaran) */
      width: 100%; /* Lebar penuh dari parent */
    } /* Akhir .field-block */

    @media (min-width: 992px) { /* Breakpoint untuk desktop (>= 992px) */
      .form-grid { /* Atur ulang layout form di desktop */
        display: grid;        /* Desktop: atur jadi grid 2 kolom */
        grid-template-columns: 1fr 1fr; /* Dua kolom sama besar */
        column-gap: 18px;     /* Jarak horizontal antar kolom */
        row-gap: 8px;         /* Jarak vertikal antar baris */
        align-items: flex-start; /* Align item di atas */
      } /* Akhir .form-grid (desktop) */

      /* Mapping posisi field di grid desktop */
      /* Baris 1 */
      .field-name   { grid-column: 1; grid-row: 1; } /* Field nama di kolom 1 baris 1 */
      .field-service{ grid-column: 2; grid-row: 1; } /* Field tipe layanan di kolom 2 baris 1 */

      /* Baris 2 */
      .field-payment{ grid-column: 1; grid-row: 2; } /* Field metode pembayaran di kolom 1 baris 2 */
      .field-table  { grid-column: 2; grid-row: 2; } /* Field nomor meja di kolom 2 baris 2 */

      .page { /* Penyesuaian untuk kontainer halaman di desktop */
        padding-bottom: 80px;  /* Ruang bawah ekstra untuk desktop */
      } /* Akhir .page (desktop) */

      #checkoutForm { /* Penyesuaian untuk form checkout di desktop */
        padding-bottom: 60px;  /* Ruang antara form dan bawah halaman */
      } /* Akhir #checkoutForm (desktop) */
    } /* Akhir @media (min-width: 992px) */

    @media (max-width: 600px) { /* Breakpoint untuk layar kecil (<= 600px) */
      .topbar-inner,
      .page { /* Topbar dan konten di mobile */
        max-width: 100%;   /* Lebar penuh layar */
        padding: 12px 16px; /* Sedikit lebih rapat di mobile */
      } /* Akhir .topbar-inner & .page (mobile) */

      .page { /* Kontainer halaman di mobile */
        margin: 0 auto 90px; /* Tetap tanpa gap atas, ada jarak bawah ekstra */
      } /* Akhir .page (mobile) */

      /* 👉 Jarak antar form di mobile sedikit diperkecil */
      .form-grid { /* Form grid di mobile */
        gap: 5px;  /* Bikin jarak vertikal antar field lebih rapat */
      } /* Akhir .form-grid (mobile) */
    } /* Akhir @media (max-width: 600px) */

    /* ====== CUSTOM ALERT CAFFORA ====== */
    .cf-alert[hidden] { /* State ketika komponen alert diberi atribut hidden */
      display: none;                /* Jika ada atribut hidden, sembunyikan */
    } /* Akhir .cf-alert[hidden] */

    .cf-alert { /* Overlay untuk custom alert */
      position: fixed;              /* Tetap di posisi yang sama saat scroll */
      inset: 0;                     /* Full layar (top,right,bottom,left = 0) */
      z-index: 999;                 /* Di paling depan */
      display: flex;                /* Flex untuk center isi */
      align-items: center;          /* Vertikal center box alert */
      justify-content: center;      /* Horizontal center box alert */
      pointer-events: none;         /* Click tidak ke overlay, kecuali box alert */
    } /* Akhir .cf-alert */

    .cf-alert__backdrop { /* Background hitam transparan di belakang box alert */
      position: absolute;           /* Posisi absolut di dalam .cf-alert */
      inset: 0;                     /* Full area .cf-alert */
      background: rgba(0, 0, 0, 0.45); /* Latar hitam transparan 45% */
    } /* Akhir .cf-alert__backdrop */

    .cf-alert__box { /* Box utama alert (kartu pesan) */
      position: relative;           /* Supaya berada di atas backdrop */
      pointer-events: auto;         /* Box bisa diklik (override pointer-events parent) */
      max-width: 320px;             /* Lebar maksimum 320px */
      width: 90%;                   /* Lebar responsif (90% dari layar) */
      background: #fffdf8;          /* Background krem lembut */
      border-radius: 18px;          /* Sudut box yang membulat */
      padding: 16px 18px 14px;      /* Padding dalam box alert */
      box-shadow: 0 18px 40px rgba(0,0,0,.18); /* Shadow cukup tebal */
      display: flex;                /* Susunan elemen di dalam box */
      flex-direction: column;       /* Susunan vertikal (title, message, button) */
      gap: 10px;                    /* Jarak antar elemen di dalam box */
      font-family: Poppins, system-ui, -apple-system, "Segoe UI",
                   Roboto, Arial, sans-serif; /* Font family box alert */
    } /* Akhir .cf-alert__box */

    .cf-alert__title { /* Judul di dalam alert (misal: "Caffora") */
      font-weight: 600;             /* Teks tebal */
      font-size: 0.92rem;           /* Ukuran teks judul */
      color: var(--brown);          /* Warna coklat brand */
      margin-bottom: 2px;           /* Jarak bawah kecil dari judul ke pesan */
    } /* Akhir .cf-alert__title */

    .cf-alert__message { /* Isi pesan di alert */
      font-size: 0.9rem;            /* Ukuran teks pesan */
      color: #413731;               /* Warna coklat gelap */
    } /* Akhir .cf-alert__message */

    .cf-alert__btn { /* Tombol “Oke” di dalam alert */
      align-self: flex-end;         /* Posisi tombol di sisi kanan */
      margin-top: 6px;              /* Jarak atas dari pesan */
      border: 0;                    /* Tanpa border */
      border-radius: 999px;         /* Tombol pill (sangat bundar) */
      padding: 8px 18px;            /* Padding dalam tombol */
      background: var(--gold);      /* Background kuning emas */
      color: var(--brown);          /* Teks coklat */
      font-weight: 600;             /* Teks tebal */
      font-size: 0.9rem;            /* Ukuran teks tombol */
      cursor: pointer;              /* Cursor tangan */
    } /* Akhir .cf-alert__btn */

    /* Loader kecil di tombol (animasi spinner) */
    @keyframes spin { /* Definisi keyframes animasi spin */
      from { transform: rotate(0); }   /* Mulai dari rotasi 0 derajat */
      to   { transform: rotate(360deg); } /* Putar penuh 360 derajat */
    } /* Akhir @keyframes spin */
  </style>

<body> <!-- Awal body dokumen HTML -->

  <!-- TOP BAR -->
  <div class="topbar"> <!-- Wrapper bar bagian atas (sticky) -->
    <div class="topbar-inner"> <!-- Kontainer isi topbar yang terpusat -->
      <!-- Link kembali ke halaman cart -->
      <a class="back-link" href="./cart.php"> <!-- Link kembali menuju cart.php -->
        <i class="bi bi-arrow-left"></i> <!-- Ikon panah kiri dari Bootstrap Icons -->
        <span>Kembali</span> <!-- Teks “Kembali” di samping ikon -->
      </a>
    </div> <!-- Akhir .topbar-inner -->
  </div> <!-- Akhir .topbar -->

  <main class="page"> <!-- Kontainer utama konten halaman checkout -->
    <!-- RINGKASAN ITEM CART TERPILIH (diisi via JS) -->
    <div id="summary"></div> <!-- Tempat menampilkan list item dan total -->

    <!-- FORM CHECKOUT -->
    <form id="checkoutForm" class="mt-4 pb-4"> <!-- Form untuk proses checkout -->

      <div class="form-grid"> <!-- Grid untuk field-field form -->

        <!-- 1. Nama Customer -->
        <div class="field-block field-name mb-3"> <!-- Blok field nama customer -->
          <label class="form-label" for="customer_name">
            Nama Customer <!-- Label teks untuk input nama -->
          </label>
          <!-- Input nama customer, default diisi dari session PHP -->
          <input
            type="text"                       <!-- Jenis input teks -->
            class="form-control"              <!-- Kelas Bootstrap + custom -->
            id="customer_name"                <!-- ID untuk diakses JS & label -->
            value="<?= htmlspecialchars($nameDefault) ?>" <!-- Value diisi nama dari session -->
            required                          <!-- Wajib diisi sebelum submit -->
          >
        </div> <!-- Akhir blok field nama -->

        <!-- 2. Tipe Layanan (Dine In / Take Away) -->
        <div class="field-block field-service mb-3"> <!-- Blok field tipe layanan -->
          <label class="form-label">
            Tipe Layanan <!-- Label untuk custom select tipe layanan -->
          </label>
          <!-- Custom select Caffora -->
          <div class="cf-select" data-target="service_type"> <!-- Wrapper custom select, target ke input hidden service_type -->
            <div class="cf-select__trigger" tabindex="0"> <!-- Tombol trigger dropdown (bisa fokus keyboard) -->
              <span
                class="cf-select__text"
                id="service_type_label"
              >
                Dine In <!-- Teks default yang ditampilkan untuk tipe layanan -->
              </span>
              <i class="bi bi-chevron-down cf-select__icon"></i> <!-- Ikon panah bawah -->
            </div> <!-- Akhir .cf-select__trigger -->

            <div class="cf-select__list"> <!-- Dropdown list opsi tipe layanan -->
              <div
                class="cf-select__option is-active"
                data-value="dine_in"
              >
                Dine In <!-- Opsi layanan makan di tempat -->
              </div>
              <div
                class="cf-select__option"
                data-value="take_away"
              >
                Take Away <!-- Opsi layanan bawa pulang -->
              </div>
            </div> <!-- Akhir .cf-select__list -->
          </div> <!-- Akhir .cf-select -->

          <!-- Nilai asli disimpan di input hidden untuk dikirim ke backend -->
          <input
            type="hidden"                 <!-- Input tidak terlihat -->
            id="service_type"             <!-- ID yang dirujuk custom select JS -->
            value="dine_in"               <!-- Nilai default: dine_in -->
          >
        </div> <!-- Akhir blok field tipe layanan -->

        <!-- 3. Nomor Meja (hanya untuk Dine In) -->
        <div class="field-block field-table mb-3" id="tableWrap"> <!-- Blok field nomor meja, bisa disembunyikan -->
          <label class="form-label" for="table_no">
            Nomor Meja <!-- Label teks nomor meja -->
          </label>
          <input
            type="text"                <!-- Input teks biasa -->
            class="form-control"       <!-- Kelas form-control + styling custom -->
            id="table_no"              <!-- ID untuk diakses JS (syncTableField) -->
            placeholder="Misal: 05"    <!-- Placeholder contoh isian nomor meja -->
          >
        </div> <!-- Akhir blok field nomor meja -->

        <!-- 4. Metode Pembayaran -->
        <div class="field-block field-payment mb-4"> <!-- Blok field metode pembayaran -->
          <label class="form-label">
            Metode Pembayaran <!-- Label untuk custom select metode pembayaran -->
          </label>
          <!-- Custom select untuk metode pembayaran -->
          <div class="cf-select" data-target="payment_method"> <!-- Wrapper custom select payment, target input hidden payment_method -->
            <div class="cf-select__trigger" tabindex="0"> <!-- Trigger dropdown metode pembayaran -->
              <span
                class="cf-select__text"
                id="payment_method_label"
              >
                Cash <!-- Teks default untuk metode pembayaran -->
              </span>
              <i class="bi bi-chevron-down cf-select__icon"></i> <!-- Ikon panah bawah -->
            </div> <!-- Akhir .cf-select__trigger -->

            <div class="cf-select__list"> <!-- Dropdown list opsi pembayaran -->
              <div
                class="cf-select__option is-active"
                data-value="cash"
              >
                Cash <!-- Opsi bayar tunai -->
              </div>
              <div
                class="cf-select__option"
                data-value="bank_transfer"
              >
                Bank Transfer <!-- Opsi bayar via transfer bank -->
              </div>
              <div
                class="cf-select__option"
                data-value="qris"
              >
                QRIS <!-- Opsi bayar via QRIS -->
              </div>
              <div
                class="cf-select__option"
                data-value="ewallet"
              >
                E-Wallet <!-- Opsi bayar via dompet digital -->
              </div>
            </div> <!-- Akhir .cf-select__list -->
          </div> <!-- Akhir .cf-select -->

          <!-- Hidden input untuk nilai payment_method -->
          <input
            type="hidden"                 <!-- Input tersembunyi -->
            id="payment_method"           <!-- ID yang diupdate custom select via JS -->
            value="cash"                  <!-- Nilai default: cash -->
          >
        </div> <!-- Akhir blok field metode pembayaran -->
      </div> <!-- Akhir .form-grid -->

      <!-- TOMBOL SUBMIT -->
      <div class="d-flex justify-content-end mb-3 field-submit"> <!-- Wrapper tombol submit, rata kanan -->
        <button type="submit" class="btn-primary-cf">
          Konfirmasi Pesanan <!-- Teks tombol untuk submit form checkout -->
        </button>
      </div> <!-- Akhir wrapper tombol submit -->
    </form> <!-- Akhir form checkout -->
  </main> <!-- Akhir main.page -->

  <!-- POPUP ALERT CUSTOM CAFFORA (pengganti alert browser) -->
  <div id="cfAlert" class="cf-alert" hidden> <!-- Wrapper overlay alert custom, awalnya hidden -->
    <div class="cf-alert__backdrop"></div> <!-- Backdrop gelap di belakang box alert -->
    <div class="cf-alert__box"> <!-- Box kartu alert -->
      <div class="cf-alert__title">Caffora</div> <!-- Judul alert (nama brand) -->
      <div class="cf-alert__message" id="cfAlertMessage">
        Pesan <!-- Placeholder teks pesan, diubah via JS -->
      </div>
      <button
        type="button"       <!-- Tombol biasa (bukan submit form) -->
        class="cf-alert__btn"
        id="cfAlertOk"
      >
        Oke <!-- Teks tombol untuk menutup alert -->
      </button>
    </div> <!-- Akhir .cf-alert__box -->
  </div> <!-- Akhir .cf-alert (overlay alert custom) --> 
</head> <!-- Menutup tag <head>, semua meta, title, dan CSS selesai didefinisikan -->
<body>  <!-- Membuka tag <body>, semua konten yang terlihat di halaman ada di dalam bagian ini -->

  <!-- TOP BAR -->
  <div class="topbar">
    <div class="topbar-inner">
      <!-- Link kembali ke halaman cart -->
      <a class="back-link" href="./cart.php">
        <i class="bi bi-arrow-left"></i>
        <span>Kembali</span>
      </a>
    </div>
  </div>

  <main class="page">
    <!-- RINGKASAN ITEM CART TERPILIH (diisi via JS) -->
    <div id="summary"></div>

    <!-- FORM CHECKOUT -->
    <form id="checkoutForm" class="mt-4 pb-4">

      <div class="form-grid">
        <!-- 1. Nama Customer -->
        <div class="field-block field-name mb-3">
          <label class="form-label" for="customer_name">
            Nama Customer
          </label>
          <input
            type="text"
            class="form-control"
            id="customer_name"
            value="<?= htmlspecialchars($nameDefault) ?>" <!-- Isi dari session -->
            required
          >
        </div>

        <!-- 2. Tipe Layanan (Dine In / Take Away) -->
        <div class="field-block field-service mb-3">
          <label class="form-label">
            Tipe Layanan
          </label>
          <!-- Custom select Caffora -->
          <div class="cf-select" data-target="service_type">
            <div class="cf-select__trigger" tabindex="0">
              <span
                class="cf-select__text"
                id="service_type_label"
              >
                Dine In
              </span>
              <i class="bi bi-chevron-down cf-select__icon"></i>
            </div>
            <div class="cf-select__list">
              <div
                class="cf-select__option is-active"
                data-value="dine_in"
              >
                Dine In
              </div>
              <div
                class="cf-select__option"
                data-value="take_away"
              >
                Take Away
              </div>
            </div>
          </div>
          <!-- Nilai asli disimpan di input hidden untuk dikirim ke backend -->
          <input
            type="hidden"
            id="service_type"
            value="dine_in"
          >
        </div>

        <!-- 3. Nomor Meja (hanya untuk Dine In) -->
        <div class="field-block field-table mb-3" id="tableWrap">
          <label class="form-label" for="table_no">
            Nomor Meja
          </label>
          <input
            type="text"
            class="form-control"
            id="table_no"
            placeholder="Misal: 05"
          >
        </div>

        <!-- 4. Metode Pembayaran -->
        <div class="field-block field-payment mb-4">
          <label class="form-label">
            Metode Pembayaran
          </label>
          <!-- Custom select untuk metode pembayaran -->
          <div class="cf-select" data-target="payment_method">
            <div class="cf-select__trigger" tabindex="0">
              <span
                class="cf-select__text"
                id="payment_method_label"
              >
                Cash
              </span>
              <i class="bi bi-chevron-down cf-select__icon"></i>
            </div>
            <div class="cf-select__list">
              <div
                class="cf-select__option is-active"
                data-value="cash"
              >
                Cash
              </div>
              <div
                class="cf-select__option"
                data-value="bank_transfer"
              >
                Bank Transfer
              </div>
              <div
                class="cf-select__option"
                data-value="qris"
              >
                QRIS
              </div>
              <div
                class="cf-select__option"
                data-value="ewallet"
              >
                E-Wallet
              </div>
            </div>
          </div>
          <!-- Hidden input untuk nilai payment_method -->
          <input
            type="hidden"
            id="payment_method"
            value="cash"
          >
        </div>
      </div>

      <!-- TOMBOL SUBMIT -->
      <div class="d-flex justify-content-end mb-3 field-submit">
        <button type="submit" class="btn-primary-cf">
          Konfirmasi Pesanan
        </button>
      </div>
    </form>
  </main>

  <!-- POPUP ALERT CUSTOM CAFFORA (pengganti alert browser) -->
  <div id="cfAlert" class="cf-alert" hidden>
    <div class="cf-alert__backdrop"></div>
    <div class="cf-alert__box">
      <div class="cf-alert__title">Caffora</div>
      <div class="cf-alert__message" id="cfAlertMessage">
        Pesan
      </div>
      <button
        type="button"
        class="cf-alert__btn"
        id="cfAlertOk"
      >
        Oke
      </button>
    </div>
  </div>

  <script>
    (function () {
      // ===================================================
      // KONFIGURASI PATH & KONSTAN DASAR
      // ===================================================

      // Deteksi base app secara dinamis dari URL saat ini
      // Contoh: /caffora-app1/public/customer/checkout.php
      // -> APP_BASE = "/caffora-app1"
      const PUBLIC_SPLIT = '/public/';
      const idxBase      = window.location.pathname.indexOf(PUBLIC_SPLIT);
      const APP_BASE     =
        idxBase > -1 ? window.location.pathname.slice(0, idxBase) : '';

      // Endpoint API untuk membuat pesanan baru
      const API_CREATE  = APP_BASE + '/backend/api/orders.php?action=create';
      // URL halaman riwayat pesanan customer
      const HISTORY_URL = APP_BASE + '/public/customer/history.php';

      // Key untuk localStorage:
      // - KEY_CART: semua item cart
      // - KEY_SELECT: item mana saja yang dipilih di halaman cart
      const KEY_CART   = 'caffora_cart';
      const KEY_SELECT = 'caffora_cart_selected';

      // Tarif pajak 11%
      const TAX_RATE = 0.11;

      // Referensi elemen DOM utama di halaman
      const $summary    = document.getElementById('summary');       // kontainer ringkasan
      const $form       = document.getElementById('checkoutForm');  // form checkout
      const $serviceHid = document.getElementById('service_type');  // input hidden tipe layanan
      const $table      = document.getElementById('table_no');      // input nomor meja
      const $tableWrap  = document.getElementById('tableWrap');     // wrapper field nomor meja

      // ID user yang dikirim dari PHP ke JS
      const USER_ID = <?= json_encode($userId, JSON_UNESCAPED_SLASHES) ?>;

      // ---------------------------------------------------
      // Helper: format angka ke Rupiah (Rp 10.000)
      // ---------------------------------------------------
      const rp = (n) =>
        'Rp ' + Number(n || 0).toLocaleString('id-ID');

      // ---------------------------------------------------
      // Helper: escape HTML sederhana
      // Menghindari XSS ketika pakai innerHTML
      // ---------------------------------------------------
      const esc = (s) =>
        String(s || '').replace(/[&<>"']/g, (m) => ({
          '&': '&amp;',
          '<': '&lt;',
          '>': '&gt;',
          '"': '&quot;',
          "'": '&#39;'
        })[m]);

      // ---------------------------------------------------
      // Ambil cart dari localStorage
      // Struktur: array of { id, name, qty, price, image, ... }
      // ---------------------------------------------------
      const getCart = () => {
        try {
          return JSON.parse(localStorage.getItem(KEY_CART) || '[]');
        } catch {
          return []; // fallback jika parse gagal
        }
      };

      // ---------------------------------------------------
      // Simpan cart ke localStorage
      // ---------------------------------------------------
      const setCart = (items) =>
        localStorage.setItem(KEY_CART, JSON.stringify(items));

      // ---------------------------------------------------
      // Ambil ID item yang dipilih dari localStorage
      // KEY_SELECT berisi array id item yang di-check di cart
      // ---------------------------------------------------
      const getSelectedIds = () => {
        try {
          return JSON.parse(
            localStorage.getItem(KEY_SELECT) || '[]'
          );
        } catch {
          return [];
        }
      };

      // ===================================================
      // RENDER RINGKASAN ITEM YANG DIPILIH DI ATAS FORM
      // ===================================================
      function renderSummary() {
        const cart   = getCart();                     // semua item di cart
        const selIds = getSelectedIds().map(String);  // id item yang terpilih

        // Filter hanya item yang dipilih di halaman cart
        const items = cart.filter((it) =>
          selIds.includes(String(it.id))
        );

        // Jika tidak ada item yang dipilih → tampilkan pesan
        if (!items.length) {
          $summary.innerHTML =
            '<div class="text-muted">' +
              'Tidak ada item yang dipilih. ' +
              'Silakan kembali ke keranjang.' +
            '</div>';
          return;
        }

        let subtotal = 0;   // total sebelum pajak
        let html     = '';  // string HTML untuk item + total

        items.forEach((it) => {
          const qty   = Number(it.qty)   || 0;     // jumlah
          const price = Number(it.price) || 0;     // harga per item
          const img   = it.image || it.image_url || ''; // url gambar
          const lineT = qty * price;              // total per item

          subtotal += lineT;                      // akumulasikan subtotal

          // Templating HTML untuk 1 item
          html += `
            <div class="item-row">
              <div class="left-info">
                <img
                  class="thumb"
                  src="${img}"
                  alt="${esc(it.name || 'Menu')}"
                >
                <div class="name">
                  ${esc(it.name || '')} x ${qty}
                </div>
              </div>
              <div class="line-right">
                ${rp(lineT)}
              </div>
            </div>`;
        });

        // Hitung pajak dan total grand
        const tax   = Math.round(subtotal * TAX_RATE);
        const total = subtotal + tax;

        // Tambahkan blok total di bawah daftar item
        html += `
          <div class="tot-block">
            <div class="tot-line">
              <span>Subtotal</span>
              <span>${rp(subtotal)}</span>
            </div>
            <div class="tot-line">
              <span>Pajak 11%</span>
              <span>${rp(tax)}</span>
            </div>
            <div class="tot-grand">
              <span>Total</span>
              <span>${rp(total)}</span>
            </div>
          </div>`;

        // Sisipkan HTML ke #summary
        $summary.innerHTML        = html;
        // Simpan subtotal/tax/total ke dataset untuk dipakai saat submit
        $summary.dataset.subtotal = subtotal;
        $summary.dataset.tax      = tax;
        $summary.dataset.total    = total;
      }

      // Panggil sekali di awal saat halaman load
      renderSummary();

      // ===================================================
      // INISIALISASI CUSTOM SELECT (TIPE LAYANAN & PEMBAYARAN)
      // ===================================================
      function initCfSelect() {
        const selects  = document.querySelectorAll('.cf-select');

        // Fungsi untuk menutup semua dropdown
        const closeAll = () => {
          selects.forEach((s) => s.classList.remove('is-open'));
        };

        selects.forEach((sel) => {
          const targetId = sel.dataset.target;                     // id input hidden target
          const trigger  = sel.querySelector('.cf-select__trigger');
          const list     = sel.querySelector('.cf-select__list');
          const label    = sel.querySelector('.cf-select__text');

          // Klik trigger → toggle dropdown
          trigger.addEventListener('click', (e) => {
            e.stopPropagation();                                   // jangan propagate ke document
            const isOpen = sel.classList.contains('is-open');
            closeAll();                                            // tutup semua lain dulu
            if (!isOpen) {
              sel.classList.add('is-open');                        // buka dropdown ini
            }
          });

          // Klik salah satu option pada list
          list
            .querySelectorAll('.cf-select__option')
            .forEach((opt) => {
              opt.addEventListener('click', () => {
                const val  = opt.dataset.value;          // nilai yang akan diset
                const text = opt.textContent.trim();     // label untuk ditampilkan

                // Update label yang terlihat
                label.textContent = text;

                // Set nilai ke input hidden yang sesuai
                const hid = document.getElementById(targetId);
                if (hid) {
                  hid.value = val;
                }

                // Update state "is-active" di semua opsi
                list
                  .querySelectorAll('.cf-select__option')
                  .forEach((o) => o.classList.remove('is-active'));
                opt.classList.add('is-active');

                // Khusus untuk type layanan, kita perlu sync field nomor meja
                if (targetId === 'service_type') {
                  syncTableField(val);
                }

                // Tutup dropdown setelah memilih
                sel.classList.remove('is-open');
              });
            });
        });

        // Klik di luar dropdown (document) → tutup semua
        document.addEventListener('click', () => closeAll());
      }

      // Jalankan inisialisasi custom select saat load
      initCfSelect();

      // ===================================================
      // TAMPIL / SEMBUNYIKAN FIELD NOMOR MEJA
      // tergantung tipe layanan (dine_in / take_away)
      // ===================================================
      function syncTableField(valNow) {
        const v = valNow ?? $serviceHid.value; // pakai argumen valNow kalau ada

        // Jika dine_in: field nomor meja muncul & aktif
        if (v === 'dine_in') {
          $tableWrap.style.display = '';               // tampilkan wrapper
          $table.removeAttribute('disabled');          // input aktif
        }
        // Jika selain itu (take_away): sembunyikan
        else {
          $tableWrap.style.display = 'none';           // sembunyikan wrapper
          $table.value = '';                           // kosongkan nilai
          $table.setAttribute('disabled', 'disabled'); // disable input
        }
      }

      // Set initial state nomor meja (berdasarkan default service_type)
      syncTableField();

      // ===================================================
      // SHOW / HIDE LOADING DI TOMBOL KONFIRMASI
      // Mengganti teks tombol dengan spinner "Memproses..."
      // ===================================================
      function showBtnLoading(btn, on) {
        if (on) {
          btn.disabled    = true;             // disable tombol
          btn.dataset.text = btn.innerHTML;   // simpan isi HTML sebelumnya
          btn.innerHTML   =
            '<span style="' +
              'display:inline-block;' +
              'width:14px;height:14px;' +
              'border:2px solid #fff;' +
              'border-right-color:transparent;' +
              'border-radius:50%;' +
              'margin-right:8px;' +
              'vertical-align:middle;' +
              'animation:spin .7s linear infinite;' +
            '"></span>Memproses...';
        } else {
          btn.disabled = false;
          btn.innerHTML =
            btn.dataset.text || 'Konfirmasi Pesanan'; // kembalikan teks sebelumnya
        }
      }

      // ===================================================
      // POPUP ALERT CUSTOM (PENGGANTI alert() BAWAAN BROWSER)
      // ===================================================
      function showCfAlert(message, onClose) {
        const wrap  = document.getElementById('cfAlert');        // wrapper overlay
        const msgEl = document.getElementById('cfAlertMessage'); // elemen teks pesan
        const okBtn = document.getElementById('cfAlertOk');      // tombol Oke

        // Jika elemen tidak tersedia, fallback ke alert bawaan
        if (!wrap || !msgEl || !okBtn) {
          alert(message);
          if (typeof onClose === 'function') {
            onClose();
          }
          return;
        }

        msgEl.textContent = message; // set pesan
        wrap.hidden       = false;   // tampilkan overlay

        function close() {
          wrap.hidden = true;        // sembunyikan overlay
          okBtn.removeEventListener('click', handle);
          if (typeof onClose === 'function') {
            onClose();               // callback optional setelah ditutup
          }
        }

        function handle() {
          close();
        }

        // Klik tombol Oke → tutup alert (once sekali)
        okBtn.addEventListener('click', handle, { once: true });

        // Klik backdrop (area gelap) juga menutup alert
        const backdrop = wrap.querySelector('.cf-alert__backdrop');
        if (backdrop) {
          backdrop.onclick = close;
        }
      }

      // ===================================================
      // SUBMIT FORM CHECKOUT
      // - Validasi item terpilih
      // - Susun payload
      // - Kirim data ke API backend
      // - Hapus item yang sudah dipesan dari localStorage
      // ===================================================
      $form.addEventListener('submit', async (e) => {
        e.preventDefault(); // cegah form reload default

        const btn = $form.querySelector('.btn-primary-cf');
        showBtnLoading(btn, true); // tampilkan loading di tombol

        // Ambil item yang dipilih dari cart
        const cart     = getCart();
        const selIds   = getSelectedIds().map(String);
        const itemsSel = cart.filter((it) =>
          selIds.includes(String(it.id))
        );

        // Jika tidak ada item terpilih, tampilkan alert
        if (!itemsSel.length) {
          showCfAlert('Tidak ada item yang dipilih.');
          showBtnLoading(btn, false);
          return;
        }

        // Ambil subtotal, pajak, dan total yang sebelumnya disimpan di dataset summary
        const subtotal = Number($summary.dataset.subtotal || 0);
        const tax      = Number($summary.dataset.tax || 0);
        const grand    = Number($summary.dataset.total || 0);

        // Siapkan payload JSON yang akan dikirim ke backend
        const payload = {
          user_id:        USER_ID,
          customer_name:  document
            .getElementById('customer_name')
            .value.trim(),
          service_type:   document
            .getElementById('service_type')
            .value,
          table_no:       ($table.value || '').trim() || null,
          payment_method: document
            .getElementById('payment_method')
            .value,
          payment_status: 'pending',        // status awal pembayaran
          subtotal:       subtotal,
          tax_amount:     tax,
          grand_total:    grand,
          items:          itemsSel.map((it) => ({
            menu_id: Number(it.id),         // id menu
            qty:     Number(it.qty)  || 0,  // qty
            price:   Number(it.price)|| 0   // harga satuan
          }))
        };

        try {
          // Kirim ke endpoint create order di backend
          const res = await fetch(API_CREATE, {
            method:      'POST',
            headers:     { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body:        JSON.stringify(payload)
          });

          // Coba parse JSON; jika gagal, baca sebagai text untuk debugging
          let js;
          try {
            js = await res.json();
          } catch {
            const txt = await res.text();
            throw new Error(
              txt ? txt.substring(0, 300) : 'Invalid JSON'
            );
          }

          // Jika HTTP status bukan 2xx atau response ok=false → anggap error
          if (!res.ok || !js.ok) {
            throw new Error(js.error || ('HTTP ' + res.status));
          }

          // Hapus item yang sudah dipesan dari cart localStorage
          const remaining = cart.filter((it) =>
            !selIds.includes(String(it.id))
          );
          setCart(remaining);            // simpan cart baru (sisa item)
          localStorage.removeItem(KEY_SELECT); // hapus pilihan item

          const inv = js.invoice_no || '';     // nomor invoice (jika ada di response)

          // Tampilkan pesan sukses lalu redirect ke halaman riwayat
          showCfAlert(
            'Pesanan berhasil dibuat! Invoice: ' + inv,
            function () {
              window.location.href = HISTORY_URL;
            }
          );
        } catch (err) {
          // Tangani error: tampilkan pesan ke user
          showCfAlert(
            'Checkout gagal: ' + (err?.message || err)
          );
        } finally {
          // Apapun hasilnya, matikan loading tombol
          showBtnLoading(btn, false);
        }
      });
    })();
  </script>
</body>
</html>
