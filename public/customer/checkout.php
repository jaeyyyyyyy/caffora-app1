<?php
// public/customer/checkout.php
// Halaman checkout untuk customer (wajib login sebagai customer)

declare(strict_types=1);

require_once __DIR__ . '/../../backend/auth_guard.php';
require_login(['customer']); // pastikan hanya customer yang bisa akses

// Nama default di input diambil dari sesi login
$nameDefault = $_SESSION['user_name'] ?? '';
$userId      = (int)($_SESSION['user_id'] ?? 0);
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Checkout â€” Caffora</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap & Icons -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
  >
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    rel="stylesheet"
  >

   <style>                                                /* Awal blok CSS internal */

      :root {                                              /* Root: tempat definisi variabel CSS global */
        --yellow:       #FFD54F;                           /* Warna kuning utama brand */
        --camel:        #DAA85C;                           /* Warna camel / coklat muda */
        --brown:        #4B3F36;                           /* Warna coklat tua untuk teks utama */
        --line:         #e5e7eb;                           /* Warna garis pemisah lembut */
        --bg:           #fffdf8;                           /* Warna background krem lembut */
        --gold:         #FFD54F;                           /* Warna emas (alias dari yellow) */
        --gold-200:     #FFE883;                           /* Warna emas lebih terang */
        --gold-soft:    #F6D472;                           /* Warna emas lembut untuk fokus/border */
        --input-border: #E8E2DA;                           /* Warna border input form */
      }                                                     /* Akhir deklarasi variabel di :root */

      /* Global */
      /* Komentar penanda bagian styling global */
      * {                                                  /* Selector global untuk semua elemen */
        font-family: Poppins, system-ui, -apple-system,
                     "Segoe UI", Roboto, Arial, sans-serif;/* Rantai fallback font */
        box-sizing: border-box;                            /* Gunakan border-box agar padding & border
                                                              masuk hitungan width/height */
      }                                                     /* Akhir aturan untuk selector * */

      body {                                               /* Styling untuk elemen body */
        background:  var(--bg);                            /* Gunakan background krem dari variabel --bg */
        color:       var(--brown);                         /* Warna teks utama coklat tua */
        overflow-x:  hidden;                               /* Nonaktifkan scroll horizontal */
        margin:      0;                                    /* Hilangkan margin default browser */
      }                                                     /* Akhir aturan body */

      /* ====== TOPBAR ====== */
      /* Komentar penanda bagian topbar navigasi */
      .topbar {                                            /* Container bar bagian atas halaman */
        background:    #fff;                               /* Latar belakang putih */
        border-bottom: 1px solid rgba(0,0,0,.05);          /* Garis bawah tipis sebagai pemisah */
        position:      sticky;                             /* Topbar menempel di bagian atas saat scroll */
        top:           0;                                  /* Posisi menempel di titik 0 dari atas viewport */
        z-index:       20;                                 /* Z-index cukup tinggi agar berada di atas konten */
      }                                                     /* Akhir aturan .topbar */

      .topbar-inner {                                      /* Wrapper isi topbar */
        max-width:   1200px;                               /* Lebar maksimal konten topbar */
        margin:      0 auto;                               /* Posisikan di tengah secara horizontal */
        padding:     12px 24px;                            /* Padding vertikal 12px dan horizontal 24px */
        min-height:  52px;                                 /* Tinggi minimal topbar */
        display:     flex;                                 /* Gunakan flex layout */
        align-items: center;                               /* Vertikal center konten di dalam topbar */
        gap:         10px;                                 /* Jarak antar elemen di dalam topbar */
      }                                                     /* Akhir aturan .topbar-inner */

      .back-link {                                         /* Styling untuk link "Kembali" */
        display:        inline-flex;                       /* Tampilkan sebagai inline-flex */
        align-items:    center;                            /* Vertikal center ikon dan teks */
        gap:            10px;                              /* Jarak antara ikon dan teks */
        color:          var(--brown);                      /* Warna teks coklat brand */
        text-decoration:none;                              /* Hilangkan garis bawah pada link */
        border:         0;                                 /* Tanpa border */
        background:     transparent;                       /* Background transparan (seperti teks biasa) */
        padding:        0;                                 /* Tanpa padding ekstra */
      }                                                     /* Akhir aturan .back-link */

      .back-link span {                                    /* Teks di dalam link kembali */
        font-family: system-ui, -apple-system,
                     "Segoe UI", Roboto, Arial, sans-serif !important;
                                                          /* Paksa pakai system font sederhana */
        font-size:   1rem;                                 /* Ukuran 1rem (kurang lebih 16px) */
        font-weight: 600;                                  /* Teks agak tebal */
        line-height: 1.3;                                  /* Tinggi baris 1.3 untuk keterbacaan */
      }                                                     /* Akhir aturan .back-link span */

      .back-link .bi {                                     /* Ikon panah kiri di link kembali */
        width:          18px;                              /* Lebar area ikon 18px */
        height:         18px;                              /* Tinggi area ikon 18px */
        display:        inline-flex;                       /* Gunakan inline-flex untuk center isi */
        align-items:    center;                            /* Vertikal center ikon di area */
        justify-content:center;                            /* Horizontal center ikon di area */
        font-size:      18px !important;                   /* Ukuran font ikon 18px (override default) */
        line-height:    18px !important;                   /* Line-height disamakan dengan tinggi area */
      }                                                     /* Akhir aturan .back-link .bi */

      /* ====== LAYOUT CONTENT ====== */
      /* Komentar penanda bagian layout konten utama */
      .page {                                              /* Container utama untuk konten checkout */
        max-width: 1200px;                                 /* Lebar maksimum untuk konten */
        margin:    0 auto 32px;                            /* Tanpa margin atas, margin bawah 32px,
                                                              center horizontal */
        padding:   0 24px;                                 /* Padding kiri-kanan 24px */
      }                                                     /* Akhir aturan .page */

      /* Ringkasan item */
      /* Komentar penanda area ringkasan item di atas form */
      .item-row {                                          /* Satu baris ringkasan item di summary */
        display:        flex;                              /* Gunakan flex layout horizontal */
        align-items:    center;                            /* Vertikal center isi baris */
        justify-content:space-between;                     /* Ruang di antara sisi kiri dan kanan */
        gap:           12px;                               /* Jarak antara kolom kiri dan kanan */
        padding:       14px 0;                             /* Padding atas-bawah 14px */
        border-bottom: 1px solid rgba(0,0,0,.06);          /* Garis pemisah tipis antar item */
      }                                                     /* Akhir aturan .item-row */

      .left-info {                                         /* Kolom kiri ringkasan item (gambar + nama + qty) */
        display:     flex;                                 /* Flex row untuk gambar dan teks */
        gap:         12px;                                 /* Jarak antara gambar dan teks */
        align-items: center;                               /* Vertikal center antara gambar dan teks */
        flex:        1;                                    /* Ambil ruang sisa yang tersedia */
        min-width:   0;                                    /* Izinkan konten shrink dengan ellipsis */
      }                                                     /* Akhir aturan .left-info */

      .thumb {                                             /* Gambar thumbnail item */
        width:         64px;                               /* Lebar gambar 64px */
        height:        64px;                               /* Tinggi gambar 64px */
        border-radius: 12px;                               /* Sudut membulat untuk gambar */
        object-fit:    cover;                              /* Skala gambar agar menutupi area tanpa distorsi */
        background:    #f3f3f3;                            /* Warna latar saat gambar gagal load */
        flex-shrink:   0;                                  /* Jangan mengecil saat ruang sempit */
      }                                                     /* Akhir aturan .thumb */

      .name {                                              /* Teks nama item di ringkasan */
        font-weight: 600;                                  /* Teks tebal */
        font-size:   1rem;                                 /* Ukuran teks 1rem */
        line-height: 1.3;                                  /* Tinggi baris 1.3 */
        color:       #2b2b2b;                              /* Warna teks abu gelap */
        white-space: nowrap;                               /* Jangan bungkus ke baris baru */
        overflow:    hidden;                               /* Sembunyikan teks yang melampaui lebar */
        text-overflow:ellipsis;                            /* Tampilkan "..." jika teks terlalu panjang */
      }                                                     /* Akhir aturan .name */

      .line-right {                                        /* Kolom kanan untuk nominal harga per item */
        flex-shrink: 0;                                    /* Jangan menyusut saat ruang sempit */
        font-weight: 600;                                  /* Teks tebal */
        font-size:   .95rem;                               /* Ukuran teks sedikit lebih kecil dari 1rem */
        color:       var(--brown);                         /* Warna teks coklat */
        text-align:  right;                                /* Teks rata kanan */
        min-width:   90px;                                 /* Lebar minimum kolom nominal */
      }                                                     /* Akhir aturan .line-right */

      /* Total / subtotal */
      /* Komentar penanda blok subtotal dan total */
      .tot-block {                                         /* Wrapper untuk subtotal, pajak, dan total */
        margin-top:  10px;                                 /* Jarak atas dari list item */
        border-top:  2px dashed rgba(0,0,0,.05);           /* Garis putus-putus di bagian atas blok */
        padding-top: 10px;                                 /* Padding atas di dalam blok total */
      }                                                     /* Akhir aturan .tot-block */

      .tot-line {                                          /* Satu baris untuk subtotal atau pajak */
        display:        flex;                              /* Flex layout horizontal */
        justify-content:space-between;                     /* Label di kiri, angka di kanan */
        align-items:    center;                            /* Vertikal center */
        margin-bottom:  6px;                               /* Jarak bawah antar baris */
        font-size:      .93rem;                            /* Ukuran teks 0.93rem */
        color:          #4b3f36;                           /* Warna coklat medium */
      }                                                     /* Akhir aturan .tot-line */

      .tot-line strong {                                   /* Elemen teks tebal di dalam baris subtotal/pajak */
        font-weight: 600;                                  /* Ketebalan font 600 */
      }                                                     /* Akhir aturan .tot-line strong */

      .tot-grand {                                         /* Baris untuk total akhir (grand total) */
        display:        flex;                              /* Flex layout horizontal */
        justify-content:space-between;                     /* Label dan nilai berada di sisi berlawanan */
        align-items:    center;                            /* Vertikal center */
        margin-top:     6px;                               /* Jarak atas dari baris sebelumnya */
        font-weight:    700;                               /* Teks lebih tebal (700) */
        font-size:      1rem;                              /* Ukuran teks 1rem */
        color:          #2b2b2b;                           /* Warna abu gelap */
      }                                                     /* Akhir aturan .tot-grand */

      /* ====== FORM INPUT ====== */
      /* Komentar penanda bagian styling input form */
      .form-label {                                        /* Label untuk setiap field form */
        font-weight: 600;                                  /* Teks label cukup tebal */
        font-size:   1rem;                                 /* Ukuran teks label 1rem */
        line-height: 1.3;                                  /* Tinggi baris label 1.3 */
        color:       #2b2b2b;                              /* Warna teks label abu gelap */
        margin-bottom:6px;                                 /* Jarak bawah label ke input */
      }                                                     /* Akhir aturan .form-label */

      .form-control {                                      /* Styling untuk input teks Bootstrap yang dikustom */
        width:        100%;                                /* Input mengisi lebar container */
        max-width:    100%;                                /* Jangan melebihi lebar container */
        border-radius:14px !important;                     /* Sudut input dibulatkan 14px (override Bootstrap) */
        padding:      8px 14px;                            /* Padding vertikal 8px dan horizontal 14px */
        font-size:    .95rem;                              /* Ukuran teks input 0.95rem */
        line-height:  1.3;                                 /* Tinggi baris teks dalam input */
        border:       1px solid var(--input-border);       /* Border sesuai warna variabel input-border */
        background-color:#fff;                             /* Background putih */
        box-shadow:   none;                                /* Hilangkan bayangan bawaan Bootstrap */
        transition:   border-color .12s ease;              /* Animasi halus saat warna border berubah */
      }                                                     /* Akhir aturan .form-control */

      .form-control:focus {                                /* State saat input dalam kondisi fokus */
        border-color: var(--gold-soft) !important;         /* Ubah warna border menjadi emas lembut */
        box-shadow:  none !important;                      /* Hilangkan shadow fokus default Bootstrap */
      }                                                     /* Akhir aturan .form-control:focus */

      /* Custom select (Tipe layanan & Pembayaran) */
      /* Komentar penanda styling custom select */
      .cf-select {                                         /* Wrapper custom select */
        position: relative;                                /* Agar dropdown list bisa ditempatkan absolute
                                                              di dalamnya */
        width:    100%;                                    /* Lebar penuh container */
      }                                                     /* Akhir aturan .cf-select */

      .cf-select__trigger {                                /* Tombol pemicu dropdown custom select */
        width:         100%;                               /* Lebar penuh */
        background:    #fff;                               /* Background putih */
        border:        1px solid var(--input-border);      /* Border sama dengan input */
        border-radius: 14px;                               /* Sudut membulat 14px */
        padding:       8px 38px 8px 14px;                  /* Padding kiri 14px dan ruang kanan untuk ikon */
        display:       flex;                               /* Flex layout */
        align-items:   center;                             /* Vertikal center konten trigger */
        justify-content:space-between;                     /* Label kiri dan ikon kanan */
        gap:          12px;                                /* Jarak antara teks dan ikon */
        cursor:       pointer;                             /* Tampilkan pointer saat hover */
        transition:   border-color .12s ease;              /* Transisi border saat fokus/open */
      }                                                     /* Akhir aturan .cf-select__trigger */

      .cf-select__trigger:focus-visible,
      .cf-select.is-open .cf-select__trigger {             /* State saat trigger fokus keyboard atau dropdown
                                                              terbuka */
        border-color: var(--gold-soft);                    /* Border berubah ke warna emas lembut */
        outline:     none;                                 /* Hilangkan outline bawaan browser */
      }                                                     /* Akhir aturan fokus/open trigger custom select */

      .cf-select__text {                                   /* Teks yang tampil di trigger custom select */
        font-size:   .95rem;                               /* Ukuran teks 0.95rem */
        color:       #2b2b2b;                              /* Warna teks abu gelap */
        white-space: nowrap;                               /* Satu baris saja */
        overflow:    hidden;                               /* Sembunyikan teks berlebih */
        text-overflow:ellipsis;                            /* Tambahkan ellipsis "..." jika kepanjangan */
      }                                                     /* Akhir aturan .cf-select__text */

      .cf-select__icon {                                   /* Ikon panah di sisi kanan trigger */
        flex:       0 0 auto;                              /* Jangan melebar atau mengecil */
        color:      var(--brown);                          /* Warna ikon coklat */
        font-size:  .9rem;                                 /* Ukuran ikon sedikit lebih kecil dari teks */
      }                                                     /* Akhir aturan .cf-select__icon */

      .cf-select__list {                                   /* Dropdown list opsi untuk custom select */
        position:   absolute;                              /* Diposisikan relatif terhadap .cf-select */
        left:       0;                                     /* Mulai dari sisi kiri trigger */
        top:        calc(100% + 6px);                      /* Muncul sedikit di bawah trigger */
        width:      100%;                                  /* Lebar sama dengan trigger */
        background: #fff;                                  /* Background putih */
        border:     1px solid rgba(0,0,0,.02);             /* Border sangat tipis */
        border-radius:14px;                                /* Sudut membulat */
        box-shadow: 0 16px 30px rgba(0,0,0,.09);           /* Bayangan halus di bawah dropdown */
        overflow:   hidden;                                /* Sembunyikan overflow */
        z-index:    40;                                    /* Di atas elemen lain di sekitar */
        display:    none;                                  /* Tersembunyi secara default */
        max-height: 260px;                                 /* Tinggi maksimum dropdown */
        overflow-y: auto;                                  /* Scroll vertical jika konten melebihi tinggi */
      }                                                     /* Akhir aturan .cf-select__list */

      .cf-select.is-open .cf-select__list {                /* Saat wrapper memiliki class is-open */
        display: block;                                    /* Tampilkan daftar opsi */
      }                                                     /* Akhir aturan state open dropdown */

      .cf-select__option {                                 /* Satu baris opsi dalam dropdown */
        padding:  9px 14px;                                /* Padding dalam opsi */
        font-size:.9rem;                                   /* Ukuran teks opsi 0.9rem */
        color:    #413731;                                 /* Warna teks coklat gelap */
        cursor:   pointer;                                 /* Tangan (pointer) saat hover */
        background:#fff;                                   /* Background putih */
      }                                                     /* Akhir aturan .cf-select__option */

      .cf-select__option:hover {                           /* State saat kursor berada di atas opsi */
        background: #FFF2C9;                               /* Ubah latar belakang menjadi kuning lembut */
      }                                                     /* Akhir aturan hover untuk opsi */

      .cf-select__option.is-active {                       /* Opsi yang sedang terpilih */
        background:  #FFEB9B;                              /* Warna kuning lebih pekat untuk opsi aktif */
        font-weight: 600;                                  /* Teks opsi aktif dibuat lebih tebal */
      }                                                     /* Akhir aturan opsi aktif */

      /* Tombol utama konfirmasi */
      /* Komentar penanda styling tombol submit */
      .btn-primary-cf {                                    /* Tombol "Konfirmasi Pesanan" */
        background-color: var(--gold);                     /* Warna background kuning emas */
        color:            var(--brown) !important;         /* Warna teks coklat, override dengan !important */
        border:           0;                               /* Tanpa border */
        border-radius:    14px;                            /* Sudut tombol membulat 14px */
        font-family:      Arial, Helvetica, sans-serif;    /* Font tombol menggunakan Arial/Helvetica */
        font-weight:      600;                             /* Teks tombol agak tebal */
        font-size:        .88rem;                          /* Ukuran teks sedikit lebih kecil dari 1rem */
        padding:          10px 18px;                       /* Padding vertikal 10px dan horizontal 18px */
        display:          inline-flex;                     /* Tampilkan sebagai inline-flex */
        align-items:      center;                          /* Vertikal center konten tombol */
        justify-content:  center;                          /* Horizontal center konten tombol */
        gap:              8px;                             /* Jarak jika ada ikon di dalam tombol */
        white-space:      nowrap;                          /* Teks tidak dibungkus ke baris baru */
        box-shadow:       none;                            /* Hilangkan shadow default */
        cursor:           pointer;                         /* Pointer menandakan tombol bisa diklik */
      }                                                     /* Akhir aturan .btn-primary-cf */

      /* GRID FORM RESPONSIVE */
      /* Komentar penanda bagian grid form responsif */
      .form-grid {                                         /* Grid pembungkus field-field form */
        display:        flex;                              /* Default mobile: gunakan flex */
        flex-direction: column;                            /* Susunan field bertumpuk secara vertikal */
        gap:            18px;                              /* Jarak antar blok field di mobile */
      }                                                     /* Akhir aturan .form-grid */

      .field-block {                                       /* Wrapper umum tiap blok field
                                                              (nama, tipe, meja, pembayaran) */
        width: 100%;                                       /* Blok field mengisi lebar parent */
      }                                                     /* Akhir aturan .field-block */

      @media (min-width: 992px) {                          /* Breakpoint untuk tampilan desktop (>= 992px) */

        .form-grid {                                       /* Atur ulang layout form di desktop */
          display:              grid;                      /* Gunakan CSS grid */
          grid-template-columns:1fr 1fr;                   /* Dua kolom dengan lebar sama */
          column-gap:           18px;                      /* Jarak horizontal antar kolom */
          row-gap:              8px;                       /* Jarak vertikal antar baris field */
          align-items:          flex-start;                /* Field disejajarkan ke atas */
        }                                                   /* Akhir aturan .form-grid di desktop */

        .field-name    { grid-column: 1; grid-row: 1; }    /* Field nama di kolom 1 baris 1 */
        .field-service { grid-column: 2; grid-row: 1; }    /* Field tipe layanan di kolom 2 baris 1 */
        .field-payment { grid-column: 1; grid-row: 2; }    /* Field metode pembayaran di kolom 1 baris 2 */
        .field-table   { grid-column: 2; grid-row: 2; }    /* Field nomor meja di kolom 2 baris 2 */

        .page {                                            /* Penyesuaian kontainer halaman di desktop */
          padding-bottom: 80px;                            /* Tambah padding bawah agar konten tidak
                                                              menempel tepi */
        }                                                   /* Akhir aturan .page di desktop */

        #checkoutForm {                                    /* Penyesuaian form checkout di desktop */
          padding-bottom: 60px;                            /* Tambah ruang antara form dan bawah halaman */
        }                                                   /* Akhir aturan #checkoutForm di desktop */

      }                                                     /* Akhir blok media query min-width 992px */

      @media (max-width: 600px) {                          /* Breakpoint untuk layar kecil (<= 600px) */

        .topbar-inner,
        .page {                                            /* Pengaturan topbar-inner dan kontainer page
                                                              di mobile kecil */
          max-width: 100%;                                 /* Lebar penuh layar */
          padding:   12px 16px;                            /* Padding sedikit lebih sempit */
        }                                                   /* Akhir aturan untuk .topbar-inner dan .page
                                                              di mobile */

        .page {                                            /* Kontainer halaman di mobile kecil */
          margin: 0 auto 90px;                             /* Margin bawah lebih besar untuk ruang ekstra */
        }                                                   /* Akhir aturan .page di mobile kecil */

        .form-grid {                                       /* Grid form khusus di layar kecil */
          gap: 5px;                                        /* Jarak antar field dibuat sedikit lebih rapat */
        }                                                   /* Akhir aturan .form-grid di mobile kecil */

      }                                                     /* Akhir blok media query max-width 600px */

      /* ====== CUSTOM ALERT CAFFORA ====== */
      /* Komentar penanda bagian alert custom */
      .cf-alert[hidden] {                                  /* State ketika elemen alert diberi atribut hidden */
        display: none;                                     /* Sembunyikan elemen sepenuhnya */
      }                                                     /* Akhir aturan .cf-alert[hidden] */

      .cf-alert {                                          /* Wrapper overlay untuk popup alert custom */
        position:       fixed;                             /* Tetap di posisi layar saat scroll */
        inset:          0;                                 /* Menutupi seluruh viewport (top/right/bottom/left=0) */
        z-index:        999;                               /* Di atas hampir semua elemen lain */
        display:        flex;                              /* Flex layout untuk center konten */
        align-items:    center;                            /* Vertikal center box alert */
        justify-content:center;                            /* Horizontal center box alert */
        pointer-events: none;                              /* Default overlay tidak menerima klik */
      }                                                     /* Akhir aturan .cf-alert */

      .cf-alert__backdrop {                                /* Latar belakang gelap di belakang box alert */
        position:   absolute;                              /* Mengisi seluruh area .cf-alert */
        inset:      0;                                     /* Full layar pada wrapper */
        background: rgba(0, 0, 0, 0.45);                   /* Warna hitam transparan 45% */
      }                                                     /* Akhir aturan .cf-alert__backdrop */

      .cf-alert__box {                                     /* Box utama alert yang berisi teks dan tombol */
        position:       relative;                          /* Di atas backdrop */
        pointer-events: auto;                              /* Box bisa diklik (override pointer-events parent) */
        max-width:      320px;                             /* Lebar maksimum box 320px */
        width:          90%;                               /* Lebar responsif 90% dari viewport */
        background:     #fffdf8;                           /* Background krem lembut */
        border-radius:  18px;                              /* Sudut box membulat 18px */
        padding:        16px 18px 14px;                    /* Padding dalam box: atas/kanan/bawah */
        box-shadow:     0 18px 40px rgba(0,0,0,.18);       /* Bayangan cukup tebal di bawah box */
        display:        flex;                              /* Flex layout vertikal */
        flex-direction: column;                            /* Elemen di dalam box disusun vertikal */
        gap:            10px;                              /* Jarak antar elemen (title, message, button) */
        font-family:    Poppins, system-ui, -apple-system,
                        "Segoe UI", Roboto, Arial, sans-serif;
                                                          /* Font yang konsisten dengan body */
      }                                                     /* Akhir aturan .cf-alert__box */

      .cf-alert__title {                                   /* Teks judul di dalam alert */
        font-weight: 600;                                  /* Teks judul cukup tebal */
        font-size:   0.92rem;                              /* Ukuran teks judul 0.92rem */
        color:       var(--brown);                         /* Warna teks judul coklat brand */
        margin-bottom:2px;                                 /* Jarak kecil di bawah judul */
      }                                                     /* Akhir aturan .cf-alert__title */

      .cf-alert__message {                                 /* Teks pesan utama alert */
        font-size: .9rem;                                  /* Ukuran teks pesan 0.9rem */
        color:      #413731;                               /* Warna teks coklat gelap */
      }                                                     /* Akhir aturan .cf-alert__message */

      .cf-alert__btn {                                     /* Tombol “Oke” di bagian bawah alert */
        align-self:    flex-end;                           /* Posisi tombol di sisi kanan bawah box */
        margin-top:    6px;                                /* Jarak atas tombol dari pesan */
        border:        0;                                  /* Tanpa border */
        border-radius: 999px;                              /* Tombol berbentuk pill sangat bulat */
        padding:       8px 18px;                           /* Padding vertical 8px horizontal 18px */
        background:    var(--gold);                        /* Background tombol kuning emas */
        color:         var(--brown);                       /* Warna teks coklat */
        font-weight:   600;                                /* Teks tombol cukup tebal */
        font-size:     0.9rem;                             /* Ukuran teks tombol 0.9rem */
        cursor:        pointer;                            /* Tanda bahwa tombol dapat diklik */
      }                                                     /* Akhir aturan .cf-alert__btn */

      @keyframes spin {                                    /* Definisi animasi CSS bernama spin */
        from {
          transform: rotate(0);                            /* Frame awal: rotasi 0 derajat */
        }
        to {
          transform: rotate(360deg);                       /* Frame akhir: rotasi penuh 360 derajat */
        }
      }                                                     /* Akhir definisi keyframes spin */

    </style>                                               <!-- Penutup blok style -->

  </body>
  <!-- Penutup elemen body -->

