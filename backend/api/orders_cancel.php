<?php

// Pembuka file PHP

// backend/api/orders_cancel.php
// Endpoint API khusus untuk membatalkan pesanan

declare(strict_types=1);
// Aktifkan strict typing

header('Content-Type: application/json; charset=utf-8');
// Set header respons ke JSON UTF-8

session_start();
// Mulai atau lanjutkan session

require_once __DIR__.'/../config.php';
// Load konfigurasi utama (koneksi DB, konstanta, dll.)

/* ==== AUDIT HELPER ==== */
$helperCandidates = [
    __DIR__.'/../lib/audit_helper.php',
    // Lokasi normal audit_helper di folder lib atas

    __DIR__.'/lib/audit_helper.php',
    // Lokasi alternatif jika helper diletakkan di /api/lib
];
// Daftar kandidat path untuk file helper audit

foreach ($helperCandidates as $p) {
    // Loop setiap kandidat path
    if (is_file($p)) {
        // Jika file helper ditemukan di path ini
        require_once $p;
        // Load file helper audit

        break;
        // Berhenti di kandidat pertama yang ketemu
    }
}

if (! function_exists('audit_log')) {
    // Jika setelah require, fungsi audit_log belum ada

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
        // Definisi fungsi fallback audit_log

        // fallback aman bila helper belum ada
        error_log(
            "[AUDIT_FALLBACK] $actorId $entity#$entityId $action ($remark)"
        );
        // Tulis log sederhana ke error_log server

        return true;
        // Selalu kembalikan true supaya tidak memutus flow utama
    }
}

/* ==== DB ==== */
$mysqli = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
// Buat koneksi baru ke database dengan konfigurasi dari config.php

