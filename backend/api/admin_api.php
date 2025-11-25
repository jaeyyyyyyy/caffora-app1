<?php
// ---------------------------------------------
// File  : backend/menu_catalog_api.php (misal)
// Desc  : API katalog menu (list/add/update) untuk admin/karyawan
// ---------------------------------------------

declare(strict_types=1);                      // Aktifkan strict typing PHP

session_start();                              // Mulai session untuk ambil role user

require_once __DIR__ . '/../config.php';      // Load konfigurasi (DB, BASE_URL, dll)

// Set header respons JSON dengan charset UTF-8
header('Content-Type: application/json; charset=utf-8');

/* --------------------------------------------
   Guard: hanya admin / karyawan yang boleh akses
-------------------------------------------- */

// Ambil role dari session (default string kosong)
$role = $_SESSION['user_role'] ?? '';

// Jika role bukan admin atau karyawan → unauthorized
if (
  !in_array(
    $role,                                    // Nilai role saat ini
    ['admin', 'karyawan'],                    // Role yang diizinkan
    true                                      // Cek strict
  )
) {
  http_response_code(401);                    // Set HTTP status 401 (Unauthorized)

  echo json_encode([                          // Kirim JSON error singkat
    'error' => 'Unauthorized'
  ]);

  exit;                                       // Hentikan script
}

/* --------------------------------------------
   Helpers
-------------------------------------------- */

/**
 * jfail()
 * Kirim respon error JSON dan langsung exit.
 */
function jfail(
  string $m,                                  // Pesan error
  int $c = 400                                // HTTP status code (default 400)
): void {
  http_response_code($c);                     // Set HTTP status code

  echo json_encode([                          // Kirim JSON error
    'error' => $m
  ]);

  exit;                                       // Hentikan eksekusi script
}

/**
 * ok()
 * Kirim respon sukses JSON (data optional).
 */
function ok($d = null): void {
  echo json_encode(                           // Encode data ke JSON
    $d ?? ['ok' => true]                      // Kalau null → kirim { ok: true }
  );
  exit;                                       // Hentikan script
}

/**
 * clean_str()
 * Trim string, rapikan spasi berlebih, dan batasi panjang.
 */
function clean_str(
  ?string $s,                                 // Input string (boleh null)
  int $max = 255                              // Panjang maksimal
): string {
  $s = trim((string)$s);                      // Ubah ke string & trim spasi ujung
  $s = preg_replace(                          // Ganti spasi berlebih dengan 1 spasi
    '/\s+/',                                  // Pattern: 1+ whitespace
    ' ',                                      // Ganti dengan 1 spasi
    $s
  );
  return mb_substr(                           // Potong string maksimal $max karakter
    $s,
    0,
    $max,
    'UTF-8'
  );
}

/**
 * num()
 * Konversi nilai ke float dengan 2 desimal (pembulatan aman).
 */
function num($v): float {
  return (float)number_format(                // Format angka menjadi 2 desimal
    (float)$v,                                // Cast ke float dulu
    2,                                        // 2 angka di belakang koma
    '.',                                      // Separator desimal
    ''                                        // Tanpa pemisah ribuan
  );
}

/* --------------------------------------------
   Upload paths
-------------------------------------------- */

// Path folder upload di filesystem (server)
$UPLOAD_DIR = __DIR__ . '/../uploads/menu';

// Prefix URL publik untuk akses gambar dari browser
$PUBLIC_PREFIX = BASE_URL . '/backend/uploads/menu';

// Jika folder upload belum ada, coba buat dengan permission 0755
if (!is_dir($UPLOAD_DIR)) {
  @mkdir(
    $UPLOAD_DIR,
    0755,
    true
  );
}

/* --------------------------------------------
   Ambil parameter action
-------------------------------------------- */

// Ambil 'action' dari GET, kalau tidak ada cek POST, kalau tidak ada → string kosong
$act = $_GET['action']
    ?? $_POST['action']
    ?? '';

/* --------------------------------------------
   Action: LIST CATALOG
-------------------------------------------- */
if ($act === 'list_catalog') {

  // Siapkan array penampung baris menu
  $rows = [];

  // Query untuk ambil data menu
  $sql = "
    SELECT
      id,
      name,
      category,
      image,
      price,
      stock_status
    FROM
      menu
    ORDER BY
      id ASC
  ";

  // Eksekusi query menggunakan koneksi $conn
  if ($q = $conn->query($sql)) {

    // Loop hasil query baris per baris
    while ($r = $q->fetch_assoc()) {

      // Tambahkan data ke array $rows dengan format rapi
      $rows[] = [
        'id'          => (int)$r['id'],        // ID menu
        'name'        => (string)$r['name'],   // Nama menu
        'category'    => (string)$r['category'], // Kategori
        'image'       => (string)($r['image'] ?? ''), // Nama file gambar (kalau ada)
        'image_path'  => $r['image']          // URL gambar publik (kalau ada)
          ? $PUBLIC_PREFIX . '/' . rawurlencode($r['image'])
          : null,
        'price'       => (float)$r['price'],   // Harga sebagai float
        'stock_status'=> (string)$r['stock_status'], // Status stok
      ];
    }

    // Tutup result set query
    $q->close();
  }

  // Kirim respon sukses dengan data rows
  ok($rows);
}

