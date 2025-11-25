<?php
// ---------------------------------------------
// File  : backend/api/menu.php
// Desc  : API untuk mengambil daftar menu
// ---------------------------------------------

declare(strict_types=1);                          // Aktifkan strict typing PHP

// ---------------------------------------------
// BATASAN METHOD (HANYA GET DIPERBOLEHKAN)
// ---------------------------------------------
if (
  ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET'    // Cek apakah method BUKAN GET
) {
  http_response_code(405);                       // Set HTTP status 405 (Method Not Allowed)

  header('Content-Type: application/json; charset=utf-8'); // Set header JSON
  echo json_encode([                             // Kirim response JSON error
    'ok'    => false,                            // Flag gagal
    'error' => 'Method not allowed'              // Pesan error
  ]);
  exit;                                          // Hentikan eksekusi script
}

// ---------------------------------------------
// HEADER JSON + NO-CACHE
// ---------------------------------------------
header('Content-Type: application/json; charset=utf-8');   // Response berupa JSON UTF-8
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0'); // Jangan cache di client
header('Pragma: no-cache');                               // Tambahan header no-cache (legacy)

// ---------------------------------------------
// LOAD KONFIGURASI (DB, BASE_URL, DLL)
// ---------------------------------------------
require_once __DIR__ . '/../config.php';        // Memuat konfigurasi aplikasi (termasuk $conn)

// ---------------------------------------------
// AMBIL PARAMETER FILTER DARI QUERY STRING
// ---------------------------------------------

$q = trim(                                      // Ambil parameter 'q' dari GET lalu trim
  (string)($_GET['q'] ?? '')                    // Jika tidak ada, default string kosong
);

$category = strtolower(                         // Ambil parameter 'category' lalu lowercase
  trim(                                         // Trim spasi kiri-kanan
    (string)($_GET['category'] ?? '')           // Jika tidak ada, default string kosong
  )
);                                              // Diharapkan: food | pastry | drink

$status = (                                     // Ambil parameter 'status'
  ($_GET['status'] ?? '') === 'Ready'           // Hanya jika nilainya tepat 'Ready'
)
  ? 'Ready'                                     // Jika sama → isi 'Ready'
  : '';                                         // Jika tidak → string kosong (abaikan)

// ---------------------------------------------
// PERSIAPAN WHERE & PARAMETER UNTUK QUERY
// ---------------------------------------------
$where  = [];                                   // Array untuk menampung klausa WHERE
$params = [];                                   // Array untuk menampung nilai bind_param
$types  = '';                                   // String tipe data bind_param (misal: 'ss')

// Filter berdasarkan status stok (Ready)
if ($status) {                                  // Jika $status tidak kosong
  $where[]  = 'stock_status = ?';               // Tambah kondisi WHERE menggunakan placeholder
  $params[] = $status;                          // Tambah nilai parameter 'Ready'
  $types   .= 's';                              // Tambah tipe 's' (string) untuk bind_param
}

// Filter berdasarkan keyword pencarian 'q'
if ($q !== '') {                                // Jika ada keyword pencarian
  $where[] = '(name LIKE ? OR category LIKE ?)';// Cari di kolom name atau category (LIKE)
  $like    = '%' . $q . '%';                    // Siapkan pola LIKE '%q%'
  $params[] = $like;                            // Param untuk name LIKE ?
  $params[] = $like;                            // Param untuk category LIKE ?
  $types   .= 'ss';                             // Tambah dua tipe 's' untuk bind_param
}

// Filter berdasarkan kategori (food, pastry, drink)
if (
  in_array(
    $category,                                  // Nilai kategori yang diinput
    ['food', 'pastry', 'drink'],                // Daftar kategori valid
    true                                        // Cek strict (tipe dan nilai)
  )
) {
  $where[]  = 'LOWER(category) = ?';            // Tambah kondisi WHERE category (lowercase)
  $params[] = $category;                        // Tambah nilai kategori ke parameter
  $types   .= 's';                              // Tambah tipe 's' (string)
}

// ---------------------------------------------
// SUSUN QUERY SELECT MENU
// ---------------------------------------------
$sql = 'SELECT '                                // Awal query SELECT
     . 'id, name, category, image, price, '     // Kolom-kolom yang diambil
     . 'stock_status, created_at '
     . 'FROM menu';                             // Dari tabel menu

if ($where) {                                   // Jika ada kondisi WHERE
  $sql .= ' WHERE '                             // Tambahkan keyword WHERE
       . implode(' AND ', $where);              // Gabung semua kondisi dengan AND
}

$sql .= ' ORDER BY created_at DESC';            // Urutkan menu dari yang terbaru

