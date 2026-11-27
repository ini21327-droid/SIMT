<?php
/**
 * Modul Admin: CRUD Semester Akademik
 */
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

$pdo = getDB();
$pageTitle = 'Semester Akademik';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($name !== '') {
            if ($isActive === 1) {
                $pdo->exec("UPDATE semesters SET is_active = 0");
            }
            $stmt = $pdo->prepare("INSERT INTO semesters (name, is_active) VALUES (?, ?)");
            $stmt->execute([$name, $isActive]);
            setFlash('success', "Semester '{$name}' berhasil ditambahkan.");
        }
    } elseif ($action === 'activate') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->exec("UPDATE semesters SET is_active = 0");
        $stmt = $pdo->prepare("UPDATE semesters SET is_active = 1 WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('success', 'Semester aktif berhasil diperbarui.');
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM semesters WHERE id = ?");
            $stmt->execute([$id]);
            setFlash('success', 'Semester berhasil dihapus.');
        } catch (PDOException $e) {
            setFlash('danger', 'Semester tidak dapat dihapus karena sudah memiliki Mata Kuliah terkait.');
        }
    }

    header('Location: ' . BASE_URL . '/admin/semesters.php');
    exit;
}

$semesters = $pdo->query("
    SELECT s.*, (SELECT COUNT(*) FROM courses c WHERE c.semester_id = s.id) AS total_courses
    FROM semesters s
    ORDER BY s.is_active DESC, s.id DESC
")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card-custom p-3 p-md-4">
            <h6 class="fw-bold mb-3"><i class="fa-solid fa-calendar-plus text-sky me-2"></i>Tambah Semester Baru</h6>
            <form method="POST" action="">
                <?= csrfInput() ?>
                <input type="hidden" name="action" value="create">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Nama Semester</label>
                    <input type="text" name="name" class="form-control" placeholder="Contoh: Genap 2026/2027" required>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="is_active" id="semActiveCheck">
                    <label class="form-check-label small" for="semActiveCheck">Jadikan sebagai Semester Aktif</label>
                </div>
                <button type="submit" class="btn btn-sky w-100">Simpan Semester</button>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card-custom p-3 p-md-4">
            <h6 class="fw-bold mb-3">Daftar Semester Akademik</h6>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama Semester</th>
                            <th>Jumlah Mata Kuliah</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($semesters as $sem): ?>
                            <tr>
                                <td>#<?= (int)$sem['id'] ?></td>
                                <td class="fw-semibold"><?= e($sem['name']) ?></td>
                                <td><?= (int)$sem['total_courses'] ?> Mata Kuliah</td>
                                <td>
                                    <?php if ((int)$sem['is_active'] === 1): ?>
                                        <span class="badge badge-status-green"><i class="fa-solid fa-check me-1"></i>Semester Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border">Tidak Aktif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ((int)$sem['is_active'] === 0): ?>
                                        <form method="POST" action="" class="d-inline">
                                            <?= csrfInput() ?>
                                            <input type="hidden" name="action" value="activate">
                                            <input type="hidden" name="id" value="<?= (int)$sem['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-success">Set Aktif</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" action="" class="d-inline" onsubmit="return confirm('Hapus semester ini?');">
                                        <?= csrfInput() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$sem['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

