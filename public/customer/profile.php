<?php

  // Lokasi file: public/customer/profile.php
  // File ini menangani halaman profil customer

  // Aktifkan strict typing untuk keamanan tipe data
  declare(strict_types=1);

  // Import guard autentikasi
  require_once __DIR__ . '/../../backend/auth_guard.php';

  // Batasi akses: hanya user dengan role "customer"
  require_login(['customer']);

  // ============================================================
  // BASE URL DINAMIS (agar tidak hardcode)
  // Contoh:
  //   /public/customer/profile.php → BASE = ""
  //   /caffora-app1/public/...     → BASE = "/caffora-app1"
  // ============================================================

  // Ambil path script yang sedang berjalan
  $script = $_SERVER['SCRIPT_NAME'] ?? '';

  // Cari posisi "/public/" dalam path
  $pos = strpos($script, '/public/');

  // Potong string sebelum "/public/" untuk mendapatkan BASE
  $BASE = $pos !== false
          ? substr($script, 0, $pos)
          : '';

  // ============================================================
  // AMBIL DATA USER DARI SESSION LOGIN
  // ============================================================

  // ID user (integer)
  $userId = (int)($_SESSION['user_id'] ?? 0);

  // Nama user (fallback: "Customer")
  $name = $_SESSION['user_name'] ?? 'Customer';

  // Email user
  $email = $_SESSION['user_email'] ?? '';

  // Nomor telepon user
  $phone = $_SESSION['user_phone'] ?? '';

  // Avatar user (jika ada)
  $avatar = $_SESSION['user_avatar'] ?? '';

?>

<!-- Deklarasi tipe dokumen HTML5 -->
<!DOCTYPE html>

