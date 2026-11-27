<?php
/**
 * Modul Jadwal & Daftar Kelas Perkuliahan (RBAC Sesuai Spesifikasi Bagian 5)
 * - Admin & Kaprodi: Lihat Semua Kelas & Peserta
 * - Dosen: Lihat Kelas yang Diampu Sendiri
 * - Mahasiswa: Lihat Kelas yang Diambil (KRS)
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pdo = getDB();
$user = currentUser();
$role = $user['role'];
$userId = (int)$user['id'];
$pageTitle = 'Jadwal & Kelas Perkuliahan';

$sql = "
    SELECT cl.*, c.course_code, c.course_name, c.sks,
           u.full_name AS dosen_name, u.nim_nidn AS dosen_nidn,
           s.name AS semester_name, s.is_active AS sem_active,
           (SELECT COUNT(*) FROM class_enrollments ce WHERE ce.class_id = cl.id) AS student_count,
           (SELECT COUNT(*) FROM attendance_sessions asess WHERE asess.class_id = cl.id AND asess.status = 'open') AS open_sessions
    FROM classes cl
    JOIN courses c ON cl.course_id = c.id
    JOIN users u ON c.dosen_id = u.id
    JOIN semesters s ON c.semester_id = s.id
";
$params = [];

if ($role === 'dosen') {
    $sql .= " WHERE c.dosen_id = ?";
    $params[] = $userId;
} elseif ($role === 'mahasiswa') {
    $sql .= " JOIN class_enrollments mce ON mce.class_id = cl.id WHERE mce.student_id = ?";
    $params[] = $userId;
}

$sql .= " ORDER BY FIELD(cl.schedule_day, 'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'), cl.start_time ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$classes = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
    <div>
        <h4 class="fw-bold mb-1">Jadwal &amp; Kelas Perkuliahan</h4>
        <p class="text-muted small mb-0">
            <?php if ($role === 'mahasiswa'): ?>
                Daftar mata kuliah dan jadwal ruang kelas yang Anda ambil pada semester ini.
            <?php elseif ($role === 'dosen'): ?>
                Daftar kelas perkuliahan yang Anda ampu beserta akses cepat absensi GPS dan tugas.
            <?php else: ?>
                Seluruh jadwal kelas perkuliahan aktif yang terdaftar di dalam sistem akademik.
            <?php endif; ?>
        </p>
    </div>
    <?php if ($role === 'admin'): ?>
        <a href="<?= e(BASE_URL) ?>/admin/classes.php" class="btn btn-sky btn-sm">
            <i class="fa-solid fa-gear me-1"></i>Kelola Kelas &amp; Jadwal
        </a>
    <?php endif; ?>
</div>

<?php if (empty($classes)): ?>
    <div class="card-custom p-5 text-center text-muted">
        <i class="fa-regular fa-calendar-xmark fs-2 mb-2 d-block"></i>
        Belum ada jadwal kelas yang terdaftar untuk akun Anda.
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($classes as $cl): ?>
            <div class="col-md-6 col-xl-4">
                <div class="card-custom p-4 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-sky"><?= e($cl['course_code']) ?></span>
                                <span class="badge bg-navy">Kelas <?= e($cl['class_name']) ?></span>
                            </div>
                            <span class="badge bg-sky-soft"><?= (int)$cl['sks'] ?> SKS</span>
                        </div>

                        <h5 class="fw-bold mb-1"><?= e($cl['course_name']) ?></h5>
                        <div class="small text-muted mb-3">
                            <i class="fa-solid fa-user-tie me-1 text-sky"></i><?= e($cl['dosen_name']) ?>
                        </div>

                        <div class="p-3 rounded-3 bg-light border small d-flex flex-column gap-2 mb-3">
                            <div class="d-flex justify-content-between">
                                <span class="text-muted"><i class="fa-regular fa-calendar me-1"></i>Hari &amp; Jam:</span>
                                <span class="fw-semibold"><?= e($cl['schedule_day']) ?>, <?= e(substr($cl['start_time'], 0, 5)) ?>–<?= e(substr($cl['end_time'], 0, 5)) ?> WIB</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted"><i class="fa-solid fa-door-open me-1"></i>Ruangan:</span>
                                <span class="fw-semibold text-sky"><?= e($cl['room']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted"><i class="fa-solid fa-users me-1"></i>Peserta Kelas:</span>
                                <span class="fw-semibold"><?= (int)$cl['student_count'] ?> Mahasiswa</span>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                        <?php if ((int)$cl['open_sessions'] > 0): ?>
                            <span class="badge badge-status-green"><span class="pulse-dot"></span>Absensi GPS Buka</span>
                        <?php else: ?>
                            <span class="small text-muted"><?= e($cl['semester_name']) ?></span>
                        <?php endif; ?>

                        <?php if ($role === 'dosen'): ?>
                            <a href="<?= e(BASE_URL) ?>/dosen/attendance.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">Buka Sesi</a>
                        <?php elseif ($role === 'mahasiswa'): ?>
                            <a href="<?= e(BASE_URL) ?>/mahasiswa/attendance.php" class="btn btn-sm btn-sky rounded-pill px-3">Cek Absen</a>
                        <?php else: ?>
                            <a href="<?= e(BASE_URL) ?>/kaprodi/reports.php?class_id=<?= (int)$cl['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">Lihat Rekap</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>

