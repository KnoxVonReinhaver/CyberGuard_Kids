<?php
require_once 'koneksi.php';

if (isset($_GET['aksi']) && $_GET['aksi'] === 'keluar') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
    header('Location: login.php');
    exit;
}

if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin.php' : 'game.php'));
    exit;
}

$error          = '';
$kategoriTujuan = trim($_GET['kategori'] ?? $_POST['kategori'] ?? '');
$sukses         = $_SESSION['sukses'] ?? '';
unset($_SESSION['sukses']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!cekCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Sesi keamanan tidak valid. Silakan coba lagi.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = mysqli_prepare($koneksi, 'SELECT id, username, password, role FROM users WHERE username = ?');
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);

            $_SESSION['user_id']    = $user['id'];
            $_SESSION['username']   = $user['username'];
            $_SESSION['role']       = $user['role'];
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            if ($user['role'] === 'admin') {
                $tujuan = 'admin.php';
            } else {
                $tujuan = $kategoriTujuan !== '' ? 'game.php?kategori=' . urlencode($kategoriTujuan) : 'game.php';
            }

            header('Location: ' . $tujuan);
            exit;
        }

        $error = 'Username atau password salah. Coba periksa lagi.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0D1B2A">
    <title>Masuk - CyberGuard Kids</title>
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
            <h1 class="h3 mb-1">Masuk ke Markas</h1>
            <p class="text-muted-cyber mb-0">Siap menjaga dunia maya hari ini?</p>
        </div>

        <?php if ($sukses): ?>
            <div class="alert alert-success py-2 rounded-4" role="status"><i class="bi bi-check-circle me-2"></i><?= e($sukses) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-cyber py-2" role="alert"><i class="bi bi-exclamation-triangle me-2"></i><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="login.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="kategori" value="<?= e($kategoriTujuan) ?>">
            <div class="mb-3">
                <label for="username" class="form-label"><i class="bi bi-person-fill me-1"></i>Username</label>
                <input type="text" class="form-control" id="username" name="username" placeholder="Tulis username" required autofocus>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label"><i class="bi bi-lock-fill me-1"></i>Password</label>
                <input type="password" class="form-control" id="password" name="password" placeholder="Tulis password" required>
            </div>
            <button type="submit" class="btn btn-gold w-100 btn-lg">Masuk</button>
        </form>

        <div class="text-center mt-4">
            <p class="mb-2">Belum punya akun? <a href="register.php" class="fw-bold">Daftar di sini</a></p>
            <a href="index.php"><i class="bi bi-arrow-left me-1"></i>Kembali ke beranda</a>
        </div>
    </div>
</main>
</body>
</html>