<!-- Bahasa dokumen Indonesia -->
<html lang="id">
<head>
  <!-- Set encoding karakter ke UTF-8 -->
  <meta charset="utf-8" />

  <!-- Responsif di semua perangkat -->
  <meta
    name="viewport"
    content="width=device-width,initial-scale=1"
  />

  <!-- Judul halaman yang tampil di tab browser -->
  <title>Profil</title>

  <!-- Load Bootstrap Icons dari CDN -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    rel="stylesheet"
  />

  <!-- Load font Poppins dari Google Fonts -->
  <link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap"
    rel="stylesheet"
  />

  <style>                               /* Gaya CSS untuk halaman profil & sheet */
    :root{                             /* Root: definisi variabel tema global */
      --gold:#ffd54f;                  /* Warna emas utama (aksen tombol) */
      --ink:#222;                      /* Warna teks utama gelap */
      --brown:#4b3f36;                 /* Warna coklat brand Caffora */
      --bg:#fafbfc;                    /* Warna latar belakang lembut */
      --line:#eceff3;                  /* Warna garis pemisah halus */
    }                                  /* Tutup :root */

    /* global, sama seperti checkout */
    * {                                /* Selector global untuk semua elemen */
      font-family: Poppins, system-ui, -apple-system, "Segoe UI",
                   Roboto, Arial, sans-serif;               /* Font utama + fallback */
      box-sizing: border-box;         /* Hitung width/height termasuk padding+border */
    }

    body {                             /* Aturan dasar body halaman */
      background: var(--bg);           /* Gunakan warna latar dari variabel --bg */
      color: var(--ink);               /* Warna teks utama dari variabel --ink */
      margin: 0;                       /* Hilangkan margin default browser */
    }

    /* ====== TOPBAR (copy dari checkout) ====== */
    .topbar{                           /* Bar atas yang menempel di bagian atas */
      background:#fff;                 /* Latar topbar putih bersih */
      border-bottom:1px solid rgba(0,0,0,.05);  /* Garis bawah tipis */
      position:sticky;                 /* Tetap di atas saat di-scroll */
      top:0;                           /* Menempel persis di tepi atas */
      z-index:20;                      /* Di atas konten lainnya */
    }

    .topbar-inner{                     /* Isi konten di dalam topbar */
      max-width:1200px;                /* Batas lebar maksimum konten */
      margin:0 auto;                   /* Tengah-kan secara horizontal */
      padding:12px 24px;               /* Ruang dalam atas/bawah & kiri/kanan */
      min-height:52px;                 /* Tinggi minimum topbar */
      display:flex;                    /* Gunakan flexbox */
      align-items:center;              /* Vertikal: rata tengah */
      gap:10px;                        /* Jarak antar elemen di topbar */
    }

    .back-link{                        /* Tombol/tautan kembali di topbar */
      display:inline-flex;             /* Inline-flex agar sejajar dengan teks lain */
      align-items:center;              /* Pusatkan ikon & teks secara vertikal */
      gap:10px;                        /* Jarak antara ikon dan teks */
      color:var(--brown);              /* Warna teks coklat brand */
      text-decoration:none;            /* Hilangkan garis bawah link */
      border:0;                        /* Tanpa border (jika berupa button) */
      background:transparent;          /* Latar transparan */
      padding:0;                       /* Hilangkan padding default */
    }

    /* 👉 Paksa teks “Kembali” pakai system-ui (Inter) */
    .back-link span{                   /* Span teks di dalam tombol kembali */
      font-family: system-ui, -apple-system, "Segoe UI",
                   Roboto, Arial, sans-serif !important;    /* Pakai system font */
      font-size:1rem;                  /* 16px */
      font-weight:600;                 /* Tebal semi-bold */
      line-height:1.3;                 /* Tinggi baris 1.3 */
    }

    /* Ikon panah 18x18 sama seperti checkout */
    .back-link .bi{                    /* Ikon panah bootstrap di back-link */
      width:18px;                      /* Lebar area ikon 18px */
      height:18px;                     /* Tinggi area ikon 18px */
      display:inline-flex;             /* Inline-flex untuk centering */
      align-items:center;              /* Vertikal: pusat */
      justify-content:center;          /* Horizontal: pusat */
      font-size:18px !important;       /* Ukuran ikon 18px */
      line-height:18px !important;     /* Tinggi baris 18px */
    }

    /* ====== KONTEN ====== */
    main {                             /* Kontainer utama isi halaman */
      max-width: 1200px;               /* Batas lebar maksimum */
      margin: 0 auto;                  /* Tengah-kan konten */
      padding: 14px 18px 50px;         /* Padding atas, samping, dan bawah */
    }

    /* ====== HEADER PROFIL ====== */
    .profile-head {                    /* Header profil (avatar + nama + tombol) */
      display: flex;                   /* Gunakan flexbox */
      align-items: center;             /* Vertikal: rata tengah */
      justify-content: space-between;  /* Kiri dan kanan diberi jarak maksimum */
      gap: 18px 26px;                  /* Gap baris dan kolom */
      margin: 4px 0 10px;              /* Jarak atas dan bawah header */
    }

    .profile-left {                    /* Bagian kiri: avatar + info user */
      display: flex;                   /* Susun horizontal */
      align-items: center;             /* Pusatkan vertikal */
      gap: 18px;                       /* Jarak avatar dan teks */
      min-width: 0;                    /* Izinkan menyusut (untuk ellipsis) */
    }

    .avatar {                          /* Lingkaran foto profil */
      width: 64px;                     /* Lebar avatar 64px */
      height: 64px;                    /* Tinggi avatar 64px */
      border-radius: 50%;              /* Bentuk lingkaran penuh */
      background: #eee center / cover no-repeat;  /* Latar abu + gambar cover */
      flex-shrink: 0;                  /* Jangan mengecil saat ruang sempit */
    }

    .who {                             /* Wrapper nama dan email user */
      min-width: 0;                    /* Diperlukan untuk teks ellipsis */
    }

    .who .name {                       /* Teks nama user */
      font-weight: 600;                /* Semi-bold */
      font-size: 1.02rem;              /* Sedikit lebih besar dari 16px */
      margin-bottom: 2px;              /* Jarak ke teks email */
    }

    .who .mail {                       /* Teks email user */
      color: var(--muted);             /* Warna abu (muted) */
      font-size: 0.7rem;               /* Ukuran kecil */
      word-break: break-word;          /* Izinkan patah kata jika kepanjangan */
    }

    /* ====== LIST ====== */
    .profile-box {                     /* Container daftar item profil */
      background: transparent;         /* Tanpa card solid */
      border: 0;                       /* Tanpa border */
      border-radius: 0;                /* Sudut tidak dibulatkan */
      max-width: 100%;                 /* Lebar penuh */
      margin-top: 4px;                 /* Jarak dari header profil */
    }

    .row {                             /* Satu baris item pengaturan */
      display: flex;                   /* Susun label, value, ikon secara horizontal */
      align-items: center;             /* Vertikal: rata tengah */
      gap: 12px;                       /* Jarak antar elemen */
      padding: 16px 0;                 /* Padding atas-bawah 16px */
      border-bottom: 1px solid #f5f5f5;/* Garis pemisah antar baris */
    }

    .row:last-child {                  /* Baris terakhir list */
      border-bottom: none;             /* Hilangkan garis pemisah */
    }

    .label {                           /* Label kiri item */
      font-weight: 500;                /* Tebal medium */
      font-size: 0.95rem;              /* Sedikit lebih kecil dari 16px */
    }

    .value {                           /* Nilai/isi di sisi kanan */
      margin-left: auto;               /* Dorong ke paling kanan */
      margin-right: 4px;               /* Jarak dengan ikon chevron */
      color: var(--muted);             /* Warna teks redup */
      font-size: 0.9rem;               /* Ukuran font nilai */
      overflow: hidden;                /* Sembunyikan teks berlebih */
      text-overflow: ellipsis;         /* Tambah "..." jika kepanjangan */
      white-space: nowrap;             /* Jangan pindah baris */
      max-width: 55%;                  /* Batas lebar maksimum */
      text-align: right;               /* Rata kanan */
    }

    .chev-btn {                        /* Tombol ikon chevron kanan */
      background: none;                /* Tanpa background */
      border: none;                    /* Tanpa border */
      color: #9aa0a6;                  /* Warna ikon abu kebiruan */
      font-size: 24px;                 /* Ukuran ikon 18px */
      line-height: 1;                  /* Line-height rapat */
      cursor: pointer;                 /* Tunjukkan bisa diklik */
      padding: 4px;                    /* Area klik sedikit lebih luas */
      margin-left: 2px;                /* Jarak dengan teks value */
    }

    /* ====== BUTTON – MATCH CART/CHECKOUT ====== */
    .btn {                             /* Gaya dasar semua tombol */
      border: none;                    /* Tanpa border */
      border-radius: 14px;             /* Sudut membulat 14px */
      cursor: pointer;                 /* Kursor pointer saat hover */

      padding: 10px 18px;              /* Ruang dalam tombol */
      min-height: 41px;                /* Tinggi minimal tombol */

      display: inline-flex;            /* Inline-flex untuk align isi */
      align-items: center;             /* Vertikal: rata tengah */
      justify-content: center;         /* Horizontal: rata tengah */
      gap: 8px;                        /* Jarak ikon dan teks */

      font-family: Arial, Helvetica, sans-serif !important;  /* Paksa font Arial */
      font-size: 14.08px !important;   /* Ukuran font spesifik */
      font-weight: 600;                /* Semi-bold */
      line-height: 1.2;                /* Tinggi baris tombol */

      white-space: nowrap;             /* Teks tidak turun baris */
      box-shadow: none;               /* Tanpa bayangan */
    }

    /* ekstra jaga-jaga untuk tombol di bottom sheet */
    .actions .btn {                    /* Tombol di area actions sheet */
      font-family: Arial, Helvetica, sans-serif !important;  /* Konsisten font */
      font-size: 14.08px !important;  /* Konsisten ukuran */
      padding: 10px 18px !important;  /* Konsisten padding */
      min-height: 41px !important;    /* Konsisten tinggi */
      border-radius: 14px !important; /* Konsisten radius */
    }

    .btn.primary {                     /* Tombol utama (primary) */
      background-color: var(--gold);   /* Latar emas */
      color: var(--brown) !important;  /* Teks coklat kontras */
    }

    .btn.secondary {                   /* Tombol sekunder */
      background-color: #f3f4f6;       /* Latar abu muda */
      color: #333;                     /* Teks abu gelap */
    }

    .btn.primary:hover {               /* Hover tombol primary */
      filter: brightness(1.05);        /* Sedikit lebih cerah */
    }

    .btn.secondary:hover {             /* Hover tombol secondary */
      background: #e9eaec;             /* Sedikit lebih gelap */
    }

    /* ====== BOTTOM SHEET ====== */
    .sheet[hidden] {                   /* Sheet saat disembunyikan */
      display: none;                   /* Jangan tampilkan */
    }

    .sheet {                           /* Overlay bottom sheet */
      position: fixed;                 /* Posisi relatif ke viewport */
      inset: 0;                        /* Tutup seluruh layar */
      display: flex;                   /* Flex untuk penempatan panel */
      align-items: flex-end;           /* Panel menempel bawah */
      background: rgba(0, 0, 0, 0.35); /* Overlay gelap transparan */
      z-index: 50;                     /* Di depan konten lain */
    }

    .panel {                           /* Panel isi bottom sheet */
      width: 100%;                     /* Lebar penuh di mobile */
      background: #fff;                /* Latar putih */
      border-top-left-radius: 16px;    /* Sudut kiri atas membulat */
      border-top-right-radius: 16px;   /* Sudut kanan atas membulat */
      padding: 18px;                   /* Ruang dalam panel */
      max-height: 75vh;                /* Tinggi maksimum 75% viewport */
      overflow: auto;                  /* Scroll jika konten tinggi */
    }

    .panel h3 {                        /* Judul dalam panel */
      margin: 0 0 12px;                /* Hilangkan margin atas, beri bawah */
      font-size: 1.05rem;              /* Sedikit lebih besar */
      font-weight: 600;                /* Semi-bold */
    }

    .field {                           /* Satu blok field (label + input) */
      display: flex;                   /* Flex container */
      flex-direction: column;          /* Susun vertikal */
      margin-bottom: 12px;             /* Jarak antar field */
    }

    label {                            /* Label input */
      font-size: 0.9rem;               /* Ukuran kecil */
      color: #555;                     /* Warna abu gelap */
      margin-bottom: 6px;              /* Jarak ke input */
    }

    input.text {                       /* Input teks umum */
      padding: 11px;                   /* Ruang dalam input */
      border: 1px solid #ddd;          /* Border abu terang */
      border-radius: 14px;              /* Sudut membulat */
      font: inherit;                   /* Ikuti font parent */
      width: 100%;                     /* Lebar penuh */
      outline: none;                   /* Tanpa outline bawaan */
      box-shadow: none;                /* Tanpa shadow */
    }

    input.text:focus,
    input.text:focus-visible {         /* State fokus input */
      outline: none !important;        /* Hilangkan outline */
      box-shadow: none !important;     /* Hilangkan shadow */
      border-color: #ddd !important;   /* Border tetap abu */
    }

    .actions {                         /* Container tombol aksi sheet */
      display: flex;                   /* Flex untuk deret tombol */
      justify-content: flex-end;       /* Rata kanan */
      gap: 10px;                       /* Jarak antar tombol */
      margin-top: 12px;                /* Jarak dari field terakhir */
    }

    /* responsive */
    @media (max-width: 700px) {        /* Aturan untuk layar kecil */
      .topbar-inner,
      main {                           /* Topbar & konten di mobile */
        max-width: 100%;               /* Lebar penuh */
        padding: 10px 14px;            /* Padding lebih kecil */
      }

      .profile-head {                  /* Header profil di mobile */
        flex-wrap: wrap;               /* Izinkan turun baris */
        margin-bottom: 6px;            /* Margin bawah lebih kecil */
      }

      .row {                           /* Baris list di mobile */
        padding: 14px 0;               /* Padding sedikit lebih kecil */
      }

      .value {                         /* Nilai kanan di mobile */
        max-width: 50%;                /* Batasi lebar agar tidak overflow */
      }
    }                                  /* TUTUP @media */

    /* ====== CUSTOM ALERT CAFFORA ====== */
    .cf-alert[hidden] {                                  /* State ketika elemen alert diberi atribut hidden */
      display: none;                                     /* Sembunyikan elemen sepenuhnya */
    }

    .cf-alert {                                          /* Wrapper overlay untuk popup alert custom */
      position:       fixed;                             /* Tetap di posisi layar saat scroll */
      inset:          0;                                 /* Menutupi seluruh viewport (top/right/bottom/left=0) */
      z-index:        999;                               /* Di atas hampir semua elemen lain */
      display:        flex;                              /* Flex layout untuk center konten */
      align-items:    center;                            /* Vertikal center box alert */
      justify-content:center;                            /* Horizontal center box alert */
      pointer-events: none;                              /* Default overlay tidak menerima klik */
    }

    .cf-alert__backdrop {                                /* Latar belakang gelap di belakang box alert */
      position:   absolute;                              /* Mengisi seluruh area .cf-alert */
      inset:      0;                                     /* Full layar pada wrapper */
      background: rgba(0, 0, 0, 0.45);                   /* Warna hitam transparan 45% */
    }

    .cf-alert__box {                                     /* Box utama alert yang berisi teks dan tombol */
      position:       relative;                          /* Di atas backdrop */
      pointer-events: auto;                              /* Box bisa diklik (override pointer-events parent) */
      max-width:      320px;                             /* Lebar maksimum box 320px */
      width:          90%;                               /* Lebar responsif 90% dari viewport */
      background:     #fffdf8;                           /* Background krem lembut */
      border-radius:  18px;                              /* Sudut box membulat 18px */
      padding:        16px 18px 14px;                    /* Padding dalam box: atas/kanan/bawah */
      box-shadow:     0 18px 40px rgba(0,0,0,.18);       /* Bayangan cukup tebal di bawah box */
      display:        flex;                              /* Flex layout vertikal */
      flex-direction: column;                            /* Elemen di dalam box disusun vertikal */
      gap:            10px;                              /* Jarak antar elemen (title, message, button) */
      font-family:    Poppins, system-ui, -apple-system,
                      "Segoe UI", Roboto, Arial, sans-serif;
    }

    .cf-alert__title {                                   /* Teks judul di dalam alert */
      font-weight: 600;                                  /* Teks judul cukup tebal */
      font-size:   0.92rem;                              /* Ukuran teks judul 0.92rem */
      color:       var(--brown);                         /* Warna teks judul coklat brand */
      margin-bottom:2px;                                 /* Jarak kecil di bawah judul */
    }

    .cf-alert__message {                                 /* Teks pesan utama alert */
      font-size: .9rem;                                  /* Ukuran teks pesan 0.9rem */
      color:      #413731;                               /* Warna teks coklat gelap */
    }

    .cf-alert__btn {                                     /* Tombol “Oke” di bagian bawah alert */
      align-self:    flex-end;                           /* Posisi tombol di sisi kanan bawah box */
      margin-top:    6px;                                /* Jarak atas tombol dari pesan */
      border:        0;                                  /* Tanpa border */
      border-radius: 999px;                              /* Tombol berbentuk pill sangat bulat */
      padding:       8px 18px;                           /* Padding vertical 8px horizontal 18px */
      background:    var(--gold);                        /* Background tombol kuning emas */
      color:         var(--brown);                       /* Warna teks coklat */
      font-weight:   600;                                /* Teks tombol cukup tebal */
      font-size:     0.9rem;                             /* Ukuran teks tombol 0.9rem */
      cursor:        pointer;                            /* Tanda bahwa tombol dapat diklik */
    }

    @keyframes spin {                                    /* Definisi animasi CSS bernama spin */
      from {
        transform: rotate(0);                            /* Frame awal: rotasi 0 derajat */
      }
      to {
        transform: rotate(360deg);                       /* Frame akhir: rotasi penuh 360 derajat */
      }
    }
  </style>

  <!-- PROJECT_BASE auto-detect untuk JS -->
  <script>
    // Fungsi IIFE agar langsung dieksekusi
    (function () {
      // Konstanta penanda lokasi folder "/public/"
      const SPLIT = "/public/";

      // Cari posisi "/public/" dalam pathname URL
      const i = location.pathname.indexOf(SPLIT);

      // Tentukan PROJECT_BASE:
      // Jika "/public/" ditemukan → ambil substring sebelum itu
      // Jika tidak ditemukan → string kosong
      window.PROJECT_BASE = i > -1
        ? location.pathname.slice(0, i)
        : "";
      // Eksekusi fungsi IIFE selesai
    })();
  </script>
