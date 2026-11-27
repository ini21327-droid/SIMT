<?php
/**
 * Modul Dosen: Buat & Kelola Tugas Perkuliahan + Auto-Generate Kode Tugas & Deadline
 */
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['dosen', 'admin']);

$pdo = getDB();
$user = currentUser();
$userId = (int)$user['id'];
$isAdmin = ($user['role'] === 'admin');
$pageTitle = 'Kelola & Nilai Tugas Mahasiswa';

if ($isAdmin) {
    $classes = $pdo->query("
        SELECT cl.id, cl.class_name, c.course_code, c.course_name, c.dosen_id
        FROM classes cl
        JOIN courses c ON cl.course_id = c.id
        ORDER BY c.course_code ASC, cl.class_name ASC
    ")->fetchAll();
} else {
    $clsStmt = $pdo->prepare("
        SELECT cl.id, cl.class_name, c.course_code, c.course_name, c.dosen_id
        FROM classes cl
        JOIN courses c ON cl.course_id = c.id
        WHERE c.dosen_id = ?
        ORDER BY c.course_code ASC, cl.class_name ASC
    ");
    $clsStmt->execute([$userId]);
    $classes = $clsStmt->fetchAll();
}

$allowedClasses = [];
foreach ($classes as $cItem) {
    $allowedClasses[(int)$cItem['id']] = (int)$cItem['dosen_id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_task') {
        $classId     = (int)($_POST['class_id'] ?? 0);
        $customCode  = strtoupper(trim($_POST['task_code'] ?? ''));
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $deadline    = $_POST['deadline'] ?? '';

        if (!isset($allowedClasses[$classId])) {
            setFlash('danger', 'Kelas tidak valid.');
        } elseif ($title !== '' && $deadline !== '') {
            $taskCode = $customCode !== '' ? $customCode : generateTaskCode($pdo, $classId);
            $attachmentPath = null;

            if (!empty($_FILES['attachment']['name'])) {
                $upload = handleTaskFileUpload($_FILES['attachment'], 'soal');
                if (!$upload['success']) {
                    setFlash('danger', $upload['error']);
                    header('Location: ' . BASE_URL . '/dosen/tasks.php');
                    exit;
                }
                $attachmentPath = $upload['file_path'];
            }

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO tasks (class_id, dosen_id, task_code, title, description, attachment_path, deadline)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $classId,
                    $allowedClasses[$classId],
                    $taskCode,
                    $title,
                    $description,
                    $attachmentPath,
                    date('Y-m-d H:i:s', strtotime($deadline))
                ]);
                setFlash('success', "Tugas baru berhasil diterbitkan dengan Kode: {$taskCode}");
            } catch (PDOException $e) {
                setFlash('danger', "Gagal membuat tugas: Kode Tugas '{$taskCode}' sudah digunakan.");
            }
        } else {
            setFlash('danger', 'Mohon lengkapi Kelas, Judul Tugas, dan Batas Waktu (Deadline).');
        }
    } elseif ($action === 'delete_task') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND (dosen_id = ? OR ? = 1)");
        $stmt->execute([$taskId, $userId, $isAdmin ? 1 : 0]);
        setFlash('success', 'Tugas berhasil dihapus.');
    }

    header('Location: ' . BASE_URL . '/dosen/tasks.php');
    exit;
}

