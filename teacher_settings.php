<?php
if (session_id() === '') {
    @session_start();
}
include 'db.php';


if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);

function tsHasTable(?mysqli $conn, string $table): bool {
    if (!$conn) return false;
    $safe = $conn->real_escape_string($table);
    $res = @$conn->query("SHOW TABLES LIKE '{$safe}'");
    return ($res && $res->num_rows > 0);
}

function tsGetCols(?mysqli $conn, string $table): array {
    $cols = [];
    if (!tsHasTable($conn, $table)) {
        return $cols;
    }
    $res = @$conn->query("SHOW COLUMNS FROM `{$table}`");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $cols[] = $r['Field'];
        }
    }
    return $cols;
}

function tsStatusInfo(int $pct): array {
    $pct = max(0, min(100, $pct));
    if ($pct >= 100) {
        return ['label' => 'Complete', 'cls' => 'st-complete'];
    }
    if ($pct === 0) {
        return ['label' => 'NOT WORKING', 'cls' => 'st-not-working'];
    }
    return ['label' => 'Working', 'cls' => 'st-working'];
}

$profile_msg = '';
$profile_err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_profile_pic']) && isset($conn) && $conn instanceof mysqli) {
    if (!isset($_FILES['new_profile_pic']) || $_FILES['new_profile_pic']['error'] !== UPLOAD_ERR_OK) {
        $profile_err = 'Select an image file first to change your profile.';
    } else {
        $file_tmp  = $_FILES['new_profile_pic']['tmp_name'];
        $file_size = intval($_FILES['new_profile_pic']['size'] ?? 0);
        $orig_name = basename((string)($_FILES['new_profile_pic']['name'] ?? 'avatar'));
        $ext       = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
        $allowed   = ['jpg', 'jpeg', 'png', 'webp'];

        $img_info = @getimagesize($file_tmp);
        if ($img_info === false || !in_array($ext, $allowed, true)) {
            $profile_err = 'JPG, JPEG, PNG, or WEBP image files only.';
        } elseif ($file_size > 3 * 1024 * 1024) {
            $profile_err = 'File is too large (max 3MB allowed).';
        } else {
            $upload_dir = __DIR__ . '/uploads/avatars';
            if (!is_dir($upload_dir)) {
                @mkdir($upload_dir, 0777, true);
            }

            $new_filename = 'teacher_' . $user_id . '_' . time() . '.' . $ext;
            $dest_path    = $upload_dir . '/' . $new_filename;
            $rel_path     = 'uploads/avatars/' . $new_filename;

            if (@move_uploaded_file($file_tmp, $dest_path)) {
                $ucols_check = tsGetCols($conn, 'users');
                if (!in_array('profile_pic', $ucols_check, true)) {
                    @$conn->query("ALTER TABLE `users` ADD COLUMN `profile_pic` VARCHAR(255) NULL DEFAULT NULL");
                }

                $upd = @$conn->prepare("UPDATE `users` SET `profile_pic` = ? WHERE `id` = ? LIMIT 1");
                if ($upd) {
                    $upd->bind_param("si", $rel_path, $user_id);
                    if (@$upd->execute()) {
                        $_SESSION['profile_pic'] = $rel_path;
                        $profile_msg = 'Your profile picture has been updated!';
                    } else {
                        $profile_err = 'The file was uploaded but not saved to the database.';
                    }
                    $upd->close();
                }
            } else {
                $profile_err = 'The image was not uploaded. Please check the folder permissions of uploads/avatars.';
            }
        }
    }
}

$teacher = null;
if (isset($conn) && $conn instanceof mysqli) {
    $stmt = @$conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        if (@$stmt->execute()) {
            $res = $stmt->get_result();
            if ($res && $res->num_rows > 0) {
                $teacher = $res->fetch_assoc();
            }
        }
        $stmt->close();
    }
}

$teacher_name = trim((string)(
    $teacher['full_name']
    ?? $teacher['name']
    ?? $teacher['username']
    ?? $_SESSION['full_name']
    ?? $_SESSION['username']
    ?? 'Teacher'
));

