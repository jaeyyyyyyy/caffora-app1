<?php
// ===============================================
// File   : public/customer/cart.php
// Fungsi : Halaman keranjang belanja pelanggan
// Akses  : Hanya untuk user dengan role "customer"
// ===============================================

// Aktifkan strict types di PHP 7+
declare(strict_types=1);

// Load guard otentikasi
require_once __DIR__.'/../../backend/auth_guard.php';

// Wajib login sebagai customer
require_login(['customer']);
?>
<!-- Deklarasi tipe dokumen HTML5 -->
<!doctype html>

<!-- Dokumen berbahasa Indonesia -->
<html lang="id">
  <head>
    <!-- Set encoding karakter ke UTF-8 -->
    <meta charset="utf-8">

    <!-- Judul tab browser -->
    <title>Keranjang — Caffora</title>

    <!-- Supaya tampilan responsif di mobile -->
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1"
    >

    <!-- ===============================
         CSS Bootstrap & Bootstrap Icons
         =============================== -->

    <!-- CSS Bootstrap 5.3.3 -->
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet"
    >

    <!-- Icon set Bootstrap Icons -->
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
      rel="stylesheet"
    >
   
    <style> 
  /* ==========================================
     Variabel warna brand Caffora
     ========================================== */
  :root {
    --yellow: #FFD54F;        /* Warna kuning utama */
    --camel: #DAA85C;         /* Warna camel */
    --brown: #4B3F36;         /* Warna coklat utama */
    --line: #e5e7eb;          /* Warna garis lembut */
    --bg: #fffdf8;            /* Background krem */
    --gold: #FFD54F;          /* Warna emas tombol */
    --gold-200: #FFE883;      /* Emas lebih muda */
  }

  * {
    font-family:              /* Font prioritas */
      "Poppins",
      system-ui,
      -apple-system,
      "Segoe UI",
      Roboto,
      Arial,
      sans-serif;
    box-sizing: border-box;   /* Hitungan termasuk border */
  }

  body {
    background: var(--bg);    /* Set warna background */
    color: var(--brown);      /* Warna teks utama */
    overflow-x: hidden;       /* Hindari scroll horizontal */
    margin: 0;                /* Hilangkan margin default */
  }

  /* ==========================================
     TOPBAR
     ========================================== */
  .topbar {
    background: #fff;                         /* Background putih */
    border-bottom: 1px solid rgba(0,0,0,.05); /* Garis bawah tipis */
    position: sticky;                         /* Nempel di atas */
    top: 0;                                   /* Posisi 0px dari atas */
    z-index: 20;                              /* Di atas konten lainnya */
  }

  .topbar .inner {
    max-width: 1200px;                        /* Lebar maksimal */
    margin: 0 auto;                           /* Tengah */
    padding: 12px 16px;                       /* Ruang dalam */
    min-height: 52px;                         /* Tinggi minimum bar */
    display: flex;                            /* Mode flex */
    align-items: center;                      /* Tengah vertikal */
    justify-content: space-between;           /* Kiri–kanan */
    gap: 12px;                                /* Jarak antar elemen */
  }

  .back-link {
    display: inline-flex;                     /* Flex inline */
    align-items: center;                      /* Tengah vertikal */
    gap: 10px;                                /* Jarak ikon-teks */
    color: var(--brown);                      /* Warna teks */
    text-decoration: none;                    /* Hilangkan underline */
    font-weight: 600;                         /* Tebal */
    font-size: 1rem;                          /* Ukuran font */
    line-height: 1.3;                         /* Tinggi baris */
  }

  .back-link .chev {
    font-size: 18px;                          /* Ukuran ikon */
    line-height: 1;                           /* Tinggi minimal */
  }

  .topbar-actions {
    display: flex;                            /* Flex kanan */
    align-items: center;                      /* Tengah */
    gap: 8px;                                 /* Jarak tombol */
    flex-shrink: 0;                           /* Tidak mengecil */
  }

  /* ==========================================
     Tombol emas
     ========================================== */
  .btn-gold,
  .btn-primary-cf {
    background-color: var(--gold);            /* Warna emas */
    color: var(--brown) !important;           /* Warna teks */
    border: 0;                                /* Tanpa border */
    border-radius: 14px;                      /* Sudut membulat */
    font-family: Arial, Helvetica, sans-serif;/* Font fallback */
    font-weight: 600;                         /* Tebal */
    font-size: .88rem;                        /* Ukuran tombol */
    padding: 10px 18px;                       /* Ruang dalam */
    display: inline-flex;                     /* Flex tombol */
    align-items: center;                      /* Tengah vertikal */
    justify-content: center;                  /* Tengah horizontal */
    gap: 8px;                                 /* Jarak icon-teks */
    white-space: nowrap;                      /* Tidak turun baris */
    box-shadow: none;                         /* Tanpa shadow */
    cursor: pointer;                          /* Cursor tangan */
  }

  /* ==========================================
     Wrapper konten utama
     ========================================== */
  .page {
    max-width: 1200px;                        /* Lebar konten */
    margin: 12px auto 40px;                   /* Margin vertikal */
    padding: 0 16px;                          /* Padding kiri–kanan */
  }

  @media (min-width: 992px) {
    .topbar .inner,
    .page {
      padding-left: 12px;                     /* Padding kiri */
      padding-right: 12px;                    /* Padding kanan */
    }
  }

  /* ==========================================
     Item keranjang
     ========================================== */
  .cart-row {
    display: flex;                            /* Flex item cart */
    align-items: center;                      /* Tengah */
    justify-content: space-between;           /* Jarak kiri-kanan */
    flex-wrap: nowrap;                        /* Tidak wrap */
    gap: 12px;                                /* Jarak elemen */
    padding: 16px 0;                          /* Padding vertikal */
    border-bottom: 1px solid rgba(0,0,0,.06); /* Garis bawah */
  }

  .cart-info {
    display: flex;                            /* Flex info produk */
    align-items: center;                      /* Tengah */
    flex: 1;                                  /* Isi ruang sisa */
    gap: 12px;                                /* Jarak antar elemen */
    min-width: 0;                             /* Boleh mengecil */
  }

  .row-check {
    width: 18px;                              /* Ukuran checkbox */
    height: 18px;                             /* Tinggi */
    border: 2px solid var(--yellow);          /* Garis kuning */
    border-radius: 4px;                       /* Sudut */
    appearance: none;                         /* Custom tampil */
    background: #fff;                         /* Putih */
    cursor: pointer;                          /* Pointer */
    position: relative;                       /* Untuk centang */
    flex-shrink: 0;                           /* Tidak mengecil */
  }

  .row-check:checked {
    background: var(--yellow);                /* Warna saat aktif */
  }

  .row-check:checked::after {
    content: "";                              /* Isi centang */
    position: absolute;                       /* Posisi absolut */
    left: 4px;                                /* Geser kiri */
    top: 0;                                   /* Posisi atas */
    width: 6px;                               /* Panjang garis */
    height: 10px;                             /* Tinggi centang */
    border: 2px solid var(--brown);          /* Warna centang */
    border-top: none;                        /* Hilangkan sisi */
    border-left: none;                       /* Hilangkan sisi */
    transform: rotate(45deg);                /* Rotasi */
  }

  .cart-thumb {
    width: 80px;                              /* Lebar gambar */
    height: 80px;                             /* Tinggi gambar */
    border-radius: 12px;                      /* Sudut membulat */
    object-fit: cover;                        /* Cover crop */
    background: #f3f3f3;                      /* Background abu */
    flex-shrink: 0;                           /* Tidak mengecil */
  }

  .info-text {
    flex: 1;                                  /* Lepas ruang */
    min-width: 0;                             /* Boleh mengecil */
  }

  .name {
    font-weight: 600;                         /* Nama tebal */
    color: #2b2b2b;                           /* Warna teks */
    font-size: 1rem;                          /* Ukuran */
    white-space: nowrap;                      /* Tidak wrap */
    overflow: hidden;                         /* Sembunyikan overflow */
    text-overflow: ellipsis;                  /* Tiga titik */
  }

  .price {
    font-size: .92rem;                        /* Ukuran harga */
    color: #777;                              /* Warna abu */
  }

  .qty {
    display: flex;                            /* Flex qty */
    align-items: center;                      /* Tengah */
    gap: 8px;                                 /* Jarak elemen */
    flex-shrink: 0;                           /* Tidak mengecil */
  }

  .btn-qty {
    width: 34px;                              /* Tombol kecil */
    height: 34px;                             /* Tinggi */
    border-radius: 10px;                      /* Sudut */
    border: 1px solid var(--line);           /* Border tipis */
    background: #fff;                         /* Putih */
    color: #2b2b2b;                          /* Warna teks */
    font-weight: 700;                        /* Tebal */
    transition:
      background .15s ease,                 /* Animasi bg */
      border-color .15s ease;               /* Animasi border */
    cursor: pointer;                         /* Tangan */
  }

  .btn-qty:hover {
    background: #fafafa;                     /* Hover lembut */
    border-color: #d8dce2;                  /* Border hover */
  }

  .qty-val {
    min-width: 28px;                         /* Lebar minimal */
    text-align: center;                      /* Tengah */
    font-weight: 600;                        /* Tebal */
  }

  /* ==========================================
     Ringkasan subtotal + tombol checkout
     ========================================== */
  .summary {
    display: flex;                            /* Flex */
    align-items: center;                      /* Tengah vertikal */
    justify-content: space-between;           /* Pisah kiri-kanan */
    flex-wrap: wrap;                          /* Boleh wrap */
    gap: 12px;                                /* Jarak */
    padding: 20px 0;                          /* Padding Y */
    margin-top: 8px;                          /* Jarak atas */
    border-top: 2px dashed rgba(0,0,0,.08);   /* Garis atas */
  }

  .subtotal {
    font-size: 1rem;                          /* Ukuran */
    font-weight: 600;                         /* Tebal */
  }

  /* ==========================================
     Responsive (mobile)
     ========================================== */
  @media (max-width: 768px) {
    .topbar .inner,
    .page {
      max-width: 100%;                        /* Full */
      padding: 12px 16px;                     /* Padding */
    }

    .page {
      margin: 0 auto 40px;                    /* Jarak bawah */
    }

    .cart-row {
      gap: 10px;                              /* Jarak kecil */
    }

    .cart-thumb {
      width: 60px;                            /* Thumb kecil */
      height: 60px;
    }

    .btn-qty {
      width: 30px;                            /* Tombol kecil */
      height: 30px;
      border-radius: 8px;
    }

    .summary {
      flex-direction: column;                 /* Kolom */
      align-items: center;                    /* Tengah */
      text-align: center;                     /* Teks tengah */
    }

    .summary .btn-primary-cf {
      width: auto;                            /* Otomatis */
      max-width: 240px;                       /* Maksimal */
    }
  }

  .empty {
    padding: 32px 4px;                        /* Ruang */
    text-align: center;                       /* Tengah */
    color: #6b7280;                           /* Abu */
    font-size: .95rem;                        /* Ukuran */
  }

  /* ==========================================
     SIMPLE MODAL ALERT
     ========================================== */
  .cf-alert-backdrop {
    position: fixed;                          /* Tetap di layar */
    inset: 0;                                 /* Full layar */
    background: rgba(0,0,0,.4);               /* Hitam transparan */
    display: flex;                            /* Flex */
    align-items: center;                      /* Tengah */
    justify-content: center;                  /* Tengah */
    padding: 16px;                            /* Ruang */
    z-index: 50;                              /* Di atas */
  }

  .cf-alert-card {
    max-width: 360px;                         /* Lebar card */
    width: 100%;                              /* Full */
    background: #fff;                         /* Putih */
    border-radius: 18px;                      /* Bundar */
    box-shadow: 0 18px 40px rgba(15,23,42,.18); /* Shadow */
    padding: 20px 20px 16px;                  /* Ruang */
  }

  .cf-alert-title {
    font-weight: 600;                         /* Tebal */
    font-size: 1rem;                          /* Ukuran */
    margin-bottom: 8px;                       /* Jarak */
    color: #111827;                           /* Hitam */
  }

  .cf-alert-message {
    font-size: .95rem;                        /* Ukuran */
    color: #4b5563;                           /* Abu */
    margin-bottom: 16px;                      /* Jarak */
  }

  .cf-alert-footer {
    display: flex;                            /* Flex */
    justify-content: flex-end;                /* Kanan */
  }

  .cf-alert-btn {
    align-self: flex-end;                     /* Sisi kanan */
    margin-top: 6px;                          /* Jarak */
    border: 0;                                /* Tanpa border */
    border-radius: 999px;                     /* Pill */
    padding: 8px 18px;                        /* Ruang */
    background: var(--gold);                  /* Emas */
    color: var(--brown);                      /* Coklat */
    font-weight: 600;                         /* Tebal */
    font-size: 0.9rem;                        /* Ukuran */
    cursor: pointer;                          /* Tangan */
  }

  @keyframes spin {
    from { transform: rotate(0); }            /* Awal 0° */
    to   { transform: rotate(360deg); }       /* Muter penuh */
  }
