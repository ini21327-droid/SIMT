<?php
/**
 * Halaman Profil Pengguna & Pembaruan Kata Sandi Aman
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pdo = getDB();
$sessionUser = currentUser();
$userId = (int)$sessionUser['id'];
$pageTitle = 'Profil & Keamanan Akun';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($fullName === '' || $email === '') {
            setFlash('danger', 'Nama Lengkap dan Email tidak boleh kosong.');
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
                $stmt->execute([$fullName, $email, $userId]);
                $_SESSION['user']['full_name'] = $fullName;
                $_SESSION['user']['email'] = $email;
                setFlash('success', 'Informasi profil Anda berhasil diperbarui.');
            } catch (PDOException $e) {
                setFlash('danger', 'Email sudah digunakan oleh akun lain.');
            }
        }
    } elseif ($action === 'change_password') {
        $currentPwd = $_POST['current_password'] ?? '';
        $newPwd = $_POST['new_password'] ?? '';
        $confirmPwd = $_POST['confirm_password'] ?? '';

        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($currentPwd, (string)$hash)) {
            setFlash('danger', 'Kata sandi saat ini tidak sesuai.');
        } elseif (strlen($newPwd) < 6) {
            setFlash('danger', 'Kata sandi baru minimal 6 karakter.');
        } elseif ($newPwd !== $confirmPwd) {
            setFlash('danger', 'Konfirmasi kata sandi baru tidak cocok.');
        } else {
            $upd = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $upd->execute([password_hash($newPwd, PASSWORD_DEFAULT), $userId]);
            setFlash('success', 'Kata sandi Anda berhasil diperbarui secara aman.');
        }
    }

    header('Location: ' . BASE_URL . '/profile.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$profile = $stmt->fetch();

include __DIR__ . '/../includes/header.php';
?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card-custom p-4">
            <div class="text-center pb-3 mb-3 border-bottom">
                <div class="avatar-circle bg-gradient-sky text-white fw-extrabold mx-auto mb-3 shadow" style="width: 72px; height: 72px; font-size: 1.75rem;">
                    <?= e(strtoupper(substr($profile['full_name'] ?? 'U', 0, 1))) ?>
                </div>
                <h5 class="fw-bold mb-1"><?= e($profile['full_name']) ?></h5>
                <span class="badge bg-navy text-uppercase"><?= e($profile['role']) ?></span>
                <?php if (!empty($profile['nim_nidn'])): ?>
                    <span class="badge bg-sky-soft">NIM/NIDN: <?= e($profile['nim_nidn']) ?></span>
                <?php endif; ?>
            </div>

            <form method="POST" action="">
                <?= csrfInput() ?>
                <input type="hidden" name="action" value="update_profile">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Username Sistem (Tetap)</label>
                    <input type="text" class="form-control bg-light" value="<?= e($profile['username']) ?>" disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Nama Lengkap</label>
                    <input type="text" name="full_name" class="form-control" value="<?= e($profile['full_name']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Alamat Email</label>
                    <input type="email" name="email" class="form-control" value="<?= e($profile['email']) ?>" required>
                </div>
                <button type="submit" class="btn btn-sky w-100">
                    <i class="fa-solid fa-floppy-disk me-1"></i>Simpan Profil
                </button>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card-custom p-4">
            <h6 class="fw-bold mb-1"><i class="fa-solid fa-key text-sky me-2"></i>Ubah Kata Sandi Keamanan</h6>
            <p class="text-muted small mb-4">Kata sandi dienkripsi satu arah menggunakan algoritma standar <code>bcrypt (password_hash)</code>.</p>

            <form method="POST" action="">
                <?= csrfInput() ?>
                <input type="hidden" name="action" value="change_password">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Kata Sandi Saat Ini</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Kata Sandi Baru (Min. 6 Karakter)</label>
                        <input type="password" name="new_password" class="form-control" minlength="6" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Ulangi Kata Sandi Baru</label>
                        <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-navy">
                    <i class="fa-solid fa-shield-halved me-1"></i>Perbarui Kata Sandi
                </button>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

