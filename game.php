<?php
require_once 'koneksi.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pemain') {
    header('Location: login.php');
    exit;
}

$idUser = $_SESSION['user_id'];

$kategoriPilih = null;
if (isset($_GET['kategori'])) {
    $kategoriPilih = trim($_GET['kategori']);
    if ($kategoriPilih === 'semua') {
        $kategoriPilih = '';
    }
}

if (!isset($_SESSION['misi']) || isset($_GET['ulang']) || $kategoriPilih !== null) {
    if ($kategoriPilih === null) {
        $kategoriPilih = $_SESSION['kategori_misi'] ?? '';
    }

    $ids = [];
    if ($kategoriPilih === '') {
        $hasil = mysqli_query($koneksi, 'SELECT id FROM skenario WHERE is_deleted = 0');
    } else {
        $stmt = mysqli_prepare($koneksi, 'SELECT id FROM skenario WHERE kategori = ? AND is_deleted = 0');
        mysqli_stmt_bind_param($stmt, 's', $kategoriPilih);
        mysqli_stmt_execute($stmt);
        $hasil = mysqli_stmt_get_result($stmt);
    }
    while ($baris = mysqli_fetch_assoc($hasil)) {
        $ids[] = (int) $baris['id'];
    }
    shuffle($ids);

    $_SESSION['misi']          = $ids;
    $_SESSION['urut']          = 0;
    $_SESSION['skor']          = 0;
    $_SESSION['feedback']      = null;
    $_SESSION['kategori_misi'] = $kategoriPilih;

    if (isset($_GET['ulang']) || isset($_GET['kategori'])) {
        header('Location: game.php');
        exit;
    }
}

$tersedia = [];
$hasil = mysqli_query($koneksi, 'SELECT id FROM skenario WHERE is_deleted = 0');
while ($baris = mysqli_fetch_assoc($hasil)) {
    $tersedia[(int) $baris['id']] = true;
}
$sudahDijawab = array_slice($_SESSION['misi'], 0, $_SESSION['urut']);
$sisaMisi     = array_filter(array_slice($_SESSION['misi'], $_SESSION['urut']), function ($id) use ($tersedia) {
    return isset($tersedia[$id]);
});
$_SESSION['misi'] = array_merge($sudahDijawab, array_values($sisaMisi));

