<?php
// ---------------------------------------------
// File  : backend/api/karyawan_search.php
// Desc  : Endpoint search untuk karyawan
// ---------------------------------------------

declare(strict_types=1);                        // Aktifkan strict typing

session_start();                                // Mulai session untuk baca user login

// Lokasi file ini: /backend/api/karyawan_search.php
// Jadi config.php dan auth_guard.php cukup naik 1 folder
require_once __DIR__ . '/../config.php';        // Load konfigurasi (DB, BASE_URL, dll)
require_once __DIR__ . '/../auth_guard.php';    // Load helper auth (jika dibutuhkan)

/**
 * Karena ini endpoint API, kita TIDAK mau redirect ke login.html
 * kalau tidak punya session. Kita balikin JSON 401 saja.
 */
if (
  !isset($_SESSION['user_id'])                  // Jika belum ada user_id di session
  || (($_SESSION['user_role'] ?? '') !== 'karyawan') // Atau role bukan karyawan
) {
  http_response_code(401);                      // Set HTTP status 401 Unauthorized

  header('Content-Type: application/json; charset=utf-8'); // Set header JSON

  echo json_encode([                            // Kirim respons JSON error
    'ok'     => false,                          // Flag gagal
    'error'  => 'Unauthorized',                 // Pesan error singkat
    'message'=> 'Silakan login sebagai karyawan.' // Pesan detail untuk client
  ]);
  exit;                                         // Hentikan eksekusi script
}

// Set header respons sebagai JSON UTF-8
header('Content-Type: application/json; charset=utf-8');

// Ambil query pencarian dari parameter GET 'q'
$q = trim(
  $_GET['q'] ?? ''                              // Jika tidak ada, gunakan string kosong
);

// Jika query kosong atau panjang < 2 karakter
if (
  $q === ''                                     // Kosong
  || mb_strlen($q) < 2                          // Atau kurang dari 2 karakter (terlalu pendek)
) {
  echo json_encode([                            // Kembalikan JSON sukses tapi tanpa hasil
    'ok'      => true,                          // Flag sukses
    'results' => []                             // Array hasil kosong
  ], JSON_UNESCAPED_UNICODE);                  // Jangan escape karakter Unicode
  exit;                                         // Hentikan script (tidak lanjut query DB)
}

// Susun pattern LIKE dengan wildcard %
$like = '%' . $q . '%';                         // Contoh: '%teh%'

// Siapkan array untuk menampung semua hasil search
$results = [];                                  // Nanti berisi data orders & menu

/* =================================================
   CARI ORDERS
   Tabel: orders (id, invoice_no, customer_name, total, order_status)
   ================================================= */
$sqlOrder = "
  SELECT
    id,
    invoice_no,
    customer_name,
    total,
    order_status
  FROM
    orders
  WHERE
    invoice_no LIKE ?
    OR customer_name LIKE ?
  ORDER BY
    created_at DESC
  LIMIT 6
";                                              // Query pencarian orders maksimal 6 baris

// Siapkan prepared statement untuk query orders
if ($stmt = $conn->prepare($sqlOrder)) {

  // Bind parameter LIKE (invoice_no dan customer_name)
  $stmt->bind_param(
    'ss',                                       // Dua parameter string
    $like,                                      // Untuk invoice_no LIKE ?
    $like                                       // Untuk customer_name LIKE ?
  );

  $stmt->execute();                             // Eksekusi query

  $res = $stmt->get_result();                   // Ambil result set

  // Loop setiap baris hasil query orders
  while ($row = $res->fetch_assoc()) {

    // Tambahkan data order ke array results
    $results[] = [
      'type'  => 'order',                       // Tipe item = order
      'key'   => $row['invoice_no']             // Key utama: invoice_no jika ada
                  ?: ('#ORD-' . $row['id']),    // Kalau invoice_no kosong → pakai #ORD-id
      'label' => $row['invoice_no']             // Label utama: invoice_no
                  ?: ('Order #' . $row['id']),  // Kalau kosong → "Order #id"
      'sub'   => (                              // Subteks (info tambahan)
        $row['customer_name']                   // Jika ada nama customer
          ? $row['customer_name'] . ' • '       // Tampilkan "Nama • "
          : ''                                  // Kalau tidak ada, kosong
      ) . 'Status: '                            // Tambah kata "Status: "
      . ($row['order_status'] ?: '-')           // Isi status, jika null/kosong → '-'
    ];
  }

  $stmt->close();                               // Tutup prepared statement orders
}

/* =================================================
   CARI MENU
   Tabel: menu (id, name, stock_status, price)
   ================================================= */
$sqlMenu = "
  SELECT
    id,
    name,
    stock_status,
    price
  FROM
    menu
  WHERE
    name LIKE ?
  ORDER BY
    name
  LIMIT 6
";                                              // Query pencarian menu maksimal 6 baris

// Siapkan prepared statement untuk query menu
if ($stmt = $conn->prepare($sqlMenu)) {

  // Bind parameter LIKE untuk nama menu
  $stmt->bind_param(
    's',                                        // Satu parameter string
    $like                                       // Untuk name LIKE ?
  );

  $stmt->execute();                             // Eksekusi query

  $res = $stmt->get_result();                   // Ambil result set

  // Loop setiap baris hasil query menu
  while ($row = $res->fetch_assoc()) {

    // Bentuk teks sub: stok + harga jika ada
    $results[] = [
      'type'  => 'menu',                        // Tipe item = menu
      'key'   => $row['name'],                  // Key: nama menu
      'label' => $row['name'],                  // Label utama: nama menu
      'sub'   => 'Stok: '                       // Awali dengan keterangan stok
                 . ($row['stock_status']        // Jika ada status stok di DB
                    ?? '-')                     // Kalau null → '-'
                 . (                            // Lanjutkan kalau ada harga
                    $row['price'] !== null      // Cek harga tidak null
                      ? ' • Rp '                // Tambah pemisah dan label "Rp"
                        . number_format(        // Format harga ke Rupiah
                            (float)$row['price'], // Cast ke float
                            0,                  // Tanpa desimal
                            ',',                // Separator desimal
                            '.'                 // Separator ribuan
                          )
                      : ''                      // Jika price null → tidak tambah apa-apa
                   ),
    ];
  }

  $stmt->close();                               // Tutup prepared statement menu
}

// =================================================
// KIRIM RESPON JSON KE CLIENT
// =================================================
echo json_encode(                               // Encode hasil ke JSON
  [
    'ok'      => true,                          // Flag sukses
    'results' => $results                       // Gabungan hasil dari orders & menu
  ],
  JSON_UNESCAPED_UNICODE                        // Jangan escape karakter Unicode
);
