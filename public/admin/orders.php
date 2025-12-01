<?php
// public/admin/orders.php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../../backend/auth_guard.php';
require_login(['admin']); // ADMIN SAJA
require_once __DIR__ . '/../../backend/config.php';

$adminName = $_SESSION['user_name'] ?? 'Admin';
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Orders — Admin Panel</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://code.iconify.design/2/2.2.1/iconify.min.js"></script>

  <style>
    :root{
      --gold:#ffd54f;
      --gold-soft:#f4d67a;
      --brown:#4B3F36;
      --ink:#111827;
      --radius:18px;
      --sidebar-w:320px;
    }

    body{
      background:#FAFAFA;
      color:var(--ink);
      font-family:Inter,system-ui,Segoe UI,Roboto,Arial;
    }

    /* Sidebar */
    .sidebar{
      position:fixed;
      left:-320px;
      top:0;
      bottom:0;
      width:var(--sidebar-w);
      background:#fff;
      border-right:1px solid rgba(0,0,0,.05);
      transition:left .25s ease;
      z-index:1050;
      padding:16px 18px;
      overflow-y:auto;
    }
    .sidebar.show{ left:0; }

    .sidebar-head{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:10px;
      margin-bottom:10px;
    }
    .sidebar-inner-toggle,
    .sidebar-close-btn{
      background:transparent;
      border:0;
      width:40px;
      height:36px;
      display:grid;
      place-items:center;
    }
    .hamb-icon{
      width:24px;
      height:20px;
      display:flex;
      flex-direction:column;
      justify-content:space-between;
      gap:4px;
    }
    .hamb-icon span{
      height:2px;
      background:var(--brown);
      border-radius:99px;
    }

    .sidebar .nav-link{
      display:flex;
      align-items:center;
      gap:12px;
      padding:12px 14px;
      border-radius:16px;
      font-weight:600;
      color:#111;
      text-decoration:none;
      background:transparent;
      user-select:none;
    }
    .sidebar .nav-link:hover{
      background:rgba(255,213,79,0.25);
    }
    .sidebar hr{
      border-color:rgba(0,0,0,.05);
      opacity:1;
    }

    .backdrop-mobile{ display:none; }
    .backdrop-mobile.active{
      display:block;
      position:fixed;
      inset:0;
      background:rgba(0,0,0,.35);
      z-index:1040;
    }

    .content{
      margin-left:0;
      padding:16px 14px 40px;
    }

    .topbar{
      display:flex;
      align-items:center;
      gap:12px;
      margin-bottom:16px;
    }

    .btn-menu{
      background:transparent;
      border:0;
      width:40px;
      height:38px;
      display:grid;
      place-items:center;
    }

    .search-box{
      position:relative;
      flex:1 1 auto;
      min-width:0;
    }
    .search-input{
      height:46px;
      width:100%;
      border-radius:9999px;
      padding-left:16px;
      padding-right:44px;
      border:1px solid #e5e7eb;
      background:#fff;
      outline:none;
    }
    .search-input:focus{
      border-color:var(--gold-soft);
    }
    .search-icon{
      position:absolute;
      right:16px;
      top:50%;
      transform:translateY(-50%);
      font-size:1.1rem;
      color:var(--brown);
      cursor:pointer;
    }

    .top-actions{
      display:flex;
      gap:14px;
    }
    .icon-btn{
      width:38px;
      height:38px;
      border-radius:999px;
      display:flex;
      align-items:center;
      justify-content:center;
      color:var(--brown);
      text-decoration:none;
      position:relative;
    }
    .notif-dot{
      position:absolute;
      top:3px;
      right:3px;
      width:8px;
      height:8px;
      border-radius:999px;
      background:#4b3f36;
      box-shadow:0 0 0 1.5px #fff;
    }
    .d-none{ display:none !important; }

    .cardx{
      background:#fff;
      border:1px solid #f7d78d;
      border-radius:var(--radius);
      padding:18px 22px;
    }

    /* ===== Tabel ===== */
    .table{
      margin-bottom:0;
    }
    .table thead th,
    .table td{
      vertical-align:middle;
      white-space:nowrap;
      text-align:center;
      padding:0.8rem 1.1rem;
    }
    .table thead th{
      background:#fffbe6;
      font-weight:600;
    }

    .col-invoice{ min-width:170px; text-align:left; }
    .col-name   { min-width:120px; text-align:left; }
    .col-total  { min-width:130px; }
    .col-status { min-width:130px; }
    .col-method { min-width:125px; }
    .col-actions{ min-width:260px; }

    .col-total{
      font-variant-numeric:tabular-nums;
    }

    /* Aksi: 2 baris (Mulai+Lunas, Batal+Struk) */
    .actions-cell{
      display:flex;
      flex-direction:column;
      align-items:center;
      gap:4px;
    }
    .actions-row{
      display:flex;
      flex-wrap:wrap;
      justify-content:center;
      gap:6px;
    }
    .actions-row .btn{
      border-radius:12px;
      padding:.28rem .9rem;
      font-size:.8rem;
    }
    .actions-row a.btn{
      display:inline-flex;
      align-items:center;
      gap:4px;
    }

    @media (min-width:992px){
      .content{
        padding:20px 26px 50px;
      }
      .search-box{ max-width:1100px; }
    }

    /* ===== Modal konfirmasi batal ala Caffora ===== */
    #modalCancel .modal-dialog{
      max-width:420px;
    }
    #modalCancel .modal-content{
      border-radius:24px;
      border:none;
      box-shadow:0 20px 60px rgba(15,23,42,.35);
      padding:4px 2px 6px;
    }
    #modalCancel .modal-header{
      border-bottom:0;
      padding:18px 22px 6px;
    }
    #modalCancel .modal-title{
      font-weight:700;
      color:var(--brown);
    }
    #modalCancel .modal-body{
      padding:6px 22px 18px;
      font-size:.95rem;
    }
    #modalCancel .modal-footer{
      border-top:0;
      padding:4px 22px 18px;
      gap:10px;
    }
    #modalCancel .btn{
      border-radius:999px;
      font-weight:600;
      padding:.48rem 1.3rem;
    }
    #modalCancel .btn-light{
      background:#fff7e0;
      border-color:#fff7e0;
      color:var(--brown);
    }
    #modalCancel .btn-light:hover{
      background:#ffefc2;
      border-color:#ffefc2;
    }
    #modalCancel .btn-danger{
      background:#ef4444;
      border-color:#ef4444;
    }
    #modalCancel .btn-danger:hover{
      background:#dc2626;
      border-color:#dc2626;
    }
    .modal-backdrop.show{
      background:rgba(15,23,42,.55);
    }
  </style>