</html>
<!-- Penutup dokumen HTML -->

  <!-- TOP BAR -->
  <div class="topbar">
    <div class="topbar-inner">
      <a class="back-link" href="./cart.php">
        <i class="bi bi-arrow-left"></i>
        <span>Kembali</span>
      </a>
    </div>
  </div>

  <main class="page">
      
    <!-- RINGKASAN ITEM CART TERPILIH -->
    <div id="summary"></div>

    <!-- FORM CHECKOUT -->
    <form id="checkoutForm" class="mt-4 pb-4">

      <div class="form-grid">
          
        <!-- 1. Nama Customer -->
        <div class="field-block field-name mb-3">
          <label class="form-label" for="customer_name">
            Nama Customer
          </label>
          <input
            type="text"
            class="form-control"
            id="customer_name"
            value="<?= htmlspecialchars($nameDefault) ?>"
            required
          >
        </div>

        <!-- 2. Tipe Layanan -->
        <div class="field-block field-service mb-3">
          <label class="form-label">
            Tipe Layanan
          </label>
          <div class="cf-select" data-target="service_type">
            <div class="cf-select__trigger" tabindex="0">
              <span
                class="cf-select__text"
                id="service_type_label"
              >
                Dine In
              </span>
              <i class="bi bi-chevron-down cf-select__icon"></i>
            </div>
            <div class="cf-select__list">
              <div
                class="cf-select__option is-active"
                data-value="dine_in"
              >
                Dine In
              </div>
              <div
                class="cf-select__option"
                data-value="take_away"
              >
                Take Away
              </div>
            </div>
          </div>
          <input
            type="hidden"
            id="service_type"
            value="dine_in"
          >
        </div>

        <!-- 3. Nomor Meja (hanya Dine In) -->
        <div class="field-block field-table mb-3" id="tableWrap">
          <label class="form-label" for="table_no">
            Nomor Meja
          </label>
          <input
            type="text"
            class="form-control"
            id="table_no"
            placeholder="Misal: 05"
          >
        </div>

        <!-- 4. Metode Pembayaran -->
        <div class="field-block field-payment mb-4">
          <label class="form-label">
            Metode Pembayaran
          </label>
          <div class="cf-select" data-target="payment_method">
            <div class="cf-select__trigger" tabindex="0">
              <span
                class="cf-select__text"
                id="payment_method_label"
              >
                Cash
              </span>
              <i class="bi bi-chevron-down cf-select__icon"></i>
            </div>
            <div class="cf-select__list">
              <div
                class="cf-select__option is-active"
                data-value="cash"
              >
                Cash
              </div>
              <div
                class="cf-select__option"
                data-value="bank_transfer"
              >
                Bank Transfer
              </div>
              <div
                class="cf-select__option"
                data-value="qris"
              >
                QRIS
              </div>
              <div
                class="cf-select__option"
                data-value="ewallet"
              >
                E-Wallet
              </div>
            </div>
          </div>
          <input
            type="hidden"
            id="payment_method"
            value="cash"
          >
        </div>
      </div>

      <!-- TOMBOL SUBMIT -->
      <div class="d-flex justify-content-end mb-3 field-submit">
        <button type="submit" class="btn-primary-cf">
          Konfirmasi Pesanan
        </button>
      </div>
    </form>
  </main>

  <!-- POPUP ALERT CUSTOM CAFFORA -->
  <div id="cfAlert" class="cf-alert" hidden>
    <div class="cf-alert__backdrop"></div>
    <div class="cf-alert__box">
      <div class="cf-alert__title">Caffora</div>
      <div class="cf-alert__message" id="cfAlertMessage">
        Pesan
      </div>
      <button
        type="button"
        class="cf-alert__btn"
        id="cfAlertOk"
      >
        Oke
      </button>
    </div>
  </div>
  
  <script>
    (function () {
      // ===================================================
      // KONFIGURASI PATH & KONSTAN DASAR
      // ===================================================

      // Deteksi base app secara dinamis (bisa di root / subfolder)
      const PUBLIC_SPLIT = '/public/';
      const idxBase      = window.location.pathname.indexOf(PUBLIC_SPLIT);
      const APP_BASE     =
        idxBase > -1 ? window.location.pathname.slice(0, idxBase) : '';

      // Endpoint API buat pesanan & halaman riwayat
      const API_CREATE  = APP_BASE + '/backend/api/orders.php?action=create';
      const HISTORY_URL = APP_BASE + '/public/customer/history.php';

      // Key untuk localStorage
      const KEY_CART   = 'caffora_cart';
      const KEY_SELECT = 'caffora_cart_selected';

      // Tarif pajak 11%
      const TAX_RATE = 0.11;

      // Referensi elemen DOM utama
      const $summary    = document.getElementById('summary');
      const $form       = document.getElementById('checkoutForm');
      const $serviceHid = document.getElementById('service_type');
      const $table      = document.getElementById('table_no');
      const $tableWrap  = document.getElementById('tableWrap');

      // ID user dari PHP
      const USER_ID = <?= json_encode($userId, JSON_UNESCAPED_SLASHES) ?>;

      // ---------------------------------------------------
      // Helper: format angka ke Rupiah
      // ---------------------------------------------------
      const rp = (n) =>
        'Rp ' + Number(n || 0).toLocaleString('id-ID');

      // ---------------------------------------------------
      // Helper: escape HTML sederhana (hindari XSS di innerHTML)
      // ---------------------------------------------------
      const esc = (s) =>
        String(s || '').replace(/[&<>"']/g, (m) => ({
          '&': '&amp;',
          '<': '&lt;',
          '>': '&gt;',
          '"': '&quot;',
          "'": '&#39;'
        })[m]);

      // ---------------------------------------------------
      // Ambil cart dari localStorage
      // ---------------------------------------------------
      const getCart = () => {
        try {
          return JSON.parse(localStorage.getItem(KEY_CART) || '[]');
        } catch {
          return [];
        }
      };

      // ---------------------------------------------------
      // Simpan cart ke localStorage
      // ---------------------------------------------------
      const setCart = (items) =>
        localStorage.setItem(KEY_CART, JSON.stringify(items));

      // ---------------------------------------------------
      // Ambil ID item yang dipilih dari localStorage
      // ---------------------------------------------------
      const getSelectedIds = () => {
        try {
          return JSON.parse(
            localStorage.getItem(KEY_SELECT) || '[]'
          );
        } catch {
          return [];
        }
      };

      // ===================================================
      // RENDER RINGKASAN ITEM YANG DIPILIH DI ATAS FORM
      // ===================================================
      function renderSummary() {
        const cart   = getCart();
        const selIds = getSelectedIds().map(String);

        // Filter hanya item yang dipilih di halaman cart
        const items = cart.filter((it) =>
          selIds.includes(String(it.id))
        );

        if (!items.length) {
          $summary.innerHTML =
            '<div class="text-muted">' +
              'Tidak ada item yang dipilih. ' +
              'Silakan kembali ke keranjang.' +
            '</div>';
          return;
        }

        let subtotal = 0;
        let html     = '';

        items.forEach((it) => {
          const qty   = Number(it.qty)   || 0;
          const price = Number(it.price) || 0;
          const img   = it.image || it.image_url || '';
          const lineT = qty * price;

          subtotal += lineT;

          html += `
            <div class="item-row">
              <div class="left-info">
                <img
                  class="thumb"
                  src="${img}"
                  alt="${esc(it.name || 'Menu')}"
                >
                <div class="name">
                  ${esc(it.name || '')} x ${qty}
                </div>
              </div>
              <div class="line-right">
                ${rp(lineT)}
              </div>
            </div>`;
        });

        const tax   = Math.round(subtotal * TAX_RATE);
        const total = subtotal + tax;

        html += `
          <div class="tot-block">
            <div class="tot-line">
              <span>Subtotal</span>
              <span>${rp(subtotal)}</span>
            </div>
            <div class="tot-line">
              <span>Pajak 11%</span>
              <span>${rp(tax)}</span>
            </div>
            <div class="tot-grand">
              <span>Total</span>
              <span>${rp(total)}</span>
            </div>
          </div>`;

        $summary.innerHTML        = html;
        $summary.dataset.subtotal = subtotal;
        $summary.dataset.tax      = tax;
        $summary.dataset.total    = total;
      }

      // Panggil sekali di awal
      renderSummary();

      // ===================================================
      // INISIALISASI CUSTOM SELECT (TIPE LAYANAN & PEMBAYARAN)
      // ===================================================
      function initCfSelect() {
        const selects = document.querySelectorAll('.cf-select');

        // Tutup semua dropdown
        const closeAll = () => {
          selects.forEach((s) => s.classList.remove('is-open'));
        };

        selects.forEach((sel) => {
          const targetId = sel.dataset.target;
          const trigger  = sel.querySelector('.cf-select__trigger');
          const list     = sel.querySelector('.cf-select__list');
          const label    = sel.querySelector('.cf-select__text');

          // Klik trigger -> buka / tutup dropdown
          trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = sel.classList.contains('is-open');
            closeAll();
            if (!isOpen) {
              sel.classList.add('is-open');
            }
          });

          // Klik salah satu opsi di dropdown
          list
            .querySelectorAll('.cf-select__option')
            .forEach((opt) => {
              opt.addEventListener('click', () => {
                const val  = opt.dataset.value;
                const text = opt.textContent.trim();

                // Update label tampilan
                label.textContent = text;

                // Set nilai ke input hidden
                const hid = document.getElementById(targetId);
                if (hid) {
                  hid.value = val;
                }

                // Update state aktif di list
                list
                  .querySelectorAll('.cf-select__option')
                  .forEach((o) => o.classList.remove('is-active'));
                opt.classList.add('is-active');

                // Khusus service_type, sync field nomor meja
                if (targetId === 'service_type') {
                  syncTableField(val);
                }

                sel.classList.remove('is-open');
              });
            });
        });

        // Klik di luar dropdown -> tutup semua
        document.addEventListener('click', () => closeAll());
      }

      // Jalankan inisialisasi custom select
      initCfSelect();

      // ===================================================
      // TAMPIL / SEMBUNYIKAN FIELD NOMOR MEJA
      // tergantung tipe layanan (dine_in / take_away)
      // ===================================================
      function syncTableField(valNow) {
        const v = valNow ?? $serviceHid.value;

        // Dine-in -> nomor meja aktif
        if (v === 'dine_in') {
          $tableWrap.style.display = '';
          $table.removeAttribute('disabled');
        }
        // Take-away -> sembunyikan nomor meja
        else {
          $tableWrap.style.display = 'none';
          $table.value             = '';
          $table.setAttribute('disabled', 'disabled');
        }
      }

      // Set initial state nomor meja
      syncTableField();

      // ===================================================
      // SHOW / HIDE LOADING DI TOMBOL KONFIRMASI
      // ===================================================
      function showBtnLoading(btn, on) {
        if (on) {
          btn.disabled     = true;
          btn.dataset.text = btn.innerHTML;
          btn.innerHTML    =
            '<span style="' +
              'display:inline-block;' +
              'width:14px;height:14px;' +
              'border:2px solid #fff;' +
              'border-right-color:transparent;' +
              'border-radius:50%;' +
              'margin-right:8px;' +
              'vertical-align:middle;' +
              'animation:spin .7s linear infinite;' +
            '"></span>Memproses...';
        } else {
          btn.disabled  = false;
          btn.innerHTML =
            btn.dataset.text || 'Konfirmasi Pesanan';
        }
      }

      // ===================================================
      // POPUP ALERT CUSTOM (PENGGANTI alert() BAWAAN BROWSER)
      // ===================================================
      function showCfAlert(message, onClose) {
        const wrap  = document.getElementById('cfAlert');
        const msgEl = document.getElementById('cfAlertMessage');
        const okBtn = document.getElementById('cfAlertOk');

        // Fallback ke alert biasa kalau komponen tidak ada
        if (!wrap || !msgEl || !okBtn) {
          alert(message);
          if (typeof onClose === 'function') {
            onClose();
          }
          return;
        }

        msgEl.textContent = message;
        wrap.hidden       = false;

        function close() {
          wrap.hidden = true;
          okBtn.removeEventListener('click', handle);
          if (typeof onClose === 'function') {
            onClose();
          }
        }

        function handle() {
          close();
        }

        okBtn.addEventListener('click', handle, { once: true });

        const backdrop = wrap.querySelector('.cf-alert__backdrop');
        if (backdrop) {
          backdrop.onclick = close;
        }
      }

      // ===================================================
      // SUBMIT FORM CHECKOUT
      // - Validasi item terpilih
      // - Kirim data ke API backend
      // - Hapus item yang sudah dipesan dari localStorage
      // ===================================================
      $form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const btn = $form.querySelector('.btn-primary-cf');
        showBtnLoading(btn, true);

        // Ambil item yang dipilih
        const cart     = getCart();
        const selIds   = getSelectedIds().map(String);
        const itemsSel = cart.filter((it) =>
          selIds.includes(String(it.id))
        );

        if (!itemsSel.length) {
          showCfAlert('Tidak ada item yang dipilih.');
          showBtnLoading(btn, false);
          return;
        }

        // Ambil subtotal, pajak, total dari dataset di summary
        const subtotal = Number($summary.dataset.subtotal || 0);
        const tax      = Number($summary.dataset.tax || 0);
        const grand    = Number($summary.dataset.total || 0);

        // Siapkan payload ke API backend
        const payload = {
          user_id:        USER_ID,
          customer_name:  document
            .getElementById('customer_name')
            .value.trim(),
          service_type:   document
            .getElementById('service_type')
            .value,
          table_no:       ($table.value || '').trim() || null,
          payment_method: document
            .getElementById('payment_method')
            .value,
          payment_status: 'pending',
          subtotal:       subtotal,
          tax_amount:     tax,
          grand_total:    grand,
          items:          itemsSel.map((it) => ({
            menu_id: Number(it.id),
            qty:     Number(it.qty)   || 0,
            price:   Number(it.price) || 0
          }))
        };

        try {
          // Kirim ke endpoint create order
          const res = await fetch(API_CREATE, {
            method:      'POST',
            headers:     { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body:        JSON.stringify(payload)
          });

          // Robust parsing: coba JSON, kalau gagal baca text
          let js;
          try {
            js = await res.json();
          } catch {
            const txt = await res.text();
            throw new Error(
              txt ? txt.substring(0, 300) : 'Invalid JSON'
            );
          }

          if (!res.ok || !js.ok) {
            throw new Error(js.error || ('HTTP ' + res.status));
          }

          // Hapus item yang sudah dipesan dari cart localStorage
          const remaining = cart.filter((it) =>
            !selIds.includes(String(it.id))
          );
          setCart(remaining);
          localStorage.removeItem(KEY_SELECT);

          const inv = js.invoice_no || '';

          // Tampilkan pesan sukses lalu redirect ke riwayat
          showCfAlert(
            'Pesanan berhasil dibuat! Invoice: ' + inv,
            function () {
              window.location.href = HISTORY_URL;
            }
          );
        } catch (err) {
          showCfAlert(
            'Checkout gagal: ' + (err?.message || err)
          );
        } finally {
          showBtnLoading(btn, false);
        }
      });
    })();
  </script>
  <!-- Penutup elemen body -->
</body>
<!-- Penutup dokumen HTML -->
</html>