// ---------------------------------------------
// FUNGSI BANTU: MEMBENTUK URL GAMBAR MENU
// ---------------------------------------------
function build_image_url(                       // Fungsi untuk membentuk URL gambar
  ?string $img,                                 // Nama/path gambar (boleh null)
  string $baseUrl                               // BASE_URL aplikasi
): string {

  $img = trim((string)$img);                    // Pastikan $img string dan di-trim

  // Jika kosong → kembalikan placeholder default
  if ($img === '') {
    return rtrim($baseUrl, '/')                 // Hilangkan slash di akhir BASE_URL
         . '/public/assets/img/placeholder-1x1.png'; // Path gambar placeholder
  }

  // Jika sudah URL absolut (http/https) atau data URI → langsung kembalikan
  if (
    preg_match('~^https?://~i', $img)           // Cek mulai dengan http/https
    || str_starts_with($img, 'data:')           // Atau mulai dengan 'data:' (base64)
  ) {
    return $img;                                // Tidak perlu diubah, langsung return
  }

  // Jika relatif lokal, bersihkan leading slash
  $rel = ltrim($img, '/');                      // Hilangkan '/' di depan jika ada

  // Jika diawali 'public/' → buang prefix tersebut
  if (str_starts_with($rel, 'public/')) {
    $rel = substr($rel, 7);                     // Hapus 7 karakter 'public/'
  }

  // Pecah path relatif menjadi array folder/file
  $parts = $rel !== ''                          // Jika rel tidak kosong
    ? explode('/', $rel)                        // Pecah berdasarkan '/'
    : [];                                       // Jika kosong → array kosong

  $file = $parts                                // Jika ada bagian path
    ? array_pop($parts)                         // Ambil elemen terakhir sebagai nama file
    : '';                                       // Jika tidak ada → file kosong

  $dirRel = $parts                              // Sisa bagian path adalah folder relatif
    ? implode('/', $parts)                      // Gabung kembali jadi string 'dir1/dir2'
    : '';                                       // Jika tidak ada folder → string kosong

  // Tentukan path absolut folder 'public' secara filesystem
  $publicFs = realpath(                         // Coba dapatkan path absolut 'public'
    __DIR__ . '/../../public'
  ) ?: (
    __DIR__ . '/../../public'                   // Jika realpath gagal, pakai path relatif
  );

  // Susun path filesystem lengkap ke file gambar
  $fsPath  = rtrim($publicFs, DIRECTORY_SEPARATOR) // Hilangkan separator di akhir
           . DIRECTORY_SEPARATOR               // Tambah separator
           . ($dirRel ? $dirRel . DIRECTORY_SEPARATOR : '') // Tambah folder jika ada
           . $file;                            // Tambah nama file

  // Jika file tidak ditemukan, coba cari dengan perbedaan case huruf
  if (
    !is_file($fsPath)                          // Jika file belum ditemukan
    && $file !== ''                            // Dan nama file tidak kosong
  ) {
    $dirFs = rtrim($publicFs, DIRECTORY_SEPARATOR) // Path folder public
           . DIRECTORY_SEPARATOR                // Tambah separator
           . $dirRel;                           // Tambah folder relatif

    if (is_dir($dirFs)) {                       // Jika foldernya ada
      $lower = strtolower($file);               // Nama file lowercase
      foreach (scandir($dirFs) ?: [] as $entry) { // Loop semua file di folder
        if ($entry === '.' || $entry === '..') { // Lewati . dan ..
          continue;                              // Lanjut ke next file
        }
        if (strtolower($entry) === $lower) {     // Cocokkan nama file tanpa case-sensitive
          $file = $entry;                        // Pakai nama file asli di disk
          break;                                 // Berhenti cari
        }
      }
    }
  }

  // Susun path relatif yang aman (folder + rawurlencode nama file)
  $safeRel = ($dirRel ? $dirRel . '/' : '')     // Tambah folder jika ada
           . rawurlencode($file);               // Encode nama file agar aman di URL

  // Kembalikan URL absolut: BASE_URL + /public/ + path relatif
  return rtrim($baseUrl, '/')                   // Hilangkan trailing slash di BASE_URL
       . '/public/'                             // Tambah prefix /public/
       . ltrim($safeRel, '/');                  // Tambah path relatif file
}

// ---------------------------------------------
// EKSEKUSI QUERY & BANGUN HASIL JSON
// ---------------------------------------------
$items = [];                                     // Array untuk menampung hasil menu

$stmt = $conn->prepare($sql);                    // Siapkan prepared statement berdasarkan $sql

if ($stmt) {                                     // Jika prepare berhasil

  if ($params) {                                 // Jika ada parameter untuk di-bind
    $stmt->bind_param(                          // Bind parameter dinamis
      $types,                                   // String tipe parameter (misal 'sss')
      ...$params                                // Spread nilai param ke bind_param
    );
  }

  $stmt->execute();                             // Eksekusi statement

  $res = $stmt->get_result();                   // Ambil result set

  // Loop setiap baris hasil query
  while ($row = $res->fetch_assoc()) {

    // Tambahkan data menu ke array items dalam format rapi
    $items[] = [
      'id'         => (int)$row['id'],          // ID menu (integer)
      'name'       => (string)$row['name'],     // Nama menu
      'category'   => strtolower(               // Kategori menu (lowercase)
        (string)$row['category']
      ),
      'price'      => number_format(            // Harga dalam format Rupiah (string)
        (float)$row['price'],                   // Nilai harga asli
        0,                                      // Tanpa desimal
        ',',                                    // Separator desimal
        '.'                                     // Separator ribuan
      ),
      'price_int'  => (int)round(               // Harga dibulatkan ke integer
        (float)$row['price']                    // Nilai price sebagai float
      ),
      'stock'      => (string)$row['stock_status'], // Status stok (Ready / dll)
      'image_url'  => build_image_url(          // URL gambar menu lengkap
        $row['image'] ?? '',                    // Nama/path gambar dari DB (boleh null)
        BASE_URL                                // BASE_URL dari config
      ),
      'created_at' => (string)$row['created_at'] // Waktu menu dibuat
    ];
  }

  $stmt->close();                               // Tutup prepared statement
}

// ---------------------------------------------
// KIRIM RESPON JSON KE CLIENT
// ---------------------------------------------
echo json_encode(                               // Encode data ke JSON
  [
    'ok'    => true,                            // Flag sukses
    'items' => $items                           // Daftar menu
  ],
  JSON_UNESCAPED_SLASHES                        // Jangan escape karakter '/'
  | JSON_UNESCAPED_UNICODE                      // Jangan escape karakter Unicode
);
