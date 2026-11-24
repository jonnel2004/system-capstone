<?php
if (session_id() === '') {
    @session_start();
}
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}


$avatar_pic = trim((string)($teacher['profile_pic'] ?? $_SESSION['profile_pic'] ?? ''));
$has_avatar_pic = ($avatar_pic !== '' && file_exists(__DIR__ . '/' . $avatar_pic));

$student_id = intval($_SESSION['user_id']);
$student_name = trim((string)($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Student'));
$student_program_raw = trim((string)($_SESSION['program'] ?? $_SESSION['course'] ?? $_SESSION['course_code'] ?? $_SESSION['department'] ?? ''));
$student_block_raw = trim((string)($_SESSION['block'] ?? $_SESSION['block_name'] ?? $_SESSION['section'] ?? ''));

function tableExists(mysqli $conn, string $table): bool {
    $safe = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return ($res && $res->num_rows > 0);
}

function pickField(array $row, array $keys, string $default = ''): string {
    foreach ($keys as $k) {
        if (isset($row[$k]) && trim((string)$row[$k]) !== '') {
            return trim((string)$row[$k]);
        }
    }
    return $default;
}

function extractProgram(string $rawProg, string $combinedText = ''): string {
    $src = strtoupper(trim($rawProg . ' ' . $combinedText));
    foreach (['BSIT', 'BSCS', 'BSIS', 'BSOAD'] as $p) {
        if (strpos($src, $p) !== false) {
            return $p;
        }
    }
    return strtoupper(trim($rawProg));
}

function normalizeBlock(string $blk): string {
    $s = trim($blk);
    $s = preg_replace('/^(BSIT|BSCS|BSIS|BSOAD)\s*[-–—]?\s*/i', '', $s);
    $s = preg_replace('/^block\s*/i', '', $s);
    $s = preg_replace('/[\s\-–—]+/', '', $s);
    return strtoupper(trim((string)$s));
}

function formatCourseBlock(string $course, string $block): string {
    $course = extractProgram($course) ?: strtoupper(trim($course));
    $clean_block = preg_replace('/^block\s*/i', '', trim($block));
    $clean_block = preg_replace('/^(BSIT|BSCS|BSIS|BSOAD)\s*[-–—]?\s*/i', '', trim((string)$clean_block));

    if ($course !== '' && $clean_block !== '') {
        return $course . ' - Block ' . $clean_block;
    }
    if ($course !== '') {
        return $course;
    }
    if ($clean_block !== '') {
        return 'Block ' . $clean_block;
    }
    return '';
}

function normalizeMode(string $rawMode, string $room): string {
    $m = strtolower(trim($rawMode));
    $r = strtolower(trim($room));
    if (
        strpos($m, 'online') !== false ||
        strpos($m, 'virtual') !== false ||
        strpos($m, 'meet') !== false ||
        strpos($m, 'zoom') !== false ||
        strpos($r, 'online') !== false ||
        strpos($r, 'zoom') !== false ||
        strpos($r, 'meet') !== false
    ) {
        return 'ONLINE';
    }
    return 'FACE-TO-FACE';
}


$conn->query("CREATE TABLE IF NOT EXISTS admin_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    target_program VARCHAR(30) DEFAULT '',
    target_block VARCHAR(30) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");


if ($student_id > 0 && tableExists($conn, 'users')) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($u = $res->fetch_assoc()) {
            $db_name = pickField($u, ['full_name', 'name', 'student_name', 'username'], '');
            if ($db_name !== '') {
                $student_name = $db_name;
            }
            $db_prog = pickField($u, ['program', 'course_code', 'course', 'department'], '');
            $db_blk  = pickField($u, ['block_name', 'block', 'section'], '');
            if ($db_prog === '' && $db_blk !== '') {
                $db_prog = extractProgram('', $db_blk);
            }
            if ($db_prog !== '') {
                $student_program_raw = $db_prog;
            }
            if ($db_blk !== '') {
                $student_block_raw = $db_blk;
            }
        }
        $stmt->close();
    }
}

$norm_prog = extractProgram($student_program_raw, $student_block_raw);
$norm_blk  = normalizeBlock($student_block_raw);
$has_filter = ($norm_prog !== '' || $norm_blk !== '');


$teachers_map = [];
if (tableExists($conn, 'users')) {
    $tq = $conn->query("SELECT * FROM users WHERE LOWER(role) = 'teacher'");
    if ($tq) {
        while ($tr = $tq->fetch_assoc()) {
            $tid = (int)$tr['id'];
            $teachers_map[$tid] = pickField($tr, ['full_name', 'name', 'teacher_name', 'username'], 'Instructor');
        }
    }
}


$dashboard_subjects = [];
if (tableExists($conn, 'teacher_assignments')) {
    $q = $conn->query("SELECT * FROM teacher_assignments ORDER BY id ASC");
    if ($q) {
        while ($r = $q->fetch_assoc()) {
            $subj = pickField($r, ['subject_name', 'subject', 'subject_code', 'title', 'name'], 'Subject');
            $rc   = pickField($r, ['course_code', 'program', 'course', 'course_name', 'department'], '');
            $rb   = pickField($r, ['block_name', 'block', 'section'], '');
            $ip   = extractProgram($rc, $rb);
            $ib   = normalizeBlock($rb);
            $tid  = (int)($r['teacher_id'] ?? 0);
            $tname = $teachers_map[$tid] ?? 'Assigned Instructor';

            $match = true;
            if ($has_filter) {
                $prog_ok = ($norm_prog === '' || $ip === $norm_prog);
                $blk_ok  = ($norm_blk === '' || $ib === $norm_blk);
                $match = ($prog_ok && $blk_ok);
            }

            if ($match) {
                $dashboard_subjects[] = [
                    'subject'    => $subj,
                    'prog_block' => formatCourseBlock($ip ?: $rc, $rb),
                    'teacher'    => $tname
                ];
            }
        }
    }
}


function fetchStudentActivities(mysqli $conn, string $norm_prog, string $norm_blk, bool $has_filter): array {
    $items = [];
    $act_tables = ['activities', 'assignments', 'student_activities', 'teacher_activities'];
    foreach ($act_tables as $tbl) {
        if (!tableExists($conn, $tbl)) {
            continue;
        }
        $q = $conn->query("SELECT * FROM `{$tbl}` ORDER BY id DESC LIMIT 12");
        if ($q) {
            while ($r = $q->fetch_assoc()) {
                $title = pickField($r, ['title', 'activity_title', 'assignment_title', 'name', 'subject', 'subject_name'], 'New Activity');
                $subj  = pickField($r, ['subject_name', 'subject', 'course_name', 'title'], '');
                $desc  = pickField($r, ['description', 'instructions', 'details', 'content'], '');
                $blk   = pickField($r, ['block_name', 'block', 'section'], '');
                $prog  = pickField($r, ['program_code', 'program', 'course_code', 'course'], '');
                $date  = pickField($r, ['created_at', 'due_date', 'deadline', 'date_posted'], '');

                $ip = extractProgram($prog, $blk . ' ' . $subj);
                $ib = normalizeBlock($blk);

                if ($has_filter && ($ip !== '' || $ib !== '')) {
                    $prog_ok = ($norm_prog === '' || $ip === '' || $ip === $norm_prog);
                    $blk_ok  = ($norm_blk === '' || $ib === '' || $ib === $norm_blk);
                    if (!$prog_ok || !$blk_ok) {
                        continue;
                    }
                }

                $items[] = [
                    'id'         => (int)($r['id'] ?? 0),
                    'title'      => $title,
                    'subject'    => $subj,
                    'desc'       => $desc,
                    'prog_block' => formatCourseBlock($ip ?: $prog, $blk),
                    'time'       => $date
                ];
            }
        }
        if (!empty($items)) {
            break;
        }
    }
    return $items;
}


$dashboard_sessions = [];
$sess_tables = ['room_schedules', 'live_sessions', 'teacher_live_sessions'];
foreach ($sess_tables as $tbl) {
    if (!tableExists($conn, $tbl)) {
        continue;
    }
    $q = $conn->query("SELECT * FROM `{$tbl}` ORDER BY id DESC LIMIT 12");
    if ($q) {
        while ($r = $q->fetch_assoc()) {
            $room = pickField($r, ['room_name', 'room', 'room_number', 'location'], '');
            $day  = pickField($r, ['day_of_week', 'day_name', 'day'], '');
            $time = pickField($r, ['time_slot', 'timeslot', 'time', 'schedule_time'], '');
            $sb   = pickField($r, ['subject_block'], '');
            $sub  = pickField($r, ['subject_name', 'subject', 'title'], '');
            $blk  = pickField($r, ['block_name', 'block', 'section'], '');
            $prog = pickField($r, ['program_code', 'program', 'course_code', 'course'], '');
            $mode = normalizeMode(pickField($r, ['session_mode', 'mode', 'class_mode'], ''), $room);

            if ($sub === '' && $sb !== '') {
                $sub = $sb;
            }
            if ($sub === '') {
                $sub = 'Session';
            }

            $ip = extractProgram($prog, $blk . ' ' . $sb);
            $ib = normalizeBlock($blk ?: $sb);

            if ($has_filter && ($ip !== '' || $ib !== '')) {
                $prog_ok = ($norm_prog === '' || $ip === '' || $ip === $norm_prog);
                $blk_ok  = ($norm_blk === '' || $ib === '' || $ib === $norm_blk);
                if (!$prog_ok || !$blk_ok) {
                    continue;
                }
            }

            $label_title = $sub;
            $clean_b = preg_replace('/^block\s+/i', '', trim($blk));
            if ($clean_b !== '' && stripos($label_title, $clean_b) === false) {
                $label_title .= ' - Block ' . $clean_b;
            }

            $meta_parts = [];
            if ($day !== '') $meta_parts[] = $day;
            if ($time !== '') $meta_parts[] = $time;
            if ($room !== '') $meta_parts[] = 'Room ' . $room;

            $dashboard_sessions[] = [
                'title' => $label_title,
                'meta'  => implode(' • ', $meta_parts),
                'mode'  => $mode
            ];
        }
    }
    if (!empty($dashboard_sessions)) {
        break;
    }
}


function fetchRealTimeNotifications(mysqli $conn, string $norm_prog, string $norm_blk, bool $has_filter): array {
    $notifs = [];

    if (tableExists($conn, 'admin_notifications')) {
        $q = $conn->query("SELECT * FROM admin_notifications ORDER BY id DESC LIMIT 15");
        if ($q) {
            while ($r = $q->fetch_assoc()) {
                $tp = extractProgram((string)($r['target_program'] ?? ''));
                $tb = normalizeBlock((string)($r['target_block'] ?? ''));
                if ($has_filter) {
                    if ($tp !== '' && $norm_prog !== '' && $tp !== $norm_prog) continue;
                    if ($tb !== '' && $norm_blk !== '' && $tb !== $norm_blk) continue;
                }
                $notifs[] = [
                    'uid'     => 'admin_' . (int)$r['id'],
                    'source'  => 'Admin',
                    'badge'   => 'ADMIN',
                    'title'   => (string)($r['title'] ?? 'Admin Notice'),
                    'message' => (string)($r['message'] ?? ''),
                    'time'    => (string)($r['created_at'] ?? '')
                ];
            }
        }
    }

    foreach (['announcements', 'admin_announcements', 'notifications'] as $atbl) {
        if (!tableExists($conn, $atbl)) {
            continue;
        }
        $q = $conn->query("SELECT * FROM `{$atbl}` ORDER BY id DESC LIMIT 15");
        if ($q) {
            while ($r = $q->fetch_assoc()) {
                $raw_role = strtolower(trim(pickField($r, ['sender_role', 'role', 'source', 'created_by_role'], '')));
                if ($raw_role === '') {
                    $raw_role = ($atbl === 'admin_announcements') ? 'admin' : 'teacher';
                }
                $is_teacher_sender = (strpos($raw_role, 'teacher') !== false || strpos($raw_role, 'instructor') !== false || strpos($raw_role, 'faculty') !== false);
                $source_label = $is_teacher_sender ? 'Teacher' : 'Admin';
                $ntype = strtolower(trim(pickField($r, ['notif_type', 'type'], 'announcement')));
                $badge_label = $is_teacher_sender
                    ? ($ntype === 'activity' ? 'TEACHER • ACTIVITY' : 'TEACHER')
                    : 'ADMIN';

                $title = pickField($r, ['title', 'subject', 'subject_code', 'heading', 'name'], $source_label . ' Announcement');
                $msg   = pickField($r, ['message', 'body', 'content', 'description'], '');
                $tp    = extractProgram(pickField($r, ['program_code', 'program', 'target_program'], ''));
                $tb    = normalizeBlock(pickField($r, ['block_name', 'block', 'target_block'], ''));
                if ($has_filter) {
                    if ($tp !== '' && $norm_prog !== '' && $tp !== $norm_prog) continue;
                    if ($tb !== '' && $norm_blk !== '' && $tb !== $norm_blk) continue;
                }
                $notifs[] = [
                    'uid'     => $atbl . '_' . (int)($r['id'] ?? 0),
                    'source'  => $source_label,
                    'badge'   => $badge_label,
                    'title'   => $title,
                    'message' => $msg,
                    'time'    => pickField($r, ['created_at', 'date_posted', 'date'], '')
                ];
            }
        }
        break;
    }

    $acts = fetchStudentActivities($conn, $norm_prog, $norm_blk, $has_filter);
    foreach ($acts as $a) {
        $notifs[] = [
            'uid'     => 'act_' . $a['id'] . '_' . md5($a['title']),
            'source'  => 'Teacher',
            'badge'   => 'NEW ACTIVITY',
            'title'   => $a['title'],
            'message' => ($a['subject'] !== '' ? $a['subject'] : 'New activity from teacher') . ($a['prog_block'] !== '' ? ' (' . $a['prog_block'] . ')' : ''),
            'time'    => $a['time']
        ];
    }

    return $notifs;
}

if (isset($_GET['rt_feed']) && $_GET['rt_feed'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    $rt_notifs = fetchRealTimeNotifications($conn, $norm_prog, $norm_blk, $has_filter);
    $rt_acts   = fetchStudentActivities($conn, $norm_prog, $norm_blk, $has_filter);
    echo json_encode([
        'notifications' => $rt_notifs,
        'activities'    => $rt_acts,
        'server_time'   => date('h:i:s A')
    ]);
    exit();
}

$dashboard_activities = fetchStudentActivities($conn, $norm_prog, $norm_blk, $has_filter);
$dashboard_notifs     = fetchRealTimeNotifications($conn, $norm_prog, $norm_blk, $has_filter);
$program_display = $student_program_raw !== '' ? $student_program_raw : ($norm_prog ?: 'Student');
$section_badge   = formatCourseBlock($norm_prog ?: $student_program_raw, $student_block_raw);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCDI Student Portal - Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .dash-top-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 14px;
            margin-bottom: 20px;
        }
        .dash-welcome h1 {
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
        }
        .dash-welcome p {
            font-size: 13px;
            color: #64748b;
            margin: 4px 0 0 0;
        }
        .rt-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #6ee7b7;
            padding: 7px 14px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
        }
        .rt-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
            animation: pulseDot 1.6s infinite;
        }
        @keyframes pulseDot {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(1.25); }
            100% { opacity: 1; transform: scale(1); }
        }
        .dash-split-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.85fr) minmax(300px, 1fr);
            gap: 20px;
            align-items: start;
        }
        @media (max-width: 980px) {
            .dash-split-layout {
                grid-template-columns: 1fr;
            }
        }
        .dash-main-col {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        .dash-side-col {
            position: sticky;
            top: 20px;
        }
        .dash-card-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 18px 20px;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
        }
        .dash-box-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        .dash-box-title {
            font-size: 17px;
            font-weight: 800;
            color: #0b2545;
            margin: 0;
        }
        .dash-link {
            font-size: 12px;
            font-weight: 700;
            color: #1e6091;
            text-decoration: none;
        }
        .dash-link:hover {
            text-decoration: underline;
        }
        .mini-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #1e6091;
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 10px;
        }
        .mini-item:last-child {
            margin-bottom: 0;
        }
        .mini-item-title {
            font-size: 14px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 3px 0;
        }
        .mini-item-sub {
            font-size: 12px;
            color: #475569;
            font-weight: 600;
        }
        .notif-scroll-area {
            max-height: 460px;
            overflow-y: auto;
            padding-right: 6px;
            scrollbar-width: thin;
            scrollbar-color: #94a3b8 #f1f5f9;
        }
        .notif-scroll-area::-webkit-scrollbar {
            width: 6px;
        }
        .notif-scroll-area::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 999px;
        }
        .notif-scroll-area::-webkit-scrollbar-thumb {
            background: #94a3b8;
            border-radius: 999px;
        }
        .notif-scroll-area::-webkit-scrollbar-thumb:hover {
            background: #64748b;
        }
        .notif-item {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 10px;
        }
        .notif-item:last-child {
            margin-bottom: 0;
        }
        .notif-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
        }
        .notif-badge {
            font-size: 10px;
            font-weight: 800;
            padding: 3px 8px;
            border-radius: 999px;
        }
        .notif-badge.admin {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fcd34d;
        }
        .notif-badge.teacher {
            background: #e0f2fe;
            color: #0369a1;
            border: 1px solid #7dd3fc;
        }
        .session-row {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }
        .session-row:last-child {
            margin-bottom: 0;
        }
        .mode-pill {
            font-size: 10px;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 999px;
            white-space: nowrap;
        }
        .mode-pill.f2f {
            background: #1d4ed8;
            color: #ffffff;
        }
        .mode-pill.online {
            background: #0284c7;
            color: #ffffff;
        }
        .rt-toast {
            position: fixed;
            right: 20px;
            bottom: 20px;
            background: #0b2545;
            color: #ffffff;
            border-left: 5px solid #38bdf8;
            padding: 14px 18px;
            border-radius: 12px;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.25);
            z-index: 2000;
            max-width: 340px;
            display: none;
        }
        .rt-toast.show {
            display: block;
        }
    </style>
