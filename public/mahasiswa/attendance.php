<?php
/**
 * Modul Mahasiswa: Absensi Perkuliahan Berbasis Google Maps + Sistem Pencari & Deteksi Perangkat
 */
require_once __DIR__ . '/../../includes/auth.php';
requireRole('mahasiswa');

$pdo = getDB();
syncExpiredAttendanceSessions($pdo);

$user = currentUser();
$studentId = (int)$user['id'];
$pageTitle = 'Absensi GPS & Deteksi Perangkat';

$stmt = $pdo->prepare("
    SELECT s.*, cl.class_name, cl.room, c.course_code, c.course_name, u.full_name AS dosen_name,
           a.id AS attendance_id, a.check_in_time, a.distance_meters, a.gps_accuracy,
           a.device_info, a.device_fingerprint, a.status AS my_status
    FROM attendance_sessions s
    JOIN class_enrollments ce ON s.class_id = ce.class_id
    JOIN classes cl ON s.class_id = cl.id
    JOIN courses c ON cl.course_id = c.id
    JOIN users u ON s.dosen_id = u.id
    LEFT JOIN attendances a ON a.session_id = s.id AND a.student_id = ?
    WHERE ce.student_id = ? AND s.status = 'open'
    ORDER BY s.opened_at DESC
");
$stmt->execute([$studentId, $studentId]);
$openSessions = $stmt->fetchAll();

$selectedSessionId = (int)($_GET['session_id'] ?? ($openSessions[0]['id'] ?? 0));
$activeSession = null;
foreach ($openSessions as $os) {
    if ((int)$os['id'] === $selectedSessionId) {
        $activeSession = $os;
        break;
    }
}
if (!$activeSession && !empty($openSessions)) {
    $activeSession = $openSessions[0];
}

$extraScripts = [BASE_URL . '/assets/js/gps-attendance.js'];
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
    <div>
        <h4 class="fw-bold mb-1">Absensi Google Maps &amp; Pemindai Perangkat</h4>
        <p class="text-muted small mb-0">
            Sistem memindai identitas perangkat dan sensor lokasi GPS Anda di area <strong><?= e(CAMPUS_NAME) ?></strong>.
        </p>
    </div>
    <a href="<?= e(BASE_URL) ?>/mahasiswa/history.php" class="btn btn-outline-primary btn-sm rounded-pill px-3">
        <i class="fa-solid fa-clock-rotate-left me-1"></i>Riwayat Kehadiran Saya
    </a>
</div>

<?php if (empty($openSessions)): ?>
    <div class="card-custom p-5 text-center">
        <div class="brand-icon mx-auto mb-3" style="width: 60px; height: 60px; font-size: 1.5rem;">
            <i class="fa-solid fa-satellite-dish"></i>
        </div>
        <h5 class="fw-bold">Tidak Ada Sesi Absensi yang Sedang Dibuka</h5>
        <p class="text-muted small mb-0">Sesi absensi GPS akan muncul secara otomatis di sini ketika Dosen Pengampu mengaktifkannya.</p>
    </div>
<?php else: ?>
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card-custom p-3 p-md-4">
                <h6 class="fw-bold mb-3"><span class="pulse-dot"></span>Sesi Terbuka (<?= count($openSessions) ?>)</h6>
                <div class="d-flex flex-column gap-2">
                    <?php foreach ($openSessions as $s): ?>
                        <?php $isSelected = ($activeSession && (int)$activeSession['id'] === (int)$s['id']); ?>
                        <a href="attendance.php?session_id=<?= (int)$s['id'] ?>"
                           class="p-3 rounded-3 text-decoration-none border <?= $isSelected ? 'border-primary bg-sky-soft' : 'bg-light text-slate-800' ?>">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="badge bg-navy"><?= e($s['session_code']) ?></span>
                                <?php if ($s['attendance_id']): ?>
                                    <span class="badge badge-status-green"><i class="fa-solid fa-check me-1"></i>Sudah Absen</span>
                                <?php else: ?>
                                    <span class="badge badge-status-yellow" data-countdown="<?= e($s['closed_at']) ?>">Aktif</span>
                                <?php endif; ?>
                            </div>
                            <div class="fw-bold text-slate-800"><?= e($s['course_code']) ?> - <?= e($s['course_name']) ?></div>
                            <div class="small text-muted">Kelas <?= e($s['class_name']) ?> &bull; Pertemuan #<?= (int)$s['meeting_number'] ?></div>
                            <div class="small text-danger mt-1">
                                <i class="fa-regular fa-clock me-1"></i>Tutup: <?= e(formatDateTime($s['closed_at'])) ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <?php if ($activeSession): ?>
                <div class="card-custom p-3 p-md-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3 border-bottom pb-3">
                        <div>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <span class="badge bg-navy"><?= e($activeSession['session_code']) ?></span>
                                <span class="badge bg-sky-soft"><i class="fa-brands fa-google me-1"></i>Radius: <?= (int)$activeSession['radius_meters'] ?> Meter</span>
                                <span class="badge badge-status-yellow" data-countdown="<?= e($activeSession['closed_at']) ?>">Aktif</span>
                            </div>
                            <h5 class="fw-bold mt-2 mb-1"><?= e($activeSession['course_code']) ?> - <?= e($activeSession['course_name']) ?> (<?= e($activeSession['class_name']) ?>)</h5>
                            <div class="small text-muted">
                                Dosen: <strong><?= e($activeSession['dosen_name']) ?></strong> &bull;
                                Ruangan: <strong class="text-sky"><?= e($activeSession['room']) ?></strong> &bull;
                                Pertemuan #<?= (int)$activeSession['meeting_number'] ?>: <?= e($activeSession['topic']) ?>
                            </div>
                        </div>
                    </div>

                    <!-- Panel Deteksi Perangkat (Device Telemetry Scanner) -->
                    <div id="studentDeviceScannerBox" class="p-3 rounded-3 border bg-light mb-3">
                        <div class="small text-muted"><i class="fa-solid fa-spinner fa-spin me-2 text-sky"></i>Memindai spesifikasi perangkat &amp; sensor GPS Anda...</div>
                    </div>

                    <?php if ($activeSession['attendance_id']): ?>
                        <div class="alert alert-success modern-alert d-flex align-items-center gap-3 mb-0">
                            <i class="fa-solid fa-circle-check fs-2 text-success"></i>
                            <div>
                                <div class="fw-bold">Presensi GPS &amp; Perangkat Anda Sudah Tervalidasi!</div>
                                <div class="small mt-1">
                                    Waktu Check-In: <strong><?= e(formatDateTime($activeSession['check_in_time'])) ?></strong> &bull;
                                    Jarak Haversine: <strong><?= e((string)$activeSession['distance_meters']) ?> meter</strong>
                                    <?= $activeSession['gps_accuracy'] ? ' (±' . e((string)$activeSession['gps_accuracy']) . 'm)' : '' ?> &bull;
                                    Status: <span class="badge badge-status-green text-uppercase"><?= e($activeSession['my_status']) ?></span>
                                </div>
                                <?php if (!empty($activeSession['device_info'])): ?>
                                    <div class="small text-muted mt-1">
                                        <i class="fa-solid fa-mobile-screen me-1"></i>Perangkat Tercatat: <strong><?= e($activeSession['device_info']) ?></strong>
                                        (<code><?= e($activeSession['device_fingerprint'] ?? '-') ?></code>)
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div id="gpsStatusAlert" class="alert alert-info modern-alert py-2 small mb-3">
                            <i class="fa-solid fa-spinner fa-spin me-2"></i>Menghubungkan ke satelit Google Maps &amp; sensor GPS perangkat...
                        </div>

                        <div class="row g-2 mb-3 small">
                            <div class="col-sm-4">
                                <div class="p-2 px-3 border rounded-3 bg-light h-100">
                                    <div class="text-muted"><i class="fa-solid fa-compass me-1 text-sky"></i>Koordinat Perangkat:</div>
                                    <div id="studentCoordsLabel" class="fw-bold mt-1">Memindai...</div>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="p-2 px-3 border rounded-3 bg-light h-100">
                                    <div class="text-muted"><i class="fa-solid fa-satellite me-1 text-sky"></i>Akurasi Sensor GPS:</div>
                                    <div id="studentAccuracyLabel" class="fw-bold mt-1">Menunggu sensor...</div>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="p-2 px-3 border rounded-3 bg-light h-100">
                                    <div class="text-muted"><i class="fa-solid fa-ruler-combined me-1 text-sky"></i>Jarak ke Kelas:</div>
                                    <div id="studentDistanceLabel" class="fw-bold mt-1">Menghitung...</div>
                                </div>
                            </div>
                        </div>

                        <div id="studentAttendanceMap" class="leaflet-map-box mb-3"></div>

                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-2">
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" id="btnRefreshGps" class="btn btn-sm btn-outline-primary">
                                    <i class="fa-solid fa-location-crosshairs me-1"></i>Pindai Ulang Perangkat
                                </button>
                                <button type="button" id="btnLiveTrackGps" class="btn btn-sm btn-outline-success">
                                    <i class="fa-solid fa-satellite me-1"></i>Radar Live GPS: OFF
                                </button>
                                <button type="button" id="btnSimulateInside" class="btn btn-sm btn-outline-secondary" title="Simulasikan posisi perangkat berada di dalam Kampus Unimal Reuleut">
                                    <i class="fa-solid fa-wand-magic-sparkles me-1"></i>Simulasi di Kampus Unimal
                                </button>
                            </div>

                            <button type="button" id="btnSubmitAttendance" class="btn btn-sky px-4 py-2 fw-bold" disabled>
                                <i class="fa-solid fa-location-dot me-2"></i>Absen Sekarang
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($activeSession): ?>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        <?php if (!$activeSession['attendance_id']): ?>
        AATMS_GPS.initStudentCheckIn({
            mapId: 'studentAttendanceMap',
            sessionId: <?= (int)$activeSession['id'] ?>,
            targetLat: <?= (float)$activeSession['target_lat'] ?>,
            targetLng: <?= (float)$activeSession['target_lng'] ?>,
            radiusMeters: <?= (int)$activeSession['radius_meters'] ?>,
            statusBoxId: 'gpsStatusAlert',
            distanceLabelId: 'studentDistanceLabel',
            coordsLabelId: 'studentCoordsLabel',
            deviceBoxId: 'studentDeviceScannerBox',
            accuracyLabelId: 'studentAccuracyLabel',
            btnLocateId: 'btnRefreshGps',
            btnLiveTrackId: 'btnLiveTrackGps',
            btnSimulateInsideId: 'btnSimulateInside',
            btnSubmitId: 'btnSubmitAttendance'
        });
        <?php else: ?>
        AATMS_GPS.detectDeviceTelemetry().then(telemetry => {
            const box = document.getElementById('studentDeviceScannerBox');
            if (box) {
                box.innerHTML = `<div class="small"><i class="fa-solid ${telemetry.deviceIcon} text-sky me-2"></i>Perangkat Aktif Saat Ini: <strong>${escapeHtml(telemetry.summaryString)}</strong> &bull; ID: <code>${escapeHtml(telemetry.fingerprintId)}</code></div>`;
            }
        });
        <?php endif; ?>
    });
    </script>
    <?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
