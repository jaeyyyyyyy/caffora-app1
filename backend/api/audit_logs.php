<?php
// ---------------------------------------------
// File  : backend/api/audit_logs.php
// Desc  : API untuk list audit logs (khusus admin)
// ---------------------------------------------

declare(strict_types=1);                        // Aktifkan strict typing

session_start();                                // Mulai session (cek login admin)

require_once __DIR__ . '/../config.php';        // Load config (DB, BASE_URL, dll)
require_once __DIR__ . '/../auth_guard.php';    // Load auth guard (require_login)

// Hanya admin yang boleh mengakses audit log
require_login(['admin']);                      // Batasi akses ke role 'admin'

header('Content-Type: application/json; charset=utf-8'); // Response JSON UTF-8

/* =========================
   KONEKSI
========================= */

// Jika $conn belum ada atau bukan instance mysqli
if (
  !isset($conn)
  || !($conn instanceof mysqli)
) {
  // Buat koneksi mysqli baru
  $conn = @new mysqli(
    DB_HOST,                                    // Host database
    DB_USER,                                    // Username database
    DB_PASS,                                    // Password database
    DB_NAME                                     // Nama database
  );

  // Jika gagal konek, balas error 500
  if ($conn->connect_errno) {
    http_response_code(500);                   // Set HTTP status 500
    echo json_encode([                         // Kirim JSON error
      'ok'    => false,
      'error' => 'db connect failed'
    ]);
    exit;                                      // Hentikan script
  }

  // Set charset ke utf8mb4
  $conn->set_charset('utf8mb4');
}

/* =========================
   PARAM & DEFAULT
========================= */

// Keyword pencarian bebas
$q = trim(
  (string)(
    $_GET['q'] ?? ''
  )
);

// Filter entity (order|payment)
$entity = strtolower(
  trim(
    (string)(
      $_GET['entity'] ?? ''
    )
  )
);

// Filter berdasarkan actor_id (user yang melakukan aksi)
$actor_id = (int)(
  $_GET['actor_id'] ?? 0
);

// Filter tanggal mulai (format YYYY-MM-DD)
$from = trim(
  (string)(
    $_GET['from'] ?? ''
  )
);

// Filter tanggal akhir (format YYYY-MM-DD)
$to = trim(
  (string)(
    $_GET['to'] ?? ''
  )
);

// Halaman yang diminta (minimal 1)
$page = max(
  1,
  (int)(
    $_GET['page'] ?? 1
  )
);

// Jumlah data per halaman (1–100)
$perPage = min(
  100,
  max(
    1,
    (int)(
      $_GET['per_page'] ?? 20
    )
  )
);

// Flag hanya log penting (default 1)
$importantOnly = (int)(
  $_GET['important_only'] ?? 1
);

// Parameter sort, default created_at_desc
$sort = $_GET['sort'] ?? 'created_at_desc';

// Map pilihan sort ke klausa ORDER BY SQL
$sortMap = [
  'created_at_desc' => 'a.created_at DESC, a.id DESC', // terbaru dulu
  'created_at_asc'  => 'a.created_at ASC,  a.id ASC',  // terlama dulu
  'actor_asc'       => 'a.actor_id ASC,   a.created_at DESC', // urut actor naik
  'actor_desc'      => 'a.actor_id DESC,  a.created_at DESC', // urut actor turun
];

// Ambil ORDER BY dari map, fallback ke created_at_desc
$orderBy = $sortMap[$sort] ?? $sortMap['created_at_desc'];

/* =========================
   BANGUN WHERE DINAMIS
========================= */

// Array untuk menampung potongan WHERE
$where = [];

// String tipe parameter untuk bind_param
$types = '';

// Array nilai parameter untuk bind_param
$params = [];

/* 1) Batasi entitas ke order & payment secara default */

// Hanya mengizinkan entity order dan payment
$allowedEntities = ['order', 'payment'];

// Jika entity di-isi dan termasuk allowedEntities
if (
  $entity !== ''
  && in_array($entity, $allowedEntities, true)
) {
  $where[] = 'a.entity = ?';                   // Tambah where entity = ?
  $types  .= 's';                             // Tambah tipe string
  $params[] = $entity;                        // Tambah nilai entity
} else {
  // Jika tidak difilter, batasi ke dua entity ini saja
  $where[] = "(a.entity IN ('order','payment'))";
}

/* 2) Important-only rules */

