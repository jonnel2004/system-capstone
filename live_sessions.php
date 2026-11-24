<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') { 
    header("Location: login.php"); 
    exit(); 
}

$avatar_pic = trim((string)($teacher['profile_pic'] ?? $_SESSION['profile_pic'] ?? ''));
$has_avatar_pic = ($avatar_pic !== '' && file_exists(__DIR__ . '/' . $avatar_pic));


$student_id = intval($_SESSION['user_id']);


$student_query = $conn->query("SELECT full_name, program, block FROM users WHERE id = $student_id LIMIT 1");
$student = ($student_query && $student_query->num_rows > 0) ? $student_query->fetch_assoc() : [];

$student_name = trim((string)($student['full_name'] ?? $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Student'));
if ($student_name === '') {
    $student_name = 'Student';
}

$raw_program = trim($student['program'] ?? '');
if (preg_match('/\(([A-Za-z0-9]+)\)/', $raw_program, $matches)) {
    $student_program = strtoupper(trim($matches[1])); 
} else {
    $student_program = strtoupper($raw_program);
}

$raw_block = trim($student['block'] ?? '');
$clean_block = strtoupper(trim(preg_replace('/^block\s*-?\s*/i', '', $raw_block))); 
$norm_block  = str_replace('-', '', $clean_block);

$display_label = trim($student_program . ' - ' . $clean_block, ' -');


$safe_prog = $conn->real_escape_string($student_program);
$safe_blk  = $conn->real_escape_string($clean_block);
$safe_norm = $conn->real_escape_string($norm_block);

$conditions = [];
if ($safe_norm !== '') {
    $conditions[] = "UPPER(REPLACE(REPLACE(TRIM(subject_block), 'BLOCK', ''), '-', '')) LIKE '%$safe_norm%'";
}
if ($safe_prog !== '') {
    $conditions[] = "UPPER(TRIM(subject_block)) LIKE '%$safe_prog%'";
}

$where_sql = !empty($conditions) ? "(" . implode(" OR ", $conditions) . ")" : "1=1";
if ($safe_norm !== '' && $safe_prog !== '') {

    $where_sql = "UPPER(REPLACE(REPLACE(TRIM(subject_block), 'BLOCK', ''), '-', '')) LIKE '%$safe_norm%'";
}

$sessions_query = $conn->query("
    SELECT * 
    FROM room_schedules 
    WHERE $where_sql
    ORDER BY id DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student - Live Sessions</title>
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --primary: #1e6091;
            --bg-light: #f8fafc;
            --text-main: #1e293b;
        }
        body { background-color: var(--bg-light); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: var(--text-main); }
        .session-card {
            background: white;
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            border: 1px solid #e2e8f0;
        }
        .badge-live {
            background: #ef4444;
            color: white;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-f2f {
            background: #3b82f6;
            color: white;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>
    
    <header class="topbar" style="background-color: #0b2545;">
        <div class="brand">
            <div class="brand-logo"><img src="logo.jpg" alt="logo" style="width:100%; height:100%; object-fit:contain;"></div>
            <span>CCDI Student Portal</span>
        </div>
        <input type="text" id="studentSubjectSearch" class="search-bar" placeholder="Search subject or block">
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
        <a href="activity_feed.php">Activities</a>
        <a href="live_sessions.php" class="active">Live Sessions</a>
        <a href="settings.php">Settings</a>
    </nav>

    <main class="main-wrapper">
        <div class="page-header" style="margin-bottom: 24px;">
            <h1 style="font-size: 28px; font-weight: 800; color: #0f172a;">Student Sessions</h1>
        </div>

        <div class="panel-card" style="background: transparent; padding: 0; box-shadow: none;">
            <?php if ($sessions_query && $sessions_query->num_rows > 0): ?>
                <?php while ($row = $sessions_query->fetch_assoc()): ?>
                    <?php 
                        $room = trim($row['room_name'] ?? '');
                        $is_live = (stripos($room, 'online') !== false || stripos($room, 'live') !== false || empty($room));
                    ?>
                    <div class="session-card">
                        <div>
                            <h3 style="font-size: 18px; font-weight: 700; color: #1e293b; margin: 0 0 4px 0;">
                                <?php echo htmlspecialchars($row['subject_block'] ?? 'Subject Session'); ?>
                            </h3>
                            <span style="font-size: 13px; color: #64748b;">
                                <?php echo htmlspecialchars($row['day_of_week'] ?? ''); ?>
                                <?php if (!empty($row['time_slot'])): ?>
                                    | <?php echo htmlspecialchars($row['time_slot']); ?>
                                <?php endif; ?>
                                <?php if (!$is_live): ?>
                                    | Room: <strong><?php echo htmlspecialchars($room); ?></strong>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <?php if ($is_live): ?>
                                <span class="badge-live">LIVE</span>
                            <?php else: ?>
                                <span class="badge-f2f">Face-to-Face</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="background: white; padding: 30px; border-radius: 16px; border: 1px solid #e2e8f0; text-align: center; color: #64748b;">
                    There is no schedule set yet for your block. (<strong><?php echo htmlspecialchars($display_label !== '' ? $display_label : 'N/A'); ?></strong>).
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>