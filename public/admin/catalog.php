<?php
// public/admin/catalog.php
declare(strict_types=1);

require_once __DIR__ . '/../../backend/config.php';
require_once __DIR__ . '/../../backend/auth_guard.php';
require_once __DIR__ . '/../../backend/helpers.php';
require_login(['admin']);

// fallback helper jika belum ada
if (!function_exists('h')) {
  function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('rupiah')) {
  function rupiah($n): string { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }
}

$msg = $_GET['msg'] ?? '';

/* ===== ACTIONS (CRUD) ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $act   = $_POST['action'] ?? '';
  $id    = (int)($_POST['id'] ?? 0);
  $name  = trim((string)($_POST['name'] ?? ''));
  $cat   = strtolower(trim((string)($_POST['category'] ?? '')));
  $cat   = in_array($cat, ['food','pastry','drink'], true) ? $cat : 'food';
  $price = (float)($_POST['price'] ?? 0);
  $stat  = ($_POST['stock_status'] ?? 'Ready') === 'Sold Out' ? 'Sold Out' : 'Ready';

  // upload gambar (opsional)
  $imagePath = null;
  if (!empty($_FILES['image']['name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg','jpeg','png','webp'], true)) {
      $updir = __DIR__ . '/../../public/uploads/menu';
      if (!is_dir($updir)) {
        @mkdir($updir, 0777, true);
      }
      $new = 'm_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
      if (move_uploaded_file($_FILES['image']['tmp_name'], $updir . '/' . $new)) {
        $imagePath = 'uploads/menu/' . $new;
      }
    }
  }

  if ($act === 'add') {
    $stmt = $conn->prepare(
      "INSERT INTO menu(name,category,image,price,stock_status) VALUES (?,?,?,?,?)"
    );
    $stmt->bind_param('sssds', $name, $cat, $imagePath, $price, $stat);
    $ok = $stmt->execute();
    $stmt->close();
    header('Location: ' . $_SERVER['PHP_SELF'] . '?msg=' . urlencode(
      $ok ? 'Menu ditambahkan.' : 'Gagal menambah menu.'
    ));
    exit;
  }

  if ($act === 'edit' && $id > 0) {
    if ($imagePath) {
      $stmt = $conn->prepare(
        "UPDATE menu
         SET name=?, category=?, image=?, price=?, stock_status=?
         WHERE id=?"
      );
      $stmt->bind_param('sssdsi', $name, $cat, $imagePath, $price, $stat, $id);
    } else {
      $stmt = $conn->prepare(
        "UPDATE menu
         SET name=?, category=?, price=?, stock_status=?
         WHERE id=?"
      );
      $stmt->bind_param('ssdsi', $name, $cat, $price, $stat, $id);
    }
    $ok = $stmt->execute();
    $stmt->close();
    header('Location: ' . $_SERVER['PHP_SELF'] . '?msg=' . urlencode(
      $ok ? 'Menu diperbarui.' : 'Gagal mengedit menu.'
    ));
    exit;
  }
}

/* ===== DELETE ===== */
if (isset($_GET['delete'])) {
  $id   = (int)$_GET['delete'];
  $stmt = $conn->prepare("DELETE FROM menu WHERE id=?");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $stmt->close();
  header('Location: ' . $_SERVER['PHP_SELF'] . '?msg=' . urlencode('Menu dihapus.'));
  exit;
}

