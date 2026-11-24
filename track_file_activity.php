<?php
if (session_id() === '') {
    @session_start();
}
include 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'message' => 'Not logged in']);
    exit();
}

$student_id = intval($_SESSION['user_id']);
$activity_id = intval($_POST['activity_id'] ?? 0);
$action = trim((string)($_POST['action'] ?? 'open')); 
$scroll_pct = intval($_POST['progress'] ?? 25);

if ($activity_id <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Invalid activity ID']);
    exit();
}

$conn->query("
    CREATE TABLE IF NOT EXISTS student_activity_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        activity_id INT NOT NULL,
        progress_percent INT NOT NULL DEFAULT 0,
        status_label VARCHAR(50) NOT NULL DEFAULT 'Not Started',
        last_action VARCHAR(50) NOT NULL DEFAULT 'none',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_student_activity (student_id, activity_id)
    )
");


$current_pct = 0;
$stmt = $conn->prepare("SELECT progress_percent FROM student_activity_progress WHERE student_id = ? AND activity_id = ? LIMIT 1");
$stmt->bind_param("ii", $student_id, $activity_id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $current_pct = intval($row['progress_percent']);
}
$stmt->close();

if ($action === 'complete') {
    $new_pct = 100;
    $status_label = 'Completed';
} else {
    
    $candidate = max(25, min(85, $scroll_pct));
    if ($action === 'open') {
        $candidate = max($candidate, 25);
    } elseif ($action === 'download') {
        $candidate = max($candidate, 50);
    } elseif ($action === 'scroll') {
        $candidate = max($candidate, $scroll_pct);
        $candidate = min(85, $candidate);
    }
    $new_pct = max($current_pct, $candidate);
    if ($new_pct >= 100) {
        $new_pct = 85; 
    }
    $status_label = 'Working';
}

$ins = $conn->prepare("
    INSERT INTO student_activity_progress (student_id, activity_id, progress_percent, status_label, last_action)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        progress_percent = VALUES(progress_percent),
        status_label = VALUES(status_label),
        last_action = VALUES(last_action),
        updated_at = CURRENT_TIMESTAMP
");
$ins->bind_param("iiiss", $student_id, $activity_id, $new_pct, $status_label, $action);
$ok = $ins->execute();
$ins->close();

echo json_encode([
    'ok' => $ok,
    'progress' => $new_pct,
    'status' => $status_label,
    'text' => ($new_pct >= 100) ? '100% - Tapos Nang Basahin' : ($new_pct . '% - Nagwo-work / Binabasa')
]);