<?php
session_start();
require_once 'config.php';

mysqli_report(MYSQLI_REPORT_OFF);

$koneksi = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$koneksi) {
    error_log('Koneksi database gagal: ' . mysqli_connect_error());
    die('Maaf, sistem sedang mengalami gangguan koneksi. Silakan coba beberapa saat lagi.');
}

mysqli_set_charset($koneksi, 'utf8mb4');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrfToken()
{
    return $_SESSION['csrf_token'] ?? '';
}

function cekCsrf($token)
{
    return isset($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

function e($teks)
{
    return htmlspecialchars((string) $teks, ENT_QUOTES, 'UTF-8');
}

function ikonKategori($kategori)
{
    $daftar = [
        'Phishing'        => 'bi-envelope-exclamation',
        'Kata Sandi'      => 'bi-key',
        'Privasi'         => 'bi-eye-slash',
        'Perundungan'     => 'bi-chat-heart', 
        'Malware'         => 'bi-bug-fill',       
        'Predator Daring' => 'bi-person-x-fill',  
        'Keamanan Fisik'  => 'bi-laptop',
    ];

    return $daftar[$kategori] ?? 'bi-shield-lock';
}
?>