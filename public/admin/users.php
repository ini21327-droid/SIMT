<?php
/**
 * Modul Admin: CRUD Pengguna (Admin, Dosen, Mahasiswa, Kaprodi)
 */
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

$pdo = getDB();
$pageTitle = 'Kelola Pengguna';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $role     = $_POST['role'] ?? 'mahasiswa';
        $nimNidn  = trim($_POST['nim_nidn'] ?? '') ?: null;
        $password = $_POST['password'] ?? 'password123';
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($username === '' || $fullName === '' || $email === '') {
            setFlash('danger', 'Username, Nama Lengkap, dan Email wajib diisi.');
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, password_hash, full_name, email, role, nim_nidn, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $username,
                    password_hash($password, PASSWORD_DEFAULT),
                    $fullName,
                    $email,
                    $role,
                    $nimNidn,
                    $isActive
                ]);
                setFlash('success', "Pengguna {$fullName} ({$role}) berhasil ditambahkan.");
            } catch (PDOException $e) {
                setFlash('danger', 'Gagal menambah pengguna: Username atau Email sudah terdaftar.');
            }
        }
    } elseif ($action === 'update') {
        $id       = (int)($_POST['id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $role     = $_POST['role'] ?? 'mahasiswa';
        $nimNidn  = trim($_POST['nim_nidn'] ?? '') ?: null;
        $password = $_POST['password'] ?? '';
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        try {
            if ($password !== '') {
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET username = ?, password_hash = ?, full_name = ?, email = ?, role = ?, nim_nidn = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $fullName, $email, $role, $nimNidn, $isActive, $id]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET username = ?, full_name = ?, email = ?, role = ?, nim_nidn = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([$username, $fullName, $email, $role, $nimNidn, $isActive, $id]);
            }
            setFlash('success', 'Data pengguna berhasil diperbarui.');
        } catch (PDOException $e) {
            setFlash('danger', 'Gagal memperbarui data pengguna: Username atau Email konflik.');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)currentUser()['id']) {
            setFlash('danger', 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif.');
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                setFlash('success', 'Pengguna berhasil dihapus.');
            } catch (PDOException $e) {
                setFlash('danger', 'Pengguna tidak dapat dihapus karena masih berelasi dengan mata kuliah/data akademik.');
            }
        }
    }

    header('Location: ' . BASE_URL . '/admin/users.php');
    exit;
}

