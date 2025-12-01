<?php

// ---------------------------------------------
// File  : backend/api/finance.php
// Desc  : API ringkasan finansial (revenue, top menu)
// ---------------------------------------------

declare(strict_types=1);                        // Aktifkan strict typing

// Set header respons JSON dengan charset UTF-8
header('Content-Type: application/json; charset=utf-8');

// Load konfigurasi (harusnya sudah ada $conn (mysqli) & BASE_URL)
require_once __DIR__.'/../config.php';

// ------------------------------------------------------
// Kalau mau pakai session guard admin, bisa aktifkan ini
// ------------------------------------------------------
// session_start();                              // Mulai session
// if (
//   !isset($_SESSION['user_id'])                // Jika belum login
//   || (($_SESSION['user_role'] ?? '') !== 'admin') // Atau bukan admin
// ) {
//   http_response_code(403);                    // Forbidden
//   echo json_encode([                          // Kirim JSON error
//     'ok'    => false,
//     'error' => 'forbidden'
//   ]);
//   exit;                                       // Hentikan eksekusi
// }

/**
 * send_error()
 * Helper untuk mengirim respons error JSON lalu keluar.
 */
function send_error(
    string $msg,                                  // Pesan error
    int $code = 400                               // HTTP status code, default 400
): void {
    http_response_code($code);                    // Set HTTP status code
    echo json_encode(                             // Kirim JSON error
        [
            'ok' => false,                         // Flag gagal
            'error' => $msg,                           // Pesan error
        ],
        JSON_UNESCAPED_UNICODE                      // Jangan escape karakter Unicode
    );
    exit;                                         // Hentikan eksekusi script
}

// Jika $conn tidak ada atau koneksi error
if (! isset($conn) || $conn->connect_errno) {
    send_error(                                   // Kirim error 500
        'db not connected',                         // Pesan error
        500                                         // HTTP code 500
    );
}

/* ============================================================
   BACA PARAMETER RANGE
   ============================================================ */

// Ambil parameter range dari query string (default '7d')
$range = $_GET['range'] ?? '7d';

// Buat objek DateTime untuk hari ini (tanpa jam)
$today = new DateTime('today');

// endDate = hari ini (default)
$endDate = clone $today;

// startDate juga awalnya hari ini (akan dimodif sesuai range)
$startDate = clone $today;

// Jika range = 'today'
if ($range === 'today') {

    // Dari pagi sampai malam hari ini (start tetap today 00:00:00)
    $startDate = clone $today;

} elseif ($range === '30d') {

    // Range 30 hari: mundur 29 hari (jadi total 30 hari termasuk hari ini)
    $startDate->modify('-29 day');

} elseif ($range === 'custom') {

    // Custom range: ambil from dan to dari query string
    $from = $_GET['from'] ?? '';                 // Tanggal awal (Y-m-d)
    $to = $_GET['to'] ?? '';                 // Tanggal akhir (Y-m-d)

    // Jika keduanya ada
    if ($from && $to) {

        // Parse tanggal from dengan format Y-m-d
        $tmpStart = DateTime::createFromFormat(
            'Y-m-d',
            $from
        );

        // Parse tanggal to dengan format Y-m-d
        $tmpEnd = DateTime::createFromFormat(
            'Y-m-d',
            $to
        );

        // Jika parse berhasil untuk keduanya
        if ($tmpStart && $tmpEnd) {

            // Pakai tanggal custom
            $startDate = $tmpStart;
            $endDate = $tmpEnd;

        } else {

            // Kalau format salah → fallback ke 7 hari terakhir
            $startDate->modify('-6 day');           // Mundur 6 hari (total 7 hari)
            $range = '7d';                          // Update range info
        }
    } else {

        // from/to tidak dikirim → fallback 7 hari terakhir
        $startDate->modify('-6 day');             // Mundur 6 hari
        $range = '7d';                            // Set range menjadi 7d
    }
} else {

    // Selain itu (default) → 7 hari terakhir
    $startDate->modify('-6 day');               // Mundur 6 hari dari today
}

