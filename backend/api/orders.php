<?php

// Pembuka file PHP

// backend/api/orders.php
// Endpoint API untuk operasi terkait pesanan (orders)

declare(strict_types=1);
// Aktifkan strict typing

header('Content-Type: application/json; charset=utf-8');
// Set response header ke JSON UTF-8

session_start();
// Mulai atau lanjutkan session

require_once __DIR__.'/../config.php';
// Load konfigurasi utama (BASE_URL, DB konstanta, dll.)

$BASE = rtrim(BASE_URL, '/');
// Simpan BASE_URL tanpa slash di belakang untuk dipakai di link

/* ==== AUDIT HELPER (robust loader) ==== */
$__candidates = [
    __DIR__.'/../lib/audit_helper.php',  // lokasi normal helper audit
    __DIR__.'/lib/audit_helper.php',     // fallback jika helper ada di /api/lib
];
// Daftar kemungkinan path file audit_helper.php

foreach ($__candidates as $__p) {
    // Loop semua kandidat lokasi
    if (is_file($__p)) {
        // Jika file ada di path ini
        require_once $__p;
        // Load file helper
        break;
        // Stop setelah ketemu pertama
    }
}

if (! function_exists('audit_log')) {
    // Jika fungsi audit_log tidak ditemukan setelah require

    // fallback aman bila helper belum ada agar API tidak fatal
    function audit_log(
        mysqli $db,
        int $actorId,
        string $entity,
        int $entityId,
        string $action,
        string $fromVal = '',
        string $toVal = '',
        string $remark = ''
    ): bool {
        // Fungsi fallback hanya log ke error_log

        error_log("[AUDIT_FALLBACK] $actorId $entity#$entityId $action ($remark)");
        // Tulis pesan audit ke error_log

        return true;
        // Tetap kembalikan true agar flow tidak rusak
    }
}

$mysqli = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
// Buat koneksi MySQLi baru dengan konstanta DB_*

if ($mysqli->connect_errno) {
    // Jika terjadi error koneksi database

    echo json_encode(
        [
            'ok' => false,
            'error' => 'DB connect failed: '.$mysqli->connect_error,
        ],
        JSON_UNESCAPED_SLASHES
    );
    // Kirim JSON error dengan pesan koneksi gagal

    exit;
    // Hentikan eksekusi script
}

$mysqli->set_charset('utf8mb4');
// Set charset koneksi ke utf8mb4

/* -------------------- helpers -------------------- */
function out(array $arr): void
{
    // Helper untuk mengirim respons JSON sukses/generic

    echo json_encode($arr, JSON_UNESCAPED_SLASHES);
    // Encode array ke JSON tanpa escape slash

    exit;
    // Hentikan script setelah kirim output
}

function bad(string $msg, int $code = 400): void
{
    // Helper untuk mengirim error JSON dengan HTTP status

    http_response_code($code);
    // Set HTTP response code

    out(
        [
            'ok' => false,
            'error' => $msg,
        ]
    );
    // Panggil out() dengan struktur error
}

/** Normalisasi role ke 3 varian resmi */
function norm_role(?string $r): ?string
{
    // Fungsi untuk menormalkan role ke admin/karyawan/customer

    if ($r === null) {
        // Jika input null
        return null;
        // Kembalikan null
    }

    $r = strtolower(trim($r));
    // Ubah role ke huruf kecil dan trim spasi

    if ($r === 'admin') {
        // Jika persis "admin"
        return 'admin';
        // Kembalikan admin
    }

    if (
        in_array(
            $r,
            ['karyawan', 'pegawai', 'employee', 'staff', 'barista'],
            true
        )
    ) {
        // Jika termasuk varian nama karyawan/staff
        return 'karyawan';
        // Kembalikan role karyawan
    }

    if (
        in_array(
            $r,
            ['customer', 'pelanggan'],
            true
        )
    ) {
        // Jika termasuk varian nama customer
        return 'customer';
        // Kembalikan role customer
    }

    return 'customer';
    // Fallback aman: anggap customer
}

/** Simpan notifikasi (tidak dicatat ke audit) */
function create_notification(
    mysqli $db,
    ?int $userId,
    ?string $role,
    string $message,
    ?string $link = null
): void {
    // Fungsi untuk menyimpan notifikasi ke tabel notifications

    $role = norm_role($role);
    // Normalkan role

    $roleParam = $role ?? '';
    // Jika null, gunakan string kosong

    $uid = $userId ?? 0;
    // Jika userId null, gunakan 0

    $sql =
      "INSERT INTO notifications (user_id, role, message, status, created_at, link)
     VALUES (NULLIF(?,0), NULLIF(?,''), ?, 'unread', NOW(), ?)";
    // Query insert notifikasi, NULLIF untuk user_id/role kosong

    if (! $stmt = $db->prepare($sql)) {
        // Jika prepare gagal
        return;
        // Keluar tanpa error fatal
    }

    $stmt->bind_param('isss', $uid, $roleParam, $message, $link);
    // Bind user_id, role, message, link ke statement

    $stmt->execute();
    // Eksekusi insert

    $stmt->close();
    // Tutup statement
}

