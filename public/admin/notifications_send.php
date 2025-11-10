<?php
// public/admin/notifications_send.php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../../backend/auth_guard.php';
require_login(['admin']);
require_once __DIR__ . '/../../backend/config.php';

// ambil daftar user untuk opsi “user tertentu”
$users = [];
if ($res = $conn->query("SELECT id, name, email, role FROM users ORDER BY role, name")) {
  $users = $res->fetch_all(MYSQLI_ASSOC);
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Kirim Notifikasi — Admin Panel</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://code.iconify.design/2/2.2.1/iconify.min.js"></script>

  <style>
    :root{
      --gold:#FFD54F;
      --gold-border:#f7d78d;
      --brown:#4B3F36;
      --ink:#0f172a;
      --radius:18px;
      --sidebar-w:320px;
      --soft:#fff7d1;
      --hover:#ffefad;
      --line:#E8E2DA;
      --btn-radius:14px;
    }
    *,:before,:after{ box-sizing:border-box; }
    html,body{ height:100%; }
    body{
      background:#FAFAFA; color:var(--ink);
      font-family:Inter,system-ui,Segoe UI,Roboto,Arial;
      -webkit-tap-highlight-color:transparent;
    }

    /* ===== Sidebar (match Finance/Audit) ===== */
    .sidebar{
      position:fixed; left:-320px; top:0; bottom:0; width:var(--sidebar-w);
      background:#fff; border-right:1px solid rgba(0,0,0,.05);
      transition:left .25s ease; z-index:1050; padding:16px 18px; overflow-y:auto;
    }
    .sidebar.show{ left:0; }
    .sidebar-head{ display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:10px; }
    .sidebar-inner-toggle, .sidebar-close-btn{ background:transparent; border:0; width:40px; height:36px; display:grid; place-items:center; }
    .hamb-icon{ width:24px; height:20px; display:flex; flex-direction:column; justify-content:space-between; gap:4px; }
    .hamb-icon span{ height:2px; background:var(--brown); border-radius:99px; }
    .sidebar .nav-link{ display:flex; align-items:center; gap:12px; padding:12px 14px; border-radius:16px; font-weight:600; color:#111; text-decoration:none; }
    .sidebar .nav-link:hover{ background:rgba(255,213,79,0.25); color:#111; }
    .sidebar hr{ border-color:rgba(0,0,0,.05); opacity:1; }

    /* Backdrop mobile */
    .backdrop-mobile{ position:fixed; inset:0; background:rgba(0,0,0,.25); z-index:1040; display:none; }
    .backdrop-mobile.active{ display:block; }

    /* ===== Content + Topbar (match Finance) ===== */
    .content{ margin-left:0; padding:16px 14px 50px; }
    .topbar{ display:flex; align-items:center; gap:12px; margin-bottom:16px; }
    .btn-menu{ background:transparent; border:0; width:40px; height:38px; display:grid; place-items:center; }
    .search-box{ position:relative; flex:1 1 420px; min-width:220px; }
    .search-input{
      height:46px; width:100%; border-radius:9999px; padding-left:16px; padding-right:44px;
      border:1px solid #e5e7eb; background:#fff; outline:none; transition:all .12s;
    }
    .search-input:focus{ border-color:var(--gold); box-shadow:none; }
    .search-icon{ position:absolute; right:16px; top:50%; transform:translateY(-50%); color:var(--brown); cursor:pointer; }
    .search-suggest{
      position:absolute; top:100%; left:0; margin-top:6px; background:#fff; border:1px solid rgba(247,215,141,.8);
      border-radius:16px; width:100%; max-height:280px; overflow-y:auto; display:none; z-index:40;
    }
    .search-suggest.visible{ display:block; }
    .search-empty{ padding:12px 14px; color:#6b7280; font-size:.8rem; }

    .top-actions{ display:flex; align-items:center; gap:14px; flex:0 0 auto; }
    .icon-btn{ width:38px; height:38px; border-radius:999px; display:flex; align-items:center; justify-content:center; color:var(--brown); text-decoration:none; background:transparent; outline:none; }

    /* ===== Cards & Form ===== */
    .cardx{ background:#fff; border:1px solid var(--gold-border); border-radius:var(--radius); padding:18px; }
    .form-label{ font-weight:600; margin-bottom:.35rem; }
    .form-control,.form-select{
      height:46px; border-radius:14px !important; border:1px solid var(--line) !important;
      background:#fff !important; color:#111 !important; box-shadow:none !important; outline:0 !important;
    }
    .form-control:focus{ border-color:var(--gold) !important; }
    textarea.form-control{ min-height:120px; resize:vertical; }

    /* ===== Buttons (enterprise look) ===== */
    .btn-primary-enterprise{
      background-color: var(--gold); color: var(--brown);
      border:0; border-radius: var(--btn-radius);
      font-family: Arial, Helvetica, sans-serif; font-weight:700; font-size:.95rem;
      padding:10px 18px; display:inline-flex; align-items:center; gap:8px; line-height:1;
    }
    .btn-primary-enterprise:hover{ filter:brightness(.97); color:var(--brown); }

    /* radio & checkbox accent */
    input[type="radio"], input[type="checkbox"]{ accent-color:var(--gold); cursor:pointer; }

    /* ===== cf-select (custom select user) ===== */
    .cf-select{ position:relative; width:100%; }
    .cf-select__trigger{
      width:100%; background:#fff; border:1px solid var(--line); border-radius:14px;
      padding:8px 38px 8px 14px; display:flex; align-items:center; justify-content:space-between; gap:12px;
      cursor:pointer; user-select:none; transition:border-color .12s ease;
    }
    .cf-select__trigger:focus-visible, .cf-select.is-open .cf-select__trigger{ border-color:var(--gold); outline:0; }
    .cf-select__text{ font-size:.95rem; color:#2b2b2b; white-space:nowrap; text-overflow:ellipsis; overflow:hidden; }
    .cf-select__icon{ color:var(--brown); font-size:.9rem; }
    .cf-select__list{
      position:absolute; left:0; top:calc(100% + 6px); width:100%; background:#fff;
      border:1px solid rgba(247,215,141,.9); border-radius:14px; box-shadow:0 16px 30px rgba(0,0,0,.08);
      overflow:hidden; display:none; max-height:260px; overflow-y:auto; z-index:40;
    }
    .cf-select.is-open .cf-select__list{ display:block; }
    .cf-select__option{ padding:10px 14px; font-size:.9rem; color:#413731; cursor:pointer; }
    .cf-select__option:hover{ background:var(--hover); }
    .cf-select__option.is-active{ background:var(--soft); font-weight:600; }
    .cf-select.is-disabled .cf-select__trigger{ background:#f9fafb; border-color:#ececec; color:#9ca3af; cursor:not-allowed; }
    .cf-select.is-disabled .cf-select__icon{ color:#c1c1c1; }

    /* helper row */
    .helper{ color:#6b7280; font-size:.85rem; }

    /* ===== Responsive ===== */
    @media (min-width:992px){
      .content{ padding-left:60px !important; padding-right:60px !important; padding-top:20px; padding-bottom:60px; }
      .search-box{ max-width:1100px !important; }
    }
    @media (min-width:1200px){
      .content{ padding-left:10px !important; padding-right:10px !important; }
    }
    @media (max-width:575.98px){
      .content{ padding:14px 12px 70px; }
      .topbar{ gap:10px; }
      .search-box{ min-width:0; width:100%; order:2; flex:1 1 100%; }
      .top-actions{ order:3; }
      .btn-menu{ order:1; }
      .cardx{ padding:16px; }
      .btn-primary-enterprise{ width:100%; justify-content:center; height:44px; }
    }
  </style>
</head>
<body>

<div id="backdrop" class="backdrop-mobile"></div>

<!-- sidebar -->
<aside class="sidebar" id="sideNav">
  <div class="sidebar-head">
    <button class="sidebar-inner-toggle" id="toggleSidebarInside" aria-label="Tutup menu"></button>
    <button class="sidebar-close-btn" id="closeSidebar" aria-label="Tutup menu"><i class="bi bi-x-lg"></i></button>
  </div>

  <nav class="nav flex-column gap-2" id="sidebar-nav">
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/index.php"><i class="bi bi-house-door"></i> Dashboard</a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/orders.php"><i class="bi bi-receipt"></i> Orders</a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/catalog.php"><i class="bi bi-box-seam"></i> Catalog</a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/users.php"><i class="bi bi-people"></i> Users</a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/finance.php"><i class="bi bi-cash-coin"></i> Finance</a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/notifications_send.php"><i class="bi bi-megaphone"></i> Kirim Notifikasi</a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/audit.php"><i class="bi bi-shield-check"></i> Audit Log</a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/settings.php"><i class="bi bi-gear"></i> Settings</a>
    <hr>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/help.php"><i class="bi bi-question-circle"></i> Help Center</a>
    <a class="nav-link" href="<?= BASE_URL ?>/backend/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
  </nav>
</aside>

<!-- Content -->
<main class="content">
  <!-- Topbar -->
  <div class="topbar">
    <button class="btn-menu" id="openSidebar" aria-label="Buka menu">
      <div class="hamb-icon"><span></span><span></span><span></span></div>
    </button>

    <div class="search-box">
      <input class="search-input" id="searchInput" placeholder="Search..." autocomplete="off" />
      <i class="bi bi-search search-icon" id="searchIcon" aria-hidden="true"></i>
      <div class="search-suggest" id="searchSuggest"></div>
    </div>

    <div class="top-actions">
      <a id="btnBell" class="icon-btn position-relative text-decoration-none" href="<?= BASE_URL ?>/public/admin/notifications.php" aria-label="Notifikasi">
        <span class="iconify" data-icon="mdi:bell-outline" data-width="24" data-height="24"></span>
        <span id="badgeNotif" class="d-none" style="position:absolute;top:3px;right:3px;width:8px;height:8px;background:#4b3f36;border-radius:999px;box-shadow:0 0 0 1.5px #fff;"></span>
      </a>
      <a class="icon-btn text-decoration-none" href="<?= BASE_URL ?>/public/admin/settings.php" aria-label="Akun">
        <span class="iconify" data-icon="mdi:account-circle-outline" data-width="28" data-height="28"></span>
      </a>
    </div>
  </div>

  <h2 class="fw-bold mb-1">Kirim Notifikasi</h2>
  <div class="helper mb-3">Kirim pengumuman, promo, atau pesan operasional ke pengguna Caffora.</div>

  <div class="cardx">
    <form id="notifForm" class="vstack gap-3">
      <!-- Target -->
      <div>
        <label class="form-label">Target</label>
        <div class="d-flex flex-wrap gap-4">
          <div class="form-check">
            <input class="form-check-input" type="radio" name="target_type" id="tAll"  value="all" checked>
            <label class="form-check-label" for="tAll">Semua (Customer & Karyawan)</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="target_type" id="tRoleC" value="role_customer">
            <label class="form-check-label" for="tRoleC">Role: Customer</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="target_type" id="tRoleK" value="role_karyawan">
            <label class="form-check-label" for="tRoleK">Role: Karyawan</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="target_type" id="tUser" value="user">
            <label class="form-check-label" for="tUser">User tertentu</label>
          </div>
        </div>

        <!-- cf-select user tertentu -->
        <div class="mt-2">
          <div class="cf-select is-disabled" id="userSelect" data-target="target_user">
            <div class="cf-select__trigger" tabindex="0" aria-disabled="true">
              <span class="cf-select__text" id="user_label">Pilih user</span>
              <i class="bi bi-chevron-down cf-select__icon"></i>
            </div>
            <div class="cf-select__list" id="user_list" role="listbox" aria-label="Daftar user">
              <div class="cf-select__option is-active" data-value="">Pilih user</div>
              <?php foreach ($users as $u):
                $label = ($u['name'] ?: $u['email']).' — '.$u['role']; ?>
                <div class="cf-select__option" data-value="<?= (int)$u['id'] ?>">
                  <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <input type="hidden" id="target_user" value="">
          <div class="helper mt-1">Aktif saat memilih “User tertentu”.</div>
        </div>
      </div>

      <!-- Pesan -->
      <div>
        <label class="form-label">Pesan</label>
        <textarea id="message" class="form-control" rows="4" maxlength="500" placeholder="Tulis pesan (mis. promo, pengumuman…)" required></textarea>
        <div class="helper mt-1"><span id="charLeft">500</span> karakter tersisa.</div>
      </div>

      <!-- Link -->
      <div>
        <label class="form-label">Link (opsional)</label>
        <input id="link" type="url" class="form-control" placeholder="https://...">
        <div class="helper mt-1">Jika diisi, penerima dapat membuka tautan ini dari notifikasi.</div>
      </div>

      <!-- Submit -->
      <div class="d-flex gap-2 flex-wrap">
        <button class="btn-primary-enterprise" type="submit">
          <i class="bi bi-send"></i><span>Kirim</span>
        </button>
        <span id="result" class="align-self-center small text-muted"></span>
      </div>
    </form>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const BASE = "<?= rtrim(BASE_URL, '/') ?>";

  /* ===== Sidebar ===== */
  const sideNav  = document.getElementById('sideNav');
  const backdrop = document.getElementById('backdrop');
  document.getElementById('openSidebar')?.addEventListener('click', ()=>{ sideNav.classList.add('show'); backdrop.classList.add('active'); });
  document.getElementById('closeSidebar')?.addEventListener('click', ()=>{ sideNav.classList.remove('show'); backdrop.classList.remove('active'); });
  backdrop?.addEventListener('click', ()=>{ sideNav.classList.remove('show'); backdrop.classList.remove('active'); });
  document.querySelectorAll('#sidebar-nav .nav-link').forEach(a=>{
    a.addEventListener('click',function(){
      document.querySelectorAll('#sidebar-nav .nav-link').forEach(l=>l.classList.remove('active'));
      this.classList.add('active');
      if (window.innerWidth < 1200){ sideNav.classList.remove('show'); backdrop.classList.remove('active'); }
    });
  });

  /* ===== Notif badge (refresh berkala) ===== */
  async function refreshAdminNotifBadge(){
    const badge = document.getElementById('badgeNotif'); if (!badge) return;
    try{
      const r = await fetch(BASE + "/backend/api/notifications.php?action=unread_count",{credentials:"same-origin"});
      if(!r.ok) return; const js = await r.json();
      const c = Number(js.count||0); badge.classList.toggle('d-none', !(c>0));
    }catch(e){}
  }
  refreshAdminNotifBadge(); setInterval(refreshAdminNotifBadge, 30000);

  /* ===== cf-select ===== */
  (function initCfSelect(){
    const wrap    = document.getElementById('userSelect');
    const trigger = wrap.querySelector('.cf-select__trigger');
    const list    = wrap.querySelector('.cf-select__list');
    const label   = document.getElementById('user_label');
    const hidden  = document.getElementById('target_user');

    function toggleOpen(){ if (!wrap.classList.contains('is-disabled')) wrap.classList.toggle('is-open'); }
    trigger.addEventListener('click', (e)=>{ e.stopPropagation(); toggleOpen(); });
    trigger.addEventListener('keydown', (e)=>{ if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleOpen(); }});
    document.addEventListener('click', ()=> wrap.classList.remove('is-open'));

    list.querySelectorAll('.cf-select__option').forEach(opt=>{
      opt.addEventListener('click', ()=>{
        const val = opt.dataset.value || '';
        hidden.value = val;
        label.textContent = opt.textContent.trim();
        list.querySelectorAll('.cf-select__option').forEach(o=>o.classList.remove('is-active'));
        opt.classList.add('is-active');
        wrap.classList.remove('is-open');
      });
    });

    const rAll  = document.getElementById('tAll');
    const rRC   = document.getElementById('tRoleC');
    const rRK   = document.getElementById('tRoleK');
    const rUser = document.getElementById('tUser');
    function updateUserSelect(){
      const dis = !rUser.checked;
      if (dis){
        wrap.classList.add('is-disabled'); wrap.classList.remove('is-open');
        hidden.value = ''; label.textContent = 'Pilih user';
        list.querySelectorAll('.cf-select__option').forEach(o=>o.classList.remove('is-active'));
        list.querySelector('.cf-select__option[data-value=""]')?.classList.add('is-active');
        trigger.setAttribute('aria-disabled','true');
      }else{
        wrap.classList.remove('is-disabled');
        trigger.removeAttribute('aria-disabled');
      }
    }
    [rAll,rRC,rRK,rUser].forEach(r => r.addEventListener('change', updateUserSelect));
    updateUserSelect();
  })();

  /* ===== Counter karakter pesan ===== */
  (function(){
    const ta = document.getElementById('message');
    const out= document.getElementById('charLeft');
    function upd(){ out.textContent = Math.max(0, (ta.maxLength || 500) - ta.value.length); }
    ta.addEventListener('input', upd); upd();
  })();

  /* ===== Submit ===== */
  document.getElementById('notifForm').addEventListener('submit', async (e)=>{
    e.preventDefault();
    const msg  = document.getElementById('message').value.trim();
    const link = document.getElementById('link').value.trim();
    if (!msg) { alert('Pesan wajib diisi'); return; }

    const rAll  = document.getElementById('tAll').checked;
    const rRC   = document.getElementById('tRoleC').checked;
    const rRK   = document.getElementById('tRoleK').checked;
    const rUser = document.getElementById('tUser').checked;
    const uid   = document.getElementById('target_user').value.trim();

    const fd = new FormData();
    fd.append('action','create');
    if (rAll) {
      fd.append('target_type','all');
    } else if (rRC) {
      fd.append('target_type','role'); fd.append('target_role','customer');
    } else if (rRK) {
      fd.append('target_type','role'); fd.append('target_role','karyawan');
    } else if (rUser) {
      if (!uid) { alert('Pilih user'); return; }
      fd.append('target_type','user'); fd.append('target_user', uid);
    }
    fd.append('message', msg);
    fd.append('link', link);

    const $res = document.getElementById('result');
    $res.textContent = 'Mengirim…';
    try{
      const res  = await fetch(BASE + '/backend/api/notifications.php', { method:'POST', credentials:'same-origin', body: fd });
      const data = await res.json();
      if (data.ok) {
        $res.textContent = 'Sukses ✓'; $res.style.color = '#16a34a';
        setTimeout(()=>{$res.textContent=''; $res.removeAttribute('style');}, 1500);
        (e.target).reset();
        document.getElementById('charLeft').textContent = (document.getElementById('message').maxLength || 500);
        // reset cf-select
        document.getElementById('target_user').value = '';
        document.getElementById('user_label').textContent = 'Pilih user';
        document.querySelectorAll('#user_list .cf-select__option').forEach(o=>o.classList.remove('is-active'));
        document.querySelector('#user_list .cf-select__option[data-value=""]')?.classList.add('is-active');
        // default kembali ke “Semua”
        document.getElementById('tAll').checked = true;
        document.getElementById('tAll').dispatchEvent(new Event('change'));
      } else {
        $res.textContent = data.error || 'Gagal mengirim.';
        $res.style.color = '#b91c1c';
      }
    }catch(err){
      $res.textContent = 'Gagal mengirim.'; $res.style.color = '#b91c1c';
    }
  });

  /* ===== SEARCH (admin → fallback karyawan) ===== */
  function attachSearch(inputEl, suggestEl){
    if (!inputEl || !suggestEl) return;
    const ADMIN_EP = BASE + "/backend/api/admin_search.php";
    const KARY_EP  = BASE + "/backend/api/karyawan_search.php";
    async function fetchResults(q){
      try { const r = await fetch(ADMIN_EP+"?q="+encodeURIComponent(q)); if(r.ok) return await r.json(); } catch(e){}
      try { const r2= await fetch(KARY_EP +"?q="+encodeURIComponent(q)); if(r2.ok) return await r2.json(); } catch(e){}
      return {ok:true,results:[]};
    }
    inputEl.addEventListener('input', async function(){
      const q = this.value.trim();
      if (q.length < 2){ suggestEl.classList.remove('visible'); suggestEl.innerHTML=''; return; }
      const data = await fetchResults(q);
      const arr = Array.isArray(data.results)?data.results:[];
      if (!arr.length){ suggestEl.innerHTML='<div class="search-empty">Tidak ada hasil.</div>'; suggestEl.classList.add('visible'); return; }
      let html=''; arr.forEach(r=>{ html += `<div class="item" data-type="${r.type}" data-key="${r.key}">${r.label ?? ''} ${r.sub ? `<small>${r.sub}</small>` : ''}</div>`; });
      suggestEl.innerHTML = html; suggestEl.classList.add('visible');
      suggestEl.querySelectorAll('.item').forEach(it=>{
        it.addEventListener('click',()=>{
          const type = it.dataset.type, key = it.dataset.key;
          if (type==='order')      window.location = BASE + "/public/admin/orders.php?search="+encodeURIComponent(key);
          else if (type==='menu')  window.location = BASE + "/public/admin/catalog.php?search="+encodeURIComponent(key);
          else if (type==='user')  window.location = BASE + "/public/admin/users.php?search="+encodeURIComponent(key);
          else                     window.location = BASE + "/public/admin/orders.php?search="+encodeURIComponent(key);
        });
      });
    });
    document.addEventListener('click',(ev)=>{ if(!suggestEl.contains(ev.target) && ev.target!==inputEl){ suggestEl.classList.remove('visible'); }});
    document.getElementById('searchIcon')?.addEventListener('click',()=>inputEl.focus());
  }
  attachSearch(document.getElementById('searchInput'), document.getElementById('searchSuggest'));
</script>
</body>
</html>
