<?php
/**
 * Endpoint JSON / Form POST: Penilaian & Feedback Tugas Mahasiswa oleh Dosen (Spesifikasi 6.4)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode harus POST.']);
    exit;
}

if (!isLoggedIn() || !in_array(currentUser()['role'] ?? '', ['dosen', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Hanya Dosen yang berhak menilai tugas.']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$csrfToken = $payload['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF tidak valid.']);
    exit;
}

$pdo = getDB();
$submissionId = (int)($payload['submission_id'] ?? 0);
$score        = isset($payload['score']) && $payload['score'] !== '' ? (float)$payload['score'] : null;
$feedback     = trim($payload['feedback'] ?? '');
$reviewStatus = $payload['review_status'] ?? 'graded';

if ($submissionId <= 0 || $score === null || $score < 0 || $score > 100) {
    echo json_encode(['success' => false, 'message' => 'Nilai harus berada di antara 0 hingga 100.']);
    exit;
}

if (!in_array($reviewStatus, ['submitted', 'reviewed', 'graded'], true)) {
    $reviewStatus = 'graded';
}

$user = currentUser();
$stmt = $pdo->prepare("
    SELECT ts.id, t.dosen_id
    FROM task_submissions ts
    JOIN tasks t ON ts.task_id = t.id
    WHERE ts.id = ?
");
$stmt->execute([$submissionId]);
$sub = $stmt->fetch();

if (!$sub) {
    echo json_encode(['success' => false, 'message' => 'Data pengumpulan tugas tidak ditemukan.']);
    exit;
}

if ($user['role'] !== 'admin' && (int)$sub['dosen_id'] !== (int)$user['id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Anda bukan dosen pengampu tugas ini.']);
    exit;
}

$upd = $pdo->prepare("
    UPDATE task_submissions
    SET score = ?, feedback = ?, review_status = ?, graded_at = NOW()
    WHERE id = ?
");
$upd->execute([$score, $feedback ?: null, $reviewStatus, $submissionId]);

echo json_encode([
    'success'       => true,
    'submission_id' => $submissionId,
    'score'         => number_format($score, 2),
    'review_status' => $reviewStatus,
    'graded_at'     => formatDateTime(date('Y-m-d H:i:s')),
    'message'       => 'Nilai dan komentar berhasil disimpan.'
]);

