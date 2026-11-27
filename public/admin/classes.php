<?php
/**
 * Modul Admin: CRUD Kelas Perkuliahan & Jadwal (Create, Edit, Delete + Auto-Lookup Kode)
 */
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

$pdo = getDB();
$pageTitle = 'Kelas & Jadwal Perkuliahan';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $courseId  = (int)($_POST['course_id'] ?? 0);
        $className = trim($_POST['class_name'] ?? '');
        $day       = trim($_POST['schedule_day'] ?? 'Senin');
        $startTime = $_POST['start_time'] ?? '08:00';
        $endTime   = $_POST['end_time'] ?? '10:30';
        $room      = trim($_POST['room'] ?? '');

        if ($courseId > 0 && $className !== '') {
            $stmt = $pdo->prepare("
                INSERT INTO classes (course_id, class_name, schedule_day, start_time, end_time, room)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$courseId, $className, $day, $startTime, $endTime, $room]);
            setFlash('success', "Kelas {$className} berhasil dibuat.");
        }
    } elseif ($action === 'update') {
        $id        = (int)($_POST['id'] ?? 0);
        $courseId  = (int)($_POST['course_id'] ?? 0);
        $className = trim($_POST['class_name'] ?? '');
        $day       = trim($_POST['schedule_day'] ?? 'Senin');
        $startTime = $_POST['start_time'] ?? '08:00';
        $endTime   = $_POST['end_time'] ?? '10:30';
        $room      = trim($_POST['room'] ?? '');

        if ($id > 0 && $courseId > 0 && $className !== '') {
            $stmt = $pdo->prepare("
                UPDATE classes
                SET course_id = ?, class_name = ?, schedule_day = ?, start_time = ?, end_time = ?, room = ?
                WHERE id = ?
            ");
            $stmt->execute([$courseId, $className, $day, $startTime, $endTime, $room, $id]);
            setFlash('success', "Jadwal kelas {$className} berhasil diperbarui.");
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('success', 'Kelas berhasil dihapus.');
    }

    header('Location: ' . BASE_URL . '/admin/classes.php');
    exit;
}

$classes = $pdo->query("
    SELECT cl.*, c.course_code, c.course_name, c.sks, u.full_name AS dosen_name,
           (SELECT COUNT(*) FROM class_enrollments ce WHERE ce.class_id = cl.id) AS student_count
    FROM classes cl
    JOIN courses c ON cl.course_id = c.id
    JOIN users u ON c.dosen_id = u.id
    ORDER BY c.course_code ASC, cl.class_name ASC
")->fetchAll();

$courses = $pdo->query("
    SELECT c.id, c.course_code, c.course_name, u.full_name AS dosen_name
    FROM courses c
    JOIN users u ON c.dosen_id = u.id
    ORDER BY c.course_code ASC
")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card-custom p-3 p-md-4">
            <h6 class="fw-bold mb-3"><i class="fa-solid fa-plus-circle text-sky me-2"></i>Buka Kelas Baru</h6>
            <form method="POST" action="">
                <?= csrfInput() ?>
                <input type="hidden" name="action" value="create">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Pilih Mata Kuliah (atau Ketik Kode)</label>
                    <div class="input-group mb-2">
                        <input type="text" id="quickCourseCode" class="form-control form-control-sm text-uppercase" placeholder="Ketik kode misal IF201...">
                        <button class="btn btn-sm btn-outline-primary" type="button" id="btnAutoFillCourse">Muat Kode</button>
                    </div>
                    <select name="course_id" id="courseSelect" class="form-select" required>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" data-code="<?= e($c['course_code']) ?>">
                                <?= e($c['course_code']) ?> - <?= e($c['course_name']) ?> (<?= e($c['dosen_name']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Nama Kelas</label>
                        <input type="text" name="class_name" class="form-control" placeholder="TI-3A" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Hari</label>
                        <select name="schedule_day" class="form-select">
                            <?php foreach (['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'] as $hari): ?>
                                <option value="<?= $hari ?>"><?= $hari ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Jam Mulai</label>
                        <input type="time" name="start_time" class="form-control" value="08:00" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Jam Selesai</label>
                        <input type="time" name="end_time" class="form-control" value="10:30" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Ruangan</label>
                    <input type="text" name="room" class="form-control" placeholder="Lab Komputer 1 / R.302" required>
                </div>
                <button type="submit" class="btn btn-sky w-100">Simpan Kelas</button>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card-custom p-3 p-md-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h6 class="fw-bold mb-0">Daftar Kelas Perkuliahan Aktif (<?= count($classes) ?>)</h6>
                <input type="text" class="form-control form-control-sm" style="max-width: 230px;" placeholder="Cari kelas / ruangan..." data-table-search="#adminClassesTable">
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="adminClassesTable">
                    <thead>
                        <tr>
                            <th>Kelas</th>
                            <th>Mata Kuliah</th>
                            <th>Dosen</th>
                            <th>Jadwal &amp; Ruangan</th>
                            <th>Peserta</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($classes as $cl): ?>
                            <tr>
                                <td><span class="badge bg-navy"><?= e($cl['class_name']) ?></span></td>
                                <td>
                                    <div class="fw-semibold"><?= e($cl['course_code']) ?> - <?= e($cl['course_name']) ?></div>
                                    <small class="text-muted"><?= (int)$cl['sks'] ?> SKS</small>
                                </td>
                                <td><?= e($cl['dosen_name']) ?></td>
                                <td>
                                    <div><?= e($cl['schedule_day']) ?>, <?= e(substr($cl['start_time'], 0, 5)) ?>–<?= e(substr($cl['end_time'], 0, 5)) ?></div>
                                    <small class="text-sky fw-medium"><i class="fa-solid fa-door-open me-1"></i><?= e($cl['room']) ?></small>
                                </td>
                                <td>
                                    <a href="<?= e(BASE_URL) ?>/admin/enrollments.php?class_id=<?= (int)$cl['id'] ?>" class="badge bg-sky-soft text-decoration-none">
                                        <?= (int)$cl['student_count'] ?> Mahasiswa
                                    </a>
                                </td>
                                <td class="text-end">
                                    <a href="<?= e(BASE_URL) ?>/admin/enrollments.php?class_id=<?= (int)$cl['id'] ?>" class="btn btn-sm btn-outline-success" title="Kelola Peserta">
                                        <i class="fa-solid fa-users"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-edit-class"
                                        data-id="<?= (int)$cl['id'] ?>"
                                        data-course="<?= (int)$cl['course_id'] ?>"
                                        data-name="<?= e($cl['class_name']) ?>"
                                        data-day="<?= e($cl['schedule_day']) ?>"
                                        data-start="<?= e(substr($cl['start_time'], 0, 5)) ?>"
                                        data-end="<?= e(substr($cl['end_time'], 0, 5)) ?>"
                                        data-room="<?= e($cl['room']) ?>"
                                        data-bs-toggle="modal" data-bs-target="#editClassModal">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <form method="POST" action="" class="d-inline" onsubmit="return confirm('Hapus kelas ini?');">
                                        <?= csrfInput() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$cl['id'] ?>">
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

<!-- Modal Edit Kelas -->
<div class="modal fade" id="editClassModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="" class="modal-content modern-modal border-0 shadow-lg overflow-hidden">
            <?= csrfInput() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editClassId">
            <div class="modal-header bg-navy text-white border-0">
                <h6 class="modal-title fw-bold">Edit Jadwal &amp; Ruangan Kelas</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Mata Kuliah</label>
                    <select name="course_id" id="editClassCourse" class="form-select" required>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= e($c['course_code']) ?> - <?= e($c['course_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Nama Kelas</label>
                        <input type="text" name="class_name" id="editClassName" class="form-control" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Hari</label>
                        <select name="schedule_day" id="editClassDay" class="form-select">
                            <?php foreach (['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'] as $hari): ?>
                                <option value="<?= $hari ?>"><?= $hari ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Jam Mulai</label>
                        <input type="time" name="start_time" id="editClassStart" class="form-control" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Jam Selesai</label>
                        <input type="time" name="end_time" id="editClassEnd" class="form-control" required>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Ruangan</label>
                    <input type="text" name="room" id="editClassRoom" class="form-control" required>
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
document.getElementById('btnAutoFillCourse')?.addEventListener('click', () => {
    const code = document.getElementById('quickCourseCode').value.trim().toUpperCase();
    const sel = document.getElementById('courseSelect');
    for (const opt of sel.options) {
        if (opt.dataset.code === code) {
            sel.value = opt.value;
            if (typeof AATMS_UI !== 'undefined') AATMS_UI.toast('Mata kuliah ' + code + ' terpilih!', 'success');
            return;
        }
    }
    if (typeof AATMS_UI !== 'undefined') AATMS_UI.toast('Kode mata kuliah ' + code + ' tidak ditemukan.', 'danger');
});

document.querySelectorAll('.btn-edit-class').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('editClassId').value = btn.dataset.id;
        document.getElementById('editClassCourse').value = btn.dataset.course;
        document.getElementById('editClassName').value = btn.dataset.name;
        document.getElementById('editClassDay').value = btn.dataset.day;
        document.getElementById('editClassStart').value = btn.dataset.start;
        document.getElementById('editClassEnd').value = btn.dataset.end;
        document.getElementById('editClassRoom').value = btn.dataset.room;
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
