<?php

// ---------------------------------------------
// File  : backend/api/admin_search.php
// Desc  : Endpoint search global khusus admin
// ---------------------------------------------

declare(strict_types=1);                        // Aktifkan strict typing

session_start();                                // Mulai session untuk baca login

require_once __DIR__.'/../config.php';        // Load konfigurasi (DB, BASE_URL, dll)
require_once __DIR__.'/../auth_guard.php';    // Load auth guard (jaga akses)

/*
 * ADMIN ONLY
 * Beda sama karyawan_search.php yang cuma bolehin karyawan.
 */

// Cek apakah user sudah login DAN rolenya admin
if (
    ! isset($_SESSION['user_id'])                  // Jika tidak ada user_id di session
    || (($_SESSION['user_role'] ?? '') !== 'admin') // Atau role bukan 'admin'
) {
    http_response_code(401);                      // Set HTTP status 401 Unauthorized

    header('Content-Type: application/json; charset=utf-8'); // Set header JSON

    echo json_encode([                            // Kirim JSON error
        'ok' => false,                         // Flag gagal
        'error' => 'Unauthorized',                // Pesan error singkat
        'message' => 'Silakan login sebagai admin.', // Pesan penjelasan
    ]);

    exit;                                         // Hentikan eksekusi script
}

// Set header respons JSON UTF-8
header('Content-Type: application/json; charset=utf-8');

// Ambil parameter 'q' dari query string lalu trim spasi
$q = trim(
    $_GET['q'] ?? ''                              // Jika tidak ada, pakai string kosong
);

// Jika q kosong atau panjang < 2 karakter, kembalikan hasil kosong
if (
    $q === ''                                     // Kosong
    || mb_strlen($q) < 2                          // Kurang dari 2 karakter
) {
    echo json_encode([                            // Kirim JSON sukses tapi tanpa hasil
        'ok' => true,                          // Flag sukses
        'results' => [],                             // Array hasil kosong
    ], JSON_UNESCAPED_UNICODE);                  // Jangan escape karakter Unicode

    exit;                                         // Hentikan skrip (tidak query ke DB)
}

// Siapkan pattern LIKE dengan wildcard %
$like = '%'.$q.'%';                         // Misal q = 'kopi' → '%kopi%'

// Array untuk menampung semua hasil gabungan
$results = [];                                  // Hasil search (orders, menu, users)

/* =========================================================
   1. CARI ORDERS (sama seperti karyawan, tapi admin lihat semua)
   ========================================================= */

// Query pencarian orders berdasar invoice_no atau customer_name
$sqlOrder = '
  SELECT
    id,
    invoice_no,
    customer_name,
    total,
    order_status
  FROM
    orders
  WHERE
    invoice_no   LIKE ?
    OR customer_name LIKE ?
  ORDER BY
    created_at DESC
  LIMIT 8
';

// Siapkan prepared statement untuk query orders
if ($stmt = $conn->prepare($sqlOrder)) {

    // Bind dua parameter string (untuk invoice_no & customer_name)
    $stmt->bind_param(
        'ss',                                       // Dua string: ?, ?
        $like,                                      // Untuk invoice_no LIKE ?
        $like                                       // Untuk customer_name LIKE ?
    );

    // Eksekusi query orders
    $stmt->execute();

    // Ambil result set
    $res = $stmt->get_result();

    // Loop tiap baris hasil orders
    while ($row = $res->fetch_assoc()) {

        // Tambahkan hasil ke array results dalam struktur rapi
        $results[] = [
            'type' => 'order',                       // Tipe item: order
            'key' => $row['invoice_no']             // Key utama: invoice_no jika ada
                        ?: ('#ORD-'.$row['id']),    // Jika tidak ada → "#ORD-{id}"
            'label' => $row['invoice_no']             // Label yang ditampilkan
                        ?: ('Order #'.$row['id']),  // Jika kosong → "Order #id"
            'sub' => (                              // Sub teks deskriptif
                $row['customer_name']                   // Jika ada customer_name
                  ? $row['customer_name'].' • '       // Tampilkan "Nama • "
                  : ''                                  // Kalau tidak ada → kosong
            )
            .'Status: '                              // Tambah teks "Status: "
            .($row['order_status'] ?: '-'),          // Isi status, jika null/kosong → '-'
        ];
    }

    // Tutup prepared statement orders
    $stmt->close();
}

