<?php
// Pembuka file PHP

// backend/api/users.php
// API untuk mengambil daftar user (khusus admin)

declare(strict_types=1);
// Aktifkan strict typing

header('Content-Type: application/json; charset=utf-8');
// Set header respons menjadi JSON UTF-8

require_once __DIR__ . '/../config.php';
// Import konfigurasi (DB, session setup, dsb.)

session_start();
// Mulai atau lanjutkan session

if (
  !isset($_SESSION['user_id']) ||
  ($_SESSION['user_role'] ?? '') !== 'admin'
) {
  // Cek apakah user sudah login & memiliki role admin

  http_response_code(403);
  // Set status 403 Forbidden

  echo json_encode(['ok' => false, 'error' => 'forbidden']);
  // Kirim JSON error

  exit;
  // Hentikan eksekusi
}

$qRaw    = $_GET['q']      ?? '';
// Ambil query search dari parameter GET (nama/email)

$roleRaw = $_GET['role']   ?? '';
// Ambil filter role dari GET

$statRaw = $_GET['status'] ?? '';
// Ambil filter status (pending/active)

$q      = trim((string)$qRaw);
// Trim whitespace nilai pencarian

$role   = trim((string)$roleRaw);
// Trim whitespace untuk role

$status = trim((string)$statRaw);
// Trim whitespace untuk status

$where  = [];
// Array untuk menyimpan kondisi WHERE

$params = [];
// Array parameter untuk prepared statement

$types  = '';
// String untuk tipe bind_param (misalnya "sss")

if ($q !== '') {
  // Jika ada teks pencarian

  $where[] = '(name LIKE ? OR email LIKE ?)';
  // Tambah klausul pencarian nama/email

  $like = '%' . $q . '%';
  // Format LIKE pattern

  $params[] = $like;
  // Parameter pertama untuk name LIKE

  $params[] = $like;
  // Parameter kedua untuk email LIKE

  $types   .= 'ss';
  // Tambah tipe parameter untuk bind_param
}

if (in_array($role, ['admin', 'customer', 'karyawan'], true)) {
  // Jika role valid untuk difilter

  $where[] = 'role = ?';
  // Tambahkan kondisi role

  $params[] = $role;
  // Tambahkan nilai role ke params

  $types   .= 's';
  // Tambah tipe parameter
}

if (in_array($status, ['pending', 'active'], true)) {
  // Jika filter status valid

  $where[] = 'status = ?';
  // Tambah WHERE status

  $params[] = $status;
  // Masukan parameter status

  $types   .= 's';
  // Tambahkan tipe bind_param
}

$sql =
  "SELECT id, name, email, role, status, created_at
   FROM users";
// Query dasar tanpa filter

if ($where) {
  // Jika ada kondisi WHERE

  $sql .= ' WHERE ' . implode(' AND ', $where);
  // Gabungkan seluruh kondisi dengan AND
}

$sql .= " ORDER BY created_at DESC";
// Urutkan dari yang terbaru

$items = [];
// Array hasil akhir user list

$stmt = $conn->prepare($sql);
// Siapkan prepared statement

if ($stmt) {
  // Hanya eksekusi jika prepare sukses

  if ($params) {
    // Jika ada parameter untuk bind

    $stmt->bind_param($types, ...$params);
    // Bind parameter ke query
  }

  $stmt->execute();
  // Eksekusi query

  $res = $stmt->get_result();
  // Ambil hasil statement

  while ($r = $res->fetch_assoc()) {
    // Loop setiap baris hasil query

    $items[] = [
      'id'         => (int)$r['id'],
      // Cast id → integer

      'name'       => (string)$r['name'],
      // Nama user

      'email'      => (string)$r['email'],
      // Email user

      'role'       => (string)$r['role'],
      // Role user (admin/karyawan/customer)

      'status'     => (string)$r['status'],
      // Status user (pending/active)

      'created_at' => (string)$r['created_at'],
      // Waktu dibuat
    ];
  }

  $stmt->close();
  // Tutup statement
}

echo json_encode(
  ['ok' => true, 'items' => $items],
  JSON_UNESCAPED_SLASHES
);
// Kirim hasil respons JSON tanpa escape slash
