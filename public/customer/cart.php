<?php
// ===============================================
// File   : public/customer/cart.php
// Fungsi : Halaman keranjang belanja pelanggan
// Akses  : Hanya untuk user dengan role "customer"
// ===============================================

declare(strict_types=1); // Aktifkan strict types di PHP 7+

require_once __DIR__ . '/../../backend/auth_guard.php'; // Load guard otentikasi
require_login(['customer']);                            // Wajib login sebagai customer
?>
<!doctype html> <!-- Deklarasi tipe dokumen HTML5 -->
<html lang="id"> <!-- Dokumen berbahasa Indonesia -->
<head>
  <meta charset="utf-8"> <!-- Set encoding karakter ke UTF-8 -->
  <title>Keranjang — Caffora</title> <!-- Judul tab browser -->
  <meta
    name="viewport"
    content="width=device-width, initial-scale=1"
  > <!-- Supaya tampilan responsif di mobile -->

  <!-- ===============================
       CSS Bootstrap & Bootstrap Icons
       =============================== -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
  > <!-- CSS Bootstrap 5.3.3 -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    rel="stylesheet"
  > <!-- Icon set Bootstrap Icons -->

  <style>
    /* ==========================================
       Variabel warna brand Caffora
       ========================================== */
    :root {
      --yellow:    #FFD54F;  /* Kuning utama */
      --camel:     #DAA85C;  /* Kuning kecoklatan */
      --brown:     #4B3F36;  /* Coklat teks utama */
      --line:      #e5e7eb;  /* Warna garis/pembatas */
      --bg:        #fffdf8;  /* Background krem lembut */
      --gold:      #FFD54F;  /* Alias ke kuning emas */
      --gold-200:  #FFE883;  /* Versi kuning lebih muda */
    }

    /* Reset sederhana + font global */
    * {
      font-family:
        "Poppins",              /* Font utama */
        system-ui,
        -apple-system,
        "Segoe UI",
        Roboto,
        Arial,
        sans-serif;             /* Font fallback */
      box-sizing: border-box;   /* Lebar elemen termasuk padding & border */
    }

    body {
      background: var(--bg);    /* Warna latar body */
      color: var(--brown);      /* Warna teks utama */
      overflow-x: hidden;       /* Hilangkan scroll horizontal */
      margin: 0;                /* Hilangkan margin default body */
    }

    /* ==========================================
       TOPBAR (bar atas dengan tombol kembali)
       ========================================== */
    .topbar {
      background: #fff;                         /* Topbar putih */
      border-bottom: 1px solid rgba(0,0,0,.05);/* Garis tipis bawah topbar */
      position: sticky;                        /* Menempel di atas saat scroll */
      top: 0;                                  /* Posisi menempel di 0 (atas) */
      z-index: 20;                             /* Di atas konten lain */
    }

    .topbar .inner {
      max-width: 1200px;       /* Batas lebar konten topbar */
      margin: 0 auto;          /* Diletakkan di tengah layar */
      padding: 12px 16px;      /* Ruang dalam kiri-kanan dan atas-bawah */
      min-height: 52px;        /* Tinggi minimum topbar */

      display: flex;           /* Gunakan flexbox */
      align-items: center;     /* Rata tengah vertikal isi */
      justify-content: space-between; /* Kiri-kanan berjauhan */
      gap: 12px;               /* Jarak antar elemen dalam topbar */
    }

    /* Link kembali ke halaman katalog */
    .back-link {
      display: inline-flex;    /* Flex khusus untuk ikon + teks */
      align-items: center;     /* Vertikal center */
      gap: 10px;               /* Jarak ikon dan teks */

      color: var(--brown);     /* Warna teks */
      text-decoration: none;   /* Hilangkan garis bawah link */
      font-weight: 600;        /* Tebal medium */
      font-size: 1rem;         /* Ukuran font 16px */
      line-height: 1.3;        /* Tinggi baris */
    }

    .back-link .chev {
      font-size: 18px;         /* Ukuran ikon panah */
      line-height: 1;          /* Hindari tinggi baris berlebih */
    }

    /* Wrapper tombol kanan di topbar */
    .topbar-actions {
      display: flex;           /* Deret tombol secara horizontal */
      align-items: center;     /* Rata tengah vertikal */
      gap: 8px;                /* Jarak antar tombol */
      flex-shrink: 0;          /* Jangan mengecil ketika ruang sempit */
    }

    /* ==========================================
       Tombol emas (Pilih semua / Hapus / Checkout)
       ========================================== */
    .btn-gold,
    .btn-primary-cf {
      background-color: var(--gold);          /* Warna tombol emas */
      color: var(--brown) !important;         /* Teks coklat, paksa override */
      border: 0;                              /* Tanpa border default */
      border-radius: 14px;                    /* Sudut membulat */

      font-family: Arial, Helvetica, sans-serif; /* Font untuk tombol */
      font-weight: 600;                       /* Tebal */
      font-size: .88rem;                      /* Sedikit lebih kecil dari 14px */

      padding: 10px 18px;                     /* Ruang dalam tombol */

      display: inline-flex;                   /* Flex untuk ikon + teks */
      align-items: center;                    /* Rata tengah vertikal */
      justify-content: center;                /* Rata tengah horizontal */
      gap: 8px;                               /* Jarak antara ikon & teks */

      white-space: nowrap;                    /* Jangan bungkus ke baris baru */
      box-shadow: none;                       /* Tanpa bayangan default */
      cursor: pointer;                        /* Cursor tangan */
    }

    /* ==========================================
       Wrapper konten utama halaman
       ========================================== */
    .page {
      max-width: 1200px;       /* Batasi lebar konten */
      margin: 12px auto 40px;  /* Margin atas 12px, bawah 40px, center horizontal */
      padding: 0 16px;         /* Padding kiri-kanan */
    }

    /* ==========================================
       RESPONSIVE PADDING (mobile & desktop)
       ========================================== */

    /* Mobile / tablet (<= 768px) */
    @media (max-width: 768px) {
      .topbar .inner,
      .page {
        max-width: 100%;       /* Pakai lebar penuh di mobile */
        padding-left: 16px;    /* Padding kiri */
        padding-right: 16px;   /* Padding kanan */
      }

      .page {
        margin: 0 auto 40px;   /* Hilangkan margin atas ekstra di mobile */
      }
    }

    /* Desktop (>= 992px) */
    @media (min-width: 992px) {
      .topbar .inner,
      .page {
        padding-left: 12px;    /* Padding kiri lebih kecil di desktop */
        padding-right: 12px;   /* Padding kanan lebih kecil di desktop */
      }
    }

    /* ==========================================
       Item keranjang
       ========================================== */
    .cart-row {
      display: flex;           /* Susun item secara horizontal */
      align-items: center;     /* Rata tengah vertikal */
      justify-content: space-between; /* Info kiri & qty kanan berjauhan */
      flex-wrap: nowrap;       /* Jangan bungkus ke baris lain */
      gap: 12px;               /* Jarak antar elemen */

      padding: 16px 0;         /* Padding atas-bawah card item */
      border-bottom: 1px solid rgba(0,0,0,.06); /* Garis pemisah antar item */
    }

    .cart-info {
      display: flex;           /* Checkbox + gambar + teks */
      align-items: center;     /* Rata tengah vertikal */
      flex: 1;                 /* Ambil sisa lebar */
      gap: 12px;               /* Jarak antar elemen */
      min-width: 0;            /* Agar teks bisa terpangkas (ellipsis) */
    }

    /* Checkbox per item */
    .row-check {
      width: 18px;             /* Lebar checkbox custom */
      height: 18px;            /* Tinggi checkbox */

      border: 2px solid var(--yellow); /* Border kuning */
      border-radius: 4px;      /* Sudut sedikit membulat */

      appearance: none;        /* Hilangkan style default browser */
      background: #fff;        /* Background putih */
      cursor: pointer;         /* Cursor tangan */

      position: relative;      /* Untuk pseudo-element centang */
      flex-shrink: 0;          /* Jangan mengecil */
    }

    .row-check:checked {
      background: var(--yellow); /* Background kuning saat dicentang */
    }

    .row-check:checked::after {
      content: "";             /* Isi pseudo-element */
      position: absolute;      /* Posisi relatif ke checkbox */

      left: 4px;               /* Posisi dari kiri */
      top: 0;                  /* Posisi dari atas */

      width: 6px;              /* Lebar garis centang */
      height: 10px;            /* Tinggi garis centang */

      border: 2px solid var(--brown);     /* Warna garis centang */
      border-top: none;                   /* Hilangkan sisi atas */
      border-left: none;                  /* Hilangkan sisi kiri */
      transform: rotate(45deg);           /* Rotasi membentuk centang */
    }

    /* Thumbnail gambar menu */
    .cart-thumb {
      width: 80px;             /* Lebar gambar */
      height: 80px;            /* Tinggi gambar */
      border-radius: 12px;     /* Sudut membulat */

      object-fit: cover;       /* Gambar isi penuh tanpa distorsi */
      background: #f3f3f3;     /* Background abu jika gambar belum muncul */
      flex-shrink: 0;          /* Jangan mengecil */
    }

    .info-text {
      flex: 1;                 /* Ambil sisa lebar */
      min-width: 0;            /* Bisa ter-ellipsis */
    }

    .name {
      font-weight: 600;        /* Nama menu agak tebal */
      color: #2b2b2b;          /* Warna teks nama */
      font-size: 1rem;         /* Ukuran font */

      white-space: nowrap;     /* Jangan pindah baris */
      overflow: hidden;        /* Sembunyikan teks berlebih */
      text-overflow: ellipsis; /* Tampilkan "..." jika kepanjangan */
    }

    .price {
      font-size: .92rem;       /* Sedikit lebih kecil */
      color: #777;             /* Abu-abu untuk harga satuan */
    }

    /* Kontrol kuantitas per item */
    .qty {
      display: flex;           /* Minus, angka, plus sejajar */
      align-items: center;     /* Rata tengah vertikal */
      gap: 8px;                /* Jarak antar elemen */
      flex-shrink: 0;          /* Jangan mengecil */
    }

    .btn-qty {
      width: 34px;             /* Lebar tombol +/- */
      height: 34px;            /* Tinggi tombol */
      border-radius: 10px;     /* Sudut membulat */

      border: 1px solid var(--line);  /* Border abu muda */
      background: #fff;        /* Background putih */
      color: #2b2b2b;          /* Warna teks ikon +/- */
      font-weight: 700;        /* Tebal */

      transition:
        background .15s ease,  /* Animasi warna background */
        border-color .15s ease;/* Animasi warna border */
      cursor: pointer;         /* Cursor tangan */
    }

    .btn-qty:hover {
      background: #fafafa;     /* Background sedikit gelap saat hover */
      border-color: #d8dce2;   /* Border abu sedikit lebih gelap */
    }

    .qty-val {
      min-width: 28px;         /* Lebar minimum kolom angka qty */
      text-align: center;      /* Angka rata tengah */
      font-weight: 600;        /* Tebal */
    }

    /* ==========================================
       Bagian ringkasan subtotal + tombol checkout
       ========================================== */
    .summary {
      display: flex;           /* Subtotal + tombol sejajar */
      align-items: center;     /* Rata tengah vertikal */
      justify-content: space-between; /* Jarak kiri-kanan */

      flex-wrap: wrap;         /* Boleh pindah baris di mobile */
      gap: 12px;               /* Jarak antar elemen */

      padding: 20px 0;         /* Padding atas-bawah ringkasan */
      margin-top: 8px;         /* Jarak dari list item */

      border-top: 2px dashed rgba(0,0,0,.08); /* Garis putus-putus di atas */
    }

    .subtotal {
      font-size: 1rem;         /* Ukuran font subtotal */
      font-weight: 600;        /* Tebal */
    }

    /* ==========================================
       Responsive tambahan untuk mobile
       ========================================== */
    @media (max-width: 768px) {
      .topbar .inner,
      .page {
        max-width: 100%;       /* Full width di mobile */
        padding: 12px 16px;    /* Padding standar mobile */
      }

      .page {
        margin: 0 auto 40px;   /* Margin bawah 40px, atas 0 */
      }

      .cart-row {
        gap: 10px;             /* Jarak item sedikit lebih kecil */
      }

      .cart-thumb {
        width: 60px;           /* Thumbnail lebih kecil di mobile */
        height: 60px;
      }

      .btn-qty {
        width: 30px;           /* Tombol qty lebih kecil */
        height: 30px;
        border-radius: 8px;    /* Sudut sedikit berkurang */
      }

      .summary {
        flex-direction: column; /* Susun subtotal & tombol vertikal */
        align-items: center;    /* Rata tengah */
        text-align: center;     /* Teks summary center */
      }

      .summary .btn-primary-cf {
        width: auto;           /* Lebar otomatis */
        max-width: 240px;      /* Batas lebar tombol di mobile */
      }
    }

    /* Pesan ketika keranjang kosong */
    .empty {
      padding: 32px 4px;       /* Ruang atas-bawah cukup besar */
      text-align: center;      /* Teks ditengah */
      color: #6b7280;          /* Warna abu-abu */
      font-size: .95rem;       /* Ukuran font sedikit kecil */
    }
  </style>