/* ===== DATA ===== */
$res   = $conn->query("SELECT * FROM menu ORDER BY created_at DESC");
$menus = $res?->fetch_all(MYSQLI_ASSOC) ?? [];
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Catalog — Admin Desk</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
  >
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    rel="stylesheet"
  >
  <script src="https://code.iconify.design/2/2.2.1/iconify.min.js"></script>
  <style>
   :root{                                         /* Root variable */
      --gold:#FFD54F;                          /* Warna emas utama */
      --gold-soft:#F6D472;                     /* Warna emas lembut */
      --brown:#4B3F36;                         /* Warna coklat */
      --ink:#111827;                           /* Warna teks utama */
      --muted:#6B7280;                         /* Warna teks muted */
      --radius:18px;                           /* Radius global */
      --sidebar-w:320px;                       /* Lebar sidebar */
      --input-border:#E8E2DA;                  /* Border input */
      --bg:#FAFAFA;                            /* Background halaman */
      --btn-radius:14px;                       /* Radius tombol */
    }
    *{                                         /* Global reset */
      box-sizing:border-box;                   /* Box model */
      font-family:Poppins,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif; /* Font */
    }
    body{                                      /* Body */
      background:var(--bg);                    /* Background */
      color:var(--ink);                        /* Warna teks */
      margin:0;                                /* Reset margin */
    }

    /* ===== SIDEBAR ===== */
    .sidebar{                                  /* Sidebar utama */
      position:fixed;                          /* Posisi fixed */
      left:-320px;                             /* Sembunyi kiri */
      top:0;                                   /* Atas */
      bottom:0;                                /* Bawah */
      width:var(--sidebar-w);                  /* Lebar sidebar */
      background:#fff;                         /* Warna latar */
      border-right:1px solid rgba(0,0,0,.05);  /* Garis kanan */
      transition:left .25s ease;               /* Animasi geser */
      z-index:1050;                            /* Layer */
      padding:16px 18px;                       /* Padding */
      overflow-y:auto;                         /* Scroll vertikal */
    }
    .sidebar.show{ left:0; }                   /* Sidebar aktif */
    .sidebar-head{                             /* Header sidebar */
      display:flex;                            /* Flex */
      align-items:center;                      /* Tengah vertikal */
      justify-content:space-between;           /* Spasi kiri-kanan */
      gap:10px;                                /* Jarak */
      margin-bottom:10px;                      /* Margin bawah */
    }
    .sidebar-inner-toggle,
    .sidebar-close-btn{                        /* Tombol sidebar */
      background:transparent;                  /* Transparan */
      border:0;                                /* Tanpa border */
      width:40px;                              /* Lebar */
      height:36px;                             /* Tinggi */
      display:grid;                            /* Grid */
      place-items:center;                     /* Tengah */
    }
    .hamb-icon{                                /* Icon hamburger */
      width:24px;                              /* Lebar */
      height:20px;                             /* Tinggi */
      display:flex;                            /* Flex */
      flex-direction:column;                  /* Vertikal */
      justify-content:space-between;           /* Spasi */
      gap:4px;                                 /* Jarak */
    }
    .hamb-icon span{                           /* Garis icon */
      height:2px;                              /* Tinggi garis */
      background:var(--brown);                 /* Warna garis */
      border-radius:999px;                     /* Rounded */
    }
    .sidebar .nav-link{                        /* Link sidebar */
      display:flex;                            /* Flex */
      align-items:center;                     /* Tengah */
      gap:12px;                                /* Jarak */
      padding:12px 14px;                      /* Padding */
      border-radius:16px;                     /* Radius */
      font-weight:600;                        /* Tebal */
      color:#111;                             /* Warna teks */
      text-decoration:none;                   /* Tanpa underline */
    }
    .sidebar .nav-link:hover{                  /* Hover link */
      background:rgba(255,213,79,.25);        /* Background hover */
    }
    .sidebar hr{                               /* Garis pemisah */
      border-color:rgba(0,0,0,.05);           /* Warna */
      opacity:1;                              /* Opacity */
    }

    /* ===== BACKDROP ===== */
    .backdrop-mobile{                          /* Backdrop */
      display:none;                            /* Hidden */
    }
    .backdrop-mobile.active{                   /* Backdrop aktif */
      display:block;                           /* Tampil */
      position:fixed;                          /* Fixed */
      inset:0;                                 /* Full layar */
      background:rgba(0,0,0,.35);              /* Overlay */
      z-index:1040;                            /* Layer */
    }

    /* ===== TOPBAR & CONTENT ===== */
    .content{                                  /* Konten */
      margin-left:0;                           /* Reset margin */
      padding:16px 14px 50px;                  /* Padding */
    }
    .topbar{                                   /* Topbar */
      display:flex;                            /* Flex */
      align-items:center;                     /* Tengah */
      gap:12px;                                /* Jarak */
      margin-bottom:16px;                     /* Margin bawah */
    }
    .btn-menu{                                 /* Tombol menu */
      background:transparent;                  /* Transparan */
      border:0;                                /* Tanpa border */
      width:40px;                              /* Lebar */
      height:38px;                             /* Tinggi */
      display:grid;                            /* Grid */
      place-items:center;                     /* Tengah */
    }

    /* ===== RESPONSIVE ===== */
    @media (min-width:992px){                  /* Tablet+ */
      .content{
        padding:20px 26px 60px;                /* Padding besar */
      }
      .search-box{
        max-width:1100px;                      /* Lebar search */
      }
    }
    @media (min-width:1200px){                 /* Desktop */
      .content{
        padding-left:10px !important;          /* Padding kiri */
        padding-right:10px !important;         /* Padding kanan */
      }
    }
    @media (min-width:370px){                  /* HP medium */
      .content{
        padding-left:8px !important;           /* Padding kiri */
        padding-right:8px !important;          /* Padding kanan */
      }
      .topbar{
        padding-left:4px !important;           /* Padding kiri */
        padding-right:4px !important;          /* Padding kanan */
      }
    }
    @media (max-width:575.98px){               /* HP */
      .content{
        padding:16px 14px 70px;                /* Padding */
      }
      .topbar{
        padding:8px 0;                          /* Padding */
        gap:10px;                               /* Jarak */
      }
      .search-box{
        min-width:0;                           /* Reset */
        width:100%;                            /* Full */
        flex:1 1 100%;                         /* Flex */
      }
    }
    @media (max-width:360px){                  /* HP kecil */
      .icon-btn{
        width:34px;                            /* Lebar */
        height:34px;                           /* Tinggi */
      }
      .search-input{
        height:40px;                           /* Tinggi */
        padding-left:12px;                     /* Padding kiri */
        padding-right:38px;                    /* Padding kanan */
      }
    }

  </style>
