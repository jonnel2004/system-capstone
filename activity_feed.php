<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'db.php';
require_once __DIR__ . '/notifications_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$student_id = intval($_SESSION['user_id']);
$student_name = trim((string)($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Student'));
if ($student_name === '') {
    $student_name = 'Student';
}

$student_program = '';
$student_block = '';
$student_profile_pic = '';

$u_q = $conn->query("SELECT * FROM users WHERE id = $student_id LIMIT 1");
if ($u_q && $u_q->num_rows > 0) {
    $u_row = $u_q->fetch_assoc();
    if (!empty($u_row['full_name'])) {
        $student_name = trim((string)$u_row['full_name']);
    } elseif (!empty($u_row['username'])) {
        $student_name = trim((string)$u_row['username']);
    }
    $student_program = trim((string)($u_row['program'] ?? $u_row['course'] ?? ''));
    $student_block = trim((string)($u_row['block'] ?? ''));
    $student_profile_pic = trim((string)($u_row['profile_pic'] ?? ''));
}

$avatar_pic = trim((string)($student_profile_pic !== '' ? $student_profile_pic : ($_SESSION['profile_pic'] ?? '')));
$has_avatar_pic = ($avatar_pic !== '' && file_exists(__DIR__ . '/' . $avatar_pic));


function normalizeBlockKey($raw) {
    $s = strtolower(trim((string)$raw));
    $s = preg_replace('/\bblock\b/i', '', $s);
    $s = preg_replace('/[^a-z0-9]/', '', $s);
    return $s;
}


function normalizeProgramKey($raw) {
    $s = trim((string)$raw);
    if ($s === '') {
        return '';
    }
    if (preg_match('/\(([^()]+)\)\s*$/', $s, $m)) {
        $acr = strtolower(preg_replace('/[^a-z0-9]/i', '', $m[1]));
        if ($acr !== '') {
            return $acr;
        }
    }
    $low = strtolower($s);
    $map = [
        'bsis' => ['information systems', 'bsis', 'bs is'],
        'bscs' => ['computer science', 'bscs', 'bs cs'],
        'bsit' => ['information technology', 'bsit', 'bs it'],
        'bsoad' => ['office administration', 'bsoa', 'bsoad', 'office admin'],
    ];
    foreach ($map as $code => $needles) {
        foreach ($needles as $needle) {
            if (strpos($low, $needle) !== false) {
                return $code;
            }
        }
    }
    return strtolower(preg_replace('/[^a-z0-9]/', '', $s));
}


$conn->query("
    CREATE TABLE IF NOT EXISTS student_activity_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        activity_id INT NOT NULL,
        progress_percent INT NOT NULL DEFAULT 0,
        status_label VARCHAR(50) NOT NULL DEFAULT 'Not Started',
        last_action VARCHAR(50) NOT NULL DEFAULT 'none',
        submission_note TEXT NULL,
        submission_file VARCHAR(255) NULL,
        submitted_at DATETIME NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_student_activity (student_id, activity_id)
    )
");

$prog_cols = [];
$pcol_res = @$conn->query("SHOW COLUMNS FROM student_activity_progress");
if ($pcol_res) {
    while ($pc = $pcol_res->fetch_assoc()) {
        $prog_cols[] = $pc['Field'];
    }
}
if (!in_array('submission_note', $prog_cols, true)) {
    @$conn->query("ALTER TABLE student_activity_progress ADD COLUMN submission_note TEXT NULL DEFAULT NULL");
}
if (!in_array('submission_file', $prog_cols, true)) {
    @$conn->query("ALTER TABLE student_activity_progress ADD COLUMN submission_file VARCHAR(255) NULL DEFAULT NULL");
}
if (!in_array('submitted_at', $prog_cols, true)) {
    @$conn->query("ALTER TABLE student_activity_progress ADD COLUMN submitted_at DATETIME NULL DEFAULT NULL");
}

$act_cols = [];
$acol_res = @$conn->query("SHOW COLUMNS FROM activities");
if ($acol_res) {
    while ($ac = $acol_res->fetch_assoc()) {
        $act_cols[] = $ac['Field'];
    }
    if (!in_array('allow_submission', $act_cols, true)) {
        @$conn->query("ALTER TABLE activities ADD COLUMN allow_submission TINYINT(1) NOT NULL DEFAULT 1");
    }
    if (!in_array('program', $act_cols, true)) {
        @$conn->query("ALTER TABLE activities ADD COLUMN program VARCHAR(100) NULL DEFAULT NULL");
    }
}

$tca_cols = [];
$tcol_res = @$conn->query("SHOW COLUMNS FROM teacher_created_activities");
if ($tcol_res) {
    while ($tc = $tcol_res->fetch_assoc()) {
        $tca_cols[] = $tc['Field'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['track_activity_id'])) {
    header('Content-Type: application/json; charset=utf-8');

    $act_id = intval($_POST['track_activity_id']);
    $action = trim((string)($_POST['track_action'] ?? ''));
    $req_pct = isset($_POST['progress_percent']) ? intval($_POST['progress_percent']) : 0;
    $req_pct = max(0, min(100, $req_pct));

    if ($act_id <= 0) {
        echo json_encode(['ok' => false, 'message' => 'Invalid activity ID']);
        exit();
    }

    $cur_pct = 0;
    $cur_status = 'Not Started';
    $cur_action = 'none';

    $chk = $conn->prepare("SELECT progress_percent, status_label, last_action FROM student_activity_progress WHERE student_id = ? AND activity_id = ? LIMIT 1");
    if ($chk) {
        $chk->bind_param("ii", $student_id, $act_id);
        $chk->execute();
        $res = $chk->get_result();
        if ($r = $res->fetch_assoc()) {
            $cur_pct = intval($r['progress_percent']);
            $cur_status = (string)$r['status_label'];
            $cur_action = (string)$r['last_action'];
        }
        $chk->close();
    }

    $new_pct = $cur_pct;
    $new_status = $cur_status;
    $new_action = $cur_action;

    if ($action === 'opened') {
        $new_pct = ($cur_pct > 0) ? min(100, $cur_pct) : 1;
        $new_status = ($new_pct >= 100) ? '100% Done' : 'Working';
        $new_action = 'opened';
    } elseif ($action === 'scrolling') {
        if ($cur_pct > 0 || $cur_action === 'opened' || $cur_action === 'scrolling') {
            $candidate = min(99, max($cur_pct, $req_pct));
            if ($candidate <= $cur_pct && $cur_pct < 99) {
                $candidate = min(99, $cur_pct + 1);
            }
            $new_pct = max(1, $candidate);
            $new_status = 'Working';
            $new_action = 'scrolling';
        } else {
            $new_pct = 0;
            $new_status = 'Not Started';
            $new_action = 'none';
        }
    } elseif ($action === 'mark_done') {
        $new_pct = 100;
        $new_status = '100% Done';
        $new_action = 'mark_done';
    }

    $stmt = $conn->prepare("
        INSERT INTO student_activity_progress (student_id, activity_id, progress_percent, status_label, last_action)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            progress_percent = VALUES(progress_percent),
            status_label = VALUES(status_label),
            last_action = VALUES(last_action),
            updated_at = CURRENT_TIMESTAMP
    ");
    if ($stmt) {
        $stmt->bind_param("iiiss", $student_id, $act_id, $new_pct, $new_status, $new_action);
        $stmt->execute();
        $stmt->close();
    }

    echo json_encode([
        'ok' => true,
        'activity_id' => $act_id,
        'progress_percent' => $new_pct,
        'status_label' => $new_status,
        'last_action' => $new_action
    ]);
    exit();
}


$submit_msg = '';
$submit_err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_through_system_aid'])) {
    $sub_aid = intval($_POST['submit_through_system_aid']);
    $sub_note = trim((string)($_POST['submission_note'] ?? ''));
    $sub_file_path = '';

    if ($sub_aid <= 0) {
        $submit_err = 'Invalid activity.';
    } else {
        if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
                $orig_sub = $_FILES['submission_file']['name'];
                $tmp_sub = $_FILES['submission_file']['tmp_name'];
                $ext_sub = strtolower(pathinfo($orig_sub, PATHINFO_EXTENSION));
                $allowed_sub = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'zip', 'jpg', 'jpeg', 'png'];
                if (!in_array($ext_sub, $allowed_sub, true)) {
                    $submit_err = 'This file format is not allowed for submission..';
                } else {
                    $sub_dir = __DIR__ . '/uploads/submissions';
                    if (!is_dir($sub_dir)) {
                        @mkdir($sub_dir, 0777, true);
                    }
                    $new_sub_name = 'sub_' . $student_id . '_' . $sub_aid . '_' . time() . '.' . $ext_sub;
                    if (move_uploaded_file($tmp_sub, $sub_dir . '/' . $new_sub_name)) {
                        $sub_file_path = 'uploads/submissions/' . $new_sub_name;
                    } else {
                        $submit_err = 'This file was not uploaded.';
                    }
                }
            } else {
                $submit_err = 'There was a problem uploading the file.';
            }
        }

        if ($submit_err === '' && $sub_note === '' && $sub_file_path === '') {
            $submit_err = 'Enter your answer/note or attach a file before submitting.';
        }

        if ($submit_err === '') {
            $done_pct = 100;
            $done_lbl = 'Submitted (100% Done)';
            $act_lbl = 'submitted';
            $now_dt = date('Y-m-d H:i:s');

            $stmt_sub = $conn->prepare("
                INSERT INTO student_activity_progress
                    (student_id, activity_id, progress_percent, status_label, last_action, submission_note, submission_file, submitted_at)
                VALUES (?, ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?)
                ON DUPLICATE KEY UPDATE
                    progress_percent = VALUES(progress_percent),
                    status_label = VALUES(status_label),
                    last_action = VALUES(last_action),
                    submission_note = COALESCE(VALUES(submission_note), submission_note),
                    submission_file = COALESCE(VALUES(submission_file), submission_file),
                    submitted_at = VALUES(submitted_at),
                    updated_at = CURRENT_TIMESTAMP
            ");
            if ($stmt_sub) {
                $stmt_sub->bind_param("iiisssss", $student_id, $sub_aid, $done_pct, $done_lbl, $act_lbl, $sub_note, $sub_file_path, $now_dt);
                if ($stmt_sub->execute()) {
                    $submit_msg = 'The activity has been submitted through the system!';
                    ccdiSendNotification(
                        $conn,
                        $student_id,
                        'student',
                        'teacher',
                        'submission',
                        'New Submission: ' . $student_name,
                        'Nagsumite si ' . $student_name . ' sa Activity #' . $sub_aid . '.',
                        null,
                        $student_program !== '' ? $student_program : null,
                        $student_block !== '' ? $student_block : null
                    );
                } else {
                    $submit_err = 'The submission was not saved.';
                }
                $stmt_sub->close();
            }
        }
    }
}

$my_progress = [];
$pq = $conn->query("SELECT * FROM student_activity_progress WHERE student_id = $student_id");
if ($pq) {
    while ($pr = $pq->fetch_assoc()) {
        $my_progress[intval($pr['activity_id'])] = $pr;
    }
}

$teacher_programs = [];
foreach (['teacher_assignments', 'teacher_assignment', 'teacher_blocks'] as $t_tbl) {
    try {
        $tr = $conn->query("SELECT * FROM `{$t_tbl}`");
        if ($tr) {
            while ($trow = $tr->fetch_assoc()) {
                $tid = intval($trow['teacher_id'] ?? $trow['user_id'] ?? 0);
                $p_val = trim((string)($trow['program'] ?? $trow['course'] ?? $trow['department'] ?? ''));
                if ($tid > 0 && $p_val !== '') {
                    $teacher_programs[$tid][] = normalizeProgramKey($p_val);
                }
            }
        }
    } catch (mysqli_sql_exception $e) {
    }
}

$stu_prog_key = normalizeProgramKey($student_program);
$stu_blk_key = normalizeBlockKey($student_block);

$student_notifs = ccdiFetchNotificationsForUser(
    $conn,
    $student_id,
    'student',
    $student_program,
    $student_block,
    8
);

$activities = [];
$aq = @$conn->query("SELECT * FROM activities ORDER BY id DESC");
if ($aq) {
    while ($ar = $aq->fetch_assoc()) {
        $act_blk_raw = trim((string)($ar['block'] ?? $ar['block_name'] ?? ''));
        $act_blk_key = normalizeBlockKey($act_blk_raw);
        if ($act_blk_key !== '' && $stu_blk_key !== '' && $act_blk_key !== $stu_blk_key) {
            continue;
        }

        $act_prog_raw = trim((string)($ar['program'] ?? $ar['course'] ?? ''));
        if ($act_prog_raw === '' && !empty($ar['teacher_id'])) {
            $tid_chk = intval($ar['teacher_id']);
            if (!empty($teacher_programs[$tid_chk])) {
                $t_progs = array_values(array_unique(array_filter($teacher_programs[$tid_chk])));
                if (count($t_progs) === 1) {
                    $act_prog_raw = $t_progs[0];
                }
            }
        }
        $act_prog_key = normalizeProgramKey($act_prog_raw);
        if ($act_prog_key !== '' && $stu_prog_key !== '' && $act_prog_key !== $stu_prog_key) {
            continue;
        }

        $allow_sub = isset($ar['allow_submission']) ? (intval($ar['allow_submission']) === 1) : true;
        if (isset($ar['submission_type']) && strtolower(trim((string)$ar['submission_type'])) === 'file_only') {
            $allow_sub = false;
        }
        $ar['_allow_submission'] = $allow_sub;

        $activities[] = $ar;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCDI Student Portal - Activity Feed</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .notif-card {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 16px;
            padding: 16px 20px;
            margin-bottom: 18px;
        }
        .notif-item {
            background: #ffffff;
            border: 1px solid #dbeafe;
            border-radius: 10px;
            padding: 10px 14px;
            margin-top: 8px;
        }
        .feed-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 18px 22px;
            margin-bottom: 16px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
        }
        .feed-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 8px;
        }
        .deadline-pill {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fcd34d;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
        }
        .done-pill {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
        }
        .prog-track {
            width: 100%;
            height: 12px;
            background: #e2e8f0;
            border-radius: 999px;
            overflow: hidden;
            margin: 10px 0;
        }
        .prog-fill {
            height: 100%;
            background: #16a34a;
            transition: width 0.2s ease;
        }
        .act-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 12px;
        }
        .act-btn {
            border: none;
            border-radius: 10px;
            padding: 9px 16px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }
        .btn-open-file {
            background: #1d4ed8;
            color: #ffffff;
        }
        .btn-open-file:hover {
            background: #1e40af;
        }
        .btn-mark-done {
            background: #16a34a;
            color: #ffffff;
        }
        .btn-mark-done:hover {
            background: #15803d;
        }
        .system-submit-box {
            margin-top: 14px;
            padding: 14px 16px;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
        }
        .system-submit-box label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #334155;
            margin-bottom: 4px;
        }
        .system-submit-box textarea {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 10px;
            resize: vertical;
        }
        .btn-submit-sys {
            background: #0b2545;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            padding: 8px 14px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }
        .btn-submit-sys:hover {
            background: #1e6091;
        }

        .viewer-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.65);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .viewer-backdrop.open {
            display: flex;
        }
        .viewer-modal {
            background: #ffffff;
            border-radius: 16px;
            width: 100%;
            max-width: 780px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 24px 48px rgba(15, 23, 42, 0.3);
        }
        .viewer-head {
            background: #0b2545;
            color: #ffffff;
            padding: 14px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        .viewer-scroll-body {
            padding: 20px;
            overflow-y: auto;
            max-height: 62vh;
            background: #f8fafc;
        }
        .viewer-foot {
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
    </style>
</head>
<body>

    <header class="topbar" style="background-color: #0b2545;">
        <div class="brand">
            <div class="brand-logo"><img src="logo.jpg" alt="logo" style="width:100%; height:100%; object-fit:contain;"></div>
            <span>CCDI Student Portal</span>
        </div>
        <input type="text" id="feedSearch" class="search-bar" placeholder="Search activity or subject">
        <div class="user-menu">
        <div class="user-avatar" style="overflow:hidden; padding:0; display:flex; align-items:center; justify-content:center;">
          <?php if ($has_avatar_pic): ?>
          <img src="<?php echo htmlspecialchars($avatar_pic); ?>" alt="Student Profile" style="width:100%; height:100%; object-fit:cover; display:block;">
          <?php else: ?>
           ST
           <?php endif; ?>
           </div>
            <span><?php echo htmlspecialchars($student_name); ?></span>
            <a href="logout.php" class="logout-link">logout</a>
        </div>
    </header>

    <nav class="sidebar">
        <a href="dashboard.php">Dashboard</a>
        <a href="subjects.php">My Subjects</a>
        <a href="activity_feed.php" class="active">Activity Feed</a>
        <a href="live_sessions.php">Live Sessions</a>
        <a href="student_settings.php">Settings</a>
    </nav>

    <main class="main-wrapper">
        <h1 style="font-size:26px; font-weight:800; color:#0f172a; margin-bottom:18px;">Activity Feed</h1>

        <section class="notif-card" aria-label="Instant Notifications and Announcements">
            <div style="font-size:15px; font-weight:800; color:#0b2545;">
                Instant Notifications &amp; Announcements
            </div>
            <?php if (!empty($student_notifs)): ?>
                <?php foreach ($student_notifs as $nf): ?>
                    <div class="notif-item">
                        <div style="display:flex; justify-content:space-between; gap:8px; flex-wrap:wrap;">
                            <strong style="font-size:13px; color:#0f172a;">
                                <?php echo htmlspecialchars((string)($nf['title'] ?? 'Update')); ?>
                            </strong>
                            <span style="font-size:11px; color:#64748b; font-weight:700;">
                                <?php echo strtoupper(htmlspecialchars((string)($nf['sender_role'] ?? 'system'))); ?>
                                <?php if (!empty($nf['created_at'])): ?>
                                    &bull; <?php echo htmlspecialchars((string)$nf['created_at']); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div style="font-size:13px; color:#334155; margin-top:4px;">
                            <?php echo htmlspecialchars((string)($nf['message'] ?? '')); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="font-size:13px; color:#475569; margin-top:6px;">
                    No new announcements or notifications for your program and block yet.
                </div>
            <?php endif; ?>
        </section>

        <?php if ($submit_msg !== ''): ?>
            <div style="background:#dcfce7; color:#166534; padding:10px 16px; border-radius:8px; margin-bottom:14px; font-weight:700; font-size:13px;">
                <?php echo htmlspecialchars($submit_msg); ?>
            </div>
        <?php endif; ?>
        <?php if ($submit_err !== ''): ?>
            <div style="background:#fee2e2; color:#991b1b; padding:10px 16px; border-radius:8px; margin-bottom:14px; font-weight:700; font-size:13px;">
                <?php echo htmlspecialchars($submit_err); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($activities)): ?>
            <?php foreach ($activities as $act): ?>
                <?php
                    $aid = intval($act['id']);
                    $subj = trim((string)($act['subject_code'] ?? $act['subject'] ?? $act['course_code'] ?? 'Subject'));
                    $title = trim((string)($act['title'] ?? $act['activity_name'] ?? 'Activity'));
                    $blk = trim((string)($act['block'] ?? $act['block_name'] ?? ''));
                    $prog_lbl = trim((string)($act['program'] ?? $act['course'] ?? ''));
                    $file_path = trim((string)($act['file_path'] ?? ''));
                    $allow_sub = (bool)($act['_allow_submission'] ?? true);
                    $dl_raw = trim((string)($act['deadline'] ?? ''));
                    $dl_txt = 'No deadline';
                    if ($dl_raw !== '' && $dl_raw !== '0000-00-00 00:00:00') {
                        $ts = strtotime($dl_raw);
                        if ($ts !== false) {
                            $dl_txt = date('M d, Y h:i A', $ts);
                        }
                    }

                    $p_row = $my_progress[$aid] ?? null;
                    $pct = $p_row ? intval($p_row['progress_percent']) : 0;
                    $st_lbl = $p_row ? (string)$p_row['status_label'] : 'Not Started';
                    $submitted_at = $p_row ? trim((string)($p_row['submitted_at'] ?? '')) : '';
                    $is_done = ($pct >= 100 || stripos($st_lbl, 'done') !== false || $submitted_at !== '');
                    if ($is_done) {
                        $pct = 100;
                        $st_lbl = ($submitted_at !== '') ? 'Submitted (100% Done)' : '100% Done';
                    }
                ?>
                <div
                    class="feed-card js-feed-card"
                    data-aid="<?php echo $aid; ?>"
                    data-title="<?php echo htmlspecialchars($title, ENT_QUOTES); ?>"
                    data-subject="<?php echo htmlspecialchars($subj, ENT_QUOTES); ?>"
                    data-file="<?php echo htmlspecialchars($file_path, ENT_QUOTES); ?>"
                    data-pct="<?php echo $pct; ?>"
                >
                    <div class="feed-top">
                        <div>
                            <strong style="font-size:13px; color:#1e6091;"><?php echo htmlspecialchars($subj); ?></strong>
                            <?php if ($prog_lbl !== ''): ?>
                                <span style="font-size:12px; color:#475569;"> &bull; Program: <?php echo htmlspecialchars($prog_lbl); ?></span>
                            <?php endif; ?>
                            <?php if ($blk !== ''): ?>
                                <span style="font-size:12px; color:#64748b;"> &bull; Block: <?php echo htmlspecialchars($blk); ?></span>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;" class="js-badges-wrap">
                            <?php if ($is_done): ?>
                                <span class="done-pill js-done-badge"><?php echo ($submitted_at !== '') ? 'Submitted' : 'Completed'; ?></span>
                            <?php endif; ?>
                            <span class="deadline-pill">Deadline: <?php echo htmlspecialchars($dl_txt); ?></span>
                        </div>
                    </div>

                    <h2 style="font-size:18px; font-weight:800; color:#0f172a; margin:0 0 6px 0;"><?php echo htmlspecialchars($title); ?></h2>
                    <div style="font-size:12px; color:#475569; font-weight:700;" class="js-status-text">
                        Status: <?php echo htmlspecialchars($st_lbl); ?> (<span class="js-pct-num"><?php echo $pct; ?></span>%)
                    </div>

                    <div class="prog-track">
                        <div class="prog-fill js-pct-bar" style="width: <?php echo $pct; ?>%;"></div>
                    </div>

                    <div class="act-actions">
                        <button type="button" class="act-btn btn-open-file js-open-file-btn" data-aid="<?php echo $aid; ?>">
                            Open File
                        </button>

                        <button type="button" class="act-btn btn-mark-done js-mark-done-btn" data-aid="<?php echo $aid; ?>">
                            Mark as Done
                        </button>
                    </div>

                    <?php if ($allow_sub): ?>
                        <form method="POST" enctype="multipart/form-data" class="system-submit-box">
                            <div style="font-size:13px; font-weight:800; color:#0f172a; margin-bottom:8px;">
                                Submit Through System
                            </div>
                            <input type="hidden" name="submit_through_system_aid" value="<?php echo $aid; ?>">

                            <label for="sub_note_<?php echo $aid; ?>">Submission Answer / Note</label>
                            <textarea id="sub_note_<?php echo $aid; ?>" name="submission_note" rows="2" placeholder="Put here your Answer..."><?php echo htmlspecialchars((string)($p_row['submission_note'] ?? '')); ?></textarea>

                            <label for="sub_file_<?php echo $aid; ?>">Attach Submission File (Optional)</label>
                            <input type="file" id="sub_file_<?php echo $aid; ?>" name="submission_file" style="margin-bottom:10px; font-size:12px;">

                            <div>
                                <button type="submit" class="btn-submit-sys">Submit Activity</button>
                                <?php if ($submitted_at !== ''): ?>
                                    <span style="margin-left:8px; font-size:12px; font-weight:700; color:#15803d;">
                                        Submitted noong <?php echo htmlspecialchars($submitted_at); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:24px; text-align:center; color:#64748b;">
                There are no activities for your program and block at the moment.
            </div>
        <?php endif; ?>
    </main>

    <div class="viewer-backdrop" id="fileViewerModal">
        <div class="viewer-modal">
            <div class="viewer-head">
                <div>
                    <div style="font-size:11px; color:#93c5fd; font-weight:700;" id="viewerSubject">SUBJECT</div>
                    <h3 style="margin:2px 0 0 0; font-size:16px; font-weight:800;" id="viewerTitle">File Preview</h3>
                </div>
                <button type="button" id="closeViewerBtn" style="background:#ef4444; color:#fff; border:none; border-radius:8px; padding:6px 12px; font-weight:700; cursor:pointer;">Close</button>
            </div>

            <div class="viewer-scroll-body" id="viewerScrollArea">
                <div id="viewerEmbedBox" style="font-size:13px; color:#334155;">No file attached.</div>
            </div>

            <div class="viewer-foot">
                <div style="font-size:12px; font-weight:700; color:#0f172a;">
                    Scroll Progress: <span id="viewerPctLabel">0</span>% &bull; <span id="viewerModeNote" style="color:#1e6091;">Reading</span>
                </div>
                <div style="display:flex; gap:8px;">
                    <a id="viewerDirectLink" href="#" target="_blank" style="background:#e2e8f0; color:#0f172a; text-decoration:none; padding:7px 12px; border-radius:8px; font-size:12px; font-weight:700;">
                        Open in New Tab
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function() {
            var searchEl = document.getElementById('feedSearch');
            var cards = document.querySelectorAll('.js-feed-card');
            var modal = document.getElementById('fileViewerModal');
            var closeBtn = document.getElementById('closeViewerBtn');
            var scrollArea = document.getElementById('viewerScrollArea');
            var viewerSubj = document.getElementById('viewerSubject');
            var viewerTitle = document.getElementById('viewerTitle');
            var viewerEmbed = document.getElementById('viewerEmbedBox');
            var viewerLink = document.getElementById('viewerDirectLink');
            var viewerPctLbl = document.getElementById('viewerPctLabel');
            var viewerModeNote = document.getElementById('viewerModeNote');

            var activeAid = null;
            var lastSentPct = 0;

            if (searchEl) {
                searchEl.addEventListener('input', function() {
                    var q = this.value.toLowerCase().trim();
                    cards.forEach(function(c) {
                        c.style.display = (q === '' || c.textContent.toLowerCase().indexOf(q) !== -1) ? 'block' : 'none';
                    });
                });
            }

            function updateCardUi(aid, pct, statusLabel) {
                var card = document.querySelector('.js-feed-card[data-aid="' + aid + '"]');
                if (!card) return;
                var safePct = Math.max(0, Math.min(100, parseInt(pct || 0, 10)));
                card.setAttribute('data-pct', String(safePct));

                var pctNum = card.querySelector('.js-pct-num');
                var pctBar = card.querySelector('.js-pct-bar');
                var statusTxt = card.querySelector('.js-status-text');
                var badgesWrap = card.querySelector('.js-badges-wrap');

                if (pctNum) pctNum.textContent = String(safePct);
                if (pctBar) pctBar.style.width = safePct + '%';
                if (statusTxt) {
                    statusTxt.innerHTML = 'Status: ' + (statusLabel || (safePct >= 100 ? '100% Done' : 'Working')) + ' (<span class="js-pct-num">' + safePct + '</span>%)';
                }
                if (safePct >= 100 && badgesWrap && !badgesWrap.querySelector('.js-done-badge')) {
                    var pill = document.createElement('span');
                    pill.className = 'done-pill js-done-badge';
                    pill.textContent = 'Completed';
                    badgesWrap.insertBefore(pill, badgesWrap.firstChild);
                }
            }

            function sendTrack(aid, action, pct) {
                var body = new URLSearchParams();
                body.append('track_activity_id', String(aid));
                body.append('track_action', String(action));
                body.append('progress_percent', String(pct || 0));

                return fetch('activity_feed.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString()
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data && data.ok) {
                        updateCardUi(data.activity_id, data.progress_percent, data.status_label);
                        if (viewerPctLbl && activeAid === data.activity_id) {
                            viewerPctLbl.textContent = String(data.progress_percent);
                        }
                    }
                })
                .catch(function() {});
            }

            document.querySelectorAll('.js-open-file-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var aid = parseInt(this.getAttribute('data-aid'), 10);
                    var card = document.querySelector('.js-feed-card[data-aid="' + aid + '"]');
                    if (!card || !modal) return;

                    activeAid = aid;
                    var title = card.getAttribute('data-title') || 'File Preview';
                    var subj = card.getAttribute('data-subject') || 'SUBJECT';
                    var filePath = card.getAttribute('data-file') || '';
                    var curPct = parseInt(card.getAttribute('data-pct') || '0', 10);
                    lastSentPct = curPct;

                    if (viewerSubj) viewerSubj.textContent = subj;
                    if (viewerTitle) viewerTitle.textContent = title;
                    if (viewerPctLbl) viewerPctLbl.textContent = String(curPct > 0 ? curPct : 1);
                    if (viewerModeNote) viewerModeNote.textContent = 'Reading';

                    if (viewerEmbed) {
                        if (filePath !== '') {
                            var ext = filePath.split('.').pop().toLowerCase();
                            if (ext === 'pdf') {
                                viewerEmbed.innerHTML = '<iframe src="' + filePath + '" style="width:100%; height:420px; border:none; border-radius:8px;"></iframe>';
                            } else if (['jpg', 'jpeg', 'png', 'gif', 'webp'].indexOf(ext) !== -1) {
                                viewerEmbed.innerHTML = '<img src="' + filePath + '" alt="Attachment" style="max-width:100%; height:auto; border-radius:8px; display:block; margin:0 auto;">';
                            } else {
                                viewerEmbed.innerHTML = '<div style="padding:16px; background:#ffffff; border:1px solid #cbd5e1; border-radius:10px;">Attached file: <strong>' + filePath + '</strong>. Use "Open in New Tab" below if preview is not supported in browser.</div>';
                            }
                            if (viewerLink) {
                                viewerLink.href = filePath;
                                viewerLink.style.display = 'inline-block';
                            }
                        } else {
                            viewerEmbed.innerHTML = '<div style="padding:16px; background:#ffffff; border:1px solid #cbd5e1; border-radius:10px;">No file attached to this activity. Scroll through this panel or click <strong>Mark as Done</strong> when finished.</div>';
                            if (viewerLink) viewerLink.style.display = 'none';
                        }
                    }

                    if (scrollArea) scrollArea.scrollTop = 0;
                    modal.classList.add('open');
                    sendTrack(aid, 'opened', curPct > 0 ? curPct : 1);
                });
            });

            if (scrollArea) {
                scrollArea.addEventListener('scroll', function() {
                    if (!activeAid) return;
                    var maxScroll = scrollArea.scrollHeight - scrollArea.clientHeight;
                    var pct = 1;
                    if (maxScroll > 0) {
                        pct = Math.round((scrollArea.scrollTop / maxScroll) * 99);
                        pct = Math.max(1, Math.min(99, pct));
                    }
                    if (viewerPctLbl) viewerPctLbl.textContent = String(pct);
                    if (pct > lastSentPct && (pct - lastSentPct >= 5 || pct >= 90)) {
                        lastSentPct = pct;
                        sendTrack(activeAid, 'scrolling', pct);
                    }
                });
            }

            document.querySelectorAll('.js-mark-done-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var aid = parseInt(this.getAttribute('data-aid'), 10);
                    if (!aid) return;
                    sendTrack(aid, 'mark_done', 100);
                });
            });

            if (closeBtn && modal) {
                closeBtn.addEventListener('click', function() {
                    modal.classList.remove('open');
                    activeAid = null;
                });
            }
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        modal.classList.remove('open');
                        activeAid = null;
                    }
                });
            }
        })();
    </script>
</body>
</html>