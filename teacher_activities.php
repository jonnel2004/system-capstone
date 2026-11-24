<?php
if (session_id() === '') {
    @session_start();
}
include 'db.php';
require_once __DIR__ . '/notifications_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$teacher_id = intval($_SESSION['user_id']);
$teacher_name = trim((string)($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Teacher'));

$avatar_pic = trim((string)($teacher['profile_pic'] ?? $_SESSION['profile_pic'] ?? ''));
$has_avatar_pic = ($avatar_pic !== '' && file_exists(__DIR__ . '/' . $avatar_pic));


$conn->query("
    CREATE TABLE IF NOT EXISTS activities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NULL,
        subject_code VARCHAR(100) NOT NULL DEFAULT '',
        title VARCHAR(255) NOT NULL DEFAULT '',
        program VARCHAR(100) NULL DEFAULT NULL,
        block VARCHAR(50) NOT NULL DEFAULT '',
        allow_submission TINYINT(1) NOT NULL DEFAULT 1,
        file_path VARCHAR(255) NULL DEFAULT NULL,
        deadline DATETIME NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");


$chk_dl = $conn->query("SHOW COLUMNS FROM activities LIKE 'deadline'");
if ($chk_dl && $chk_dl->num_rows === 0) {
    $conn->query("ALTER TABLE activities ADD COLUMN deadline DATETIME NULL DEFAULT NULL AFTER file_path");
}
$chk_prog = $conn->query("SHOW COLUMNS FROM activities LIKE 'program'");
if ($chk_prog && $chk_prog->num_rows === 0) {
    $conn->query("ALTER TABLE activities ADD COLUMN program VARCHAR(100) NULL DEFAULT NULL AFTER block");
}
$chk_sub = $conn->query("SHOW COLUMNS FROM activities LIKE 'allow_submission'");
if ($chk_sub && $chk_sub->num_rows === 0) {
    $conn->query("ALTER TABLE activities ADD COLUMN allow_submission TINYINT(1) NOT NULL DEFAULT 1 AFTER file_path");
}


$conn->query("
    CREATE TABLE IF NOT EXISTS student_activity_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        activity_id INT NOT NULL,
        progress_percent INT NOT NULL DEFAULT 0,
        status_label VARCHAR(50) NOT NULL DEFAULT 'Not Working',
        last_action VARCHAR(50) NOT NULL DEFAULT 'none',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_student_activity (student_id, activity_id)
    )
");

$act_cols = [];
$col_res = $conn->query("SHOW COLUMNS FROM activities");
if ($col_res) {
    while ($c = $col_res->fetch_assoc()) {
        $act_cols[] = $c['Field'];
    }
}

$activity_msg = '';
$activity_err = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_teacher_announcement'])) {
    $ann_title = trim((string)($_POST['announcement_title'] ?? ''));
    $ann_msg   = trim((string)($_POST['announcement_message'] ?? ''));
    $ann_prog  = trim((string)($_POST['announcement_program'] ?? ''));
    $ann_blk   = trim((string)($_POST['announcement_block'] ?? ''));

    if ($ann_title === '' || $ann_msg === '') {
        $activity_err = 'Enter the title and message of the announcement..';
    } else {
        $sent = ccdiSendNotification(
            $conn,
            $teacher_id,
            'teacher',
            'student',
            'announcement',
            $ann_title,
            $ann_msg,
            null,
            $ann_prog !== '' ? $ann_prog : null,
            $ann_blk !== '' ? $ann_blk : null
        );
        if ($sent) {
            $activity_msg = 'The announcement for the selected target has been posted.!';
        } else {
            $activity_err = 'The announcement was not posted.t.';
        }
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_activity_id'])) {
    $del_id = intval($_POST['delete_activity_id']);

    if ($del_id > 0) {
        $del_prog = $conn->prepare("DELETE FROM student_activity_progress WHERE activity_id = ?");
        if ($del_prog) {
            $del_prog->bind_param("i", $del_id);
            $del_prog->execute();
            $del_prog->close();
        }

        $del_act = $conn->prepare("DELETE FROM activities WHERE id = ? LIMIT 1");
        if ($del_act) {
            $del_act->bind_param("i", $del_id);
            if ($del_act->execute() && $del_act->affected_rows > 0) {
                $activity_msg = 'The activity has been deleted!';
            } else {
                $activity_err = 'The activity was not deleted or does not exist in the database.';
            }
            $del_act->close();
        } else {
            $activity_err = 'There was an error deleting the activity.';
        }
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_activity'])) {
    $subj = trim((string)($_POST['subject_code'] ?? ''));
    $title = trim((string)($_POST['activity_title'] ?? ''));
    $program = trim((string)($_POST['program'] ?? ''));
    $block = trim((string)($_POST['block'] ?? ''));
    $allow_sub = (isset($_POST['allow_submission']) && (string)$_POST['allow_submission'] === '0') ? 0 : 1;
    $deadline_raw = trim((string)($_POST['deadline'] ?? ''));
    $deadline_val = null;
    if ($deadline_raw !== '') {
        $ts = strtotime($deadline_raw);
        if ($ts !== false) {
            $deadline_val = date('Y-m-d H:i:s', $ts);
        }
    }
    $uploaded_file = '';

    if ($subj === '' || $title === '') {
        $activity_err = 'Enter the Subject and Activity Title.';
    } else {
        if (isset($_FILES['activity_file']) && $_FILES['activity_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/uploads/activities';
            if (!is_dir($upload_dir)) {
                @mkdir($upload_dir, 0777, true);
            }
            $orig = basename($_FILES['activity_file']['name']);
            $safe_name = time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $orig);
            if (move_uploaded_file($_FILES['activity_file']['tmp_name'], $upload_dir . '/' . $safe_name)) {
                $uploaded_file = 'uploads/activities/' . $safe_name;
            }
        }

        $fields = [];
        $placeholders = [];
        $types = '';
        $vals = [];

        if (in_array('teacher_id', $act_cols, true)) {
            $fields[] = 'teacher_id';
            $placeholders[] = '?';
            $types .= 'i';
            $vals[] = $teacher_id;
        }
        if (in_array('subject_code', $act_cols, true)) {
            $fields[] = 'subject_code';
            $placeholders[] = '?';
            $types .= 's';
            $vals[] = $subj;
        } elseif (in_array('subject', $act_cols, true)) {
            $fields[] = 'subject';
            $placeholders[] = '?';
            $types .= 's';
            $vals[] = $subj;
        }
        if (in_array('title', $act_cols, true)) {
            $fields[] = 'title';
            $placeholders[] = '?';
            $types .= 's';
            $vals[] = $title;
        }
        if ($program !== '' && in_array('program', $act_cols, true)) {
            $fields[] = 'program';
            $placeholders[] = '?';
            $types .= 's';
            $vals[] = $program;
        }
        if (in_array('block', $act_cols, true)) {
            $fields[] = 'block';
            $placeholders[] = '?';
            $types .= 's';
            $vals[] = $block;
        }
        if (in_array('allow_submission', $act_cols, true)) {
            $fields[] = 'allow_submission';
            $placeholders[] = '?';
            $types .= 'i';
            $vals[] = $allow_sub;
        }
        if ($uploaded_file !== '' && in_array('file_path', $act_cols, true)) {
            $fields[] = 'file_path';
            $placeholders[] = '?';
            $types .= 's';
            $vals[] = $uploaded_file;
        }
        if (in_array('deadline', $act_cols, true)) {
            $fields[] = 'deadline';
            $placeholders[] = '?';
            $types .= 's';
            $vals[] = $deadline_val;
        }

        if (!empty($fields)) {
            $sql_ins = "INSERT INTO activities (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $ins = $conn->prepare($sql_ins);
            if ($ins) {
                $ins->bind_param($types, ...$vals);
                if ($ins->execute()) {
                    $activity_msg = 'The new activity has been added.!';
                    $dl_notif_txt = ($deadline_val !== null) ? date('M d, Y h:i A', strtotime($deadline_val)) : 'No deadline';
                    ccdiSendNotification(
                        $conn,
                        $teacher_id,
                        'teacher',
                        'student',
                        'activity',
                        'New Activity: ' . $title,
                        $subj . ' — Deadline: ' . $dl_notif_txt,
                        null,
                        $program !== '' ? $program : null,
                        $block !== '' ? $block : null
                    );
                } else {
                    $activity_err = 'The activity was not saved..';
                }
                $ins->close();
            }
        }
    }
}


function fetchProgressByActivity(mysqli $conn): array {
    $map = [];
    $q_prog = $conn->query("
        SELECT
            sap.activity_id,
            sap.progress_percent,
            sap.status_label,
            sap.last_action,
            sap.updated_at,
            COALESCE(NULLIF(TRIM(u.full_name), ''), u.username) AS student_name,
            u.block AS student_block
        FROM student_activity_progress sap
        INNER JOIN users u ON u.id = sap.student_id
        ORDER BY sap.updated_at DESC
    ");
    if ($q_prog && $q_prog->num_rows > 0) {
        while ($p = $q_prog->fetch_assoc()) {
            $aid = intval($p['activity_id']);
            $pct = max(0, min(100, intval($p['progress_percent'])));
            $rawStatus = strtolower(trim((string)($p['status_label'] ?? '')));
            $rawAction = strtolower(trim((string)($p['last_action'] ?? '')));

            $opened_file = ($rawAction === 'opened' || $rawAction === 'open' || $pct > 0 || strpos($rawStatus, 'working') !== false || strpos($rawStatus, 'done') !== false);
            $is_working  = ($pct > 0 || strpos($rawStatus, 'working') !== false || strpos($rawStatus, 'done') !== false || strpos($rawStatus, 'progress') !== false || strpos($rawStatus, 'started') !== false);

            $p['progress_percent'] = $pct;
            $p['opened_file'] = $opened_file;
            $p['is_working'] = $is_working;
            $p['work_badge'] = $is_working ? 'Working' : 'Not Working';
            $map[$aid][] = $p;
        }
    }
    return $map;
}


if (isset($_GET['rt_monitor']) && $_GET['rt_monitor'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'progress_by_act' => fetchProgressByActivity($conn),
        'server_time'     => date('h:i:s A')
    ]);
    exit();
}


$activities = [];
$order_col = in_array('id', $act_cols, true) ? 'id DESC' : '1';
$q_act = $conn->query("SELECT * FROM activities ORDER BY {$order_col}");
if ($q_act && $q_act->num_rows > 0) {
    while ($row = $q_act->fetch_assoc()) {
        $activities[] = $row;
    }
}

$progress_by_act = fetchProgressByActivity($conn);
$teacher_notifs = array_values(array_filter(
    ccdiFetchNotificationsForUser($conn, $teacher_id, 'teacher', '', '', 20),
    function ($n) use ($teacher_id) {
        $sRole = strtolower(trim((string)($n['sender_role'] ?? 'teacher')));
        $sId   = isset($n['sender_id']) ? intval($n['sender_id']) : 0;
        return ($sRole === 'teacher' && ($sId === 0 || $sId === $teacher_id)) || ($sRole === 'admin');
    }
));
$teacher_notifs = array_slice($teacher_notifs, 0, 6);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCDI Teacher Portal - Activities</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .teacher-top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 16px;
        }
        .teacher-title-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .teacher-badge-icon {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: #0b2545;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        .teacher-top-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .btn-add-activity {
            background: #0b2545;
            color: #ffffff;
            border: none;
            border-radius: 10px;
            padding: 9px 16px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }
        .btn-add-activity:hover {
            background: #1e6091;
        }
        .announcement-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 16px 20px;
            margin-bottom: 18px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
        }
        .announcement-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        .announcement-grid input,
        .announcement-grid select,
        .announcement-box textarea {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13px;
            background: #ffffff;
        }
        .live-indicator {
            font-size: 12px;
            font-weight: 700;
            color: #15803d;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .live-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #16a34a;
            display: inline-block;
        }
        .activity-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 18px;
        }
        .activity-pill-btn {
            background: #e2e8f0;
            color: #1e293b;
            border: none;
            border-radius: 999px;
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }
        .activity-pill-btn.active {
            background: #1e6091;
            color: #ffffff;
        }
        .teacher-activity-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);
            margin-bottom: 18px;
        }
        .activity-card-banner {
            background: linear-gradient(135deg, #1d70b8, #1e6091);
            color: #ffffff;
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .activity-banner-left {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 0.3px;
        }
        .activity-banner-center {
            font-size: 16px;
            font-weight: 700;
        }
        .activity-banner-right {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .block-tag {
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }
        .deadline-tag {
            background: #fef3c7;
            color: #92400e;
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
        }
        .btn-delete-activity {
            background-color: #dc2626;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: background-color 0.15s ease, transform 0.15s ease;
        }
        .btn-delete-activity:hover {
            background-color: #991b1b;
            transform: translateY(-1px);
        }
        .activity-card-body {
            padding: 16px 20px;
        }
        .student-progress-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            padding: 12px 14px;
            border-radius: 12px;
            margin-bottom: 10px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .student-progress-item:last-child {
            margin-bottom: 0;
        }
        .student-progress-item.done {
            background: #84b7d3;
            color: #ffffff;
            border-color: #0284c7;
        }
        .student-progress-item.working {
            background: #fefce8;
            color: #713f12;
            border-color: #fde047;
        }
        .student-progress-item.not-working {
            background: #fef2f2;
            color: #7f1d1d;
            border-color: #fecaca;
        }
        .work-badge {
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
        }
        .work-badge.is-working {
            background: #dcfce7;
            color: #166534;
        }
        .work-badge.is-not-working {
            background: #fee2e2;
            color: #991b1b;
        }
        .status-pill {
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            background: #ffffff;
            color: #0f172a;
        }
        .modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .modal-backdrop.open {
            display: flex;
        }
        .modal-box {
            background: #ffffff;
            border-radius: 16px;
            padding: 22px;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.2);
        }
        .modal-field {
            margin-bottom: 12px;
        }
        .modal-field label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #334155;
            margin-bottom: 4px;
        }
        .modal-field input,
        .modal-field select {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            background: #ffffff;
        }
    </style>
</head>
<body>

    <header class="topbar" style="background-color: #0b2545;">
        <div class="brand">
            <div class="brand-logo"><img src="logo.jpg" alt="logo" style="width:100%; height:100%; object-fit:contain;"></div>
            <span>CCDI Teacher Portal</span>
        </div>
        <input type="text" id="teacherActivitySearch" class="search-bar" placeholder="Search">
        <div class="user-menu">
            <div class="user-avatar" style="overflow:hidden; padding:0; display:flex; align-items:center; justify-content:center;">
                <?php if ($has_avatar_pic): ?>
                    <img src="<?php echo htmlspecialchars($avatar_pic); ?>" alt="Profile Picture" style="width:100%; height:100%; object-fit:cover; display:block;">
                <?php else: ?>
                    TC
                <?php endif; ?>
            </div>
            <span><?php echo htmlspecialchars($teacher_name); ?></span>
            <a href="logout.php" class="logout-link">logout</a>
        </div>
    </header>

    <nav class="sidebar">
        <a href="teacher_dashboard.php">Dashboard</a>
        <a href="teacher_subjects.php">Subjects</a>
        <a href="teacher_activities.php" class="active">Activities</a>
        <a href="teacher_live.php">Live Sessions</a>
        <a href="teacher_settings.php">Settings</a>
    </nav>

    <main class="main-wrapper">
        <div class="teacher-top-bar">
            <div class="teacher-title-group">
                <div class="teacher-badge-icon">&#128100;</div>
                <div>
                    <h1 style="font-size:22px; font-weight:800; color:#0f172a; margin:0;"><?php echo htmlspecialchars($teacher_name); ?></h1>
                    <p style="font-size:12px; color:#64748b; margin:2px 0 0 0;">Manage Activities, Deadlines, Announcements &amp; Student File Progress</p>
                </div>
            </div>

            <div class="teacher-top-actions">
                <button type="button" id="openAddActivityModal" class="btn-add-activity">+ Add Activity</button>
                <span class="live-indicator">
                    <span class="live-dot"></span>
                    Real-time Monitoring Active (<span id="rtMonitorTime"><?php echo date('h:i:s A'); ?></span>)
                </span>
            </div>
        </div>

        <?php if ($activity_msg !== ''): ?>
            <div style="background:#dcfce7; color:#15803d; border:1px solid #86efac; padding:10px 14px; border-radius:10px; font-size:13px; font-weight:700; margin-bottom:14px;">
                <?php echo htmlspecialchars($activity_msg); ?>
            </div>
        <?php endif; ?>
        <?php if ($activity_err !== ''): ?>
            <div style="background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; padding:10px 14px; border-radius:10px; font-size:13px; font-weight:700; margin-bottom:14px;">
                <?php echo htmlspecialchars($activity_err); ?>
            </div>
        <?php endif; ?>

        <section class="announcement-box" aria-label="Post Announcement">
            <div style="font-size:15px; font-weight:800; color:#0f172a;">Post Announcement to Students</div>
            <form method="POST">
                <div class="announcement-grid">
                    <input type="text" name="announcement_title" placeholder="Announcement Title (e.g. Class Reminder)" required>
                    <select name="announcement_program">
                        <option value="">All Programs</option>
                        <option value="BSIS">BSIS</option>
                        <option value="BSCS">BSCS</option>
                        <option value="BSIT">BSIT</option>
                        <option value="BSOAD">BSOAD</option>
                    </select>
                    <input type="text" name="announcement_block" placeholder="Block (optional, e.g. 3A)">
                </div>
                <div style="margin-top:10px;">
                    <textarea name="announcement_message" rows="2" placeholder="Write your announcement message..." required></textarea>
                </div>
                <div style="margin-top:10px;">
                    <button type="submit" name="post_teacher_announcement" class="btn-add-activity">Send Announcement</button>
                </div>
            </form>

            <?php if (!empty($teacher_notifs)): ?>
                <div style="margin-top:14px; border-top:1px solid #e2e8f0; padding-top:10px;">
                    <div style="font-size:12px; font-weight:800; color:#334155; margin-bottom:6px;">Recent Notifications</div>
                    <?php foreach ($teacher_notifs as $tn): ?>
                        <?php
                            $sRole = strtolower(trim((string)($tn['sender_role'] ?? 'teacher')));
                            $senderBadge = ($sRole === 'admin') ? 'Admin' : 'Teacher';
                            $badgeBg = ($sRole === 'admin') ? '#fee2e2' : '#dbeafe';
                            $badgeColor = ($sRole === 'admin') ? '#991b1b' : '#1e40af';
                        ?>
                        <div style="font-size:12px; color:#475569; padding:5px 0; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                            <span style="background:<?php echo $badgeBg; ?>; color:<?php echo $badgeColor; ?>; font-weight:800; padding:2px 8px; border-radius:999px; font-size:11px;">
                                <?php echo htmlspecialchars($senderBadge); ?>
                            </span>
                            <strong><?php echo htmlspecialchars((string)($tn['title'] ?? '')); ?></strong>:
                            <span><?php echo htmlspecialchars((string)($tn['message'] ?? '')); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <div class="activity-pills" id="activityFilterPills">
            <button type="button" class="activity-pill-btn active" data-filter="all">All (<?php echo count($activities); ?>)</button>
            <?php foreach ($activities as $act): ?>
                <?php
                    $aid = intval($act['id']);
                    $pill_title = trim((string)($act['title'] ?? 'Activity'));
                    $pill_prog = trim((string)($act['program'] ?? ''));
                    $pill_block = trim((string)($act['block'] ?? ''));
                    $meta_parts = array_filter([$pill_prog, $pill_block]);
                    $pill_label = $pill_title . (!empty($meta_parts) ? ' (' . implode(' • ', $meta_parts) . ')' : '');
                ?>
                <button type="button" class="activity-pill-btn" data-filter="act-<?php echo $aid; ?>">
                    <?php echo htmlspecialchars($pill_label); ?>
                </button>
            <?php endforeach; ?>
        </div>

        <div id="teacherActivitiesContainer">
            <?php if (!empty($activities)): ?>
                <?php foreach ($activities as $act): ?>
                    <?php
                        $aid = intval($act['id']);
                        $subj_disp = trim((string)($act['subject_code'] ?? $act['subject'] ?? 'SUBJECT'));
                        $title_disp = trim((string)($act['title'] ?? 'Untitled Activity'));
                        $prog_disp = trim((string)($act['program'] ?? ''));
                        $block_disp = trim((string)($act['block'] ?? ''));
                        $sub_mode_disp = (isset($act['allow_submission']) && intval($act['allow_submission']) === 0)
                            ? 'File Only'
                            : 'Submit Through System';
                        $dl_raw = trim((string)($act['deadline'] ?? ''));
                        $dl_disp = 'No deadline';
                        if ($dl_raw !== '' && $dl_raw !== '0000-00-00 00:00:00') {
                            $ts_dl = strtotime($dl_raw);
                            if ($ts_dl !== false) {
                                $dl_disp = date('M d, Y h:i A', $ts_dl);
                            }
                        }
                        $stu_list = $progress_by_act[$aid] ?? [];
                    ?>
                    <div class="teacher-activity-card js-teacher-act-card" data-card-id="act-<?php echo $aid; ?>" data-activity-id="<?php echo $aid; ?>">
                        <div class="activity-card-banner">
                            <div class="activity-banner-left"><?php echo htmlspecialchars($subj_disp); ?></div>
                            <div class="activity-banner-center"><?php echo htmlspecialchars($title_disp); ?></div>
                            <div class="activity-banner-right">
                                <?php if ($prog_disp !== ''): ?>
                                    <span class="block-tag">Program: <?php echo htmlspecialchars($prog_disp); ?></span>
                                <?php endif; ?>
                                <?php if ($block_disp !== ''): ?>
                                    <span class="block-tag">Block: <?php echo htmlspecialchars($block_disp); ?></span>
                                <?php endif; ?>
                                <span class="block-tag"><?php echo htmlspecialchars($sub_mode_disp); ?></span>
                                <span class="deadline-tag">Deadline: <?php echo htmlspecialchars($dl_disp); ?></span>

                                <form method="POST" style="display:inline-block; margin:0;" onsubmit="return confirm('Are you sure you want to delete the activity &quot;<?php echo htmlspecialchars($title_disp, ENT_QUOTES); ?>&quot;?');">
                                    <input type="hidden" name="delete_activity_id" value="<?php echo $aid; ?>">
                                    <button type="submit" class="btn-delete-activity" title="Delete Activity">
                                        &#128465; Delete
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="activity-card-body" data-progress-for="<?php echo $aid; ?>">
                            <?php if (!empty($stu_list)): ?>
                                <?php foreach ($stu_list as $sp): ?>
                                    <?php
                                        $pct = intval($sp['progress_percent']);
                                        $is_done = ($pct >= 100);
                                        $is_working = !empty($sp['is_working']);
                                        $opened_file = !empty($sp['opened_file']);
                                        $item_cls = $is_done ? 'done' : ($is_working ? 'working' : 'not-working');
                                        $open_label = $opened_file ? 'Opened file' : 'Not opened file';
                                        $work_label = $is_working ? 'Working' : 'Not Working';
                                        $detail_txt = $is_done
                                            ? ('100% done - ' . $open_label)
                                            : ($work_label . ' (' . $pct . '%) - ' . $open_label . (!empty($sp['last_action']) ? ' • ' . htmlspecialchars($sp['last_action']) : ''));
                                    ?>
                                    <div class="student-progress-item <?php echo $item_cls; ?>">
                                        <div>
                                            <strong>&#128100; <?php echo htmlspecialchars($sp['student_name']); ?></strong>
                                            <?php if (!empty($sp['student_block'])): ?>
                                                <span style="font-size:12px; opacity:0.9;">(Block: <?php echo htmlspecialchars($sp['student_block']); ?>)</span>
                                            <?php endif; ?>
                                            <span class="work-badge <?php echo $is_working ? 'is-working' : 'is-not-working'; ?>">
                                                <?php echo htmlspecialchars($work_label); ?>
                                            </span>
                                            <div style="font-size:12px; margin-top:4px; opacity:0.95;">
                                                <?php echo $detail_txt; ?> &bull; <?php echo htmlspecialchars($sp['updated_at'] ?? ''); ?>
                                            </div>
                                        </div>
                                        <span class="status-pill">
                                            <?php echo $is_done ? '100% done' : ($pct . '%'); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="font-size:13px; color:#64748b; margin:4px 0;">No students have opened or are working on this activity.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="panel-card" style="text-align:center; color:#64748b; padding:28px;">
                No activity has been created yet. Click <strong>+ Add Activity</strong> to create one.
                </div>
            <?php endif; ?>
        </div>
    </main>

    <div class="modal-backdrop" id="addActivityModal">
        <div class="modal-box">
            <h2 style="font-size:18px; font-weight:800; color:#0f172a; margin:0 0 14px 0;">Add New Activity</h2>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-field">
                    <label>Subject Code</label>
                    <input type="text" name="subject_code" placeholder="e.g. IS 101" required>
                </div>
                <div class="modal-field">
                    <label>Activity Title</label>
                    <input type="text" name="activity_title" placeholder="e.g. Lab Exercise 1" required>
                </div>
                <div class="modal-field">
                    <label>Program</label>
                    <select name="program">
                        <option value="">All Programs</option>
                        <option value="BSIS">BSIS</option>
                        <option value="BSCS">BSCS</option>
                        <option value="BSIT">BSIT</option>
                        <option value="BSOAD">BSOAD</option>
                    </select>
                </div>
                <div class="modal-field">
                    <label>Block</label>
                    <input type="text" name="block" placeholder="e.g. 3A">
                </div>
                <div class="modal-field">
                    <label>Submission Mode</label>
                    <select name="allow_submission">
                        <option value="1">Submit Through System</option>
                        <option value="0">File Only</option>
                    </select>
                </div>
                <div class="modal-field">
                    <label>Deadline (Optional)</label>
                    <input type="datetime-local" name="deadline">
                </div>
                <div class="modal-field">
                    <label>Attachment (Optional)</label>
                    <input type="file" name="activity_file">
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:16px;">
                    <button type="button" id="closeAddActivityModal" style="background:#e2e8f0; color:#1e293b; border:none; border-radius:8px; padding:8px 14px; font-weight:700; cursor:pointer;">Cancel</button>
                    <button type="submit" name="create_activity" class="btn-add-activity">Save Activity</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const modal = document.getElementById('addActivityModal');
            const openBtn = document.getElementById('openAddActivityModal');
            const closeBtn = document.getElementById('closeAddActivityModal');

            if (openBtn && modal) {
                openBtn.addEventListener('click', function () {
                    modal.classList.add('open');
                });
            }
            if (closeBtn && modal) {
                closeBtn.addEventListener('click', function () {
                    modal.classList.remove('open');
                });
            }
            if (modal) {
                modal.addEventListener('click', function (e) {
                    if (e.target === modal) {
                        modal.classList.remove('open');
                    }
                });
            }

            const pills = document.querySelectorAll('#activityFilterPills .activity-pill-btn');
            const cards = document.querySelectorAll('#teacherActivitiesContainer .js-teacher-act-card');
            pills.forEach(function (pill) {
                pill.addEventListener('click', function () {
                    pills.forEach(function (p) { p.classList.remove('active'); });
                    pill.classList.add('active');
                    const filter = pill.getAttribute('data-filter');
                    cards.forEach(function (card) {
                        if (filter === 'all' || card.getAttribute('data-card-id') === filter) {
                            card.style.display = '';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                });
            });

            const searchInput = document.getElementById('teacherActivitySearch');
            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    const q = searchInput.value.trim().toLowerCase();
                    cards.forEach(function (card) {
                        const txt = card.textContent.toLowerCase();
                        card.style.display = (q === '' || txt.indexOf(q) !== -1) ? '' : 'none';
                    });
                });
            }

            const timeEl = document.getElementById('rtMonitorTime');
            setInterval(function () {
                fetch('teacher_activities.php?rt_monitor=1')
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        if (data && data.server_time && timeEl) {
                            timeEl.textContent = data.server_time;
                        }
                    })
                    .catch(function () {});
            }, 10000);
        })();
    </script>
</body>
</html>