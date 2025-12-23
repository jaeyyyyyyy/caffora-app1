<?php
// public/admin/help.php
declare(strict_types=1);

require_once __DIR__ . '/../../backend/auth_guard.php';
require_login(['admin']); // hanya admin
require_once __DIR__ . '/../../backend/config.php';

$userName = $_SESSION['user_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <title>FAQ — Caffora (Admin)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- Fonts & CSS -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />

  <style>
 :root{                                              /* Root variable */
  --gold:#ffd54f;                                   /* Warna emas */
  --ink:#222;                                       /* Warna teks utama */
  --brown:#4b3f36;                                  /* Warna coklat */
  --bg:#fafbfc;                                     /* Background halaman */
  --line:#eceff3;                                   /* Warna garis */
  --faq-active:#fff7dd;                             /* FAQ aktif */
}

*{                                                  /* Selector global */
  font-family:                                      /* Font utama */
    "Poppins",                                     /* Font Poppins */
    system-ui,                                     /* System font */
    -apple-system,                                 /* Apple system */
    Segoe UI,                                      /* Windows UI */
    Roboto,                                        /* Roboto */
    Arial,                                         /* Arial */
    sans-serif !important;                          /* Sans-serif */
  box-sizing:border-box;                            /* Box sizing */
}

html,                                               /* HTML */
body{                                               /* Body */
  background:var(--bg);                             /* Background */
  color:var(--ink);                                 /* Warna teks */
  margin:0;                                         /* Hilangkan margin */
}

/* TOPBAR (samakan dengan settings) */
.topbar{                                            /* Topbar */
  position:sticky;                                  /* Sticky */
  top:0;                                            /* Posisi atas */
  z-index:20;                                       /* Layer */
  background:#fff;                                  /* Background */
  border-bottom:1px solid rgba(0,0,0,.04);          /* Border bawah */
  padding-right:40px;                               /* Ruang kanan ikon */
}

.topbar .inner{                                     /* Inner topbar */
  max-width:1200px;                                 /* Lebar maksimal */
  margin:0 auto;                                    /* Tengah */
  padding:12px 24px;                                /* Padding */
  min-height:52px;                                  /* Tinggi minimum */
  display:flex;                                     /* Flex */
  align-items:center;                               /* Tengah vertikal */
  gap:8px;                                          /* Jarak */
}

/* Back link — beri jarak ke input search/teks */
.back-link{                                         /* Tombol kembali */
  display:inline-flex;                              /* Inline flex */
  align-items:center;                               /* Tengah vertikal */
  gap:10px;                                         /* Jarak icon-teks */
  color:var(--brown);                               /* Warna teks */
  text-decoration:none;                             /* Tanpa underline */
  font-weight:600;                                  /* Tebal */
  font-size:16px;                                   /* Ukuran font */
  line-height:1.3;                                  /* Tinggi baris */
  padding-right:50px;                               /* Geser kanan */
}

.back-link i{                                       /* Icon back */
  font-size:18px !important;                        /* Ukuran icon */
  width:18px;                                       /* Lebar */
  height:18px;                                      /* Tinggi */
  line-height:1;                                    /* Line height */
  display:inline-flex;                              /* Inline flex */
  align-items:center;                               /* Tengah vertikal */
  justify-content:center;                           /* Tengah horizontal */
}

/* Responsif tetap seperti semula */
@media (max-width:576px){                           /* Mobile kecil */
  .topbar .inner{                                  /* Inner topbar */
    max-width:100%;                                /* Lebar penuh */
    padding:30px 30px;                             /* Padding */
  }
}

/* LAYOUT */
.wrap{                                              /* Wrapper utama */
  max-width:1200px;                                 /* Lebar maksimal */
  margin:10px auto 10px;                            /* Margin */
  padding:0 16px;                                   /* Padding */
}

/* FAQ LIST (sama seperti karyawan) */
.faq-list{                                          /* List FAQ */
  background:#fff;                                  /* Background */
  border-radius:0;                                  /* Tanpa radius */
  overflow:hidden;                                  /* Hidden overflow */
  box-shadow:0 1px 3px rgba(0,0,0,.02);              /* Shadow */
}

.faq-item{                                          /* Item FAQ */
  border-bottom:1px solid var(--line);               /* Border bawah */
  background:#fff;                                  /* Background */
  transition:background .15s ease;                  /* Transisi */
}

.faq-item:last-child{ border-bottom:0; }            /* Item terakhir */

.faq-q{                                             /* Pertanyaan FAQ */
  width:100%;                                       /* Lebar penuh */
  text-align:left;                                  /* Rata kiri */
  background:#fff;                                  /* Background */
  border:0;                                         /* Tanpa border */
  padding:12px 12px;                                /* Padding */
  display:flex;                                     /* Flex */
  align-items:center;                               /* Tengah vertikal */
  justify-content:space-between;                    /* Spasi */
  gap:16px;                                         /* Jarak */
  font-weight:500;                                  /* Tebal */
  font-size:.95rem;                                 /* Ukuran font */
  color:var(--ink);                                 /* Warna teks */
  cursor:pointer;                                   /* Pointer */
  transition:background .2s ease;                   /* Transisi */
}

.faq-q:hover{ background:#fffbea; }                 /* Hover FAQ */

.faq-body{                                          /* Isi FAQ */
  display:none;                                     /* Sembunyi */
  background:#fff;                                  /* Background */
  padding:0 12px 12px;                              /* Padding */
  font-size:.9rem;                                  /* Ukuran font */
  line-height:1.6;                                  /* Tinggi baris */
  color:#374151;                                    /* Warna teks */
  transition:all .2s ease;                          /* Transisi */
}

.faq-item.open .faq-body{                           /* FAQ terbuka */
  display:block;                                    /* Tampil */
  background:#fff;                                  /* Background */
}

.faq-item.open .chev{                               /* Chevron aktif */
  transform:rotate(180deg);                         /* Rotasi */
}

.chev{                                              /* Chevron */
  transition:transform .15s ease;                   /* Transisi */
}

/* CONTACT LIST (disalin) */
.contact{                                           /* Kontak */
  margin-top:14px;                                  /* Margin atas */
  background:#fff;                                  /* Background */
  border-radius:14px;                               /* Radius */
  padding:4px 10px;                                 /* Padding */
  box-shadow:0 1px 3px rgba(0,0,0,.02);              /* Shadow */
}

.c-row{                                             /* Baris kontak */
  display:grid;                                     /* Grid */
  grid-template-columns:24px 1fr 16px;              /* Kolom */
  align-items:center;                               /* Tengah vertikal */
  gap:10px;                                         /* Jarak */
  padding:10px 2px;                                 /* Padding */
  text-decoration:none;                             /* Tanpa underline */
  color:var(--ink);                                 /* Warna teks */
  font-weight:500;                                  /* Tebal */
  font-size:.9rem;                                  /* Ukuran font */
}

.c-row .bi{                                         /* Icon kontak */
  font-size:1.05rem;                                /* Ukuran */
  color:var(--brown);                               /* Warna */
}

.c-row .chev{                                       /* Chevron kontak */
  color:#a3a3a3;                                    /* Warna */
}

.c-row.static{                                      /* Kontak statis */
  grid-template-columns:24px 1fr;                   /* Dua kolom */
}

@media (max-width:576px){                           /* Mobile */
  .topbar .inner,                                  /* Inner topbar */
  .wrap{                                           /* Wrapper */
    max-width:100%;                                /* Lebar penuh */
    padding:10px 10px;                             /* Padding */
  }

  .wrap{ margin:8px auto 28px; }                   /* Margin wrapper */

  .faq-q{ font-size:.88rem; }                      /* Font FAQ */

  .faq-body{ font-size:.86rem; }                   /* Font isi FAQ */

  .c-row{                                          /* Baris kontak */
    font-size:.88rem;                              /* Ukuran font */
    padding:8px 0;                                 /* Padding */
  }
}

/* ===== Samakan lebar isi dengan padding topbar seperti di settings ===== */
@media (min-width:992px){                          /* Desktop besar */
  .wrap{                                           /* Wrapper */
    max-width:1400px;                              /* Lebar besar */
    padding-left:30px;                             /* Padding kiri */
    padding-right:30px;                            /* Padding kanan */
  }
}

/* ====== kode responsif ipad rotasi ====== */
@media screen                                     /* Media screen */
  and (orientation:landscape)                     /* Landscape */
  and (min-width:1024px)                          /* Min width */
  and (max-width:1366px){                         /* Max width */

  .topbar .inner,                                 /* Inner topbar */
  .page-inner,                                    /* Page inner */
  .settings-inner{                                /* Settings inner */
    max-width:100%;                               /* Lebar penuh */
    padding-left:max(30px, env(safe-area-inset-left)); /* Safe area */
    padding-right:24px;                           /* Padding kanan */
  }
}

@media screen and (max-width:768px){              /* Mobile besar */
  .topbar{                                        /* Topbar */
    padding-left:12px;                            /* Padding kiri */
    padding-right:12px;                           /* Padding kanan */
  }
}
  </style>
</head>
<body>

  <!-- Header -->
  <div class="topbar">
    <div class="inner">
      <a class="back-link" href="<?= BASE_URL ?>/public/admin/index.php" aria-label="Kembali">
        <i class="bi bi-arrow-left"></i><span>Kembali</span>
      </a>
    </div>
  </div>

  <main class="wrap">

   <div class="faq-list" id="faq">
  <!-- 1 -->
  <div class="faq-item open">
    <button class="faq-q" type="button">
      <span>Alur verifikasi & rekonsiliasi pembayaran yang benar?</span>
      <i class="bi bi-chevron-down chev"></i>
    </button>
    <div class="faq-body">
      Buka <strong>Orders → Payments</strong> lalu cek transaksi <em>Pending</em>.<br>
      • Cocokkan <strong>nominal</strong>, <strong>metode</strong>, dan <strong>waktu</strong> dengan mutasi bank/Midtrans.<br>
      • Jika valid, klik <strong>Mark as Paid</strong>; jika tidak, pilih <strong>Fail/Refund</strong> dengan catatan.<br>
      • Untuk rekonsiliasi harian, unduh <strong>Export CSV</strong> dan cocokkan dengan laporan bank (settlement).  
      Tip: pastikan jam perangkat & zona waktu toko sudah sesuai agar grafik harian sinkron.
    </div>
  </div>

  <!-- 2 -->
  <div class="faq-item">
    <button class="faq-q" type="button">
      <span>Grafik revenue 7 hari tidak muncul penuh / tanggal kosong.</span>
      <i class="bi bi-chevron-down chev"></i>
    </button>
    <div class="faq-body">
      • Periksa <strong>rentang tanggal</strong> yang dipilih (Today / 7 days / 30 days / Custom).<br>
      • Pastikan <strong>zona waktu</strong> toko/perangkat benar, lalu <strong>refresh</strong> halaman Finance.<br>
      • Transaksi <em>failed/void/refunded</em> tidak dihitung revenue—cek ringkasan status bila angkanya janggal.
    </div>
  </div>

  <!-- 3 -->
<div class="faq-item">
  <button class="faq-q" type="button">
    <span>Bisakah admin mengubah pajak, biaya layanan, atau pembulatan?</span>
    <i class="bi bi-chevron-down chev"></i>
  </button>
  <div class="faq-body">
    Pengaturan pajak dan biaya layanan bersifat tetap dan ditentukan oleh kebijakan toko.  
    Admin tidak dapat mengubahnya langsung melalui tampilan sistem.  
    Jika perlu penyesuaian tarif pajak, pembulatan harga, atau biaya layanan,  
    koordinasikan dengan <strong>pihak developer</strong> agar disesuaikan secara resmi.  
    Semua perubahan tersebut akan otomatis tercermin pada laporan keuangan dan struk setelah disetujui.
  </div>
</div>


  <!-- 4 -->
  <div class="faq-item">
    <button class="faq-q" type="button">
      <span>Status pesanan di Orders berbeda dengan status pembayaran di Payments.</span>
      <i class="bi bi-chevron-down chev"></i>
    </button>
    <div class="faq-body">
      Buka pesanan terkait lalu gunakan <strong>Sinkronkan Status</strong> untuk menyamakan <em>order</em> & <em>payment</em>.  
      Jika integrasi pihak ketiga sempat bermasalah, jalankan <strong>Recheck Payment</strong> pada transaksi tersebut.
    </div>
  </div>
   
  <!-- 5 -->
<div class="faq-item">
  <button class="faq-q" type="button">
    <span>Bagaimana cara mengelola menu di Catalog?</span>
    <i class="bi bi-chevron-down chev"></i>
  </button>
  <div class="faq-body">
    Buka <strong>Catalog</strong> untuk menambahkan, mengubah, atau menghapus menu.<br>
    • Gunakan tombol <strong>Add Menu</strong> untuk menambah item baru lengkap dengan harga dan kategori.<br>
    • Klik <strong>Edit</strong> jika ingin memperbarui nama, foto produk, atau harga menu.<br>
    • Pilih <strong>Hapus</strong> untuk menghapus menu yang sudah tidak tersedia.<br><br>
    Semua perubahan akan langsung terlihat oleh karyawan dan customer setelah disimpan.
  </div>
</div>


  <!-- 6 -->
  <div class="faq-item">
    <button class="faq-q" type="button">
      <span>Membuat, mengubah, atau menonaktifkan akun pengguna & role</span>
      <i class="bi bi-chevron-down chev"></i>
    </button>
    <div class="faq-body">
      Masuk <strong>Users</strong> → <em>Add User</em> untuk akun baru (role: admin/karyawan/customer).  
      Batasi akses dengan <strong>Permissions</strong> per modul bila tersedia.  
      Nonaktifkan user via <strong>Deactivate</strong>; data historis tetap tersimpan.
    </div>
  </div>

  <!-- 7 -->
  <div class="faq-item">
    <button class="faq-q" type="button">
      <span>Refund/void transaksi & dampaknya ke laporan</span>
      <i class="bi bi-chevron-down chev"></i>
    </button>
    <div class="faq-body">
      Dari <strong>Orders → Payments</strong>, pilih transaksi → <strong>Refund</strong> atau <strong>Void</strong> sesuai kebijakan.  
      Revenue periode terkait akan menyesuaikan; pesanan ditandai <em>cancelled/failed</em> sesuai alur.
    </div>
  </div>

  <!-- 8 -->
  <div class="faq-item">
    <button class="faq-q" type="button">
      <span>Ekspor laporan (CSV/Excel) berdasarkan periode & filter</span>
      <i class="bi bi-chevron-down chev"></i>
    </button>
    <div class="faq-body">
      Di <strong>Finance</strong>, pilih rentang tanggal (Today/7 days/30 days/Custom), lalu klik <strong>Export CSV</strong>.  
      Gunakan filter <em>status</em> untuk memisahkan <em>paid/failed/refunded</em> saat analisis di spreadsheet.
    </div>
  </div>

  <!-- 9 -->
  <div class="faq-item">
    <button class="faq-q" type="button">
      <span>Kirim pengumuman/flash sale via notifikasi</span>
      <i class="bi bi-chevron-down chev"></i>
    </button>
    <div class="faq-body">
      Buka <strong>Kirim Notifikasi</strong>, isi judul & konten, pilih target (<em>semua</em>/role/segmen), lalu <strong>Kirim</strong>.  
      Lihat riwayat & metrik baca di <strong>Notifications</strong>.
    </div>
  </div>

  <!-- 10 -->
  <div class="faq-item">
    <button class="faq-q" type="button">
      <span>Mengelola harga: langsung atau terjadwal?</span>
      <i class="bi bi-chevron-down chev"></i>
    </button>
    <div class="faq-body">
      Di <strong>Catalog → Edit Menu</strong> pilih <em>Update Now</em> untuk langsung, atau <em>Schedule</em> untuk aktif pada waktu tertentu.  
      Perubahan terjadwal terekam di audit log.
    </div>
  </div>

 <!-- 11 -->
<div class="faq-item">
  <button class="faq-q" type="button">
    <span>Apa fungsi menu Settings?</span>
    <i class="bi bi-chevron-down chev"></i>
  </button>
  <div class="faq-body">
    Menu <strong>Settings</strong> digunakan untuk mengelola profil admin, seperti memperbarui nama, foto, email, kata sandi, dan nomor kontak.  
    Fitur ini berfokus pada pengaturan akun dan identitas pengguna.
  </div>
</div>

    <!-- Kontak -->
    <div class="contact">
      <a class="c-row" href="https://wa.me/628782302337" target="_blank" rel="noopener" aria-label="WhatsApp Caffora">
        <i class="bi bi-whatsapp"></i>
        <span>WhatsApp</span>
        <i class="bi bi-chevron-right chev"></i>
      </a>

      <a class="c-row" href="mailto:cafforaproject@gmail.com" aria-label="Kirim Email ke Caffora">
        <i class="bi bi-envelope"></i>
        <span>Email</span>
        <i class="bi bi-chevron-right chev"></i>
      </a>

      <a class="c-row" href="https://maps.app.goo.gl/Fznq9KLusdNXH2RE7" target="_blank" rel="noopener" aria-label="Buka lokasi di Google Maps">
        <i class="bi bi-geo-alt"></i>
        <span>Jl. Kopi No. 123, Purwokerto</span>
        <i class="bi bi-chevron-right chev"></i>
      </a>

      <div class="c-row static" role="group" aria-label="Jam operasional">
        <i class="bi bi-clock"></i>
        <span>08.00–22.00</span>
      </div>
    </div>
  </main>
<!-- Load Bootstrap JS bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
  // Mengambil semua elemen FAQ item
  const items = document.querySelectorAll('.faq-item');

  // Loop setiap item FAQ
  items.forEach(item => {

    // Ambil tombol pertanyaan FAQ
    const btn = item.querySelector('.faq-q');

    // Tambahkan event klik pada tombol
    btn.addEventListener('click', () => {

      // Tutup semua FAQ lain selain yang diklik
      items.forEach(i => {
        // Jika bukan item yang aktif, hapus class open
        if (i !== item) i.classList.remove('open');
      });

      // Toggle buka/tutup FAQ yang diklik
      item.classList.toggle('open');
    });
  });
</script>

<!-- Akhir body -->
</body>

<!-- Akhir dokumen HTML -->
</html>
