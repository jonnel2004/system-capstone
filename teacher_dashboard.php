<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') { 
    header("Location: login.php"); 
    exit(); 
}

$teacher_id = intval($_SESSION['user_id']);
$teacher_name = $_SESSION['full_name'] ?? 'MR. BEN';


$avatar_pic = trim((string)($teacher['profile_pic'] ?? $_SESSION['profile_pic'] ?? ''));
$has_avatar_pic = ($avatar_pic !== '' && file_exists(__DIR__ . '/' . $avatar_pic));

function tdTableExists(mysqli $conn, string $table): bool {
    $safe = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return ($res && $res->num_rows > 0);
}

function tdGetColumns(mysqli $conn, string $table): array {
    $cols = [];
    if (!tdTableExists($conn, $table)) {
        return $cols;
    }
    $res = $conn->query("SHOW COLUMNS FROM `{$table}`");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $cols[] = $r['Field'];
        }
    }
    return $cols;
}

function tdFormatStatus(int $pct, string $lastAction = ''): array {
    $pct = max(0, min(100, $pct));
    $act = strtolower(trim($lastAction));

    if ($pct >= 100) {
        return [
            'pct'       => 100,
            'css_class' => 'badge-working',
            'label'     => 'Complete',
            'right_txt' => '100% Complete'
        ];
    }
    if ($pct === 0) {
        return [
            'pct'       => 0,
            'css_class' => 'badge-not-working',
            'label'     => 'NOT WORKING',
            'right_txt' => '0% done'
        ];
    }
    $extra = ($act === 'opened' || $act === 'scrolling' || $act === 'reading_only') ? ' • Opened File' : '';
    return [
        'pct'       => $pct,
        'css_class' => 'badge-working',
        'label'     => 'Working' . $extra,
        'right_txt' => $pct . '% done'
    ];
}

$assignments_query = $conn->query("SELECT * FROM teacher_assignments WHERE teacher_id = $teacher_id");
$assigned_blocks = [];
if ($assignments_query && $assignments_query->num_rows > 0) {
    while ($row = $assignments_query->fetch_assoc()) {
        $assigned_blocks[] = $row;
    }
}

$rt_students = [];
$has_sap = tdTableExists($conn, 'student_activity_progress');
$has_sa  = tdTableExists($conn, 'student_activities');
$has_act = tdTableExists($conn, 'activities');
$act_cols = $has_act ? tdGetColumns($conn, 'activities') : [];
$act_title_expr = ($has_act && in_array('title', $act_cols, true)) ? 'a.title' : "'Activity'";
$join_act_sql = $has_act ? "LEFT JOIN activities a ON a.id = sap.activity_id" : "";

