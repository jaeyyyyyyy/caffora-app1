<?php
// Pembuka file PHP

// backend/logout.php
// File untuk melakukan proses logout user

declare(strict_types=1);
// Aktifkan strict typing

require_once __DIR__.'/config.php';
// Import konfigurasi (BASE_URL, session settings, helper)

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
// Mulai session bila belum aktif

/* --- bersihkan session --- */
$_SESSION = [];
// Kosongkan semua data session

if (ini_get('session.use_cookies')) {
  // Jika session menggunakan cookie

  $p = session_get_cookie_params();
  // Ambil parameter cookie session

  setcookie(
    session_name(),
    '',
    time() - 42000,
    $p['path'],
    $p['domain'],
    $p['secure'],
    $p['httponly']
  );
  // Hapus cookie session dengan waktu kedaluwarsa lampau
}

session_destroy();
// Hancurkan session sepenuhnya

/* --- hapus cookies app --- */
$https =
  (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
  || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
// Deteksi apakah koneksi HTTPS (termasuk via proxy/CDN)

$cookieOpts = [
  'expires'  => time() - 3600,
  'path'     => '/',
  'secure'   => $https,
  'httponly' => false,
  'samesite' => 'Lax'
];
// Opsi cookie untuk menghapus cookie aplikasi

setcookie('caffora_auth', '', $cookieOpts);
// Hapus cookie auth flag

setcookie('caffora_uid', '', $cookieOpts);
// Hapus cookie user ID

/* --- minta browser bersihkan storage (Chrome/Edge/Firefox modern) --- */
header('Clear-Site-Data: "storage", "cookies"');
// Perintah untuk membersihkan localStorage, sessionStorage, cookies

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
// Nonaktifkan cache browser

header('Pragma: no-cache');
// Tambahan header anti-cache

/* --- fallback JS: bersihkan storage lalu redirect ke /login --- */
$to = rtrim((string)BASE_URL, '/') . '/login';
// Target redirect akhir setelah logout
?>
<!doctype html>
<!-- Dokumen HTML minimal untuk eksekusi JS logout -->
<html>
  <meta charset="utf-8">
  <!-- Set charset UTF-8 -->

  <title>Logging out…</title>
  <!-- Judul sederhana -->

<script>
try {
  // Blok cleanup aman dalam try-catch

  // hapus semua key keranjang & sisa legacy
  const PREFIXES = [
    'caffora_cart_',
    'cart',
    'wishlist',
    'caffora_'
  ];
  // Daftar prefix untuk menghapus item localStorage lama

  for (let i = 0; i < localStorage.length; i++) {
    const k = localStorage.key(i);
    // Nama key localStorage saat ini

    if (!k) continue;
    // Lewatkan jika key null

    if (PREFIXES.some(p => k.startsWith(p)))
      localStorage.removeItem(k);
    // Hapus key jika prefix cocok
  }

  sessionStorage.clear?.();
  // Bersihkan sessionStorage jika didukung browser

  // bersihkan CacheStorage (kalau pernah pakai service worker)
  if (window.caches?.keys)
    caches.keys().then(keys =>
      keys.forEach(k => caches.delete(k))
    );
  // Hapus semua cache service worker
} catch (e) {
  // Abaikan error agar logout tetap berlanjut
}

location.replace(<?= json_encode($to) ?>);
// Redirect ke halaman login
</script>

<noscript>
  <!-- Fallback jika JavaScript dimatikan -->
  <meta http-equiv="refresh" content="0;url=<?= htmlspecialchars($to, ENT_QUOTES) ?>">
</noscript>

<body>
  Logging out…
  <!-- Pesan singkat selama redirect -->
</body>

</html>