/* =========================================================
   2. CARI MENU / CATALOG
   ========================================================= */

// Query pencarian menu berdasar name
$sqlMenu = '
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
';

// Siapkan prepared statement untuk query menu
if ($stmt = $conn->prepare($sqlMenu)) {

    // Bind satu parameter string (untuk name LIKE ?)
    $stmt->bind_param(
        's',                                        // Satu string
        $like                                       // Pattern LIKE
    );

    // Eksekusi query menu
    $stmt->execute();

    // Ambil result set
    $res = $stmt->get_result();

    // Loop tiap baris hasil menu
    while ($row = $res->fetch_assoc()) {

        // Tambahkan hasil menu ke array results
        $results[] = [
            'type' => 'menu',                        // Tipe item: menu
            'key' => (string) $row['id'],            // Key: id menu sebagai string
            'label' => $row['name'],                  // Label: nama menu
            'sub' => 'Stok: '                       // Awali dengan info stok
                       .($row['stock_status']        // Jika ada stock_status
                          ?? '-')                     // Kalau null → '-'
                       .(
                           $row['price'] !== null      // Jika price tidak null
                             ? ' • Rp '                // Tambah pemisah & label Rupiah
                               .number_format(        // Format angka harga
                                   (float) $row['price'], // Cast ke float
                                   0,                  // Tanpa desimal
                                   ',',                // Separator desimal
                                   '.'                 // Separator ribuan
                               )
                             : ''                      // Jika price null → tidak tambahkan apa pun
                       ),
        ];
    }

    // Tutup prepared statement menu
    $stmt->close();
}

/* =========================================================
   3. CARI USERS (ini bedanya admin, bisa cari user)
   ========================================================= */

// Query pencarian users berdasar name atau email
$sqlUsers = '
  SELECT
    id,
    name,
    email,
    role,
    status
  FROM
    users
  WHERE
    name  LIKE ?
    OR email LIKE ?
  ORDER BY
    name
  LIMIT 6
';

// Siapkan prepared statement untuk query users
if ($stmt = $conn->prepare($sqlUsers)) {

    // Bind dua parameter string (untuk name & email LIKE)
    $stmt->bind_param(
        'ss',                                       // Dua string
        $like,                                      // Untuk name LIKE ?
        $like                                       // Untuk email LIKE ?
    );

    // Eksekusi query users
    $stmt->execute();

    // Ambil result set
    $res = $stmt->get_result();

    // Loop tiap baris hasil users
    while ($row = $res->fetch_assoc()) {

        // Label utama: nama jika ada, kalau tidak pakai email
        $label = $row['name']                       // Jika name tidak kosong
          ?: $row['email'];                         // Kalau kosong → pakai email

        // Tambahkan hasil user ke array results
        $results[] = [
            'type' => 'user',                        // Tipe item: user
            'key' => (string) $row['id'],            // Key: id user sebagai string
            'label' => $label,                        // Label: nama atau email
            'sub' => (                              // Sub teks gabungan email, role, status
                $row['email']                           // Jika email ada
                  ? $row['email'].' • '               // Tampilkan "email • "
                  : ''                                  // Kalau tidak ada → kosong
            )
            .'Role: '                                // Tambah label Role
            .($row['role'] ?? '-')                   // Isi role, kalau null → '-'
            .' • Status: '                           // Tambah label Status
            .($row['status'] ?? '-'),                // Isi status, kalau null → '-'
        ];
    }

    // Tutup prepared statement users
    $stmt->close();
}

/* =========================================================
   RESPON JSON KE CLIENT
   ========================================================= */

// Kirim JSON berisi semua hasil pencarian
echo json_encode(
    [
        'ok' => true,                          // Flag sukses
        'results' => $results,                      // Array gabungan hasil
    ],
    JSON_UNESCAPED_UNICODE                        // Jangan escape karakter Unicode
);