$roleFilter = $_GET['role'] ?? '';
if (in_array($roleFilter, ['admin', 'dosen', 'mahasiswa', 'kaprodi'], true)) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE role = ? ORDER BY role ASC, full_name ASC");
    $stmt->execute([$roleFilter]);
    $users = $stmt->fetchAll();
} else {
    $users = $pdo->query("SELECT * FROM users ORDER BY role ASC, full_name ASC")->fetchAll();
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
    <div>
        <h4 class="fw-bold mb-1">Manajemen Pengguna Sistem</h4>
        <p class="text-muted small mb-0">Kelola akun Administrator, Dosen, Mahasiswa, dan Ketua Program Studi.</p>
    </div>
    <button type="button" class="btn btn-sky" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="fa-solid fa-user-plus me-1"></i>Tambah Pengguna Baru
    </button>
</div>

<div class="card-custom p-3 p-md-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="d-flex flex-wrap gap-2">
            <a href="users.php" class="btn btn-sm <?= $roleFilter === '' ? 'btn-navy' : 'btn-outline-secondary' ?>">Semua (<?= count($users) ?>)</a>
            <a href="users.php?role=dosen" class="btn btn-sm <?= $roleFilter === 'dosen' ? 'btn-navy' : 'btn-outline-secondary' ?>">Dosen</a>
            <a href="users.php?role=mahasiswa" class="btn btn-sm <?= $roleFilter === 'mahasiswa' ? 'btn-navy' : 'btn-outline-secondary' ?>">Mahasiswa</a>
            <a href="users.php?role=kaprodi" class="btn btn-sm <?= $roleFilter === 'kaprodi' ? 'btn-navy' : 'btn-outline-secondary' ?>">Kaprodi</a>
            <a href="users.php?role=admin" class="btn btn-sm <?= $roleFilter === 'admin' ? 'btn-navy' : 'btn-outline-secondary' ?>">Admin</a>
        </div>
        <input type="text" class="form-control form-control-sm" style="max-width: 240px;" placeholder="Cari nama, username, NIM..." data-table-search="#adminUsersTable">
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="adminUsersTable">
            <thead>
                <tr>
                    <th>Nama Lengkap</th>
                    <th>Username</th>
                    <th>NIM / NIDN</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($u['full_name']) ?></td>
                        <td><code><?= e($u['username']) ?></code></td>
                        <td><?= e($u['nim_nidn'] ?? '-') ?></td>
                        <td class="small"><?= e($u['email']) ?></td>
                        <td><span class="badge bg-navy-soft text-uppercase"><?= e($u['role']) ?></span></td>
                        <td>
                            <?php if ((int)$u['is_active'] === 1): ?>
                                <span class="badge badge-status-green">Aktif</span>
                            <?php else: ?>
                                <span class="badge badge-status-red">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary btn-edit-user"
                                data-id="<?= (int)$u['id'] ?>"
                                data-username="<?= e($u['username']) ?>"
                                data-fullname="<?= e($u['full_name']) ?>"
                                data-email="<?= e($u['email']) ?>"
                                data-role="<?= e($u['role']) ?>"
                                data-nim="<?= e($u['nim_nidn'] ?? '') ?>"
                                data-active="<?= (int)$u['is_active'] ?>"
                                data-bs-toggle="modal" data-bs-target="#editUserModal">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <?php if ((int)$u['id'] !== (int)currentUser()['id']): ?>
                                <form method="POST" action="" class="d-inline" onsubmit="return confirm('Hapus pengguna ini?');">
                                    <?= csrfInput() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah Pengguna -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="" class="modal-content">
            <?= csrfInput() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-header bg-navy text-white">
                <h6 class="modal-title fw-bold">Tambah Pengguna Baru</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Nama Lengkap & Gelar</label>
                    <input type="text" name="full_name" class="form-control" required>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Username Login</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">NIM / NIDN</label>
                        <input type="text" name="nim_nidn" class="form-control" placeholder="Opsional untuk Admin">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Role / Hak Akses</label>
                        <select name="role" class="form-select" required>
                            <option value="mahasiswa">Mahasiswa</option>
                            <option value="dosen">Dosen</option>
                            <option value="kaprodi">Kaprodi</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Password Awal</label>
                        <input type="text" name="password" class="form-control" value="password123" required>
                    </div>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_active" id="addActiveCheck" checked>
                    <label class="form-check-label small" for="addActiveCheck">Akun Aktif</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-sky">Simpan Pengguna</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Pengguna -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="" class="modal-content">
            <?= csrfInput() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editUserId">
            <div class="modal-header bg-navy text-white">
                <h6 class="modal-title fw-bold">Edit Data Pengguna</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Nama Lengkap</label>
                    <input type="text" name="full_name" id="editFullName" class="form-control" required>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Username</label>
                        <input type="text" name="username" id="editUsername" class="form-control" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">NIM / NIDN</label>
                        <input type="text" name="nim_nidn" id="editNimNidn" class="form-control">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Email</label>
                    <input type="email" name="email" id="editEmail" class="form-control" required>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Role</label>
                        <select name="role" id="editRole" class="form-select" required>
                            <option value="mahasiswa">Mahasiswa</option>
                            <option value="dosen">Dosen</option>
                            <option value="kaprodi">Kaprodi</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Password Baru (Opsional)</label>
                        <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tetap">
                    </div>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_active" id="editActiveCheck">
                    <label class="form-check-label small" for="editActiveCheck">Akun Aktif</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-sky">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.btn-edit-user').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('editUserId').value = btn.dataset.id;
        document.getElementById('editUsername').value = btn.dataset.username;
        document.getElementById('editFullName').value = btn.dataset.fullname;
        document.getElementById('editEmail').value = btn.dataset.email;
        document.getElementById('editRole').value = btn.dataset.role;
        document.getElementById('editNimNidn').value = btn.dataset.nim;
        document.getElementById('editActiveCheck').checked = (btn.dataset.active === '1');
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

