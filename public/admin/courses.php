<?php
/**
 * Modul Admin: CRUD Mata Kuliah (dengan Kode Unik untuk Pemanggilan Otomatis)
 */
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

$pdo = getDB();
$pageTitle = 'Kelola Mata Kuliah';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $code       = strtoupper(trim($_POST['course_code'] ?? ''));
        $name       = trim($_POST['course_name'] ?? '');
        $sks        = (int)($_POST['sks'] ?? 3);
        $dosenId    = (int)($_POST['dosen_id'] ?? 0);
        $semesterId = (int)($_POST['semester_id'] ?? 0);

        try {
            $stmt = $pdo->prepare("
                INSERT INTO courses (course_code, course_name, sks, dosen_id, semester_id)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$code, $name, $sks, $dosenId, $semesterId]);
            setFlash('success', "Mata Kuliah {$code} - {$name} berhasil ditambahkan.");
        } catch (PDOException $e) {
            setFlash('danger', "Gagal menambah mata kuliah: Kode '{$code}' sudah digunakan.");
        }
    } elseif ($action === 'update') {
        $id         = (int)($_POST['id'] ?? 0);
        $code       = strtoupper(trim($_POST['course_code'] ?? ''));
        $name       = trim($_POST['course_name'] ?? '');
        $sks        = (int)($_POST['sks'] ?? 3);
        $dosenId    = (int)($_POST['dosen_id'] ?? 0);
        $semesterId = (int)($_POST['semester_id'] ?? 0);

        try {
            $stmt = $pdo->prepare("
                UPDATE courses
                SET course_code = ?, course_name = ?, sks = ?, dosen_id = ?, semester_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$code, $name, $sks, $dosenId, $semesterId, $id]);
            setFlash('success', "Mata Kuliah {$code} berhasil diperbarui.");
        } catch (PDOException $e) {
            setFlash('danger', 'Gagal memperbarui mata kuliah: Kode mata kuliah harus unik.');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
            $stmt->execute([$id]);
            setFlash('success', 'Mata kuliah berhasil dihapus.');
        } catch (PDOException $e) {
            setFlash('danger', 'Gagal menghapus mata kuliah.');
        }
    }

    header('Location: ' . BASE_URL . '/admin/courses.php');
    exit;
}