</style>

   
  </head>

  <body>
    <!-- ==========================================
         TOPBAR NAVIGATION
         ========================================== -->
    <div class="topbar">
      <div class="inner">
        <a
          class="back-link"
          href="./index.php"
        >
          <i class="bi bi-arrow-left chev"></i>
          <span>Lanjut Belanja</span>
        </a>

        <div class="topbar-actions">
          <button
            id="btnSelectAll"
            class="btn-gold"
          >
            Pilih semua
          </button>

          <button
            id="btnDeleteSelected"
            class="btn-gold"
          >
            Hapus
          </button>
        </div>
      </div>
    </div>

    <!-- Konten utama -->
    <main class="page">
      <div id="cartList"></div>

      <div class="summary">
        <div
          class="subtotal"
          id="subtotal"
        >
          Subtotal: Rp 0
        </div>

        <button
          id="btnCheckout"
          class="btn-primary-cf"
        >
          Buat pesanan
        </button>
      </div>
    </main>

    <!-- ==========================================
         MODAL ALERT (bukan alert browser)
         ========================================== -->
    <div
      id="cartAlert"
      class="cf-alert-backdrop d-none"
    >
      <div class="cf-alert-card">
        <div class="cf-alert-title">Caffora</div>
        <div
          id="cartAlertMessage"
          class="cf-alert-message"
        ></div>
        <div class="cf-alert-footer">
          <button
            type="button"
            id="cartAlertOk"
            class="btn-primary-cf cf-alert-btn"
          >
            Oke
          </button>
        </div>
      </div>
    </div>

    <!-- ==========================================
     SCRIPT: Logika keranjang (localStorage)
     ========================================== -->
