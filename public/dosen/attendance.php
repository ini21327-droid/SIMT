<?php
/**
 * Modul Dosen: Buka / Tutup / Perpanjang Sesi Absensi GPS & Monitoring Peta Kehadiran Mahasiswa
 */
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['dosen', 'admin']);

$pdo = getDB();
syncExpiredAttendanceSessions($pdo);

$user = currentUser();
$userId = (int)$user['id'];
$isAdmin = ($user['role'] === 'admin');
$pageTitle = 'Sesi Absensi GPS & Monitoring Peta';

if ($isAdmin) {
    $classes = $pdo->query("
        SELECT cl.id, cl.class_name, cl.room, c.course_code, c.course_name, c.dosen_id
        FROM classes cl
        JOIN courses c ON cl.course_id = c.id
        ORDER BY c.course_code ASC, cl.class_name ASC
    ")->fetchAll();
} else {
    $clsStmt = $pdo->prepare("
        SELECT cl.id, cl.class_name, cl.room, c.course_code, c.course_name, c.dosen_id
        FROM classes cl
        JOIN courses c ON cl.course_id = c.id
        WHERE c.dosen_id = ?
        ORDER BY c.course_code ASC, cl.class_name ASC
    ");
    $clsStmt->execute([$userId]);
    $classes = $clsStmt->fetchAll();
}

// Map kepemilikan kelas untuk validasi keamanan RBAC
$allowedClassDosen = [];
foreach ($classes as $cItem) {
    $allowedClassDosen[(int)$cItem['id']] = (int)$cItem['dosen_id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'open_session') {
        $classId       = (int)($_POST['class_id'] ?? 0);
        $meetingNumber = max(1, (int)($_POST['meeting_number'] ?? 1));
        $topic         = trim($_POST['topic'] ?? '');
        $targetLat     = (float)($_POST['target_lat'] ?? 5.23405600);
        $targetLng     = (float)($_POST['target_lng'] ?? 96.98813900);
        $radiusMeters  = max(10, (int)($_POST['radius_meters'] ?? 100));
        $durationMins  = max(5, (int)($_POST['duration_minutes'] ?? 90));

        if (!isset($allowedClassDosen[$classId])) {
            setFlash('danger', 'Kelas tidak valid atau bukan kelas yang Anda ampu.');
        } elseif ($topic !== '') {
            $sessionCode = generateSessionCode($pdo, $classId, $meetingNumber);
            $openedAt = date('Y-m-d H:i:s');
            $closedAt = date('Y-m-d H:i:s', time() + ($durationMins * 60));
            $sessionDosenId = $allowedClassDosen[$classId];

            $stmt = $pdo->prepare("
                INSERT INTO attendance_sessions
                (class_id, dosen_id, session_code, meeting_number, topic, target_lat, target_lng, radius_meters, opened_at, closed_at, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'open')
            ");
            $stmt->execute([
                $classId,
                $sessionDosenId,
                $sessionCode,
                $meetingNumber,
                $topic,
                $targetLat,
                $targetLng,
                $radiusMeters,
                $openedAt,
                $closedAt
            ]);
            $newSessionId = (int)$pdo->lastInsertId();
            setFlash('success', "Sesi Absensi GPS {$sessionCode} berhasil dibuka dengan radius {$radiusMeters} meter.");
            header('Location: ' . BASE_URL . '/dosen/attendance.php?monitor=' . $newSessionId);
            exit;
        } else {
            setFlash('danger', 'Mohon lengkapi Kelas dan Topik Perkuliahan.');
        }
    } elseif ($action === 'close_session') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE attendance_sessions SET status = 'closed', closed_at = NOW() WHERE id = ? AND (dosen_id = ? OR ? = 1)");
        $stmt->execute([$sessionId, $userId, $isAdmin ? 1 : 0]);
        setFlash('success', 'Sesi absensi telah ditutup.');
        header('Location: ' . BASE_URL . '/dosen/attendance.php?monitor=' . $sessionId);
        exit;
    } elseif ($action === 'extend_session') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $stmt = $pdo->prepare("
            UPDATE attendance_sessions
            SET status = 'open',
                closed_at = DATE_ADD(GREATEST(closed_at, NOW()), INTERVAL 15 MINUTE)
            WHERE id = ? AND (dosen_id = ? OR ? = 1)
        ");
        $stmt->execute([$sessionId, $userId, $isAdmin ? 1 : 0]);
        setFlash('success', 'Durasi sesi absensi berhasil diperpanjang +15 menit.');
        header('Location: ' . BASE_URL . '/dosen/attendance.php?monitor=' . $sessionId);
        exit;
    } elseif ($action === 'manual_status') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $studentId = (int)($_POST['student_id'] ?? 0);
        $status    = $_POST['status'] ?? 'hadir';

        if (in_array($status, ['hadir', 'terlambat', 'izin', 'sakit', 'alpha'], true)) {
            $sessStmt = $pdo->prepare("SELECT target_lat, target_lng FROM attendance_sessions WHERE id = ? AND (dosen_id = ? OR ? = 1)");
            $sessStmt->execute([$sessionId, $userId, $isAdmin ? 1 : 0]);
            $sessCoords = $sessStmt->fetch();

            if ($sessCoords) {
                $stmt = $pdo->prepare("
                    INSERT INTO attendances (session_id, student_id, check_in_time, student_lat, student_lng, distance_meters, status, location_valid)
                    VALUES (?, ?, NOW(), ?, ?, 0.00, ?, 1)
                    ON DUPLICATE KEY UPDATE status = VALUES(status)
                ");
                $stmt->execute([$sessionId, $studentId, $sessCoords['target_lat'], $sessCoords['target_lng'], $status]);
                setFlash('success', 'Status kehadiran mahasiswa berhasil diperbarui.');
            }
        }
        header('Location: ' . BASE_URL . '/dosen/attendance.php?monitor=' . $sessionId);
        exit;
    }

    header('Location: ' . BASE_URL . '/dosen/attendance.php');
    exit;
}

if ($isAdmin) {
    $sessions = $pdo->query("
        SELECT s.*, cl.class_name, c.course_code, c.course_name,
               (SELECT COUNT(*) FROM attendances a WHERE a.session_id = s.id AND a.status IN ('hadir','terlambat')) AS present_count,
               (SELECT COUNT(*) FROM class_enrollments ce WHERE ce.class_id = s.class_id) AS total_students
        FROM attendance_sessions s
        JOIN classes cl ON s.class_id = cl.id
        JOIN courses c ON cl.course_id = c.id
        ORDER BY s.opened_at DESC
    ")->fetchAll();
} else {
    $sessStmt = $pdo->prepare("
        SELECT s.*, cl.class_name, c.course_code, c.course_name,
               (SELECT COUNT(*) FROM attendances a WHERE a.session_id = s.id AND a.status IN ('hadir','terlambat')) AS present_count,
               (SELECT COUNT(*) FROM class_enrollments ce WHERE ce.class_id = s.class_id) AS total_students
        FROM attendance_sessions s
        JOIN classes cl ON s.class_id = cl.id
        JOIN courses c ON cl.course_id = c.id
        WHERE s.dosen_id = ?
        ORDER BY s.opened_at DESC
    ");
    $sessStmt->execute([$userId]);
    $sessions = $sessStmt->fetchAll();
}

$monitorId = (int)($_GET['monitor'] ?? ($sessions[0]['id'] ?? 0));
$monitorSession = null;
$monitorStudents = [];
$mapAttendances = [];

if ($monitorId > 0) {
    $mStmt = $pdo->prepare("
        SELECT s.*, cl.class_name, cl.room, c.course_code, c.course_name, u.full_name AS dosen_name
        FROM attendance_sessions s
        JOIN classes cl ON s.class_id = cl.id
        JOIN courses c ON cl.course_id = c.id
        JOIN users u ON s.dosen_id = u.id
        WHERE s.id = ? AND (s.dosen_id = ? OR ? = 1)
    ");
    $mStmt->execute([$monitorId, $userId, $isAdmin ? 1 : 0]);
    $monitorSession = $mStmt->fetch();

    if ($monitorSession) {
        $stStmt = $pdo->prepare("
            SELECT u.id AS student_id, u.full_name, u.nim_nidn,
                   a.id AS attendance_id, a.check_in_time, a.student_lat, a.student_lng,
                   a.distance_meters, a.gps_accuracy, a.device_info, a.device_fingerprint,
                   a.ip_address, a.status, a.location_valid
            FROM class_enrollments ce
            JOIN users u ON ce.student_id = u.id
            LEFT JOIN attendances a ON a.session_id = ? AND a.student_id = u.id
            WHERE ce.class_id = ?
            ORDER BY u.nim_nidn ASC, u.full_name ASC
        ");
        $stStmt->execute([$monitorId, $monitorSession['class_id']]);
        $monitorStudents = $stStmt->fetchAll();

        foreach ($monitorStudents as $ms) {
            if (!empty($ms['attendance_id'])) {
                $ms['check_in_time'] = formatDateTime($ms['check_in_time']);
                $mapAttendances[] = $ms;
            }
        }
    }
}

$extraScripts = [BASE_URL . '/assets/js/gps-attendance.js'];
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
    <div>
        <h4 class="fw-bold mb-1">Manajemen Absensi GPS &amp; Visualisasi Peta</h4>
        <p class="text-muted small mb-0">Atur radius geofencing kelas dengan rumus Haversine dan pantau lokasi mahasiswa secara <em>real-time</em>.</p>
    </div>
    <button class="btn btn-sky" type="button" data-bs-toggle="collapse" data-bs-target="#openSessionCollapse">
        <i class="fa-solid fa-satellite-dish me-2"></i>Buka Sesi Absensi GPS Baru
    </button>
</div>

<!-- Form Collapse Buka Sesi Absensi Baru -->
<div class="collapse <?= empty($sessions) ? 'show' : '' ?> mb-4" id="openSessionCollapse">
    <div class="card-custom p-3 p-md-4 border-sky">
        <h6 class="fw-bold mb-3"><i class="fa-solid fa-tower-broadcast text-sky me-2"></i>Konfigurasi Titik Koordinat &amp; Radius Sesi Baru</h6>
        <form method="POST" action="">
            <?= csrfInput() ?>
            <input type="hidden" name="action" value="open_session">
            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Pilih Kelas Perkuliahan</label>
                        <select name="class_id" class="form-select" required>
                            <?php foreach ($classes as $cl): ?>
                                <option value="<?= (int)$cl['id'] ?>">
                                    <?= e($cl['course_code']) ?> - <?= e($cl['course_name']) ?> (Kelas <?= e($cl['class_name']) ?> &bull; <?= e($cl['room']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <label class="form-label small fw-semibold">Pertemuan</label>
                            <input type="number" name="meeting_number" class="form-control" min="1" max="32" value="3" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label small fw-semibold">Radius (m)</label>
                            <input type="number" name="radius_meters" id="pickerRadius" class="form-control" min="10" max="2000" value="100" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label small fw-semibold">Durasi (Mnt)</label>
                            <input type="number" name="duration_minutes" class="form-control" min="5" max="720" value="90" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Topik / Materi Perkuliahan</label>
                        <input type="text" name="topic" class="form-control" placeholder="Contoh: Arsitektur Keamanan Web & Geofencing" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Target Latitude</label>
                            <input type="text" name="target_lat" id="pickerLat" class="form-control" value="5.23405600" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Target Longitude</label>
                            <input type="text" name="target_lng" id="pickerLng" class="form-control" value="96.98813900" required>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <button type="button" id="btnLecturerGps" class="btn btn-sm btn-outline-primary">
                            <i class="fa-solid fa-location-crosshairs me-1"></i>Gunakan Lokasi GPS Saya
                        </button>
                        <button type="button" id="btnResetCampus" class="btn btn-sm btn-outline-secondary">
                            <i class="fa-solid fa-building-columns me-1"></i>Kampus Unimal Reuleut
                        </button>
                    </div>
                    <button type="submit" class="btn btn-sky w-100 py-2 fw-bold">
                        <i class="fa-solid fa-bolt me-1"></i>Aktifkan Sesi Absensi Sekarang
                    </button>
                </div>

                <div class="col-lg-7">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-semibold mb-0">Peta Satelit Google Maps — Kampus Unimal Reuleut:</label>
                        <span class="badge bg-sky-soft"><i class="fa-brands fa-google me-1"></i>Google Maps Hybrid</span>
                    </div>
                    <div id="lecturerPickerMap" class="leaflet-map-box mb-2"></div>
                    <div id="lecturerAddressLabel" class="small text-muted"></div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="row g-4">
    <!-- Daftar Sesi Absensi -->
    <div class="col-lg-4">
        <div class="card-custom p-3 p-md-4 h-100">
            <h6 class="fw-bold mb-3">Riwayat &amp; Sesi Aktif (<?= count($sessions) ?>)</h6>
            <?php if (empty($sessions)): ?>
                <div class="text-center text-muted py-4 small">Belum ada sesi absensi yang dibuat.</div>
            <?php else: ?>
                <div class="d-flex flex-column gap-2">
                    <?php foreach ($sessions as $s): ?>
                        <?php $isActiveMonitor = ((int)$s['id'] === $monitorId); ?>
                        <a href="attendance.php?monitor=<?= (int)$s['id'] ?>"
                           class="p-3 rounded-3 text-decoration-none border transition-all <?= $isActiveMonitor ? 'border-primary bg-sky-soft' : 'bg-light text-slate-800' ?>">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="badge bg-navy"><?= e($s['session_code']) ?></span>
                                <?php if ($s['status'] === 'open'): ?>
                                    <span class="badge badge-status-green"><span class="pulse-dot"></span>OPEN</span>
                                <?php else: ?>
                                    <span class="badge badge-status-red">CLOSED</span>
                                <?php endif; ?>
                            </div>
                            <div class="fw-bold text-slate-800"><?= e($s['course_code']) ?> - <?= e($s['course_name']) ?></div>
                            <div class="small text-muted">Kelas <?= e($s['class_name']) ?> &bull; Pertemuan #<?= (int)$s['meeting_number'] ?></div>
                            <div class="d-flex justify-content-between align-items-center mt-2 small text-slate-800">
                                <span><i class="fa-solid fa-bullseye me-1 text-sky"></i>Radius <strong><?= (int)$s['radius_meters'] ?>m</strong></span>
                                <span><i class="fa-solid fa-user-check me-1 text-success"></i><strong><?= (int)$s['present_count'] ?>/<?= (int)$s['total_students'] ?></strong> Hadir</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Panel Monitoring Peta & Daftar Kehadiran Sesi Terpilih -->
    <div class="col-lg-8">
        <?php if (!$monitorSession): ?>
            <div class="card-custom p-5 text-center text-muted">
                Pilih salah satu sesi absensi di sebelah kiri untuk melihat peta sebaran lokasi GPS mahasiswa.
            </div>
        <?php else: ?>
            <div class="card-custom p-3 p-md-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3 pb-3 border-bottom">
                    <div>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="badge bg-navy"><?= e($monitorSession['session_code']) ?></span>
                            <button type="button" class="btn btn-xs-chip" data-copy-code="<?= e($monitorSession['session_code']) ?>" data-copy-label="Kode Sesi">
                                <i class="fa-regular fa-copy me-1"></i>Salin Kode
                            </button>
                            <?php if ($monitorSession['status'] === 'open'): ?>
                                <span class="badge badge-status-green"><span class="pulse-dot"></span>OPEN</span>
                                <span class="badge badge-status-yellow" data-countdown="<?= e($monitorSession['closed_at']) ?>">Aktif</span>
                            <?php else: ?>
                                <span class="badge badge-status-red">CLOSED</span>
                            <?php endif; ?>
                            <span class="badge bg-sky-soft">Radius: <?= (int)$monitorSession['radius_meters'] ?>m</span>
                        </div>
                        <h5 class="fw-bold mb-1 mt-2"><?= e($monitorSession['course_code']) ?> - <?= e($monitorSession['course_name']) ?> (Kelas <?= e($monitorSession['class_name']) ?>)</h5>
                        <div class="small text-muted">
                            Pertemuan #<?= (int)$monitorSession['meeting_number'] ?>: <strong><?= e($monitorSession['topic']) ?></strong>
                            &bull; Tutup: <?= e(formatDateTime($monitorSession['closed_at'])) ?>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <form method="POST" action="">
                            <?= csrfInput() ?>
                            <input type="hidden" name="action" value="extend_session">
                            <input type="hidden" name="session_id" value="<?= (int)$monitorSession['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                <i class="fa-solid fa-clock-rotate-left me-1"></i>+15 Menit
                            </button>
                        </form>

                        <?php if ($monitorSession['status'] === 'open'): ?>
                            <form method="POST" action="" onsubmit="return confirm('Tutup sesi absensi ini sekarang?');">
                                <?= csrfInput() ?>
                                <input type="hidden" name="action" value="close_session">
                                <input type="hidden" name="session_id" value="<?= (int)$monitorSession['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="fa-solid fa-lock me-1"></i>Tutup Sesi
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Peta Monitoring Titik Mahasiswa -->
                <div id="monitorSessionMap" class="leaflet-map-box mb-4"></div>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <h6 class="fw-bold mb-0">Daftar Kehadiran Peserta Kelas (<?= count($monitorStudents) ?> Mahasiswa)</h6>
                    <input type="text" class="form-control form-control-sm" style="max-width: 220px;" placeholder="Cari nama / NIM..." data-table-search="#monitorAttendanceTable">
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="monitorAttendanceTable">
                        <thead>
                            <tr>
                                <th>NIM &amp; Nama Mahasiswa</th>
                                <th>Perangkat Terdeteksi</th>
                                <th>Waktu Check-In</th>
                                <th>Jarak &amp; Akurasi</th>
                                <th>Status</th>
                                <th class="text-end">Set Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monitorStudents as $m): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= e($m['full_name']) ?></div>
                                        <code class="small"><?= e($m['nim_nidn'] ?? '-') ?></code>
                                    </td>
                                    <td>
                                        <?php if (!empty($m['device_info'])): ?>
                                            <div class="small fw-medium text-slate-800">
                                                <i class="fa-solid <?= strpos($m['device_info'], 'Laptop') !== false ? 'fa-laptop' : 'fa-mobile-screen-button' ?> text-sky me-1"></i>
                                                <?= e($m['device_info']) ?>
                                            </div>
                                            <small class="text-muted">ID: <code><?= e($m['device_fingerprint'] ?? '-') ?></code></small>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small"><?= !empty($m['check_in_time']) ? e($m['check_in_time']) : '<span class="text-muted">Belum check-in</span>' ?></td>
                                    <td>
                                        <?php if ($m['distance_meters'] !== null): ?>
                                            <span class="badge bg-sky-soft"><?= e((string)$m['distance_meters']) ?> m</span>
                                            <?php if (!empty($m['gps_accuracy'])): ?>
                                                <div class="text-muted" style="font-size:0.7rem;">Akurasi ±<?= e((string)$m['gps_accuracy']) ?>m</div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $st = $m['status'] ?? 'alpha';
                                        if (!$m['attendance_id']) {
                                            echo '<span class="badge badge-status-red">BELUM ABSEN</span>';
                                        } elseif ($st === 'hadir') {
                                            echo '<span class="badge badge-status-green">HADIR</span>';
                                        } elseif ($st === 'terlambat') {
                                            echo '<span class="badge badge-status-yellow">TERLAMBAT</span>';
                                        } elseif ($st === 'izin' || $st === 'sakit') {
                                            echo '<span class="badge badge-status-blue">' . e(strtoupper($st)) . '</span>';
                                        } else {
                                            echo '<span class="badge badge-status-red">ALPHA</span>';
                                        }
                                        ?>
                                    </td>
                                    <td class="text-end">
                                        <form method="POST" action="" class="d-inline-flex gap-1">
                                            <?= csrfInput() ?>
                                            <input type="hidden" name="action" value="manual_status">
                                            <input type="hidden" name="session_id" value="<?= (int)$monitorSession['id'] ?>">
                                            <input type="hidden" name="student_id" value="<?= (int)$m['student_id'] ?>">
                                            <select name="status" class="form-select form-select-sm" style="width: 112px;" onchange="this.form.submit()">
                                                <option value="">-- Ubah --</option>
                                                <option value="hadir">Hadir</option>
                                                <option value="terlambat">Terlambat</option>
                                                <option value="izin">Izin</option>
                                                <option value="sakit">Sakit</option>
                                                <option value="alpha">Alpha</option>
                                            </select>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    AATMS_GPS.initLecturerPicker({
        mapId: 'lecturerPickerMap',
        collapseId: 'openSessionCollapse',
        latInputId: 'pickerLat',
        lngInputId: 'pickerLng',
        radiusInputId: 'pickerRadius',
        btnGpsId: 'btnLecturerGps',
        btnSimCampusId: 'btnResetCampus',
        addressLabelId: 'lecturerAddressLabel'
    });

    <?php if ($monitorSession): ?>
    AATMS_GPS.initLecturerMonitorMap({
        mapId: 'monitorSessionMap',
        targetLat: <?= (float)$monitorSession['target_lat'] ?>,
        targetLng: <?= (float)$monitorSession['target_lng'] ?>,
        radiusMeters: <?= (int)$monitorSession['radius_meters'] ?>,
        attendances: <?= json_encode($mapAttendances) ?>
    });
    <?php endif; ?>
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
