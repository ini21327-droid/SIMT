<?php
/**
 * Modul Admin: Kelola Peserta Kelas (Class Enrollments / KRS)
 */
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

$pdo = getDB();
$pageTitle = 'Peserta Kelas (Enrollment)';

$classes = $pdo->query("
    SELECT cl.id, cl.class_name, c.course_code, c.course_name, u.full_name AS dosen_name
    FROM classes cl
    JOIN courses c ON cl.course_id = c.id
    JOIN users u ON c.dosen_id = u.id
    ORDER BY c.course_code ASC, cl.class_name ASC
")->fetchAll();

$selectedClassId = (int)($_GET['class_id'] ?? ($classes[0]['id'] ?? 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';
    $classId = (int)($_POST['class_id'] ?? $selectedClassId);

    if ($action === 'enroll') {
        $studentIds = $_POST['student_ids'] ?? [];
        $added = 0;
        $stmt = $pdo->prepare("INSERT IGNORE INTO class_enrollments (class_id, student_id) VALUES (?, ?)");
        foreach ($studentIds as $sid) {
            $stmt->execute([$classId, (int)$sid]);
            $added += $stmt->rowCount();
        }
        setFlash('success', "{$added} mahasiswa berhasil ditambahkan ke kelas.");
    } elseif ($action === 'unenroll') {
        $enrollId = (int)($_POST['enrollment_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM class_enrollments WHERE id = ?");
        $stmt->execute([$enrollId]);
        setFlash('success', 'Mahasiswa berhasil dikeluarkan dari kelas.');
    }

    header('Location: ' . BASE_URL . '/admin/enrollments.php?class_id=' . $classId);
    exit;
}

// Ambil daftar mahasiswa yang sudah terdaftar di kelas terpilih
$enrolledStmt = $pdo->prepare("
    SELECT ce.id AS enrollment_id, u.id AS student_id, u.full_name, u.nim_nidn, u.email
    FROM class_enrollments ce
    JOIN users u ON ce.student_id = u.id
    WHERE ce.class_id = ?
    ORDER BY u.nim_nidn ASC, u.full_name ASC
");
$enrolledStmt->execute([$selectedClassId]);
$enrolledStudents = $enrolledStmt->fetchAll();

// Ambil mahasiswa yang belum terdaftar di kelas tersebut
$availableStmt = $pdo->prepare("
    SELECT u.id, u.full_name, u.nim_nidn
    FROM users u
    WHERE u.role = 'mahasiswa' AND u.is_active = 1
      AND u.id NOT IN (SELECT student_id FROM class_enrollments WHERE class_id = ?)
    ORDER BY u.nim_nidn ASC, u.full_name ASC
");
$availableStmt->execute([$selectedClassId]);
$availableStudents = $availableStmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
    <div>
        <h4 class="fw-bold mb-1">Manajemen Peserta Kelas (Enrollment)</h4>
        <p class="text-muted small mb-0">Daftarkan mahasiswa ke dalam kelas perkuliahan agar dapat melakukan absensi GPS dan mengumpulkan tugas.</p>
    </div>
    <form method="GET" action="" class="d-flex gap-2">
        <select name="class_id" class="form-select" onchange="this.form.submit()">
            <?php foreach ($classes as $cl): ?>
                <option value="<?= (int)$cl['id'] ?>" <?= (int)$cl['id'] === $selectedClassId ? 'selected' : '' ?>>
                    <?= e($cl['course_code']) ?> - <?= e($cl['course_name']) ?> (Kelas <?= e($cl['class_name']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card-custom p-3 p-md-4">
            <h6 class="fw-bold mb-3"><i class="fa-solid fa-user-plus text-sky me-2"></i>Tambahkan Mahasiswa ke Kelas</h6>
            <?php if (empty($availableStudents)): ?>
                <div class="alert alert-light border small text-muted mb-0">
                    Semua mahasiswa aktif sudah terdaftar di kelas ini.
                </div>
            <?php else: ?>
                <form method="POST" action="">
                    <?= csrfInput() ?>
                    <input type="hidden" name="action" value="enroll">
                    <input type="hidden" name="class_id" value="<?= $selectedClassId ?>">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Pilih Mahasiswa (Bisa pilih lebih dari satu)</label>
                        <div class="border rounded p-2" style="max-height: 260px; overflow-y: auto;">
                            <?php foreach ($availableStudents as $m): ?>
                                <div class="form-check py-1">
                                    <input class="form-check-input" type="checkbox" name="student_ids[]" value="<?= (int)$m['id'] ?>" id="mhs_<?= (int)$m['id'] ?>">
                                    <label class="form-check-label small" for="mhs_<?= (int)$m['id'] ?>">
                                        <strong><?= e($m['nim_nidn'] ?? '-') ?></strong> &mdash; <?= e($m['full_name']) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-sky w-100">Daftarkan Mahasiswa Terpilih</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card-custom p-3 p-md-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0">Daftar Peserta Terdaftar (<?= count($enrolledStudents) ?> Mahasiswa)</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>NIM</th>
                            <th>Nama Mahasiswa</th>
                            <th>Email</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($enrolledStudents)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">Belum ada mahasiswa di kelas ini.</td></tr>
                        <?php else: ?>
                            <?php foreach ($enrolledStudents as $idx => $es): ?>
                                <tr>
                                    <td><?= $idx + 1 ?></td>
                                    <td><code><?= e($es['nim_nidn'] ?? '-') ?></code></td>
                                    <td class="fw-semibold"><?= e($es['full_name']) ?></td>
                                    <td class="small"><?= e($es['email']) ?></td>
                                    <td class="text-end">
                                        <form method="POST" action="" class="d-inline" onsubmit="return confirm('Keluarkan mahasiswa dari kelas ini?');">
                                            <?= csrfInput() ?>
                                            <input type="hidden" name="action" value="unenroll">
                                            <input type="hidden" name="class_id" value="<?= $selectedClassId ?>">
                                            <input type="hidden" name="enrollment_id" value="<?= (int)$es['enrollment_id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="fa-solid fa-user-minus"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

