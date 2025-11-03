<?php
// public/admin/help.php
declare(strict_types=1);

require_once __DIR__ . '/../../backend/auth_guard.php';
require_login(['admin']);
require_once __DIR__ . '/../../backend/config.php';

$BASE = BASE_URL;
$name = $_SESSION['user_name'] ?? 'Admin';
$email = $_SESSION['user_email'] ?? '';
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Help Center — Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root{
      --gold:#FFD54F; --gold-soft:#F6D472; --brown:#4B3F36; --ink:#0f172a;
      --bg:#fff; --muted:#6b7280;
      --line:#efe6c8;
      --card-border:#f7d78d;
      --radius:18px;
      --sidebar-w:320px;
    }
    *,:before,:after{ box-sizing:border-box; }
    body{
      background:#FAFAFA; color:var(--ink);
      font-family:Inter,system-ui,Segoe UI,Roboto,Arial; font-weight:500;
      line-height:1.4;
    }

    /* ===== Topbar ===== */
    .topbar{
      position:sticky; top:0; z-index:1035; background:#fff;
      border-bottom:1px solid #f1f1f1;
    }
    .topbar .brand{
      font-family: "Playfair Display", Georgia, serif;
      font-weight:700; letter-spacing:.3px; color:var(--brown);
    }
    .icon-btn{
      width:38px;height:38px;border-radius:50%;
      display:inline-flex;align-items:center;justify-content:center;
      border:1px solid #e5e7eb;background:#fff;cursor:pointer;
      transition:.16s ease;
    }
    .icon-btn:hover{ background:#fff7d1; border-color:var(--gold-soft); }

    .searchbox{
      position:relative; width:min(520px, 100%);
    }
    .searchbox input{
      height:42px; border-radius:999px; padding:0 42px;
      border:1.8px solid #e5e7eb;
    }
    .searchbox input:focus{
      border-color:var(--gold); outline:0; box-shadow:0 0 0 4px rgba(255,213,79,.15);
    }
    .searchbox .bi-search{
      position:absolute; left:14px; top:50%; transform:translateY(-50%); color:#111;
    }

    /* ===== Sidebar (Canvas Off) ===== */
    .sidebar{
      position:fixed; top:0; bottom:0; left:-320px; width:var(--sidebar-w);
      background:#fff; border-right:1px solid #eee; z-index:1040;
      transition:left .25s ease;
      overflow-y:auto; padding:14px 16px 24px;
    }
    .sidebar.open{ left:0; }
    .sidebar-head{
      display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;
    }
    .sidebar-inner-toggle{
      width:32px;height:32px;border-radius:50%;border:0;background:transparent;
      background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 16 16" fill="black"><path d="M2 12.5a.5.5 0 010-1h12a.5.5 0 010 1H2zm0-4a.5.5 0 010-1h12a.5.5 0 010 1H2zm0-4a.5.5 0 010-1h12a.5.5 0 010 1H2z"/></svg>');
      background-repeat:no-repeat;background-position:center; cursor:pointer;
    }
    .sidebar-close-btn{
      background:#fff;border:1px solid #e5e7eb;border-radius:50%;width:32px;height:32px;
      display:flex;align-items:center;justify-content:center;
    }
    .sidebar .nav-link{
      border:1px solid transparent; border-radius:12px; padding:10px 12px; color:#111;
    }
    .sidebar .nav-link:hover{
      background:#fff7d1; border-color:#ffeaa3;
    }
    .sidebar .nav-link.active{
      background:#fff7d1; border-color:#ffe28a;
    }

    /* ===== Page ===== */
    .page{
      max-width:1300px; margin:0 auto; padding:18px 18px 44px;
    }
    .page .title{
      font-weight:700; font-size:26px; color:var(--brown);
    }

    /* ===== Help layout ===== */
    .help-layout{
      display:grid; grid-template-columns: 1fr 320px; gap:24px;
    }
    @media (max-width: 992px){
      .help-layout{ grid-template-columns: 1fr; }
    }
    .toc{
      position:sticky; top:82px; align-self:start;
      background:#fff; border:1px solid var(--card-border);
      border-radius:16px; padding:14px 14px;
    }
    .toc h6{ font-weight:700; font-size:14px; color:#374151; margin-bottom:8px; }
    .toc a{ display:block; padding:8px 10px; border-radius:10px; color:#111; text-decoration:none; }
    .toc a:hover{ background:#fff7d1; }
    .toc a.active{ background:#ffeaa3; }

    .help-card{
      background:#fff; border:1px solid var(--card-border);
      border-radius:16px; padding:16px 16px;
    }
    .help-card h5{ font-weight:700; color:#111; }
    .tag{
      display:inline-block; font-size:12px; padding:4px 8px; border-radius:999px;
      border:1px solid #e5e7eb; margin-right:6px; color:#374151; background:#fafafa;
    }

    /* highlight heading when anchored */
    :target{ scroll-margin-top:90px; }

    /* Footer note */
    .small-note{ color:var(--muted); font-size:13px; }
  </style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar" id="sideNav" aria-label="Sidebar navigasi">
  <div class="sidebar-head">
    <button class="sidebar-inner-toggle" id="toggleSidebarInside" aria-label="Tutup menu"></button>
    <button class="sidebar-close-btn" id="closeSidebar" aria-label="Tutup menu"><i class="bi bi-x-lg"></i></button>
  </div>
  <nav class="nav flex-column gap-2" id="sidebar-nav">
    <a class="nav-link" href="<?= $BASE ?>/public/admin/index.php"><i class="bi bi-house-door"></i> Dashboard</a>
    <a class="nav-link" href="<?= $BASE ?>/public/admin/orders.php"><i class="bi bi-receipt"></i> Orders</a>
    <a class="nav-link" href="<?= $BASE ?>/public/admin/catalog.php"><i class="bi bi-box-seam"></i> Catalog</a>
    <a class="nav-link" href="<?= $BASE ?>/public/admin/users.php"><i class="bi bi-people"></i> Users</a>
    <a class="nav-link" href="<?= $BASE ?>/public/admin/finance.php"><i class="bi bi-cash-coin"></i> Finance</a>
    <a class="nav-link" href="<?= $BASE ?>/public/admin/notifications_send.php"><i class="bi bi-megaphone"></i> Kirim Notifikasi</a>
    <a class="nav-link" href="<?= $BASE ?>/public/admin/settings.php"><i class="bi bi-gear"></i> Settings</a>
    <hr>
    <a class="nav-link active" href="<?= $BASE ?>/public/admin/help.php"><i class="bi bi-question-circle"></i> Help Center</a>
    <a class="nav-link" href="<?= $BASE ?>/backend/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
  </nav>
</aside>

<!-- Topbar -->
<header class="topbar">
  <div class="container-fluid py-2 px-3 d-flex align-items-center gap-2">
    <button class="icon-btn me-1" id="openSidebar" aria-label="Buka menu"><i class="bi bi-list"></i></button>
    <div class="brand me-2">Caffora Admin</div>

    <div class="searchbox ms-auto me-2">
      <i class="bi bi-search"></i>
      <input id="helpSearch" type="search" class="form-control" placeholder="Cari topik bantuan (mis. 'refund', 'export CSV', 'hak akses')">
    </div>

    <button class="icon-btn" title="Pencarian"><i class="bi bi-search"></i></button>
    <button class="icon-btn" title="Notifikasi"><i class="bi bi-bell"></i></button>
    <button class="icon-btn" title="Profil"><i class="bi bi-person-circle"></i></button>
  </div>
</header>

<!-- Page -->
<main class="page">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="title mb-0">Help Center</h1>
    <span class="small-note">Masuk sebagai <strong><?= htmlspecialchars($name) ?></strong></span>
  </div>

  <div class="help-layout">
    <!-- Left: Content -->
 

     



    <!-- Right: TOC -->
    <nav class="toc" id="toc">
      <h6>Daftar Topik</h6>
      <a href="#mulai">Mulai Cepat</a>
      <a href="#dashboard">Dashboard</a>
      <a href="#orders">Orders</a>
      <a href="#catalog">Catalog</a>
      <a href="#users">Users</a>
      <a href="#finance">Finance</a>
      <a href="#notif">Kirim Notifikasi</a>
      <a href="#settings">Settings</a>
      <a href="#faq">FAQ</a>
      <a href="#shortcuts">Shortcuts</a>
      <a href="#support">Dukungan</a>
    </nav>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // ===== Sidebar open/close (canvas off)
  const body = document.body;
  const sideNav = document.getElementById('sideNav');
  const openSidebar = document.getElementById('openSidebar');
  const closeSidebar = document.getElementById('closeSidebar');
  const toggleInside = document.getElementById('toggleSidebarInside');

  function openSide(){ sideNav.classList.add('open'); body.style.overflow='hidden'; }
  function closeSide(){ sideNav.classList.remove('open'); body.style.overflow=''; }

  openSidebar?.addEventListener('click', openSide);
  closeSidebar?.addEventListener('click', closeSide);
  toggleInside?.addEventListener('click', closeSide);
  // tutup saat klik luar
  document.addEventListener('click', (e)=>{
    if(sideNav.classList.contains('open')){
      const inside = e.target.closest('#sideNav, #openSidebar');
      if(!inside) closeSide();
    }
  });
  // keyboard
  document.addEventListener('keydown', (e)=>{
    if(e.key === 'Escape') closeSide();
    if(e.altKey && (e.key==='h' || e.key==='H')) {
      if(sideNav.classList.contains('open')) closeSide(); else openSide();
    }
    if(e.key === '/' && document.activeElement.tagName !== 'INPUT'){
      e.preventDefault(); document.getElementById('helpSearch').focus();
    }
    if(e.shiftKey && (e.key==='K' || e.key==='k')){
      location.hash = '#faq';
    }
  });

  // ===== TOC active highlight while scrolling
  const tocLinks = Array.from(document.querySelectorAll('#toc a'));
  const sections = tocLinks.map(a => document.querySelector(a.getAttribute('href'))).filter(Boolean);

  const obs = new IntersectionObserver((entries)=>{
    entries.forEach(entry=>{
      const id = '#'+entry.target.id;
      const link = document.querySelector('#toc a[href="'+id+'"]');
      if(entry.isIntersecting){
        tocLinks.forEach(l=>l.classList.remove('active'));
        link?.classList.add('active');
      }
    });
  }, { rootMargin: '-40% 0px -55% 0px', threshold: 0 });

  sections.forEach(sec=>obs.observe(sec));

  // ===== In-page search filter
  const searchInput = document.getElementById('helpSearch');
  const helpCards = Array.from(document.querySelectorAll('#helpContent .help-card'));
  const origDisplay = new Map(helpCards.map(c=>[c, c.style.display || '']));

  searchInput.addEventListener('input', ()=>{
    const q = searchInput.value.trim().toLowerCase();
    if(!q){
      helpCards.forEach(c=>c.style.display = origDisplay.get(c));
      return;
    }
    helpCards.forEach(c=>{
      const txt = c.innerText.toLowerCase();
      c.style.display = txt.includes(q) ? origDisplay.get(c) : 'none';
    });
  });
</script>
</body>
</html>
