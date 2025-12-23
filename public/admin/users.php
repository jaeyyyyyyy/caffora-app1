<?php  
// Tagline: Halaman manajemen user admin Caffora untuk tambah, edit, dan hapus akun dengan UI modern dan aman.

// public/admin/users.php
declare(strict_types=1); // Aktifkan strict types agar pengecekan tipe PHP lebih ketat.
session_start(); // Mulai atau lanjutkan session untuk pakai data login admin.

require_once __DIR__ . '/../../backend/config.php';      // Load konfigurasi (DB, BASE_URL, dll).
require_once __DIR__ . '/../../backend/auth_guard.php';  // Load guard autentikasi & otorisasi.
require_once __DIR__ . '/../../backend/helpers.php';     // Load helper umum jika tersedia.
require_login(['admin']); // Pastikan hanya admin yang boleh akses halaman ini.

// fallback helper
if (!function_exists('h')) { // Jika fungsi h() belum didefinisikan sebelumnya,
  function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); } // buat helper untuk escape HTML output.
}

/* ================== UTIL ================== */
function emailUsedByOther(mysqli $conn, string $email, int $exceptId = 0): bool { // Cek apakah email sudah dipakai user lain (kecuali ID tertentu saat edit).
  $sql = $exceptId > 0
    ? "SELECT id FROM users WHERE email=? AND id<>? LIMIT 1"  // Query saat edit: exclude user dengan ID yang sedang diedit.
    : "SELECT id FROM users WHERE email=? LIMIT 1";           // Query saat tambah: cek email saja.
  $stmt = $conn->prepare($sql); // Siapkan prepared statement untuk hindari SQL injection.
  if ($exceptId > 0) { $stmt->bind_param('si', $email, $exceptId); } // Bind email + id ketika mode edit.
  else { $stmt->bind_param('s', $email); } // Bind hanya email ketika mode tambah.
  $stmt->execute(); // Jalankan query.
  $stmt->store_result(); // Simpan result agar bisa cek num_rows.
  $exists = $stmt->num_rows > 0; // True kalau ada minimal 1 baris, artinya email sudah dipakai.
  $stmt->close(); // Tutup statement.
  return $exists; // Kembalikan status exist atau tidak.
}

$msg = $_GET['msg'] ?? ''; // Ambil pesan feedback dari query string (kalau ada) untuk ditampilkan di alert.