// Jika hanya log penting
if ($importantOnly === 1) {
  // Aturan log penting:
  // - Semua entity 'order'
  // - entity 'payment' dengan action tertentu
  //   atau to_val mengandung 'paid'
  $where[] = "(
      a.entity = 'order'
      OR (
        a.entity = 'payment'
        AND (
          a.action IN ('paid','mark_paid','refund','update_status')
          OR a.to_val LIKE '%paid%'
          OR a.to_val LIKE '%\"paid\"%'
        )
      )
    )";
}

/* 3) Filter Actor */

// Jika actor_id > 0 (valid)
if ($actor_id > 0) {
  $where[] = 'a.actor_id = ?';                // Tambah where actor_id
  $types  .= 'i';                             // Tambah tipe integer
  $params[] = $actor_id;                      // Tambah nilai actor_id
}

/* 4) Rentang tanggal (pakai created_at) */

// Validasi format tanggal from (YYYY-MM-DD)
$fromOk = preg_match(
  '/^\d{4}-\d{2}-\d{2}$/',
  $from
);

// Validasi format tanggal to (YYYY-MM-DD)
$toOk = preg_match(
  '/^\d{4}-\d{2}-\d{2}$/',
  $to
);

// Jika from & to keduanya valid
if ($fromOk && $toOk) {
  $where[] = 'a.created_at BETWEEN ? AND ?';   // Filter rentang tanggal
  $types  .= 'ss';                             // Dua parameter string
  $params[] = $from . ' 00:00:00';             // Tanggal mulai jam 00:00:00
  $params[] = $to   . ' 23:59:59';             // Tanggal akhir jam 23:59:59

// Jika hanya from yang valid
} elseif ($fromOk) {
  $where[] = 'a.created_at >= ?';              // Filter mulai tanggal from
  $types  .= 's';                              // Satu parameter string
  $params[] = $from . ' 00:00:00';

// Jika hanya to yang valid
} elseif ($toOk) {
  $where[] = 'a.created_at <= ?';              // Filter sampai tanggal to
  $types  .= 's';                              // Satu parameter string
  $params[] = $to . ' 23:59:59';
}

/* 5) Query text (remark/action/from/to + entity_id angka) */

// Jika keyword pencarian teks tidak kosong
if ($q !== '') {

  // Siapkan pattern LIKE
  $like = '%' . $q . '%';

  // Sub-klausa untuk pencarian di beberapa kolom
  $sub = '('
       . 'a.remark LIKE ? '
       . 'OR a.action LIKE ? '
       . 'OR a.from_val LIKE ? '
       . 'OR a.to_val LIKE ?';

  // Tambah 4 parameter string ke types & params
  $types  .= 'ssss';
  $params[] = $like;                           // remark LIKE ?
  $params[] = $like;                           // action LIKE ?
  $params[] = $like;                           // from_val LIKE ?
  $params[] = $like;                           // to_val LIKE ?

  // Jika q hanya berisi digit → anggap bisa juga entity_id
  if (ctype_digit($q)) {
    $sub   .= ' OR a.entity_id = ?';           // Tambah filter entity_id
    $types .= 'i';                             // Tambah tipe integer
    $params[] = (int)$q;                       // Tambah nilai entity_id
  }

  $sub   .= ')';                               // Tutup sub-klausa
  $where[] = $sub;                             // Tambah ke array where
}

// Susun WHERE final (jika ada)
$whereSql = $where
  ? 'WHERE ' . implode(' AND ', $where)        // Gabungkan dengan AND
  : '';                                        // Jika tidak ada → string kosong

/* =========================
   COUNT TOTAL
   (Join ke users cukup; join ke orders tidak dibutuhkan untuk hitung)
========================= */

// Query hitung total baris yang cocok filter
$countSql = "
  SELECT
    COUNT(*) AS c
  FROM
    audit_logs a
  LEFT JOIN
    users u ON u.id = a.actor_id
  $whereSql
";

// Siapkan prepared statement untuk count
$st = $conn->prepare($countSql);

// Jika ada parameter, bind types dan params
if ($types !== '') {
  $st->bind_param(
    $types,                                   // Tipe parameter
    ...$params                                // Nilai parameter (spread array)
  );
}

// Eksekusi query count
$st->execute();

// Ambil hasil count
$totalRows = (int)(
  $st->get_result()
    ->fetch_assoc()['c'] ?? 0
);

// Tutup statement count
$st->close();

// Hitung total halaman berdasarkan totalRows & perPage
$totalPages = max(
  1,
  (int)ceil(
    $totalRows / $perPage
  )
);

