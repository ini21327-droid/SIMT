<?php
/**
 * Modul Mahasiswa: Lihat & Kumpulkan Tugas Multi-Format (Teks / PDF / Word / Foto)
 * dengan Penguncian Deadline Otomatis Server-Side & Drag-and-Drop Upload
 */
require_once __DIR__ . '/../../includes/auth.php';
requireRole('mahasiswa');

$pdo = getDB();
$user = currentUser();
$studentId = (int)$user['id'];
$pageTitle = 'Tugas Perkuliahan & Pengumpulan';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $taskId     = (int)($_POST['task_id'] ?? 0);
    $textAnswer = trim($_POST['text_answer'] ?? '');

    $tStmt = $pdo->prepare("
        SELECT t.*
        FROM tasks t
        JOIN class_enrollments ce ON ce.class_id = t.class_id AND ce.student_id = ?
        WHERE t.id = ?
        LIMIT 1
    ");
    $tStmt->execute([$studentId, $taskId]);
    $taskRow = $tStmt->fetch();

    if (!$taskRow) {
        setFlash('danger', 'Tugas tidak ditemukan atau Anda tidak terdaftar pada kelas tersebut.');
        header('Location: ' . BASE_URL . '/mahasiswa/tasks.php');
        exit;
    }

    // Validasi Deadline Wajib di Backend PHP menggunakan waktu server Asia/Jakarta (Spesifikasi 6.3)
    if (time() > strtotime($taskRow['deadline'])) {
        setFlash('danger', 'Pengumpulan ditolak oleh server: Batas waktu (Deadline) tugas ini telah berakhir pada ' . formatDateTime($taskRow['deadline']) . '.');
        header('Location: ' . BASE_URL . '/mahasiswa/tasks.php?task_id=' . $taskId);
        exit;
    }

    $subStmt = $pdo->prepare("SELECT * FROM task_submissions WHERE task_id = ? AND student_id = ?");
    $subStmt->execute([$taskId, $studentId]);
    $existingSub = $subStmt->fetch();

    if ($existingSub && $existingSub['review_status'] === 'graded') {
        setFlash('danger', 'Tugas Anda sudah dinilai oleh Dosen dan tidak dapat diubah lagi.');
        header('Location: ' . BASE_URL . '/mahasiswa/tasks.php?task_id=' . $taskId);
        exit;
    }

    $hasNewFile = !empty($_FILES['submission_file']['name']);
    if ($textAnswer === '' && !$hasNewFile && empty($existingSub['file_path'])) {
        setFlash('danger', 'Mohon isi Jawaban Teks atau unggah File Tugas (.pdf, .doc, .docx, .jpg, .png).');
        header('Location: ' . BASE_URL . '/mahasiswa/tasks.php?task_id=' . $taskId);
        exit;
    }

    $filePath = $existingSub['file_path'] ?? null;
    $fileType = $existingSub['file_type'] ?? ($textAnswer !== '' ? 'text/plain' : null);

    if ($hasNewFile) {
        $upload = handleTaskFileUpload($_FILES['submission_file'], 'mhs' . $studentId);
        if (!$upload['success']) {
            setFlash('danger', $upload['error']);
            header('Location: ' . BASE_URL . '/mahasiswa/tasks.php?task_id=' . $taskId);
            exit;
        }
        $filePath = $upload['file_path'];
        $fileType = $upload['file_type'];
    }

    $stmt = $pdo->prepare("
        INSERT INTO task_submissions (task_id, student_id, text_answer, file_path, file_type, submitted_at, review_status)
        VALUES (?, ?, ?, ?, ?, NOW(), 'submitted')
        ON DUPLICATE KEY UPDATE
            text_answer = VALUES(text_answer),
            file_path = VALUES(file_path),
            file_type = VALUES(file_type),
            submitted_at = NOW(),
            review_status = 'submitted'
    ");
    $stmt->execute([
        $taskId,
        $studentId,
        $textAnswer !== '' ? $textAnswer : null,
        $filePath,
        $fileType
    ]);

    setFlash('success', "Tugas {$taskRow['task_code']} berhasil dikumpulkan ke server!");
    header('Location: ' . BASE_URL . '/mahasiswa/tasks.php?task_id=' . $taskId);
    exit;
}

$stmt = $pdo->prepare("
    SELECT t.*, cl.class_name, c.course_code, c.course_name, u.full_name AS dosen_name,
           ts.id AS submission_id, ts.text_answer, ts.file_path, ts.file_type,
           ts.submitted_at, ts.score, ts.feedback, ts.review_status, ts.graded_at
    FROM tasks t
    JOIN class_enrollments ce ON t.class_id = ce.class_id
    JOIN classes cl ON t.class_id = cl.id
    JOIN courses c ON cl.course_id = c.id
    JOIN users u ON t.dosen_id = u.id
    LEFT JOIN task_submissions ts ON ts.task_id = t.id AND ts.student_id = ?
    WHERE ce.student_id = ?
    ORDER BY t.deadline ASC
");
$stmt->execute([$studentId, $studentId]);
$tasks = $stmt->fetchAll();

$selectedTaskId = (int)($_GET['task_id'] ?? ($tasks[0]['id'] ?? 0));
$activeTask = null;
foreach ($tasks as $t) {
    if ((int)$t['id'] === $selectedTaskId) {
        $activeTask = $t;
        break;
    }
}
if (!$activeTask && !empty($tasks)) {
    $activeTask = $tasks[0];
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
    <div>
        <h4 class="fw-bold mb-1">Pengumpulan Tugas Mahasiswa</h4>
        <p class="text-muted small mb-0">Mendukung jawaban teks langsung maupun unggah dokumen (<code>PDF, Word, JPG/PNG</code> maks. 10 MB).</p>
    </div>
    <div class="d-flex gap-2">
        <div class="input-group input-group-sm" style="max-width: 280px;">
            <input type="text" id="filterTaskCode" class="form-control text-uppercase" placeholder="Cari kode: TGS-IF201-01">
            <button class="btn btn-sky" type="button" id="btnFilterTaskCode">Buka</button>
        </div>
    </div>
</div>

<?php if (empty($tasks)): ?>
    <div class="card-custom p-5 text-center text-muted">
        Belum ada tugas yang diterbitkan pada kelas Anda.
    </div>
<?php else: ?>
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card-custom p-3 p-md-4">
                <h6 class="fw-bold mb-3">Daftar Tugas Kelas Anda (<?= count($tasks) ?>)</h6>
                <div class="d-flex flex-column gap-2" id="studentTaskList">
                    <?php foreach ($tasks as $t): ?>
                        <?php
                        $cd = getDeadlineCountdown($t['deadline']);
                        $isSelected = ($activeTask && (int)$activeTask['id'] === (int)$t['id']);
                        ?>
                        <a href="tasks.php?task_id=<?= (int)$t['id'] ?>"
                           data-code="<?= e($t['task_code']) ?>"
                           class="p-3 rounded-3 text-decoration-none border <?= $isSelected ? 'border-primary bg-sky-soft' : 'bg-light text-slate-800' ?>">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="badge bg-navy"><?= e($t['task_code']) ?></span>
                                <?php if ($t['submission_id']): ?>
                                    <?php if ($t['review_status'] === 'graded'): ?>
                                        <span class="badge badge-status-green">Nilai: <?= e((string)$t['score']) ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-status-yellow">Sudah Kumpul</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge badge-status-red">Belum Kumpul</span>
                                <?php endif; ?>
                            </div>
                            <div class="fw-bold text-slate-800"><?= e($t['title']) ?></div>
                            <div class="small text-muted"><?= e($t['course_code']) ?> - <?= e($t['course_name']) ?> (<?= e($t['class_name']) ?>)</div>
                            <div class="d-flex justify-content-between align-items-center mt-2 small">
                                <span class="text-muted"><i class="fa-regular fa-calendar me-1"></i><?= e(formatDateTime($t['deadline'])) ?></span>
                                <span class="badge <?= $cd['expired'] ? 'badge-status-red' : 'badge-status-green' ?>"
                                      <?= !$cd['expired'] ? 'data-countdown="' . e($t['deadline']) . '"' : '' ?>>
                                    <?= e($cd['label']) ?>
                                </span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <?php if ($activeTask): ?>
                <?php $cd = getDeadlineCountdown($activeTask['deadline']); ?>
                <div class="card-custom p-3 p-md-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 border-bottom pb-3 mb-3">
                        <div>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <span class="badge bg-navy"><?= e($activeTask['task_code']) ?></span>
                                <span class="badge <?= $cd['expired'] ? 'badge-status-red' : 'badge-status-green' ?>"
                                      <?= !$cd['expired'] ? 'data-countdown="' . e($activeTask['deadline']) . '"' : '' ?>>
                                    <i class="fa-regular fa-clock me-1"></i><?= e($cd['label']) ?>
                                </span>
                            </div>
                            <h5 class="fw-bold mt-2 mb-1"><?= e($activeTask['title']) ?></h5>
                            <div class="small text-muted">
                                Mata Kuliah: <strong><?= e($activeTask['course_code']) ?> - <?= e($activeTask['course_name']) ?></strong> &bull;
                                Dosen: <strong><?= e($activeTask['dosen_name']) ?></strong>
                            </div>
                            <div class="small text-danger fw-medium mt-1">
                                Batas Waktu Server: <?= e(formatDateTime($activeTask['deadline'])) ?>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 rounded-3 bg-light border small mb-3">
                        <div class="fw-semibold mb-1 text-sky"><i class="fa-solid fa-circle-info me-1"></i>Instruksi Pengerjaan:</div>
                        <div><?= nl2br(e($activeTask['description'])) ?></div>
                        <?php if (!empty($activeTask['attachment_path'])): ?>
                            <div class="mt-2 pt-2 border-top">
                                <a href="<?= e(API_URL . '/file.php?type=task&id=' . (int)$activeTask['id'] . '&download=1') ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fa-solid fa-paperclip me-1"></i>Unduh Berkas Soal dari Dosen
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($activeTask['submission_id'] && $activeTask['review_status'] === 'graded'): ?>
                        <div class="alert alert-success modern-alert mb-3">
                            <div class="w-100">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="fw-bold mb-0"><i class="fa-solid fa-award me-2"></i>Tugas Telah Dinilai oleh Dosen</h6>
                                    <span class="fs-4 fw-extrabold text-success"><?= e((string)$activeTask['score']) ?> / 100</span>
                                </div>
                                <?php if (!empty($activeTask['feedback'])): ?>
                                    <div class="small mt-2 pt-2 border-top">
                                        <strong>Feedback Dosen:</strong> <?= nl2br(e($activeTask['feedback'])) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($activeTask['submission_id']): ?>
                        <div class="mb-3 p-3 border rounded-3 bg-light small">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-bold text-sky"><i class="fa-solid fa-cloud-arrow-up me-1"></i>Jawaban Terkirim</span>
                                <span class="text-muted">Waktu: <?= e(formatDateTime($activeTask['submitted_at'])) ?></span>
                            </div>
                            <?php if (!empty($activeTask['text_answer'])): ?>
                                <div class="p-2 bg-white rounded border mb-2"><?= nl2br(e($activeTask['text_answer'])) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($activeTask['file_path'])): ?>
                                <a href="<?= e(API_URL . '/file.php?type=submission&id=' . (int)$activeTask['submission_id']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="fa-solid fa-file-arrow-down me-1"></i>Lihat / Unduh Berkas Jawaban Saya
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($cd['expired']): ?>
                        <div class="alert alert-danger modern-alert d-flex align-items-center gap-3 mb-0">
                            <i class="fa-solid fa-lock fs-3"></i>
                            <div>
                                <div class="fw-bold">Deadline Berakhir — Form Pengumpulan Terkunci Otomatis</div>
                                <div class="small">Waktu server telah melewati batas waktu pengumpulan tugas (<?= e(formatDateTime($activeTask['deadline'])) ?>).</div>
                            </div>
                        </div>
                    <?php elseif ($activeTask['review_status'] === 'graded'): ?>
                        <div class="alert alert-secondary modern-alert small mb-0">
                            <i class="fa-solid fa-lock me-2"></i>Pengumpulan dikunci karena tugas Anda sudah selesai dinilai oleh Dosen.
                        </div>
                    <?php else: ?>
                        <form method="POST" action="" enctype="multipart/form-data">
                            <?= csrfInput() ?>
                            <input type="hidden" name="task_id" value="<?= (int)$activeTask['id'] ?>">

                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Jawaban Teks Langsung (Opsional jika mengunggah berkas)</label>
                                <textarea name="text_answer" rows="4" class="form-control" placeholder="Ketik jawaban, penjelasan analisis, atau tautan pendukung di sini..."><?= e($activeTask['text_answer'] ?? '') ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Unggah Berkas Tugas (Maks. 10 MB)</label>
                                <div class="upload-dropzone">
                                    <input type="file" name="submission_file" class="d-none" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                    <i class="fa-solid fa-cloud-arrow-up text-sky fs-3 mb-2 d-block"></i>
                                    <div class="fw-semibold small">Klik atau seret berkas jawaban Anda ke area ini</div>
                                    <div class="text-muted" style="font-size: 0.75rem;">Format didukung: <code>.pdf, .doc, .docx, .jpg, .png</code> (Boleh teks saja, berkas saja, atau keduanya)</div>
                                    <div class="dropzone-filename"></div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-sky w-100 fw-bold py-2">
                                <i class="fa-solid fa-paper-plane me-2"></i><?= $activeTask['submission_id'] ? 'Perbarui Pengumpulan Tugas' : 'Kumpulkan Tugas Sekarang' ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<script>
document.getElementById('btnFilterTaskCode')?.addEventListener('click', () => {
    const code = document.getElementById('filterTaskCode').value.trim().toUpperCase();
    if (!code) return;
    const items = document.querySelectorAll('#studentTaskList a[data-code]');
    for (const item of items) {
        if (item.dataset.code.toUpperCase() === code) {
            window.location.href = item.getAttribute('href');
            return;
        }
    }
    if (typeof AATMS_UI !== 'undefined') {
        AATMS_UI.toast('Kode tugas ' + code + ' tidak ditemukan pada kelas Anda.', 'danger');
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
