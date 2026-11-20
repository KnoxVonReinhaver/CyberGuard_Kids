<?php
require_once 'koneksi.php';

$misi     = mysqli_query($koneksi, 'SELECT kategori, COUNT(*) AS jumlah FROM skenario GROUP BY kategori ORDER BY kategori');
$peran    = $_SESSION['role'] ?? '';
$linkMain = $peran === 'admin' ? 'admin.php' : ($peran === 'pemain' ? 'game.php' : 'login.php');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0D1B2A">
    <title>CyberGuard Kids - Petualangan Aman di Dunia Maya</title>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>

<header>
    <nav class="navbar navbar-expand-lg navbar-cyber sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="gambar/LOGO.png" alt="Logo CyberGuard Kids" class="logo-nav">
                CyberGuard Kids
            </a>
            <button class="navbar-toggler border-info" type="button" data-bs-toggle="collapse" data-bs-target="#menuUtama" aria-label="Buka menu">
                <i class="bi bi-list text-info fs-2"></i>
            </button>
            <div class="collapse navbar-collapse" id="menuUtama">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                    <li class="nav-item"><a class="nav-link active" href="index.php">Beranda</a></li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item"><a class="nav-link" href="<?= $_SESSION['role'] === 'admin' ? 'admin.php' : 'game.php' ?>">
                            <?= $_SESSION['role'] === 'admin' ? 'Panel Guru' : 'Main Game' ?></a></li>
                        <li class="nav-item"><a class="nav-link" href="login.php?aksi=keluar">Keluar</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="login.php">Masuk</a></li>
                        <li class="nav-item"><a class="nav-link" href="register.php">Daftar</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
</header>

<main>
    <section class="hero">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-md-6">
                    <span class="badge-game mb-3"><i class="bi bi-shield-check"></i> Game edukasi keamanan siber</span>
                    <h1 class="mb-3">Jadilah <span class="text-cyan">Pahlawan Siber</span> yang Cerdas dan Aman!</h1>
                    <p class="lead mb-4">Hadapi situasi nyata di dunia maya, pilih tindakan yang tepat, dan kumpulkan skor untuk naik ke papan peringkat.</p>
                    <div class="d-flex flex-wrap gap-3 justify-content-center justify-content-md-start">
                        <a href="<?= $linkMain ?>" class="btn btn-gold btn-lg">
                            <i class="bi bi-controller me-2"></i>Mulai Bermain
                        </a>
                        <a href="#misi" class="btn btn-outline-cyber btn-lg">Lihat Misi</a>
                    </div>
                </div>
                <div class="col-md-6 text-center">
                    <img src="gambar/maskot.png" alt="Maskot CyberGuard Kids" class="logo-hero">
                </div>
            </div>
        </div>
    </section>

    <section class="container">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="glass-card h-100">
                    <div class="fitur-icon"><i class="bi bi-envelope-exclamation"></i></div>
                    <h3 class="h5">Waspada Phishing</h3>
                    <p class="text-muted-cyber mb-0">Kenali pesan palsu yang meminta data pribadimu.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="glass-card h-100">
                    <div class="fitur-icon"><i class="bi bi-key"></i></div>
                    <h3 class="h5">Kata Sandi Kuat</h3>
                    <p class="text-muted-cyber mb-0">Pelajari cara membuat dan menjaga kata sandi rahasia.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="glass-card h-100">
                    <div class="fitur-icon"><i class="bi bi-trophy"></i></div>
                    <h3 class="h5">Papan Peringkat</h3>
                    <p class="text-muted-cyber mb-0">Bersaing dengan teman dan jadilah juara 5 besar.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="misi" class="container mt-5">
        <h2 class="h3 mb-4"><i class="bi bi-flag-fill text-gold me-2"></i>Pilih Misimu</h2>
        <div class="row g-4 justify-content-center">
            <?php $adaMisi = false; while ($m = mysqli_fetch_assoc($misi)): $adaMisi = true; ?>
                <div class="col-6 col-sm-6 col-lg-3">
                    <a href="<?= $peran === 'admin' ? 'admin.php' : $linkMain . '?kategori=' . urlencode($m['kategori']) ?>" class="glass-card misi-card d-block h-100 text-center">
                        <div class="fitur-icon mx-auto"><i class="bi <?= ikonKategori($m['kategori']) ?>"></i></div>
                        <h3 class="h5 text-light"><?= e($m['kategori']) ?></h3>
                        <p class="text-muted-cyber mb-0"><?= (int) $m['jumlah'] ?> skenario</p>
                    </a>
                </div>
            <?php endwhile; ?>
            <?php if (!$adaMisi): ?>
                <div class="col-12"><p class="text-muted-cyber">Misi belum tersedia. Guru dapat menambahkannya lewat Panel Guru.</p></div>
            <?php endif; ?>
        </div>
        <div class="text-center mt-4">
            <a href="<?= $peran === 'admin' ? 'admin.php' : $linkMain . '?kategori=semua' ?>" class="btn btn-gold btn-lg"><i class="bi bi-controller me-2"></i>Mulai Misi</a>
        </div>
    </section>
</main>

<footer class="footer-cyber">
    <div class="container text-center">
        &copy; <?= date('Y') ?> CyberGuard Kids
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>