/* ================== ACTIONS ================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') { // Jika request datang dengan metode POST (tambah/edit user).
  $act    = $_POST['action'] ?? '';                     // Ambil jenis aksi: add atau edit.
  $id     = (int)($_POST['id'] ?? 0);                   // ID user untuk edit (0 kalau add).
  $name   = trim((string)($_POST['name'] ?? ''));       // Nama user, di-trim dari spasi.
  $email  = trim((string)($_POST['email'] ?? ''));      // Email user, di-trim.
  $role   = trim((string)($_POST['role'] ?? 'customer')); // Role, default customer.
  $status = trim((string)($_POST['status'] ?? 'active')); // Status, default active.
  $pass   = (string)($_POST['password'] ?? '');         // Password (opsional saat edit).

  if (!in_array($role, ['admin','karyawan','customer'], true)) $role = 'customer'; // Validasi role, kalau tidak sesuai fallback ke customer.
  if (!in_array($status, ['pending','active'], true)) $status = 'active';          // Validasi status, fallback ke active.

  // ADD
  if ($act === 'add') { // Jika aksi adalah tambah user baru.
    if ($name === '' || $email === '' || $pass === '') { // Validasi field wajib diisi.
      header('Location: '.$_SERVER['PHP_SELF'].'?msg='.urlencode('Nama, email, dan password wajib diisi.')); // Redirect dengan pesan error.
      exit; // Stop eksekusi.
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { // Validasi format email.
      header('Location: '.$_SERVER['PHP_SELF'].'?msg='.urlencode('Format email tidak valid.')); // Redirect kalau email invalid.
      exit;
    }
    if (emailUsedByOther($conn, $email, 0)) { // Cek apakah email sudah digunakan user lain.
      header('Location: '.$_SERVER['PHP_SELF'].'?msg='.urlencode('Email sudah digunakan.')); // Redirect dengan pesan email sudah dipakai.
      exit;
    }
    $hash = password_hash($pass, PASSWORD_DEFAULT); // Hash password dengan algoritma bawaan PHP.
    $stmt = $conn->prepare("INSERT INTO users(name,email,password,role,status) VALUES (?,?,?,?,?)"); // Siapkan query insert user baru.
    $stmt->bind_param('sssss', $name, $email, $hash, $role, $status); // Bind parameter ke query.
    $ok = $stmt->execute(); // Eksekusi insert dan simpan status berhasil/gagal.
    $stmt->close(); // Tutup statement.
    header('Location: '.$_SERVER['PHP_SELF'].'?msg='.urlencode($ok ? 'User ditambahkan.' : 'Gagal menambah user.')); // Redirect dengan pesan sesuai hasil.
    exit; // Stop eksekusi setelah redirect.
  }

  // EDIT
  if ($act === 'edit' && $id > 0) { // Jika aksi edit dan ID valid.
    if ($name === '' || $email === '') { // Nama dan email wajib saat edit.
      header('Location: '.$_SERVER['PHP_SELF'].'?msg='.urlencode('Nama dan email wajib.')); // Redirect dengan pesan error.
      exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { // Validasi format email.
      header('Location: '.$_SERVER['PHP_SELF'].'?msg='.urlencode('Format email tidak valid.')); // Redirect kalau email invalid.
      exit;
    }
    if (emailUsedByOther($conn, $email, $id)) { // Cek email sudah dipakai user lain (bukan ID ini).
      header('Location: '.$_SERVER['PHP_SELF'].'?msg='.urlencode('Email sudah dipakai user lain.')); // Redirect dengan pesan email sudah dipakai.
      exit;
    }

    if ($pass !== '') { // Jika admin mengisi password baru saat edit.
      $hash = password_hash($pass, PASSWORD_DEFAULT); // Hash password baru.
      $stmt = $conn->prepare("UPDATE users SET name=?, email=?, password=?, role=?, status=? WHERE id=?"); // Query update termasuk password.
      $stmt->bind_param('sssssi', $name, $email, $hash, $role, $status, $id); // Bind parameter lengkap dengan password.
    } else { // Jika password tidak diisi (tidak diubah).
      $stmt = $conn->prepare("UPDATE users SET name=?, email=?, role=?, status=? WHERE id=?"); // Query update tanpa password.
      $stmt->bind_param('ssssi', $name, $email, $role, $status, $id); // Bind parameter tanpa password.
    }
    $ok = $stmt->execute(); // Eksekusi update user.
    $stmt->close(); // Tutup statement.
    header('Location: '.$_SERVER['PHP_SELF'].'?msg='.urlencode($ok ? 'User diperbarui.' : 'Gagal mengedit user.')); // Redirect dengan pesan hasil update.
    exit;
  }
}

// DELETE
if (isset($_GET['delete'])) { // Jika ada parameter delete di query string.
  $id = (int)$_GET['delete']; // Ambil ID user yang akan dihapus.
  if ($id > 0) { // Hanya proses jika ID > 0.
    $stmt = $conn->prepare("DELETE FROM users WHERE id=?"); // Siapkan query hapus user.
    $stmt->bind_param('i', $id); // Bind ID user.
    $stmt->execute(); // Eksekusi delete.
    $stmt->close(); // Tutup statement.
    header('Location: '.$_SERVER['PHP_SELF'].'?msg='.urlencode('User dihapus.')); // Redirect kembali dengan pesan sukses hapus.
    exit; // Stop eksekusi.
  }
}

/* ================== DATA ================== */
$rows = []; // Inisialisasi array kosong untuk menampung data user.
$res = $conn->query("SELECT id,name,email,role,status,created_at FROM users ORDER BY created_at DESC"); // Ambil semua user, urut terbaru.
if ($res) { // Jika query berhasil.
  $rows = $res->fetch_all(MYSQLI_ASSOC); // Ambil semua baris sebagai array asosiatif.
  $res->close(); // Tutup result set.
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8"> <!-- Set encoding karakter ke UTF-8 -->
  <title>Users — Admin Desk</title> <!-- Judul tab halaman admin Users -->
  <meta name="viewport" content="width=device-width,initial-scale=1"> <!-- Buat layout responsive di mobile -->

  <!-- Load Bootstrap CSS untuk layout dan komponen dasar -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Load Bootstrap Icons untuk ikon-ikon -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <!-- Iconify untuk ikon tambahan (material, dsb) -->
  <script src="https://code.iconify.design/2/2.2.1/iconify.min.js"></script>

  <style>
    :root{
      --gold:#FFD54F;            /* Warna utama emas untuk aksen/tombol */
      --gold-soft:#F6D472;       /* Versi lembut dari emas untuk hover/focus */
      --brown:#4B3F36;           /* Warna coklat brand Caffora */
      --ink:#111827;             /* Warna teks utama gelap */
      --radius:18px;             /* Radius default card */
      --sidebar-w:320px;         /* Lebar sidebar di desktop */
      --input-border:#E8E2DA;    /* Warna border input custom */
      --bg:#FAFAFA;              /* Warna background umum */
      --btn-radius:14px; /* pusat radius tombol, termasuk Batal */ /* Radius global untuk tombol */
    }
    *{ box-sizing:border-box; font-family:Poppins,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif; } /* Global reset + font stack */
    body{ background:var(--bg); color:var(--ink); } /* Set warna dasar body */

    /* ===== SIDEBAR ===== */
    .sidebar{
      position:fixed; left:-320px; top:0; bottom:0; width:var(--sidebar-w); /* Sidebar off-canvas di kiri */
      background:#fff; border-right:1px solid rgba(0,0,0,.05);               /* Background putih + border tipis */
      transition:left .25s ease; z-index:1050; padding:16px 18px; overflow-y:auto; /* Animasi buka/tutup dan scroll */
    }
    .sidebar.show{ left:0; } /* Saat class show ditambahkan, sidebar bergeser ke dalam layar */
    .sidebar-head{ display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:10px; } /* Header sidebar (tombol + title opsional) */
    .sidebar-inner-toggle,.sidebar-close-btn{ background:transparent; border:0; width:40px; height:36px; display:grid; place-items:center; } /* Tombol ikon tanpa border */
    .hamb-icon{ width:24px; height:20px; display:flex; flex-direction:column; justify-content:space-between; gap:4px; } /* Icon hamburger garis tiga */
    .hamb-icon span{ height:2px; background:var(--brown); border-radius:99px; } /* Garis-garis kecil hamburger */
    .sidebar .nav-link{ display:flex; align-items:center; gap:12px; padding:12px 14px; border-radius:16px; font-weight:600; color:#111; text-decoration:none; } /* Gaya menu sidebar */
    .sidebar .nav-link:hover{ background:rgba(255,213,79,.25); } /* Hover dengan highlight emas transparan */
    .sidebar .nav-link.active{ background:rgba(255,213,79,.4); } /* Menu aktif sedikit lebih pekat */

    .backdrop-mobile{ display:none; } /* Overlay hitam untuk mode mobile (default hidden) */
    .backdrop-mobile.active{ display:block; position:fixed; inset:0; background:rgba(0,0,0,.35); z-index:1040; } /* Muncul saat sidebar aktif */

    /* ===== CONTENT ===== */
    .content{ padding:16px 14px 40px; } /* Wrapper konten utama dengan padding */
    .topbar{ display:flex; align-items:center; gap:12px; margin-bottom:16px; } /* Bar atas di konten (menu + search + icon) */
    .btn-menu{ background:transparent; border:0; width:40px; height:38px; display:grid; place-items:center; } /* Tombol buka sidebar (hamburger) */

    .search-box{ position:relative; flex:1 1 auto; } /* Wrapper input search agar fleksibel */
    .search-input{
      height:46px; width:100%; border-radius:9999px;          /* Input bulat (pill) */
      padding-left:16px; padding-right:44px;                  /* Ruang kiri dan kanan (untuk ikon) */
      border:1px solid #e5e7eb; background:#fff; outline:none; transition:border-color .12s ease; /* Border halus + animasi focus */
    }
    .search-input:focus{ border-color:var(--gold-soft) !important; } /* Saat fokus, border jadi warna emas lembut */
    .search-icon{ position:absolute; right:16px; top:50%; transform:translateY(-50%); color:var(--brown); cursor:pointer; } /* Ikon search di kanan dalam input */

    .top-actions{ display:flex; align-items:center; gap:14px; } /* Wrapper ikon notifikasi & akun */
    .icon-btn{ width:38px; height:38px; border-radius:999px; display:flex; align-items:center; justify-content:center; color:var(--brown); text-decoration:none; position:relative; } /* Tombol ikon bulat */

    /* ——— Notif badge (dot kecil) ——— */
    .notif-dot{
      position:absolute; top:3px; right:4px;      /* Posisi dot di pojok kanan atas ikon */
      width:8px; height:8px; border-radius:999px; /* Dot bulat kecil */
      background:#4B3F36; box-shadow:0 0 0 1.5px #fff; /* Warna dot + outline putih */
    }
    .d-none{ display:none !important; } /* Utility untuk sembunyikan elemen */

    .cardx{ background:#fff; border:1px solid #f7d78d; border-radius:var(--radius); padding:18px; } /* Card custom untuk tabel user */
    .table thead th{ background:#fffbe6; } /* Background header tabel kuning lembut */

    /* ===== Buttons (Arial) ===== */
    .btn-saffron,.btn-add-main,.modal-footer .btn{
      background-color: var(--gold);                 /* Warna tombol default emas */
      color: var(--brown) !important;                /* Teks coklat */
      border: 0;                                     /* Tanpa border jelas */
      border-radius: 14px;                           /* Sudut tombol membulat */
      font-family: Arial, Helvetica, sans-serif;     /* Paksa Arial di tombol */
      font-weight: 600;                              /* Tebal sedang */
      font-size: .88rem;                             /* Ukuran teks tombol */
      padding: 10px 18px;                            /* Padding horizontal & vertikal */
      display: inline-flex;                          /* Flex untuk align konten */
      align-items: center;                           /* Vertikal tengah */
      justify-content: center;                       /* Horizontal tengah */
      gap: 8px;                                      /* Jarak ikon-teks */
      white-space: nowrap;                           /* Jangan bungkus teks */
      box-shadow: none;                              /* Tanpa shadow */
    }
    .btn-saffron:hover{ background:#FFE07A; color:#111; } /* Hover tombol emas sedikit lebih terang */

    /* tombol Tambah User – clean */
    .btn-add-main{
      background:var(--gold); border:1px solid rgba(0,0,0,.02); color:#111; /* Tambah border halus */
      border-radius:var(--btn-radius); padding:.6rem 1.1rem;                /* Gunakan radius global */
      display:inline-flex; align-items:center; gap:.55rem;                  /* Ikon + teks */
    }
    .btn-add-main:hover{ background:#FFE07A; } /* Hover tombol tambah user */
    /* SVG plus tebal */
    .btn-add-main svg.icon-plus{
      width:18px; height:18px;           /* Ukuran ikon plus */
      stroke:currentColor; fill:none;    /* Hanya stroke, tanpa fill */
      stroke-width:3.2;                  /* Ketebalan garis plus */
      stroke-linecap:round; stroke-linejoin:round; /* Ujung dan sudut bulat */
      display:inline-block;              /* Tampilkan sebagai inline-block */
    }
    /* jika masih ada .plus-dot di HTML lama, matikan saja */
    .btn-add-main .plus-dot{ display:none !important; } /* Sembunyikan elemen dot lawas jika ada */

    /* form */
    .form-control{
      border:1px solid var(--input-border) !important; border-radius:14px !important; /* Input dengan border krem & radius besar */
      box-shadow:none !important; outline:none !important;                             /* Hilangkan shadow default Bootstrap */
    }
    .form-control:focus{ border-color:var(--gold-soft) !important; box-shadow:none !important; outline:none !important; } /* Fokus input pakai warna emas lembut */

    /* custom select */
    .cf-select{ position:relative; width:100%; } /* Wrapper custom select role/status */
    .cf-select__trigger{
      width:100%; background:#fff; border:1px solid var(--input-border); border-radius:14px;
      padding:8px 38px 8px 14px; display:flex; align-items:center; justify-content:space-between; gap:12px;
      cursor:pointer; transition:border-color .12s ease; /* Trigger (bagian yang diklik) */
    }
    .cf-select.is-open .cf-select__trigger,.cf-select__trigger:focus-visible{ border-color:var(--gold-soft); outline:none; } /* Saat terbuka/focus, border highlight */
    .cf-select__text{ font-size:.9rem; color:#2b2b2b; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; } /* Label teks dalam select */
    .cf-select__icon{ color:var(--brown); } /* Ikon panah turun */
    .cf-select__list{
      position:absolute; left:0; top:calc(100% + 6px); width:100%; background:#fff;
      border:1px solid rgba(0,0,0,.02); border-radius:14px; box-shadow:0 14px 30px rgba(0,0,0,.09);
      display:none; max-height:240px; overflow-y:auto; z-index:200000; /* > modal */ /* Dropdown options */
    }
    .cf-select.is-open .cf-select__list{ display:block; } /* Saat select open, tampilkan list */
    .cf-select__option{ padding:9px 14px; font-size:.88rem; color:#413731; cursor:pointer; } /* Satu opsi di dropdown */
    .cf-select__option:hover{ background:#FFF2C9; } /* Hover opsi dengan highlight lembut */
    .cf-select__option.is-active{ background:#FFEB9B; font-weight:600; } /* Opsi aktif diberi warna lebih pekat */

    @media(min-width:992px){
      .content{ padding:20px 26px 50px; }    /* Lebih lega di desktop */
      .search-box{ max-width:1100px; }       /* Batasi lebar search */
    }
    @media print{ .d-print-none{ display:none !important; } } /* Sembunyikan elemen tertentu saat print */

    /* ===== Modal polish ===== */
    .modal-content{ border:0 !important; box-shadow:0 18px 50px rgba(0,0,0,.16); border-radius:18px; } /* Card modal halus */
    .modal-header{ border-bottom:0 !important; } /* Hilangkan border header modal */
    .modal-footer{ border-top:0 !important; }   /* Hilangkan border footer modal */
    .modal-body{ padding-bottom:110px; } /* ruang dropdown agar tak keluar */ /* Tambah ruang bawah agar select tidak ketimpa */

    /* Batal seperti contoh (abu-abu) */
    .modal-footer .btn-outline-secondary{
      background:#F3F4F6;                  /* abu lembut */
      color:#111827;                       /* teks gelap */
      border:1px solid #E5E7EB !important; /* garis halus */
      border-radius:var(--btn-radius) !important;
      font-family: Arial, Helvetica, sans-serif;
      font-weight: 700;
      box-shadow:none;
    }
    .modal-footer .btn-outline-secondary:hover{
      background:#ECEFF3;
      border-color:#E5E7EB !important;
    }
    .modal-footer .btn-saffron{ border-radius:var(--btn-radius) !important; } /* Samakan radius tombol Simpan */
  </style>
</head>
<body>

<!-- backdrop -->
<div id="backdrop" class="backdrop-mobile"></div> <!-- Overlay gelap untuk sidebar saat di mobile -->

<!-- sidebar -->
<aside class="sidebar" id="sideNav">
  <div class="sidebar-head">
    <button class="sidebar-inner-toggle" id="toggleSidebarInside" aria-label="Tutup menu"></button> <!-- Tombol close di dalam sidebar -->
    <button class="sidebar-close-btn" id="closeSidebar" aria-label="Tutup menu">
      <i class="bi bi-x-lg"></i> <!-- Ikon X untuk tutup sidebar -->
    </button>
  </div>

  <nav class="nav flex-column gap-2" id="sidebar-nav"> <!-- Menu navigasi vertikal admin -->
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/index.php"><i class="bi bi-house-door"></i> Dashboard</a> <!-- Link ke dashboard -->
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/orders.php"><i class="bi bi-receipt"></i> Orders</a> <!-- Link ke orders -->
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/catalog.php"><i class="bi bi-box-seam"></i> Catalog</a> <!-- Link ke katalog produk -->
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/users.php"><i class="bi bi-people"></i> Users</a> <!-- Link ke halaman pengguna (current) -->
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/finance.php"><i class="bi bi-cash-coin"></i> Finance</a> <!-- Link ke finansial -->
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/notifications_send.php"><i class="bi bi-megaphone"></i> Notifications</a> <!-- Link broadcast notif -->
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/audit.php"><i class="bi bi-shield-check"></i> Audit Log</a> <!-- Link audit log -->
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/settings.php"><i class="bi bi-gear"></i> Settings</a> <!-- Link pengaturan admin -->
    <hr> <!-- Garis pisah menu utama dan bantuan -->
    <a class="nav-link" href="<?= BASE_URL ?>/public/admin/help.php"><i class="bi bi-question-circle"></i> Help Center</a> <!-- Link pusat bantuan -->
    <a class="nav-link" href="<?= BASE_URL ?>/backend/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a> <!-- Logout admin -->
  </nav>
</aside>

<!-- content -->
<main class="content">
  <!-- topbar -->
  <div class="topbar">
    <button class="btn-menu" id="openSidebar" aria-label="Buka menu">
      <div class="hamb-icon"><span></span><span></span><span></span></div> <!-- Ikon hamburger tiga garis -->
    </button>

    <div class="search-box">
      <input class="search-input" id="searchInput" placeholder="Search..." autocomplete="off"> <!-- Input pencarian user (client-side filter) -->
      <i class="bi bi-search search-icon" id="searchIcon"></i> <!-- Ikon search di kanan input -->
    </div>

    <div class="top-actions">
      <a id="btnBell" class="icon-btn position-relative text-decoration-none" href="<?= BASE_URL ?>/public/admin/notifications.php" aria-label="Notifikasi">
        <span class="iconify" data-icon="mdi:bell-outline" data-width="24" data-height="24"></span> <!-- Ikon lonceng notifikasi -->
        <span id="badgeNotif" class="notif-dot d-none"></span> <!-- Dot notif jika ada unread -->
      </a>
      <a class="icon-btn text-decoration-none" href="<?= BASE_URL ?>/public/admin/settings.php" aria-label="Akun">
        <span class="iconify" data-icon="mdi:account-circle-outline" data-width="28" data-height="28"></span> <!-- Ikon profil / akun -->
      </a>
    </div>
  </div>

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="fw-bold m-0">Kelola User</h2> <!-- Judul halaman: kelola user -->
    <button class="btn-add-main d-print-none" data-bs-toggle="modal" data-bs-target="#userModal" onclick="openAdd()">
      <!-- SVG plus tebal -->
      <svg class="icon-plus" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path d="M12 5v14M5 12h14"></path> <!-- Garis vertikal & horizontal membentuk plus -->
      </svg>
      <span>Tambah User</span> <!-- Label tombol tambah user -->
    </button>
  </div>

  <?php if ($msg): ?> <!-- Jika ada pesan dari query string, tampilkan alert -->
    <div class="alert alert-warning py-2"><?= h($msg) ?></div> <!-- Alert warning dengan isi pesan -->
  <?php endif; ?>

  <div class="cardx">
    <div class="table-responsive">
      <table class="table align-middle mb-0" id="usersTable">
        <thead>
          <tr>
            <th>ID</th>          <!-- Kolom ID user -->
            <th>Nama</th>        <!-- Kolom nama -->
            <th>Email</th>       <!-- Kolom email -->
            <th>Role</th>        <!-- Kolom role (admin/karyawan/customer) -->
            <th>Status</th>      <!-- Kolom status (active/pending) -->
            <th>Dibuat</th>      <!-- Kolom tanggal dibuat -->
            <th class="d-print-none">Aksi</th> <!-- Kolom aksi (edit/hapus), disembunyikan saat print -->
          </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?> <!-- Jika tidak ada data user sama sekali -->
          <tr><td colspan="7" class="text-center text-muted py-4">Belum ada data.</td></tr> <!-- Tampilkan satu baris info kosong -->
        <?php else: foreach ($rows as $u): ?> <!-- Jika ada data, loop tiap user -->
          <tr>
            <td><?= (int)$u['id'] ?></td> <!-- Tampilkan ID user -->
            <td><?= h($u['name']) ?></td> <!-- Tampilkan nama user (escaped) -->
            <td><?= h($u['email']) ?></td> <!-- Tampilkan email user (escaped) -->
            <td><span class="badge text-bg-secondary text-capitalize"><?= h($u['role']) ?></span></td> <!-- Tampilkan role dengan badge -->
            <td>
              <?php if ($u['status'] === 'active'): ?> <!-- Jika status aktif -->
                <span class="badge text-bg-success">Aktif</span> <!-- Badge hijau Aktif -->
              <?php else: ?> <!-- Selain itu dianggap pending -->
                <span class="badge text-bg-warning text-dark">Pending</span> <!-- Badge kuning Pending -->
              <?php endif; ?>
            </td>
            <td><small class="text-muted"><?= h($u['created_at']) ?></small></td> <!-- Tanggal dibuat dalam teks kecil -->
            <td class="d-print-none text-nowrap"> <!-- Cell aksi, tidak tampil di mode print -->
              <button class="btn btn-sm btn-outline-primary me-1"
                title="Edit"
                onclick='openEdit(<?= (int)$u["id"] ?>, <?= json_encode($u, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>)'>
                <i class="bi bi-pencil-square"></i> <!-- Tombol edit user, kirim data ke JS openEdit -->
              </button>
              <a class="btn btn-sm btn-outline-danger"
                href="?delete=<?= (int)$u['id'] ?>"
                title="Hapus"
                onclick="return confirm('Hapus user ini?')">
                <i class="bi bi-trash"></i> <!-- Tombol hapus user dengan konfirmasi browser -->
              </a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<!-- MODAL ADD / EDIT -->
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post"> <!-- Form modal untuk tambah/edit user, submit ke halaman ini -->
      <div class="modal-header">
        <h5 class="modal-title" id="userTitle">Tambah User</h5> <!-- Judul modal dinamis (Tambah / Edit) -->
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button> <!-- Tombol X tutup modal -->
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" id="action" value="add"> <!-- Hidden field action: add atau edit -->
        <input type="hidden" name="id" id="id">                    <!-- Hidden ID user saat edit -->
        <!-- hidden buat custom select -->
        <input type="hidden" name="role" id="role" value="customer">   <!-- Role disimpan di hidden, dikontrol custom select -->
        <input type="hidden" name="status" id="status" value="active"> <!-- Status disimpan di hidden, dikontrol custom select -->

        <div class="mb-2">
          <label class="form-label">Nama</label> <!-- Label input nama -->
          <input type="text" class="form-control" name="name" id="name" required> <!-- Input nama wajib diisi -->
        </div>
        <div class="mb-2">
          <label class="form-label">Email</label> <!-- Label input email -->
          <input type="email" class="form-control" name="email" id="email" required> <!-- Input email wajib diisi -->
        </div>
        <div class="mb-2">
          <label class="form-label">Password <small class="text-muted">(kosongkan saat edit)</small></label> <!-- Info password untuk add/edit -->
          <input type="password" class="form-control" name="password" id="password" minlength="6"> <!-- Input password minimal 6 karakter -->
        </div>

        <div class="row">
          <div class="col-md-6 mb-2">
            <label class="form-label">Role</label> <!-- Label untuk role user -->
            <div class="cf-select" data-target="role"> <!-- Custom select yang mengisi hidden role -->
              <div class="cf-select__trigger" tabindex="0">
                <span class="cf-select__text" id="role_label">Customer</span> <!-- Label teks role terpilih -->
                <i class="bi bi-chevron-down cf-select__icon"></i> <!-- Ikon panah dropdown -->
              </div>
              <div class="cf-select__list">
                <div class="cf-select__option is-active" data-value="customer">Customer</div> <!-- Opsi role customer (default) -->
                <div class="cf-select__option" data-value="karyawan">Karyawan</div>          <!-- Opsi role karyawan -->
                <div class="cf-select__option" data-value="admin">Admin</div>                <!-- Opsi role admin -->
              </div>
            </div>
          </div>
          <div class="col-md-6 mb-2">
            <label class="form-label">Status</label> <!-- Label status user -->
            <div class="cf-select" data-target="status"> <!-- Custom select untuk status -->
              <div class="cf-select__trigger" tabindex="0">
                <span class="cf-select__text" id="status_label">Aktif</span> <!-- Label status terpilih -->
                <i class="bi bi-chevron-down cf-select__icon"></i> <!-- Ikon panah dropdown -->
              </div>
              <div class="cf-select__list">
                <div class="cf-select__option is-active" data-value="active">Aktif</div>   <!-- Opsi status aktif (default) -->
                <div class="cf-select__option" data-value="pending">Pending</div>          <!-- Opsi status pending -->
              </div>
            </div>
          </div>
        </div>

      </div>
      <div class="modal-footer">
        <!-- Tombol Batal seperti gambar -->
        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Batalkan</button> <!-- Tutup modal tanpa simpan -->
        <button class="btn btn-saffron" type="submit">Simpan</button> <!-- Submit form untuk simpan perubahan -->
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script> <!-- Load JS Bootstrap bundle (modal, dll) -->
<script>
/* ===== Sidebar ===== */
const sideNav = document.getElementById('sideNav');          // Referensi elemen sidebar.
const backdrop = document.getElementById('backdrop');        // Referensi overlay/backdrop untuk mobile.

document.getElementById('openSidebar')?.addEventListener('click', ()=>{ sideNav.classList.add('show'); backdrop.classList.add('active'); }); // Saat tombol open diklik, tampilkan sidebar + backdrop.
document.getElementById('closeSidebar')?.addEventListener('click', closeSide);        // Tombol X di sidebar menutup sidebar.
document.getElementById('toggleSidebarInside')?.addEventListener('click', closeSide); // Tombol inside juga menutup sidebar.
backdrop?.addEventListener('click', closeSide); // Klik pada backdrop juga menutup sidebar.

function closeSide(){ sideNav.classList.remove('show'); backdrop.classList.remove('active'); } // Fungsi helper untuk sembunyikan sidebar dan backdrop.

document.querySelectorAll('#sidebar-nav .nav-link').forEach(a=>{
  a.addEventListener('click', function(){
    document.querySelectorAll('#sidebar-nav .nav-link').forEach(l=>l.classList.remove('active')); // Hapus class active dari semua link.
    this.classList.add('active'); // Set link yang diklik sebagai active.
    if (window.innerWidth < 1200) closeSide(); // Di layar kecil, tutup sidebar setelah klik menu.
  });
});

/* ===== Notif badge: polling unread_count ===== */
async function refreshAdminNotifBadge(){
  const badge = document.getElementById('badgeNotif'); if (!badge) return; // Jika elemen badge tidak ada, hentikan.
  try{
    const res = await fetch("<?= BASE_URL ?>/backend/api/notifications.php?action=unread_count", {
      credentials:"same-origin",
      headers: { "Cache-Control":"no-cache" }
    }); // Panggil API unread_count notifikasi admin.
    if (!res.ok) return; // Jika response bukan 200, jangan lanjut.
    const data = await res.json(); // Parse JSON hasil API.
    const count = Number(data?.count ?? 0); // Ambil nilai count, fallback 0.
    badge.classList.toggle('d-none', !(count > 0)); // Tampilkan atau sembunyikan dot sesuai jumlah notif.
  }catch(e){ /* silent */ } // Abaikan error jaringan tanpa notifikasi.
}
refreshAdminNotifBadge(); // Panggilan awal saat halaman dibuka.
setInterval(refreshAdminNotifBadge, 30000); // Refresh badge tiap 30 detik.

/* ===== Custom Select ===== */
document.addEventListener('click', function(e){
  document.querySelectorAll('.cf-select').forEach(sel=>{
    const trig = sel.querySelector('.cf-select__trigger'); // Element trigger select.
    const list = sel.querySelector('.cf-select__list');    // Dropdown list opsi.
    if (trig.contains(e.target)) {                         // Jika klik di area trigger,
      sel.classList.toggle('is-open');                     // toggle buka/tutup dropdown.
    } else if (!list.contains(e.target)) {                 // Jika klik di luar trigger dan di luar list,
      sel.classList.remove('is-open');                     // pastikan dropdown tertutup.
    }
  });
});

document.querySelectorAll('.cf-select').forEach(sel=>{
  const targetId = sel.getAttribute('data-target');        // Ambil id field hidden yang dikontrol.
  const hidden = document.getElementById(targetId);        // Hidden input (role/status).
  const label  = document.getElementById(targetId + '_label'); // Label teks di trigger.
  sel.querySelectorAll('.cf-select__option').forEach(opt=>{
    opt.addEventListener('click', function(){
      sel.querySelectorAll('.cf-select__option').forEach(o=>o.classList.remove('is-active')); // Hapus aktif dari semua opsi.
      this.classList.add('is-active'); // Tandai opsi yang dipilih sebagai aktif.
      hidden.value = this.getAttribute('data-value'); // Isi hidden input dengan value opsi.
      label.textContent = this.textContent.trim();    // Update label teks di trigger.
      sel.classList.remove('is-open');                // Tutup dropdown setelah pilih.
    });
  });
});

/* ===== Modal Helpers ===== */
const modalEl = document.getElementById('userModal');     // Referensi elemen modal user.
const modal = new bootstrap.Modal(modalEl);               // Inisialisasi Bootstrap Modal.

function openAdd(){
  document.getElementById('userTitle').textContent = 'Tambah User'; // Set judul modal untuk mode tambah.
  document.getElementById('action').value = 'add';                 // Set action hidden ke add.
  document.getElementById('id').value = '';                        // Kosongkan id (tidak dipakai).
  document.getElementById('name').value = '';                      // Reset field nama.
  document.getElementById('email').value = '';                     // Reset field email.
  document.getElementById('password').value = '';                  // Reset field password.
  // reset custom select
  document.getElementById('role').value = 'customer';              // Default role customer.
  document.getElementById('role_label').textContent = 'Customer';  // Label trigger role.
  document.getElementById('status').value = 'active';              // Default status aktif.
  document.getElementById('status_label').textContent = 'Aktif';   // Label trigger status.
  document.querySelectorAll('[data-target="role"] .cf-select__option').forEach(o=>o.classList.remove('is-active')); // Reset active opsi role.
  document.querySelector('[data-target="role"] .cf-select__option[data-value="customer"]').classList.add('is-active'); // Set customer aktif.
  document.querySelectorAll('[data-target="status"] .cf-select__option').forEach(o=>o.classList.remove('is-active')); // Reset active opsi status.
  document.querySelector('[data-target="status"] .cf-select__option[data-value="active"]').classList.add('is-active'); // Set active aktif.

  modal.show(); // Tampilkan modal di layar.
}

function openEdit(id, row){
  document.getElementById('userTitle').textContent = 'Edit User'; // Ganti judul modal ke Edit User.
  document.getElementById('action').value = 'edit';              // Set action hidden ke edit.
  document.getElementById('id').value = id;                      // Isi hidden id dengan ID user.
  document.getElementById('name').value = row.name || '';        // Isi field nama dari data row.
  document.getElementById('email').value = row.email || '';      // Isi field email dari data row.
  document.getElementById('password').value = '';                // Kosongkan field password (tidak tampil hash).

  const roleVal = (row.role || 'customer').toLowerCase(); // Tentukan role user (default customer).
  document.getElementById('role').value = roleVal;        // Isi hidden role dengan nilai tersebut.
  document.getElementById('role_label').textContent =
    roleVal === 'admin' ? 'Admin' : (roleVal === 'karyawan' ? 'Karyawan' : 'Customer'); // Update label role yang tampil.
  document.querySelectorAll('[data-target="role"] .cf-select__option').forEach(o=>o.classList.remove('is-active')); // Reset opsi aktif role.
  document.querySelector(`[data-target="role"] .cf-select__option[data-value="${roleVal}"]`)?.classList.add('is-active'); // Tandai opsi role sesuai data user.

  const st = (row.status || 'active');               // Ambil status user (default active).
  document.getElementById('status').value = st;      // Isi hidden status.
  document.getElementById('status_label').textContent = st === 'active' ? 'Aktif' : 'Pending'; // Label status sesuai nilai.
  document.querySelectorAll('[data-target="status"] .cf-select__option').forEach(o=>o.classList.remove('is-active')); // Reset opsi aktif status.
  document.querySelector(`[data-target="status"] .cf-select__option[data-value="${st}"]`)?.classList.add('is-active'); // Tandai opsi status user.

  modal.show(); // Buka modal dalam mode edit dengan data terisi.
}

/* ===== Search client-side ===== */
const searchInput = document.getElementById('searchInput');   // Input search di topbar.
const searchIcon  = document.getElementById('searchIcon');    // Ikon search di kanan input.
const tableBody   = document.querySelector('#usersTable tbody'); // Tbody tabel user yang akan difilter.

function doFilter(){
  const q = (searchInput.value || '').toLowerCase(); // Ambil query pencarian dan ubah ke lowercase.
  tableBody.querySelectorAll('tr').forEach(tr => {
    const text = tr.innerText.toLowerCase(); // Gabungan teks di baris tersebut (lowercase).
    tr.style.display = text.includes(q) ? '' : 'none'; // Tampilkan baris jika mengandung query, jika tidak sembunyikan.
  });
}
searchInput?.addEventListener('input', doFilter); // Filter saat user mengetik di input.
searchIcon?.addEventListener('click', doFilter); // Filter juga saat ikon search diklik.
</script>
</body>
</html>
