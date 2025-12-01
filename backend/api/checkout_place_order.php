<?php

// ---------------------------------------------
// File  : backend/checkout_place_order.php
// Desc  : Proses place order dari customer
// ---------------------------------------------

declare(strict_types=1);                        // Aktifkan strict typing untuk PHP

session_start();                                // Mulai session (ambil data user, cart)

require_once __DIR__.'/config.php';           // Load config (DB, BASE_URL, dll)
require_once __DIR__.'/auth_guard.php';       // Load auth guard (fungsi require_login)

// Wajib login sebagai customer untuk place order
require_login(['customer']);                    // Hanya role 'customer' yang diizinkan

// Set respons default sebagai JSON UTF-8
header('Content-Type: application/json; charset=utf-8');

/* ====== Audit helper (pakai lib bila ada, fallback inline) ====== */

// Path file helper audit
$__auditHelper = __DIR__.'/lib/audit_helper.php';

// Jika file helper audit ada, load
if (is_file($__auditHelper)) {
    require_once $__auditHelper;
}

// Jika fungsi audit_log belum ada (fallback)
if (! function_exists('audit_log')) {

    /**
     * Minimal helper untuk tabel audit_logs.
     * Mencatat aktivitas penting ke tabel audit_logs.
     */
    function audit_log(
        mysqli $db,                                  // Koneksi DB
        int $actorId,                             // ID user pelaku
        string $entity,                              // Entitas: 'order' | 'payment' | 'menu' | 'user'
        int $entityId,                            // ID entitas
        string $action,                              // Aksi: 'create' | 'cancel' | 'paid' | ...
        string $fromVal = '',                        // Nilai sebelumnya (opsional)
        string $toVal = '',                        // Nilai sesudahnya (opsional)
        string $remark = ''                         // Catatan tambahan
    ): bool {

        // Batasi panjang remark maksimal 255 karakter
        if (mb_strlen($remark) > 255) {
            $remark = mb_substr($remark, 0, 255);
        }

        // Query insert ke tabel audit_logs
        $sql = '
      INSERT INTO audit_logs
        (actor_id, entity, entity_id, action, from_val, to_val, remark)
      VALUES
        (?,?,?,?,?,?,?)
    ';

        // Siapkan prepared statement
        $stmt = $db->prepare($sql);

        // Jika gagal prepare, kembalikan false
        if (! $stmt) {
            return false;
        }

        // Bind parameter ke statement
        $stmt->bind_param(
            'isissss',
            $actorId,                                  // actor_id
            $entity,                                   // entity
            $entityId,                                 // entity_id
            $action,                                   // action
            $fromVal,                                  // from_val
            $toVal,                                    // to_val
            $remark                                    // remark
        );

        // Eksekusi insert audit
        $ok = $stmt->execute();

        // Tutup statement
        $stmt->close();

        // Kembalikan status eksekusi
        return $ok;
    }
}

