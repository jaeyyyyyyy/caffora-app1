<?php
// Pembuka file PHP

// Helper untuk escape output HTML
function h($string) {
    // Fungsi h() digunakan untuk mencegah XSS dengan meng-escape karakter HTML
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    // Convert karakter spesial menjadi entitas HTML (kutip single/double di-escape)
}

// Helper untuk generate OTP random 6 digit
function generateOtp($length = 6) {
    // Fungsi untuk membuat kode OTP numeric dengan panjang tertentu

    $digits = '0123456789';
    // Daftar karakter angka yang diperbolehkan

    $otp = '';
    // Variabel penampung hasil OTP

    for ($i = 0; $i < $length; $i++) {
        // Loop sebanyak panjang OTP yang diminta

        $otp .= $digits[random_int(0, strlen($digits) - 1)];
        // Pilih angka acak menggunakan random_int() yang cryptographically secure
    }

    return $otp;
    // Kembalikan hasil OTP
}

// Helper untuk cek expired OTP
function isOtpExpired($expiredAt) {
    // Fungsi cek apakah OTP sudah kedaluwarsa berdasarkan timestamp

    return strtotime($expiredAt) < time();
    // Jika waktu expired lebih kecil dari waktu sekarang → OTP dianggap expired
}