</head>
<body>

<div id="backdrop" class="backdrop-mobile"></div>

<!-- sidebar -->
<aside class="sidebar" id="sideNav">
  <div class="sidebar-head">
    <button class="sidebar-inner-toggle" id="toggleSidebarInside" aria-label="Tutup menu"></button>
    <button class="sidebar-close-btn" id="closeSidebar" aria-label="Tutup menu">
      <i class="bi bi-x-lg"></i>
    </button>
  </div>

  <nav class="nav flex-column gap-2" id="sidebar-nav">
    <!-- gunakan URL “clean” -->
    <a class="nav-link" href="<?= BASE_URL ?>/admin"><i class="bi bi-house-door"></i> Dashboard</a>
    <a class="nav-link" href="<?= BASE_URL ?>/admin/orders"><i class="bi bi-receipt"></i> Orders</a>
    <a class="nav-link" href="<?= BASE_URL ?>/admin/catalog"><i class="bi bi-box-seam"></i> Catalog</a>
    <a class="nav-link" href="<?= BASE_URL ?>/admin/users"><i class="bi bi-people"></i> Users</a>
    <a class="nav-link" href="<?= BASE_URL ?>/admin/finance"><i class="bi bi-cash-coin"></i> Finance</a>
    <a class="nav-link" href="<?= BASE_URL ?>/admin/notifications"><i class="bi bi-megaphone"></i> Notifications</a>
    <a class="nav-link" href="<?= BASE_URL ?>/admin/audit"><i class="bi bi-shield-check"></i> Audit Log</a>
    <a class="nav-link" href="<?= BASE_URL ?>/admin/settings"><i class="bi bi-gear"></i> Settings</a>
    <hr>
    <a class="nav-link" href="<?= BASE_URL ?>/admin/help"><i class="bi bi-question-circle"></i> Help Center</a>
    <a class="nav-link" href="<?= BASE_URL ?>/backend/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
  </nav>
