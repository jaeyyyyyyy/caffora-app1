<?php
// public/customer/cart.php
declare(strict_types=1);

require_once __DIR__ . '/../../backend/auth_guard.php';
require_login(['customer']); // wajib login
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Keranjang — Caffora</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root {
      --yellow: #FFD54F;
      --camel: #DAA85C;
      --brown: #4B3F36;
      --line: #e5e7eb;
      --bg: #fffdf8;
      --gold: #FFD54F;
      --gold-200: #FFE883;
    }

    * {
      font-family: "Poppins", system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
      box-sizing: border-box;
    }

    body {
      background: var(--bg);
      color: var(--brown);
      overflow-x: hidden;
      margin: 0;
    }

    .topbar{
      background:#fff;
      border-bottom:1px solid rgba(0,0,0,.05);
      position:sticky;
      top:0;
      z-index:20;
    }

    .topbar .inner{
      max-width:1200px;
      margin:0 auto;
      /* rapat ala admin */
      padding:12px 16px;
      min-height:52px;
      display:flex;
      align-items:center;
      gap:12px;
      justify-content:space-between;
    }

    .back-link{
      display:inline-flex;
      align-items:center;
      gap:10px;
      color:var(--brown);
      text-decoration:none;
      font-weight:600;
      font-size:1rem;
      line-height:1.3;
    }
    .back-link .chev{
      font-size:18px;
      line-height:1;
    }

    .topbar-actions{
      display:flex;
      align-items:center;
      gap:8px;
      flex-shrink:0;
    }

    /* Tombol emas */
    .btn-gold,
    .btn-primary-cf {
     background-color: var(--gold);
  color: var(--brown) !important;
  border: 0;
  border-radius: 14px;
  font-family: Arial, Helvetica, sans-serif; /* ← ini yang bikin kaya versi pertama */
  font-weight: 600;
  font-size: .88rem;
  padding: 10px 18px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  white-space: nowrap;
  box-shadow: none;
} 
    

    /* === konten utama === */
    .page{
      max-width:1200px;
      margin:12px auto 40px;
      /* rapat ala admin */
      padding:0 16px;
    }

    /* layar super lebar – tetap rapat */
    @media (min-width: 1400px){
      .topbar .inner, .page{
        padding-left:14px;
        padding-right:14px;
      }
    }

    .cart-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 16px 0;
      border-bottom: 1px solid rgba(0,0,0,.06);
      gap: 12px;
      flex-wrap: nowrap;
    }

    .cart-info {
      display: flex;
      align-items: center;
      flex: 1;
      gap: 12px;
      min-width: 0;
    }

    .row-check {
      width: 18px;
      height: 18px;
      border: 2px solid var(--yellow);
      border-radius: 4px;
      appearance: none;
      background: #fff;
      cursor: pointer;
      position: relative;
      flex-shrink: 0;
    }

    .row-check:checked { background: var(--yellow); }
    .row-check:checked::after {
      content: "";
      position: absolute;
      left: 4px;
      top: 0;
      width: 6px;
      height: 10px;
      border: 2px solid var(--brown);
      border-top: none;
      border-left: none;
      transform: rotate(45deg);
    }

    .cart-thumb {
      width: 80px;
      height: 80px;
      border-radius: 12px;
      object-fit: cover;
      background: #f3f3f3;
      flex-shrink: 0;
    }

    .info-text { flex: 1; min-width: 0; }
    .name { font-weight: 600; color: #2b2b2b; font-size: 1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .price { font-size: .92rem; color: #777; }

    .qty { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
    .btn-qty {
      width: 34px; height: 34px; border-radius: 10px;
      border: 1px solid var(--line); background: #fff; color: #2b2b2b; font-weight: 700;
      transition: background .15s ease, border-color .15s ease; cursor: pointer;
    }
    .btn-qty:hover { background: #fafafa; border-color: #d8dce2; }
    .qty-val { min-width: 28px; text-align: center; font-weight: 600; }

    .summary {
      display: flex; align-items: center; justify-content: space-between;
      gap: 12px; padding: 20px 0; border-top: 2px dashed rgba(0,0,0,.08); margin-top: 8px; flex-wrap: wrap;
    }
    .subtotal { font-size: 1rem; font-weight: 600; }

    @media (max-width: 768px) {
      .topbar .inner, .page { max-width: 100%; padding: 12px 16px; }
      .page{ margin: 0 auto 40px; }
      .cart-row { gap: 10px; }
      .cart-thumb { width: 60px; height: 60px; }
      .btn-qty { width: 30px; height: 30px; border-radius: 8px; }
      .summary { flex-direction: column; align-items: center; text-align: center; }
      .summary .btn-primary-cf { width: auto; max-width: 240px; }
    }
  </style>
</head>

<body>
  <!-- TOPBAR -->
  <div class="topbar">
    <div class="inner">
      <a class="back-link" href="./index.php">
        <i class="bi bi-arrow-left chev"></i>
        <span>Lanjut Belanja</span>
      </a>
      <div class="topbar-actions">
        <button id="btnSelectAll" class="btn-gold">Pilih semua</button>
        <button id="btnDeleteSelected" class="btn-gold">Hapus</button>
      </div>
    </div>
  </div>

  <!-- Catatan: data-* tidak dipakai lagi; base path sekarang auto dari URL -->
  <main class="page">
    <div id="cartList"></div>

    <div class="summary">
      <div class="subtotal" id="subtotal">Subtotal: Rp 0</div>
      <button id="btnCheckout" class="btn-primary-cf">Buat pesanan </button>
    </div>
  </main>

<script>
(function () {
  // ========= BASE PATH OTOMATIS (UNTUK HOSTING / SUBFOLDER) =========
  var PUBLIC_SPLIT = "/public/";
  var pathname = window.location.pathname;
  var idx = pathname.indexOf(PUBLIC_SPLIT);
  var PROJECT_BASE = idx > -1 ? pathname.slice(0, idx) : ""; // "" di root, "/caffora-app1" kalau di subfolder
  var PUBLIC_BASE = PROJECT_BASE + "/public";
  var UPLOADS_BASE = PUBLIC_BASE + "/uploads/menu";
  var PLACEHOLDER = PUBLIC_BASE + "/assets/img/placeholder.jpg";

  var KEY = "caffora_cart";
  var $list = document.getElementById("cartList");
  var $subtotal = document.getElementById("subtotal");
  var $btnSelectAll = document.getElementById("btnSelectAll");
  var $btnDelete = document.getElementById("btnDeleteSelected");
  var $btnCheckout = document.getElementById("btnCheckout");
  var selectedIds = new Set();

  function rupiah(n) {
    return "Rp " + Number(n || 0).toLocaleString("id-ID");
  }

  function escapeHtml(s) {
    return String(s || "").replace(/[&<>\"']/g, function (m) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[m];
    });
  }

  function getCart() {
    try {
      return JSON.parse(localStorage.getItem(KEY) || "[]");
    } catch (e) {
      return [];
    }
  }

  function setCart(c) {
    localStorage.setItem(KEY, JSON.stringify(c));
  }

  function line(it) {
    return (Number(it.price) || 0) * (Number(it.qty) || 0);
  }

  // ====== NORMALISASI URL GAMBAR (SUPAYA COCOK DI HOSTING) ======
  function normalizeImageUrl(raw) {
    if (!raw) return "";
    var u = String(raw).trim().replace(/\\/g, "/");

    // kalau ada full URL (misal http://localhost/public/uploads/menu/..) -> ambil path-nya saja
    if (/^https?:\/\//i.test(u)) {
      try {
        var urlObj = new URL(u);
        u = urlObj.pathname || "";
      } catch (e) {
        // kalau gagal parse, tetap pakai u apa adanya
      }
    }

    // sudah dalam bentuk /public/uploads/menu/...
    if (u.indexOf(PUBLIC_BASE + "/uploads/menu/") === 0) return u;
    if (u.indexOf("/public/uploads/menu/") === 0) return PROJECT_BASE + u;
    if (u.indexOf("public/uploads/menu/") === 0) {
      u = u.replace(/^\/?public\/uploads\/menu\//i, "");
      return (UPLOADS_BASE + "/" + u).replace(/([^:])\/{2,}/g, "$1/");
    }

    // bentuk lain yang mengandung uploads/menu di mana pun
    if (/uploads\/menu\//i.test(u)) {
      u = u.replace(/^.*uploads\/menu\//i, ""); // buang prefix apa pun sebelum uploads/menu
      return (UPLOADS_BASE + "/" + u).replace(/([^:])\/{2,}/g, "$1/");
    }

    // hanya nama file saja (tanpa slash)
    if (u.indexOf("/") === -1) {
      var fname = u.trim();
      var lower = fname.toLowerCase();
      return (UPLOADS_BASE + "/" + lower).replace(/([^:])\/{2,}/g, "$1/");
    }

    // path lain: pastikan ada slash di depan
    if (u.charAt(0) !== "/") u = "/" + u;

    // kalau mulai dari /uploads/menu/... tanpa /public
    if (/^\/?uploads\/menu\//i.test(u)) {
      u = u.replace(/^\/?uploads\/menu\//i, "");
      return (UPLOADS_BASE + "/" + u).replace(/([^:])\/{2,}/g, "$1/");
    }

    // fallback terakhir: pakai apa adanya (kemungkinan sudah /public/...)
    return u;
  }

  // ====== MIGRASI DATA CART LAMA (DARI LOCALHOST → HOSTING) ======
  function migrateCart() {
    var c = getCart(),
      changed = false,
      clearAll = false;

    for (var i = 0; i < c.length; i++) {
      var it = c[i];
      var raw = it.image_url || it.image || "";

      // kalau ada jejak localhost di url → clear satu keranjang lama, biar tidak error di hosting
      if (typeof raw === "string" && /localhost|127\.0\.0\.1/i.test(raw)) {
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
    if (changed) setCart(c);
  }

  function render() {
    migrateCart();
    var cart = getCart();
    if (!cart.length) {
      $list.innerHTML =
        '<div class="empty">Keranjang Anda kosong. Tambahkan menu dari katalog.</div>';
      $subtotal.textContent = "Subtotal: Rp 0";
      selectedIds.clear();
      return;
    }

    var html = "";
    for (var i = 0; i < cart.length; i++) {
      var it = cart[i];
      var img = normalizeImageUrl(it.image_url || it.image || "") || PLACEHOLDER;
      var checked = selectedIds.has(String(it.id)) ? " checked" : "";

      html +=
        '<div class="cart-row">' +
        '<div class="cart-info">' +
        '<input type="checkbox" class="row-check" data-id="' +
        escapeHtml(String(it.id)) +
        '"' +
        checked +
        ">" +
        '<img class="cart-thumb" src="' +
        img +
        '" alt="' +
        escapeHtml(it.name || "Menu") +
        '" ' +
        // onerror: coba sekali lagi pakai lowercase, baru pakai placeholder
        'onerror="if(!this.dataset.retried){this.dataset.retried=1;this.src=this.src.toLowerCase();}else{this.src=\'' +
        PLACEHOLDER +
        '\';}">' +
        '<div class="info-text">' +
        '<div class="name">' +
        escapeHtml(it.name || "Menu") +
        "</div>" +
        '<div class="price">' +
        rupiah(it.price || 0) +
        "</div>" +
        "</div>" +
        "</div>" +
        '<div class="qty">' +
        '<button class="btn-qty" data-act="dec" data-idx="' +
        i +
        '">−</button>' +
        '<span class="qty-val">' +
        (it.qty || 1) +
        "</span>" +
        '<button class="btn-qty" data-act="inc" data-idx="' +
        i +
        '">+</button>' +
        "</div>" +
        "</div>";
    }
    $list.innerHTML = html;

    // hitung subtotal berdasarkan item terpilih
    var sub = 0;
    for (var k = 0; k < cart.length; k++) {
      if (selectedIds.has(String(cart[k].id))) {
        sub += line(cart[k]);
      }
    }
    $subtotal.textContent = "Subtotal: " + rupiah(sub);

    // handler qty
    $list.querySelectorAll("[data-act]").forEach(function (btn) {
      btn.onclick = function () {
        var act = this.dataset.act;
        var idx = parseInt(this.dataset.idx, 10);
        var c = getCart();
        if (!c[idx]) return;

        if (act === "inc") c[idx].qty++;
        else if (act === "dec") {
          if (c[idx].qty > 1) c[idx].qty--;
          else {
            selectedIds.delete(String(c[idx].id));
            c.splice(idx, 1);
          }
        }
        setCart(c);
        render();
      };
    });

    // handler checkbox item
    $list.querySelectorAll(".row-check").forEach(function (ch) {
      ch.onchange = function () {
        var id = String(this.dataset.id || "");
        if (this.checked) selectedIds.add(id);
        else selectedIds.delete(id);

        var sub2 = 0,
          cart2 = getCart();
        for (var j = 0; j < cart2.length; j++) {
          if (selectedIds.has(String(cart2[j].id))) sub2 += line(cart2[j]);
        }
        $subtotal.textContent = "Subtotal: " + rupiah(sub2);
      };
    });
  }

  // === PILIH SEMUA ===
  $btnSelectAll.onclick = function () {
    var cart = getCart();
    if (!cart.length) {
      alert("Keranjang Anda kosong.");
      return;
    }

    // cek apakah semua sudah terpilih
    var allSelected = cart.every(function (it) {
      return selectedIds.has(String(it.id));
    });

    selectedIds.clear();
    // jika belum semua terpilih, pilih semua
    if (!allSelected) {
      cart.forEach(function (it) {
        selectedIds.add(String(it.id));
      });
    }
    // kalau tadinya semua sudah terpilih, tombol jadi unselect all
    render();
  };

  // === HAPUS YANG TERPILIH ===
  $btnDelete.onclick = function () {
    if (selectedIds.size === 0) {
      alert("Pilih item yang ingin dihapus.");
      return;
    }
    var c = getCart().filter(function (it) {
      return !selectedIds.has(String(it.id));
    });
    setCart(c);
    selectedIds.clear();
    render();
  };

  // === ARAHKAN KE CHECKOUT DENGAN BASE PATH OTOMATIS ===
  $btnCheckout.onclick = function () {
    if (selectedIds.size === 0) {
      alert("Pilih item terlebih dahulu.");
      return;
    }
    localStorage.setItem(
      "caffora_cart_selected",
      JSON.stringify(Array.from(selectedIds))
    );
    window.location.href = PUBLIC_BASE + "/customer/checkout.php";
    // contoh hasil:
    //  - di root  => /public/customer/checkout.php
    //  - di subdir=> /caffora-app1/public/customer/checkout.php
  };

  render();
})();
</script>

</body>
</html>
