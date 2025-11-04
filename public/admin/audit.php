<?php 
// public/admin/audit.php
declare(strict_types=1);

require_once __DIR__ . '/../../backend/auth_guard.php';
require_login(['admin']);
require_once __DIR__ . '/../../backend/config.php';

$BASE = BASE_URL;
$adminName = $_SESSION['user_name'] ?? 'Admin';
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Audit Log — Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root{
      --gold:#FFD54F; --gold-soft:#F6D472; --brown:#4B3F36; --ink:#0f172a; --muted:#6b7280;
      --line:#efe6c8; --bg:#FAFAFA; --white:#fff; --radius:18px; --radius-sm:12px; --sidebar-w:320px;
      --shadow: 0 10px 24px rgba(0,0,0,.06);
    }
    *,:before,:after{ box-sizing:border-box; }
    html,body{ height:100% }
    body{ margin:0; background:var(--bg); color:var(--ink); font-family:Inter,system-ui,Segoe UI,Roboto,Arial; }

    /* Backdrop & Sidebar */
    .backdrop-mobile{position:fixed; inset:0; background:rgba(17,24,39,.45); opacity:0; pointer-events:none; transition:.2s; z-index:80}
    .backdrop-mobile.show{opacity:1; pointer-events:auto}
    .sidebar{position:fixed; left:-320px; top:0; bottom:0; width:var(--sidebar-w); background:#fff; border-right:1px solid var(--line); z-index:90; transform:translateX(0); transition:.22s}
    .sidebar.show{transform:translateX(320px)}
    .sidebar-head{display:flex; justify-content:flex-end; align-items:center; gap:8px; padding:12px}
    .sidebar-close-btn{width:38px;height:38px;border-radius:12px;border:1px solid #eee;background:#fff;cursor:pointer}
    .nav{padding:10px}
    .nav .nav-link{display:flex; align-items:center; gap:12px; padding:12px 14px; margin-bottom:6px; border-radius:12px; color:#111; text-decoration:none; border:1px solid transparent}
    .nav .nav-link i{opacity:.9}
    .nav .nav-link:hover{background:#fffdf2;border-color:var(--gold-soft)}
    .nav .nav-link.active{background:#fffbe6;border-color:var(--gold-soft); font-weight:600}

    /* Page wrapper */
    .page{max-width:1200px; margin:20px auto; padding:0 16px}

    /* Topbar */
    .topbar{
      display:flex; align-items:center; justify-content:space-between;
      padding:10px 12px; background:#fff; border:1px solid var(--line); border-radius:var(--radius);
      position:sticky; top:10px; z-index:70; box-shadow:var(--shadow);
    }
    .left-title{display:flex; align-items:center; gap:10px}
    .hamburger{width:40px;height:40px;display:grid;place-items:center;border-radius:12px;border:1px solid #e5e7eb;background:#fff;cursor:pointer}
    .title{display:flex;align-items:center;gap:10px;font-weight:750}
    .title i{color:var(--brown)}
    .chip-user{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border:1px solid #e5e7eb;border-radius:999px;background:#fff}

    /* Filter bar */
    .panel{
      background:#fff; border:1px solid var(--line); border-radius:var(--radius); padding:12px; margin-top:14px; box-shadow:var(--shadow);
    }
    .filters{display:grid; gap:10px; grid-template-columns:1fr 220px 160px 160px 160px auto auto}
    .input, select{height:42px; border:1.6px solid #e5e7eb; border-radius:12px; padding:0 12px; outline:0}
    .input:focus, select:focus{border-color:var(--gold)}
    .btn{height:42px; padding:0 14px; border-radius:12px; border:1.6px solid var(--gold-soft); background:var(--gold); color:#111; font-weight:700; cursor:pointer}
    .btn.secondary{background:#fff;border-color:#e5e7eb}
    .btn.icon{display:inline-flex;align-items:center;gap:8px}
    .quick{display:flex; gap:8px; flex-wrap:wrap; margin-top:8px}
    .chip{display:inline-flex; align-items:center; gap:6px; height:34px; padding:0 12px; border:1.6px solid #e5e7eb; border-radius:999px; background:#fff; cursor:pointer; font-size:14px}
    .chip.active,.chip:hover{border-color:var(--gold-soft); background:#fffbe6}

    /* Table */
    .card{background:#fff; border:1px solid var(--line); border-radius:var(--radius); margin-top:16px; overflow:auto; box-shadow:var(--shadow)}
    table{width:100%; border-collapse:collapse}
    th,td{padding:14px 12px; border-bottom:1px dashed #eee; font-size:14px; text-align:left; vertical-align:middle}
    th{background:#fffdf2; position:sticky; top:0; z-index:1}
    .muted{color:var(--muted)}
    .badge{display:inline-flex; align-items:center; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:700; background:#fff; border:1.4px solid var(--gold-soft); color:#111}
    .actor{display:flex; align-items:center; gap:10px}
    .avatar{width:30px; height:30px; border-radius:999px; display:grid; place-items:center; background:#fffbe6; border:1px solid var(--gold-soft); font-weight:700; font-size:12px}
    .name{font-weight:700}
    .role, .idline{font-size:12px; color:var(--muted)}

    /* Empty state */
    .empty{padding:28px; text-align:center; color:var(--muted)}

    /* Pagination */
    .pager{display:flex; justify-content:flex-end; gap:8px; padding:12px}
    .pager .btn{height:36px}

    /* Responsive */
    @media (max-width: 1024px){ .filters{grid-template-columns:1fr 200px 140px 1fr 1fr auto} }
    @media (max-width: 720px){ .filters{grid-template-columns:1fr 1fr} }
  </style>
</head>
<body>

<!-- Sidebar (consisten dengan halaman admin lain) -->
<div id="backdrop" class="backdrop-mobile"></div>
<aside id="sideNav" class="sidebar" aria-label="Menu samping">
  <div class="sidebar-head">
    <button id="closeSidebar" class="sidebar-close-btn" aria-label="Tutup"><i class="bi bi-x-lg"></i></button>
  </div>
  <nav class="nav">
    <a class="nav-link" href="<?= $BASE ?>/public/admin/index.php"><i class="bi bi-house-door"></i> Dashboard</a>
    <a class="nav-link" href="<?= $BASE ?>/public/admin/orders.php"><i class="bi bi-receipt"></i> Orders</a>
    <a class="nav-link" href="<?= $BASE ?>/public/admin/catalog.php"><i class="bi bi-box-seam"></i> Catalog</a>
    <a class="nav-link" href="<?= $BASE ?>/public/admin/users.php"><i class="bi bi-people"></i> Users</a>
    <a class="nav-link" href="<?= $BASE ?>/public/admin/finance.php"><i class="bi bi-cash-coin"></i> Finance</a>
    <a class="nav-link" href="<?= $BASE ?>/public/admin/notifications_send.php"><i class="bi bi-megaphone"></i> Kirim Notifikasi</a>
    <a class="nav-link active" href="<?= $BASE ?>/public/admin/audit.php"><i class="bi bi-shield"></i> Audit Log</a>
    <hr>
    <a class="nav-link" href="<?= $BASE ?>/public/admin/help.php"><i class="bi bi-question-circle"></i> Help Center</a>
    <a class="nav-link" href="<?= $BASE ?>/backend/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
  </nav>
</aside>

<div class="page">
  <!-- Topbar -->
  <div class="topbar" role="banner">
    <div class="left-title">
      <button id="openSidebar" class="hamburger" aria-label="Buka menu"><i class="bi bi-list"></i></button>
      <div class="title"><i class="bi bi-shield"></i><span>Audit Log</span></div>
    </div>
    <div class="chip-user"><i class="bi bi-person-circle"></i> <strong><?= htmlspecialchars($adminName) ?></strong></div>
  </div>

  <!-- Filters -->
  <div class="panel">
    <div class="filters">
      <input id="q" class="input" type="search" placeholder="Cari aktivitas/remark..." aria-label="Cari" />
      <select id="entity" aria-label="Filter entitas">
        <option value="">Semua Entitas</option>
        <option value="order">Order</option>
        <option value="payment">Payment</option>
        <option value="user">User</option>
        <option value="menu">Menu</option>
        <option value="notification">Notification</option>
      </select>
      <input id="actor_id" class="input" type="number" min="0" placeholder="Actor ID" aria-label="ID actor" />
      <input id="from" class="input" type="date" aria-label="Dari tanggal" />
      <input id="to" class="input" type="date" aria-label="Sampai tanggal" />
      <button id="apply" class="btn icon" aria-label="Terapkan filter"><i class="bi bi-funnel"></i> Terapkan</button>
      <button id="exportCsv" class="btn secondary icon" aria-label="Export CSV"><i class="bi bi-filetype-csv"></i> Export CSV</button>
    </div>
    <div class="quick" aria-label="Rentang cepat">
      <button class="chip" data-range="1">Hari ini</button>
      <button class="chip" data-range="7">7 Hari</button>
      <button class="chip" data-range="30">30 Hari</button>
      <button class="chip" data-range="0"><i class="bi bi-x"></i> Reset</button>
    </div>
  </div>

  <!-- Table -->
  <div class="card" role="region" aria-label="Daftar audit">
    <table>
      <thead>
        <tr>
          <th>Aktivitas</th>
          <th>Objek</th> <!-- DIGANTI dari 'Target' -->
          <th>Oleh</th>
          <th>Waktu</th>
          <th>Catatan</th>
        </tr>
      </thead>
      <tbody id="tbody">
        <tr><td colspan="5" class="empty">Memuat data…</td></tr>
      </tbody>
    </table>
  </div>

  <div class="pager">
    <button class="btn secondary" id="prev"><i class="bi bi-chevron-left"></i> Prev</button>
    <div id="pageInfo" class="muted" style="align-self:center"></div>
    <button class="btn secondary" id="next">Next <i class="bi bi-chevron-right"></i></button>
  </div>
</div>

<script>
const BASE = '<?= $BASE ?>';
const API  = BASE + '/backend/api/audit_logs.php';

const state = {
  page: 1, per_page: 20, total_pages: 1,
  lastRows: [],
  filters: { q:'', entity:'', actor_id:'', from:'', to:'', sort:'created_at_desc' }
};

/* ====== Small utils ====== */
const $ = s => document.querySelector(s);
const $$ = s => document.querySelectorAll(s);
const h = s => (s==null?'':String(s))
  .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

const initials = name => (name||'#?').trim().slice(0,2).toUpperCase();
function fmtDate(d){
  if(!d) return '';
  const dt = new Date(String(d).replace(' ','T'));
  if (isNaN(dt.getTime())) return d;
  const pad = n => String(n).padStart(2,'0');
  return `${pad(dt.getDate())}/${pad(dt.getMonth()+1)}/${dt.getFullYear()} ${pad(dt.getHours())}:${pad(dt.getMinutes())}`;
}
function safeJson(s){ if(!s||typeof s!=='string') return null; try{const j=JSON.parse(s); return (j && typeof j==='object') ? j : null;}catch{ return null; }}

/* ====== Sidebar ====== */
const els = {
  backdrop: $('#backdrop'), side: $('#sideNav'),
  openSide: $('#openSidebar'), closeSide: $('#closeSidebar'),
  q: $('#q'), entity: $('#entity'), actor_id: $('#actor_id'), from: $('#from'), to: $('#to'),
  apply: $('#apply'), exportCsv: $('#exportCsv'),
  tbody: $('#tbody'), prev: $('#prev'), next: $('#next'), pageInfo: $('#pageInfo')
};
function showSidebar(b){ els.side.classList[b?'add':'remove']('show'); els.backdrop.classList[b?'add':'remove']('show'); }
els.openSide.addEventListener('click', ()=>showSidebar(true));
els.closeSide.addEventListener('click', ()=>showSidebar(false));
els.backdrop.addEventListener('click', ()=>showSidebar(false));

/* ====== Human summary rules (non-IT) ====== */
function summarize(row){
  const e = row.entity, a = row.action, id = row.entity_id;
  const to = safeJson(row.to_val);

  // 1) Pesanan batal
  if (e==='order' && a==='cancel') {
    const reason = (to && to.reason) ? ` (alasan: ${to.reason})` : '';
    return { activity: `Pesanan dibatalkan${reason}`, target: `Order #${id}` };
  }
  // 2) Pembayaran — tampilkan HANYA ketika menjadi paid/lunas
  if (e==='payment' && (a==='update_status' || a==='mark_paid')) {
    const newStatus = (to && (to.status || to.payment_status)) || (row.to_val || '');
    const isPaid = /(^|\b)paid(\b|$)/i.test(String(newStatus));
    return isPaid ? { activity: `Pembayaran diterima (lunas)`, target: `Payment #${id}` } : null;
  }
  // 3) Notifikasi
  if (e==='user' && a==='send_notification') {
    const dest = (row.to_val||'').replace(/^order#/, 'Order #');
    return { activity: `Notifikasi dikirim`, target: dest || `User #${id}` };
  }
  // 4) Menu diperbarui
  if (e==='menu' && a==='update') {
    return { activity: `Menu diperbarui`, target: `Menu #${id}` };
  }
  // Fallback singkat
  return { activity: `${(a||'Aksi')} pada ${(e||'entitas')}`, target: `${(e||'-')} #${id}` };
}

function shouldShow(row){
  return !!summarize(row); // jika null (tak penting), disembunyikan
}

/* ====== Render ====== */
function render(rows){
  const filtered = (rows||[]).filter(shouldShow);
  state.lastRows = filtered;

  if(!filtered.length){
    els.tbody.innerHTML = `<tr><td colspan="5" class="empty">Tidak ada data</td></tr>`;
    return;
  }

  let html='';
  filtered.forEach(r=>{
    const s = summarize(r);
    // Nama & role jika tersedia dari API (LEFT JOIN users); fallback ID jelas
    const name = r.actor_name ? String(r.actor_name) : `ID #${r.actor_id}`;
    const role = r.actor_role ? String(r.actor_role) : '';

    html += `<tr>
      <td><strong>${h(s.activity)}</strong></td>
      <td><span class="badge">${h(r.entity||'-')}</span> <span class="muted">(${h(s.target)})</span></td>
      <td>
        <div class="actor">
          <span class="avatar">${h(initials(name))}</span>
          <div>
            <div class="name">${h(name)}</div>
            ${role ? `<div class="role">${h(role)}</div>` : (!r.actor_name ? `<div class="idline">Aktor tidak diketahui (log hanya ID)</div>` : '')}
          </div>
        </div>
      </td>
      <td class="muted">${h(fmtDate(r.created_at))}</td>
      <td>${h(r.remark||'—')}</td>
    </tr>`;
  });
  els.tbody.innerHTML = html;
}

function renderPagination(page, total){
  state.page = page; state.total_pages = total;
  els.pageInfo.textContent = `Halaman ${page} / ${total}`;
  els.prev.disabled = page<=1; els.next.disabled = page>=total;
}

/* ====== Fetch ====== */
async function fetchData(){
  const p = new URLSearchParams({ page: state.page, per_page: state.per_page, sort: state.filters.sort });
  for (const k of ['q','entity','actor_id','from','to']) if(state.filters[k]) p.set(k, state.filters[k]);

  els.tbody.innerHTML = `<tr><td colspan="5" class="empty">Memuat data…</td></tr>`;
  try{
    const res = await fetch(`${API}?${p.toString()}`, {credentials:'same-origin'});
    const json = await res.json();
    if(!json.ok) throw new Error(json.error || 'Gagal memuat');
    render(json.data);
    renderPagination(json.pagination.page, json.pagination.total_pages);
  }catch(e){
    els.tbody.innerHTML = `<tr><td colspan="5" class="empty">Error: ${h(e.message)}</td></tr>`;
  }
}

/* ====== Handlers ====== */
let t;
$('#q').addEventListener('input', ()=>{ clearTimeout(t); t=setTimeout(()=>{ state.page=1; state.filters.q=$('#q').value.trim(); fetchData(); }, 350); });
$('#apply').addEventListener('click', ()=>{ state.page=1; state.filters.entity=$('#entity').value; state.filters.actor_id=$('#actor_id').value.trim(); state.filters.from=$('#from').value; state.filters.to=$('#to').value; fetchData(); });
$('#prev').addEventListener('click', ()=>{ if(state.page>1){ state.page--; fetchData(); }});
$('#next').addEventListener('click', ()=>{ if(state.page<state.total_pages){ state.page++; fetchData(); }});
$$('.chip').forEach(ch=> ch.addEventListener('click', ()=>{
  $$('.chip').forEach(c=>c.classList.remove('active')); ch.classList.add('active');
  const days = Number(ch.dataset.range||0);
  if(days>0){
    const now=new Date(); const to=now.toISOString().slice(0,10);
    const from=new Date(now.getTime()-(days-1)*864e5).toISOString().slice(0,10);
    $('#from').value = from; $('#to').value = to;
  } else { $('#from').value=''; $('#to').value=''; }
  state.page=1; state.filters.from=$('#from').value; state.filters.to=$('#to').value; fetchData();
}));

/* ====== Export CSV (yang sedang tampil) ====== */
$('#exportCsv').addEventListener('click', ()=>{
  const rows = state.lastRows || [];
  if (!rows.length){ alert('Tidak ada data.'); return; }
  const headers = ['created_at','entity','entity_id','activity','target','actor_name','actor_role','remark'];
  const data = rows.map(r=>{
    const s = summarize(r) || {activity:'',target:''};
    return {
      created_at: r.created_at,
      entity: r.entity, entity_id: r.entity_id,
      activity: s.activity, target: s.target,
      actor_name: r.actor_name || `ID #${r.actor_id}`,
      actor_role: r.actor_role || '',
      remark: r.remark || ''
    };
  });
  const csv = [headers.join(',')]
    .concat(data.map(o=>headers.map(k=>`"${String(o[k]??'').replace(/"/g,'""')}"`).join(',')))
    .join('\n');
  const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
  const a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download=`audit_logs_${Date.now()}.csv`; a.click(); URL.revokeObjectURL(a.href);
});

/* init */
fetchData();

/* Sidebar toggles */
const openSidebar = document.getElementById('openSidebar');
const closeSidebar = document.getElementById('closeSidebar');
const side = document.getElementById('sideNav');
const backdrop = document.getElementById('backdrop');
function showSidebar(b){ side.classList[b?'add':'remove']('show'); backdrop.classList[b?'add':'remove']('show'); }
openSidebar.addEventListener('click', ()=>showSidebar(true));
closeSidebar.addEventListener('click', ()=>showSidebar(false));
backdrop.addEventListener('click', ()=>showSidebar(false));
</script>
</body>
</html>
