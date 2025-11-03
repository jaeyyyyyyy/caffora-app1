<?php
// public/customer/notifications.php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../../backend/auth_guard.php';
require_login(['customer']);
require_once __DIR__ . '/../../backend/config.php';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
  die("Koneksi gagal: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

$userId = (int)($_SESSION['user_id'] ?? 0);

$sql = "
  SELECT id, user_id, role, message, status, created_at, link
  FROM notifications
  WHERE user_id = ?
     OR (user_id IS NULL AND (role IS NULL OR role = 'customer'))
  ORDER BY created_at DESC
  LIMIT 100
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $userId);
$stmt->execute();
$res = $stmt->get_result();

$notifs = [];
while ($row = $res->fetch_assoc()) {
  $notifs[] = $row;
}
$stmt->close();

$BASE = '/caffora-app1';
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Notifikasi — Caffora</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <!-- ikon bi supaya sama dengan profile / checkout -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root{
      --gold:#FFD54F;
      --brown:#4B3F36;
      --page:#FFFDF8;
      --ink:#111827;
      --muted:#6b7280;
      --line:rgba(0,0,0,.03);
    }
    *{
      box-sizing:border-box;
      font-family:Poppins,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;
    }
    body{
      background:var(--page);
      color:var(--ink);
      margin:0;
    }

    /* ===== TOPBAR (disamain dgn profile / checkout) ===== */
    .topbar{
      position:sticky;
      top:0;
      z-index:50;
      background:#fff;
      border-bottom:1px solid #efefef;
    }
    .topbar .inner{
      max-width:1200px;
      margin:0 auto;
      padding:12px 24px;
      min-height:52px;
      display:flex;
      align-items:center;
      gap:8px;
    }
    .back-link{
      display:inline-flex;
      align-items:center;
      gap:10px;
      color:var(--ink);
      text-decoration:none;
      font-weight:600;
      font-size:1rem;
      line-height:1.3;
      cursor:pointer;
    }
    .back-link .chev{
      font-size:18px;
      line-height:1;
    }

    /* ===== PAGE WRAPPER ===== */
    .page{
      max-width:1200px;
      margin:0 auto;
      padding:14px 24px 56px;
    }

    /* ===== NOTIF CARD ===== */
    .notif-card{
      background:#fff;
      border:1px solid rgba(75,63,54,.04);
      border-radius:18px;
      padding:16px 20px 14px;
      display:flex;
      gap:14px;
      cursor:pointer;
      transition:box-shadow .15s ease;
      box-shadow:0 1px 2px rgba(0,0,0,0.02);
    }
    .notif-card + .notif-card{
      margin-top:12px;
    }
    .notif-card:hover{
      box-shadow:0 4px 16px rgba(0,0,0,0.04);
    }
    .notif-unread{
      background:#FFF3C4;
      border-color:rgba(255,213,79,.35);
    }
    .notif-body{flex:1;}
    .notif-msg{
      font-size:.95rem;
      line-height:1.6;
      color:#111827;
    }
    .notif-time{
      font-size:.8rem;
      color:var(--muted);
      margin-top:4px;
    }
    .empty-box{
      background:#fff;
      border-radius:18px;
      border:1px dashed rgba(0,0,0,.04);
      text-align:center;
      padding:40px 24px;
      color:var(--muted);
    }

    /* ===== RESPONSIVE ===== */
    @media (max-width:700px){
      .topbar .inner{
        max-width:100%;
        padding:12px 16px;   /* nempel kiri sama seperti profile */
      }
      .page{
        max-width:100%;
        padding:12px 16px 8px;  /* jarak atas kecil biar deket header, bawah dikurangin */
      }
      .notif-card{
        border-radius:14px;
        padding:14px 14px 12px;
      }
    }
  </style>
</head>
<body>
  <!-- HEADER -->
  <div class="topbar">
    <div class="inner">
      <a href="<?= htmlspecialchars($BASE) ?>/public/customer/index.php" class="back-link">
        <i class="bi bi-arrow-left chev"></i>
        <span>Kembali</span>
      </a>
    </div>
  </div>

  <!-- LIST NOTIF -->
  <main class="page">
    <?php if (!count($notifs)): ?>
      <div class="empty-box">Belum ada notifikasi.</div>
    <?php else: ?>
      <?php foreach ($notifs as $n): ?>
        <div
          class="notif-card <?= $n['status'] === 'unread' ? 'notif-unread' : '' ?>"
          <?php if (!empty($n['link'])): ?>
            data-link="<?= htmlspecialchars($n['link'], ENT_QUOTES, 'UTF-8') ?>"
          <?php endif; ?>
        >
          <div class="notif-body">
            <div class="notif-msg">
              <?= htmlspecialchars($n['message'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div class="notif-time" data-time="<?= htmlspecialchars($n['created_at'], ENT_QUOTES, 'UTF-8') ?>"></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </main>

  <script>
    // format waktu "x menit lalu"
    function formatTimeAgo(dateStr){
      const t = new Date(dateStr).getTime();
      if (isNaN(t)) return dateStr;
      const diff = (Date.now() - t)/1000;
      if (diff < 60) return Math.floor(diff) + " detik lalu";
      if (diff < 3600) return Math.floor(diff/60) + " menit lalu";
      if (diff < 86400) return Math.floor(diff/3600) + " jam lalu";
      if (diff < 604800) return Math.floor(diff/86400) + " hari lalu";
      return new Date(dateStr).toLocaleString("id-ID");
    }
    function refreshTimes(){
      document.querySelectorAll(".notif-time[data-time]").forEach(el=>{
        el.textContent = formatTimeAgo(el.dataset.time);
      });
    }
    refreshTimes();
    setInterval(refreshTimes, 10000);

    // klik kartu → buka link
    document.querySelectorAll(".notif-card[data-link]").forEach(card=>{
      card.addEventListener("click", ()=>{
        const link = card.getAttribute("data-link");
        if (link) window.location.href = link;
      });
    });

    // tandai read semua
    fetch("<?= $BASE ?>/backend/api/notifications.php?action=mark_all_read", {
      credentials: "same-origin"
    }).catch(()=>{});
  </script>
</body>
</html>
