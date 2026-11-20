<?php
require_once 'koneksi.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$pesan = $_SESSION['pesan'] ?? '';
unset($_SESSION['pesan']);

$pesanMurid = $_SESSION['pesan_murid'] ?? '';
unset($_SESSION['pesan_murid']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi   = $_POST['aksi'] ?? '';
    $tujuan = '';

    if (!cekCsrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['pesan'] = 'Sesi keamanan tidak valid. Silakan coba lagi.';
        header('Location: admin.php');
        exit;
    }

    if ($aksi === 'simpan') {
        $id         = (int) ($_POST['id'] ?? 0);
        $kategori   = trim($_POST['kategori'] ?? '');
        $cerita     = trim($_POST['cerita'] ?? '');
        $opsiA      = trim($_POST['opsi_a'] ?? '');
        $opsiB      = trim($_POST['opsi_b'] ?? '');
        $benar      = ($_POST['jawaban_benar'] ?? 'A') === 'B' ? 'B' : 'A';
        $penjelasan = trim($_POST['penjelasan'] ?? '');

        if ($kategori === '' || $cerita === '' || $opsiA === '' || $opsiB === '' || $penjelasan === '') {
            $_SESSION['pesan'] = 'Semua kolom wajib diisi.';
        } elseif ($id > 0) {
            $stmt = mysqli_prepare($koneksi, 'UPDATE skenario SET kategori=?, cerita=?, opsi_a=?, opsi_b=?, jawaban_benar=?, penjelasan=? WHERE id=?');
            mysqli_stmt_bind_param($stmt, 'ssssssi', $kategori, $cerita, $opsiA, $opsiB, $benar, $penjelasan, $id);
            mysqli_stmt_execute($stmt);
            $_SESSION['pesan'] = 'Skenario berhasil diperbarui.';
        } else {
            $stmt = mysqli_prepare($koneksi, 'INSERT INTO skenario (kategori, cerita, opsi_a, opsi_b, jawaban_benar, penjelasan) VALUES (?, ?, ?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'ssssss', $kategori, $cerita, $opsiA, $opsiB, $benar, $penjelasan);
            mysqli_stmt_execute($stmt);
            $_SESSION['pesan'] = 'Skenario baru berhasil ditambahkan.';
        }
    }

    if ($aksi === 'hapus') {
        $id = (int) ($_POST['id'] ?? 0);

        $stmt = mysqli_prepare($koneksi, 'UPDATE skenario SET is_deleted = 1 WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);

        $_SESSION['pesan'] = 'Skenario berhasil dinonaktifkan. Riwayat jawaban murid tetap aman.';
    }

    if ($aksi === 'reset_riwayat' || $aksi === 'hapus_murid') {
        $idMurid = (int) ($_POST['id'] ?? 0);
        $stmt = mysqli_prepare($koneksi, "SELECT username FROM users WHERE id = ? AND role = 'pemain'");
        mysqli_stmt_bind_param($stmt, 'i', $idMurid);
        mysqli_stmt_execute($stmt);
        $target = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if ($target) {
            if ($aksi === 'reset_riwayat') {
                $stmt = mysqli_prepare($koneksi, 'DELETE FROM jawaban WHERE user_id = ?');
                mysqli_stmt_bind_param($stmt, 'i', $idMurid);
                mysqli_stmt_execute($stmt);

                $stmt = mysqli_prepare($koneksi, 'UPDATE users SET skor = 0 WHERE id = ?');
                mysqli_stmt_bind_param($stmt, 'i', $idMurid);
                mysqli_stmt_execute($stmt);
                $_SESSION['pesan_murid'] = 'Riwayat jawaban dan skor ' . $target['username'] . ' berhasil direset.';
            } else {
                $stmt = mysqli_prepare($koneksi, "DELETE FROM users WHERE id = ? AND role = 'pemain'");
                mysqli_stmt_bind_param($stmt, 'i', $idMurid);
                mysqli_stmt_execute($stmt);
                $_SESSION['pesan_murid'] = 'Akun ' . $target['username'] . ' berhasil dihapus.';
            }
        } else {
            $_SESSION['pesan_murid'] = 'Murid tidak ditemukan.';
        }
        $tujuan = '#pantauan';
    }

    header('Location: admin.php' . $tujuan);
    exit;
}

$edit = null;
if (isset($_GET['edit'])) {
    $idEdit = (int) $_GET['edit'];
    $stmt = mysqli_prepare($koneksi, 'SELECT * FROM skenario WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $idEdit);
    mysqli_stmt_execute($stmt);
    $edit = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}

$daftar = mysqli_query($koneksi, 'SELECT * FROM skenario WHERE is_deleted = 0 ORDER BY id DESC');

$rekap = mysqli_query($koneksi, "SELECT u.id, u.username, u.skor,
        (SELECT COUNT(*) FROM skenario WHERE is_deleted = 0) AS total_soal,
        COALESCE(SUM(CASE WHEN j.benar = 1 THEN 1 ELSE 0 END), 0) AS benar,
        COALESCE(SUM(CASE WHEN j.benar = 0 THEN 1 ELSE 0 END), 0) AS salah
    FROM users u
    LEFT JOIN (
        SELECT j1.user_id, j1.skenario_id, j1.benar
        FROM jawaban j1
        INNER JOIN (
            SELECT MAX(id) AS max_id
            FROM jawaban
            GROUP BY user_id, skenario_id
        ) j2 ON j1.id = j2.max_id
    ) j ON u.id = j.user_id
    WHERE u.role = 'pemain'
    GROUP BY u.id, u.username, u.skor
    ORDER BY u.username");

$murid  = null;
$detail = null;
if (isset($_GET['murid'])) {
    $idMurid = (int) $_GET['murid'];
    $stmt = mysqli_prepare($koneksi, "SELECT id, username FROM users WHERE id = ? AND role = 'pemain'");
    mysqli_stmt_bind_param($stmt, 'i', $idMurid);
    mysqli_stmt_execute($stmt);
    $murid = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($murid) {
        $stmt = mysqli_prepare($koneksi, 'SELECT s.kategori, s.cerita, s.jawaban_benar, j.pilihan, j.benar, j.waktu
            FROM skenario s
            LEFT JOIN jawaban j ON j.id = (SELECT MAX(id) FROM jawaban WHERE user_id = ? AND skenario_id = s.id)
            WHERE s.is_deleted = 0
            ORDER BY s.id');
        mysqli_stmt_bind_param($stmt, 'i', $idMurid);
        mysqli_stmt_execute($stmt);
        $detail = mysqli_stmt_get_result($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0D1B2A">
    <title>Panel Guru - CyberGuard Kids</title>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>

<header>
    <nav class="navbar navbar-cyber sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="gambar/LOGO.png" alt="Logo CyberGuard Kids" class="logo-nav">
                <span class="d-none d-sm-inline">Panel Guru</span>
            </a>
            <div class="d-flex align-items-center gap-3">
                <span class="text-light"><i class="bi bi-person-gear text-cyan me-1"></i><?= e($_SESSION['username']) ?></span>
                <a href="login.php?aksi=keluar" class="btn btn-outline-cyber btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Keluar</a>
            </div>
        </div>
    </nav>
</header>

<main class="container py-3 py-md-5">
    <?php if ($pesan): ?>
        <div class="alert alert-info border-info bg-transparent text-info rounded-4" role="status"><i class="bi bi-info-circle me-2"></i><?= e($pesan) ?></div>
    <?php endif; ?>

    <section class="glass-card mb-5" aria-labelledby="judulForm">
        <h2 id="judulForm" class="h4 mb-4">
            <i class="bi <?= $edit ? 'bi-pencil-square' : 'bi-plus-circle-fill' ?> text-mint me-2"></i><?= $edit ? 'Ubah Skenario' : 'Tambah Skenario' ?>
        </h2>
        <form method="post" action="admin.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="aksi" value="simpan">
            <input type="hidden" name="id" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="kategori" class="form-label">Kategori</label>
                    <input type="text" class="form-control" id="kategori" name="kategori" list="daftarKategori" placeholder="Contoh: Phishing" value="<?= e($edit['kategori'] ?? '') ?>" required>
                    <datalist id="daftarKategori">
                        <option value="Phishing"><option value="Kata Sandi"><option value="Privasi"><option value="Perundungan">
                    </datalist>
                </div>
                <div class="col-md-6">
                    <label for="jawaban_benar" class="form-label">Jawaban benar</label>
                    <select class="form-select" id="jawaban_benar" name="jawaban_benar">
                        <option value="A" <?= ($edit['jawaban_benar'] ?? '') === 'A' ? 'selected' : '' ?>>Opsi A</option>
                        <option value="B" <?= ($edit['jawaban_benar'] ?? '') === 'B' ? 'selected' : '' ?>>Opsi B</option>
                    </select>
                </div>
                <div class="col-12">
                    <label for="cerita" class="form-label">Cerita kasus</label>
                    <textarea class="form-control" id="cerita" name="cerita" rows="3" placeholder="Tulis situasi yang dihadapi anak" required><?= e($edit['cerita'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label for="opsi_a" class="form-label">Opsi A</label>
                    <input type="text" class="form-control" id="opsi_a" name="opsi_a" value="<?= e($edit['opsi_a'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="opsi_b" class="form-label">Opsi B</label>
                    <input type="text" class="form-control" id="opsi_b" name="opsi_b" value="<?= e($edit['opsi_b'] ?? '') ?>" required>
                </div>
                <div class="col-12">
                    <label for="penjelasan" class="form-label">Penjelasan jawaban</label>
                    <textarea class="form-control" id="penjelasan" name="penjelasan" rows="2" placeholder="Alasan jawaban yang benar, ditampilkan setelah pemain menjawab" required><?= e($edit['penjelasan'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-4">
                <button type="submit" class="btn btn-gold"><i class="bi bi-save me-2"></i><?= $edit ? 'Simpan perubahan' : 'Tambah skenario' ?></button>
                <?php if ($edit): ?>
                    <a href="admin.php" class="btn btn-outline-cyber">Batal</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="glass-card" aria-labelledby="judulTabel">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <h2 id="judulTabel" class="h4 mb-0"><i class="bi bi-table text-mint me-2"></i>Daftar Skenario</h2>
            <div class="search-box-cyber">
                <input type="search" id="cariSkenario" class="form-control form-control-sm" placeholder="Cari kategori atau cerita..." aria-label="Cari skenario">
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-cyber table-striped table-hover align-middle" id="tabelSkenario">
                <thead>
                    <tr>
                        <th scope="col">No</th>
                        <th scope="col">Kategori</th>
                        <th scope="col">Cerita</th>
                        <th scope="col">Kunci</th>
                        <th scope="col" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; while ($s = mysqli_fetch_assoc($daftar)): ?>
                        <tr class="baris-data">
                            <td><?= $no++ ?></td>
                            <td><span class="kategori-tag"><?= e($s['kategori']) ?></span></td>
                            <td style="min-width: 260px;"><?= e(mb_strimwidth($s['cerita'], 0, 90, '...')) ?></td>
                            <td><span class="badge text-bg-warning"><?= e($s['jawaban_benar']) ?></span></td>
                            <td class="text-end text-nowrap">
                                <a href="admin.php?edit=<?= (int) $s['id'] ?>" class="btn btn-sm btn-outline-info btn-icon-sm"><i class="bi bi-pencil"></i> Ubah</a>
                                <form method="post" action="admin.php" class="d-inline" onsubmit="return confirm('Nonaktifkan skenario ini?');">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="aksi" value="hapus">
                                    <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger btn-icon-sm"><i class="bi bi-trash"></i> Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($no === 1): ?>
                        <tr><td colspan="5" class="text-center text-muted-cyber">Belum ada skenario. Tambahkan lewat formulir di atas.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section id="pantauan" class="glass-card mt-5" aria-labelledby="judulPantauan">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <h2 id="judulPantauan" class="h4 mb-0"><i class="bi bi-people-fill text-mint me-2"></i>Pantauan Murid</h2>
            <div class="search-box-cyber">
                <input type="search" id="cariMurid" class="form-control form-control-sm" placeholder="Cari nama murid..." aria-label="Cari murid">
            </div>
        </div>
        <?php if ($pesanMurid): ?>
            <div class="alert alert-info border-info bg-transparent text-info rounded-4" role="status"><i class="bi bi-info-circle me-2"></i><?= e($pesanMurid) ?></div>
        <?php endif; ?>
        <div class="table-responsive">
            <table class="table table-cyber table-striped table-hover align-middle" id="tabelMurid">
                <thead>
                    <tr>
                        <th scope="col">Murid</th>
                        <th scope="col" class="text-center">Benar</th>
                        <th scope="col" class="text-center">Salah</th>
                        <th scope="col" class="text-center">Belum dijawab</th>
                        <th scope="col" class="text-end">Skor tertinggi</th>
                        <th scope="col" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $adaMurid = false; while ($r = mysqli_fetch_assoc($rekap)): $adaMurid = true; ?>
                        <?php $belum = $r['total_soal'] - $r['benar'] - $r['salah']; ?>
                        <tr class="baris-data">
                            <td><i class="bi bi-person-circle text-cyan me-1"></i><?= e($r['username']) ?></td>
                            <td class="text-center status-benar"><?= (int) $r['benar'] ?></td>
                            <td class="text-center status-salah"><?= (int) $r['salah'] ?></td>
                            <td class="text-center status-belum"><?= (int) $belum ?></td>
                            <td class="text-end"><?= (int) $r['skor'] ?></td>
                            <td class="text-end">
                                <div class="d-flex flex-wrap gap-1 justify-content-end">
                                    <a href="admin.php?murid=<?= (int) $r['id'] ?>#detailMurid" class="btn btn-sm btn-outline-info btn-icon-sm"><i class="bi bi-search"></i> Detail</a>
                                    <form method="post" action="admin.php" class="d-inline" data-nama="<?= e($r['username']) ?>" onsubmit="return confirm('Reset seluruh riwayat jawaban dan skor ' + this.dataset.nama + '?');">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="aksi" value="reset_riwayat">
                                        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-warning btn-icon-sm"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
                                    </form>
                                    <form method="post" action="admin.php" class="d-inline" data-nama="<?= e($r['username']) ?>" onsubmit="return confirm('Hapus akun ' + this.dataset.nama + ' beserta seluruh riwayatnya? Tindakan ini tidak dapat dibatalkan.');">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="aksi" value="hapus_murid">
                                        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger btn-icon-sm"><i class="bi bi-person-x"></i> Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if (!$adaMurid): ?>
                        <tr><td colspan="6" class="text-center text-muted-cyber">Belum ada murid yang mendaftar.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($murid): ?>
            <div id="detailMurid" class="mt-5">
                <h3 class="h5 mb-3">Hasil <span class="text-cyan"><?= e($murid['username']) ?></span> per skenario</h3>
                <div class="table-responsive">
                    <table class="table table-cyber table-striped align-middle">
                        <thead>
                            <tr>
                                <th scope="col">Kategori</th>
                                <th scope="col">Cerita</th>
                                <th scope="col">Pilihan murid</th>
                                <th scope="col">Kunci</th>
                                <th scope="col">Status</th>
                                <th scope="col">Waktu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($d = mysqli_fetch_assoc($detail)): ?>
                                <tr>
                                    <td><span class="kategori-tag"><?= e($d['kategori']) ?></span></td>
                                    <td style="min-width: 240px;"><?= e(mb_strimwidth($d['cerita'], 0, 80, '...')) ?></td>
                                    <td><?= $d['pilihan'] ? e($d['pilihan']) : '-' ?></td>
                                    <td><?= e($d['jawaban_benar']) ?></td>
                                    <td>
                                        <?php if ($d['pilihan'] === null): ?>
                                            <span class="status-belum"><i class="bi bi-dash-circle me-1"></i>Belum dijawab</span>
                                        <?php elseif ($d['benar']): ?>
                                            <span class="status-benar"><i class="bi bi-check-circle-fill me-1"></i>Benar</span>
                                        <?php else: ?>
                                            <span class="status-salah"><i class="bi bi-x-circle-fill me-1"></i>Salah</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-nowrap text-muted-cyber"><?= $d['waktu'] ? e(date('d M Y H:i', strtotime($d['waktu']))) : '-' ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <a href="admin.php#pantauan" class="btn btn-outline-cyber btn-sm">Tutup detail</a>
            </div>
        <?php endif; ?>
    </section>
</main>

<footer class="footer-cyber">
    <div class="container text-center">&copy; <?= date('Y') ?> CyberGuard Kids</div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function pasangFilterTabel(idInput, idTabel) {
    var input = document.getElementById(idInput);
    var tabel = document.getElementById(idTabel);
    if (!input || !tabel) return;

    input.addEventListener('input', function () {
        var kataKunci = this.value.toLowerCase();
        var baris = tabel.querySelectorAll('tbody tr.baris-data');
        baris.forEach(function (tr) {
            var teks = tr.textContent.toLowerCase();
            tr.style.display = teks.indexOf(kataKunci) !== -1 ? '' : 'none';
        });
    });
}

pasangFilterTabel('cariSkenario', 'tabelSkenario');
pasangFilterTabel('cariMurid', 'tabelMurid');
</script>
</body>
</html>