// Format waktu mulai: yyyy-mm-dd 00:00:00
$startStr = $startDate->format(
    'Y-m-d 00:00:00'
);

// Format waktu akhir: yyyy-mm-dd 23:59:59
$endStr = $endDate->format(
    'Y-m-d 23:59:59'
);

/* ============================================================
   BIKIN LABEL TANGGAL UNTUK CHART
   ============================================================ */

// Array label tanggal (string Y-m-d)
$labels = [];

// Map tanggal → nilai revenue (float)
$mapRevenue = [];

// Buat periode harian dari startDate s/d endDate (inklusif)
$period = new DatePeriod(
    $startDate,                                  // Tanggal mulai
    new DateInterval('P1D'),                     // Interval 1 hari
    (clone $endDate)->modify('+1 day')           // Sampai endDate + 1 hari (biar inklusif)
);

// Loop setiap hari dalam periode
foreach ($period as $d) {

    // Format key tanggal: 'Y-m-d'
    $key = $d->format('Y-m-d');

    // Tambah ke array label
    $labels[] = $key;

    // Inisialisasi revenue tanggal ini = 0.0
    $mapRevenue[$key] = 0.0;
}

/* ============================================================
   QUERY REVENUE DARI ORDERS
   hanya payment_status = 'paid'
   ============================================================ */

// Query untuk total revenue per hari
$sql = "
  SELECT
    DATE(created_at) AS d,          -- tanggal saja (tanpa jam)
    SUM(total)       AS s           -- jumlah total revenue per hari
  FROM
    orders
  WHERE
    created_at BETWEEN ? AND ?      -- filter range tanggal
    AND payment_status = 'paid'     -- hanya order yang sudah dibayar
  GROUP BY
    DATE(created_at)                -- grup per tanggal
  ORDER BY
    d ASC                           -- urut tanggal naik
";

// Siapkan prepared statement
$stmt = $conn->prepare($sql);

// Jika gagal prepare → error 500
if (! $stmt) {
    send_error(
        'failed to prepare revenue query: '.$conn->error,
        500
    );
}

// Bind parameter range tanggal (startStr dan endStr)
$stmt->bind_param(
    'ss',                             // Dua parameter string
    $startStr,                        // Tanggal mulai
    $endStr                           // Tanggal akhir
);

// Eksekusi statement
$stmt->execute();

// Ambil result set
$res = $stmt->get_result();

// Loop hasil query revenue
while ($row = $res->fetch_assoc()) {

    // Set revenue tanggal d di mapRevenue
    $mapRevenue[$row['d']] = (float) $row['s'];
}

// Tutup statement revenue
$stmt->close();

/* Susun ulang revenue sesuai urutan labels */
$revenue = [];                                 // Array revenue per hari (urut sesuai labels)

// Loop setiap label tanggal
foreach ($labels as $d) {

    // Ambil nilai revenue dari map (default 0.0 jika tidak ada)
    $revenue[] = $mapRevenue[$d] ?? 0.0;
}

// Hitung total revenue keseluruhan
$totalRevenue = array_sum($revenue);

/* ============================================================
   TOTAL ORDER PAID
   ============================================================ */