</aside>

<!-- Content -->
<main class="content">
  <div class="topbar">
    <button class="btn-menu" id="openSidebar" aria-label="Buka menu">
      <div class="hamb-icon"><span></span><span></span><span></span></div>
    </button>

    <div class="search-box">
      <input class="search-input" id="searchInput" placeholder="Search..." autocomplete="off" />
      <i class="bi bi-search search-icon" id="searchIcon"></i>
      <div class="search-suggest" id="searchSuggest"></div>
    </div>

    <div class="top-actions">
      <a id="btnBell" class="icon-btn text-decoration-none" href="<?= BASE_URL ?>/admin/notifications" aria-label="Notifikasi">
        <span class="iconify" data-icon="mdi:bell-outline" data-width="24" data-height="24"></span>
        <span id="badgeNotif" class="notif-dot d-none"></span>
      </a>
      <a class="icon-btn text-decoration-none" href="<?= BASE_URL ?>/admin/settings" aria-label="Akun">
        <span class="iconify" data-icon="mdi:account-circle-outline" data-width="28" data-height="28"></span>
      </a>
    </div>
  </div>

  <h2 class="fw-bold mb-3">Daftar Pesanan</h2>

  <div class="cardx">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th class="col-invoice">Invoice</th>
            <th class="col-name">Nama</th>
            <th class="col-total">Total</th>
            <th class="col-status">Pesanan</th>
            <th class="col-status">Pembayaran</th>
            <th class="col-method">Metode</th>
            <th class="col-actions">Aksi</th>
          </tr>
        </thead>
        <tbody id="rows">
          <tr><td colspan="7" class="text-center text-muted py-4">Memuat...</td></tr>
        </tbody>
      </table>
    </div>
  </div>

</main>