</head>
<body>

<div id="backdrop" class="backdrop-mobile"></div>

<!-- sidebar -->
<aside class="sidebar" id="sideNav">
  <div class="sidebar-head">
    <button
      class="sidebar-inner-toggle"
      id="toggleSidebarInside"
      aria-label="Tutup menu"
    ></button>
    <button
      class="sidebar-close-btn"
      id="closeSidebar"
      aria-label="Tutup menu"
    >
      <i class="bi bi-x-lg"></i>
    </button>
  </div>

  <nav class="nav flex-column gap-2" id="sidebar-nav">
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/index.php">
      <i class="bi bi-house-door"></i> Dashboard
    </a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/orders.php">
      <i class="bi bi-receipt"></i> Orders
    </a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/catalog.php">
      <i class="bi bi-box-seam"></i> Catalog
    </a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/users.php">
      <i class="bi bi-people"></i> Users
    </a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/finance.php">
      <i class="bi bi-cash-coin"></i> Finance
    </a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/notifications_send.php">
      <i class="bi bi-megaphone"></i> Notifications
    </a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/audit.php">
      <i class="bi bi-shield-check"></i> Audit Log
    </a>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/settings.php">
      <i class="bi bi-gear"></i> Settings
    </a>
    <hr>
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/help.php">
      <i class="bi bi-question-circle"></i> Help Center
    </a>
    <a class="nav-link" href="<?= BASE_URL ?>/backend/logout.php">
      <i class="bi bi-box-arrow-right"></i> Logout
    </a>
  </nav>
</aside>