/** CFR + tanggal + bulan (3 huruf) + kode jam + random */
function generateInvoiceNo(): string
{
    // Fungsi untuk membuat nomor invoice unik

    $prefix = 'CFR';
    // Prefix invoice (Caffora)

    $day = strtoupper(date('d'));
    // Tanggal (2 digit) dalam huruf besar

    $mon = strtoupper(date('M'));
    // Bulan 3 huruf (JAN, FEB, dst) huruf besar

    $hourCode = chr(65 + (int) date('G'));
    // Kode jam: 0 -> A, 1 -> B, dst

    $rand = strtoupper(
        substr(
            bin2hex(random_bytes(4)),
            0,
            6
        )
    );
    // Random 6 karakter hex (upper)

    return $prefix.$day.$mon.$hourCode.$rand;
    // Gabungkan semua bagian jadi nomor invoice
}

/* -------------------- const & enums -------------------- */
$ALLOWED_ORDER_STATUS = ['new', 'processing', 'ready', 'completed', 'cancelled'];
// Daftar status order yang valid

$ALLOWED_PAYMENT_STATUS = ['pending', 'paid', 'failed', 'refunded', 'overdue'];
// Daftar status pembayaran yang valid

$ALLOWED_METHOD = ['cash', 'bank_transfer', 'qris', 'ewallet'];
// Daftar metode pembayaran yang diperbolehkan

$ALLOWED_SERVICE = ['dine_in', 'take_away'];
// Jenis layanan (makan di tempat / dibawa pulang)

const TAX_RATE = 0.11;
// Persentase pajak (11%)

$action = $_GET['action'] ?? 'list';
// Ambil parameter action, default 'list'

$actorId = (int) ($_SESSION['user_id'] ?? 0);
// ID user yang melakukan aksi (untuk audit)

/* ======================================================
   LIST
====================================================== */
if ($action === 'list') {
    // Jika action=list → ambil daftar orders

    $q = trim((string) ($_GET['q'] ?? ''));
    // Pencarian teks (invoice_no atau customer_name)

    $order_status = trim((string) ($_GET['order_status'] ?? ''));
    // Filter status pesanan

    $payment_status = trim((string) ($_GET['payment_status'] ?? ''));
    // Filter status pembayaran

    $where = [];
    // Array kondisi WHERE

    $types = '';
    // String tipe parameter untuk bind_param

    $params = [];
    // Array nilai parameter bind_param

    if ($q !== '') {
        // Jika ada query pencarian

        $where[] = '(invoice_no LIKE ? OR customer_name LIKE ?)';
        // Tambahkan kondisi LIKE pada invoice_no atau customer_name

        $like = '%'.$q.'%';
        // Pola LIKE dengan wildcard

        $params[] = $like;
        // Parameter untuk invoice_no LIKE

        $params[] = $like;
        // Parameter untuk customer_name LIKE

        $types .= 'ss';
        // Tipe parameter: dua string
    }

    if ($order_status !== '') {
        // Jika filter order_status diisi

        if (! in_array($order_status, $ALLOWED_ORDER_STATUS, true)) {
            // Jika bukan status yang diizinkan
            bad('Invalid order_status');
            // Kirim error invalid order_status
        }

        $where[] = 'order_status=?';
        // Tambahkan kondisi filter status pesanan

        $params[] = $order_status;
        // Tambah nilai parameter order_status

        $types .= 's';
        // Tambah tipe parameter string
    }

    if ($payment_status !== '') {
        // Jika filter payment_status diisi

        if (! in_array($payment_status, $ALLOWED_PAYMENT_STATUS, true)) {
            // Jika status tidak valid
            bad('Invalid payment_status');
            // Kirim error invalid payment_status
        }

        $where[] = 'payment_status=?';
        // Tambahkan kondisi filter status pembayaran

        $params[] = $payment_status;
        // Tambah nilai parameter

        $types .= 's';
        // Tambah tipe parameter string
    }

    $sql =
      'SELECT id,user_id,invoice_no,customer_name,service_type,table_no,
            total,subtotal,tax_amount,grand_total,
            order_status,payment_status,payment_method,
            created_at,updated_at
     FROM orders';
    // Query dasar ambil data orders

    if ($where) {
        // Jika ada kondisi WHERE

        $sql .= ' WHERE '.implode(' AND ', $where);
        // Gabungkan semua kondisi dengan AND
    }

    $sql .= ' ORDER BY created_at DESC, id DESC';
    // Urutkan dari yang terbaru (created_at lalu id)

    if ($params) {
        // Jika ada parameter (butuh prepared statement)

        $stmt = $mysqli->prepare($sql);
        // Siapkan prepared statement

        if (! $stmt) {
            // Jika prepare gagal
            bad('Prepare failed: '.$mysqli->error, 500);
            // Kirim error server
        }

        $stmt->bind_param($types, ...$params);
        // Bind tipe & parameter ke prepared statement

        $stmt->execute();
        // Eksekusi statement

        $res = $stmt->get_result();
        // Ambil hasil query

    } else {
        // Jika tidak ada parameter (boleh query langsung)

        $res = $mysqli->query($sql);
        // Eksekusi query biasa

        if (! $res) {
            // Jika query gagal
            bad('Query failed: '.$mysqli->error, 500);
            // Kirim error server
        }
    }

    $items = [];
    // Array penampung daftar order

    while ($row = $res->fetch_assoc()) {
        // Loop setiap baris hasil query

        $row['id'] = (int) $row['id'];
        // Pastikan id dalam bentuk integer

        $row['user_id'] = $row['user_id'] !== null
          ? (int) $row['user_id']
          : null;
        // Jika user_id ada → cast ke int, jika tidak → null

        $row['total'] = (float) $row['total'];
        // Cast total ke float

        $row['subtotal'] = (int) $row['subtotal'];
        // Cast subtotal ke integer

        $row['tax_amount'] = (int) $row['tax_amount'];
        // Cast tax_amount ke integer

        $row['grand_total'] = (int) $row['grand_total'];
        // Cast grand_total ke integer

        $items[] = $row;
        // Tambahkan baris ini ke array items
    }

    out(
        [
            'ok' => true,
            'items' => $items,
        ]
    );
    // Kirim respon JSON sukses dengan daftar items
}