try {
    // Ambil ID user dari session sebagai actorId
    $actorId = (int) (
        $_SESSION['user_id'] ?? 0
    );

    // Nama customer yang mengisi form checkout
    $customer_name = trim(
        (string) (
            $_POST['customer_name'] ?? ''
        )
    );

    // Jenis layanan: dine_in | takeaway | delivery
    $service_type = trim(
        (string) (
            $_POST['service_type'] ?? 'dine_in'
        )
    );

    // Nomor meja (kalau dine in)
    $table_no = trim(
        (string) (
            $_POST['table_no'] ?? ''
        )
    );

    // Metode pembayaran: cash / qris / dll (default cash)
    $payment_method = trim(
        (string) (
            $_POST['payment_method'] ?? 'cash'
        )
    );

    // Keranjang diambil dari session
    $cart = $_SESSION['cart'] ?? [];

    // Jika cart kosong, lempar exception
    if (! $cart) {
        throw new Exception('Keranjang kosong.');
    }

    // -------------------------------------------------
    // Generate invoice incremental sederhana
    // -------------------------------------------------

    // Ambil invoice terakhir berdasarkan id terbesar
    $res = $conn->query('
    SELECT
      invoice_no
    FROM
      orders
    ORDER BY
      id DESC
    LIMIT 1
  ');

    // Ambil invoice terakhir (jika ada)
    $last = $res?->fetch_assoc()['invoice_no'] ?? null;

    // Default nomor urut invoice = 1
    $n = 1;

    // Jika ada invoice terakhir dan mengandung angka
    if (
        $last
        && preg_match('~(\d+)~', $last, $m)
    ) {
        // Ambil angka terakhir dan tambah 1
        $n = (int) $m[1] + 1;
    }

    // Format invoice: INV-xxx (3 digit)
    $invoice_no = sprintf(
        'INV-%03d',
        $n
    );

    // Mulai transaksi database
    $conn->begin_transaction();

    // -------------------------------------------------
    // Hitung total dari DB (anti manipulasi harga)
    // -------------------------------------------------

    $grand = 0;                                   // Total grand total
    $items = [];                                  // Array item yang akan disimpan

    // Siapkan prepared statement untuk ambil data menu
    $stmtM = $conn->prepare('
    SELECT
      id,
      name,
      price
    FROM
      menu
    WHERE
      id = ?
  ');

    // Loop semua item di keranjang
    foreach ($cart as $c) {

        // Ambil menu_id dari cart
        $menu_id = (int) (
            $c['menu_id'] ?? 0
        );

        // Ambil qty dari cart (min 1)
        $qty = max(
            1,
            (int) (
                $c['qty'] ?? 1
            )
        );

        // Bind menu_id ke statement menu
        $stmtM->bind_param(
            'i',
            $menu_id
        );

        // Eksekusi query menu
        $stmtM->execute();

        // Ambil satu baris menu
        $r = $stmtM
            ->get_result()
            ->fetch_assoc();

        // Jika menu tidak ditemukan, lempar exception
        if (! $r) {
            throw new Exception(
                "Menu $menu_id tidak ditemukan."
            );
        }

        // Harga menu dari database (integer)
        $price = (int) $r['price'];

        // Hitung subtotal item = price * qty
        $sub = $price * $qty;

        // Tambahkan ke grand total
        $grand += $sub;

        // Simpan detail item ke array items
        $items[] = [
            'menu_id' => $menu_id,                  // ID menu
            'name' => (string) $r['name'],        // Nama menu
            'qty' => $qty,                      // Jumlah
            'price' => $price,                    // Harga satuan
            'subtotal' => $sub,                       // Subtotal
        ];
    }

    // Tutup statement menu
    $stmtM->close();

    // -------------------------------------------------
    // Simpan ke tabel orders
    // -------------------------------------------------

    // Status awal order: new
    $order_status = 'new';

    // Status awal pembayaran: pending
    $payment_status = 'pending';

    // Siapkan statement insert ke tabel orders
    $stmtO = $conn->prepare('
    INSERT INTO orders
      (
        invoice_no,
        customer_name,
        service_type,
        table_no,
        total,
        payment_method,
        order_status,
        payment_status,
        created_at
      )
    VALUES
      (?,?,?,?,?,?,?,?, NOW())
  ');

    // Bind parameter order ke statement
    $stmtO->bind_param(
        'ssssisss',
        $invoice_no,                                // invoice_no
        $customer_name,                             // customer_name
        $service_type,                              // service_type
        $table_no,                                  // table_no
        $grand,                                     // total
        $payment_method,                            // payment_method
        $order_status,                              // order_status
        $payment_status                             // payment_status
    );

    // Eksekusi insert orders, jika gagal lempar exception
    if (! $stmtO->execute()) {
        throw new Exception('Gagal menyimpan pesanan.');
    }

    // Ambil ID order baru yang barusan diinsert
    $order_id = (int) $conn->insert_id;

    // Tutup statement orders
    $stmtO->close();

    // -------------------------------------------------
    // Simpan ke tabel order_items
    // -------------------------------------------------

    // Siapkan statement insert untuk order_items
    $stmtI = $conn->prepare('
    INSERT INTO order_items
      (order_id, menu_id, qty, price, subtotal)
    VALUES
      (?,?,?,?,?)
  ');

    // Loop semua item yang sudah dihitung
    foreach ($items as $it) {

        // Bind data item ke statement order_items
        $stmtI->bind_param(
            'iiiii',
            $order_id,                                // order_id
            $it['menu_id'],                           // menu_id
            $it['qty'],                               // qty
            $it['price'],                             // price
            $it['subtotal']                           // subtotal
        );

        // Eksekusi insert, jika gagal lempar exception
        if (! $stmtI->execute()) {
            throw new Exception('Gagal menyimpan item.');
        }
    }

    // Tutup statement order_items
    $stmtI->close();

    /* ===== AUDIT: hanya event penting — ORDER CREATED ===== */

    // Siapkan nilai toVal dalam bentuk JSON ringkas
    $toVal = json_encode(
        [
            'invoice' => $invoice_no,          // Nomor invoice
            'customer' => $customer_name,       // Nama customer
            'service_type' => $service_type,        // Jenis layanan
            'table_no' => $table_no,            // Nomor meja
            'payment_method' => $payment_method,      // Metode pembayaran
            'total' => $grand,               // Grand total
            'items_count' => count($items),         // Jumlah item
        ],
        JSON_UNESCAPED_UNICODE                      // Jangan escape karakter Unicode
    );

    // Catat log audit: event order created
    audit_log(
        $conn,                                      // Koneksi DB
        $actorId,                                   // ID user pelaku
        'order',                                    // entity = 'order'
        $order_id,                                  // entity_id = ID order
        'create',                                   // action = 'create'
        '',                                         // fromVal kosong
        $toVal,                                     // toVal = JSON ringkas order
        'place order'                               // remark
    );

    // Catatan:
    // TIDAK mencatat "payment intent/pending" agar audit bersih.
    // Nanti saat pembayaran sukses, endpoint pembayaran akan mencatat 'paid'.

    // Kosongkan keranjang setelah order berhasil
    unset($_SESSION['cart']);

    // Commit transaksi (semua query berhasil)
    $conn->commit();

    // Kirim respon JSON sukses ke frontend
    echo json_encode([
        'ok' => true,                         // Flag sukses
        'invoice' => $invoice_no,                  // Nomor invoice
        'redirect' => BASE_URL                      // URL redirect ke riwayat
          .'/public/customer/history.php',
    ]);

} catch (Throwable $e) {                         // Tangkap semua error/exception

    // Jika ada koneksi dan ada error di mysqli → rollback
    if ($conn && $conn->errno) {
        $conn->rollback();
    }

    // Set HTTP status 400 (bad request)
    http_response_code(400);

    // Kirim respon JSON error dengan pesan exception
    echo json_encode([
        'ok' => false,                           // Flag gagal
        'error' => $e->getMessage(),                 // Pesan error
    ]);
}