$courses = $pdo->query("
    SELECT c.*, u.full_name AS dosen_name, u.nim_nidn AS dosen_nidn, s.name AS semester_name,
           (SELECT COUNT(*) FROM classes cl WHERE cl.course_id = c.id) AS total_classes
    FROM courses c
    JOIN users u ON c.dosen_id = u.id
    JOIN semesters s ON c.semester_id = s.id
    ORDER BY c.course_code ASC
")->fetchAll();

$dosens = $pdo->query("SELECT id, full_name, nim_nidn FROM users WHERE role = 'dosen' AND is_active = 1 ORDER BY full_name ASC")->fetchAll();
$semesters = $pdo->query("SELECT id, name, is_active FROM semesters ORDER BY is_active DESC, id DESC")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
    <div>
        <h4 class="fw-bold mb-1">Manajemen Mata Kuliah</h4>
        <p class="text-muted small mb-0">Setiap mata kuliah memiliki <strong>Kode Unik</strong> (misal <code>IF201</code>) untuk fitur pemanggilan data otomatis.</p>
    </div>
    <button type="button" class="btn btn-sky" data-bs-toggle="modal" data-bs-target="#addCourseModal">
        <i class="fa-solid fa-plus me-1"></i>Tambah Mata Kuliah
    </button>
</div>

<div class="card-custom p-3 p-md-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h6 class="fw-bold mb-0">Daftar Mata Kuliah Terdaftar (<?= count($courses) ?>)</h6>
        <input type="text" class="form-control form-control-sm" style="max-width: 250px;" placeholder="Cari kode / nama matkul..." data-table-search="#adminCoursesTable">
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="adminCoursesTable">
            <thead>
                <tr>
                    <th>Kode Matkul</th>
                    <th>Nama Mata Kuliah</th>
                    <th>SKS</th>
                    <th>Dosen Pengampu</th>
                    <th>Semester</th>
                    <th>Kelas</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($courses as $c): ?>
                    <tr>
                        <td><span class="badge bg-sky"><?= e($c['course_code']) ?></span></td>
                        <td class="fw-semibold"><?= e($c['course_name']) ?></td>
                        <td><?= (int)$c['sks'] ?> SKS</td>
                        <td>
                            <div><?= e($c['dosen_name']) ?></div>
                            <small class="text-muted">NIDN: <?= e($c['dosen_nidn'] ?? '-') ?></small>
                        </td>
                        <td><?= e($c['semester_name']) ?></td>
                        <td><span class="badge bg-light text-dark border"><?= (int)$c['total_classes'] ?> Kelas</span></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary btn-edit-course"
                                data-id="<?= (int)$c['id'] ?>"
                                data-code="<?= e($c['course_code']) ?>"
                                data-name="<?= e($c['course_name']) ?>"
                                data-sks="<?= (int)$c['sks'] ?>"
                                data-dosen="<?= (int)$c['dosen_id'] ?>"
                                data-semester="<?= (int)$c['semester_id'] ?>"
                                data-bs-toggle="modal" data-bs-target="#editCourseModal">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <form method="POST" action="" class="d-inline" onsubmit="return confirm('Hapus mata kuliah ini beserta kelas terkait?');">
                                <?= csrfInput() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah Mata Kuliah -->
<div class="modal fade" id="addCourseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="" class="modal-content">
            <?= csrfInput() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-header bg-navy text-white">
                <h6 class="modal-title fw-bold">Tambah Mata Kuliah</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3">
                    <div class="col-5">
                        <label class="form-label small fw-semibold">Kode Mata Kuliah</label>
                        <input type="text" name="course_code" class="form-control text-uppercase" placeholder="Misal: IF204" required>
                    </div>
                    <div class="col-7">
                        <label class="form-label small fw-semibold">Bobot SKS</label>
                        <input type="number" name="sks" class="form-control" min="1" max="6" value="3" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Nama Mata Kuliah</label>
                    <input type="text" name="course_name" class="form-control" placeholder="Contoh: Kecerdasan Buatan" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Dosen Pengampu</label>
                    <select name="dosen_id" class="form-select" required>
                        <?php foreach ($dosens as $d): ?>
                            <option value="<?= (int)$d['id'] ?>"><?= e($d['full_name']) ?> (<?= e($d['nim_nidn'] ?? '-') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Semester</label>
                    <select name="semester_id" class="form-select" required>
                        <?php foreach ($semesters as $s): ?>
                            <option value="<?= (int)$s['id'] ?>" <?= (int)$s['is_active'] === 1 ? 'selected' : '' ?>>
                                <?= e($s['name']) ?><?= (int)$s['is_active'] === 1 ? ' (Aktif)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-sky">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Mata Kuliah -->
<div class="modal fade" id="editCourseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="" class="modal-content">
            <?= csrfInput() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editCourseId">
            <div class="modal-header bg-navy text-white">
                <h6 class="modal-title fw-bold">Edit Mata Kuliah</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3">
                    <div class="col-5">
                        <label class="form-label small fw-semibold">Kode Mata Kuliah</label>
                        <input type="text" name="course_code" id="editCourseCode" class="form-control text-uppercase" required>
                    </div>
                    <div class="col-7">
                        <label class="form-label small fw-semibold">Bobot SKS</label>
                        <input type="number" name="sks" id="editCourseSks" class="form-control" min="1" max="6" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Nama Mata Kuliah</label>
                    <input type="text" name="course_name" id="editCourseName" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Dosen Pengampu</label>
                    <select name="dosen_id" id="editCourseDosen" class="form-select" required>
                        <?php foreach ($dosens as $d): ?>
                            <option value="<?= (int)$d['id'] ?>"><?= e($d['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Semester</label>
                    <select name="semester_id" id="editCourseSemester" class="form-select" required>
                        <?php foreach ($semesters as $s): ?>
                            <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
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
document.querySelectorAll('.btn-edit-course').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('editCourseId').value = btn.dataset.id;
        document.getElementById('editCourseCode').value = btn.dataset.code;
        document.getElementById('editCourseName').value = btn.dataset.name;
        document.getElementById('editCourseSks').value = btn.dataset.sks;
        document.getElementById('editCourseDosen').value = btn.dataset.dosen;
        document.getElementById('editCourseSemester').value = btn.dataset.semester;
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