$teacher_dept = trim((string)(
    $teacher['department']
    ?? $teacher['program']
    ?? $_SESSION['department']
    ?? 'IT Department'
));

$profile_pic_path = trim((string)($teacher['profile_pic'] ?? $_SESSION['profile_pic'] ?? ''));


$avatar_pic = trim((string)($teacher['profile_pic'] ?? $_SESSION['profile_pic'] ?? ''));
$has_avatar_pic = ($avatar_pic !== '' && file_exists(__DIR__ . '/' . $avatar_pic));

$handled_blocks = [];
if (isset($conn) && $conn instanceof mysqli && tsHasTable($conn, 'teacher_assignments')) {
    $ta_q = @$conn->query("SELECT * FROM teacher_assignments WHERE teacher_id = {$user_id}");
    if ($ta_q) {
        while ($ta = $ta_q->fetch_assoc()) {
            $blk = strtoupper(trim((string)($ta['block_name'] ?? $ta['block'] ?? '')));
            if ($blk !== '' && !in_array($blk, $handled_blocks, true)) {
                $handled_blocks[] = $blk;
            }
        }
    }
}

$student_work_rows = [];
if (isset($conn) && $conn instanceof mysqli && tsHasTable($conn, 'users')) {
    $ucols = tsGetCols($conn, 'users');
    $has_full = in_array('full_name', $ucols, true);
    $has_user = in_array('username', $ucols, true);
    $has_blk  = in_array('block', $ucols, true);
    $has_role = in_array('role', $ucols, true);

    if ($has_full && $has_user) {
        $stu_name_expr = "COALESCE(NULLIF(TRIM(u.full_name), ''), u.username, 'Student')";
    } elseif ($has_full) {
        $stu_name_expr = "COALESCE(NULLIF(TRIM(u.full_name), ''), 'Student')";
    } elseif ($has_user) {
        $stu_name_expr = "COALESCE(NULLIF(TRIM(u.username), ''), 'Student')";
    } else {
        $stu_name_expr = "'Student'";
    }

    $stu_blk_expr = $has_blk ? "COALESCE(u.block, '')" : "''";
    $where_role   = $has_role ? "WHERE LOWER(u.role) = 'student'" : "";

    if (tsHasTable($conn, 'student_activity_progress')) {
        $has_act = tsHasTable($conn, 'activities');
        $acols   = $has_act ? tsGetCols($conn, 'activities') : [];
        $act_title_expr = ($has_act && in_array('title', $acols, true)) ? 'a.title' : "'Activity'";
        $act_subj_expr  = ($has_act && in_array('subject_code', $acols, true)) ? 'a.subject_code' : (($has_act && in_array('subject', $acols, true)) ? 'a.subject' : "'Subject'");
        $act_blk_expr   = ($has_act && in_array('block', $acols, true)) ? 'a.block' : "''";
        $join_act = $has_act ? "LEFT JOIN activities a ON a.id = sap.activity_id" : "";
        $and_role = $has_role ? "AND LOWER(u.role) = 'student'" : "";

        $q = @$conn->query("
            SELECT
                u.id AS student_id,
                {$stu_name_expr} AS student_name,
                {$stu_blk_expr} AS student_block,
                {$act_title_expr} AS activity_title,
                {$act_subj_expr} AS subject_label,
                {$act_blk_expr} AS activity_block,
                COALESCE(sap.progress_percent, 0) AS progress_percent,
                COALESCE(sap.last_action, '') AS last_action,
                sap.updated_at
            FROM student_activity_progress sap
            INNER JOIN users u ON u.id = sap.student_id
            {$join_act}
            WHERE 1=1 {$and_role}
            ORDER BY sap.updated_at DESC
        ");
        if ($q) {
            while ($r = $q->fetch_assoc()) {
                $s_blk = strtoupper(trim((string)($r['student_block'] ?? '')));
                $a_blk = strtoupper(trim((string)($r['activity_block'] ?? '')));
                $chk_blk = ($s_blk !== '') ? $s_blk : $a_blk;

                if (!empty($handled_blocks) && $chk_blk !== '') {
                    $matched = false;
                    foreach ($handled_blocks as $hb) {
                        if (strpos($chk_blk, $hb) !== false || strpos($hb, $chk_blk) !== false) {
                            $matched = true;
                            break;
                        }
                    }
                    if (!$matched) {
                        continue;
                    }
                }

                $pct = max(0, min(100, intval($r['progress_percent'] ?? 0)));
                $st  = tsStatusInfo($pct);
                $act_txt = trim((string)($r['last_action'] ?? ''));
                if ($act_txt === '' || $act_txt === 'none') {
                    $act_txt = ($pct > 0) ? 'Opened / Working' : 'No activity yet';
                }

                $student_work_rows[] = [
                    'student_name'   => (string)($r['student_name'] ?? 'Student'),
                    'student_block'  => ($chk_blk !== '') ? $chk_blk : '—',
                    'subject_label'  => (string)($r['subject_label'] ?? 'Subject'),
                    'activity_title' => (string)($r['activity_title'] ?? 'Activity'),
                    'last_action'    => $act_txt,
                    'progress_pct'   => $pct,
                    'status_label'   => $st['label'],
                    'status_cls'     => $st['cls'],
                    'updated_at'     => (string)($r['updated_at'] ?? '')
                ];
            }
        }
    }

    if (empty($student_work_rows)) {
        $qu = @$conn->query("SELECT id, {$stu_name_expr} AS student_name, {$stu_blk_expr} AS student_block FROM users {$where_role}");
        if ($qu) {
            while ($u = $qu->fetch_assoc()) {
                $s_blk = strtoupper(trim((string)($u['student_block'] ?? '')));
                if (!empty($handled_blocks) && $s_blk !== '') {
                    $matched = false;
                    foreach ($handled_blocks as $hb) {
                        if (strpos($s_blk, $hb) !== false || strpos($hb, $s_blk) !== false) {
                            $matched = true;
                            break;
                        }
                    }
                    if (!$matched) {
                        continue;
                    }
                }
                $st = tsStatusInfo(0);
                $student_work_rows[] = [
                    'student_name'   => (string)($u['student_name'] ?? 'Student'),
                    'student_block'  => ($s_blk !== '') ? $s_blk : '—',
                    'subject_label'  => '—',
                    'activity_title' => 'No activity has been done yet.',
                    'last_action'    => 'none',
                    'progress_pct'   => 0,
                    'status_label'   => $st['label'],
                    'status_cls'     => $st['cls'],
                    'updated_at'     => ''
                ];
            }
        }
    }
}


$rep_total = count($student_work_rows);
$rep_complete = 0;
$rep_working = 0;
$rep_not_working = 0;
$rep_pct_sum = 0;
$rep_by_activity = [];

foreach ($student_work_rows as $sw) {
    $p = intval($sw['progress_pct']);
    $rep_pct_sum += $p;
    if ($p >= 100) {
        $rep_complete++;
    } elseif ($p === 0) {
        $rep_not_working++;
    } else {
        $rep_working++;
    }

    $akey = $sw['subject_label'] . ' - ' . $sw['activity_title'];
    if (!isset($rep_by_activity[$akey])) {
        $rep_by_activity[$akey] = [
            'label'    => $akey,
            'count'    => 0,
            'complete' => 0,
            'working'  => 0,
            'not_work' => 0,
            'pct_sum'  => 0
        ];
    }
    $rep_by_activity[$akey]['count']++;
    $rep_by_activity[$akey]['pct_sum'] += $p;
    if ($p >= 100) $rep_by_activity[$akey]['complete']++;
    elseif ($p === 0) $rep_by_activity[$akey]['not_work']++;
    else $rep_by_activity[$akey]['working']++;
}

$rep_avg_pct = ($rep_total > 0) ? round($rep_pct_sum / $rep_total) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCDI Teacher Portal - Settings</title>
    <link rel="stylesheet" href="style.css">
    <style>
       
        .settings-wrapper {
            max-width: 760px;
            margin: 16px auto 0 auto;
        }
        .settings-avatar-ring {
            width: 96px;
            height: 96px;
            margin: 0 auto 14px auto;
            border-radius: 50%;
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            border: 2px solid #93c5fd;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0b2545;
            font-size: 40px;
            overflow: hidden;
            box-shadow: 0 6px 16px rgba(30, 96, 145, 0.12);
        }
        .settings-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 48px 28px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            text-align: center;
        }
        .settings-avatar-ring img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .settings-role-title {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 4px 0;
        }
        .settings-subtitle {
            font-size: 13px;
            color: #64748b;
            font-weight: 600;
            margin: 0 0 20px 0;
        }
        .change-profile-box {
            max-width: 440px;
            margin: 0 auto 18px auto;
            padding: 12px 14px;
            background: #f8fafc;
            border: 1px dashed #94a3b8;
            border-radius: 12px;
            text-align: left;
        }
        .change-profile-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            flex-wrap: wrap;
        }
        .btn-change-profile {
            background: #0b2545;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            padding: 8px 14px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }
        .btn-change-profile:hover {
            background: #1e6091;
        }
        .teacher-settings-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-width: 440px;
            margin: 0 auto;
        }
        .teacher-action-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 13px 20px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
        }
        .teacher-action-btn.primary {
            background-color: #1e6091;
            color: #ffffff;
            border: none;
            box-shadow: 0 4px 12px rgba(30, 96, 145, 0.22);
        }
        .teacher-action-btn.primary:hover {
            background-color: #0b2545;
            transform: translateY(-1px);
        }
        .teacher-action-btn.secondary {
            background-color: #f8fafc;
            color: #0b2545;
            border: 1px solid #cbd5e1;
        }
        .teacher-action-btn.secondary:hover {
            background-color: #e2e8f0;
            transform: translateY(-1px);
        }
        .detail-panel-box {
            margin-top: 18px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 22px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            text-align: left;
        }
        .detail-panel-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
        }
        .work-table-wrap {
            overflow-x: auto;
        }
        .work-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .work-table th, .work-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }
        .work-table th {
            background: #f8fafc;
            font-weight: 800;
            color: #0f172a;
        }
        .st-tag {
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
            display: inline-block;
        }
        .st-complete { background: #dcfce7; color: #166534; }
        .st-working { background: #fef9c3; color: #854d0e; }
        .st-not-working { background: #fee2e2; color: #991b1b; }
        .rep-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 14px;
        }
        .rep-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px;
        }
    </style>
</head>
<body>

    <header class="topbar" style="background-color: #0b2545;">
        <div class="brand">
            <div class="brand-logo"><img src="logo.jpg" alt="logo" style="width:100%; height:100%; object-fit:contain;"></div>
            <span>CCDI Teacher Portal</span>
        </div>
        <input type="text" id="teacherSettingsSearch" class="search-bar" placeholder="Search">
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
        <a href="teacher_activities.php">Activities</a>
        <a href="teacher_live.php">Live Sessions</a>
        <a href="teacher_settings.php" class="active">Settings</a>
    </nav>

    <main class="main-wrapper">
        <div class="page-header">
            <h1>SETTINGS</h1>
            <p>Faculty account and monitoring shortcuts</p>
        </div>

        <div class="settings-wrapper">
            <div class="settings-card">
                <div class="settings-avatar-ring">
                    <?php if ($profile_pic_path !== '' && file_exists(__DIR__ . '/' . $profile_pic_path)): ?>
                        <img src="<?php echo htmlspecialchars($profile_pic_path); ?>" alt="Teacher Profile">
                    <?php else: ?>
                        <span>&#128100;</span>
                    <?php endif; ?>
                </div>

                <h2 class="settings-role-title"><?php echo htmlspecialchars($teacher_name); ?></h2>
                <p class="settings-subtitle"><?php echo htmlspecialchars($teacher_dept); ?></p>

                <?php if ($profile_msg !== ''): ?>
                    <div style="max-width:440px; margin:0 auto 12px auto; background:#dcfce7; color:#166534; padding:8px 12px; border-radius:8px; font-size:12px; font-weight:700;">
                        <?php echo htmlspecialchars($profile_msg); ?>
                    </div>
                <?php endif; ?>
                <?php if ($profile_err !== ''): ?>
                    <div style="max-width:440px; margin:0 auto 12px auto; background:#fee2e2; color:#991b1b; padding:8px 12px; border-radius:8px; font-size:12px; font-weight:700;">
                        <?php echo htmlspecialchars($profile_err); ?>
                    </div>
                <?php endif; ?>

               
                <form method="POST" enctype="multipart/form-data" class="change-profile-box">
                    <label for="new_profile_pic" style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:6px;">
                        Change Profile Picture
                    </label>
                    <div class="change-profile-row">
                        <input type="file" id="new_profile_pic" name="new_profile_pic" accept=".jpg,.jpeg,.png,.webp,image/*" style="font-size:12px; max-width:260px;">
                        <button type="submit" name="change_profile_pic" class="btn-change-profile">Change Profile</button>
                    </div>
                </form>

                <div class="teacher-settings-actions" id="teacherActionsList">
                    <button type="button" id="btnClassProductivity" class="teacher-action-btn primary">Class Productivity</button>
                    <button type="button" id="btnReportSection" class="teacher-action-btn secondary">Report</button>
                </div>
            </div>

            <div id="classProductivityPanel" class="detail-panel-box" style="display: none;">
                <div class="detail-panel-head">
                    <div>
                        <h3 style="margin:0; font-size:18px; font-weight:800; color:#0f172a;">Class Productivity</h3>
                        <p style="margin:2px 0 0 0; font-size:12px; color:#64748b;">Everything each student under your supervision has done, and their progress.</p>
                    </div>
                    <button type="button" class="js-close-panel" data-target="classProductivityPanel" style="background:#e2e8f0; color:#0f172a; border:none; border-radius:8px; padding:6px 11px; font-size:12px; font-weight:700; cursor:pointer;">Hide</button>
                </div>

                <div class="work-table-wrap">
                    <table class="work-table" id="classProdTable">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Block</th>
                                <th>Activity / File</th>
                                <th>Work</th>
                                <th>Progress</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($student_work_rows)): ?>
                                <?php foreach ($student_work_rows as $sw): ?>
                                    <tr class="js-work-row">
                                        <td><strong><?php echo htmlspecialchars($sw['student_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($sw['student_block']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($sw['subject_label']); ?>
                                            <?php if ($sw['activity_title'] !== ''): ?>
                                                &mdash; <?php echo htmlspecialchars($sw['activity_title']); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($sw['last_action']); ?></td>
                                        <td><strong><?php echo intval($sw['progress_pct']); ?>%</strong></td>
                                        <td>
                                            <span class="st-tag <?php echo htmlspecialchars($sw['status_cls']); ?>">
                                                <?php echo htmlspecialchars($sw['status_label']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; color:#64748b; padding:16px;">No activities have been submitted by students yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            
            <div id="reportPanel" class="detail-panel-box" style="display: none;">
                <div class="detail-panel-head">
                    <div>
                        <h3 style="margin:0; font-size:18px; font-weight:800; color:#0f172a;">Class Report</h3>
                        <p style="margin:2px 0 0 0; font-size:12px; color:#64748b;">Summary of completion, average progress, and performance of the class.</p>
                    </div>
                    <div style="display:flex; gap:6px;">
                        <button type="button" id="btnDownloadReportCsv" style="background:#0284c7; color:#fff; border:none; border-radius:8px; padding:6px 11px; font-size:12px; font-weight:700; cursor:pointer;">CSV</button>
                        <button type="button" onclick="window.print();" style="background:#0b2545; color:#fff; border:none; border-radius:8px; padding:6px 11px; font-size:12px; font-weight:700; cursor:pointer;">Print</button>
                        <button type="button" class="js-close-panel" data-target="reportPanel" style="background:#e2e8f0; color:#0f172a; border:none; border-radius:8px; padding:6px 11px; font-size:12px; font-weight:700; cursor:pointer;">Hide</button>
                    </div>
                </div>

                <div class="rep-grid">
                    <div class="rep-card">
                        <div style="font-size:20px; font-weight:800; color:#0b2545;"><?php echo $rep_total; ?></div>
                        <div style="font-size:12px; color:#64748b; font-weight:700;">Total Entries</div>
                    </div>
                    <div class="rep-card">
                        <div style="font-size:20px; font-weight:800; color:#15803d;"><?php echo $rep_complete; ?></div>
                        <div style="font-size:12px; color:#64748b; font-weight:700;">Complete (100%)</div>
                    </div>
                    <div class="rep-card">
                        <div style="font-size:20px; font-weight:800; color:#a16207;"><?php echo $rep_working; ?></div>
                        <div style="font-size:12px; color:#64748b; font-weight:700;">Working (1–99%)</div>
                    </div>
                    <div class="rep-card">
                        <div style="font-size:20px; font-weight:800; color:#b91c1c;"><?php echo $rep_not_working; ?></div>
                        <div style="font-size:12px; color:#64748b; font-weight:700;">NOT WORKING (0%) &bull; Avg <?php echo $rep_avg_pct; ?>%</div>
                    </div>
                </div>

                <div class="work-table-wrap">
                    <table class="work-table">
                        <thead>
                            <tr>
                                <th>Activity</th>
                                <th>Total</th>
                                <th>Complete</th>
                                <th>Working</th>
                                <th>NOT WORKING</th>
                                <th>Avg %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($rep_by_activity)): ?>
                                <?php foreach ($rep_by_activity as $ra): ?>
                                    <?php $a_avg = $ra['count'] > 0 ? round($ra['pct_sum'] / $ra['count']) : 0; ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($ra['label']); ?></strong></td>
                                        <td><?php echo intval($ra['count']); ?></td>
                                        <td><?php echo intval($ra['complete']); ?></td>
                                        <td><?php echo intval($ra['working']); ?></td>
                                        <td><?php echo intval($ra['not_work']); ?></td>
                                        <td><strong><?php echo $a_avg; ?>%</strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; color:#64748b; padding:14px;">No report data yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script>
        (function() {
            var searchInput = document.getElementById('teacherSettingsSearch');
            var actionsList = document.getElementById('teacherActionsList');
            var prodBtn = document.getElementById('btnClassProductivity');
            var repBtn = document.getElementById('btnReportSection');
            var prodPanel = document.getElementById('classProductivityPanel');
            var repPanel = document.getElementById('reportPanel');
            var csvBtn = document.getElementById('btnDownloadReportCsv');

            function toggleBox(box) {
                if (!box) return;
                var isHidden = (box.style.display === 'none' || box.style.display === '');
                box.style.display = isHidden ? 'block' : 'none';
                if (isHidden) {
                    box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }

            if (prodBtn && prodPanel) {
                prodBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    toggleBox(prodPanel);
                });
            }

            if (repBtn && repPanel) {
                repBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    toggleBox(repPanel);
                });
            }

            document.querySelectorAll('.js-close-panel').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var tid = this.getAttribute('data-target');
                    var el = tid ? document.getElementById(tid) : null;
                    if (el) el.style.display = 'none';
                });
            });

            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    var q = this.value.toLowerCase().trim();
                    if (actionsList) {
                        actionsList.querySelectorAll('.teacher-action-btn').forEach(function(btn) {
                            var text = btn.textContent.toLowerCase();
                            btn.style.display = (q === '' || text.indexOf(q) !== -1) ? 'flex' : 'none';
                        });
                    }
                    document.querySelectorAll('.js-work-row').forEach(function(row) {
                        var rtxt = row.textContent.toLowerCase();
                        row.style.display = (q === '' || rtxt.indexOf(q) !== -1) ? '' : 'none';
                    });
                });
            }

            if (csvBtn) {
                csvBtn.addEventListener('click', function() {
                    var rows = document.querySelectorAll('#classProdTable tr');
                    var lines = [];
                    rows.forEach(function(tr) {
                        var cols = tr.querySelectorAll('th, td');
                        var rowVals = [];
                        cols.forEach(function(td) {
                            var clean = td.innerText.replace(/\s+/g, ' ').trim().replace(/"/g, '""');
                            rowVals.push('"' + clean + '"');
                        });
                        lines.push(rowVals.join(','));
                    });
                    var blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
                    var url = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'teacher_class_report.csv';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                });
            }
        })();
    </script>
</body>
</html>