if ($has_sap) {
    $q_sap = $conn->query("
        SELECT
            u.id,
            u.full_name,
            u.username,
            sap.progress_percent AS progress,
            sap.last_action,
            sap.updated_at,
            {$act_title_expr} AS activity_title
        FROM student_activity_progress sap
        INNER JOIN users u ON u.id = sap.student_id
        {$join_act_sql}
        WHERE u.role = 'student'
        ORDER BY sap.updated_at DESC, u.full_name ASC
        LIMIT 12
    ");
    if ($q_sap) {
        while ($r = $q_sap->fetch_assoc()) {
            $pct = intval($r['progress'] ?? 0);
            $st_info = tdFormatStatus($pct, (string)($r['last_action'] ?? ''));
            $rt_students[] = [
                'display_name'   => !empty($r['full_name']) ? $r['full_name'] : $r['username'],
                'activity_title' => (string)($r['activity_title'] ?? 'Activity'),
                'last_action'    => (string)($r['last_action'] ?? ''),
                'updated_at'     => (string)($r['updated_at'] ?? ''),
                'css_class'      => $st_info['css_class'],
                'label'          => $st_info['label'],
                'right_txt'      => $st_info['right_txt'],
                'pct'            => $st_info['pct']
            ];
        }
    }
} elseif ($has_sa) {
    $students_query = $conn->query("
        SELECT 
            u.id, 
            u.full_name, 
            u.username,
            COALESCE(sa.progress, 0) AS progress,
            '' AS last_action,
            sa.updated_at,
            'Activity' AS activity_title
        FROM users u
        LEFT JOIN student_activities sa ON u.id = sa.user_id
        WHERE u.role = 'student'
        ORDER BY sa.updated_at DESC, u.full_name ASC
        LIMIT 12
    ");
    if ($students_query) {
        while ($r = $students_query->fetch_assoc()) {
            $pct = intval($r['progress'] ?? 0);
            $st_info = tdFormatStatus($pct, '');
            $rt_students[] = [
                'display_name'   => !empty($r['full_name']) ? $r['full_name'] : $r['username'],
                'activity_title' => 'Activity',
                'last_action'    => '',
                'updated_at'     => (string)($r['updated_at'] ?? ''),
                'css_class'      => $st_info['css_class'],
                'label'          => $st_info['label'],
                'right_txt'      => $st_info['right_txt'],
                'pct'            => $st_info['pct']
            ];
        }
    }
} else {
    $q_u = $conn->query("SELECT id, full_name, username FROM users WHERE role = 'student' ORDER BY full_name ASC LIMIT 12");
    if ($q_u) {
        while ($r = $q_u->fetch_assoc()) {
            $st_info = tdFormatStatus(0, '');
            $rt_students[] = [
                'display_name'   => !empty($r['full_name']) ? $r['full_name'] : $r['username'],
                'activity_title' => 'Activity',
                'last_action'    => '',
                'updated_at'     => '',
                'css_class'      => $st_info['css_class'],
                'label'          => $st_info['label'],
                'right_txt'      => $st_info['right_txt'],
                'pct'            => 0
            ];
        }
    }
}


$recent_notifications = [];


if (tdTableExists($conn, 'teacher_assignments')) {
    $q_ta = $conn->query("SELECT * FROM teacher_assignments WHERE teacher_id = $teacher_id ORDER BY id DESC LIMIT 5");
    if ($q_ta) {
        while ($ta = $q_ta->fetch_assoc()) {
            $subj_n = trim((string)($ta['subject_name'] ?? 'Subject'));
            $c_code = trim((string)($ta['course_code'] ?? ''));
            $b_name = trim((string)($ta['block_name'] ?? ''));
            $recent_notifications[] = [
                'title'  => 'Admin Added Subject / Block',
                'detail' => htmlspecialchars($subj_n) . ' (' . htmlspecialchars(trim($c_code . ' ' . $b_name)) . ') assigned by Admin.',
                'time'   => (string)($ta['created_at'] ?? ''),
                'ts'     => !empty($ta['created_at']) && strtotime((string)$ta['created_at']) !== false ? strtotime((string)$ta['created_at']) : intval($ta['id'] ?? 0)
            ];
        }
    }
}


if (tdTableExists($conn, 'room_schedules')) {
    $q_rs = $conn->query("SELECT * FROM room_schedules ORDER BY id DESC LIMIT 4");
    if ($q_rs) {
        while ($rs = $q_rs->fetch_assoc()) {
            $sb = trim((string)($rs['subject_block'] ?? 'Session'));
            $rm = trim((string)($rs['room_name'] ?? ''));
            $recent_notifications[] = [
                'title'  => 'Admin Schedule Update',
                'detail' => htmlspecialchars($sb) . ($rm !== '' ? ' — Room: <strong>' . htmlspecialchars($rm) . '</strong>' : ''),
                'time'   => (string)($rs['created_at'] ?? ''),
                'ts'     => !empty($rs['created_at']) && strtotime((string)$rs['created_at']) !== false ? strtotime((string)$rs['created_at']) : intval($rs['id'] ?? 0)
            ];
        }
    }
}

foreach (['admin_notifications', 'notifications', 'announcements'] as $ntbl) {
    $ncols = tdGetColumns($conn, $ntbl);
    if (!empty($ncols)) {
        $tcol = in_array('title', $ncols, true) ? 'title' : (in_array('subject', $ncols, true) ? 'subject' : '');
        $mcol = in_array('message', $ncols, true) ? 'message' : (in_array('description', $ncols, true) ? 'description' : (in_array('body', $ncols, true) ? 'body' : ''));
        $ccol = in_array('created_at', $ncols, true) ? 'created_at' : '';
        $sel = ['id'];
        if ($tcol !== '') $sel[] = "`{$tcol}` AS n_t";
        if ($mcol !== '') $sel[] = "`{$mcol}` AS n_m";
        if ($ccol !== '') $sel[] = "`{$ccol}` AS n_c";
        $qn = $conn->query("SELECT " . implode(', ', $sel) . " FROM `{$ntbl}` ORDER BY id DESC LIMIT 5");
        if ($qn) {
            while ($nr = $qn->fetch_assoc()) {
                $recent_notifications[] = [
                    'title'  => trim((string)($nr['n_t'] ?? 'Admin Notice')) ?: 'Admin Notice',
                    'detail' => htmlspecialchars(trim((string)($nr['n_m'] ?? 'New update from Admin.'))),
                    'time'   => (string)($nr['n_c'] ?? ''),
                    'ts'     => !empty($nr['n_c']) && strtotime((string)$nr['n_c']) !== false ? strtotime((string)$nr['n_c']) : intval($nr['id'] ?? 0)
                ];
            }
        }
    }
}


foreach ($rt_students as $st_item) {
    $act_l = strtolower(trim($st_item['last_action']));
    if ($st_item['pct'] > 0 || $act_l === 'opened' || $act_l === 'scrolling' || $act_l === 'reading_only' || $act_l === 'mark_done') {
        $n_title = ($st_item['pct'] >= 100)
            ? 'Student Completed Activity'
            : (($act_l === 'opened' || $act_l === 'scrolling' || $act_l === 'reading_only') ? 'Student Opened File' : 'Student Activity Progress');
        $recent_notifications[] = [
            'title'  => $n_title,
            'detail' => '<strong>' . htmlspecialchars($st_item['display_name']) . '</strong> on <strong>' . htmlspecialchars($st_item['activity_title']) . '</strong> (' . htmlspecialchars($st_item['label']) . ' • ' . intval($st_item['pct']) . '%)',
            'time'   => $st_item['updated_at'],
            'ts'     => (!empty($st_item['updated_at']) && strtotime($st_item['updated_at']) !== false) ? strtotime($st_item['updated_at']) : time()
        ];
    }
}

usort($recent_notifications, function ($a, $b) {
    return ($b['ts'] ?? 0) <=> ($a['ts'] ?? 0);
});


$schedules_query = $conn->query("SELECT * FROM room_schedules ORDER BY id DESC LIMIT 3");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCDI Teacher Portal - Teacher Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --primary: #1e6091;
            --primary-dark: #184e77;
            --accent: #52b788;
            --bg-light: #f8fafc;
            --text-main: #1e293b;
            --border-color: #e2e8f0;
        }
        body {
            background-color: var(--bg-light);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-main);
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }
        @media (max-width: 1024px) {
            .dashboard-grid { grid-template-columns: 1fr; }
        }
        .card-box {
            background: #ffffff;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            margin-bottom: 24px;
            border: 1px solid var(--border-color);
        }
        .card-title {
            font-size: 20px;
            font-weight: 800;
            color: var(--primary-dark);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .t-subject-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #0453a1;
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 10px;
            font-weight: 600;
            transition: background 0.2s;
        }
        .t-subject-row:hover {
            background: #e2e8f0;
        }
        .badge-working {
            background: #d1fae5;
            color: #065f46;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
        }
        .badge-not-working {
            background: #fee2e2;
            color: #991b1b;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
        }
        .schedule-mini-card {
            background: linear-gradient(135deg, #1e6091, #184e77);
            color: white;
            padding: 16px;
            border-radius: 14px;
            text-align: center;
            margin-bottom: 12px;
            box-shadow: 0 4px 10px rgba(30, 96, 145, 0.2);
        }
        .notifications-scroll-list {
            max-height: 240px;
            overflow-y: auto;
            padding-right: 6px;
        }
        .notifications-scroll-list::-webkit-scrollbar {
            width: 6px;
        }
        .notifications-scroll-list::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 999px;
        }
        .notifications-scroll-list::-webkit-scrollbar-thumb {
            background: #94a3b8;
            border-radius: 999px;
        }
        .notification-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }
        .notification-item:last-child {
            border-bottom: none;
        }
        .notif-dot {
            width: 8px;
            height: 8px;
            background: #3b82f6;
            border-radius: 50%;
            margin-top: 6px;
            flex-shrink: 0;
        }
    </style>