function ambilSkenario($koneksi, $id)
{
    $stmt = mysqli_prepare($koneksi, 'SELECT * FROM skenario WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['jawab']) && $_SESSION['feedback'] === null) {
    if (!cekCsrf($_POST['csrf_token'] ?? '')) {
        header('Location: game.php');
        exit;
    }

    $pilihan = $_POST['jawab'] === 'B' ? 'B' : 'A';
    $urut    = $_SESSION['urut'];

    if (isset($_SESSION['misi'][$urut])) {
        $s = ambilSkenario($koneksi, $_SESSION['misi'][$urut]);

        if (!$s) {
            $_SESSION['urut']++;
            header('Location: game.php');
            exit;
        }

        $benar = $s['jawaban_benar'] === $pilihan;

        if ($benar) {
            $_SESSION['skor'] += 10;
            $skorSaatIni = (int) $_SESSION['skor'];
            $updateSkor  = mysqli_prepare($koneksi, 'UPDATE users SET skor = GREATEST(skor, ?) WHERE id = ?');
            mysqli_stmt_bind_param($updateSkor, 'ii', $skorSaatIni, $idUser);
            mysqli_stmt_execute($updateSkor);
        }

        $idSkenario = (int) $s['id'];
        $nilai      = $benar ? 1 : 0;
        $simpan     = mysqli_prepare($koneksi, 'INSERT INTO jawaban (user_id, skenario_id, pilihan, benar) VALUES (?, ?, ?, ?)');
        mysqli_stmt_bind_param($simpan, 'iisi', $idUser, $idSkenario, $pilihan, $nilai);
        mysqli_stmt_execute($simpan);

        $_SESSION['feedback'] = [
            'skenario' => $s,
            'pilihan'  => $pilihan,
            'benar'    => $benar,
        ];
        $_SESSION['urut']++;
    }
    header('Location: game.php');
    exit;
}

if (isset($_GET['lanjut'])) {
    $_SESSION['feedback'] = null;
    header('Location: game.php');
    exit;
}

$total    = count($_SESSION['misi']);
$urut     = $_SESSION['urut'];
$skor     = $_SESSION['skor'];
$feedback = $_SESSION['feedback'];
$selesai  = $feedback === null && $urut >= $total;
$kosong   = $total === 0;

if ($selesai && !$kosong) {
    $stmt = mysqli_prepare($koneksi, 'UPDATE users SET skor = GREATEST(skor, ?) WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $skor, $idUser);
    mysqli_stmt_execute($stmt);
}

$stmt = mysqli_prepare($koneksi, 'SELECT skor FROM users WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $idUser);
mysqli_stmt_execute($stmt);
$rekorPribadi = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['skor'] ?? 0);

$skenario = (!$selesai && $feedback === null && !$kosong) ? ambilSkenario($koneksi, $_SESSION['misi'][$urut]) : null;

$papan = mysqli_query($koneksi, "SELECT username, skor FROM users WHERE role = 'pemain' ORDER BY skor DESC, username ASC LIMIT 5");

$stmtRiwayat = mysqli_prepare($koneksi, 'SELECT s.kategori, s.cerita, s.penjelasan, j.pilihan, j.benar
    FROM jawaban j
    JOIN skenario s ON s.id = j.skenario_id
    WHERE j.id IN (SELECT MAX(id) FROM jawaban WHERE user_id = ? GROUP BY skenario_id)
    ORDER BY j.id DESC LIMIT 10');
mysqli_stmt_bind_param($stmtRiwayat, 'i', $idUser);
mysqli_stmt_execute($stmtRiwayat);
$riwayatEvaluasi = mysqli_stmt_get_result($stmtRiwayat);

$tampil  = $feedback['skenario'] ?? $skenario;
$progres = $total > 0 ? round(($urut / $total) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0D1B2A">
    <title>Arena Misi - CyberGuard Kids</title>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-cyber sticky-top">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <img src="gambar/LOGO.png" alt="Logo CyberGuard Kids" class="logo-nav">
            <span class="d-none d-sm-inline">CyberGuard Kids</span>
        </a>
        <div class="d-flex align-items-center gap-1 gap-sm-2 flex-wrap justify-content-end">
            <span class="text-light d-none d-md-inline"><i class="bi bi-person-circle text-cyan me-1"></i><?= e($_SESSION['username']) ?></span>
            <span class="score-pill"><i class="bi bi-star-fill me-1"></i><?= (int) $skor ?> poin</span>
            <span class="score-pill d-none d-sm-inline" title="High score pribadi"><i class="bi bi-trophy-fill me-1"></i>Rekor <?= $rekorPribadi ?></span>
            <button type="button" id="tombolSuara" class="btn btn-outline-cyber btn-sm" aria-label="Nyalakan atau matikan suara" aria-pressed="true"><i class="bi bi-volume-up-fill"></i></button>
            <a href="login.php?aksi=keluar" class="btn btn-outline-cyber btn-sm" aria-label="Keluar"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </div>
</nav>

<main class="container py-3 py-md-5" id="arena" data-hasil="<?= $feedback ? ($feedback['benar'] ? 'benar' : 'salah') : '' ?>" data-urut="<?= (int) $urut ?>">
    <div class="row justify-content-center align-items-center g-4">
        <div class="col-lg-3 text-center">
            <div id="balonMaskot" class="balon-maskot" role="status" aria-live="polite"></div>
            <img src="gambar/maskot.png" alt="Maskot CyberGuard Kids" class="maskot-game">
        </div>

        <div class="col-lg-6">
            <div class="phone-frame">
                <?php if ($kosong): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-inbox display-4 text-cyan"></i>
                        <h2 class="h5 mt-3">Belum ada misi<?= ($_SESSION['kategori_misi'] ?? '') !== '' ? ' untuk kategori ' . e($_SESSION['kategori_misi']) : '' ?></h2>
                        <p class="text-muted-cyber">Minta guru menambahkan skenario, atau pilih kategori lain.</p>
                        <a href="index.php#misi" class="btn btn-gold">Pilih Kategori</a>
                    </div>

                <?php elseif ($selesai): ?>
                    <div class="text-center py-3">
                        <img src="../gambar/LOGO.png" alt="Logo CyberGuard Kids" class="logo-finish mb-3">
                        <h2 class="h3 text-gold">Misi Selesai!</h2>
                        <p class="mb-1">Kamu mengumpulkan</p>
                        <p class="display-5 fw-bold text-mint mb-1"><?= (int) $skor ?> poin</p>
                        <p class="text-muted-cyber">dari <?= $total * 10 ?> poin maksimal</p>
                        <div class="d-flex flex-wrap gap-2 justify-content-center">
                            <a href="game.php?ulang=1" class="btn btn-gold"><i class="bi bi-arrow-repeat me-2"></i>Main Lagi</a>
                            <a href="index.php#misi" class="btn btn-outline-cyber">Kategori Lain</a>
                        </div>
                    </div>

                <?php elseif ($tampil === null): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-exclamation-triangle display-4 text-gold"></i>
                        <h2 class="h5 mt-3">Misi tidak dapat dimuat</h2>
                        <p class="text-muted-cyber">Skenario ini mungkin baru dihapus guru.</p>
                        <a href="game.php?ulang=1" class="btn btn-gold">Muat Ulang Misi</a>
                    </div>

                <?php else: ?>
                    <div class="progress progress-cyber mb-3" role="progressbar" aria-valuenow="<?= $progres ?>" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar" style="width: <?= $progres ?>%"></div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="kategori-tag"><i class="bi <?= ikonKategori($tampil['kategori']) ?>"></i><?= e($tampil['kategori']) ?></span>
                        <small class="text-muted-cyber">Misi <?= $feedback ? $urut : $urut + 1 ?> dari <?= $total ?></small>
                    </div>

                    <div class="cerita-box"><?= nl2br(e($tampil['cerita'])) ?></div>

                    <?php
                    $opsiAcak = [
                        ['huruf' => 'A', 'teks' => $tampil['opsi_a']],
                        ['huruf' => 'B', 'teks' => $tampil['opsi_b']]
                    ];
                    
                    $hashAcak = md5($tampil['id'] . $idUser . $urut . session_id());
                    if (hexdec(substr($hashAcak, 0, 1)) % 2 === 0) {
                        $opsiAcak = array_reverse($opsiAcak);
                    }
                    ?>

                    <?php if ($feedback): ?>
                        <div class="feedback-box <?= $feedback['benar'] ? 'benar' : 'salah' ?>">
                            <i class="bi <?= $feedback['benar'] ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?> me-1"></i>
                            <?= $feedback['benar'] ? 'Hebat, jawabanmu benar! +10 poin' : 'Belum tepat, ayo belajar lagi.' ?>
                            <div class="mt-2 fw-normal"><?= e($tampil['penjelasan']) ?></div>
                        </div>
                        
                        <?php foreach ($opsiAcak as $index => $opsi): ?>
                            <?php
                            $kelas = $index === 1 ? 'opsi-b' : '';
                            if ($opsi['huruf'] === $tampil['jawaban_benar']) {
                                $kelas = 'benar';
                            } elseif ($opsi['huruf'] === $feedback['pilihan']) {
                                $kelas = 'salah';
                            }
                            ?>
                            <div class="btn-answer <?= $kelas ?>" style="pointer-events:none">
                                <i class="bi <?= $index === 0 ? 'bi-1-circle-fill' : 'bi-2-circle-fill' ?> me-2"></i><?= e($opsi['teks']) ?>
                            </div>
                        <?php endforeach; ?>
                        
                        <a href="game.php?lanjut=1" class="btn btn-gold w-100 mt-2">
                            <?= $urut >= $total ? 'Lihat Hasil' : 'Misi Berikutnya' ?><i class="bi bi-arrow-right ms-2"></i>
                        </a>

                    <?php else: ?>
                        <form method="post" action="game.php">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            
                            <?php foreach ($opsiAcak as $index => $opsi): ?>
                                <?php $kelasWarna = $index === 1 ? 'opsi-b' : ''; ?>
                                <button type="submit" name="jawab" value="<?= $opsi['huruf'] ?>" class="btn-answer <?= $kelasWarna ?>">
                                    <i class="bi <?= $index === 0 ? 'bi-1-circle-fill' : 'bi-2-circle-fill' ?> me-2"></i><?= e($opsi['teks']) ?>
                                </button>
                            <?php endforeach; ?>
                            
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-4 justify-content-center">
        <div class="col-lg-5">
            <section class="glass-card h-100" aria-labelledby="judulPeringkat">
                <h2 id="judulPeringkat" class="h4 mb-3"><i class="bi bi-trophy-fill text-gold me-2"></i>5 Besar Peringkat</h2>
                <div class="table-responsive">
                    <table class="table table-cyber table-striped table-hover align-middle">
                        <thead>
                            <tr><th scope="col">Peringkat</th><th scope="col">Pemain</th><th scope="col" class="text-end">Skor Tertinggi</th></tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; while ($p = mysqli_fetch_assoc($papan)): ?>
                                <tr>
                                    <td class="fw-bold <?= $no <= 3 ? 'rank-' . $no : '' ?>">
                                        <?= $no <= 3 ? '<i class="bi bi-award-fill me-1"></i>' : '' ?><?= $no ?>
                                    </td>
                                    <td><?= e($p['username']) ?></td>
                                    <td class="text-end"><?= (int) $p['skor'] ?></td>
                                </tr>
                            <?php $no++; endwhile; ?>
                            <?php if ($no === 1): ?>
                                <tr><td colspan="3" class="text-center text-muted-cyber">Belum ada skor. Jadilah yang pertama!</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="col-lg-7">
            <section class="glass-card h-100" aria-labelledby="judulEvaluasi">
                <h2 id="judulEvaluasi" class="h4 mb-3"><i class="bi bi-journal-check text-mint me-2"></i>Evaluasi Misi Terakhir</h2>
                <div class="table-responsive">
                    <table class="table table-cyber table-striped align-middle">
                        <thead>
                            <tr>
                                <th scope="col">Kategori</th>
                                <th scope="col">Kasus & Pembahasan</th>
                                <th scope="col" class="text-end">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $adaRiwayat = false; while ($rw = mysqli_fetch_assoc($riwayatEvaluasi)): $adaRiwayat = true; ?>
                                <tr>
                                    <td><span class="kategori-tag"><?= e($rw['kategori']) ?></span></td>
                                    <td>
                                        <div class="fw-semibold"><?= e(mb_strimwidth($rw['cerita'], 0, 75, '...')) ?></div>
                                        <small class="text-muted-cyber"><?= e($rw['penjelasan']) ?></small>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <?php if ($rw['benar']): ?>
                                            <span class="status-benar"><i class="bi bi-check-circle-fill me-1"></i>Benar</span>
                                        <?php else: ?>
                                            <span class="status-salah"><i class="bi bi-x-circle-fill me-1"></i>Salah</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            <?php if (!$adaRiwayat): ?>
                                <tr><td colspan="3" class="text-center text-muted-cyber">Belum ada misi yang diselesaikan. Ayo mulai bermain!</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</main>

<footer class="footer-cyber">
    <div class="container text-center">&copy; <?= date('Y') ?> CyberGuard Kids</div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    var arena  = document.getElementById('arena');
    var balon  = document.getElementById('balonMaskot');
    var tombol = document.getElementById('tombolSuara');
    var hasil  = arena.dataset.hasil;
    var urut   = arena.dataset.urut;
    var suaraAktif = true;

    try { suaraAktif = localStorage.getItem('suara') !== 'mati'; } catch (e) {}

    if (urut === '0') {
        try {
            Object.keys(sessionStorage).forEach(function (k) {
                if (k.indexOf('suara-') === 0) {
                    sessionStorage.removeItem(k);
                }
            });
        } catch (e) {}
    }

    var pesan = {
        awal:  ['Pilih jawaban terbaikmu!', 'Pikirkan baik-baik ya, Pahlawan!', 'Kamu pasti bisa!'],
        benar: ['Hebat! Kamu pahlawan siber sejati!', 'Mantap! Terus semangat!', 'Keren! Jawabanmu tepat sekali!'],
        salah: ['Tidak apa-apa, ayo coba lagi!', 'Semangat! Baca penjelasannya ya.', 'Belajar dari salah itu hebat!']
    };

    function acak(daftar) {
        return daftar[Math.floor(Math.random() * daftar.length)];
    }

    function tampilkanBalon(jenis) {
        balon.className = 'balon-maskot tampil ' + (jenis === 'awal' ? '' : jenis);
        balon.textContent = acak(pesan[jenis]);
    }

    function perbaruiTombol() {
        tombol.innerHTML = '<i class="bi ' + (suaraAktif ? 'bi-volume-up-fill' : 'bi-volume-mute-fill') + '"></i>';
        tombol.setAttribute('aria-pressed', suaraAktif);
    }

    function mainkanSuara(jenis) {
        if (!suaraAktif) return;

        var namaFile = jenis === 'benar' ? 'benar.mp3' : 'salah.mp3';
        var audio = new Audio('<?= BASE_URL ?>/suara/' + namaFile);
        audio.volume = 0.8;

        audio.play().catch(function (e) {
            console.warn('Pemutaran audio tertahan browser:', e);
        });
    }

    tombol.addEventListener('click', function () {
        suaraAktif = !suaraAktif;
        try { localStorage.setItem('suara', suaraAktif ? 'hidup' : 'mati'); } catch (e) {}
        perbaruiTombol();
        if (suaraAktif) { mainkanSuara('benar'); }
    });

    perbaruiTombol();

    if (hasil === 'benar' || hasil === 'salah') {
        tampilkanBalon(hasil);
        var kunci = 'suara-' + urut;
        var sudah = false;
        try {
            sudah = sessionStorage.getItem(kunci) === '1';
            sessionStorage.setItem(kunci, '1');
        } catch (e) {}
        if (!sudah) { mainkanSuara(hasil); }
    } else if (document.querySelector('.btn-answer')) {
        tampilkanBalon('awal');
    }
})();
</script>
</body>
</html>