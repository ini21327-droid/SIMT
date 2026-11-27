<?php
/**
 * Endpoint Streaming File Aman (Lampiran Soal & File Pengumpulan Tugas Mahasiswa)
 * Bekerja secara konsisten baik di XAMPP/Laragon maupun saat DocumentRoot = /public.
 * Parameter GET:
 * - type: 'task' (lampiran soal dosen) atau 'submission' (file jawaban mahasiswa)
 * - id: ID task atau ID submission
 * - download: '1' jika ingin mengunduh paksa, '0' untuk inline preview (PDF/Gambar)
 */
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    die('Silakan login untuk mengakses berkas akademik.');
}

$pdo = getDB();
$user = currentUser();
$role = $user['role'];
$userId = (int)$user['id'];

$type = $_GET['type'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$forceDownload = isset($_GET['download']) && $_GET['download'] === '1';

$relativePath = null;

if ($type === 'task' && $id > 0) {
    $stmt = $pdo->prepare("SELECT attachment_path, class_id, dosen_id FROM tasks WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row && !empty($row['attachment_path'])) {
        // Mahasiswa hanya bisa mengunduh soal dari kelas yang diambilnya
        if ($role === 'mahasiswa') {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM class_enrollments WHERE class_id = ? AND student_id = ?");
            $chk->execute([$row['class_id'], $userId]);
            if ((int)$chk->fetchColumn() === 0) {
                http_response_code(403);
                die('Akses ditolak.');
            }
        }
        $relativePath = $row['attachment_path'];
    }
} elseif ($type === 'submission' && $id > 0) {
    $stmt = $pdo->prepare("
        SELECT ts.file_path, ts.student_id, t.dosen_id
        FROM task_submissions ts
        JOIN tasks t ON ts.task_id = t.id
        WHERE ts.id = ?
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row && !empty($row['file_path'])) {
        // Hanya pemilik mahasiswa, dosen pengampu, kaprodi, atau admin yang diizinkan
        if ($role === 'mahasiswa' && (int)$row['student_id'] !== $userId) {
            http_response_code(403);
            die('Anda tidak berhak membuka berkas mahasiswa lain.');
        }
        if ($role === 'dosen' && (int)$row['dosen_id'] !== $userId) {
            http_response_code(403);
            die('Anda bukan dosen pengampu tugas ini.');
        }
        $relativePath = $row['file_path'];
    }
}

if (!$relativePath) {
    http_response_code(404);
    die('Berkas tidak ditemukan.');
}

// Proteksi Path Traversal: pastikan berkas benar-benar berada di dalam UPLOAD_TASK_DIR
$fileName = basename($relativePath);
$fullPath = realpath(UPLOAD_TASK_DIR . $fileName);
$allowedRoot = realpath(UPLOAD_TASK_DIR);

if (!$fullPath || !$allowedRoot || strpos($fullPath, $allowedRoot) !== 0 || !is_file($fullPath)) {
    http_response_code(404);
    die('Berkas fisik tidak ditemukan di server.');
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $fullPath) ?: 'application/octet-stream';
finfo_close($finfo);

$disposition = ($forceDownload || !in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true))
    ? 'attachment'
    : 'inline';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($fullPath));
header('Content-Disposition: ' . $disposition . '; filename="' . $fileName . '"');
header('X-Content-Type-Options: nosniff');
readfile($fullPath);
exit;