</head>
<body>

    <header class="topbar" style="background-color: #0b2545;" >
        <div class="brand">
            <div class="brand-logo"><img src="logo.jpg" alt="logo" style="width:100%; height:100%; object-fit:contain;"></div>
            <span>CCDI Teacher Portal</span>
        </div>
        <input type="text" class="search-bar" placeholder="Search courses, students...">
        <div class="user-menu">
             
            <div class="user-avatar" style="overflow:hidden; padding:0; display:flex; align-items:center; justify-content:center;">
                <?php if ($has_avatar_pic): ?>
                    <img src="<?php echo htmlspecialchars($avatar_pic); ?>" alt="Profile Picture" style="width:100%; height:100%; object-fit:cover; display:block;">
                <?php else: ?>
                    TC
                <?php endif; ?>
            </div>
            <span><?php echo htmlspecialchars($teacher_name); ?></span>
            <span style="cursor: pointer;" title="Notifications">&#128276;</span>
            <a href="logout.php" class="logout-link">logout</a>
        </div>
    </header>

    <nav class="sidebar">
        <a href="teacher_dashboard.php" class="active">Dashboard</a>
        <a href="teacher_subjects.php">Subjects</a>
        <a href="teacher_activities.php">Activities</a>
        <a href="teacher_live.php">Live Sessions</a>
        <a href="teacher_settings.php">Settings</a>
    </nav>

    <main class="main-wrapper">
      
        <div class="page-header" style="display:flex; align-items:center; gap:16px; margin-bottom: 24px;">
            <div style="width:65px; height:65px; background:linear-gradient(135deg, #1e6091, #0f172a); border-radius:50%; display:flex; align-items:center; justify-content:center; color:white; font-size:30px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">&#128100;</div>
            <div>
                <h1 style="font-size: 28px; font-weight: 800; text-transform:uppercase; color: #0f172a; margin: 0;"><?php echo htmlspecialchars($teacher_name); ?></h1>
                <p style="color: #64748b; font-size: 14px; margin: 4px 0 0 0;">Welcome back to your faculty portal dashboard.</p>
            </div>
        </div>

        <div class="dashboard-grid">
           
            <div>
             
                <div class="card-box">
                    <div class="card-title">
                        <span>My Assigned Subjects & Blocks</span>
                        <a href="teacher_subjects.php" style="font-size: 13px; color: #1e6091; text-decoration: none;">View All &rarr;</a>
                    </div>
                    <?php if (!empty($assigned_blocks)): ?>
                        <?php foreach ($assigned_blocks as $ab): ?>
                            <div class="t-subject-row">
                                <span>📚 <?php echo htmlspecialchars($ab['subject_name'] ?? 'Software Engineering'); ?></span>
                                <span style="background: #112e44; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px;"><?php echo htmlspecialchars($ab['course_code'] . ' ' . $ab['block_name']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="t-subject-row"><span>Software Engineering</span><span>BSIT 3B</span></div>
                        <div class="t-subject-row"><span>System Integration and Architecture</span><span>BSIT 3B</span></div>
                    <?php endif; ?>
                </div>

                <!-- Real-time Student Activity Box -->
                <div class="card-box">
                    <div class="card-title">
                        <span>Real-Time Student Activity</span>
                        <span style="font-size: 11px; background: #d1fae5; color: #065f46; padding: 4px 10px; border-radius: 12px;">Live Monitoring</span>
                    </div>
                    <div id="live-activity-dashboard">
                        <?php if (!empty($rt_students)): ?>
                            <?php foreach ($rt_students as $st): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; background: #f8fafc; padding: 12px 18px; border-radius: 12px; margin-bottom: 10px; border: 1px solid #e2e8f0;">
                                    <div>
                                        <span style="font-weight: 600; font-size: 14px;">👤 <?php echo htmlspecialchars($st['display_name']); ?></span>
                                        <div style="font-size: 12px; color: #64748b; margin-top: 2px;"><?php echo htmlspecialchars($st['activity_title']); ?></div>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 15px;">
                                        <span class="<?php echo $st['css_class']; ?>"><?php echo htmlspecialchars($st['label']); ?></span>
                                        <span style="font-size: 13px; font-weight: 700; color: #64748b;"><?php echo htmlspecialchars($st['right_txt']); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: #64748b; font-size: 14px; text-align: center; padding: 15px;">There are currently no active students.n.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            
            <div>
                
                <div class="card-box">
                    <div class="card-title" style="font-size: 18px;">
                        <span>🔔 Recent Notifications</span>
                    </div>
                    <div class="notifications-scroll-list">
                        <?php if (!empty($recent_notifications)): ?>
                            <?php foreach ($recent_notifications as $nf): ?>
                                <div class="notification-item">
                                    <div class="notif-dot"></div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($nf['title']); ?></strong><br>
                                        <span style="color: #64748b;"><?php echo $nf['detail']; ?></span>
                                        <?php if (!empty($nf['time'])): ?>
                                            <div style="font-size: 11px; color: #94a3b8; margin-top: 2px;"><?php echo htmlspecialchars($nf['time']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="notification-item">
                                <div class="notif-dot"></div>
                                <div>
                                    <strong>System Update</strong><br>
                                    <span style="color: #64748b;">New activity submissions are now synced in real time.</span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                
                <div class="card-box">
                    <div class="card-title" style="font-size: 18px;">
                        <span>📅 Upcoming Sessions</span>
                        <a href="teacher_live.php" style="font-size: 13px; color: #1e6091; text-decoration: none;">View All</a>
                    </div>
                    <?php if ($schedules_query && $schedules_query->num_rows > 0): ?>
                        <?php while ($sch = $schedules_query->fetch_assoc()): ?>
                            <div class="schedule-mini-card">
                                <strong style="font-size: 15px;"><?php echo htmlspecialchars($sch['subject_block'] ?? 'Block Session'); ?></strong><br>
                                <span style="font-size: 13px; opacity: 0.9;"><?php echo htmlspecialchars($sch['day_of_week']); ?> | <?php echo htmlspecialchars($sch['time_slot']); ?></span><br>
                                <span style="font-size: 13px; font-weight: 700; background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 6px; display: inline-block; margin-top: 4px;"><?php echo htmlspecialchars($sch['room_name']); ?></span>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="schedule-mini-card">
                            <strong>Nov. 14, 2026</strong><br>
                            <span style="font-size: 13px;">Monday | 7:00 am - 10:30 pm</span><br>
                            <strong style="font-size: 13px;">ROOM 203</strong>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

</body>
</html>