// Pastikan page tidak lebih dari totalPages
$page = min(
  $page,
  $totalPages
);

// Hitung offset untuk LIMIT
$offset = ($page - 1) * $perPage;

/* =========================
   AMBIL DATA
   >>> Tambahan penting: JOIN ke orders untuk ambil invoice_no
   >>> Berlaku untuk entity 'order' dan 'payment' (keduanya pakai order_id=entity_id)
========================= */

// Query utama untuk ambil data audit log
$selectSql = "
  SELECT
    a.id,
    a.created_at,
    a.actor_id,
    COALESCE(u.name, '') AS actor_name,
    COALESCE(u.role, '') AS actor_role,
    a.entity,
    a.entity_id,
    a.action,
    a.from_val,
    a.to_val,
    a.remark,
    COALESCE(o.invoice_no, '') AS invoice
  FROM
    audit_logs a
  LEFT JOIN
    users u ON u.id = a.actor_id
  LEFT JOIN
    orders o
      ON o.id = a.entity_id
     AND a.entity IN ('order','payment')
  $whereSql
  ORDER BY
    $orderBy
  LIMIT
    ? OFFSET ?
";

// Siapkan prepared statement untuk select data
$st2 = $conn->prepare($selectSql);

// Tambahkan dua parameter integer (LIMIT & OFFSET)
$types2 = $types . 'ii';

// Buat array argumen kedua (gabung params + limit/offset)
$args2 = $params;
$args2[] = $perPage;                          // Limit
$args2[] = $offset;                           // Offset

// Bind semua parameter (filters + limit & offset)
$st2->bind_param(
  $types2,                                    // String tipe semua parameter
  ...$args2                                   // Semua nilai parameter
);

// Eksekusi query utama
$st2->execute();

// Ambil result set
$res = $st2->get_result();

// Siapkan array data hasil
$data = [];

// Loop setiap baris hasil query
while ($r = $res->fetch_assoc()) {

  // Tambahkan ke array data dalam format rapi
  $data[] = [
    'id'         => (int)$r['id'],            // ID log
    'created_at' => (string)$r['created_at'], // Waktu dibuat
    'actor_id'   => $r['actor_id'] !== null   // Jika actor_id tidak null
      ? (int)$r['actor_id']                   // Konversi ke integer
      : null,                                 // Kalau null → null
    'actor_name' => (string)$r['actor_name'], // Nama actor
    'actor_role' => (string)$r['actor_role'], // Role actor
    'entity'     => (string)$r['entity'],     // Entity (order/payment/dll)
    'entity_id'  => (int)$r['entity_id'],     // ID entitas
    'action'     => (string)$r['action'],     // Aksi
    'from_val'   => (string)(
      $r['from_val'] ?? ''                    // Nilai sebelumnya (bisa kosong)
    ),
    'to_val'     => (string)(
      $r['to_val']   ?? ''                    // Nilai sesudah (bisa kosong)
    ),
    'remark'     => (string)(
      $r['remark']   ?? ''                    // Catatan
    ),
    // Selalu tersedia (atau string kosong) karena LEFT JOIN orders
    'invoice'    => (string)$r['invoice'],    // Nomor invoice (jika ada)
  ];
}

// Tutup statement select
$st2->close();

/* =========================
   RESPON
========================= */

// Kirim respons JSON ke client
echo json_encode(
  [
    'ok' => true,                              // Flag sukses

    // Kirim kembali filter yang dipakai (untuk debugging/UI)
    'filters' => [
      'q'              => $q,                 // Keyword pencarian
      'entity'         => $entity,           // Filter entity
      'actor_id'       => $actor_id,         // Filter actor_id
      'from'           => $from,             // Tanggal from
      'to'             => $to,               // Tanggal to
      'sort'           => $sort,             // Opsi sort
      'important_only' => $importantOnly,    // Apakah hanya log penting
    ],

    // Informasi pagination
    'pagination' => [
      'page'       => $page,                 // Halaman sekarang
      'per_page'   => $perPage,              // Data per halaman
      'total_rows' => $totalRows,            // Total baris cocok filter
      'total_pages'=> $totalPages,           // Total halaman
    ],

    // Data audit log yang dihasilkan
    'data' => $data,
  ],
  JSON_UNESCAPED_UNICODE                      // Jangan escape karakter Unicode
  | JSON_UNESCAPED_SLASHES                    // Jangan escape karakter '/'
);
