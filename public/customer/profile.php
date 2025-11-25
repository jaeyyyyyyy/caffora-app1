<?php
// public/customer/profile.php
// Halaman profil customer: tampilkan data akun + ubah nama, no HP, password, dan avatar via AJAX

declare(strict_types=1); // Aktifkan strict types di PHP 7+

// Import auth_guard untuk memastikan user sudah login
require_once __DIR__ . '/../../backend/auth_guard.php';
// Batasi halaman ini hanya bisa diakses role "customer"
require_login(['customer']);

// ==== BASE dinamis (tanpa hardcode) ====
// Ambil path script saat ini, misalnya: /caffora/public/customer/profile.php
$script = $_SERVER['SCRIPT_NAME'] ?? '';
// Cari posisi substring "/public/" di path
$pos    = strpos($script, '/public/');
// Jika ketemu, ambil substring sebelum "/public/", jadikan BASE project. Kalau tidak, BASE = "" (root).
$BASE   = $pos !== false ? substr($script, 0, $pos) : '';

// Ambil id user dari session, cast ke int
$userId = (int)($_SESSION['user_id'] ?? 0);
// Ambil nama user dari session, fallback ke "Customer"
$name   = $_SESSION['user_name']   ?? 'Customer';
// Ambil email user dari session, fallback ke string kosong
$email  = $_SESSION['user_email']  ?? '';
// Ambil nomor HP user dari session, fallback ke string kosong
$phone  = $_SESSION['user_phone']  ?? '';
// Ambil path/avatar user dari session, fallback ke string kosong
$avatar = $_SESSION['user_avatar'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8" /> <!-- Set encoding dokumen ke UTF-8 -->
  <meta name="viewport" content="width=device-width,initial-scale=1" /> <!-- Supaya responsif di mobile -->
  <title>Profil</title> <!-- Judul tab browser -->

  <!-- CDN Bootstrap Icons untuk ikon panah/back -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    rel="stylesheet"
  />
  <!-- Google Fonts Poppins untuk tipografi utama -->
  <link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap"
    rel="stylesheet"
  />

  <style>
    :root {
      /* Warna utama brand & utilitas */
      --gold:  #ffd54f;  /* Kuning emas untuk tombol utama */
      --ink:   #222;     /* Warna teks utama */
      --brown: #4b3f36;  /* Coklat khas Caffora */
      --bg:    #fafbfc;  /* Background lembut */
      --line:  #eceff3;  /* Warna garis/pembatas */
    }

    /* global, sama seperti checkout */
    * {
      /* Pakai font Poppins sebagai default, fallback ke system fonts */
      font-family: Poppins, system-ui, -apple-system, "Segoe UI",
                   Roboto, Arial, sans-serif;
      box-sizing: border-box; /* Padding dan border masuk perhitungan width/height */
    }

    body {
      background: var(--bg); /* Warna background keseluruhan halaman */
      color: var(--ink);     /* Warna teks utama */
      margin: 0;             /* Hilangkan margin default body */
    }

    /* ====== TOPBAR (copy dari checkout) ====== */
    .topbar {
      background: #fff;                         /* Background putih di topbar */
      border-bottom: 1px solid rgba(0,0,0,.05);/* Garis tipis di bawah topbar */
      position: sticky;                        /* Menempel di atas saat scroll */
      top: 0;                                  /* Posisi menempel di atas */
      z-index: 20;                             /* Di atas konten lain */
    }

    .topbar-inner {
      max-width: 1200px;       /* Batas lebar konten topbar */
      margin: 0 auto;          /* Center secara horizontal */
      padding: 12px 24px;      /* Ruang dalam atas/bawah & kiri/kanan */
      min-height: 52px;        /* Tinggi minimum topbar */
      display: flex;           /* Flexbox untuk susunan horizontal */
      align-items: center;     /* Vertikal center */
      gap: 10px;               /* Jarak antar elemen di dalam topbar */
    }

    .back-link {
      display: inline-flex;     /* Tampilkan sebagai inline-flex */
      align-items: center;      /* Center vertikal ikon + teks */
      gap: 10px;                /* Jarak antara ikon dan teks */
      color: var(--brown);      /* Warna teks/ikon back */
      text-decoration: none;    /* Hilangkan underline link */
      border: 0;                /* Hilangkan border default button */
      background: transparent;  /* Background transparan */
      padding: 0;               /* Hilangkan padding default button */
    }

    /* 👉 Paksa teks “Kembali” pakai system-ui (Inter) */
    .back-link span {
      font-family: system-ui, -apple-system, "Segoe UI",
                   Roboto, Arial, sans-serif !important; /* Override ke system font */
      font-size: 1rem;      /* 16px ukuran teks */
      font-weight: 600;     /* Tebal semi-bold */
      line-height: 1.3;     /* Jarak antar baris */
    }

    /* Ikon panah 18x18 sama seperti checkout */
    .back-link .bi {
      width: 18px;                      /* Lebar ikon */
      height: 18px;                     /* Tinggi ikon */
      display: inline-flex;             /* Flex supaya center */
      align-items: center;              /* Center vertikal */
      justify-content: center;          /* Center horizontal */
      font-size: 18px !important;       /* Ukuran font ikon */
      line-height: 18px !important;     /* Line-height agar pas */
    }

    /* ====== KONTEN ====== */
    main {
      max-width: 1200px;        /* Batas lebar konten utama */
      margin: 0 auto;           /* Center konten di tengah */
      padding: 14px 18px 50px;  /* Padding atas, samping, dan bawah */
    }

    /* ====== HEADER PROFIL ====== */
    .profile-head {
      display: flex;                      /* Susun header secara horizontal */
      align-items: center;                /* Center vertikal */
      justify-content: space-between;     /* Avatar di kiri, tombol upload di kanan */
      gap: 18px 26px;                     /* Jarak antar elemen */
      margin: 4px 0 10px;                 /* Margin atas dan bawah kecil */
    }

    .profile-left {
      display: flex;          /* Avatar + info nama/email sejajar */
      align-items: center;    /* Center vertikal */
      gap: 18px;              /* Jarak antara avatar dan teks */
      min-width: 0;           /* Agar teks bisa mengalir/ellipsis */
    }

    .avatar {
      width: 64px;                              /* Lebar avatar */
      height: 64px;                             /* Tinggi avatar */
      border-radius: 50%;                       /* Bentuk lingkaran */
      background: #eee center / cover no-repeat;/* Background default abu, center & cover */
      flex-shrink: 0;                           /* Jangan mengecil saat ruang sempit */
    }

    .who {
      min-width: 0;   /* Supaya teks bisa ter-ellipsis */
    }

    .who .name {
      font-weight: 600;     /* Tebal untuk nama */
      font-size: 1.02rem;   /* Ukuran sedikit > 16px */
      margin-bottom: 2px;   /* Jarak kecil ke email */
    }

    .who .mail {
      color: var(--muted);  /* Warna teks lebih lembut (muted) */
      font-size: 0.8rem;    /* Ukuran lebih kecil */
      word-break: break-word; /* Jika email panjang, boleh dipecah */
    }

    /* ====== LIST ====== */
    .profile-box {
      background: transparent; /* Tidak pakai card, biarkan transparan */
      border: 0;               /* Hilangkan border */
      border-radius: 0;        /* Tidak ada radius */
      max-width: 100%;         /* Lebar penuh container */
      margin-top: 4px;         /* Jarak sedikit dari header */
    }

    .row {
      display: flex;                         /* Label + value + tombol */
      align-items: center;                   /* Center vertikal */
      gap: 12px;                             /* Jarak antar elemen di row */
      padding: 16px 0;                       /* Padding atas bawah */
      border-bottom: 1px solid #f5f5f5;      /* Garis pemisah antar row */
    }

    .row:last-child {
      border-bottom: none;  /* Row terakhir tanpa garis bawah */
    }

    .label {
      font-weight: 500;   /* Bold ringan untuk label kiri */
      font-size: 0.95rem; /* Sedikit lebih kecil dari 16px */
    }

    .value {
      margin-left: auto;         /* Dorong ke kanan */
      margin-right: 4px;         /* Sedikit jarak ke tombol chevron */
      color: var(--muted);       /* Warna abu lembut */
      font-size: 0.9rem;         /* Ukuran teks value */
      overflow: hidden;          /* Sembunyikan teks berlebih */
      text-overflow: ellipsis;   /* Tampilkan ... jika kepanjangan */
      white-space: nowrap;       /* Jangan pindah baris */
      max-width: 55%;            /* Batas lebar value */
      text-align: right;         /* Rata kanan */
    }

    .chev-btn {
      background: none;          /* Tanpa background */
      border: none;              /* Tanpa border */
      color: #9aa0a6;            /* Warna abu ikon panah kanan */
      font-size: 18px;           /* Ukuran ikon */
      line-height: 1;            /* Tinggi garis */
      cursor: pointer;           /* Tampil pointer saat hover */
      padding: 4px;              /* Sedikit padding klik area */
      margin-left: 2px;          /* Jarak kecil dari value */
    }

    /* ====== BUTTON – MATCH CART/CHECKOUT ====== */
    .btn {
      border: none;                            /* Hilangkan border default */
      border-radius: 14px;                     /* Tombol rounded pill */
      cursor: pointer;                         /* Pointer saat hover */
      padding: 10px 18px;                      /* Ruang dalam */
      min-height: 41px;                        /* Tinggi minimum tombol */
      display: inline-flex;                    /* Flex untuk center konten */
      align-items: center;                     /* Center vertikal teks/ikon */
      justify-content: center;                 /* Center horizontal */
      gap: 8px;                                /* Jarak antara ikon & teks (kalau ada) */
      font-family: Arial, Helvetica, sans-serif !important; /* Font tombol mengikuti checkout */
      font-size: 14.08px !important;           /* Ukuran font konsisten */
      font-weight: 600;                        /* Tebal untuk feel button */
      line-height: 1.2;                        /* Line-height teks */
      white-space: nowrap;                     /* Jangan pindah baris */
      box-shadow: none;                        /* Hilangkan shadow default */
    }

    /* ekstra jaga-jaga untuk tombol di bottom sheet */
    .actions .btn {
      font-family: Arial, Helvetica, sans-serif !important; /* Paksa font sama */
      font-size: 14.08px !important;                       /* Paksa ukuran font */
      padding: 10px 18px !important;                       /* Paksa padding */
      min-height: 41px !important;                         /* Paksa tinggi minimum */
      border-radius: 14px !important;                      /* Paksa radius */
    }

    .btn.primary {
      background-color: var(--gold);             /* Warna tombol utama */
      color: var(--brown) !important;            /* Teks coklat */
    }

    .btn.secondary {
      background-color: #f3f4f6;                 /* Warna abu terang */
      color: #333;                               /* Teks abu gelap */
    }

    .btn.primary:hover {
      filter: brightness(1.05);                  /* Sedikit lebih terang saat hover */
    }

    .btn.secondary:hover {
      background: #e9eaec;                       /* Abu sedikit lebih gelap saat hover */
    }

    /* ====== BOTTOM SHEET ====== */
    .sheet[hidden] {
      display: none;                /* Kalau atribut hidden ada, jangan tampilkan */
    }

    .sheet {
      position: fixed;              /* Tetap di posisi layar (overlay) */
      inset: 0;                     /* Cover seluruh viewport */
      display: flex;                /* Flex container */
      align-items: flex-end;        /* Panel muncul dari bawah */
      background: rgba(0, 0, 0, 0.35); /* Overlay gelap semi transparan */
      z-index: 50;                  /* Di atas konten lain */
    }

    .panel {
      width: 100%;                  /* Lebar panel full */
      background: #fff;             /* Background putih */
      border-top-left-radius: 16px; /* Sudut atas kiri rounded */
      border-top-right-radius: 16px;/* Sudut atas kanan rounded */
      padding: 18px;                /* Padding isi panel */
      max-height: 75vh;             /* Batas tinggi max 75% viewport */
      overflow: auto;               /* Scroll jika isi kepanjangan */
    }

    .panel h3 {
      margin: 0 0 12px;             /* Margin bawah judul */
      font-size: 1.05rem;           /* Ukuran judul sheet */
      font-weight: 600;             /* Tebal */
    }

    .field {
      display: flex;                /* Stack label + input */
      flex-direction: column;       /* Susun vertikal */
      margin-bottom: 12px;          /* Jarak antar field */
    }

    label {
      font-size: 0.9rem;           /* Ukuran teks label */
      color: #555;                 /* Warna abu gelap */
      margin-bottom: 6px;          /* Jarak ke input */
    }

    input.text {
      padding: 11px;               /* Padding dalam input */
      border: 1px solid #ddd;      /* Border abu muda */
      border-radius: 8px;          /* Sudut sedikit rounded */
      font: inherit;               /* Ikut font dari body */
      width: 100%;                 /* Lebar penuh container */
      outline: none;               /* Hilangkan outline default */
      box-shadow: none;            /* Hilangkan shadow default */
    }

    input.text:focus,
    input.text:focus-visible {
      outline: none !important;         /* Pastikan tidak ada outline */
      box-shadow: none !important;      /* Hilangkan shadow fokus default */
      border-color: #ddd !important;    /* Warna border tetap abu */
    }

    .actions {
      display: flex;              /* Container tombol aksi */
      justify-content: flex-end;  /* Posisikan tombol di kanan */
      gap: 10px;                  /* Jarak antar tombol */
      margin-top: 12px;           /* Jarak ke field di atasnya */
    }

    /* desktop sheet */
    @media (min-width: 992px) {
      .sheet {
        align-items: center;      /* Di desktop, panel center vertikal */
        justify-content: center;  /* Dan center horizontal */
      }

      .panel {
        max-width: 520px;         /* Lebar maksimum panel di desktop */
        border-radius: 12px;      /* Radius di semua sudut */
      }
    }

    /* responsive */
    @media (max-width: 700px) {
      .topbar-inner,
      main {
        max-width: 100%;          /* Pada mobile, pakai lebar penuh */
        padding: 10px 14px;       /* Kurangi padding agar hemat ruang */
      }

      .profile-head {
        flex-wrap: wrap;          /* Header boleh turun ke baris baru */
        margin-bottom: 6px;       /* Margin bawah kecil */
      }

      .row {
        padding: 14px 0;          /* Sedikit lebih rapat */
      }

      .value {
        max-width: 50%;           /* Value diperkecil supaya tidak kepanjangan */
      }
    }
  </style>

  <!-- PROJECT_BASE auto-detect untuk JS -->
  <script>
    // IIFE untuk mendeteksi base path project dari URL saat ini
    (function () {
      const SPLIT = "/public/";                    // String pemisah untuk mendeteksi posisi folder public
      const i     = location.pathname.indexOf(SPLIT); // Cari index "/public/" di path URL

      // Jika ketemu "/public/", ambil substring sebelum itu sebagai PROJECT_BASE
      // Kalau tidak ketemu, PROJECT_BASE = "" (root)
      window.PROJECT_BASE = i > -1
        ? location.pathname.slice(0, i)
        : "";
    })();
  </script>
</head>
<body>
  <!-- TOPBAR -->
  <div class="topbar">
    <div class="topbar-inner">
      <!-- Tombol kembali: akan di-handle via JS (history/back atau redirect) -->
      <a class="back-link" id="goBack">
        <i class="bi bi-arrow-left"></i> <!-- Ikon panah kiri -->
        <span>Kembali</span>             <!-- Teks tombol back -->
      </a>
    </div>
  </div>

  <main>
    <?php
      // Hitung URL avatar final:
      // - Jika sudah absolut (http/https) → pakai apa adanya
      // - Jika relatif → prefix dengan BASE project
      $raw = $avatar ?: '/public/assets/img/avatar-placeholder.png';

      // Cek apakah $raw diawali http/https
      $avatarUrl = preg_match('~^https?://~i', (string) $raw)
        ? $raw          // Jika absolut URL
        : ($BASE . $raw); // Jika relatif, gabungkan dengan BASE
    ?>

    <!-- HEADER PROFIL: avatar + nama + email + tombol upload -->
    <section class="profile-head">
      <div class="profile-left">
        <!-- Avatar: background-image di-set inline dengan URL PHP -->
        <div
          id="avatar"
          class="avatar"
          style="background-image:url('<?= htmlspecialchars($avatarUrl, ENT_QUOTES) ?>')"
        ></div>

        <!-- Info nama & email user -->
        <div class="who">
          <div class="name" id="whoName">
            <?= htmlspecialchars($name) ?>
          </div>
          <div class="mail">
            <?= htmlspecialchars($email) ?>
          </div>
        </div>
      </div>

      <!-- Tombol untuk memilih file avatar baru -->
      <button class="btn primary" id="btnPhoto">
        Upload
      </button>

      <!-- Input file tersembunyi, dipicu oleh tombol Upload -->
      <input
        type="file"
        id="fileAvatar"
        accept="image/png,image/jpeg"
        hidden
      />
    </section>

    <!-- LIST DATA AKUN -->
    <section class="profile-box" aria-label="Akun">
      <!-- Row: Nama -->
      <div class="row">
        <span class="label">Nama</span>
        <span class="value" id="valName">
          <?= htmlspecialchars($name) ?>
        </span>
        <!-- Tombol chevron buka bottom sheet ubah nama -->
        <button class="chev-btn" data-open="sheetName">›</button>
      </div>

      <!-- Row: No HP -->
      <div class="row">
        <span class="label">No. HP</span>
        <span class="value" id="valPhone">
          <?= $phone ? htmlspecialchars($phone) : 'Belum diisi' ?>
        </span>
        <!-- Tombol chevron buka bottom sheet ubah nomor HP -->
        <button class="chev-btn" data-open="sheetPhone">›</button>
      </div>

      <!-- Row: Ganti password -->
      <div class="row">
        <span class="label">Ganti Password</span>
        <span class="value">Keamanan akun</span>
        <!-- Tombol chevron buka bottom sheet ganti password -->
        <button class="chev-btn" data-open="sheetPassword">›</button>
      </div>
    </section>
  </main>

  <!-- Sheet ubah nama -->
  <div class="sheet" id="sheetName" hidden>
    <div class="panel">
      <h3>Ubah Nama</h3>

      <div class="field">
        <label for="name">Nama lengkap</label>
        <!-- Input nama baru, value awal dari nama sekarang -->
        <input
          id="name"
          class="text"
          type="text"
          value="<?= htmlspecialchars($name) ?>"
        />
      </div>

      <div class="actions">
        <!-- Tombol tutup sheet tanpa menyimpan -->
        <button class="btn secondary" data-close>
          Batalkan
        </button>
        <!-- Tombol simpan nama baru -->
        <button class="btn primary" id="saveName">
          Simpan
        </button>
      </div>
    </div>
  </div>

  <!-- Sheet ubah nomor HP -->
  <div class="sheet" id="sheetPhone" hidden>
    <div class="panel">
      <h3>Ubah No. HP</h3>

      <div class="field">
        <label for="phone">Nomor HP</label>
        <!-- Input nomor HP, bisa kosong atau dari session -->
        <input
          id="phone"
          class="text"
          type="tel"
          placeholder="08xxxxxxxxxx"
          value="<?= htmlspecialchars($phone) ?>"
          autocomplete="tel"
        />
      </div>

      <div class="actions">
        <!-- Batal ubah nomor HP -->
        <button class="btn secondary" data-close>
          Batalkan
        </button>
        <!-- Simpan nomor HP baru -->
        <button class="btn primary" id="savePhone">
          Simpan
        </button>
      </div>
    </div>
  </div>

  <!-- Sheet ganti password -->
  <div class="sheet" id="sheetPassword" hidden>
    <div class="panel">
      <h3>Ganti Password</h3>

      <div class="field">
        <label for="oldpass">Password Lama</label>
        <!-- Input password lama -->
        <input
          id="oldpass"
          class="text"
          type="password"
          placeholder="••••••"
        />
      </div>

      <div class="field">
        <label for="newpass">Password Baru</label>
        <!-- Input password baru -->
        <input
          id="newpass"
          class="text"
          type="password"
          placeholder="Minimal 6 karakter"
        />
      </div>

      <div class="field">
        <label for="confpass">Konfirmasi Password</label>
        <!-- Input konfirmasi password baru -->
        <input
          id="confpass"
          class="text"
          type="password"
          placeholder="Ulangi password baru"
        />
      </div>

      <div class="actions">
        <!-- Batal ganti password -->
        <button class="btn secondary" data-close>
          Batalkan
        </button>
        <!-- Kirim request ganti password -->
        <button class="btn primary" id="savePass">
          Perbarui
        </button>
      </div>
    </div>
  </div>

  <script>
    // API endpoint dinamis (tanpa hardcode base)
    // PROJECT_BASE dideteksi di script <head>, lalu disambung dengan path backend
    const API =
      (window.PROJECT_BASE || "") +
      "/backend/api/profile_update.php";

    // Handler tombol Kembali:
    // - Jika ada history di origin yang sama → history.back()
    // - Kalau tidak ada → fallback ke halaman index customer
    document
      .getElementById("goBack") // Ambil elemen link "Kembali"
      .addEventListener("click", function (e) {
        e.preventDefault(); // Cegah perilaku default link (pergi ke href)

        try {
          const ref  = document.referrer || ""; // URL halaman sebelumnya (kalau ada)
          const same =
            ref &&
            new URL(ref, location.href).origin ===
              location.origin; // Cek apakah origin referrer sama dengan origin saat ini

          if (same && history.length > 1) {
            history.back(); // Jika iya dan history cukup, kembali ke halaman sebelumnya
            return;         // Stop eksekusi lanjut
          }
        } catch (_) {} // Jika parsing URL error, abaikan dan lanjut ke fallback

        // Fallback: kalau tidak ada history atau beda origin, arahkan ke halaman index customer
        window.location.href =
          (window.PROJECT_BASE || "") +
          "/public/customer/index.php";
      });

    // Listener untuk membuka sheet (bottom sheet) berdasarkan data-open
    document
      .querySelectorAll("[data-open]") // Cari semua tombol yang punya atribut data-open
      .forEach((btn) => {
        btn.onclick = () => {
          const id = btn.dataset.open;        // Ambil nilai data-open (id sheet)
          const el = document.getElementById(id); // Cari elemen sheet dengan id itu
          if (el) {
            el.hidden = false;               // Tampilkan sheet (hapus hidden)
          }
        };
      });

    // Listener untuk menutup sheet:
    // - klik area gelap di luar panel
    // - klik tombol dengan atribut data-close
    document
      .querySelectorAll(".sheet") // Ambil semua overlay sheet
      .forEach((s) => {
        // Tutup sheet jika klik di area overlay (bukan panel)
        s.addEventListener("click", (e) => {
          if (e.target === s) {  // Pastikan yang diklik adalah overlay, bukan isi panel
            s.hidden = true;    // Sembunyikan sheet
          }
        });

        // Tombol data-close di dalam sheet untuk menutup
        s
          .querySelectorAll("[data-close]") // Cari semua elemen dengan data-close
          .forEach((c) => {
            c.onclick = () => {
              s.hidden = true;  // Sembunyikan sheet
            };
          });
      });

    // Helper: parser JSON aman
    // - pastikan content-type JSON
    // - kalau gagal, kembalikan objek error standar
    async function safeJson(res) {
      try {
        const ct =
          (res.headers.get("content-type") || "")
            .toLowerCase(); // Ambil header Content-Type dan kecilkan huruf

        if (!ct.includes("application/json")) {
          // Jika bukan JSON, balikan error standar
          return {
            success: false,
            message: "Respon tidak valid dari server.",
          };
        }

        return await res.json(); // Jika JSON, parse dan kembalikan
      } catch {
        // Jika parsing error, juga kembalikan error standar
        return {
          success: false,
          message: "Respon tidak valid dari server.",
        };
      }
    }

    // === Upload foto profil ===
    const fileInput = document.getElementById("fileAvatar"); // Input file avatar
    const btnUpload = document.getElementById("btnPhoto");   // Tombol "Upload"
    const avatarEl  = document.getElementById("avatar");     // Elemen div avatar (background-image)

    // Klik tombol "Upload" → trigger input file
    btnUpload.onclick = () => fileInput.click();

    // Setelah user memilih file avatar → upload ke API
    fileInput.onchange = async () => {
      const file = fileInput.files[0]; // Ambil file pertama yang dipilih
      if (!file) {
        return;                        // Kalau tidak ada file (user cancel), keluar
      }

      // Validasi ukuran maksimum 2MB
      if (file.size > 2 * 1024 * 1024) {
        alert("Ukuran maksimum 2MB");  // Tampilkan pesan error
        return;                        // Stop proses
      }

      const fd = new FormData();                         // Buat FormData baru
      fd.append("profile_picture", file);                // Tambah field "profile_picture" dengan file

      const res = await fetch(API, {
        method:       "POST",          // Metode POST
        body:         fd,              // Body berupa FormData
        credentials:  "same-origin",   // Sertakan cookie/session ke origin yang sama
      });

      const js = await safeJson(res);  // Parse respon JSON dengan helper

      if (js.success) {
        // Ambil path profile_picture dari respon, jika ada
        const path =
          js.data && js.data.profile_picture
            ? js.data.profile_picture
            : "";

        if (path) {
          const base = window.PROJECT_BASE || ""; // Base path project
          const url  =
            path.startsWith("http")              // Jika path sudah http/https
              ? path                             // Pakai langsung
              : base + path;                     // Kalau relatif, gabungkan dengan base

          // Update background-image avatar di UI
          avatarEl.style.backgroundImage = `url(${url})`;
        }

        alert(js.message || "Foto profil berhasil diperbarui"); // Tampilkan pesan sukses
      } else {
        alert(js.message || "Gagal memperbarui foto profil");   // Tampilkan pesan gagal
      }
    };

    // === Simpan Nama ===
    // Mengirim nama baru ke API dan update teks di UI
    document
      .getElementById("saveName")   // Tombol simpan nama pada sheet
      .onclick = async () => {
        const name = document
          .getElementById("name")   // Input teks nama
          .value
          .trim();                  // Hilangkan spasi di awal/akhir

        if (!name) {
          alert("Nama tidak boleh kosong"); // Validasi nama harus diisi
          return;
        }

        const fd = new FormData();  // FormData untuk request
        fd.append("name", name);    // Tambahkan field "name"

        const res = await fetch(API, {
          method:      "POST",      // Kirim via POST
          body:        fd,          // Body FormData
          credentials: "same-origin", // Sertakan cookie/session
        });

        const js = await safeJson(res); // Parse JSON respon

        if (js.success) {
          // Update tampilan nama di list
          document.getElementById("valName").textContent = name;
          // Update tampilan nama di header profil
          document.getElementById("whoName").textContent = name;
          // Tutup sheet ubah nama
          document.getElementById("sheetName").hidden    = true;
        } else {
          alert(js.message || "Gagal memperbarui nama"); // Tampilkan error
        }
      };

    // === Simpan No HP ===
    // Mengirim nomor HP baru ke API dan update tampilan
    document
      .getElementById("savePhone") // Tombol simpan nomor HP
      .onclick = async () => {
        const phone = document
          .getElementById("phone") // Input telp
          .value
          .trim();                 // Hilangkan spasi

        const fd = new FormData(); // FormData baru
        fd.append("phone", phone); // Tambahkan field "phone"

        const res = await fetch(API, {
          method:      "POST",     // Kirim via POST
          body:        fd,         // Body FormData
          credentials: "same-origin", // Sertakan cookie/session
        });

        const js = await safeJson(res); // Parse respon JSON

        if (js.success) {
          // Jika phone kosong, tampilkan "Belum diisi", kalau ada tampilkan nilai phone
          document.getElementById("valPhone").textContent =
            phone || "Belum diisi";

          // Tutup sheet ubah nomor HP
          document.getElementById("sheetPhone").hidden = true;
        } else {
          alert(js.message || "Gagal memperbarui nomor HP"); // Error message
        }
      };

    // === Ganti Password ===
    // Validasi input, kirim password lama + baru ke API
    document
      .getElementById("savePass") // Tombol simpan password
      .onclick = async () => {
        const oldpass  = document.getElementById("oldpass").value;  // Password lama
        const newpass  = document.getElementById("newpass").value;  // Password baru
        const confpass = document.getElementById("confpass").value; // Konfirmasi password

        if (!oldpass || !newpass) {
          alert("Isi semua kolom password"); // Wajib isi password lama & baru
          return;
        }

        if (newpass.length < 6) {
          alert("Password minimal 6 karakter"); // Validasi panjang minimal
          return;
        }

        if (newpass !== confpass) {
          alert("Konfirmasi tidak cocok"); // Password baru dan konfirmasi harus sama
          return;
        }

        const fd = new FormData();           // FormData baru
        fd.append("old_password", oldpass);  // Field password lama
        fd.append("password",     newpass);  // Field password baru

        const res = await fetch(API, {
          method:      "POST",     // Request POST ke API
          body:        fd,         // Body FormData
          credentials: "same-origin", // Sertakan cookie/session
        });

        const js = await safeJson(res);      // Parse respon JSON

        if (js.success) {
          alert(js.message || "Password berhasil diperbarui"); // Pesan sukses
          document.getElementById("sheetPassword").hidden = true; // Tutup sheet password
        } else {
          alert(js.message || "Gagal memperbarui password");  // Pesan gagal
        }
      };
  </script>
</body>
</html>