<!-- content -->
<main class="content">
  <div class="container-xxl px-2 px-xl-3">

    <!-- topbar -->
    <div class="topbar">
      <button class="btn-menu" id="openSidebar" aria-label="Buka menu">
        <div class="hamb-icon">
          <span></span><span></span><span></span>
        </div>
      </button>

      <div class="search-box">
        <input
          class="search-input"
          id="searchInput"
          placeholder="Search..."
          autocomplete="off"
        >
        <i class="bi bi-search search-icon" id="searchIcon"></i>
      </div>

      <div class="d-flex align-items-center gap-2">
        <a
          id="btnBell"
          class="icon-btn position-relative text-decoration-none"
          href="<?= BASE_URL ?>/public/admin/notifications.php"
          aria-label="Notifikasi"
        >
          <span
            class="iconify"
            data-icon="mdi:bell-outline"
            data-width="24"
            data-height="24"
          ></span>
          <span id="badgeNotif" class="notif-dot d-none"></span>
        </a>
        <a
          class="icon-btn text-decoration-none"
          href="<?= BASE_URL ?>/public/admin/settings.php"
          aria-label="Akun"
        >
          <span
            class="iconify"
            data-icon="mdi:account-circle-outline"
            data-width="28"
            data-height="28"
          ></span>
        </a>
      </div>
    </div>

    <div class="d-flex align-items-center justify-content-between mb-3">
      <h2 class="fw-bold m-0">Kelola Menu</h2>
      <button
        class="btn-add-main d-print-none"
        data-bs-toggle="modal"
        data-bs-target="#menuModal"
        onclick="openAdd()"
      >
        <svg
          class="icon-plus"
          viewBox="0 0 24 24"
          aria-hidden="true"
          focusable="false"
        >
          <path d="M12 5v14M5 12h14"></path>
        </svg>
        <span>Tambah Menu</span>
      </button>
    </div>

    <?php if ($msg): ?>
      <div class="alert alert-warning py-2"><?= h($msg) ?></div>
    <?php endif; ?>

    <div class="cardx">
      <div class="table-responsive">
        <table class="table align-middle mb-0" id="menuTable">
          <thead>
          <tr>
            <th>ID</th>
            <th>Gambar</th>
            <th>Nama</th>
            <th>Kategori</th>
            <th>Harga</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
          </thead>
          <tbody>
          <?php if (!$menus): ?>
            <tr>
              <td colspan="7" class="text-center text-muted py-4">
                Belum ada data.
              </td>
            </tr>
          <?php else: foreach ($menus as $m): ?>
            <tr>
              <td><?= (int)$m['id'] ?></td>
              <td>
                <?php
                $src = !empty($m['image'])
                  ? (str_starts_with($m['image'], 'http')
                    ? $m['image']
                    : BASE_URL . '/public/' . $m['image'])
                  : 'https://picsum.photos/seed/caffora/96';
                ?>
                <img src="<?= h($src) ?>" alt="" class="thumb">
              </td>
              <td><?= h($m['name']) ?></td>
              <td><?= h($m['category']) ?></td>
              <td><?= rupiah($m['price']) ?></td>
              <td>
                <?php if ($m['stock_status'] === 'Ready'): ?>
                  <span class="badge text-bg-success">Ready</span>
                <?php else: ?>
                  <span class="badge text-bg-danger">Sold Out</span>
                <?php endif; ?>
              </td>
              <td class="text-nowrap">
                <button
                  class="btn btn-sm btn-outline-primary me-1"
                  title="Edit"
                  onclick='openEdit(
                    <?= (int)$m["id"] ?>,
                    <?= json_encode(
                      $m,
                      JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
                    ) ?>
                  )'
                >
                  <i class="bi bi-pencil-square"></i>
                </button>
                <a
                  class="btn btn-sm btn-outline-danger"
                  href="?delete=<?= (int)$m['id'] ?>"
                  title="Hapus"
                  onclick="return confirm('Hapus menu ini?')"
                >
                  <i class="bi bi-trash"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</main>

<!-- MODAL -->
<div class="modal fade" id="menuModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post" enctype="multipart/form-data">
      <div class="modal-header">
        <h5 class="modal-title" id="menuTitle">Tambah Menu</h5>
        <button
          type="button"
          class="btn-close"
          data-bs-dismiss="modal"
          aria-label="Tutup"
        ></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" id="action" value="add">
        <input type="hidden" name="id" id="id">
        <!-- real input buat PHP -->
        <input type="hidden" name="category" id="category" value="food">
        <input type="hidden" name="stock_status" id="stock_status" value="Ready">

        <div class="mb-2">
          <label class="form-label">Nama</label>
          <input
            type="text"
            class="form-control"
            name="name"
            id="name"
            required
          >
        </div>

        <div class="mb-2">
          <label class="form-label">Kategori</label>
          <div class="cf-select" data-target="category">
            <div class="cf-select__trigger" tabindex="0">
              <span class="cf-select__text" id="category_label">Food</span>
              <i class="bi bi-chevron-down cf-select__icon"></i>
            </div>
            <div class="cf-select__list">
              <div
                class="cf-select__option is-active"
                data-value="food"
              >Food</div>
              <div
                class="cf-select__option"
                data-value="pastry"
              >Pastry</div>
              <div
                class="cf-select__option"
                data-value="drink"
              >Drink</div>
            </div>
          </div>
        </div>

        <div class="mb-2">
          <label class="form-label">Harga</label>
          <input
            type="number"
            class="form-control"
            name="price"
            id="price"
            min="0"
            step="1"
            required
          >
        </div>

        <div class="mb-2">
          <label class="form-label">Status Stok</label>
          <div class="cf-select" data-target="stock_status">
            <div class="cf-select__trigger" tabindex="0">
              <span
                class="cf-select__text"
                id="stock_status_label"
              >Ready</span>
              <i class="bi bi-chevron-down cf-select__icon"></i>
            </div>
            <div class="cf-select__list">
              <div
                class="cf-select__option is-active"
                data-value="Ready"
              >Ready</div>
              <div
                class="cf-select__option"
                data-value="Sold Out"
              >Sold Out</div>
            </div>
          </div>
        </div>

        <div class="mb-2">
          <label class="form-label">Gambar (max 2 mb)</label>
          <input
            type="file"
            class="form-control"
            name="image"
            accept="image/*"
          >
        </div>
      </div>
      <div class="modal-footer">
        <button
          class="btn btn-outline-secondary"
          type="button"
          data-bs-dismiss="modal"
        >
          Batalkan
        </button>
        <button class="btn btn-saffron" type="submit">Simpan</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>

