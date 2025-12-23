<?php
// public/admin/audit.php
declare(strict_types=1);

require_once __DIR__ . '/../../backend/auth_guard.php';
require_login(['admin']);
require_once __DIR__ . '/../../backend/config.php';

$BASE      = BASE_URL;
$adminName = htmlspecialchars($_SESSION['user_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Audit Log — Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap (modal custom range) & Icons & Iconify -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://code.iconify.design/2/2.2.1/iconify.min.js"></script>

  <style>
   :root{                                       /* Root variable */
      --gold:#FFD54F;                        /* Warna emas utama */
      --gold-border:#f7d78d;                 /* Border emas */
      --brown:#4B3F36;                       /* Warna coklat */
      --ink:#0f172a;                         /* Warna teks utama */
      --radius:18px;                         /* Radius global */
      --sidebar-w:320px;                     /* Lebar sidebar */
      --soft:#fff7d1;                        /* Warna lembut */
      --hover:#ffefad;                       /* Warna hover */
      --btn-radius:14px;                     /* Radius tombol */
    }
    *,:before,:after{ box-sizing:border-box; } /* Box model */
    body{                                    /* Body utama */
      background:#FAFAFA;                    /* Background */
      color:var(--ink);                      /* Warna teks */
      font-family:Inter,system-ui,Segoe UI,Roboto,Arial; /* Font */
      font-weight:500;                       /* Ketebalan font */
      margin:0;                              /* Reset margin */
    }

    /* ===== Sidebar (match Finance) ===== */
    .sidebar{                                /* Sidebar utama */
      position:fixed;                        /* Posisi fixed */
      left:-320px;                           /* Tersembunyi kiri */
      top:0;                                 /* Posisi atas */
      bottom:0;                              /* Posisi bawah */
      width:var(--sidebar-w);                /* Lebar sidebar */
      background:#fff;                       /* Warna latar */
      border-right:1px solid rgba(0,0,0,.05);/* Garis kanan */
      transition:left .25s ease;             /* Animasi geser */
      z-index:1050;                          /* Layer */
      padding:16px 18px;                     /* Padding */
      overflow-y:auto;                       /* Scroll vertikal */
    }
    .sidebar.show{ left:0; }                 /* Sidebar aktif */
    .sidebar-head{                           /* Header sidebar */
      display:flex;                          /* Flex layout */
      align-items:center;                    /* Tengah vertikal */
      justify-content:space-between;         /* Spasi kiri kanan */
      gap:10px;                              /* Jarak */
      margin-bottom:10px;                    /* Margin bawah */
    }
    .sidebar-inner-toggle,
    .sidebar-close-btn{                      /* Tombol sidebar */
      background:transparent;                /* Transparan */
      border:0;                              /* Tanpa border */
      width:40px;                            /* Lebar */
      height:36px;                           /* Tinggi */
      display:grid;                          /* Grid */
      place-items:center;                   /* Tengah */
    }
    .hamb-icon{                              /* Icon hamburger */
      width:24px;                            /* Lebar */
      height:20px;                           /* Tinggi */
      display:flex;                          /* Flex */
      flex-direction:column;                /* Vertikal */
      justify-content:space-between;         /* Spasi */
      gap:4px;                               /* Jarak */
    }
    .hamb-icon span{                         /* Garis icon */
      height:2px;                            /* Tinggi garis */
      background:var(--brown);               /* Warna garis */
      border-radius:99px;                    /* Rounded */
    }
    .sidebar .nav-link{                      /* Link sidebar */
      display:flex;                          /* Flex */
      align-items:center;                   /* Tengah */
      gap:12px;                              /* Jarak icon */
      padding:12px 14px;                    /* Padding */
      border-radius:16px;                   /* Radius */
      font-weight:600;                      /* Tebal */
      color:#111;                           /* Warna teks */
      text-decoration:none;                 /* Tanpa underline */
      background:transparent;               /* Transparan */
      user-select:none;                     /* Tidak bisa select */
    }
    .sidebar .nav-link:hover{                /* Hover link */
      background:rgba(255,213,79,0.25);     /* Background hover */
    }
    .sidebar hr{                             /* Garis pemisah */
      border-color:rgba(0,0,0,.05);         /* Warna garis */
      opacity:1;                            /* Opacity */
    }

    /* Backdrop mobile */
    .backdrop-mobile{                        /* Backdrop */
      position:fixed;                       /* Fixed */
      inset:0;                              /* Full layar */
      background:rgba(0,0,0,.25);           /* Warna overlay */
      z-index:1040;                         /* Layer */
      display:none;                         /* Hidden */
    }
    .backdrop-mobile.active{ display:block; }/* Aktif */

    /* ===== Content + Topbar ===== */
    .content{                                /* Konten utama */
      margin-left:0;                        /* Reset margin */
      padding:16px 14px 50px;               /* Padding */
    }
    .topbar{                                 /* Topbar */
      display:flex;                         /* Flex */
      align-items:center;                  /* Tengah */
      gap:12px;                            /* Jarak */
      margin-bottom:16px;                  /* Margin bawah */
    }
    .btn-menu{                               /* Tombol menu */
      background:transparent;               /* Transparan */
      border:0;                             /* Tanpa border */
      width:40px;                           /* Lebar */
      height:38px;                          /* Tinggi */
      display:grid;                         /* Grid */
      place-items:center;                  /* Tengah */
    }

    /* ===== Responsive ===== */
    @media (min-width:992px){               /* Tablet+ */
      .content{
        padding:20px 26px 60px;             /* Padding besar */
      }
      .search-box{ max-width:1100px; }     /* Lebar search */
    }
    @media (min-width:1200px){              /* Desktop */
      .content{
        padding-left:10px !important;       /* Padding kiri */
        padding-right:10px !important;      /* Padding kanan */
      }
    }
    @media (max-width:360px){               /* HP kecil */
      .icon-btn{ width:34px; height:34px; } /* Icon kecil */
      .search-input{
        height:40px;                        /* Tinggi input */
        padding-left:12px;                  /* Padding kiri */
        padding-right:38px;                 /* Padding kanan */
      }
      .select-toggle{ height:38px; }        /* Tinggi select */
      #btnDownload{ height:38px; }          /* Tinggi tombol */
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
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/index.php"><i class="bi bi-house-door"></i> Dashboard</a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/orders.php"><i class="bi bi-receipt"></i> Orders</a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/catalog.php"><i class="bi bi-box-seam"></i> Catalog</a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/users.php"><i class="bi bi-people"></i> Users</a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/finance.php"><i class="bi bi-cash-coin"></i> Finance</a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/notifications_send.php"><i class="bi bi-megaphone"></i> Notifications</a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/audit.php"><i class="bi bi-shield-check"></i> Audit Log</a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/settings.php"><i class="bi bi-gear"></i> Settings</a>
    <hr>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/help.php"><i class="bi bi-question-circle"></i> Help Center</a>
    <a class="nav-link" href="<?= BASE_URL ?>/backend/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
  </nav>
</aside>

<!-- Content -->
<main class="content">
  <div class="container-xxl px-2 px-xl-3">

    <!-- Topbar -->
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
        <a id="btnBell" class="icon-btn position-relative text-decoration-none" href="<?= BASE_URL ?>/public/admin/notifications.php">
          <span class="iconify" data-icon="mdi:bell-outline" data-width="24" data-height="24"></span>
          <span id="badgeNotif" class="d-none" style="position:absolute;top:3px;right:3px;width:8px;height:8px;background:#4b3f36;border-radius:999px;box-shadow:0 0 0 1.5px #fff;"></span>
        </a>
        <a class="icon-btn text-decoration-none" href="<?= BASE_URL ?>/public/admin/settings.php">
          <span class="iconify" data-icon="mdi:account-circle-outline" data-width="28" data-height="28"></span>
        </a>
      </div>
    </div>

    <h2 class="fw-bold mb-1">Audit Log</h2>

    <!-- Periode + Download -->
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
      <div>
        <div class="text-muted small mb-1">Periode ditampilkan</div>
        <div class="fw-semibold" id="periodText">–</div>
      </div>

      <div class="range-wrap">
        <!-- native select (ghost) -->
        <select id="rangeSelectGhost" class="select-ghost" tabindex="-1" aria-hidden="true" hidden>
          <option value="today">Hari ini</option>
          <option value="7">7 hari</option>
          <option value="30">30 hari</option>
          <option value="custom">Custom…</option>
        </select>

        <!-- custom dropdown -->
        <div class="select-custom" id="rangeCustom">
          <button type="button" class="select-toggle" id="rangeBtn" aria-haspopup="listbox" aria-expanded="false" tabindex="0">
            <span id="rangeText">7 hari</span>
            <i class="bi bi-chevron-down select-caret"></i>
          </button>
          <div class="select-menu" id="rangeMenu" role="listbox" aria-labelledby="rangeBtn">
            <div class="select-item" data-value="today">Hari ini</div>
            <div class="select-item active" data-value="7">7 hari</div>
            <div class="select-item" data-value="30">30 hari</div>
            <div class="select-item" data-value="custom">Custom…</div>
          </div>
        </div>

        <!-- tombol download -->
        <button id="btnDownload" class="btn-download">
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" width="20" height="20">
            <path d="M12 3a1 1 0 011 1v8.586l2.293-2.293a1 1 0 111.414 1.414l-4.001 4a1 1 0 01-1.414 0l-4.001-4a1 1 0 111.414-1.414L11 12.586V4a1 1 0 011-1z"></path>
            <path d="M5 19a1 1 0 011-1h12a1 1 0 110 2H6a1 1 0 01-1-1z"></path>
          </svg>
          <span>Export CSV</span>
        </button>
      </div>
    </div>

    <!-- Tabel -->
    <div class="card">
      <div style="overflow:auto;">
        <table>
          <thead>
            <tr>
              <th style="min-width:160px">Tanggal</th>
              <th style="min-width:90px">Jenis</th>
              <th style="min-width:140px">Invoice</th>
              <th style="min-width:260px">Aktivitas</th>
              <th style="min-width:160px">Dibuat oleh</th>
              <th style="min-width:120px">Role</th>
              <th style="min-width:220px">Catatan</th>
            </tr>
          </thead>
          <tbody id="tbody">
            <tr><td colspan="7" class="muted">Memuat data…</td></tr>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</main>

<!-- Modal Custom Range -->
<div class="modal fade" id="modalCustom" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:460px">
    <form class="modal-content" id="customForm">
      <div class="modal-header">
        <h5 class="modal-title">Pilih rentang tanggal</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label" for="from">Dari tanggal</label>
          <input id="from" name="from" type="date" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label" for="to">Sampai tanggal</label>
          <input id="to" name="to" type="date" class="form-control" required>
        </div>
      </div>
      <div class="modal-footer flex-wrap gap-2">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn-download" style="border:0">Terapkan</button>
      </div>
    </form>
  </div>
</div>
<!-- ======================================================= -->
<!-- Bootstrap Bundle JS -->
<!-- ======================================================= -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// =======================================================
// KONFIGURASI DASAR API
// =======================================================

// Base URL aplikasi
const BASE = '<?= $BASE ?>';

// Endpoint audit log API
const API  = BASE + '/backend/api/audit_logs.php';

// =======================================================
// CACHE ELEMENT DOM
// =======================================================

const els = {
  // Sidebar & backdrop
  sideNav: document.getElementById('sideNav'),
  backdrop: document.getElementById('backdrop'),

  // Range filter
  rangeBtn:  document.getElementById('rangeBtn'),
  rangeMenu: document.getElementById('rangeMenu'),
  rangeText: document.getElementById('rangeText'),

  // Period label & table body
  periodText: document.getElementById('periodText'),
  tbody: document.getElementById('tbody'),

  // Modal custom range
  modalCustom: document.getElementById('modalCustom'),
  customForm:  document.getElementById('customForm'),
  from: document.getElementById('from'),
  to:   document.getElementById('to'),
};

// =======================================================
// STATE APLIKASI
// =======================================================

const state = {
  page: 1,
  per_page: 300,
  range: { preset: '7', from: '', to: '' },
  rows: []
};

// =======================================================
// SIDEBAR HANDLER
// =======================================================

// Buka sidebar
document.getElementById('openSidebar')?.addEventListener('click',()=>{
  els.sideNav.classList.add('show');
  els.backdrop?.classList.add('active');
});

// Tutup sidebar (tombol close)
document.getElementById('closeSidebar')?.addEventListener('click',()=>{
  els.sideNav.classList.remove('show');
  els.backdrop?.classList.remove('active');
});

// Tutup sidebar (klik backdrop)
els.backdrop?.addEventListener('click',()=>{
  els.sideNav.classList.remove('show');
  els.backdrop?.classList.remove('active');
});

// Aktifkan menu sidebar & auto close di layar kecil
document.querySelectorAll('#sidebar-nav .nav-link').forEach(a=>{
  a.addEventListener('click',function(){
    document.querySelectorAll('#sidebar-nav .nav-link')
      .forEach(l=>l.classList.remove('active'));
    this.classList.add('active');
    if (window.innerWidth < 1200){
      els.sideNav.classList.remove('show');
      els.backdrop?.classList.remove('active');
    }
  });
});

// =======================================================
// SEARCH GLOBAL (ADMIN & KARYAWAN)
// =======================================================

function attachSearch(inputEl, suggestEl){
  if (!inputEl || !suggestEl) return;

  // Endpoint pencarian admin & karyawan
  const ADMIN_EP = "<?= BASE_URL ?>/backend/api/admin_search.php";
  const KARY_EP  = "<?= BASE_URL ?>/backend/api/karyawan_search.php";

  // Fetch hasil pencarian
  async function fetchResults(q){
    try{
      const r = await fetch(ADMIN_EP + "?q=" + encodeURIComponent(q));
      if (r.ok) return await r.json();
    }catch(e){}
    try{
      const r2 = await fetch(KARY_EP + "?q=" + encodeURIComponent(q));
      if (r2.ok) return await r2.json();
    }catch(e){}
    return { ok:true, results:[] };
  }

  // Event input search
  inputEl.addEventListener('input', async function(){
    const q = this.value.trim();
    if (q.length < 2){
      suggestEl.classList.remove('visible');
      suggestEl.innerHTML = '';
      return;
    }

    const data = await fetchResults(q);
    const arr  = Array.isArray(data.results) ? data.results : [];

    if (!arr.length){
      suggestEl.innerHTML =
        '<div class="search-empty">Tidak ada hasil.</div>';
      suggestEl.classList.add('visible');
      return;
    }

    let html = '';
    arr.forEach(r=>{
      html += `
        <div class="item" data-type="${r.type}" data-key="${r.key}">
          ${r.label ?? ''} ${r.sub ? `<small>${r.sub}</small>` : ''}
        </div>`;
    });

    suggestEl.innerHTML = html;
    suggestEl.classList.add('visible');

    // Redirect saat item diklik
    suggestEl.querySelectorAll('.item').forEach(it=>{
      it.addEventListener('click',()=>{
        const type = it.dataset.type;
        const key  = it.dataset.key;
        if (type === 'order')
          window.location = "<?= BASE_URL ?>/public/admin/orders.php?search=" + encodeURIComponent(key);
        else if (type === 'menu')
          window.location = "<?= BASE_URL ?>/public/admin/catalog.php?search=" + encodeURIComponent(key);
        else if (type === 'user')
          window.location = "<?= BASE_URL ?>/public/admin/users.php?search=" + encodeURIComponent(key);
        else
          window.location = "<?= BASE_URL ?>/public/admin/orders.php?search=" + encodeURIComponent(key);
      });
    });
  });

  // Tutup suggest saat klik di luar
  document.addEventListener('click',(ev)=>{
    if (!suggestEl.contains(ev.target) && ev.target !== inputEl){
      suggestEl.classList.remove('visible');
    }
  });

  // Fokus input saat klik ikon search
  document.getElementById('searchIcon')
    ?.addEventListener('click',()=>inputEl.focus());
}

// Pasang search handler
attachSearch(
  document.getElementById('searchInput'),
  document.getElementById('searchSuggest')
);

// =======================================================
// NOTIFIKASI BADGE ADMIN
// =======================================================

async function refreshAdminNotifBadge(){
  const badge = document.getElementById('badgeNotif');
  if (!badge) return;
  try{
    const res = await fetch(
      "<?= BASE_URL ?>/backend/api/notifications.php?action=unread_count",
      { credentials:"same-origin" }
    );
    if (!res.ok) return;
    const data = await res.json();
    const count = data.count ?? 0;
    badge.classList.toggle('d-none', !(count > 0));
  }catch(e){}
}

// Refresh awal & interval
refreshAdminNotifBadge();
setInterval(refreshAdminNotifBadge, 30000);

// =======================================================
// HELPER UTILITAS
// =======================================================

// Escape HTML
const h = s => (s==null ? '' : String(s))
  .replace(/&/g,'&amp;')
  .replace(/</g,'&lt;')
  .replace(/>/g,'&gt;')
  .replace(/"/g,'&quot;');

// Padding angka
function pad(n){ return String(n).padStart(2,'0'); }

// Format datetime
function fmtDateTime(s){
  if (!s) return '';
  const dt = new Date(String(s).replace(' ','T'));
  if (isNaN(dt.getTime())) return s;
  return `${dt.getFullYear()}-${pad(dt.getMonth()+1)}-${pad(dt.getDate())} ${pad(dt.getHours())}:${pad(dt.getMinutes())}:${pad(dt.getSeconds())}`;
}

// Parse JSON aman
function parseJson(s){
  if (!s) return null;
  try{
    const j = JSON.parse(s);
    return j && typeof j === 'object' ? j : null;
  }catch{
    return null;
  }
}

// Ambil invoice dari row
function extractInvoice(row){
  if (row.invoice) return row.invoice;
  const to   = parseJson(row.to_val);
  const from = parseJson(row.from_val);
  const inv = (to && (to.invoice || to.invoice_no))
           || (from && (from.invoice || from.invoice_no));
  return inv || '';
}

// Ringkasan aktivitas
function summarize(row){
  const e  = row.entity;
  const a  = row.action;
  const to = parseJson(row.to_val);

  if (e === 'payment' && (a === 'update_status' || a === 'mark_paid')){
    const newStatus = (to && (to.status || to.payment_status)) || (row.to_val || '');
    return /(^|\b)paid(\b|$)/i.test(String(newStatus))
      ? 'Pembayaran diterima'
      : 'Status pembayaran diperbarui';
  }
  if (e === 'order' && a === 'cancel')
    return 'Pesanan dibatalkan' + ((to && to.reason) ? ` — ${to.reason}` : '');
  if (e === 'order' && a === 'create')        return 'Pesanan baru dibuat';
  if (e === 'order' && a === 'update_status') return 'Status pesanan diperbarui';

  return (a || 'Aksi') + ' pada ' + (e || 'entitas');
}

// Bersihkan catatan
function cleanRemark(rem){
  if (!rem) return '—';
  return String(rem).replace(/\bvia API\b/gi, 'by system');
}

// =======================================================
// RANGE FILTER & MODAL
// =======================================================

// Inisialisasi modal bootstrap
const bsModal = new bootstrap.Modal(document.getElementById('modalCustom'));

// Set preset range
function setPreset(preset){
  const now = new Date();
  const to  = now.toISOString().slice(0,10);
  let from  = to;

  if (preset === 'today'){
  } else if (preset === '7'){
    from = new Date(now.getTime() - 6*864e5).toISOString().slice(0,10);
  } else if (preset === '30'){
    from = new Date(now.getTime() - 29*864e5).toISOString().slice(0,10);
  }

  state.range = { preset, from, to };
  els.rangeText.textContent =
    preset === 'today' ? 'Hari ini' :
    preset === '7'     ? '7 hari'   :
    '30 hari';

  document.querySelectorAll('#rangeMenu .select-item')
    .forEach(x=>x.classList.remove('active'));

  const active = document.querySelector(`#rangeMenu .select-item[data-value="${preset}"]`);
  if (active) active.classList.add('active');

  fetchData();
}

// Apply range
function applyRange(v){
  if (v === 'custom'){
    bsModal.show();
    return;
  }
  setPreset(v);
}

// Toggle dropdown
els.rangeBtn?.addEventListener('click', ()=>{
  const shown = els.rangeMenu.classList.toggle('show');
  els.rangeBtn.setAttribute('aria-expanded', shown ? 'true' : 'false');
});

// Close dropdown
document.addEventListener('click',(e)=>{
  if (!els.rangeMenu.contains(e.target) && e.target !== els.rangeBtn){
    els.rangeMenu.classList.remove('show');
    els.rangeBtn.setAttribute('aria-expanded','false');
  }
});

// Close dropdown via ESC
document.addEventListener('keydown',(e)=>{
  if (e.key === 'Escape'){
    els.rangeMenu.classList.remove('show');
    els.rangeBtn.setAttribute('aria-expanded','false');
  }
});

// Pilih item range
document.querySelectorAll('#rangeMenu .select-item').forEach(it=>{
  it.addEventListener('click',()=>{
    document.querySelectorAll('#rangeMenu .select-item')
      .forEach(x=>x.classList.remove('active'));
    it.classList.add('active');
    els.rangeText.textContent = it.textContent.trim();
    applyRange(it.dataset.value);
  });
});

// Submit custom range
els.customForm?.addEventListener('submit',(ev)=>{
  ev.preventDefault();
  const from = els.from.value;
  const to   = els.to.value;
  if (!from || !to) return;
  state.range = { preset:'custom', from, to };
  els.rangeText.textContent = 'Custom…';
  bsModal.hide();
  fetchData();
});

// =======================================================
// FETCH & RENDER DATA
// =======================================================

async function fetchData(){
  const p = new URLSearchParams({
    page: state.page,
    per_page: state.per_page,
    sort: 'created_at_desc'
  });

  if (state.range.from) p.set('from', state.range.from);
  if (state.range.to)   p.set('to',   state.range.to);

  const labFrom = state.range.from || state.range.to || '';
  const labTo   = state.range.to   || state.range.from || '';

  els.periodText.textContent = (labFrom && labTo)
    ? `${labFrom.split('-').reverse().join('/')} – ${labTo.split('-').reverse().join('/')}`
    : '(semua)';

  els.tbody.innerHTML =
    `<tr><td colspan="7" class="muted">Memuat data…</td></tr>`;

  try{
    const res  = await fetch(`${API}?${p.toString()}`, { credentials:'same-origin' });
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'Gagal memuat');

    state.rows = (json.data || [])
      .filter(r => r && (r.entity === 'order' || r.entity === 'payment'));

    render(state.rows);
  }catch(e){
    els.tbody.innerHTML =
      `<tr><td colspan="7" class="muted">Error: ${h(e.message)}</td></tr>`;
  }
}

// Render tabel
function render(rows){
  if (!rows.length){
    els.tbody.innerHTML =
      `<tr><td colspan="7" class="muted">Tidak ada data pada periode ini.</td></tr>`;
    return;
  }

  let html = '';
  rows.forEach(r=>{
    const aktivitas = summarize(r);
    const invoice   = extractInvoice(r);
    const actorName = r.actor_name
      ? String(r.actor_name)
      : (r.actor_id ? `ID #${r.actor_id}` : '—');
    const role      = r.actor_role || (r.actor_id ? '' : '—');
    const remark    = cleanRemark(r.remark);

    html += `<tr>
      <td>${h(fmtDateTime(r.created_at))}</td>
      <td><span class="badge">${h(r.entity || '-')}</span></td>
      <td>${h(invoice)}</td>
      <td>${h(aktivitas)}</td>
      <td>${h(actorName)}</td>
      <td>${h(role || '')}</td>
      <td>${h(remark)}</td>
    </tr>`;
  });

  els.tbody.innerHTML = html;
}

// =======================================================
// DOWNLOAD CSV
// =======================================================

document.getElementById('btnDownload')
  ?.addEventListener('click',(e)=>{
    e.preventDefault();
    const rows = state.rows || [];
    if (!rows.length){
      alert('Tidak ada data untuk diekspor.');
      return;
    }

    const headers = ['Tanggal','Jenis','Invoice','Aktivitas','Dibuat oleh','Role','Catatan'];
    const data = rows.map(r=>[
      fmtDateTime(r.created_at),
      r.entity || '',
      extractInvoice(r),
      summarize(r),
      r.actor_name ? String(r.actor_name) : (r.actor_id ? `ID #${r.actor_id}` : ''),
      r.actor_role || '',
      cleanRemark(r.remark)
    ]);

    const csv = [headers.join(',')]
      .concat(
        data.map(arr =>
          arr.map(v => `"${String(v ?? '').replace(/"/g,'""')}"`).join(',')
        )
      )
      .join('\n');

    const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
    const a    = document.createElement('a');
    a.href     = URL.createObjectURL(blob);
    const from = state.range.from || 'all';
    const to   = state.range.to   || 'all';
    a.download = `audit_${from}-${to}.csv`;
    a.click();
    URL.revokeObjectURL(a.href);
  });

// =======================================================
// INIT DEFAULT (7 HARI)
// =======================================================

// Load data default 7 hari terakhir
setPreset('7');
</script>

</body>
</html>
