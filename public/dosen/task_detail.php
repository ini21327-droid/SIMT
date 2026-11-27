<?php
/**
 * Modul Dosen: Detail Monitoring Tugas (Tab Sudah Mengumpulkan vs Belum Mengumpulkan)
 * + Preview File Aman via api/file.php + Instant AJAX Grading (api/grade_submission.php)
 */
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['dosen', 'admin']);

$pdo = getDB();
$user = currentUser();
$userId = (int)$user['id'];
$isAdmin = ($user['role'] === 'admin');

$taskId = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT t.*, cl.class_name, c.course_code, c.course_name, u.full_name AS dosen_name
    FROM tasks t
    JOIN classes cl ON t.class_id = cl.id
    JOIN courses c ON cl.course_id = c.id
    JOIN users u ON t.dosen_id = u.id
    WHERE t.id = ?
");
$stmt->execute([$taskId]);
$task = $stmt->fetch();

if (!$task || (!$isAdmin && (int)$task['dosen_id'] !== $userId)) {
    setFlash('danger', 'Tugas tidak ditemukan atau Anda tidak memiliki akses.');
    header('Location: ' . BASE_URL . '/dosen/tasks.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'grade_submission') {
        $submissionId = (int)($_POST['submission_id'] ?? 0);
        $score = (float)($_POST['score'] ?? 0);
        $feedback = trim($_POST['feedback'] ?? '');

        if ($submissionId > 0 && $score >= 0 && $score <= 100) {
            $upd = $pdo->prepare("
                UPDATE task_submissions
                SET score = ?, feedback = ?, review_status = 'graded', graded_at = NOW()
                WHERE id = ? AND task_id = ?
            ");
            $upd->execute([$score, $feedback ?: null, $submissionId, $taskId]);
            setFlash('success', 'Nilai dan komentar dosen berhasil disimpan.');
        } else {
            setFlash('danger', 'Nilai harus berada pada rentang 0 - 100.');
        }
    } elseif ($action === 'update_deadline') {
        $newDeadline = $_POST['deadline'] ?? '';
        if ($newDeadline !== '') {
            $upd = $pdo->prepare("UPDATE tasks SET deadline = ? WHERE id = ?");
            $upd->execute([date('Y-m-d H:i:s', strtotime($newDeadline)), $taskId]);
            setFlash('success', 'Batas waktu deadline tugas berhasil diperbarui.');
        }
    }

    header('Location: ' . BASE_URL . '/dosen/task_detail.php?id=' . $taskId);
    exit;
}

$stStmt = $pdo->prepare("
    SELECT u.id AS student_id, u.full_name, u.nim_nidn, u.email,
           ts.id AS submission_id, ts.text_answer, ts.file_path, ts.file_type,
           ts.submitted_at, ts.score, ts.feedback, ts.review_status, ts.graded_at
    FROM class_enrollments ce
    JOIN users u ON ce.student_id = u.id
    LEFT JOIN task_submissions ts ON ts.task_id = ? AND ts.student_id = u.id
    WHERE ce.class_id = ?
    ORDER BY u.nim_nidn ASC, u.full_name ASC
");
$stStmt->execute([$taskId, $task['class_id']]);
$allEnrollments = $stStmt->fetchAll();

$submittedList = [];
$unsubmittedList = [];
$scores = [];

foreach ($allEnrollments as $row) {
    if (!empty($row['submission_id'])) {
        $submittedList[] = $row;
        if ($row['score'] !== null) {
            $scores[] = (float)$row['score'];
        }
    } else {
        $unsubmittedList[] = $row;
    }
}

$avgScore = !empty($scores) ? round(array_sum($scores) / count($scores), 2) : null;
$cd = getDeadlineCountdown($task['deadline']);
$pageTitle = 'Pemeriksaan Tugas: ' . $task['task_code'];
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
    <div>
        <a href="<?= e(BASE_URL) ?>/dosen/tasks.php" class="text-decoration-none small text-sky fw-semibold">
            <i class="fa-solid fa-arrow-left me-1"></i>Kembali ke Daftar Tugas
        </a>
        <h4 class="fw-bold mb-0 mt-1"><?= e($task['title']) ?></h4>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="badge bg-navy fs-6"><?= e($task['task_code']) ?></span>
        <button type="button" class="btn btn-xs-chip" data-copy-code="<?= e($task['task_code']) ?>" data-copy-label="Kode Tugas">
            <i class="fa-regular fa-copy me-1"></i>Salin Kode
        </button>
        <span class="badge <?= $cd['expired'] ? 'badge-status-red' : 'badge-status-green' ?> fs-6"
              <?= !$cd['expired'] ? 'data-countdown="' . e($task['deadline']) . '"' : '' ?>>
            <?= e($cd['label']) ?>
        </span>
    </div>
</div>

<!-- Ringkasan Statistik Tugas -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card py-3">
            <div>
                <div class="text-muted small">Peserta Kelas</div>
                <div class="fs-4 fw-extrabold"><?= count($allEnrollments) ?></div>
            </div>
            <div class="stat-icon bg-sky-soft" style="width:42px;height:42px;font-size:1.05rem;"><i class="fa-solid fa-users"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card accent-emerald py-3">
            <div>
                <div class="text-muted small">Sudah Kumpul</div>
                <div class="fs-4 fw-extrabold text-success"><?= count($submittedList) ?></div>
            </div>
            <div class="stat-icon" style="width:42px;height:42px;font-size:1.05rem;background:rgba(16,185,129,0.14);color:#10B981;"><i class="fa-solid fa-check"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card accent-amber py-3">
            <div>
                <div class="text-muted small">Belum Kumpul</div>
                <div class="fs-4 fw-extrabold text-danger"><?= count($unsubmittedList) ?></div>
            </div>
            <div class="stat-icon" style="width:42px;height:42px;font-size:1.05rem;background:rgba(239,68,68,0.12);color:#EF4444;"><i class="fa-solid fa-hourglass-half"></i></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card accent-purple py-3">
            <div>
                <div class="text-muted small">Rata-rata Nilai</div>
                <div class="fs-4 fw-extrabold"><?= $avgScore !== null ? e((string)$avgScore) : '-' ?></div>
            </div>
            <div class="stat-icon" style="width:42px;height:42px;font-size:1.05rem;background:rgba(139,92,246,0.14);color:#7C3AED;"><i class="fa-solid fa-star"></i></div>
        </div>
    </div>
</div>

<!-- Info Tugas & Pengaturan Deadline -->
<div class="card-custom p-3 p-md-4 mb-4">
    <div class="row g-3 align-items-center">
        <div class="col-lg-8">
            <div class="small text-muted mb-1">
                <strong><?= e($task['course_code']) ?> - <?= e($task['course_name']) ?></strong> (Kelas <?= e($task['class_name']) ?>)
                &bull; Deadline: <strong class="text-danger"><?= e(formatDateTime($task['deadline'])) ?></strong>
            </div>
            <p class="mb-2 small"><?= nl2br(e($task['description'])) ?></p>
            <?php if (!empty($task['attachment_path'])): ?>
                <a href="<?= e(API_URL . '/file.php?type=task&id=' . (int)$task['id'] . '&download=1') ?>" class="btn btn-sm btn-outline-primary">
                    <i class="fa-solid fa-paperclip me-1"></i>Unduh Lampiran Soal Dosen
                </a>
            <?php endif; ?>
        </div>
        <div class="col-lg-4">
            <form method="POST" action="" class="p-3 rounded-3 bg-light border small">
                <?= csrfInput() ?>
                <input type="hidden" name="action" value="update_deadline">
                <label class="form-label fw-semibold mb-1"><i class="fa-regular fa-calendar-plus me-1 text-sky"></i>Ubah / Perpanjang Deadline:</label>
                <div class="input-group input-group-sm">
                    <input type="datetime-local" name="deadline" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime($task['deadline'])) ?>" required>
                    <button type="submit" class="btn btn-navy">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Tabs: Sudah Mengumpulkan vs Belum Mengumpulkan -->
<ul class="nav nav-tabs mb-3" id="taskMonitorTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active fw-semibold" id="submitted-tab" data-bs-toggle="tab" data-bs-target="#submittedPane" type="button" role="tab">
            <i class="fa-solid fa-circle-check text-success me-1"></i>Sudah Mengumpulkan
            <span class="badge badge-status-green ms-1"><?= count($submittedList) ?></span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="unsubmitted-tab" data-bs-toggle="tab" data-bs-target="#unsubmittedPane" type="button" role="tab">
            <i class="fa-solid fa-circle-xmark text-danger me-1"></i>Belum Mengumpulkan
            <span class="badge badge-status-red ms-1"><?= count($unsubmittedList) ?></span>
        </button>
    </li>
</ul>

<div class="tab-content">
    <!-- Tab 1: Sudah Mengumpulkan -->
    <div class="tab-pane fade show active" id="submittedPane" role="tabpanel">
        <div class="card-custom p-3 p-md-4">
            <?php if (empty($submittedList)): ?>
                <div class="text-center text-muted py-5">Belum ada mahasiswa yang mengumpulkan tugas ini.</div>
            <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($submittedList as $sub): ?>
                        <?php $sid = (int)$sub['submission_id']; ?>
                        <div class="border rounded-4 p-3 p-md-4 bg-white shadow-sm">
                            <div class="row g-4">
                                <div class="col-lg-7">
                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="avatar-circle bg-sky-soft fw-bold" style="width:32px;height:32px;font-size:0.8rem;">
                                                <?= e(strtoupper(substr($sub['full_name'], 0, 1))) ?>
                                            </span>
                                            <div>
                                                <div class="fw-bold"><?= e($sub['full_name']) ?></div>
                                                <code class="small"><?= e($sub['nim_nidn'] ?? '-') ?></code>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="small text-muted">
                                                <i class="fa-regular fa-clock me-1"></i><?= e(formatDateTime($sub['submitted_at'])) ?>
                                            </span>
                                            <span id="statusBadge_<?= $sid ?>" class="badge <?= $sub['review_status'] === 'graded' ? 'badge-status-green' : 'badge-status-yellow' ?>">
                                                <?= $sub['review_status'] === 'graded' ? 'GRADED (' . e((string)$sub['score']) . ')' : 'SUBMITTED' ?>
                                            </span>
                                        </div>
                                    </div>

                                    <?php if (!empty($sub['text_answer'])): ?>
                                        <div class="p-3 rounded-3 bg-light border small mb-2 mt-2">
                                            <div class="fw-semibold text-muted mb-1"><i class="fa-solid fa-align-left me-1 text-sky"></i>Jawaban Teks Mahasiswa:</div>
                                            <div class="text-slate-800"><?= nl2br(e($sub['text_answer'])) ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($sub['file_path'])): ?>
                                        <?php
                                        $ext = strtolower(pathinfo($sub['file_path'], PATHINFO_EXTENSION));
                                        $isPreviewable = in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true);
                                        $previewUrl = API_URL . '/file.php?type=submission&id=' . $sid;
                                        $downloadUrl = $previewUrl . '&download=1';
                                        ?>
                                        <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                                            <span class="badge bg-sky-soft"><i class="fa-solid fa-file-lines me-1"></i>Berkas .<?= e(strtoupper($ext)) ?></span>
                                            <?php if ($isPreviewable): ?>
                                                <button type="button" class="btn btn-sm btn-outline-primary btn-preview-file"
                                                    data-url="<?= e($previewUrl) ?>"
                                                    data-ext="<?= e($ext) ?>"
                                                    data-student="<?= e($sub['full_name']) ?>"
                                                    data-bs-toggle="modal" data-bs-target="#previewFileModal">
                                                    <i class="fa-solid fa-eye me-1"></i>Preview Langsung
                                                </button>
                                            <?php endif; ?>
                                            <a href="<?= e($downloadUrl) ?>" class="btn btn-sm btn-outline-secondary">
                                                <i class="fa-solid fa-download me-1"></i>Unduh Berkas
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Form Penilaian Dosen (AJAX Instant + Fallback POST) -->
                                <div class="col-lg-5">
                                    <form method="POST" action="" class="ajax-grade-form p-3 rounded-3 bg-light border" data-submission-id="<?= $sid ?>">
                                        <?= csrfInput() ?>
                                        <input type="hidden" name="action" value="grade_submission">
                                        <input type="hidden" name="submission_id" value="<?= $sid ?>">
                                        <div class="row g-2 align-items-center mb-2">
                                            <div class="col-5">
                                                <label class="form-label small fw-semibold mb-1">Nilai (0–100)</label>
                                                <input type="number" step="0.01" min="0" max="100" name="score" class="form-control form-control-sm fw-bold"
                                                       value="<?= e((string)($sub['score'] ?? '')) ?>" placeholder="90.00" required>
                                            </div>
                                            <div class="col-7">
                                                <label class="form-label small fw-semibold mb-1">Status Penilaian</label>
                                                <div class="small text-muted" id="gradedTime_<?= $sid ?>">
                                                    <?= !empty($sub['graded_at']) ? e(formatDateTime($sub['graded_at'])) : 'Belum dinilai' ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small fw-semibold mb-1">Feedback / Catatan Dosen</label>
                                            <textarea name="feedback" rows="2" class="form-control form-control-sm" placeholder="Tulis evaluasi pekerjaan mahasiswa..."><?= e($sub['feedback'] ?? '') ?></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-sm btn-sky w-100 btn-save-grade">
                                            <i class="fa-solid fa-check-double me-1"></i>Simpan Nilai &amp; Feedback
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tab 2: Belum Mengumpulkan -->
    <div class="tab-pane fade" id="unsubmittedPane" role="tabpanel">
        <div class="card-custom p-3 p-md-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>NIM</th>
                            <th>Nama Mahasiswa</th>
                            <th>Email</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($unsubmittedList)): ?>
                            <tr><td colspan="5" class="text-center text-success py-4"><i class="fa-solid fa-circle-check me-1"></i>Seluruh mahasiswa di kelas ini sudah mengumpulkan tugas!</td></tr>
                        <?php else: ?>
                            <?php foreach ($unsubmittedList as $idx => $un): ?>
                                <tr>
                                    <td><?= $idx + 1 ?></td>
                                    <td><code><?= e($un['nim_nidn'] ?? '-') ?></code></td>
                                    <td class="fw-semibold"><?= e($un['full_name']) ?></td>
                                    <td class="small"><?= e($un['email']) ?></td>
                                    <td><span class="badge badge-status-red">BELUM MENGUMPULKAN</span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Preview File PDF / Gambar -->