// ===== sidebar =====

// Ambil elemen sidebar
const sideNav  = document.getElementById('sideNav');

// Ambil elemen backdrop
const backdrop = document.getElementById('backdrop');

// Fungsi menutup sidebar
function closeSide(){

  // Hapus class show pada sidebar
  sideNav.classList.remove('show');

  // Hapus class active pada backdrop
  backdrop.classList.remove('active');
}

// Event buka sidebar
document.getElementById('openSidebar')
  ?.addEventListener('click', ()=>{

    // Tampilkan sidebar
    sideNav.classList.add('show');

    // Aktifkan backdrop
    backdrop.classList.add('active');
  });

// Event tutup sidebar (tombol close)
document.getElementById('closeSidebar')
  ?.addEventListener('click', closeSide);

// Event tutup sidebar dari dalam sidebar
document.getElementById('toggleSidebarInside')
  ?.addEventListener('click', closeSide);

// Event klik backdrop untuk menutup sidebar
backdrop?.addEventListener('click', closeSide);

// Event klik menu sidebar
document.querySelectorAll('#sidebar-nav .nav-link').forEach(a=>{

  // Tambahkan event click pada setiap link
  a.addEventListener('click', function(){

    // Hapus status active dari semua link
    document.querySelectorAll('#sidebar-nav .nav-link')
      .forEach(l=>l.classList.remove('active'));

    // Aktifkan link yang diklik
    this.classList.add('active');

    // Tutup sidebar pada layar kecil
    if (window.innerWidth < 1200) closeSide();
  });
});

// ===== search filter di tabel =====

// Ambil input pencarian
const searchInput = document.getElementById('searchInput');

// Ambil icon search
const searchIcon  = document.getElementById('searchIcon');

// Ambil body tabel menu
const tableBody   = document.querySelector('#menuTable tbody');

// Fungsi filter tabel
function doFilter(){

  // Ambil keyword pencarian
  const q = (searchInput.value || '').toLowerCase();

  // Loop setiap baris tabel
  tableBody.querySelectorAll('tr').forEach(tr => {

    // Ambil teks baris
    const text = tr.innerText.toLowerCase();

    // Tampilkan / sembunyikan baris sesuai hasil filter
    tr.style.display = text.includes(q) ? '' : 'none';
  });
}

// Event input search
searchInput?.addEventListener('input', doFilter);

// Event klik icon search
searchIcon?.addEventListener('click', () => {

  // Jika input sedang fokus, jalankan filter
  if (document.activeElement === searchInput) {
    doFilter();

  // Jika belum fokus, fokuskan input
  } else {
    searchInput?.focus();
  }
});

// ===== notif badge (polling unread_count) =====

// Fungsi refresh badge notifikasi admin
async function refreshAdminNotifBadge(){

  // Ambil elemen badge
  const badge = document.getElementById('badgeNotif');

  // Jika badge tidak ada, hentikan
  if (!badge) return;

  try{

    // Fetch jumlah notifikasi belum dibaca
    const res = await fetch(
      "<?= BASE_URL ?>/backend/api/notifications.php?action=unread_count",
      {
        credentials:'same-origin',
        headers:{ 'Cache-Control':'no-cache' }
      }
    );

    // Jika response tidak OK, hentikan
    if (!res.ok) return;

    // Ambil data JSON
    const data  = await res.json();

    // Ambil jumlah notifikasi
    const count = Number(data?.count ?? 0);

    // Tampilkan / sembunyikan badge
    badge.classList.toggle('d-none', !(count > 0));

  }catch(e){

    // Error diabaikan (silent)
  }
}

// Panggil refresh pertama kali
refreshAdminNotifBadge();

// Set interval refresh setiap 30 detik
setInterval(refreshAdminNotifBadge, 30000);

// ===== modal helpers =====

// Ambil elemen modal
const modalEl = document.getElementById('menuModal');

// Inisialisasi Bootstrap Modal
const modal   = new bootstrap.Modal(modalEl);