if ($mysqli->connect_errno) {
    // Jika ada error koneksi database

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

/* ==== Utils ==== */
function out(array $arr): void
{
    // Helper untuk mengirim respons JSON dan berhenti

    echo json_encode($arr, JSON_UNESCAPED_SLASHES);
    // Encode array ke JSON tanpa escape karakter slash

    exit;
    // Hentikan script setelah output
}

function bad(string $msg, int $code = 400): void
{
    // Helper untuk mengirim error JSON dengan kode HTTP

    http_response_code($code);
    // Set HTTP status code

    out(
        [
            'ok' => false,
            'error' => $msg,
        ]
    );
    // Kirim body JSON berisi error dan hentikan eksekusi
}

function create_notification(
    mysqli $db,
    ?int $userId,
    ?string $role,
    string $message,
    ?string $link = null
): void {
    // Fungsi untuk menyimpan notifikasi ke tabel notifications

    $stmt = $db->prepare("
    INSERT INTO notifications (user_id, role, message, status, created_at, link)
    VALUES (?, ?, ?, 'unread', NOW(), ?)
  ");
    // Siapkan query insert notifikasi dengan status unread

    if (! $stmt) {
        // Jika prepare gagal, tidak usah fatal
        return;
        // Langsung keluar fungsi
    }

    $stmt->bind_param(
        'isss',
        $userId,
        $role,
        $message,
        $link
    );
    // Bind parameter user_id, role, message, link

    $stmt->execute();
    // Eksekusi query insert

    $stmt->close();
    // Tutup statement untuk membebaskan resource
}

/* ==== Guard ==== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // API ini hanya menerima metode POST
    bad('Use POST', 405);
    // Kirim error method not allowed
}

/* ==== Input ==== */
$order_id = (int) ($_POST['order_id'] ?? 0);
// Ambil order_id dari POST dan konversi ke integer

$reason = trim((string) ($_POST['reason'] ?? ''));
// Ambil alasan pembatalan dari POST, trim spasi

if ($order_id <= 0) {
    // Validasi: order_id wajib ada dan > 0
    bad('Missing order_id');
}

if ($reason === '') {
    // Jika alasan kosong, pakai default
    $reason = 'Pembatalan pesanan (belum bayar)';
}

/* ==== Ambil order (validasi & for audit from_val) ==== */
$stmt = $mysqli->prepare('
  SELECT id, user_id, invoice_no, customer_name, payment_status, order_status, payment_method,
         grand_total, tax_amount
  FROM orders
  WHERE id=? LIMIT 1
');
// Siapkan query untuk mengambil data order berdasarkan id

$stmt->bind_param('i', $order_id);
// Bind parameter id order

$stmt->execute();
// Eksekusi query

$order = $stmt->get_result()->fetch_assoc();
// Ambil satu baris data order sebagai array asosiatif

$stmt->close();
// Tutup statement

if (! $order) {
    // Jika order tidak ditemukan
    bad('Order tidak ditemukan', 404);
    // Kirim error 404
}

/* ==== Validasi ==== */
if ((string) $order['payment_status'] !== 'pending') {
    // Hanya boleh membatalkan pesanan yang belum dibayar (payment_status pending)
    bad('Pembatalan hanya untuk pesanan yang belum dibayar.');
}

if (
    in_array(
        (string) $order['order_status'],
        ['completed', 'cancelled'],
        true
    )
) {
    // Jika status sudah completed atau cancelled, tidak bisa dibatalkan
    bad('Pesanan tidak dapat dibatalkan.');
}

/* ==== Context ==== */
$invoice = (string) $order['invoice_no'];
// Simpan nomor invoice untuk kebutuhan pesan/notif/audit

$customerId = $order['user_id'] !== null
  ? (int) $order['user_id']
  : null;
// Ambil user_id pemilik pesanan jika ada, jika tidak null

$custName = (string) $order['customer_name'];
// Nama customer dari pesanan

$method = (string) $order['payment_method'];
// Metode pembayaran yang tercatat pada order

$actorId = (int) ($_SESSION['user_id'] ?? 0);
// ID user yang melakukan pembatalan (aktor audit)

/**
 * Data keadaan awal order (from_val) untuk keperluan audit
 */
$fromOrder = json_encode(
    [
        'order_status' => (string) $order['order_status'],
        'payment_status' => (string) $order['payment_status'],
    ],
    JSON_UNESCAPED_UNICODE
);
// Encode kondisi awal status order & payment sebagai JSON

/* ==== Transaction ==== */
$mysqli->begin_transaction();
// Mulai transaksi database

try {
    // 1) Update orders -> cancelled + failed
    $stmt = $mysqli->prepare("
    UPDATE orders
    SET order_status='cancelled',
        payment_status='failed',
        cancel_reason=?,
        canceled_by_id=?,
        canceled_at=NOW(),
        updated_at=NOW()
    WHERE id=?
  ");
    // Siapkan query update status order jadi cancelled & payment failed

    $stmt->bind_param(
        'sii',
        $reason,
        $actorId,
        $order_id
    );
    // Bind alasan, user yang membatalkan, dan id order

    if (! $stmt->execute()) {
        // Jika eksekusi update gagal
        throw new Exception(
            'Update orders failed: '.$stmt->error
        );
        // Lempar exception agar ditangani di catch
    }

    $stmt->close();
    // Tutup statement update orders

    // AUDIT: fokus ke order
    $toOrder = json_encode(
        [
            'order_status' => 'cancelled',
            'payment_status' => 'failed',
            'reason' => $reason,
            'invoice' => $invoice,
        ],
        JSON_UNESCAPED_UNICODE
    );
    // Data sesudah (to_val) untuk dicatat di audit_log

    audit_log(
        $mysqli,
        $actorId,
        'order',
        $order_id,
        'cancel',
        $fromOrder,
        $toOrder,
        'cancel via API'
    );
    // Catat perubahan status order sebagai event "cancel" di audit

    // 2) Sinkron payments -> failed & angka nol
    $note =
      'cancelled: '.
      $reason.
      ' | from order '.
      $invoice;
    // Catatan yang akan disimpan di kolom note payments

    $stmt = $mysqli->prepare('
    SELECT id, status
    FROM payments
    WHERE order_id=?
    LIMIT 1
  ');
    // Cek apakah sudah ada baris pembayaran untuk order ini

    $stmt->bind_param('i', $order_id);
    // Bind id order

    $stmt->execute();
    // Eksekusi select

    $payRow = $stmt->get_result()->fetch_assoc();
    // Ambil baris payment jika ada

    $stmt->close();
    // Tutup statement select payments

    if ($payRow) {
        // Jika sudah ada baris payments sebelumnya

        $prevPayStatus = (string) ($payRow['status'] ?? '');
        // Simpan status payment lama untuk audit

        $stmt = $mysqli->prepare("
      UPDATE payments
      SET status='failed',
          amount_gross=0,
          discount=0,
          tax=0,
          shipping=0,
          amount_net=0,
          paid_at=NULL,
          note=?,
          method=?,
          updated_at=NOW()
      WHERE order_id=?
    ");
        // Update payment: status failed dan semua angka uang jadi 0

        $stmt->bind_param(
            'ssi',
            $note,
            $method,
            $order_id
        );
        // Bind note, metode, dan id order

        if (! $stmt->execute()) {
            // Jika update payments gagal
            throw new Exception(
                'Update payments failed: '.$stmt->error
            );
            // Lempar exception
        }

        $stmt->close();
        // Tutup statement update payment

        // AUDIT: perubahan status payment (uang)
        audit_log(
            $mysqli,
            $actorId,
            'payment',
            $order_id,
            'update_status',
            $prevPayStatus !== '' ? $prevPayStatus : 'unknown',
            'failed',
            'sync cancel'
        );
        // Catat perubahan status pembayaran dari sebelumnya ke failed

    } else {
        // Jika belum ada baris payments untuk order ini

        $stmt = $mysqli->prepare("
      INSERT INTO payments
        (order_id, method, status, amount_gross, discount, tax, shipping, amount_net, paid_at, note)
      VALUES
        (?, ?, 'failed', 0, 0, 0, 0, 0, NULL, ?)
    ");
        // Insert baris payment baru dengan status failed dan nilai 0

        $stmt->bind_param(
            'iss',
            $order_id,
            $method,
            $note
        );
        // Bind order_id, metode, dan note

        if (! $stmt->execute()) {
            // Jika insert payments gagal
            throw new Exception(
                'Insert payments failed: '.$stmt->error
            );
            // Lempar exception
        }

        $stmt->close();
        // Tutup statement insert payment

        // AUDIT: membuat baris payment (status gagal)
        audit_log(
            $mysqli,
            $actorId,
            'payment',
            $order_id,
            'create_failed',
            '',
            'failed',
            'insert payment on cancel'
        );
        // Catat bahwa baris payment gagal dibuat saat cancel
    }

    // 3) Kirim notifikasi (TIDAK dicatat di audit_logs agar audit bersih)
    if ($customerId) {
        // Jika pesanan terkait customer terdaftar

        $linkCus =
          '/caffora-app1/public/customer/history.php?invoice='.
          $invoice;
        // Link riwayat pesanan customer di frontend

        create_notification(
            $mysqli,
            $customerId,
            'customer',
            'Pesanan kamu dibatalkan ('.$invoice.'). Alasan: '.$reason,
            $linkCus
        );
        // Notifikasi ke customer bahwa pesanannya dibatalkan
    }

    $msgK =
      'Pesanan dibatalkan: '.
      $custName.
      ' ('.
      $invoice.
      ') — '.
      $reason;
    // Pesan notifikasi untuk karyawan & admin

    create_notification(
        $mysqli,
        null,
        'karyawan',
        $msgK,
        '/caffora-app1/public/karyawan/orders.php'
    );
    // Notifikasi ke karyawan tentang pembatalan pesanan

    create_notification(
        $mysqli,
        null,
        'admin',
        '[ADMIN] '.$msgK,
        '/caffora-app1/public/admin/orders.php'
    );
    // Notifikasi ke admin tentang pembatalan pesanan

    $mysqli->commit();
    // Commit transaksi jika semua langkah di atas berhasil

    out(['ok' => true]);
    // Kirim respons sukses

} catch (Throwable $e) {
    // Jika ada error/exception di dalam blok try

    $mysqli->rollback();
    // Batalkan semua perubahan pada transaksi

    bad($e->getMessage(), 500);
    // Kirim error server dengan pesan exception
}