</head>

<body>
  <!-- ============================
       TOPBAR (navigasi kembali)
       ============================ -->
  <div class="topbar">
    <div class="topbar-inner">
      <!-- Tombol kembali -->
      <a class="back-link" id="goBack">
        <i class="bi bi-arrow-left"></i>
        <span>Kembali</span>
      </a>
    </div>
  </div>

  <main>
    <?php
      $raw = $avatar ?: '/public/assets/img/avatar-placeholder.png';
      // jika path avatar sudah absolut (http) biarkan,
      // kalau relatif /public/... tambahkan BASE
      $avatarUrl = preg_match('~^https?://~i', (string) $raw)
        ? $raw
        : ($BASE . $raw);
    ?>

    <!-- Section header profil -->
    <section class="profile-head">
      <!-- Wrapper kiri: avatar + info -->
      <div class="profile-left">
        <!-- Avatar user -->
        <div
          id="avatar"
          class="avatar"
          style="background-image:url('<?= htmlspecialchars($avatarUrl, ENT_QUOTES) ?>')"
        ></div>

        <!-- Wrapper nama + email -->
        <div class="who">
          <!-- Nama user -->
          <div class="name" id="whoName">
            <?= htmlspecialchars($name) ?>
          </div>

          <!-- Email user -->
          <div class="mail">
            <?= htmlspecialchars($email) ?>
          </div>
        </div>
      </div>

      <!-- Tombol upload foto -->
      <button class="btn primary" id="btnPhoto">
        Upload
      </button>

      <!-- Input file avatar -->
      <input
        type="file"
        id="fileAvatar"
        accept="image/png,image/jpeg"
        hidden
      />
    </section>

    <!-- Section daftar informasi akun -->
    <section class="profile-box" aria-label="Akun">
      <!-- Row: nama -->
      <div class="row">
        <span class="label">Nama</span>
        <span class="value" id="valName">
          <?= htmlspecialchars($name) ?>
        </span>
        <button class="chev-btn" data-open="sheetName">›</button>
      </div>

      <!-- Row: nomor HP -->
      <div class="row">
        <span class="label">No. HP</span>
        <span class="value" id="valPhone">
          <?= $phone ? htmlspecialchars($phone) : 'Belum diisi' ?>
        </span>
        <button class="chev-btn" data-open="sheetPhone">›</button>
      </div>

      <!-- Row: ganti password -->
      <div class="row">
        <span class="label">Ganti Password</span>
        <span class="value">Keamanan akun</span>
        <button class="chev-btn" data-open="sheetPassword">›</button>
      </div>
    </section>
  </main>

  <!-- Sheet ubah nama -->
  <div class="sheet" id="sheetName" hidden>
    <!-- Panel form -->
    <div class="panel">
      <h3>Ubah Nama</h3>

      <!-- Field input nama -->
      <div class="field">
        <label for="name">Nama lengkap</label>
        <input
          id="name"
          class="text"
          type="text"
          value="<?= htmlspecialchars($name) ?>"
        />
      </div>

      <!-- Tombol aksi -->
      <div class="actions">
        <button class="btn secondary" data-close>
          Batalkan
        </button>
        <button class="btn primary" id="saveName">
          Simpan
        </button>
      </div>
    </div>
  </div>

  <!-- Sheet ubah nomor HP -->
  <div class="sheet" id="sheetPhone" hidden>
    <!-- Panel form -->
    <div class="panel">
      <h3>Ubah No. HP</h3>

      <!-- Field input nomor -->
      <div class="field">
        <label for="phone">Nomor HP</label>
        <input
          id="phone"
          class="text"
          type="tel"
          placeholder="08xxxxxxxxxx"
          value="<?= htmlspecialchars($phone) ?>"
          autocomplete="tel"
        />
      </div>

      <!-- Tombol aksi -->
      <div class="actions">
        <button class="btn secondary" data-close>
          Batalkan
        </button>
        <button class="btn primary" id="savePhone">
          Simpan
        </button>
      </div>
    </div>
  </div>

  <!-- Sheet ganti password -->
  <div class="sheet" id="sheetPassword" hidden>
    <!-- Panel form -->
    <div class="panel">
      <h3>Ganti Password</h3>

      <!-- Password lama -->
      <div class="field">
        <label for="oldpass">Password Lama</label>
        <input
          id="oldpass"
          class="text"
          type="password"
          placeholder="••••••"
        />
      </div>

      <!-- Password baru -->
      <div class="field">
        <label for="newpass">Password Baru</label>
        <input
          id="newpass"
          class="text"
          type="password"
          placeholder="Minimal 6 karakter"
        />
      </div>

      <!-- Konfirmasi password -->
      <div class="field">
        <label for="confpass">Konfirmasi Password</label>
        <input
          id="confpass"
          class="text"
          type="password"
          placeholder="Ulangi password baru"
        />
      </div>

      <!-- Tombol aksi -->
      <div class="actions">
        <button class="btn secondary" data-close>
          Batalkan
        </button>
        <button class="btn primary" id="savePass">
          Perbarui
        </button>
      </div>
    </div>
  </div>

  <!-- POPUP ALERT CUSTOM CAFFORA -->
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
    // ===================================================
    // POPUP ALERT CUSTOM (PENGGANTI alert() BAWAAN BROWSER)
    // ===================================================
    function showCfAlert(message, onClose) {
      const wrap  = document.getElementById('cfAlert');
      const msgEl = document.getElementById('cfAlertMessage');
      const okBtn = document.getElementById('cfAlertOk');

      // Fallback ke alert biasa kalau komponen tidak ada
      if (!wrap || !msgEl || !okBtn) {
        alert(message);
        if (typeof onClose === 'function') {
          onClose();
        }
        return;
      }

      msgEl.textContent = message;
      wrap.hidden       = false;

      function close() {
        wrap.hidden = true;
        okBtn.removeEventListener('click', handle);
        if (typeof onClose === 'function') {
          onClose();
        }
      }

      function handle() {
        close();
      }

      okBtn.addEventListener('click', handle, { once: true });

      const backdrop = wrap.querySelector('.cf-alert__backdrop');
      if (backdrop) {
        backdrop.onclick = close;
      }
    }

    // API endpoint dinamis (tanpa hardcode base ke domain)
    const API =
      (window.PROJECT_BASE || "") +
      "/backend/api/profile_update.php";

    // Tombol Kembali: back cerdas + fallback
    // Ambil elemen dengan id "goBack" lalu pasang event listener klik
    document
      .getElementById("goBack")
      .addEventListener("click", function (e) {
        // Jangan jalankan perilaku default link (navigasi langsung)
        e.preventDefault();

        // Coba lakukan logika back cerdas di dalam blok try
        try {
          // Ambil document.referrer (halaman sebelumnya) atau string kosong
          const ref  = document.referrer || "";
          // Cek apakah referrer masih 1 origin dengan halaman sekarang
          const same =
            ref &&
            new URL(ref, location.href).origin ===
              location.origin;

          // Jika masih 1 origin dan history punya lebih dari 1 entri
          if (same && history.length > 1) {
            // Kembali ke halaman sebelumnya
            history.back();
            // Hentikan eksekusi function setelah back
            return;
          }
        // Jika parsing URL atau akses referrer gagal, abaikan error
        } catch (_) {}

        // Fallback: paksa redirect ke halaman index customer
        window.location.href =
          (window.PROJECT_BASE || "") +
          "/public/customer/index.php";
      });

    // Open/close sheets
    // Cari semua elemen yang punya atribut data-open
    document
      .querySelectorAll("[data-open]")
      .forEach((btn) => {
        // Untuk tiap tombol, saat diklik buka sheet sesuai id di data-open
        btn.onclick = () => {
          // Ambil nilai data-open (id sheet)
          const id = btn.dataset.open;
          // Cari elemen sheet berdasarkan id
          const el = document.getElementById(id);
          // Jika elemen ada, tampilkan dengan menyetel hidden = false
          if (el) el.hidden = false;
        };
      });

    // Cari semua elemen dengan class "sheet"
    document
      .querySelectorAll(".sheet")
      .forEach((s) => {
        // Klik di area overlay luar sheet untuk menutup sheet
        s.addEventListener("click", (e) => {
          // Jika target klik adalah elemen sheet (bukan panel di dalam)
          if (e.target === s) {
            // Sembunyikan sheet dengan mengatur hidden = true
            s.hidden = true;
          }
        });

        // Cari semua elemen di dalam sheet yang punya atribut data-close
        s.querySelectorAll("[data-close]").forEach((c) => {
          // Saat tombol close diklik, sembunyikan sheet
          c.onclick = () => {
            s.hidden = true;
          };
        });
      });

    // Parser JSON aman (hindari "Respon tidak valid dari server.")
    // Fungsi async untuk parse response fetch menjadi JSON dengan pengecekan content-type
    async function safeJson(res) {
      try {
        // Ambil header Content-Type dan normalisasi ke lowercase
        const ct =
          (res.headers.get("content-type") || "")
            .toLowerCase();

        // Jika bukan JSON, kembalikan objek gagal dengan pesan error
        if (!ct.includes("application/json")) {
          return {
            success: false,
            message: "Respon tidak valid dari server.",
          };
        }

        // Jika content-type benar JSON, parse body sebagai JSON
        return await res.json();
      } catch {
        // Jika parsing gagal (error), kembalikan objek gagal dengan pesan error
        return {
          success: false,
          message: "Respon tidak valid dari server.",
        };
      }
    }

    // === Upload foto ===
    // Ambil elemen input file untuk avatar
    const fileInput = document.getElementById("fileAvatar");
    // Ambil tombol upload foto
    const btnUpload = document.getElementById("btnPhoto");
    // Ambil elemen div avatar (untuk update background-image)
    const avatarEl  = document.getElementById("avatar");

    // Saat tombol upload diklik, trigger klik pada input file
    btnUpload.onclick = () => fileInput.click();

    // Saat ada perubahan pada input file (user pilih file)
    fileInput.onchange = async () => {
      // Ambil file pertama yang dipilih user
      const file = fileInput.files[0];
      // Jika tidak ada file (user batal), hentikan
      if (!file) return;

      // Validasi ukuran file maksimal 2MB
      if (file.size > 2 * 1024 * 1024) {
        showCfAlert("Ukuran maksimum 2MB");
        return;
      }

      // Buat FormData baru untuk upload ke server
      const fd = new FormData();
      // Sisipkan file ke field "profile_picture"
      fd.append("profile_picture", file);

      // Kirim request POST ke endpoint API dengan body FormData
      const res = await fetch(API, {
        method: "POST",
        body: fd,
        credentials: "same-origin",
      });

      // Parse respons menggunakan safeJson
      const js = await safeJson(res);

      // Jika respons success = true
      if (js.success) {
        // Ambil path foto profil dari data respons (jika ada)
        const path =
          js.data && js.data.profile_picture
            ? js.data.profile_picture
            : "";

        // Jika path tidak kosong
        if (path) {
          // Ambil base URL dari PROJECT_BASE atau string kosong
          const base = window.PROJECT_BASE || "";
          // Jika path diawali "http", pakai langsung; jika tidak, gabung dengan base
          const url =
            path.startsWith("http") ? path : base + path;

          // Update background-image avatar dengan URL baru
          avatarEl.style.backgroundImage = `url(${url})`;
        }

        // Tampilkan alert sukses (gunakan message dari server atau default)
        showCfAlert(js.message || "Foto profil berhasil diperbarui");
      } else {
        // Jika gagal, tampilkan alert error (pesan server atau default)
        showCfAlert(js.message || "Gagal memperbarui foto profil");
      }
    };

    // === Simpan Nama ===
    // Saat tombol "Simpan" di sheet nama diklik
    document.getElementById("saveName").onclick = async () => {
      // Ambil value dari input nama dan trim spasi
      const name = document
        .getElementById("name")
        .value
        .trim();

      // Validasi: nama tidak boleh kosong
      if (!name) {
        showCfAlert("Nama tidak boleh kosong");
        return;
      }

      // Buat FormData baru untuk payload
      const fd = new FormData();
      // Tambahkan field "name" dengan nilai nama baru
      fd.append("name", name);

      // Kirim request POST ke API untuk update nama
      const res = await fetch(API, {
        method: "POST",
        body: fd,
        credentials: "same-origin",
      });

      // Parse respons menggunakan safeJson
      const js = await safeJson(res);

      // Jika berhasil update
      if (js.success) {
        // Update teks nama di summary profil (baris list)
        document.getElementById("valName").textContent = name;
        // Update teks nama di header profil (sebelah avatar)
        document.getElementById("whoName").textContent = name;
        // Tutup sheet ubah nama
        document.getElementById("sheetName").hidden = true;
        // Tampilkan alert sukses
        showCfAlert(js.message || "Nama berhasil diperbarui");
      } else {
        // Jika gagal, tampilkan pesan error
        showCfAlert(js.message || "Gagal memperbarui nama");
      }
    };

    // === Simpan No HP ===
    // Saat tombol "Simpan" di sheet nomor HP diklik
    document.getElementById("savePhone").onclick = async () => {
      // Ambil nilai input nomor HP lalu trim spasi
      const phone = document
        .getElementById("phone")
        .value
        .trim();

      // Buat FormData untuk kirim ke server
      const fd = new FormData();
      // Tambahkan field "phone" dengan nilai nomor HP
      fd.append("phone", phone);

      // Kirim request POST ke API untuk update nomor HP
      const res = await fetch(API, {
        method: "POST",
        body: fd,
        credentials: "same-origin",
      });

      // Parse respons menggunakan safeJson
      const js = await safeJson(res);

      // Jika update berhasil
      if (js.success) {
        // Update teks nomor HP di baris list (tampilkan "Belum diisi" jika kosong)
        document.getElementById("valPhone").textContent =
          phone || "Belum diisi";

        // Tutup sheet ubah nomor HP
        document.getElementById("sheetPhone").hidden = true;

        // Tampilkan alert sukses
        showCfAlert(js.message || "Nomor HP berhasil diperbarui");
      } else {
        // Jika gagal, tampilkan pesan error
        showCfAlert(js.message || "Gagal memperbarui nomor HP");
      }
    };

    // === Ganti Password ===
    // Saat tombol "Perbarui" di sheet password diklik
    document.getElementById("savePass").onclick = async () => {
      // Ambil password lama dari input
      const oldpass  = document.getElementById("oldpass").value;
      // Ambil password baru dari input
      const newpass  = document.getElementById("newpass").value;
      // Ambil konfirmasi password baru
      const confpass = document.getElementById("confpass").value;

      // Validasi: semua kolom password wajib diisi
      if (!oldpass || !newpass) {
        showCfAlert("Isi semua kolom password");
        return;
      }

      // Validasi: panjang password baru minimal 6 karakter
      if (newpass.length < 6) {
        showCfAlert("Password minimal 6 karakter");
        return;
      }

      // Validasi: konfirmasi password harus sama dengan password baru
      if (newpass !== confpass) {
        showCfAlert("Konfirmasi tidak cocok");
        return;
      }

      // Buat FormData baru untuk kirim ke server
      const fd = new FormData();
      // Sertakan password lama pada field "old_password"
      fd.append("old_password", oldpass);
      // Sertakan password baru pada field "password"
      fd.append("password", newpass);

      // Kirim request POST ke API untuk update password
      const res = await fetch(API, {
        method: "POST",
        body: fd,
        credentials: "same-origin",
      });

      // Parse respons menggunakan safeJson
      const js = await safeJson(res);

      // Jika update password berhasil
      if (js.success) {
        // Tutup sheet ganti password
        document.getElementById("sheetPassword").hidden = true;
        // Tampilkan pesan sukses (dari server atau default)
        showCfAlert(js.message || "Password berhasil diperbarui");
      } else {
        // Jika gagal, tampilkan pesan error
        showCfAlert(js.message || "Gagal memperbarui password");
      }
    };
  </script>
</body>
</html>