// Fungsi buka modal tambah menu
function openAdd(){

  // Set judul modal
  document.getElementById('menuTitle').textContent = 'Tambah Menu';

  // Set action add
  document.getElementById('action').value = 'add';

  // Reset id
  document.getElementById('id').value    = '';

  // Reset nama
  document.getElementById('name').value  = '';

  // Reset harga
  document.getElementById('price').value = '';

  // Reset kategori
  document.getElementById('category').value        = 'food';

  // Set label kategori
  document.getElementById('category_label').textContent = 'Food';

  // Reset status stok
  document.getElementById('stock_status').value        = 'Ready';

  // Set label stok
  document.getElementById('stock_status_label').textContent = 'Ready';

  // Reset active option kategori
  document.querySelectorAll('.cf-select[data-target="category"] .cf-select__option')
    .forEach(o => o.classList.toggle(
      'is-active',
      o.dataset.value === 'food'
    ));

  // Reset active option stok
  document.querySelectorAll('.cf-select[data-target="stock_status"] .cf-select__option')
    .forEach(o => o.classList.toggle(
      'is-active',
      o.dataset.value === 'Ready'
    ));
}

// Fungsi buka modal edit menu
function openEdit(id, row){

  // Set judul modal
  document.getElementById('menuTitle').textContent = 'Edit Menu';

  // Set action edit
  document.getElementById('action').value = 'edit';

  // Set id menu
  document.getElementById('id').value    = id;

  // Set nama menu
  document.getElementById('name').value  = row.name || '';

  // Set harga menu
  document.getElementById('price').value = row.price || '';

  // Ambil kategori menu
  const cat = (row.category || 'food').toLowerCase();

  // Set value kategori
  document.getElementById('category').value = cat;

  // Set label kategori
  document.getElementById('category_label').textContent =
    cat === 'food'   ? 'Food' :
    cat === 'pastry' ? 'Pastry' : 'Drink';

  // Set active kategori
  document.querySelectorAll('.cf-select[data-target="category"] .cf-select__option')
    .forEach(o => o.classList.toggle(
      'is-active',
      o.dataset.value === cat
    ));

  // Ambil status stok
  const st = row.stock_status === 'Sold Out' ? 'Sold Out' : 'Ready';

  // Set value stok
  document.getElementById('stock_status').value = st;

  // Set label stok
  document.getElementById('stock_status_label').textContent = st;

  // Set active stok
  document.querySelectorAll('.cf-select[data-target="stock_status"] .cf-select__option')
    .forEach(o => o.classList.toggle(
      'is-active',
      o.dataset.value === st
    ));

  // Tampilkan modal
  modal.show();
}

// ===== init custom select =====

// IIFE inisialisasi custom select
(function initCfSelect(){

  // Ambil semua custom select
  const selects = document.querySelectorAll('.cf-select');

  // Fungsi menutup semua select
  const closeAll = () => {
    selects.forEach(s => s.classList.remove('is-open'));
  };

  // Loop setiap select
  selects.forEach(sel => {

    // Ambil target input hidden
    const targetId = sel.dataset.target;

    // Ambil trigger
    const trigger  = sel.querySelector('.cf-select__trigger');

    // Ambil list option
    const list     = sel.querySelector('.cf-select__list');

    // Ambil label
    const label    = sel.querySelector('.cf-select__text');

    // Validasi elemen
    if (!trigger || !list || !label) return;

    // Event klik trigger
    trigger.addEventListener('click', (e)=>{

      // Stop propagasi
      e.stopPropagation();

      // Cek status open
      const isOpen = sel.classList.contains('is-open');

      // Tutup semua select
      closeAll();

      // Toggle select aktif
      if (!isOpen) sel.classList.add('is-open');
    });

    // Event klik option
    list.querySelectorAll('.cf-select__option').forEach(opt => {

      // Tambah event click
      opt.addEventListener('click', ()=>{

        // Ambil value option
        const val  = opt.dataset.value;

        // Ambil teks option
        const text = opt.textContent.trim();

        // Set label
        label.textContent = text;

        // Set value input hidden
        const hid = document.getElementById(targetId);
        if (hid) hid.value = val;

        // Reset active option
        list.querySelectorAll('.cf-select__option')
          .forEach(o => o.classList.remove('is-active'));

        // Aktifkan option terpilih
        opt.classList.add('is-active');

        // Tutup select
        sel.classList.remove('is-open');
      });
    });
  });

  // Tutup select saat klik di luar
  document.addEventListener('click', ()=> closeAll());
})();
</script>
</body>
</html>