/* --------------------------------------------
   Action: ADD CATALOG (tambah menu baru)
-------------------------------------------- */
if ($act === 'add_catalog') {

  // Bersihkan nama menu (max 150 karakter)
  $name = clean_str(
    $_POST['name'] ?? '',
    150
  );

  // Bersihkan kategori (max 100 karakter)
  $category = clean_str(
    $_POST['category'] ?? '',
    100
  );

  // Konversi harga ke float 2 desimal
  $price = num(
    $_POST['price'] ?? 0
  );

  // Ambil status stok (default 'Ready')
  $stock = $_POST['stock_status'] ?? 'Ready';

  // Validasi field dasar
  if (
    $name === ''                               // Nama wajib diisi
    || $category === ''                        // Kategori wajib diisi
    || $price < 0                              // Harga tidak boleh negatif
  ) {
    jfail('Invalid fields');                   // Kirim error "Invalid fields"
  }

  // Validasi nilai stock_status
  if (
    !in_array(
      $stock,
      ['Ready', 'Sold Out'],
      true
    )
  ) {
    $stock = 'Ready';                          // Jika bukan nilai valid → set ke Ready
  }

  // Inisialisasi nama file gambar null (tidak ada upload)
  $img = null;

  // Jika input file 'image' ada dan beneran file yang diupload
  if (
    !empty($_FILES['image'])
    && is_uploaded_file($_FILES['image']['tmp_name'])
  ) {
    $f = $_FILES['image'];                     // Ambil info file upload

    // Jika ada error upload, kirim pesan error
    if ($f['error'] !== UPLOAD_ERR_OK) {
      jfail('Upload error ' . $f['error']);
    }

    // Deteksi MIME type file
    $mime = mime_content_type(
      $f['tmp_name']
    );

    // Hanya izinkan PNG, JPEG, WEBP
    if (
      !in_array(
        $mime,
        ['image/png', 'image/jpeg', 'image/webp'],
        true
      )
    ) {
      jfail('Invalid image type');             // MIME tidak sesuai → error
    }

    // Batasi ukuran file maksimal 1.5MB
    if ($f['size'] > 1500000) {
      jfail('Image > 1.5MB');                 // File terlalu besar
    }

    // Ambil ekstensi file asli (lowercase, default jpg)
    $ext = strtolower(
      pathinfo(
        $f['name'],
        PATHINFO_EXTENSION
      ) ?: 'jpg'
    );

    // Buat nama file unik: m_YYYYmmdd_HHMMSS_random.ext
    $img = 'm_'
         . date('Ymd_His')
         . '_'
         . bin2hex(
             random_bytes(4)
           )
         . '.'
         . $ext;

    // Pindahkan file upload ke folder tujuan
    if (
      !move_uploaded_file(
        $f['tmp_name'],
        $UPLOAD_DIR . '/' . $img
      )
    ) {
      jfail('Save image failed', 500);         // Gagal simpan file → error 500
    }
  }

  // Siapkan statement insert menu baru
  $st = $conn->prepare("
    INSERT INTO menu
      (name, category, image, price, stock_status)
    VALUES
      (?,?,?,?,?)
  ");

  // Bind parameter ke statement
  $st->bind_param(
    'sssds',
    $name,                                      // name
    $category,                                  // category
    $img,                                       // image (boleh null)
    $price,                                     // price
    $stock                                      // stock_status
  );

  // Eksekusi insert dan cek hasil
  if (!$st->execute()) {
    jfail('DB insert failed', 500);            // Jika gagal → error DB
  }

  // Ambil ID menu yang baru diinsert
  $id = $st->insert_id;

  // Tutup statement insert
  $st->close();

  // Kirim respon sukses dengan ID menu baru
  ok([
    'id' => $id
  ]);
}

/* --------------------------------------------
   Action: UPDATE CATALOG (update menu)
-------------------------------------------- */
if ($act === 'update_catalog') {

  // Ambil ID menu yang mau diupdate
  $id = (int)(
    $_POST['id'] ?? 0
  );

  // Bersihkan nama menu (max 150 karakter)
  $name = clean_str(
    $_POST['name'] ?? '',
    150
  );

  // Bersihkan kategori (max 100 karakter)
  $category = clean_str(
    $_POST['category'] ?? '',
    100
  );

  // Konversi harga ke float
  $price = num(
    $_POST['price'] ?? 0
  );

  // Ambil status stok (default Ready)
  $stock = $_POST['stock_status'] ?? 'Ready';

  // Validasi input dasar
  if (
    $id <= 0                                 // ID harus valid
    || $name === ''                          // Nama wajib
    || $category === ''                      // Kategori wajib
    || $price < 0                            // Harga tidak boleh negatif
  ) {
    jfail('Invalid fields');                 // Kirim error jika tidak valid
  }

  // Validasi nilai stok hanya antara Ready / Sold Out
  if (
    !in_array(
      $stock,
      ['Ready', 'Sold Out'],
      true
    )
  ) {
    $stock = 'Ready';                        // Jika bukan nilai valid → pakai Ready
  }

  // -----------------------------------------
  // Ambil gambar lama (jika ada) dari DB
  // -----------------------------------------

  // Inisialisasi nama file lama null
  $old = null;

  // Siapkan statement ambil image lama
  $q = $conn->prepare("
    SELECT
      image
    FROM
      menu
    WHERE
      id = ?
  ");

  // Bind parameter id ke statement
  $q->bind_param(
    'i',
    $id
  );

  // Eksekusi query
  $q->execute();

  // Ambil hasil image lama
  $old = $q
    ->get_result()
    ->fetch_assoc()['image'] ?? null;

  // Tutup statement select image lama
  $q->close();

  // -----------------------------------------
  // Proses upload image baru (jika ada)
  // -----------------------------------------

  // Default: pakai image lama
  $img = $old;

  // Jika ada upload file image baru
  if (
    !empty($_FILES['image'])
    && is_uploaded_file($_FILES['image']['tmp_name'])
  ) {
    $f = $_FILES['image'];                   // Ambil info file

    // Kalau ada error upload → kirim error
    if ($f['error'] !== UPLOAD_ERR_OK) {
      jfail('Upload error ' . $f['error']);
    }

    // Cek MIME type file
    $mime = mime_content_type(
      $f['tmp_name']
    );

    // Hanya izinkan gambar PNG, JPEG, WEBP
    if (
      !in_array(
        $mime,
        ['image/png', 'image/jpeg', 'image/webp'],
        true
      )
    ) {
      jfail('Invalid image type');
    }

    // Batasi ukuran sampai 1.5MB
    if ($f['size'] > 1500000) {
      jfail('Image > 1.5MB');
    }

    // Ambil ekstensi file (default jpg kalau kosong)
    $ext = strtolower(
      pathinfo(
        $f['name'],
        PATHINFO_EXTENSION
      ) ?: 'jpg'
    );

    // Buat nama file baru unik
    $img = 'm_'
         . date('Ymd_His')
         . '_'
         . bin2hex(
             random_bytes(4)
           )
         . '.'
         . $ext;

    // Pindahkan file upload ke folder tujuan
    if (
      !move_uploaded_file(
        $f['tmp_name'],
        $UPLOAD_DIR . '/' . $img
      )
    ) {
      jfail('Save image failed', 500);
    }

    // Hapus file lama jika ada dan masih tersimpan di folder upload
    if (
      $old
      && is_file($UPLOAD_DIR . '/' . $old)
    ) {
      @unlink(
        $UPLOAD_DIR . '/' . $old
      );
    }
  }

  // -----------------------------------------
  // Update record di tabel menu
  // -----------------------------------------

  // Siapkan statement update menu
  $st = $conn->prepare("
    UPDATE
      menu
    SET
      name = ?,
      category = ?,
      image = ?,
      price = ?,
      stock_status = ?
    WHERE
      id = ?
  ");

  // PERHATIAN:
  // Di bawah ini string tipe 'sss dsi' memang mengandung spasi
  // (sesuai kode asli) dan dua kali pemanggilan bind_param.
  // // <- TIDAK BOLEH ADA SPASI!
  $st->bind_param(
    'sss dsi',                               // String tipe dengan spasi (kode asli)
    $name,                                   // name
    $category,                               // category
    $img,                                    // image
    $price,                                  // price
    $stock,                                  // stock_status
    $id                                      // id
  );

  // gunakan string tipe yang benar:
  $st->bind_param(
    'sss dsi',                               // Baris kedua bind_param (kode asli)
    $name,
    $category,
    $img,
    $price,
    $stock,
    $id
  );

  // Catatan: di kode asli TIDAK ada execute() dan respon
  // di dalam blok update_catalog ini.
}
?>
