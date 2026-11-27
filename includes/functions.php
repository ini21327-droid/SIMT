<?php
/**
 * Helper Umum AATMS
 * - Sanitasi Output XSS
 * - Rumus Haversine (Jarak GPS dalam meter)
 * - Generator Kode Otomatis
 * - Format Tanggal Indonesia
 * - Validasi & Upload File Aman
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Escape string untuk mencegah XSS
 */
function e(?string $str): string
{
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/**
 * Menghitung jarak antara 2 titik koordinat GPS dengan rumus Haversine.
 * Jari-jari bumi R = 6371000 meter.
 *
 * @return float Jarak dalam meter
 */
function calculateHaversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $earthRadius = 6371000.0; // meter

    $phi1 = deg2rad($lat1);
    $phi2 = deg2rad($lat2);
    $deltaPhi = deg2rad($lat2 - $lat1);
    $deltaLambda = deg2rad($lon2 - $lon1);

    $a = sin($deltaPhi / 2) * sin($deltaPhi / 2) +
         cos($phi1) * cos($phi2) *
         sin($deltaLambda / 2) * sin($deltaLambda / 2);

    $c = 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));

    return round($earthRadius * $c, 2);
}

/**
 * Format datetime ke format Indonesia yang mudah dibaca
 */
function formatDateTime(?string $datetime, bool $withTime = true): string
{
    if (empty($datetime)) {
        return '-';
    }
    $ts = strtotime($datetime);
    if (!$ts) {
        return e($datetime);
    }
    $months = [
        1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
    ];
    $day = date('d', $ts);
    $month = $months[(int)date('n', $ts)] ?? date('M', $ts);
    $year = date('Y', $ts);

    if (!$withTime) {
        return "$day $month $year";
    }
    return "$day $month $year, " . date('H:i', $ts) . ' WIB';
}

/**
 * Menghitung teks sisa waktu menuju deadline
 */
function getDeadlineCountdown(string $deadline): array
{
    $now = time();
    $target = strtotime($deadline);
    $diff = $target - $now;

    if ($diff <= 0) {
        return [
            'expired' => true,
            'label'   => 'Deadline Berakhir',
            'badge'   => 'danger'
        ];
    }

    $days = floor($diff / 86400);
    $hours = floor(($diff % 86400) / 3600);
    $minutes = floor(($diff % 3600) / 60);

    $parts = [];
    if ($days > 0) {
        $parts[] = $days . ' hari';
    }
    if ($hours > 0) {
        $parts[] = $hours . ' jam';
    }
    if ($days == 0 && $minutes > 0) {
        $parts[] = $minutes . ' menit';
    }

    return [
        'expired' => false,
        'label'   => 'Sisa ' . implode(' ', $parts),
        'badge'   => ($diff < 86400) ? 'warning' : 'success'
    ];
}

/**
 * Generate kode sesi absensi otomatis (contoh: SES-IF201-03)
 */
function generateSessionCode(PDO $pdo, int $classId, int $meetingNumber): string
{
    $stmt = $pdo->prepare("
        SELECT c.course_code
        FROM classes cl
        JOIN courses c ON cl.course_id = c.id
        WHERE cl.id = ?
    ");
    $stmt->execute([$classId]);
    $courseCode = $stmt->fetchColumn() ?: 'CLS' . $classId;

    $baseCode = sprintf('SES-%s-%02d', strtoupper($courseCode), $meetingNumber);

    $check = $pdo->prepare("SELECT COUNT(*) FROM attendance_sessions WHERE session_code = ?");
    $check->execute([$baseCode]);
    if ((int)$check->fetchColumn() === 0) {
        return $baseCode;
    }

    return $baseCode . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 3));
}

/**
 * Generate kode tugas otomatis (contoh: TGS-IF201-01)
 */
function generateTaskCode(PDO $pdo, int $classId): string
{
    $stmt = $pdo->prepare("
        SELECT c.course_code
        FROM classes cl
        JOIN courses c ON cl.course_id = c.id
        WHERE cl.id = ?
    ");
    $stmt->execute([$classId]);
    $courseCode = $stmt->fetchColumn() ?: 'CLS' . $classId;

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE class_id = ?");
    $countStmt->execute([$classId]);
    $nextNum = ((int)$countStmt->fetchColumn()) + 1;

    $baseCode = sprintf('TGS-%s-%02d', strtoupper($courseCode), $nextNum);

    $check = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE task_code = ?");
    $check->execute([$baseCode]);
    if ((int)$check->fetchColumn() === 0) {
        return $baseCode;
    }

    return sprintf('TGS-%s-%02d-%s', strtoupper($courseCode), $nextNum, strtoupper(substr(bin2hex(random_bytes(2)), 0, 3)));
}

/**
 * Sinkronisasi penutupan sesi absensi yang sudah melewati closed_at
 */
function syncExpiredAttendanceSessions(PDO $pdo): void
{
    $pdo->exec("
        UPDATE attendance_sessions
        SET status = 'closed'
        WHERE status = 'open' AND closed_at < NOW()
    ");
}

/**
 * Proses upload file tugas yang aman
 *
 * @return array ['success' => bool, 'file_path' => ?string, 'file_type' => ?string, 'error' => ?string]
 */
function handleTaskFileUpload(array $file, string $prefix = 'sub'): array
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'file_path' => null, 'file_type' => null, 'error' => null];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'file_path' => null, 'file_type' => null, 'error' => 'Terjadi kesalahan saat mengunggah file (Kode: ' . $file['error'] . ').'];
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return ['success' => false, 'file_path' => null, 'file_type' => null, 'error' => 'Ukuran file melebihi batas maksimal 10 MB.'];
    }

    $originalName = $file['name'] ?? '';
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (!in_array($ext, ALLOWED_EXTENSIONS, true)) {
        return [
            'success'   => false,
            'file_path' => null,
            'file_type' => null,
            'error'     => 'Ekstensi file tidak didukung. Gunakan: ' . implode(', ', ALLOWED_EXTENSIONS)
        ];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, ALLOWED_MIMES, true)) {
        return [
            'success'   => false,
            'file_path' => null,
            'file_type' => null,
            'error'     => 'Tipe MIME file tidak valid (' . e($mimeType) . ').'
        ];
    }

    if (!is_dir(UPLOAD_TASK_DIR)) {
        mkdir(UPLOAD_TASK_DIR, 0755, true);
    }

    $newFileName = $prefix . '_' . uniqid() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $targetPath = UPLOAD_TASK_DIR . $newFileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => false, 'file_path' => null, 'file_type' => null, 'error' => 'Gagal menyimpan file ke direktori server.'];
    }

    return [
        'success'   => true,
        'file_path' => 'uploads/tasks/' . $newFileName,
        'file_type' => $mimeType,
        'error'     => null
    ];
}

/**
 * Helper Flash Message Session
 */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type'    => $type,
        'message' => $message
    ];
}

function getFlash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

