<?php
/**
 * Halaman Login Modern Split-Screen AATMS v2.0
 */
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = null;
$usernameInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $usernameInput = trim($_POST['username'] ?? '');
    $passwordInput = $_POST['password'] ?? '';

    if ($usernameInput === '' || $passwordInput === '') {
        $error = 'Mohon isi Username/NIM/NIDN dan Kata Sandi.';
    } else {
        $pdo = getDB();
        $result = attemptLogin($pdo, $usernameInput, $passwordInput);
        if ($result['success']) {
            setFlash('success', 'Selamat datang kembali, ' . $result['user']['full_name'] . '!');
            header('Location: ' . BASE_URL . '/dashboard.php');
            exit;
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk Sistem — <?= e(APP_FULL_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/style.css">
</head>
<body>
<div class="login-split-wrapper">
    <!-- Left Brand Showcase -->
    <div class="login-showcase">
        <div>
            <div class="d-flex align-items-center gap-3 mb-5">
                <div class="brand-icon" style="width: 48px; height: 48px; font-size: 1.35rem;">
                    <i class="fa-solid fa-satellite-dish"></i>
                </div>
                <div>
                    <div class="fw-extrabold fs-4 lh-1"><?= e(APP_NAME) ?></div>
                    <small class="text-sky-light">Academic Attendance &amp; Task Monitoring System</small>
                </div>
            </div>

            <span class="badge rounded-pill bg-sky bg-opacity-25 text-sky-light border border-info border-opacity-25 px-3 py-2 mb-3">
                <i class="fa-solid fa-location-crosshairs me-1"></i>Smart Academic Platform v2.0
            </span>
            <h1 class="fw-extrabold display-6 mb-3" style="line-height: 1.2;">
                Monitoring Kehadiran GPS &amp; Tugas Perkuliahan Terintegrasi.
            </h1>
            <p class="text-slate-400 mb-4" style="max-width: 520px; font-size: 0.98rem;">
                Validasi presensi berbasis koordinat radius Haversine secara <em>real-time</em> di peta interaktif, dilengkapi manajemen pengumpulan tugas berbatas waktu otomatis.
            </p>

            <div class="row g-3" style="max-width: 560px;">
                <div class="col-sm-6">
                    <div class="login-feature-pill h-100">
                        <div class="text-sky-light fw-bold small mb-1">
                            <i class="fa-solid fa-map-location-dot me-2"></i>Geofencing Haversine
                        </div>
                        <div class="small text-slate-400">Validasi jarak presisi meter di sisi server dengan peta Leaflet + OpenStreetMap.</div>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="login-feature-pill h-100">
                        <div class="text-sky-light fw-bold small mb-1">
                            <i class="fa-solid fa-stopwatch me-2"></i>Auto-Lock Deadline
                        </div>
                        <div class="small text-slate-400">Pengumpulan multi-format (PDF, Word, Foto, Teks) dengan penutupan server otomatis.</div>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="login-feature-pill h-100">
                        <div class="text-sky-light fw-bold small mb-1">
                            <i class="fa-solid fa-bolt me-2"></i>Smart Code Lookup
                        </div>
                        <div class="small text-slate-400">Pemanggilan instan mata kuliah &amp; tugas dengan kode unik (<code>Ctrl+K</code>).</div>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="login-feature-pill h-100">
                        <div class="text-sky-light fw-bold small mb-1">
                            <i class="fa-solid fa-chart-pie me-2"></i>Analitik Eksekutif
                        </div>
                        <div class="small text-slate-400">Grafik capaian kelas, peta monitoring kampus, ekspor CSV, dan cetak laporan.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="small text-slate-400 pt-4">
            &copy; <?= date('Y') ?> <?= e(APP_NAME) ?> &bull; Keamanan PDO Prepared Statements, RBAC &amp; CSRF Aktif.
        </div>
    </div>

    <!-- Right Form Pane -->
    <div class="login-form-pane">
        <div class="login-form-box">
            <div class="d-lg-none text-center mb-4">
                <div class="brand-icon mx-auto mb-2" style="width: 48px; height: 48px; font-size: 1.3rem;">
                    <i class="fa-solid fa-satellite-dish"></i>
                </div>
                <h4 class="fw-extrabold mb-0"><?= e(APP_NAME) ?></h4>
                <small class="text-muted"><?= e(APP_FULL_NAME) ?></small>
            </div>

            <div class="mb-4">
                <h3 class="fw-extrabold mb-1" style="color:#0F172A;">Selamat Datang</h3>
                <p class="text-secondary small mb-0">Silakan masuk menggunakan Username, NIM, NIDN, atau Email kampus Anda.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2 px-3 small rounded-3 d-flex align-items-center gap-2 mb-3">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <div><?= e($error) ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="" autocomplete="off" id="loginForm">
                <?= csrfInput() ?>
                <div class="mb-3">
                    <label class="form-label small fw-semibold" style="color:#334155;">Username / NIM / NIDN / Email</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="fa-regular fa-id-badge text-muted"></i></span>
                        <input type="text" name="username" id="loginUsername" class="form-control border-start-0 ps-0" placeholder="Contoh: 202401001 atau dosen1" value="<?= e($usernameInput) ?>" required autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-semibold" style="color:#334155;">Kata Sandi</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-lock text-muted"></i></span>
                        <input type="password" name="password" id="loginPassword" class="form-control border-start-0 border-end-0 ps-0" placeholder="Masukkan kata sandi" required>
                        <button class="input-group-text bg-light border-start-0 text-muted" type="button" id="togglePwdBtn" aria-label="Tampilkan Sandi">
                            <i class="fa-regular fa-eye" id="togglePwdIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-sky w-100 py-2 fw-bold">
                    <i class="fa-solid fa-right-to-bracket me-2"></i>Masuk ke Portal Akademik
                </button>
            </form>

            <div class="mt-4 pt-3 border-top">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small fw-bold text-secondary">Akses Cepat Akun Demo:</span>
                    <span class="badge bg-light text-secondary border" style="font-size: 0.68rem;">Klik untuk isi otomatis</span>
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <button type="button" class="demo-role-card demo-fill" data-user="admin" data-pass="password123">
                            <div class="fw-bold small text-dark"><i class="fa-solid fa-user-shield text-sky me-1"></i>Admin</div>
                            <div class="text-muted" style="font-size: 0.72rem;">User: <code>admin</code></div>
                        </button>
                    </div>
                    <div class="col-6">
                        <button type="button" class="demo-role-card demo-fill" data-user="dosen1" data-pass="password123">
                            <div class="fw-bold small text-dark"><i class="fa-solid fa-chalkboard-user text-success me-1"></i>Dosen</div>
                            <div class="text-muted" style="font-size: 0.72rem;">User: <code>dosen1</code></div>
                        </button>
                    </div>
                    <div class="col-6">
                        <button type="button" class="demo-role-card demo-fill" data-user="202401001" data-pass="password123">
                            <div class="fw-bold small text-dark"><i class="fa-solid fa-user-graduate text-primary me-1"></i>Mahasiswa</div>
                            <div class="text-muted" style="font-size: 0.72rem;">NIM: <code>202401001</code></div>
                        </button>
                    </div>
                    <div class="col-6">
                        <button type="button" class="demo-role-card demo-fill" data-user="kaprodi" data-pass="password123">
                            <div class="fw-bold small text-dark"><i class="fa-solid fa-user-tie text-warning me-1"></i>Kaprodi</div>
                            <div class="text-muted" style="font-size: 0.72rem;">User: <code>kaprodi</code></div>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.querySelectorAll('.demo-fill').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('loginUsername').value = btn.getAttribute('data-user');
        document.getElementById('loginPassword').value = btn.getAttribute('data-pass');
    });
});
document.getElementById('togglePwdBtn')?.addEventListener('click', () => {
    const pwd = document.getElementById('loginPassword');
    const icon = document.getElementById('togglePwdIcon');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.className = 'fa-regular fa-eye-slash';
    } else {
        pwd.type = 'password';
        icon.className = 'fa-regular fa-eye';
    }
});
</script>
</body>
</html>