</head>
<body>

    <header class="topbar" style="background-color: #0b2545;">
        <div class="brand">
            <div class="brand-logo"><img src="logo.jpg" alt="logo" style="width:100%; height:100%; object-fit:contain;"></div>
            <span>CCDI Student Portal</span>
        </div>
        <input type="text" id="dashSearchInput" class="search-bar" placeholder="Search subject, activity, or session">
        <div class="user-menu">
            <span id="notifBellBtn" title="Real-Time Notifications" style="cursor:pointer; font-weight:800;">
                &#128276; <span id="notifCountBadge" style="background:#ef4444; color:#fff; border-radius:999px; padding:1px 7px; font-size:11px;"><?php echo count($dashboard_notifs); ?></span>
            </span>
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
        <a href="dashboard.php" class="active">Dashboard</a>
        <a href="subjects.php">My Subjects</a>
        <a href="activity_feed.php">Activities</a>
        <a href="live_sessions.php">Live Sessions</a>
        <a href="settings.php">Settings</a>
    </nav>

    <main class="main-wrapper">
        <div class="dash-top-row">
            <div class="dash-welcome">
                <h1>Welcome, <?php echo htmlspecialchars($student_name); ?>!</h1>
                <p>
                    <?php echo htmlspecialchars($program_display); ?>
                    <?php if ($section_badge !== ''): ?>
                        &bull; <strong><?php echo htmlspecialchars($section_badge); ?></strong>
                    <?php endif; ?>
                </p>
            </div>
            <div class="rt-status-pill">
                <span class="rt-dot"></span>
                <span>Real-Time Updates Active (<span id="rtLastSync"><?php echo date('h:i:s A'); ?></span>)</span>
            </div>
        </div>

        <div class="dash-split-layout">
            <div class="dash-main-col">
                <div class="dash-card-box">
                    <div class="dash-box-header">
                        <h2 class="dash-box-title">My Subjects</h2>
                        <a href="student_subjects.php" class="dash-link">View All &rarr;</a>
                    </div>
                    <div id="dashSubjectsList">
                        <?php if (!empty($dashboard_subjects)): ?>
                            <?php foreach ($dashboard_subjects as $sbj): ?>
                                <div class="mini-item js-dash-searchable">
                                    <div class="mini-item-title"><?php echo htmlspecialchars($sbj['subject']); ?></div>
                                    <div class="mini-item-sub">
                                        <?php if ($sbj['prog_block'] !== ''): ?>
                                            <?php echo htmlspecialchars($sbj['prog_block']); ?> &bull;
                                        <?php endif; ?>
                                        Instructor: <?php echo htmlspecialchars($sbj['teacher']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="font-size:13px; color:#64748b; padding:8px 0;">No subjects have been assigned to your block yet..</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="dash-card-box">
                    <div class="dash-box-header">
                        <h2 class="dash-box-title">Activities</h2>
                        <a href="student_activities.php" class="dash-link">Activity Feed &rarr;</a>
                    </div>
                    <div id="dashActivitiesList">
                        <?php if (!empty($dashboard_activities)): ?>
                            <?php foreach ($dashboard_activities as $act): ?>
                                <div class="mini-item js-dash-searchable">
                                    <div class="mini-item-title"><?php echo htmlspecialchars($act['title']); ?></div>
                                    <div class="mini-item-sub">
                                        <?php if ($act['subject'] !== ''): ?>
                                            <?php echo htmlspecialchars($act['subject']); ?>
                                        <?php endif; ?>
                                        <?php if ($act['prog_block'] !== ''): ?>
                                            &bull; <?php echo htmlspecialchars($act['prog_block']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="font-size:13px; color:#64748b; padding:8px 0;">The teacher hasn't submitted any activity yet..</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="dash-card-box">
                    <div class="dash-box-header">
                        <h2 class="dash-box-title">Live Sessions</h2>
                        <a href="student_live_sessions.php" class="dash-link">All Live Sessions &rarr;</a>
                    </div>
                    <div id="dashSessionsList">
                        <?php if (!empty($dashboard_sessions)): ?>
                            <?php foreach ($dashboard_sessions as $sess): ?>
                                <?php $is_onl = ($sess['mode'] === 'ONLINE'); ?>
                                <div class="session-row js-dash-searchable">
                                    <div>
                                        <div class="mini-item-title"><?php echo htmlspecialchars($sess['title']); ?></div>
                                        <?php if ($sess['meta'] !== ''): ?>
                                            <div class="mini-item-sub"><?php echo htmlspecialchars($sess['meta']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <span class="mode-pill <?php echo $is_onl ? 'online' : 'f2f'; ?>">
                                        <?php echo htmlspecialchars($sess['mode']); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="font-size:13px; color:#64748b; padding:8px 0;">No live sessions scheduled yet..</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <aside class="dash-side-col">
                <div class="dash-card-box" id="rtNotificationsCard">
                    <div class="dash-box-header">
                        <h2 class="dash-box-title">&#128276; Notifications</h2>
                        <span style="font-size:11px; color:#64748b; font-weight:700;">Real-time</span>
                    </div>
                    <div id="rtNotificationsList" class="notif-scroll-area">
                        <?php if (!empty($dashboard_notifs)): ?>
                            <?php foreach ($dashboard_notifs as $nf): ?>
                                <?php $is_admin_nf = ($nf['source'] === 'Admin'); ?>
                                <div class="notif-item js-dash-searchable">
                                    <div class="notif-top">
                                        <span class="notif-badge <?php echo $is_admin_nf ? 'admin' : 'teacher'; ?>">
                                            <?php echo htmlspecialchars($nf['badge']); ?>
                                        </span>
                                        <span style="font-size:11px; color:#64748b;"><?php echo htmlspecialchars($nf['time']); ?></span>
                                    </div>
                                    <div class="mini-item-title"><?php echo htmlspecialchars($nf['title']); ?></div>
                                    <?php if ($nf['message'] !== ''): ?>
                                        <div class="mini-item-sub"><?php echo htmlspecialchars($nf['message']); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="font-size:13px; color:#64748b; padding:8px 0;">No new notifications from Teacher or Admin.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </aside>
        </div>
    </main>

    <div class="rt-toast" id="rtToastBox">
        <div style="font-size:11px; font-weight:800; color:#38bdf8; margin-bottom:2px;" id="rtToastSource">NEW UPDATE</div>
        <div style="font-size:13px; font-weight:800;" id="rtToastTitle"></div>
        <div style="font-size:12px; color:#cbd5e1; margin-top:2px;" id="rtToastMsg"></div>
    </div>

    <script>
        (function() {
            var searchInput = document.getElementById('dashSearchInput');
            var notifListEl = document.getElementById('rtNotificationsList');
            var actsListEl  = document.getElementById('dashActivitiesList');
            var badgeEl     = document.getElementById('notifCountBadge');
            var syncTimeEl  = document.getElementById('rtLastSync');
            var toastBox    = document.getElementById('rtToastBox');
            var toastSrc    = document.getElementById('rtToastSource');
            var toastTitle  = document.getElementById('rtToastTitle');
            var toastMsg    = document.getElementById('rtToastMsg');

            var knownUids = <?php echo json_encode(array_column($dashboard_notifs, 'uid')); ?> || [];
            var firstLoad = true;
            var toastTimer = null;

            function escapeHtml(str) {
                return String(str || '').replace(/[&<>"']/g, function(m) {
                    return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m];
                });
            }

            function applySearchFilter() {
                var q = searchInput ? searchInput.value.toLowerCase().trim() : '';
                document.querySelectorAll('.js-dash-searchable').forEach(function(el) {
                    var txt = el.textContent.toLowerCase();
                    if (el.classList.contains('session-row')) {
                        el.style.display = (q === '' || txt.indexOf(q) !== -1) ? 'flex' : 'none';
                    } else {
                        el.style.display = (q === '' || txt.indexOf(q) !== -1) ? 'block' : 'none';
                    }
                });
            }

            function showToast(item) {
                if (!toastBox || !item) return;
                if (toastSrc) toastSrc.textContent = (item.source || 'UPDATE').toUpperCase();
                if (toastTitle) toastTitle.textContent = item.title || '';
                if (toastMsg) toastMsg.textContent = item.message || '';
                toastBox.classList.add('show');
                clearTimeout(toastTimer);
                toastTimer = setTimeout(function() {
                    toastBox.classList.remove('show');
                }, 4500);
            }

            function renderNotifications(notifs) {
                if (!notifListEl) return;
                if (!Array.isArray(notifs) || notifs.length === 0) {
                    notifListEl.innerHTML = '<div style="font-size:13px; color:#64748b; padding:8px 0;">No new notifications from Teacher or Admin.</div>';
                    if (badgeEl) badgeEl.textContent = '0';
                    return;
                }

                var html = '';
                var currentUids = [];
                var newestItem = null;

                notifs.forEach(function(nf) {
                    var uid = String(nf.uid || '');
                    currentUids.push(uid);
                    if (!firstLoad && uid !== '' && knownUids.indexOf(uid) === -1 && !newestItem) {
                        newestItem = nf;
                    }
                    var isAdmin = (nf.source === 'Admin');
                    var badgeCls = isAdmin ? 'admin' : 'teacher';
                    html += '<div class="notif-item js-dash-searchable">';
                    html += '<div class="notif-top">';
                    html += '<span class="notif-badge ' + badgeCls + '">' + escapeHtml(nf.badge || (isAdmin ? 'ADMIN' : 'TEACHER')) + '</span>';
                    html += '<span style="font-size:11px; color:#64748b;">' + escapeHtml(nf.time || '') + '</span>';
                    html += '</div>';
                    html += '<div class="mini-item-title">' + escapeHtml(nf.title || '') + '</div>';
                    if (nf.message) {
                        html += '<div class="mini-item-sub">' + escapeHtml(nf.message) + '</div>';
                    }
                    html += '</div>';
                });

                notifListEl.innerHTML = html;
                if (badgeEl) badgeEl.textContent = String(notifs.length);
                knownUids = currentUids;
                firstLoad = false;

                if (newestItem) {
                    showToast(newestItem);
                }
                applySearchFilter();
            }

            if (searchInput) {
                searchInput.addEventListener('input', applySearchFilter);
            }

            setInterval(function() {
                fetch('?rt_feed=1')
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        if (!data) return;
                        if (data.server_time && syncTimeEl) {
                            syncTimeEl.textContent = data.server_time;
                        }
                        if (Array.isArray(data.notifications)) {
                            renderNotifications(data.notifications);
                        }
                    })
                    .catch(function() {});
            }, 10000);
        })();
    </script>
</body>
</html>