<!-- Modal konfirmasi batal + alasan -->
<div class="modal fade" id="modalCancel" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Caffora</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="cancel_order_id">
        <p class="mb-1">Batalkan pesanan ini?</p>
        <p class="text-muted small mb-2">
          Invoice: <span id="cancel_invoice" class="fw-semibold"></span>
        </p>

        <!-- Tambahan: alasan pembatalan -->
        <label class="form-label mt-2">
          Alasan pembatalan <span class="text-danger">*</span>
        </label>
        <select class="form-select" id="cancel_reason_sel" required>
          <option value="">Pilih alasan…</option>
          <option>Stok habis / tidak mencukupi</option>
          <option>Pelanggan tidak melanjutkan (belum bayar)</option>
          <option>Salah input pesanan</option>
          <option>Menu tidak tersedia hari ini</option>
          <option value="__custom__">Lainnya (tulis manual)</option>
        </select>

        <div class="mt-2 d-none" id="cancel_reason_custom_wrap">
          <textarea
            class="form-control"
            id="cancel_reason_custom"
            rows="2"
            placeholder="Tulis alasan singkat"
          ></textarea>
        </div>

        <div class="small text-muted mt-2">
          Pembatalan hanya untuk pesanan <strong>belum dibayar</strong>.
          Setelah dibatalkan, pembayaran akan ditandai <em>failed</em>
          dan aksi di baris ini akan dinonaktifkan.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tidak</button>
        <button type="button" class="btn btn-danger" id="btnCancelConfirm">Ya, Batalkan</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const ORIGIN  = location.origin.replace(/^http:\/\//i, "https://");
const BASE    = "<?= rtrim(BASE_URL, '/') ?>";

/* Route bersih + fallback ke legacy */
const API_ORDERS_CLEAN   = ORIGIN + "/api/orders";
const API_ORDERS_LEGACY  = ORIGIN + "/backend/api/orders.php";
const API_NOTIF_CLEAN    = ORIGIN + "/api/notifications?action=unread_count";
const API_NOTIF_LEGACY   = ORIGIN + "/backend/api/notifications.php?action=unread_count";

const $rows   = document.getElementById('rows');
const $search = document.getElementById('searchInput');

let cancelModal,
    cancelOrderInput,
    cancelInvoiceSpan,
    cancelReasonSel,
    cancelReasonCustomWrap,
    cancelReasonCustom;

/* ===== Sidebar ===== */
const sideNav  = document.getElementById('sideNav');
const backdrop = document.getElementById('backdrop');

document.getElementById('openSidebar')
  ?.addEventListener('click', () => {
    sideNav.classList.add('show');
    backdrop.classList.add('active');
  });

document.getElementById('closeSidebar')
  ?.addEventListener('click', () => {
    sideNav.classList.remove('show');
    backdrop.classList.remove('active');
  });

document.getElementById('toggleSidebarInside')
  ?.addEventListener('click', () => {
    sideNav.classList.remove('show');
    backdrop.classList.remove('active');
  });

backdrop?.addEventListener('click', () => {
  sideNav.classList.remove('show');
  backdrop.classList.remove('active');
});

/* ===== Notif badge ===== */
async function refreshAdminNotifBadge(){
  const badge = document.getElementById('badgeNotif');
  if (!badge) return;

  let url = API_NOTIF_CLEAN;
  try {
    let res = await fetch(url, { credentials:"same-origin", cache:"no-store" });
    if (!res.ok) {
      url = API_NOTIF_LEGACY;
      res = await fetch(url, { credentials:"same-origin", cache:"no-store" });
    }
    if (!res.ok) return;

    const data  = await res.json();
    const count = Number(data?.count ?? 0);
    badge.classList.toggle('d-none', !(count > 0));
  } catch(e) {
    // silent
  }
}

/* ===== Helpers ===== */
const rp = n => 'Rp ' + Number(n||0).toLocaleString('id-ID');

function badgeOrder(os){
  const map = {
    new:'secondary',
    processing:'primary',
    ready:'warning',
    completed:'success',
    cancelled:'dark'
  };
  return `
    <span class="badge text-bg-${map[os]||'secondary'} text-capitalize">
      ${os||'-'}
    </span>`;
}

function badgePay(ps){
  const map = {
    pending:'warning',
    paid:'success',
    failed:'danger',
    refunded:'info',
    overdue:'secondary'
  };
  return `
    <span class="badge text-bg-${map[ps]||'secondary'} text-capitalize">
      ${ps||'-'}
    </span>`;
}

/**
 * Tombol progres pesanan:
 * - disabledProgress = true → tombol tidak bisa diklik.
 * - kalau status sudah completed/cancelled → tampil tombol Selesai nonaktif.
 */
function nextButtons(os, disabledProgress){
  const order = ['new','processing','ready','completed'];
  const idx   = order.indexOf(os);

  if (idx === -1 || os === 'completed' || os === 'cancelled') {
    return '<button class="btn btn-sm btn-outline-secondary" disabled>Selesai</button>';
  }

  const next     = order[idx+1];
  const labelMap = { processing:'Mulai', ready:'Siap', completed:'Selesai' };

  const extraClass   = disabledProgress ? ' disabled' : '';
  const disabledAttr = disabledProgress ? ' disabled aria-disabled="true"' : '';

  return `
    <button
      class="btn btn-sm btn-outline-primary${extraClass}"
      data-act="next"
      data-val="${next}"${disabledAttr}
    >
      ${labelMap[next] || '→'}
    </button>`;
}

/* ===== request helper ===== */
async function requestJSON(url, opts){
  const res = await fetch(url, opts);
  const ct  = res.headers.get("content-type") || "";
  if (!ct.includes("application/json")) {
    throw new Error("non-json:"+res.status);
  }
  const js = await res.json();
  return { ok: res.ok, json: js };
}

/* ===== Load Orders ===== */
async function loadOrders(q=''){
  try{
    const params = new URLSearchParams({ action:'list' });
    if (q.trim()) params.set('q', q.trim());

    let url = API_ORDERS_CLEAN + "?" + params.toString();
    let res, data;

    try{
      res  = await requestJSON(url, { credentials:'same-origin', cache:'no-store' });
      data = res.json;
    }catch{
      url  = API_ORDERS_LEGACY + "?" + params.toString();
      res  = await requestJSON(url, { credentials:'same-origin', cache:'no-store' });
      data = res.json;
    }

    if (!res.ok || !data || (data.ok === false)) {
      $rows.innerHTML =
        '<tr><td colspan="7" class="text-danger text-center py-4">' +
        (data?.error || 'Gagal memuat') +
        '</td></tr>';
      return;
    }

    const items = Array.isArray(data?.items) ? data.items : [];
    if (!items.length){
      $rows.innerHTML =
        '<tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data.</td></tr>';
      return;
    }

    $rows.innerHTML = items.map(it => {
      const hrefReceipt   = BASE + '/public/admin/receipt.php?order=' + it.id;

      // jika sudah dibatalkan / failed → semua aksi dimatikan
      const disabledAll      = it.order_status === 'cancelled' || it.payment_status === 'failed';
      const disabledStrk     = it.payment_status !== 'paid' || disabledAll;

      // Batal & Lunas hanya boleh saat pembayaran masih pending
      const disabledBatal    = it.payment_status !== 'pending' || disabledAll;
      const disabledLunas    = it.payment_status !== 'pending' || disabledAll;

      // Progres (Mulai/Siap/Selesai) dikunci selama pembayaran masih pending
      const disabledProgress = it.payment_status === 'pending' || disabledAll;

      return `
        <tr
          data-id="${it.id}"
          data-pay="${it.payment_status || ''}"
          data-ost="${it.order_status || ''}"
          data-invoice="${it.invoice_no || ''}"
        >
          <td class="col-invoice fw-semibold">${it.invoice_no || '-'}</td>
          <td class="col-name">${it.customer_name || '-'}</td>
          <td class="col-total">${rp(it.total)}</td>
          <td class="col-status">${badgeOrder(it.order_status)}</td>
          <td class="col-status">${badgePay(it.payment_status)}</td>
          <td class="col-method">${it.payment_method || '-'}</td>
          <td class="col-actions">
            <div class="actions-cell">
              <div class="actions-row">
                ${nextButtons(it.order_status, disabledProgress)}
                <button
                  class="btn btn-sm btn-outline-success ${disabledLunas ? 'disabled' : ''}"
                  data-act="pay"
                  data-val="paid"
                  ${disabledLunas ? 'aria-disabled="true"' : ''}
                >
                  Lunas
                </button>
              </div>
              <div class="actions-row">
                <button
                  class="btn btn-sm btn-outline-danger ${disabledBatal ? 'disabled' : ''}"
                  data-act="cancel"
                  ${disabledBatal ? 'aria-disabled="true"' : ''}
                >
                  Batal
                </button>
                <a
                  class="btn btn-sm btn-outline-dark ${disabledStrk ? 'disabled' : ''}"
                  ${disabledStrk ? 'aria-disabled="true"' : ''}
                  href="${disabledStrk ? '#' : hrefReceipt}"
                >
                  <i class="bi bi-printer"></i>
                  Struk
                </a>
              </div>
            </div>
          </td>
        </tr>`;
    }).join('');

    bindRowActions();
  }catch(e){
    console.error(e);
    $rows.innerHTML =
      '<tr><td colspan="7" class="text-danger text-center py-4">Gagal memuat.</td></tr>';
  }
}

/* ===== Kirim update ke API ===== */
async function sendUpdate(payload){
  const headers = { 'Content-Type':'application/json' };
  let ok = false;

  try{
    let r = await fetch(
      API_ORDERS_CLEAN + '?action=update',
      {
        method:'POST',
        headers,
        credentials:'same-origin',
        body:JSON.stringify(payload)
      }
    );
    ok = r.ok;
    if (!ok) throw 0;
  }catch{
    let r = await fetch(
      API_ORDERS_LEGACY + '?action=update',
      {
        method:'POST',
        headers,
        credentials:'same-origin',
        body:JSON.stringify(payload)
      }
    );
    ok = r.ok;
  }
  return ok;
}

/* ===== Aksi pada tombol di tabel ===== */
function bindRowActions(){
  $rows.querySelectorAll('[data-act]').forEach(btn => {
    btn.addEventListener('click', async ev => {
      ev.preventDefault();

      // kalau tombol sudah disabled secara visual, jangan lakukan apa-apa
      if (btn.classList.contains('disabled')) return;

      const tr  = btn.closest('tr');
      const id  = tr?.dataset.id;
      if (!id) return;

      const act     = btn.dataset.act;
      const payStat = tr.dataset.pay || '';
      const payload = { id: Number(id) };

      if (act === 'next'){
        // Progres hanya boleh kalau tidak pending (sudah dibayar)
        if (payStat === 'pending') return;
        payload.order_status = btn.dataset.val;
        const ok = await sendUpdate(payload);
        if (ok) loadOrders($search.value);
        return;
      }

      if (act === 'pay'){
        // Lunas hanya boleh dari status pembayaran pending
        if (payStat !== 'pending') return;
        payload.payment_status = btn.dataset.val;
        const ok = await sendUpdate(payload);
        if (ok) loadOrders($search.value);
        return;
      }

      if (act === 'cancel'){
        // Batal hanya boleh dari pembayaran pending
        if (payStat !== 'pending') return;

        cancelOrderInput.value        = id;
        cancelInvoiceSpan.textContent = tr.dataset.invoice || '';
        cancelReasonSel.value         = '';
        cancelReasonCustom.value      = '';
        cancelReasonCustomWrap.classList.add('d-none');
        cancelModal.show();
      }
    });
  });
}

/* ===== Konfirmasi batal dari modal (kirim ke orders_cancel.php) ===== */
async function handleConfirmCancel(e){
  e?.preventDefault?.();

  const id  = cancelOrderInput.value;
  const opt = cancelReasonSel.value;
  const cus = cancelReasonCustom.value.trim();
  const reason = (opt === '__custom__') ? (cus || 'Alasan lain') : opt;

  if (!id || !reason){
    alert('Lengkapi alasan pembatalan.');
    return;
  }

  const btn = document.getElementById('btnCancelConfirm');
  btn.disabled   = true;
  btn.textContent = 'Memproses...';

  try{
    const res = await fetch(BASE + '/backend/api/orders_cancel.php', {
      method:'POST',
      credentials:'same-origin',
      headers:{
        'Accept':'application/json',
        'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: new URLSearchParams({ order_id:id, reason })
    });

    const raw = await res.text();
    let js; try{ js = JSON.parse(raw); }catch{ js = null; }

    if (!res.ok || !js || js.ok !== true){
      console.error('orders_cancel.php response:', res.status, raw);
      alert((js && js.error) ? js.error : 'Gagal membatalkan pesanan.');
      return;
    }

    cancelModal.hide();
    await loadOrders($search.value);
  }catch(err){
    console.error(err);
    alert('Terjadi masalah jaringan.');
  }finally{
    btn.disabled   = false;
    btn.textContent = 'Ya, Batalkan';
  }
}

/* ===== Search + init ===== */
$search.addEventListener('input', () => loadOrders($search.value));
document
  .getElementById('searchIcon')
  .addEventListener('click', () => loadOrders($search.value));

document.addEventListener('DOMContentLoaded', () => {
  cancelModal             = new bootstrap.Modal(document.getElementById('modalCancel'));
  cancelOrderInput        = document.getElementById('cancel_order_id');
  cancelInvoiceSpan       = document.getElementById('cancel_invoice');
  cancelReasonSel         = document.getElementById('cancel_reason_sel');
  cancelReasonCustomWrap  = document.getElementById('cancel_reason_custom_wrap');
  cancelReasonCustom      = document.getElementById('cancel_reason_custom');

  if (cancelReasonSel){
    cancelReasonSel.addEventListener('change', () => {
      cancelReasonCustomWrap.classList.toggle(
        'd-none',
        cancelReasonSel.value !== '__custom__'
      );
    });
  }

  document.getElementById('btnCancelConfirm')
    .addEventListener('click', handleConfirmCancel);

  loadOrders();
  refreshAdminNotifBadge();
});
</script>

</body>
</html>