// Siapkan prepared statement untuk hitung jumlah order paid
$stmt2 = $conn->prepare("
  SELECT
    COUNT(*) AS c
  FROM
    orders
  WHERE
    created_at BETWEEN ? AND ?
    AND payment_status = 'paid'
");

// Bind parameter tanggal
$stmt2->bind_param(
    'ss',                               // Dua parameter string
    $startStr,                          // Tanggal mulai
    $endStr                             // Tanggal akhir
);

// Eksekusi statement 2
$stmt2->execute();

// Ambil result set 2
$res2 = $stmt2->get_result();

// Ambil 1 baris hasil (jumlah order)
$row2 = $res2->fetch_assoc();

// Konversi ke integer (default 0 jika null)
$ordersPaid = (int) ($row2['c'] ?? 0);

// Tutup statement 2
$stmt2->close();

// Hitung rata-rata nilai order (avg_order)
$avgOrder = $ordersPaid > 0           // Jika ada order paid
  ? ($totalRevenue / $ordersPaid)     // totalRevenue dibagi jumlah order
  : 0;                                // Jika tidak ada order → 0

/* ============================================================
   TOP MENU TERLARIS
   ============================================================ */

// Array untuk tampung top menu
$topMenus = [];

// Query untuk ambil top 10 menu terlaris
$sqlTop = "
  SELECT
    m.id,
    m.name,
    m.image,
    COALESCE(SUM(oi.qty), 0)      AS sold_qty,    -- total qty terjual
    COALESCE(SUM(oi.subtotal), 0) AS sold_amount  -- total nilai penjualan
  FROM
    order_items oi
  INNER JOIN
    orders o ON o.id = oi.order_id
  INNER JOIN
    menu   m ON m.id = oi.menu_id
  WHERE
    o.created_at BETWEEN ? AND ?                 -- filter range tanggal
    AND o.payment_status = 'paid'                -- hanya order yang dibayar
  GROUP BY
    m.id,
    m.name,
    m.image
  ORDER BY
    sold_qty    DESC,                            -- urut paling banyak kuantitas
    sold_amount DESC                             -- lalu urut paling besar nominal
  LIMIT 10                                       -- batasi 10 menu terlaris
";

// Siapkan prepared statement untuk top menu
$stmt3 = $conn->prepare($sqlTop);

// Bind parameter tanggal ke stmt3
$stmt3->bind_param(
    'ss',                                         // Dua parameter string
    $startStr,                                    // Tanggal mulai
    $endStr                                       // Tanggal akhir
);

// Eksekusi statement 3
$stmt3->execute();

// Ambil result set top menu
$resTop = $stmt3->get_result();

// Loop data top menu
while ($r = $resTop->fetch_assoc()) {

    // Ambil path gambar mentah dari DB
    $imgRaw = (string) ($r['image'] ?? '');

    // Susun URL gambar absolut untuk frontend
    $imgUrl = BASE_URL.'/'.ltrim($imgRaw, '/');

    // Tambahkan ke array topMenus dalam format rapi
    $topMenus[] = [
        'id' => (int) $r['id'],             // ID menu
        'name' => $r['name'],                // Nama menu
        'image' => $r['image'],               // Path gambar relatif
        'image_url' => $imgUrl,                   // URL gambar absolut
        'sold_qty' => (int) $r['sold_qty'],       // Total qty terjual
        'sold_amount' => (float) $r['sold_amount'],  // Total nominal penjualan
    ];
}

// Tutup statement 3
$stmt3->close();

/* ============================================================
   RESPON JSON KE CLIENT
   ============================================================ */

// Kirim respons JSON final
echo json_encode(
    [
        'ok' => true,                           // Flag sukses
        'range' => $range,                         // Range yang dipakai (today/7d/30d/custom)
        'period' => [                               // Periode tanggal yang dipakai
            'from' => $startDate->format('Y-m-d'),    // Tanggal mulai (Y-m-d)
            'to' => $endDate->format('Y-m-d'),      // Tanggal akhir (Y-m-d)
        ],
        'summary' => [                              // Ringkasan angka
            'total_revenue' => $totalRevenue,         // Total revenue
            'orders_paid' => $ordersPaid,           // Jumlah order paid
            'avg_order' => $avgOrder,             // Rata-rata nilai order
        ],
        'chart' => [                                // Data untuk chart
            'labels' => $labels,                     // Label tanggal
            'revenue' => $revenue,                    // Revenue per tanggal
        ],
        'top_menus' => $topMenus,                   // Daftar menu terlaris
    ],
    JSON_UNESCAPED_UNICODE                        // Jangan escape karakter Unicode
);