<div class="modal fade" id="previewFileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content modern-modal border-0 shadow-lg overflow-hidden">
            <div class="modal-header bg-navy text-white border-0">
                <h6 class="modal-title fw-bold" id="previewModalTitle">Preview Lampiran Tugas</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-2 text-center bg-dark" id="previewModalBody" style="min-height: 450px;"></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.btn-preview-file').forEach(btn => {
        btn.addEventListener('click', () => {
            const url = btn.dataset.url;
            const ext = btn.dataset.ext;
            const student = btn.dataset.student;
            document.getElementById('previewModalTitle').textContent = 'Preview Tugas — ' + student;
            const body = document.getElementById('previewModalBody');

            if (ext === 'pdf') {
                body.innerHTML = `<iframe src="${url}" style="width:100%; height:75vh; border:0; background:#fff;"></iframe>`;
            } else {
                body.innerHTML = `<img src="${url}" class="img-fluid rounded" style="max-height:75vh;" alt="Preview">`;
            }
        });
    });

    // AJAX Instant Grading via api/grade_submission.php
    const apiBase = document.querySelector('meta[name="api-url"]')?.getAttribute('content') || '../../api';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    document.querySelectorAll('.ajax-grade-form').forEach(form => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const sid = form.dataset.submissionId;
            const scoreVal = form.querySelector('input[name="score"]').value;
            const feedbackVal = form.querySelector('textarea[name="feedback"]').value;
            const btn = form.querySelector('.btn-save-grade');
            const origHtml = btn.innerHTML;

            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Menyimpan...';

            try {
                const res = await fetch(`${apiBase}/grade_submission.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        csrf_token: csrfToken,
                        submission_id: sid,
                        score: scoreVal,
                        feedback: feedbackVal,
                        review_status: 'graded'
                    })
                });
                const data = await res.json();
                if (data.success) {
                    const badge = document.getElementById('statusBadge_' + sid);
                    if (badge) {
                        badge.className = 'badge badge-status-green';
                        badge.textContent = `GRADED (${data.score})`;
                    }
                    const timeEl = document.getElementById('gradedTime_' + sid);
                    if (timeEl) timeEl.textContent = data.graded_at;
                    if (typeof AATMS_UI !== 'undefined') AATMS_UI.toast(data.message, 'success');
                } else {
                    if (typeof AATMS_UI !== 'undefined') AATMS_UI.toast(data.message || 'Gagal menyimpan nilai.', 'danger');
                }
            } catch (err) {
                form.submit(); // Fallback standard POST
                return;
            } finally {
                btn.disabled = false;
                btn.innerHTML = origHtml;
            }
        });
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