<script>
  (function () {
    // =========================================================
    // BASE PATH OTOMATIS
    // =========================================================
    var PUBLIC_SPLIT = "/public/";
    var pathname     = window.location.pathname;
    var idx          = pathname.indexOf(PUBLIC_SPLIT);

    var PROJECT_BASE = (idx > -1)
      ? pathname.slice(0, idx)
      : "";

    var PUBLIC_BASE  = PROJECT_BASE + "/public";
    var UPLOADS_BASE = PUBLIC_BASE + "/uploads/menu";
    var PLACEHOLDER  = PUBLIC_BASE + "/assets/img/placeholder.jpg";

    var KEY = "caffora_cart";

    var $list         = document.getElementById("cartList");
    var $subtotal     = document.getElementById("subtotal");
    var $btnSelectAll = document.getElementById("btnSelectAll");
    var $btnDelete    = document.getElementById("btnDeleteSelected");
    var $btnCheckout  = document.getElementById("btnCheckout");

    // elemen modal alert
    var $alertBackdrop = document.getElementById("cartAlert");
    var $alertMsg      = document.getElementById("cartAlertMessage");
    var $alertOk       = document.getElementById("cartAlertOk");

    var selectedIds = new Set();

    // ---------------------------------------------------------
    // helper: tampilkan alert sheet di dalam layar
    // ---------------------------------------------------------
    function showCartAlert(message) {
      if (!$alertBackdrop || !$alertMsg) {
        window.alert(message);
        return;
      }
      $alertMsg.textContent = message;
      $alertBackdrop.classList.remove("d-none");
    }

    if ($alertOk) {
      $alertOk.onclick = function () {
        $alertBackdrop.classList.add("d-none");
      };
    }

    if ($alertBackdrop) {
      $alertBackdrop.addEventListener("click", function (e) {
        if (e.target === $alertBackdrop) {
          $alertBackdrop.classList.add("d-none");
        }
      });
    }

    // ---------------------------------------------------------
    // util: format angka ke Rupiah
    // ---------------------------------------------------------
    function rupiah(n) {
      return "Rp " + Number(n || 0).toLocaleString("id-ID");
    }

    // ---------------------------------------------------------
    // util: escape HTML untuk mencegah XSS
    // ---------------------------------------------------------
    function escapeHtml(s) {
      return String(s || "")
        .replace(
          /[&<>\"']/g,
          function (m) {
            return {
              "&": "&amp;",
              "<": "&lt;",
              ">": "&gt;",
              '"': "&quot;",
              "'": "&#39;"
            }[m];
          }
        );
    }

    // ---------------------------------------------------------
    // util: baca cart dari localStorage
    // ---------------------------------------------------------
    function getCart() {
      try {
        return JSON.parse(localStorage.getItem(KEY) || "[]");
      } catch (e) {
        return [];
      }
    }

    // ---------------------------------------------------------
    // util: simpan cart ke localStorage
    // ---------------------------------------------------------
    function setCart(c) {
      localStorage.setItem(KEY, JSON.stringify(c));
    }

    // ---------------------------------------------------------
    // util: total harga per item (price * qty)
    // ---------------------------------------------------------
    function line(it) {
      var price = Number(it.price) || 0;
      var qty   = Number(it.qty)   || 0;
      return price * qty;
    }

    // ---------------------------------------------------------
    // util: normalisasi URL gambar agar cocok di hosting
    // ---------------------------------------------------------
    function normalizeImageUrl(raw) {
      if (!raw) {
        return "";
      }

      var u = String(raw)
        .trim()
        .replace(/\\/g, "/");

      if (/^https?:\/\//i.test(u)) {
        try {
          var urlObj = new URL(u);
          u = urlObj.pathname || "";
        } catch (e) {
          // abaikan, tetap pakai u apa adanya
        }
      }

      if (u.indexOf(PUBLIC_BASE + "/uploads/menu/") === 0) {
        return u;
      }

      if (u.indexOf("/public/uploads/menu/") === 0) {
        return PROJECT_BASE + u;
      }

      if (u.indexOf("public/uploads/menu/") === 0) {
        u = u.replace(/^\/?public\/uploads\/menu\//i, "");

        return (UPLOADS_BASE + "/" + u)
          .replace(/([^:])\/{2,}/g, "$1/");
      }

      if (/uploads\/menu\//i.test(u)) {
        u = u.replace(/^.*uploads\/menu\//i, "");

        return (UPLOADS_BASE + "/" + u)
          .replace(/([^:])\/{2,}/g, "$1/");
      }

      if (u.indexOf("/") === -1) {
        var fname = u.trim();
        var lower = fname.toLowerCase();

        return (UPLOADS_BASE + "/" + lower)
          .replace(/([^:])\/{2,}/g, "$1/");
      }

      if (u.charAt(0) !== "/") {
        u = "/" + u;
      }

      if (/^\/?uploads\/menu\//i.test(u)) {
        u = u.replace(/^\/?uploads\/menu\//i, "");

        return (UPLOADS_BASE + "/" + u)
          .replace(/([^:])\/{2,}/g, "$1/");
      }

      return u;
    }

    // ---------------------------------------------------------
    // Migrasi cart lama: bersihkan data localhost & perbaiki path
    // ---------------------------------------------------------
    function migrateCart() {
      var c        = getCart();
      var changed  = false;
      var clearAll = false;

      for (var i = 0; i < c.length; i++) {
        var it  = c[i];
        var raw = it.image_url || it.image || "";

        if (
          typeof raw === "string" &&
          /localhost|127\.0\.0\.1/i.test(raw)
        ) {
          clearAll = true;
          break;
        }

        var ok = normalizeImageUrl(raw);

        if (ok && it.image_url !== ok) {
          it.image_url = ok;
          changed = true;
        }
      }

      if (clearAll) {
        localStorage.removeItem(KEY);
        return;
      }

      if (changed) {
        setCart(c);
      }
    }

    // ---------------------------------------------------------
    // util: hitung & update subtotal (hilangkan duplikat)
    // ---------------------------------------------------------
    function updateSubtotal(cart) {
      var c = Array.isArray(cart) ? cart : getCart();
      var sub = 0;

      for (var i = 0; i < c.length; i++) {
        var id = String(c[i].id);
        if (selectedIds.has(id)) {
          sub += line(c[i]);
        }
      }

      $subtotal.textContent = "Subtotal: " + rupiah(sub);
    }

    // ---------------------------------------------------------
    // Render tampilan cart + subtotal
    // ---------------------------------------------------------
    function render() {
      migrateCart();
      var cart = getCart();

      if (!cart.length) {
        $list.innerHTML =
          '<div class="empty">' +
            'Keranjang Anda kosong. ' +
            'Tambahkan menu dari katalog.' +
          '</div>';

        $subtotal.textContent = "Subtotal: Rp 0";
        selectedIds.clear();
        return;
      }

      var html = "";

      for (var i = 0; i < cart.length; i++) {
        var it = cart[i];

        var img = normalizeImageUrl(
          it.image_url || it.image || ""
        ) || PLACEHOLDER;

        var id        = String(it.id);
        var name      = escapeHtml(it.name || "Menu");
        var priceText = rupiah(it.price || 0);
        var checked   = selectedIds.has(id) ? " checked" : "";

        html +=
          '<div class="cart-row">' +
            '<div class="cart-info">' +
              '<input ' +
                'type="checkbox" ' +
                'class="row-check" ' +
                'data-id="' + escapeHtml(id) + '" ' +
                checked +
              '>' +
              '<img ' +
                'class="cart-thumb" ' +
                'src="' + img + '" ' +
                'alt="' + name + '" ' +
                'onerror="if(!this.dataset.retried){' +
                  'this.dataset.retried=1;' +
                  'this.src=this.src.toLowerCase();' +
                '}else{' +
                  "this.src='" + PLACEHOLDER + "';" +
                '}"' +
              '>' +
              '<div class="info-text">' +
                '<div class="name">' +
                  name +
                '</div>' +
                '<div class="price">' +
                  priceText +
                '</div>' +
              '</div>' +
            '</div>' +
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

      $list.innerHTML = html;

      // sebelumnya: loop manual subtotal → sekarang pakai helper
      updateSubtotal(cart);

      // -------------------------------------------------------
      // Handler tombol + / - kuantitas per item
      // -------------------------------------------------------
      $list
        .querySelectorAll("[data-act]")
        .forEach(function (btn) {
          btn.onclick = function () {
            var act = this.dataset.act;
            var idx = parseInt(this.dataset.idx, 10);

            var c = getCart();

            if (!c[idx]) {
              return;
            }

            if (act === "inc") {
              c[idx].qty++;
            } else if (act === "dec") {
              if (c[idx].qty > 1) {
                c[idx].qty--;
              } else {
                selectedIds.delete(String(c[idx].id));
                c.splice(idx, 1);
              }
            }

            setCart(c);
            render();
          };
        });

      // -------------------------------------------------------
      // Handler checkbox per item (pilih / batal pilih)
      // -------------------------------------------------------
      $list
        .querySelectorAll(".row-check")
        .forEach(function (ch) {
          ch.onchange = function () {
            var id = String(this.dataset.id || "");

            if (this.checked) {
              selectedIds.add(id);
            } else {
              selectedIds.delete(id);
            }

            // sebelumnya subtotal dihitung ulang manual di sini
            // sekarang cukup:
            updateSubtotal();
          };
        });
    }

    // ---------------------------------------------------------
    // Tombol "Pilih semua" / "Batalkan semua"
    // ---------------------------------------------------------
    $btnSelectAll.onclick = function () {
      var cart = getCart();

      if (!cart.length) {
        showCartAlert("Keranjang Anda kosong.");
        return;
      }

      var allSelected = cart.every(function (it) {
        return selectedIds.has(String(it.id));
      });

      selectedIds.clear();

      if (!allSelected) {
        cart.forEach(function (it) {
          selectedIds.add(String(it.id));
        });
      }

      render();
    };

    // ---------------------------------------------------------
    // Tombol "Hapus" → hapus semua item terpilih
    // ---------------------------------------------------------
    $btnDelete.onclick = function () {
      if (selectedIds.size === 0) {
        showCartAlert("Pilih item yang ingin dihapus.");
        return;
      }

      var c = getCart()
        .filter(function (it) {
          return !selectedIds.has(String(it.id));
        });

      setCart(c);
      selectedIds.clear();
      render();
    };

    // ---------------------------------------------------------
    // Tombol "Buat pesanan"
    // ---------------------------------------------------------
    $btnCheckout.onclick = function () {
      if (selectedIds.size === 0) {
        showCartAlert("Pilih item terlebih dahulu.");
        return;
      }

      localStorage.setItem(
        "caffora_cart_selected",
        JSON.stringify(Array.from(selectedIds))
      );

      window.location.href =
        PUBLIC_BASE + "/customer/checkout.php";
    };

    // Render awal saat halaman pertama kali dibuka
    render();
  })();
</script>
  </body>
    </html>


       