</head>

<body>
  <!-- ==========================================
       TOPBAR NAVIGATION
       ========================================== -->
  <div class="topbar"> <!-- Wrapper bar atas -->
    <div class="inner"> <!-- Konten dalam topbar (kiri-kanan) -->

      <!-- Tombol kembali ke halaman katalog/menu -->
      <a
        class="back-link"
        href="./index.php"
      >
        <i class="bi bi-arrow-left chev"></i> <!-- Ikon panah kiri -->
        <span>Lanjut Belanja</span>          <!-- Teks link kembali -->
      </a>

      <!-- Aksi topbar: pilih semua / hapus item terpilih -->
      <div class="topbar-actions">
        <button
          id="btnSelectAll"
          class="btn-gold"
        >
          Pilih semua
        </button> <!-- Tombol untuk pilih semua item -->

        <button
          id="btnDeleteSelected"
          class="btn-gold"
        >
          Hapus
        </button> <!-- Tombol hapus item yang terpilih -->
      </div>
    </div>
  </div>

  <!-- Catatan: data-* tidak dipakai lagi; base path sekarang auto dari URL -->
  <main class="page"> <!-- Konten utama halaman -->

    <!-- Container untuk list keranjang yang dirender via JS -->
    <div id="cartList"></div>

    <!-- Ringkasan subtotal & tombol menuju checkout -->
    <div class="summary">
      <div
        class="subtotal"
        id="subtotal"
      >
        Subtotal: Rp 0
      </div> <!-- Label subtotal -->

      <button
        id="btnCheckout"
        class="btn-primary-cf"
      >
        Buat pesanan
      </button> <!-- Tombol menuju halaman checkout -->
    </div>
  </main>

  <!-- ==========================================
       SCRIPT: Logika keranjang (localStorage)
       ========================================== -->
  <script>
    (function () {
      // =========================================================
      // BASE PATH OTOMATIS (supaya support root atau subfolder)
      // Contoh:
      //   - di root    : /public/customer/cart.php
      //   - di subdir  : /caffora-app1/public/customer/cart.php
      // =========================================================
      var PUBLIC_SPLIT = "/public/";             // String pemisah untuk deteksi base
      var pathname     = window.location.pathname; // Path URL saat ini
      var idx          = pathname.indexOf(PUBLIC_SPLIT); // Posisi "/public/" di path

      // PROJECT_BASE: "" di root, "/caffora-app1" kalau di subfolder
      var PROJECT_BASE = (idx > -1)
        ? pathname.slice(0, idx)   // Ambil substring sebelum "/public/"
        : "";                      // Jika tidak ketemu, anggap di root

      var PUBLIC_BASE  = PROJECT_BASE + "/public"; // Base folder /public, bisa root/subdir

      // Path upload menu dan placeholder image
      var UPLOADS_BASE = PUBLIC_BASE + "/uploads/menu";   // Folder upload foto menu
      var PLACEHOLDER  = PUBLIC_BASE + "/assets/img/placeholder.jpg"; // Gambar default

      // Key localStorage untuk keranjang
      var KEY = "caffora_cart";               // Nama key penyimpanan cart

      // Referensi elemen DOM utama
      var $list         = document.getElementById("cartList");       // Wrapper list item
      var $subtotal     = document.getElementById("subtotal");       // Label subtotal
      var $btnSelectAll = document.getElementById("btnSelectAll");   // Tombol pilih semua
      var $btnDelete    = document.getElementById("btnDeleteSelected"); // Tombol hapus
      var $btnCheckout  = document.getElementById("btnCheckout");    // Tombol checkout

      // Set untuk menyimpan ID item yang terpilih
      var selectedIds = new Set(); // Menyimpan id-item yang di-check

      // ---------------------------------------------------------
      // Format angka menjadi Rupiah, contoh: Rp 10.000
      // ---------------------------------------------------------
      function rupiah(n) {
        // Konversi ke number, lalu format lokal Indonesia
        return "Rp " + Number(n || 0).toLocaleString("id-ID");
      }

      // ---------------------------------------------------------
      // Escape karakter khusus untuk mencegah XSS di innerHTML
      // ---------------------------------------------------------
      function escapeHtml(s) {
        // Ubah karakter spesial ke entitas HTML
        return String(s || "").replace(
          /[&<>\"']/g,
          function (m) {
            return {
              "&": "&amp;",   // Ganti & dengan &amp;
              "<": "&lt;",    // Ganti < dengan &lt;
              ">": "&gt;",    // Ganti > dengan &gt;
              '"': "&quot;",  // Ganti " dengan &quot;
              "'": "&#39;"    // Ganti ' dengan &#39;
            }[m];
          }
        );
      }

      // ---------------------------------------------------------
      // Ambil data keranjang dari localStorage
      // ---------------------------------------------------------
      function getCart() {
        try {
          // Parse JSON dari localStorage, jika tidak ada -> []
          return JSON.parse(
            localStorage.getItem(KEY) || "[]"
          );
        } catch (e) {
          // Jika JSON rusak, kembalikan array kosong
          return [];
        }
      }

      // ---------------------------------------------------------
      // Simpan data keranjang ke localStorage
      // ---------------------------------------------------------
      function setCart(c) {
        // Ubah array ke JSON dan simpan
        localStorage.setItem(
          KEY,
          JSON.stringify(c)
        );
      }

      // ---------------------------------------------------------
      // Hitung nilai 1 baris item: price * qty
      // ---------------------------------------------------------
      function line(it) {
        var price = Number(it.price) || 0; // Harga per item
        var qty   = Number(it.qty)   || 0; // Kuantitas item
        return price * qty;               // Total harga per baris
      }

      // ---------------------------------------------------------
      // Normalisasi URL gambar agar cocok di hosting:
      // - Hilangkan base localhost
      // - Pastikan path mengarah ke /public/uploads/menu
      // ---------------------------------------------------------
      function normalizeImageUrl(raw) {
        // Jika tidak ada path, kembalikan string kosong
        if (!raw) return "";

        // Konversi ke string, ganti backslash dengan slash
        var u = String(raw)
          .trim()
          .replace(/\\/g, "/");

        // Jika full URL (http/https), pakai pathname-nya saja
        if (/^https?:\/\//i.test(u)) {
          try {
            var urlObj = new URL(u);      // Parse URL
            u = urlObj.pathname || "";    // Ambil path saja
          } catch (e) {
            // Jika gagal parse, biarkan apa adanya
          }
        }

        // Jika sudah mengandung PUBLIC_BASE + "/uploads/menu/"
        if (u.indexOf(PUBLIC_BASE + "/uploads/menu/") === 0) {
          return u;                       // Sudah benar, langsung kembalikan
        }

        // Jika mulai dengan "/public/uploads/menu/..."
        if (u.indexOf("/public/uploads/menu/") === 0) {
          return PROJECT_BASE + u;        // Tambah PROJECT_BASE di depan
        }

        // Jika mulai dengan "public/uploads/menu" tanpa slash depan
        if (u.indexOf("public/uploads/menu/") === 0) {
          // Hilangkan "public/uploads/menu/" di awal
          u = u.replace(
            /^\/?public\/uploads\/menu\//i,
            ""
          );
          // Gabungkan dengan UPLOADS_BASE dan rapikan double slash
          return (UPLOADS_BASE + "/" + u).replace(
            /([^:])\/{2,}/g,
            "$1/"
          );
        }

        // Jika path mengandung "uploads/menu" di manapun
        if (/uploads\/menu\//i.test(u)) {
          // Buang semua sebelum "uploads/menu"
          u = u.replace(
            /^.*uploads\/menu\//i,
            ""
          );
          // Gabungkan ke UPLOADS_BASE
          return (UPLOADS_BASE + "/" + u).replace(
            /([^:])\/{2,}/g,
            "$1/"
          );
        }

        // Jika hanya berupa nama file tanpa slash
        if (u.indexOf("/") === -1) {
          var fname = u.trim();           // Nama file
          var lower = fname.toLowerCase(); // Lowercase untuk konsisten
          return (UPLOADS_BASE + "/" + lower).replace(
            /([^:])\/{2,}/g,
            "$1/"
          );
        }

        // Jika tidak diawali slash, tambahkan
        if (u.charAt(0) !== "/") {
          u = "/" + u;
        }

        // Jika path seperti "/uploads/menu/..." tanpa "/public"
        if (/^\/?uploads\/menu\//i.test(u)) {
          u = u.replace(
            /^\/?uploads\/menu\//i,
            ""
          );
          return (UPLOADS_BASE + "/" + u).replace(
            /([^:])\/{2,}/g,
            "$1/"
          );
        }

        // Fallback: kembalikan apa adanya
        return u;
      }

      // ---------------------------------------------------------
      // Migrasi data cart lama:
      // - Jika ada jejak localhost, hapus keranjang agar aman
      // - Jika hanya path yang berubah, perbaiki image_url-nya
      // ---------------------------------------------------------
      function migrateCart() {
        var c        = getCart();  // Ambil cart
        var changed  = false;      // Flag ada perubahan path
        var clearAll = false;      // Flag perlu hapus semua

        for (var i = 0; i < c.length; i++) {
          var it  = c[i];                          // Item ke-i
          var raw = it.image_url || it.image || ""; // Path gambar mentah

          // Jika masih mengandung "localhost" → clear keranjang
          if (
            typeof raw === "string" &&
            /localhost|127\.0\.0\.1/i.test(raw)
          ) {
            clearAll = true;
            break;                  // Stop loop
          }

          var ok = normalizeImageUrl(raw); // Normalisasi path

          // Jika hasil normalisasi berbeda, update item
          if (ok && it.image_url !== ok) {
            it.image_url = ok;
            changed      = true;
          }
        }

        // Jika terdeteksi data localhost lama, hapus total keranjang
        if (clearAll) {
          localStorage.removeItem(KEY); // Buang key keranjang
          return;                       // Tidak lanjut simpan
        }

        // Jika ada perbaikan path gambar, simpan ulang
        if (changed) {
          setCart(c);
        }
      }

      // ---------------------------------------------------------
      // Render tampilan keranjang ke dalam #cartList
      // dan hitung subtotal berdasarkan item terpilih
      // ---------------------------------------------------------
      function render() {
        // Pastikan data cart sudah dimigrasi sekali
        migrateCart();

        var cart = getCart(); // Data keranjang terkini

        // Jika keranjang kosong, tampilkan pesan dan reset subtotal
        if (!cart.length) {
          $list.innerHTML =
            '<div class="empty">' +
              'Keranjang Anda kosong. ' +
              'Tambahkan menu dari katalog.' +
            '</div>';

          $subtotal.textContent = "Subtotal: Rp 0";
          selectedIds.clear(); // Bersihkan pilihan
          return;
        }

        // Bangun HTML item keranjang
        var html = "";

        for (var i = 0; i < cart.length; i++) {
          var it   = cart[i];                         // Item ke-i
          var img  = normalizeImageUrl(
                      it.image_url || it.image || ""
                    ) || PLACEHOLDER;                 // Path gambar final
          var id   = String(it.id);                   // ID item sebagai string
          var name = escapeHtml(it.name || "Menu");   // Nama aman XSS
          var priceText = rupiah(it.price || 0);      // Harga satuan format Rp

          // Cek apakah item ini sedang terpilih
          var checked = selectedIds.has(id)
            ? " checked"
            : "";

          // Tambah struktur HTML satu baris item ke string html
          html +=
            '<div class="cart-row">' +
              '<div class="cart-info">' +
                // Checkbox pilih item
                '<input ' +
                  'type="checkbox" ' +
                  'class="row-check" ' +
                  'data-id="' + escapeHtml(id) + '"' +
                  checked +
                '>' +
                // Gambar menu
                '<img ' +
                  'class="cart-thumb" ' +
                  'src="' + img + '" ' +
                  'alt="' + name + '" ' +
                  // onerror: coba src toLowerCase sekali, lalu fallback placeholder
                  'onerror="if(!this.dataset.retried){' +
                    'this.dataset.retried=1;' +
                    'this.src=this.src.toLowerCase();' +
                  '}else{' +
                    "this.src=\''" + PLACEHOLDER + "\';" +
                  '}"' +
                '>' +
                // Info teks (nama & harga)
                '<div class="info-text">' +
                  '<div class="name">' +
                    name +
                  '</div>' +
                  '<div class="price">' +
                    priceText +
                  '</div>' +
                '</div>' +
              '</div>' +
              // Kontrol qty kanan
              '<div class="qty">' +
                '<button ' +
                  'class="btn-qty" ' +
                  'data-act="dec" ' +
                  'data-idx="' + i + '"' +
                '>−</button>' +
                '<span class="qty-val">' +
                  (it.qty || 1) +
                '</span>' +
                '<button ' +
                  'class="btn-qty" ' +
                  'data-act="inc" ' +
                  'data-idx="' + i + '"' +
                '>+</button>' +
              '</div>' +
            '</div>';
        }

        // Pasang HTML yang sudah dibentuk ke dalam container
        $list.innerHTML = html;

        // Hitung subtotal hanya dari item yang terpilih
        var sub = 0;

        for (var k = 0; k < cart.length; k++) {
          var itemId = String(cart[k].id); // ID item ke-k
          if (selectedIds.has(itemId)) {
            sub += line(cart[k]);          // Tambah total baris ke subtotal
          }
        }

        // Tampilkan subtotal dalam format Rupiah
        $subtotal.textContent =
          "Subtotal: " + rupiah(sub);

        // ---------------------------------------------
        // Event handler tombol + / - kuantitas item
        // ---------------------------------------------
        $list
          .querySelectorAll("[data-act]")
          .forEach(function (btn) {
            // Set handler klik untuk setiap tombol +/-
            btn.onclick = function () {
              var act = this.dataset.act;          // "inc" atau "dec"
              var idx = parseInt(
                this.dataset.idx,
                10
              );                                   // Index item di array

              var c = getCart();                  // Ambil cart terbaru
              if (!c[idx]) return;                // Jika tidak ada item, stop

              // Tambah qty
              if (act === "inc") {
                c[idx].qty++;
              }
              // Kurangi qty
              else if (act === "dec") {
                if (c[idx].qty > 1) {
                  c[idx].qty--;                   // Kurangi qty selama > 1
                } else {
                  // Jika qty tinggal 1 dan dikurangi lagi → hapus item
                  selectedIds.delete(
                    String(c[idx].id)
                  );                               // Hapus dari set pilihan
                  c.splice(idx, 1);               // Hapus dari array cart
                }
              }

              setCart(c);                         // Simpan cart yang baru
              render();                           // Render ulang tampilan
            };
          });

        // ---------------------------------------------
        // Event handler checkbox per item
        // ---------------------------------------------
        $list
          .querySelectorAll(".row-check")
          .forEach(function (ch) {
            // Set handler ketika checkbox berubah
            ch.onchange = function () {
              var id = String(this.dataset.id || ""); // ID item saat ini

              if (this.checked) {
                selectedIds.add(id);            // Masukkan ke set terpilih
              } else {
                selectedIds.delete(id);         // Hapus dari set terpilih
              }

              // Re-hitung subtotal setelah perubahan pilihan
              var sub2  = 0;
              var cart2 = getCart();

              for (var j = 0; j < cart2.length; j++) {
                var cid = String(cart2[j].id);
                if (selectedIds.has(cid)) {
                  sub2 += line(cart2[j]);       // Tambah total baris
                }
              }

              // Update label subtotal
              $subtotal.textContent =
                "Subtotal: " + rupiah(sub2);
            };
          });
      }

      // ---------------------------------------------------------
      // Tombol "Pilih semua" / "Batalkan semua"
      // ---------------------------------------------------------
      $btnSelectAll.onclick = function () {
        var cart = getCart();         // Ambil cart sekarang

        // Jika keranjang kosong, tampilkan pesan
        if (!cart.length) {
          alert("Keranjang Anda kosong.");
          return;
        }

        // Cek apakah semua item sudah terpilih
        var allSelected = cart.every(
          function (it) {
            return selectedIds.has(
              String(it.id)
            );
          }
        );

        // Reset set pilihan
        selectedIds.clear();

        // Jika sebelumnya belum semua terpilih → sekarang pilih semua
        if (!allSelected) {
          cart.forEach(function (it) {
            selectedIds.add(
              String(it.id)
            );
          });
        }
        // Jika sebelumnya semua terpilih → clear saja (tidak perlu else)

        render(); // Render ulang tampilan & subtotal
      };

      // ---------------------------------------------------------
      // Tombol "Hapus" → hapus semua item yang sedang terpilih
      // ---------------------------------------------------------
      $btnDelete.onclick = function () {
        // Jika tidak ada item terpilih, beri pesan
        if (selectedIds.size === 0) {
          alert("Pilih item yang ingin dihapus.");
          return;
        }

        // Filter cart: ambil hanya yang tidak terpilih
        var c = getCart().filter(function (it) {
          return !selectedIds.has(
            String(it.id)
          );
        });

        setCart(c);             // Simpan cart baru
        selectedIds.clear();    // Kosongkan set pilihan
        render();               // Render ulang
      };

      // ---------------------------------------------------------
      // Tombol "Buat pesanan" → arah ke checkout
      //            hanya jika ada item terpilih
      // ---------------------------------------------------------
      $btnCheckout.onclick = function () {
        // Jika belum ada item terpilih, beri pesan
        if (selectedIds.size === 0) {
          alert("Pilih item terlebih dahulu.");
          return;
        }

        // Simpan list ID item terpilih ke localStorage
        // untuk dipakai di halaman checkout
        localStorage.setItem(
          "caffora_cart_selected",
          JSON.stringify(
            Array.from(selectedIds)
          )
        );

        // Arahkan ke halaman checkout sesuai base path
        window.location.href =
          PUBLIC_BASE + "/customer/checkout.php";
        // Contoh hasil:
        //  - di root   => /public/customer/checkout.php
        //  - di subdir => /caffora-app1/public/customer/checkout.php
      };

      // Render awal saat halaman pertama kali dibuka
      render();
    })(); // IIFE untuk menjaga scope lokal
  </script>
</body>
</html>