/* ======================================================
   CREATE (checkout)
====================================================== */
if ($action === 'create') {
    // Jika action=create → buat pesanan baru (checkout)

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        // Wajib method POST
        bad('Use POST', 405);
        // Kirim error method not allowed
    }

    $rawBody = file_get_contents('php://input');
    // Baca body mentah dari request

    $data = json_decode($rawBody, true);
    // Decode JSON body menjadi array asosiatif

    if (! is_array($data)) {
        // Jika body bukan JSON valid
        bad('Invalid JSON body');
        // Kirim error invalid JSON
    }

    $customer_name = trim((string) ($data['customer_name'] ?? ''));
    // Nama customer dari body

    $service_type = (string) ($data['service_type'] ?? '');
    // Jenis layanan (dine_in/take_away)

    $table_no = trim((string) ($data['table_no'] ?? ''));
    // Nomor meja (untuk dine_in)

    $pay_method = (string) ($data['payment_method'] ?? '');
    // Metode pembayaran

    $pay_status = (string) ($data['payment_status'] ?? 'pending');
    // Status pembayaran di awal

    $items = $data['items'] ?? [];
    // Daftar item pesanan (array)

    if ($customer_name === '') {
        // Nama customer wajib diisi
        bad('Nama customer wajib');
    }

    if (! in_array($service_type, $ALLOWED_SERVICE, true)) {
        // service_type harus salah satu dari dine_in / take_away
        bad('Invalid service_type');
    }

    if ($pay_method === '') {
        // Jika user tidak mengirim metode, default cash
        $pay_method = 'cash';
    }

    if (! in_array($pay_method, $ALLOWED_METHOD, true)) {
        // Metode pembayaran harus valid
        bad('Invalid payment_method');
    }

    if (! in_array($pay_status, $ALLOWED_PAYMENT_STATUS, true)) {
        // Status pembayaran harus valid
        bad('Invalid payment_status');
    }

    if (! is_array($items) || ! count($items)) {
        // Items harus array dan tidak boleh kosong
        bad('Items kosong');
    }

    // hitung harga
    $subtotal = 0;
    // Inisialisasi subtotal 0

    foreach ($items as $it) {
        // Loop semua item pesanan

        $qty = (int) ($it['qty'] ?? 0);
        // Ambil qty, default 0

        $price = (float) ($it['price'] ?? 0);
        // Ambil price, default 0

        if ($qty <= 0 || $price < 0) {
            // Jika qty tidak valid atau price negatif, skip
            continue;
        }

        $subtotal += $qty * $price;
        // Tambah ke subtotal
    }

    if ($subtotal <= 0) {
        // Jika subtotal tidak masuk akal
        bad('Total invalid');
    }

    $tax_amount = (int) round($subtotal * TAX_RATE);
    // Hitung pajak dari subtotal, dibulatkan dan cast ke int

    $grand_total = $subtotal + $tax_amount;
    // Total akhir = subtotal + pajak

    $total_legacy = $grand_total;
    // Simpan total dalam variabel legacy (kompat lama)

    // untuk payments
    $amount_gross = $grand_total;
    // Jumlah kotor pembayaran sama dengan grand_total

    $amount_net = $grand_total;
    // Jumlah bersih awal sama (belum ada diskon/ongkir)

    $paid_at = ($pay_status === 'paid')
      ? date('Y-m-d H:i:s')
      : null;
    // Jika status awal "paid", set paid_at sekarang, jika tidak null

    $invoice_no = generateInvoiceNo();
    // Buat nomor invoice unik

    $user_id = $_SESSION['user_id'] ?? null;
    // Ambil user_id dari session jika ada

    if ($service_type === 'take_away') {
        // Untuk take_away, kosongkan nomor meja
        $table_no = '';
    }

    $mysqli->begin_transaction();
    // Mulai transaksi database

    try {
        // 1) orders
        $stmt = $mysqli->prepare("
      INSERT INTO orders
        (user_id, invoice_no, customer_name, service_type, table_no,
         total, order_status, payment_status, payment_method,
         created_at, updated_at, subtotal, tax_amount, grand_total)
      VALUES
        (?, ?, ?, ?, ?, ?, 'new', ?, ?, NOW(), NOW(), ?, ?, ?)
    ");
        // Prepared statement untuk insert ke tabel orders

        if (! $stmt) {
            // Jika prepare gagal
            throw new Exception('Prepare(order) failed: '.$mysqli->error);
            // Lempar exception dengan pesan error
        }

        $stmt->bind_param(
            'issssdssiii',
            $user_id,
            $invoice_no,
            $customer_name,
            $service_type,
            $table_no,
            $total_legacy,
            $pay_status,
            $pay_method,
            $subtotal,
            $tax_amount,
            $grand_total
        );
        // Bind parameter ke INSERT orders

        if (! $stmt->execute()) {
            // Jika eksekusi gagal
            throw new Exception('Insert order failed: '.$stmt->error);
            // Lempar exception
        }

        $order_id = (int) $stmt->insert_id;
        // Ambil id order yang baru dibuat

        $stmt->close();
        // Tutup statement orders

        // 2) order_items
        $stmtItem = $mysqli->prepare('
      INSERT INTO order_items (order_id, menu_id, qty, price, discount, cogs_unit)
      VALUES (?, ?, ?, ?, 0.00, NULL)
    ');
        // Prepared statement untuk insert item orders

        if (! $stmtItem) {
            // Jika prepare gagal
            throw new Exception('Prepare(item) failed: '.$mysqli->error);
            // Lempar exception
        }

        foreach ($items as $it) {
            // Loop semua item dari body

            $menu_id = (int) ($it['menu_id'] ?? $it['id'] ?? 0);
            // Ambil menu_id atau id fallback

            $qty = (int) ($it['qty'] ?? 0);
            // Ambil quantity

            $price = (float) ($it['price'] ?? 0);
            // Ambil harga

            if ($menu_id <= 0 || $qty <= 0) {
                // Jika data item tidak valid, skip
                continue;
            }

            $stmtItem->bind_param(
                'iiid',
                $order_id,
                $menu_id,
                $qty,
                $price
            );
            // Bind order_id, menu_id, qty, price ke item

            if (! $stmtItem->execute()) {
                // Jika insert item gagal
                throw new Exception('Insert item failed: '.$stmtItem->error);
                // Lempar exception
            }
        }

        $stmtItem->close();
        // Tutup statement item

        // 3) invoices (opsional)
        $stmtInv = $mysqli->prepare(
            'INSERT INTO invoices (order_id, amount) VALUES (?, ?)'
        );
        // Siapkan insert ke tabel invoices

        if (! $stmtInv) {
            // Jika prepare gagal
            throw new Exception('Prepare(invoice) failed: '.$mysqli->error);
            // Lempar exception
        }

        $stmtInv->bind_param('id', $order_id, $grand_total);
        // Bind order_id dan amount (grand_total)

        if (! $stmtInv->execute()) {
            // Jika eksekusi gagal
            throw new Exception('Insert invoice failed: '.$stmtInv->error);
            // Lempar exception
        }

        $stmtInv->close();
        // Tutup statement invoices

        // 4) payments (AUTO)
        $stmtPay = $mysqli->prepare('
      INSERT INTO payments
        (order_id, method, status, amount_gross, discount, tax, shipping, amount_net, paid_at, note)
      VALUES
        (?, ?, ?, ?, 0, ?, 0, ?, ?, ?)
      ON DUPLICATE KEY UPDATE
        method       = VALUES(method),
        status       = VALUES(status),
        amount_gross = VALUES(amount_gross),
        tax          = VALUES(tax),
        amount_net   = VALUES(amount_net),
        paid_at      = VALUES(paid_at),
        note         = VALUES(note)
    ');
        // Statement untuk insert/update data payments

        if (! $stmtPay) {
            // Jika prepare gagal
            throw new Exception('Prepare(payment) failed: '.$mysqli->error);
            // Lempar exception
        }

        $note = 'auto import from orders #'.$invoice_no;
        // Catatan default untuk payments

        $stmtPay->bind_param(
            'issdddss',
            $order_id,
            $pay_method,
            $pay_status,
            $amount_gross,
            $tax_amount,
            $amount_net,
            $paid_at,
            $note
        );
        // Bind parameter payments

        if (! $stmtPay->execute()) {
            // Jika eksekusi gagal
            throw new Exception('Insert payment failed: '.$stmtPay->error);
            // Lempar exception
        }

        $stmtPay->close();
        // Tutup statement payment

        /* ===== AUDIT (FOKUS: order & uang) ===== */
        $toOrder = json_encode(
            [
                'invoice' => $invoice_no,
                'customer' => $customer_name,
                'service_type' => $service_type,
                'table_no' => $table_no,
                'payment_method' => $pay_method,
                'subtotal' => $subtotal,
                'tax' => $tax_amount,
                'grand_total' => $grand_total,
                'items_count' => count($items),
            ],
            JSON_UNESCAPED_UNICODE
        );
        // Data ringkas pesanan untuk dicatat di audit_log

        audit_log(
            $mysqli,
            $actorId,
            'order',
            $order_id,
            'create',
            '',
            $toOrder,
            'create via API'
        );
        // Catat pencatatan pembuatan order baru di audit

        // catat uang hanya saat langsung LUNAS
        if ($pay_status === 'paid') {
            // Jika pembayaran langsung berstatus paid

            audit_log(
                $mysqli,
                $actorId,
                'payment',
                $order_id,
                'update_status',
                'pending',
                'paid',
                'paid at create'
            );
            // Catat perubahan status pembayaran ke paid
        }

        // 5) NOTIFIKASI (tidak dicatat ke audit)
        if ($user_id) {
            // Jika order terkait dengan user login

            $msg =
              'Pesanan kamu sudah diterima. Invoice: '.
              $invoice_no;
            // Pesan notifikasi untuk customer

            $link =
              $BASE.
              '/public/customer/history.php?invoice='.
              $invoice_no;
            // Link ke riwayat pesanan customer

            create_notification(
                $mysqli,
                (int) $user_id,
                'customer',
                $msg,
                $link
            );
            // Buat notifikasi untuk customer
        }

        $msgStaff =
          'Pesanan baru dari '.
          $customer_name.
          ' total Rp '.
          number_format($grand_total, 0, ',', '.').
          ' ('.
          $invoice_no.
          ')';
        // Pesan notifikasi untuk karyawan

        create_notification(
            $mysqli,
            null,
            'karyawan',
            $msgStaff,
            $BASE.'/public/karyawan/orders.php'
        );
        // Notifikasi ke semua karyawan

        $msgAdmin =
          '[ADMIN] Pesanan baru: '.
          $customer_name.
          ' — Rp '.
          number_format($grand_total, 0, ',', '.').
          ' ('.
          $invoice_no.
          ')';
        // Pesan notifikasi untuk admin

        create_notification(
            $mysqli,
            null,
            'admin',
            $msgAdmin,
            $BASE.'/public/admin/orders.php'
        );
        // Notifikasi ke semua admin

        $mysqli->commit();
        // Commit transaksi jika semua langkah sukses

        out(
            [
                'ok' => true,
                'id' => $order_id,
                'invoice_no' => $invoice_no,
                'subtotal' => $subtotal,
                'tax_amount' => $tax_amount,
                'grand_total' => $grand_total,
                'payment_method' => $pay_method,
            ]
        );
        // Kirim respons sukses dengan ringkasan order

    } catch (Throwable $e) {
        // Jika ada exception dalam transaksi

        $mysqli->rollback();
        // Batalkan seluruh perubahan

        bad($e->getMessage(), 500);
        // Kirim error server dengan pesan exception
    }
}

/* ======================================================
   UPDATE (dipakai karyawan/admin: next status, bayar, metode, cancel)
====================================================== */
if ($action === 'update') {
    // Jika action=update → ubah status/metode/cancel pesanan

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        // Hanya boleh POST
        bad('Use POST', 405);
        // Kirim error method not allowed
    }

    $rawBody = file_get_contents('php://input');
    // Baca body request mentah

    $data = json_decode($rawBody, true);
    // Decode JSON jadi array

    if (! is_array($data)) {
        // Jika body bukan JSON array
        bad('Invalid JSON');
        // Kirim error invalid JSON
    }

    $id = (int) ($data['id'] ?? 0);
    // Ambil id order dari body

    if ($id <= 0) {
        // id wajib lebih besar dari 0
        bad('Missing id');
    }

    // baca order sekarang
    $stmt = $mysqli->prepare('
    SELECT id, user_id, invoice_no, customer_name,
           grand_total, tax_amount, payment_method, payment_status, order_status
    FROM orders WHERE id=? LIMIT 1
  ');
    // Siapkan query untuk ambil data order saat ini

    $stmt->bind_param('i', $id);
    // Bind id order

    $stmt->execute();
    // Eksekusi query

    $order = $stmt->get_result()->fetch_assoc();
    // Ambil satu baris data order

    $stmt->close();
    // Tutup statement

    if (! $order) {
        // Jika order tidak ditemukan
        bad('Order not found', 404);
        // Kirim error 404
    }

    $oldOrderStatus = (string) $order['order_status'];
    // Simpan status order lama

    $oldPayStatus = (string) $order['payment_status'];
    // Simpan status pembayaran lama

    $userIdOrder = $order['user_id'] !== null
      ? (int) $order['user_id']
      : null;
    // Ambil user_id yang punya order (kalau ada)

    $invoiceNo = (string) $order['invoice_no'];
    // Invoice dari order ini

    $customerName = (string) $order['customer_name'];
    // Nama customer

    $fields = [];
    // Array field yang akan diupdate

    $params = [];
    // Array parameter untuk bind_param update

    $types = '';
    // String tipe parameter

    $newOrderStatus = null;
    // Variabel status order baru (jika diubah)

    $newPayStatus = null;
    // Variabel status pembayaran baru (jika diubah)

    $newPayMethod = null;
    // Variabel metode bayar baru (jika diubah)

    $cancelReason = null;
    // Alasan cancel jika ada

    // order_status
    if (isset($data['order_status'])) {
        // Jika request mengirim order_status baru

        $val = (string) $data['order_status'];
        // Ambil nilai status baru

        if (! in_array($val, $GLOBALS['ALLOWED_ORDER_STATUS'], true)) {
            // Validasi status baru
            bad('Invalid order_status');
        }

        if ($val === 'processing' && $oldPayStatus !== 'paid') {
            // Tidak boleh processing jika belum paid
            bad('Pembayaran belum Lunas. Set ke paid dulu.');
        }

        if ($val === 'cancelled' && $oldPayStatus !== 'pending') {
            // Tidak boleh cancel jika sudah dibayar
            bad('Pembatalan hanya untuk pesanan yang belum dibayar.');
        }

        $fields[] = 'order_status=?';
        // Tambahkan field yang akan diupdate

        $params[] = $val;
        // Tambah nilai parameter status

        $types .= 's';
        // Tipe parameter string

        $newOrderStatus = $val;
        // Simpan status order baru

        if ($val === 'cancelled') {
            // Jika status baru cancelled, proses alasan pembatalan

            $cancelReason = trim((string) ($data['cancel_reason'] ?? ''));
            // Ambil cancel_reason jika ada

            if ($cancelReason !== '') {
                // Jika ada alasan
                $fields[] = 'cancel_reason=?';
                // Tambah field cancel_reason

                $params[] = $cancelReason;
                // Tambah parameter alasan

                $types .= 's';
                // Tipe string
            }

            $fields[] = 'canceled_by_id=?';
            // Simpan id user yang membatalkan

            $params[] = (int) ($_SESSION['user_id'] ?? 0);
            // Ambil user_id dari session, cast ke int

            $types .= 'i';
            // Tipe integer

            $fields[] = 'canceled_at=NOW()';
            // Catat waktu cancel sekarang
        }
    }

    // payment_status
    if (isset($data['payment_status'])) {
        // Jika request mengirim status pembayaran baru

        $val = (string) $data['payment_status'];
        // Ambil nilai status

        if (! in_array($val, $GLOBALS['ALLOWED_PAYMENT_STATUS'], true)) {
            // Jika status tidak valid
            bad('Invalid payment_status');
        }

        if ($oldPayStatus === 'paid' && $val === 'pending') {
            // Tidak boleh mengembalikan paid ke pending
            bad('Pembayaran sudah Lunas dan tidak bisa kembali ke Pending.');
        }

        $fields[] = 'payment_status=?';
        // Tambahkan field payment_status ke update

        $params[] = $val;
        // Tambah parameter nilai status baru

        $types .= 's';
        // Tipe parameter string

        $newPayStatus = $val;
        // Simpan status bayar baru
    }

    // payment_method
    if (array_key_exists('payment_method', $data)) {
        // Kalau key payment_method dikirim (meski null)

        $val = $data['payment_method'];
        // Ambil nilai mentah

        if ($val !== null && $val !== '') {
            // Jika ada nilai metode baru

            if (! in_array($val, $GLOBALS['ALLOWED_METHOD'], true)) {
                // Validasi metode
                bad('Invalid payment_method');
            }

            $fields[] = 'payment_method=?';
            // Tambah field payment_method ke update

            $params[] = $val;
            // Tambah parameter metode baru

            $types .= 's';
            // Tipe string

            $newPayMethod = $val;
            // Simpan metode bayar baru

        } else {
            // Jika dikosongkan (null/empty string)

            $fields[] = 'payment_method=NULL';
            // Set payment_method ke NULL di DB
        }
    }

    if (! $fields) {
        // Jika tidak ada field apapun yang diubah
        bad('No fields to update');
    }

    // update orders
    $sql =
      'UPDATE orders SET '.
      implode(', ', $fields).
      ', updated_at=NOW() WHERE id=?';
    // Query update orders sesuai fields dan set updated_at

    $params[] = $id;
    // Tambah id di akhir parameter

    $types .= 'i';
    // Tipe parameter tambahan integer

    $stmt = $mysqli->prepare($sql);
    // Siapkan prepared statement

    if (! $stmt) {
        // Jika prepare gagal
        bad('Prepare failed: '.$mysqli->error, 500);
        // Kirim error server
    }

    $stmt->bind_param($types, ...$params);
    // Bind semua parameter ke query update

    if (! $stmt->execute()) {
        // Jika eksekusi update gagal
        bad('Update failed: '.$stmt->error, 500);
        // Kirim error server
    }

    $stmt->close();
    // Tutup statement update order

    // ===== sinkron ke payments
    $grand = (float) $order['grand_total'];
    // Nilai grand_total order

    $tax = (float) $order['tax_amount'];
    // Nilai pajak order

    $pm = $newPayMethod
      ?? $order['payment_method']
      ?? 'cash';
    // Metode pembayaran terbaru (prioritas: yang baru)

    $ps = $newPayStatus
      ?? $order['payment_status']
      ?? 'pending';
    // Status bayar terbaru

    $os = $newOrderStatus
      ?? $order['order_status'];
    // Status order terbaru

    $stmt = $mysqli->prepare(
        'SELECT id, status FROM payments WHERE order_id=? LIMIT 1'
    );
    // Ambil record payment terkait order_id

    $stmt->bind_param('i', $id);
    // Bind id order

    $stmt->execute();
    // Eksekusi select payments

    $payRow = $stmt->get_result()->fetch_assoc();
    // Ambil payment row (jika ada)

    $stmt->close();
    // Tutup statement payment select

    if ($os === 'cancelled') {
        // Jika order dibatalkan

        // payments -> failed
        $noteCancel =
          'cancelled: '.
          ($cancelReason ?: '-').
          ' | from orders #'.
          $invoiceNo;
        // Catatan pembatalan untuk payment

        if ($payRow) {
            // Jika payment sudah ada

            $stmt = $mysqli->prepare(
                "UPDATE payments
         SET status='failed',
             amount_gross=0,
             discount=0,
             tax=0,
             shipping=0,
             amount_net=0,
             paid_at=NULL,
             note=?,
             method=?
         WHERE order_id=?"
            );
            // Update payment jadi failed dan amount 0

            $stmt->bind_param('ssi', $noteCancel, $pm, $id);
            // Bind catatan, metode, dan order_id

            $stmt->execute();
            // Eksekusi update

            $stmt->close();
            // Tutup statement

        } else {
            // Jika belum ada payment sebelumnya

            $stmt = $mysqli->prepare(
                "INSERT INTO payments
         (order_id, method, status, amount_gross, discount, tax, shipping, amount_net, paid_at, note)
         VALUES (?,?, 'failed', 0,0,0,0,0, NULL, ?)"
            );
            // Insert payment baru dengan status failed

            $stmt->bind_param('iss', $id, $pm, $noteCancel);
            // Bind order_id, metode, catatan

            $stmt->execute();
            // Eksekusi insert

            $stmt->close();
            // Tutup statement
        }

    } else {
        // resync normal (bukan cancelled)

        if ($payRow) {
            // Jika record payment sudah ada

            $stmt = $mysqli->prepare(
                "UPDATE payments
         SET method=?,
             status=?,
             amount_gross=?,
             tax=?,
             amount_net=?,
             paid_at = CASE WHEN ?='paid' THEN NOW() ELSE paid_at END,
             note=CONCAT('auto resync from orders #', ?)
         WHERE order_id=?"
            );
            // Update data payment sesuai order

            $stmt->bind_param(
                'ssdddssi',
                $pm,
                $ps,
                $grand,
                $tax,
                $grand,
                $ps,
                $invoiceNo,
                $id
            );
            // Bind parameter ke query

            $stmt->execute();
            // Eksekusi update

            $stmt->close();
            // Tutup statement

        } else {
            // Jika payment belum ada, buat baru

            $stmt = $mysqli->prepare(
                "INSERT INTO payments
         (order_id, method, status, amount_gross, discount, tax, shipping, amount_net, paid_at, note)
         VALUES
         (?,?,?, ?,0, ?,0, ?, CASE WHEN ?='paid' THEN NOW() ELSE NULL END,
          CONCAT('auto resync from orders #', ?))"
            );
            // Insert record payment baru

            $stmt->bind_param(
                'issdddsss',
                $id,
                $pm,
                $ps,
                $grand,
                $tax,
                $grand,
                $ps,
                $invoiceNo
            );
            // Bind parameter ke insert

            $stmt->execute();
            // Eksekusi insert

            $stmt->close();
            // Tutup statement
        }
    }

    /* ===== AUDIT (FOKUS: order & payment) ===== */
    // 1) order_status berubah
    if ($newOrderStatus !== null && $newOrderStatus !== $oldOrderStatus) {
        // Jika status order benar-benar berubah

        $from = json_encode(
            ['order_status' => $oldOrderStatus],
            JSON_UNESCAPED_UNICODE
        );
        // Nilai sebelumnya untuk audit

        $toArr = ['order_status' => $newOrderStatus];
        // Array nilai sesudah

        if ($newOrderStatus === 'cancelled') {
            // Jika status baru cancelled, sertakan reason
            $toArr['reason'] = $cancelReason;
            // Tambah reason ke array
        }

        $to = json_encode($toArr, JSON_UNESCAPED_UNICODE);
        // Encode nilai sesudah ke JSON

        audit_log(
            $mysqli,
            $actorId,
            'order',
            $id,
            'update_status',
            $from,
            $to,
            'order status changed'
        );
        // Catat perubahan status order di audit
    }

    // 2) payment_status berubah (uang)
    if ($newPayStatus !== null && $newPayStatus !== $oldPayStatus) {
        // Jika status pembayaran berubah

        audit_log(
            $mysqli,
            $actorId,
            'payment',
            $id,
            'update_status',
            $oldPayStatus,
            $newPayStatus,
            'payment status changed'
        );
        // Catat perubahan status payment di audit
    }

    /* ===== NOTIF (tetap, TIDAK diaudit) ===== */
    if ($os === 'cancelled') {
        // Jika order dibatalkan, kirim notifikasi ke karyawan & admin

        $msgKaryawan =
          'Pesanan dibatalkan: '.
          $customerName.
          ' ('.
          $invoiceNo.
          ')'.
          ($cancelReason ? ' — '.$cancelReason : '');
        // Pesan untuk karyawan

        create_notification(
            $mysqli,
            null,
            'karyawan',
            $msgKaryawan,
            $BASE.'/public/karyawan/orders.php'
        );
        // Notif ke karyawan

        $msgAdmin =
          '[ADMIN] Pesanan dibatalkan: '.
          $customerName.
          ' ('.
          $invoiceNo.
          ')'.
          ($cancelReason ? ' — '.$cancelReason : '');
        // Pesan untuk admin

        create_notification(
            $mysqli,
            null,
            'admin',
            $msgAdmin,
            $BASE.'/public/admin/orders.php'
        );
        // Notif ke admin

    } else {
        // Jika tidak dibatalkan

        if ($newPayStatus === 'paid' && $oldPayStatus !== 'paid') {
            // Jika baru berubah menjadi paid

            $msgPaid =
              'Pembayaran LUNAS untuk '.
              $customerName.
              ' ('.
              $invoiceNo.
              ').';
            // Pesan untuk bayar lunas

            create_notification(
                $mysqli,
                null,
                'karyawan',
                $msgPaid,
                $BASE.'/public/karyawan/orders.php'
            );
            // Notif ke karyawan

            create_notification(
                $mysqli,
                null,
                'admin',
                '[ADMIN] '.$msgPaid,
                $BASE.'/public/admin/orders.php'
            );
            // Notif ke admin
        }

        if ($newOrderStatus !== null && $newOrderStatus !== $oldOrderStatus) {
            // Jika status order berubah

            $msgFlow =
              'Status pesanan '.
              $invoiceNo.
              ' → '.
              $newOrderStatus.
              '.';
            // Pesan perubahan status

            create_notification(
                $mysqli,
                null,
                'karyawan',
                $msgFlow,
                $BASE.'/public/karyawan/orders.php'
            );
            // Notif ke karyawan
        }
    }

    // notif customer
    if (
        $userIdOrder &&
        $newOrderStatus !== null &&
        $newOrderStatus !== $oldOrderStatus
    ) {
        // Jika order punya user_id dan status order berubah

        $historyLink =
          $BASE.
          '/public/customer/history.php?invoice='.
          $invoiceNo;
        // Link ke riwayat pesanan customer

        if ($newOrderStatus === 'ready') {
            // Jika status ready

            create_notification(
                $mysqli,
                $userIdOrder,
                'customer',
                'Pesanan kamu sudah ready ('.$invoiceNo.').',
                $historyLink
            );
            // Notif bahwa pesanan siap diambil

        } elseif ($newOrderStatus === 'completed') {
            // Jika status completed

            create_notification(
                $mysqli,
                $userIdOrder,
                'customer',
                'Pesanan kamu sudah selesai ('.$invoiceNo.'). Terima kasih.',
                $historyLink
            );
            // Notif bahwa pesanan selesai

        } elseif ($newOrderStatus === 'cancelled') {
            // Jika status cancelled

            $msgCancelCust =
              'Pesanan kamu dibatalkan ('.
              $invoiceNo.
              ').'.
              ($cancelReason ? ' Alasan: '.$cancelReason : '');
            // Pesan pembatalan order untuk customer

            create_notification(
                $mysqli,
                $userIdOrder,
                'customer',
                $msgCancelCust,
                $historyLink
            );
            // Notif pembatalan ke customer
        }
    }

    out(
        [
            'ok' => true,
        ]
    );
    // Kirim respons sukses untuk update
}

/* ======================================================
   DEFAULT
====================================================== */
bad('Invalid action', 404);
// Jika action tidak dikenali → kirim error 404