if ($isAdmin) {
    $tasks = $pdo->query("
        SELECT t.*, cl.class_name, c.course_code, c.course_name, u.full_name AS dosen_name,
               (SELECT COUNT(*) FROM class_enrollments ce WHERE ce.class_id = t.class_id) AS total_students,
               (SELECT COUNT(*) FROM task_submissions ts WHERE ts.task_id = t.id) AS submitted_count,
               (SELECT COUNT(*) FROM task_submissions ts WHERE ts.task_id = t.id AND ts.review_status = 'graded') AS graded_count
        FROM tasks t
        JOIN classes cl ON t.class_id = cl.id
        JOIN courses c ON cl.course_id = c.id
        JOIN users u ON t.dosen_id = u.id
        ORDER BY t.created_at DESC
    ")->fetchAll();
} else {
    $tStmt = $pdo->prepare("
        SELECT t.*, cl.class_name, c.course_code, c.course_name, u.full_name AS dosen_name,
               (SELECT COUNT(*) FROM class_enrollments ce WHERE ce.class_id = t.class_id) AS total_students,
               (SELECT COUNT(*) FROM task_submissions ts WHERE ts.task_id = t.id) AS submitted_count,
               (SELECT COUNT(*) FROM task_submissions ts WHERE ts.task_id = t.id AND ts.review_status = 'graded') AS graded_count
        FROM tasks t
        JOIN classes cl ON t.class_id = cl.id
        JOIN courses c ON cl.course_id = c.id
        JOIN users u ON t.dosen_id = u.id
        WHERE t.dosen_id = ?
        ORDER BY t.created_at DESC
    ");
    $tStmt->execute([$userId]);
    $tasks = $tStmt->fetchAll();
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
    <div>
        <h4 class="fw-bold mb-1">Manajemen Tugas &amp; Penilaian Mahasiswa</h4>
        <p class="text-muted small mb-0">Buat tugas dengan kode otomatis, atur <em>deadline</em> server-side, dan periksa pengumpulan mahasiswa.</p>
    </div>
    <button type="button" class="btn btn-sky" data-bs-toggle="modal" data-bs-target="#createTaskModal">
        <i class="fa-solid fa-plus me-1"></i>Terbitkan Tugas Baru
    </button>
</div>

<div class="card-custom p-3 p-md-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h6 class="fw-bold mb-0">Daftar Tugas Perkuliahan (<?= count($tasks) ?>)</h6>
        <input type="text" class="form-control form-control-sm" style="max-width: 250px;" placeholder="Cari kode / judul tugas..." data-table-search="#lecturerTasksTable">
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="lecturerTasksTable">
            <thead>
                <tr>
                    <th>Kode Tugas</th>
                    <th>Judul &amp; Mata Kuliah</th>
                    <th>Deadline Server</th>
                    <th>Status Waktu</th>
                    <th>Progres Kumpul</th>
                    <th>Dinilai</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tasks)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Belum ada tugas yang diterbitkan.</td></tr>
                <?php else: ?>
                    <?php foreach ($tasks as $t): ?>
                        <?php
                        $cd = getDeadlineCountdown($t['deadline']);
                        $totalMhs = (int)$t['total_students'];
                        $subCount = (int)$t['submitted_count'];
                        $pct = $totalMhs > 0 ? round(($subCount / $totalMhs) * 100) : 0;
                        ?>
                        <tr>
                            <td>
                                <code class="fw-bold"><?= e($t['task_code']) ?></code>
                                <button type="button" class="btn btn-link btn-sm p-0 ms-1 text-muted" data-copy-code="<?= e($t['task_code']) ?>" data-copy-label="Kode Tugas" title="Salin Kode">
                                    <i class="fa-regular fa-copy"></i>
                                </button>
                            </td>
                            <td>
                                <div class="fw-bold"><?= e($t['title']) ?></div>
                                <small class="text-muted"><?= e($t['course_code']) ?> - <?= e($t['course_name']) ?> (Kelas <?= e($t['class_name']) ?>)</small>
                            </td>
                            <td class="small"><?= e(formatDateTime($t['deadline'])) ?></td>
                            <td>
                                <span class="badge <?= $cd['expired'] ? 'badge-status-red' : 'badge-status-green' ?>"
                                      <?= !$cd['expired'] ? 'data-countdown="' . e($t['deadline']) . '"' : '' ?>>
                                    <?= e($cd['label']) ?>
                                </span>
                            </td>
                            <td style="min-width: 150px;">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span class="fw-semibold text-success"><?= $subCount ?> / <?= $totalMhs ?> Mhs</span>
                                    <span class="text-muted"><?= $pct ?>%</span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-sky" style="width: <?= min(100, $pct) ?>%"></div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-sky-soft"><?= (int)$t['graded_count'] ?> / <?= $subCount ?></span>
                            </td>
                            <td class="text-end">
                                <a href="<?= e(BASE_URL) ?>/dosen/task_detail.php?id=<?= (int)$t['id'] ?>" class="btn btn-sm btn-sky">
                                    <i class="fa-solid fa-clipboard-check me-1"></i>Periksa
                                </a>
                                <form method="POST" action="" class="d-inline" onsubmit="return confirm('Hapus tugas ini beserta seluruh submission mahasiswa?');">
                                    <?= csrfInput() ?>
                                    <input type="hidden" name="action" value="delete_task">
                                    <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Buat Tugas Baru -->
<div class="modal fade" id="createTaskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" action="" enctype="multipart/form-data" class="modal-content modern-modal border-0 shadow-lg overflow-hidden">
            <?= csrfInput() ?>
            <input type="hidden" name="action" value="create_task">
            <div class="modal-header bg-navy text-white border-0">
                <h6 class="modal-title fw-bold"><i class="fa-solid fa-file-circle-plus text-sky me-2"></i>Buat Tugas Perkuliahan Baru</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 mb-3">
                    <div class="col-md-7">
                        <label class="form-label small fw-semibold">Pilih Kelas Perkuliahan</label>
                        <select name="class_id" class="form-select" required>
                            <?php foreach ($classes as $cl): ?>
                                <option value="<?= (int)$cl['id'] ?>">
                                    <?= e($cl['course_code']) ?> - <?= e($cl['course_name']) ?> (Kelas <?= e($cl['class_name']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold">Kode Tugas (Opsional)</label>
                        <input type="text" name="task_code" class="form-control text-uppercase" placeholder="Otomatis: TGS-IF201-XX">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Judul Tugas</label>
                    <input type="text" name="title" class="form-control" placeholder="Contoh: Implementasi REST API & Validasi Keamanan" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Deskripsi &amp; Kriteria Penilaian</label>
                    <textarea name="description" rows="4" class="form-control" placeholder="Tuliskan instruksi lengkap, format dokumen, dan bobot penilaian..." required></textarea>
                </div>
                <div class="row g-3 align-items-center">
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold">Batas Waktu Pengumpulan (WIB)</label>
                        <input type="datetime-local" name="deadline" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime('+7 days')) ?>" required>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label small fw-semibold">Lampiran Soal (Opsional — Maks 10 MB)</label>
                        <div class="upload-dropzone py-3">
                            <input type="file" name="attachment" class="d-none" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                            <div class="small text-muted">
                                <i class="fa-solid fa-cloud-arrow-up text-sky fs-5 d-block mb-1"></i>
                                Klik atau seret berkas soal ke sini (<code>PDF, DOCX, JPG, PNG</code>)
                            </div>
                            <div class="dropzone-filename"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-sky px-4">Terbitkan Tugas</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
