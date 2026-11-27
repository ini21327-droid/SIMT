<?php
/**
 * Endpoint JSON: Pemanggilan Data Otomatis Berbasis Kode (Spesifikasi 6.1)
 * Parameter GET:
 * - type: 'course' atau 'task'
 * - code: Kode Mata Kuliah (misal IF201) atau Kode Tugas (misal TGS-IF201-01)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

$pdo = getDB();
$type = strtolower(trim($_GET['type'] ?? 'course'));
$code = strtoupper(trim($_GET['code'] ?? ''));

if ($code === '') {
    echo json_encode(['success' => false, 'message' => 'Parameter kode tidak boleh kosong.']);
    exit;
}

// Auto-switch ke task jika diawali TGS-
if (strpos($code, 'TGS-') === 0) {
    $type = 'task';
}

if ($type === 'course') {
    $stmt = $pdo->prepare("
        SELECT c.id, c.course_code, c.course_name, c.sks, c.dosen_id, c.semester_id,
               u.full_name AS dosen_name, u.nim_nidn AS dosen_nidn,
               s.name AS semester_name
        FROM courses c
        JOIN users u ON c.dosen_id = u.id
        JOIN semesters s ON c.semester_id = s.id
        WHERE UPPER(c.course_code) = ?
        LIMIT 1
    ");
    $stmt->execute([$code]);
    $course = $stmt->fetch();

    if (!$course) {
        echo json_encode([
            'success' => false,
            'message' => "Kode Mata Kuliah '{$code}' tidak ditemukan."
        ]);
        exit;
    }

    $clsStmt = $pdo->prepare("
        SELECT id, class_name, schedule_day, start_time, end_time, room
        FROM classes
        WHERE course_id = ?
        ORDER BY class_name ASC
    ");
    $clsStmt->execute([$course['id']]);
    $course['classes'] = $clsStmt->fetchAll();

    echo json_encode([
        'success' => true,
        'type'    => 'course',
        'data'    => $course
    ]);
    exit;
}

if ($type === 'task') {
    $stmt = $pdo->prepare("
        SELECT t.id, t.task_code, t.title, t.description, t.deadline, t.attachment_path,
               cl.id AS class_id, cl.class_name,
               c.course_code, c.course_name, c.sks,
               u.full_name AS dosen_name
        FROM tasks t
        JOIN classes cl ON t.class_id = cl.id
        JOIN courses c ON cl.course_id = c.id
        JOIN users u ON t.dosen_id = u.id
        WHERE UPPER(t.task_code) = ?
        LIMIT 1
    ");
    $stmt->execute([$code]);
    $task = $stmt->fetch();

    if (!$task) {
        echo json_encode([
            'success' => false,
            'message' => "Kode Tugas '{$code}' tidak ditemukan."
        ]);
        exit;
    }

    $cd = getDeadlineCountdown($task['deadline']);
    $task['is_expired'] = $cd['expired'];
    $task['countdown_label'] = $cd['label'];
    $task['deadline_formatted'] = formatDateTime($task['deadline']);

    echo json_encode([
        'success' => true,
        'type'    => 'task',
        'data'    => $task
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Tipe pencarian tidak valid.']);

