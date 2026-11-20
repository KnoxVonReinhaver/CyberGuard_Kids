<?php
require_once 'koneksi.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin.php' : 'game.php'));
    exit;
}

$error    = '';
$username = '';
$role     = 'pemain';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username   = trim($_POST['username'] ?? '');
    $password   = $_POST['password'] ?? '';
    $konfirmasi = $_POST['konfirmasi'] ?? '';
    $role       = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'pemain';
    $kodeGuru   = trim($_POST['kode_guru'] ?? '');

    if (!cekCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Sesi keamanan tidak valid. Silakan coba lagi.';
    } elseif (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
        $error = 'Username 3-30 karakter, hanya huruf, angka, dan garis bawah.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif ($password !== $konfirmasi) {
        $error = 'Konfirmasi password tidak sama.';
    } elseif ($role === 'admin' && $kodeGuru !== KODE_GURU) {
        $error = 'Kode guru salah. Akun Admin hanya untuk guru.';
    } else {
        $cek = mysqli_prepare($koneksi, 'SELECT id FROM users WHERE username = ?');
        mysqli_stmt_bind_param($cek, 's', $username);
        mysqli_stmt_execute($cek);

        if (mysqli_fetch_assoc(mysqli_stmt_get_result($cek))) {
            $error = 'Username sudah dipakai. Pilih username lain.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($koneksi, 'INSERT INTO users (username, password, role) VALUES (?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'sss', $username, $hash, $role);
            mysqli_stmt_execute($stmt);

            $_SESSION['sukses'] = 'Akun berhasil dibuat. Silakan masuk.';
            header('Location: login.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0D1B2A">
    <title>Daftar - CyberGuard Kids</title>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>

<main class="auth-wrap">
    <div class="glass-card">
        <div class="text-center mb-4">
            <img src="gambar/LOGO.png" alt="Logo CyberGuard Kids" class="logo-card mb-2">
            <h1 class="h3 mb-1">Buat Akun Baru</h1>
            <p class="text-muted-cyber mb-0">Gabung dan jadi pahlawan siber!</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-cyber py-2" role="alert"><i class="bi bi-exclamation-triangle me-2"></i><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="register.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <div class="mb-3">
                <label for="username" class="form-label"><i class="bi bi-person-fill me-1"></i>Username</label>
                <input type="text" class="form-control" id="username" name="username" value="<?= e($username) ?>" placeholder="Contoh: budi_siber" required autofocus>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label"><i class="bi bi-lock-fill me-1"></i>Password</label>
                <input type="password" class="form-control" id="password" name="password" placeholder="Minimal 6 karakter" required>
            </div>
            <div class="mb-3">
                <label for="konfirmasi" class="form-label"><i class="bi bi-shield-lock-fill me-1"></i>Ulangi password</label>
                <input type="password" class="form-control" id="konfirmasi" name="konfirmasi" placeholder="Tulis password sekali lagi" required>
            </div>
            <div class="mb-3">
                <span class="form-label d-block">Daftar sebagai</span>
                <div class="row g-2">
                    <div class="col-6 role-option">
                        <input type="radio" name="role" id="rolePemain" value="pemain" <?= $role === 'pemain' ? 'checked' : '' ?>>
                        <label for="rolePemain"><i class="bi bi-controller me-1"></i>Pemain</label>
                    </div>
                    <div class="col-6 role-option">
                        <input type="radio" name="role" id="roleAdmin" value="admin" <?= $role === 'admin' ? 'checked' : '' ?>>
                        <label for="roleAdmin"><i class="bi bi-person-gear me-1"></i>Admin</label>
                    </div>
                </div>
            </div>
            <div class="mb-4" id="kolomKode" <?= $role === 'admin' ? '' : 'hidden' ?>>
                <label for="kode_guru" class="form-label"><i class="bi bi-key-fill me-1"></i>Kode guru</label>
                <input type="password" class="form-control" id="kode_guru" name="kode_guru" placeholder="Diberikan oleh pengelola">
            </div>
            <button type="submit" class="btn btn-gold w-100 btn-lg">Buat Akun</button>
        </form>

        <div class="text-center mt-4">
            <p class="mb-2">Sudah punya akun? <a href="login.php" class="fw-bold">Masuk</a></p>
            <a href="index.php"><i class="bi bi-arrow-left me-1"></i>Kembali ke beranda</a>
        </div>
    </div>
</main>

<script>
    const kolomKode = document.getElementById('kolomKode');
    document.querySelectorAll('input[name="role"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            kolomKode.hidden = this.value !== 'admin';
        });
    });
</script>
</body>
</html>