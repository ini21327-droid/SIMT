<?php
/**
 * Endpoint JSON: Submit Absensi GPS Mahasiswa dengan Validasi Server-Side Haversine
 * + Sistem Deteksi Perangkat (Device Fingerprint, OS/Browser, GPS Accuracy, Anti-Titip Absen)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode request harus POST.']);
    exit;
}

if (!isLoggedIn() || (currentUser()['role'] ?? '') !== 'mahasiswa') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Hanya mahasiswa yang dapat melakukan absensi GPS.']);
    exit;
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$csrfToken = $payload['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token keamanan CSRF tidak valid.']);
    exit;
}

$pdo = getDB();
syncExpiredAttendanceSessions($pdo);

$studentId  = (int)currentUser()['id'];
$sessionId  = (int)($payload['session_id'] ?? 0);
$studentLat = isset($payload['lat']) ? (float)$payload['lat'] : null;
$studentLng = isset($payload['lng']) ? (float)$payload['lng'] : null;

// Data Telemetri Perangkat & Akurasi Sensor GPS
$gpsAccuracy       = isset($payload['accuracy']) && is_numeric($payload['accuracy']) ? round((float)$payload['accuracy'], 2) : null;
$deviceInfo        = trim(substr((string)($payload['device_info'] ?? ''), 0, 175));
$deviceFingerprint = trim(substr((string)($payload['device_fingerprint'] ?? ''), 0, 60));
$ipAddress         = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$ipAddress         = trim(explode(',', (string)$ipAddress)[0]);

if ($deviceInfo === '') {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Device';
    $deviceInfo = substr($ua, 0, 175);
}

if ($sessionId <= 0 || $studentLat === null || $studentLng === null) {
    echo json_encode(['success' => false, 'message' => 'Koordinat lokasi GPS atau ID Sesi tidak lengkap.']);
    exit;
}

if ($studentLat < -90 || $studentLat > 90 || $studentLng < -180 || $studentLng > 180) {
    echo json_encode(['success' => false, 'message' => 'Koordinat latitude/longitude di luar rentang bumi yang valid.']);
    exit;
}

// Ambil sesi absensi & pastikan mahasiswa terdaftar di kelas tersebut
$stmt = $pdo->prepare("
    SELECT s.*, cl.class_name, c.course_code, c.course_name
    FROM attendance_sessions s
    JOIN classes cl ON s.class_id = cl.id
    JOIN courses c ON cl.course_id = c.id
    JOIN class_enrollments ce ON ce.class_id = s.class_id AND ce.student_id = ?
    WHERE s.id = ?
    LIMIT 1
");
$stmt->execute([$studentId, $sessionId]);
$session = $stmt->fetch();

if (!$session) {
    echo json_encode(['success' => false, 'message' => 'Sesi absensi tidak ditemukan atau Anda tidak terdaftar di kelas ini.']);
    exit;
}

// Validasi waktu server & status sesi
$now = time();
$closedAtTs = strtotime($session['closed_at']);

if ($session['status'] !== 'open' || $now > $closedAtTs) {
    echo json_encode([
        'success' => false,
        'message' => 'Sesi absensi telah ditutup (Batas waktu: ' . formatDateTime($session['closed_at']) . ').'
    ]);
    exit;
}

// Cek apakah mahasiswa sudah pernah absen di sesi ini
$chkStmt = $pdo->prepare("SELECT id, status, distance_meters, check_in_time FROM attendances WHERE session_id = ? AND student_id = ?");
$chkStmt->execute([$sessionId, $studentId]);
$existing = $chkStmt->fetch();
if ($existing) {
    echo json_encode([
        'success' => false,
        'message' => 'Anda sudah tercatat hadir pada sesi ini (' . formatDateTime($existing['check_in_time']) . ').'
    ]);
    exit;
}

// Cek indikasi perangkat yang sama dipakai oleh mahasiswa lain pada sesi yang sama (Anti-Titip Absen)
$deviceWarning = null;
if ($deviceFingerprint !== '') {
    $dupStmt = $pdo->prepare("
        SELECT u.full_name, u.nim_nidn
        FROM attendances a
        JOIN users u ON a.student_id = u.id
        WHERE a.session_id = ? AND a.device_fingerprint = ? AND a.student_id != ?
        LIMIT 1
    ");
    $dupStmt->execute([$sessionId, $deviceFingerprint, $studentId]);
    $dupDeviceUser = $dupStmt->fetch();
    if ($dupDeviceUser) {
        $deviceInfo .= ' [SHARED-DEVICE:' . ($dupDeviceUser['nim_nidn'] ?? $dupDeviceUser['full_name']) . ']';
        $deviceWarning = 'Perhatian: Perangkat ini terdeteksi digunakan juga oleh mahasiswa lain pada sesi ini.';
    }
}

// Perhitungan Rumus Haversine di Sisi Server (R = 6371000 meter)
$targetLat = (float)$session['target_lat'];
$targetLng = (float)$session['target_lng'];
$radiusMeters = (int)$session['radius_meters'];

$distance = calculateHaversineDistance($targetLat, $targetLng, $studentLat, $studentLng);
$isWithinRadius = ($distance <= $radiusMeters);

if (!$isWithinRadius) {
    echo json_encode([
        'success'         => false,
        'distance_meters' => $distance,
        'radius_meters'   => $radiusMeters,
        'location_valid'  => 0,
        'device_info'     => $deviceInfo,
        'message'         => sprintf(
            'Absensi ditolak! Posisi perangkat (%s) berada di luar radius kelas. Jarak aktual: %.2f meter (Batas maksimal: %d meter).',
            $deviceInfo,
            $distance,
            $radiusMeters
        )
    ]);
    exit;
}

// Simpan kehadiran beserta informasi perangkat & akurasi GPS ke tabel attendances
$ins = $pdo->prepare("
    INSERT INTO attendances
    (session_id, student_id, check_in_time, student_lat, student_lng, distance_meters, gps_accuracy, device_info, device_fingerprint, ip_address, status, location_valid)
    VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, 'hadir', 1)
");
$ins->execute([
    $sessionId,
    $studentId,
    $studentLat,
    $studentLng,
    $distance,
    $gpsAccuracy,
    $deviceInfo,
    $deviceFingerprint ?: null,
    $ipAddress
]);

echo json_encode([
    'success'         => true,
    'distance_meters' => $distance,
    'radius_meters'   => $radiusMeters,
    'gps_accuracy'    => $gpsAccuracy,
    'device_info'     => $deviceInfo,
    'device_warning'  => $deviceWarning,
    'location_valid'  => 1,
    'check_in_time'   => formatDateTime(date('Y-m-d H:i:s')),
    'message'         => sprintf(
        'Absensi berhasil! Perangkat [%s] tervalidasi pada jarak %.2f meter dari titik kelas (Radius %d m).',
        $deviceInfo,
        $distance,
        $radiusMeters
    )
]);
