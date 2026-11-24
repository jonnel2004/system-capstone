<?php
if (session_id() === '') {
    @session_start();
}
include 'db.php';

// Security check: only Admin can accses this page
$current_role = strtolower(trim($_SESSION['role'] ?? $_SESSION['user_role'] ?? ''));
if (!isset($_SESSION['user_id']) || $current_role !== 'admin') {
    header("Location: login.php");
    exit();
}

$admin_id = intval($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
$u_pic_q = @$conn->query("SELECT profile_pic FROM users WHERE id = {$admin_id} LIMIT 1");
$u_pic_row = ($u_pic_q && $u_pic_q->num_rows > 0) ? $u_pic_q->fetch_assoc() : [];
$avatar_pic = trim((string)($u_pic_row['profile_pic'] ?? $_SESSION['profile_pic'] ?? ''));
$has_avatar_pic = ($avatar_pic !== '' && file_exists(__DIR__ . '/' . $avatar_pic));


function getRealTimeRoomsCount($conn) {
    $count = 0;

    $chk_rooms = $conn->query("SHOW TABLES LIKE 'rooms'");
    if ($chk_rooms && $chk_rooms->num_rows > 0) {
        $q = $conn->query("SELECT COUNT(*) AS total FROM rooms");
        if ($q && $row = $q->fetch_assoc()) {
            $count = max($count, intval($row['total'] ?? 0));
        }
    }

    $sources = [
        ['table' => 'room_schedules', 'col' => 'room_name'],
        ['table' => 'room_schedules', 'col' => 'room'],
        ['table' => 'schedules', 'col' => 'room'],
        ['table' => 'schedules', 'col' => 'room_name'],
        ['table' => 'live_sessions', 'col' => 'room'],
        ['table' => 'live_sessions', 'col' => 'room_name'],
    ];

    foreach ($sources as $src) {
        $t_chk = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($src['table']) . "'");
        if ($t_chk && $t_chk->num_rows > 0) {
            $c_chk = $conn->query("SHOW COLUMNS FROM " . $conn->real_escape_string($src['table']) . " LIKE '" . $conn->real_escape_string($src['col']) . "'");
            if ($c_chk && $c_chk->num_rows > 0) {
                $q = $conn->query("SELECT COUNT(DISTINCT TRIM(" . $src['col'] . ")) AS total FROM " . $conn->real_escape_string($src['table']) . " WHERE " . $src['col'] . " IS NOT NULL AND TRIM(" . $src['col'] . ") <> ''");
                if ($q && $row = $q->fetch_assoc()) {
                    $count = max($count, intval($row['total'] ?? 0));
                }
            }
        }
    }

    return $count;
}

if (isset($_GET['ajax_rooms_count'])) {
    header('Content-Type: application/json');
    echo json_encode(['rooms' => getRealTimeRoomsCount($conn)]);
    exit();
}

$msg = "";


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_subject'])) {
    $code = $conn->real_escape_string(trim($_POST['code']));
    $title = $conn->real_escape_string(trim($_POST['title']));
    $instructor = $conn->real_escape_string(trim($_POST['instructor']));
    $theme = $conn->real_escape_string($_POST['card_theme']);

    if ($conn->query("INSERT INTO subjects (code, title, instructor, card_theme) VALUES ('$code', '$title', '$instructor', '$theme')")) {
        $msg = "New subject added successfully!";
    }
}


$total_students = intval($conn->query("SELECT COUNT(*) as total FROM users WHERE LOWER(TRIM(role))='student'")->fetch_assoc()['total'] ?? 0);
$total_teachers = intval($conn->query("SELECT COUNT(*) as total FROM users WHERE LOWER(TRIM(role))='teacher'")->fetch_assoc()['total'] ?? 0);
$total_rooms = getRealTimeRoomsCount($conn);

$user_cols = [];
$col_res = $conn->query("SHOW COLUMNS FROM users");
if ($col_res) {
    while ($c = $col_res->fetch_assoc()) {
        $user_cols[] = $c['Field'];
    }
}
$stu_num_col = '';
foreach (['student_id', 'student_number', 'student_no', 'id_number', 'school_id'] as $cand) {
    if (in_array($cand, $user_cols, true)) {
        $stu_num_col = $cand;
        break;
    }
}


$all_users = $conn->query("SELECT * FROM users ORDER BY id DESC");

$student_tally = [];
$q_stu_list = $conn->query("SELECT * FROM users WHERE LOWER(TRIM(role)) = 'student' ORDER BY id ASC");
if ($q_stu_list && $q_stu_list->num_rows > 0) {
    while ($s_row = $q_stu_list->fetch_assoc()) {
        $s_num = ($stu_num_col !== '' && !empty(trim((string)($s_row[$stu_num_col] ?? ''))))
            ? trim((string)$s_row[$stu_num_col])
            : '—';
        $student_tally[] = [
            'student_no' => $s_num,
            'name'       => !empty($s_row['full_name']) ? $s_row['full_name'] : ($s_row['username'] ?? 'Student'),
            'block'      => trim(($s_row['program'] ?? '') . ' ' . ($s_row['block'] ?? '')),
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCDI Admin Portal - Admin Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 24px; }
        .stat-box { background: #ffffff; padding: 20px; border-radius: 12px; border-left: 5px solid #1e6091; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .stat-box h3 { font-size: 14px; color: #64748b; }
        .stat-box p { font-size: 32px; font-weight: 800; color: #0f172a; margin-top: 6px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
        th { background-color: #f8fafc; color: #334155; }
        .role-badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .role-admin { background: #fee2e2; color: #991b1b; }
        .role-teacher { background: #fef3c7; color: #92400e; }
        .role-student { background: #dbeafe; color: #1e40af; }
        .right-stack { display: flex; flex-direction: column; gap: 20px; }
        .tally-item { display: flex; justify-content: space-between; align-items: center; padding: 8px 10px; border-bottom: 1px solid #f1f5f9; font-size: 13px; gap: 8px; }
        .tally-no { background: #e0f2fe; color: #0369a1; font-weight: 800; padding: 2px 8px; border-radius: 6px; font-size: 12px; white-space: nowrap; }
    </style>
</head>
<body>

    <header class="topbar" style="background-color: #0b2545;">
        <div class="brand">
            <div class="brand-logo"><img src="logo.jpg" alt="logo" style="width:100%; height:100%; object-fit:contain;"></div>
            <span>CCDI Admin Portal</span>
        </div>
        <input type="text" id="adminSearchInput" class="search-bar" placeholder="Search">
        <span>&#128172; &#128276;</span>
        <div class="user-menu">
        <div class="user-avatar" style="overflow:hidden; padding:0; display:flex; align-items:center; justify-content:center;">
           <?php if ($has_avatar_pic): ?>
          <img src="<?php echo htmlspecialchars($avatar_pic); ?>" alt="Admin Profile" style="width:100%; height:100%; object-fit:cover; display:block;">
          <?php else: ?>
          AD
          <?php endif; ?>
        </div>
            <span>Admin</span>
            <a href="logout.php" class="logout-link">logout</a>
        </div>
    </header>

    <nav class="sidebar">
        <a href="admin_dashboard.php" class="active">Admin Overview</a>
        <a href="admin_courses.php">Manage Courses</a>
        <a href="admin_students.php">Manage Students</a>
        <a href="admin_faculty.php">Manage Facultys</a>
        <a href="admin_program.php">Manage Programs</a>
        <a href="admin_rooms.php">Manage Rooms</a>
        <a href="admin_settings.php">System Settings</a>
    </nav>

    <main class="main-wrapper">
        <div class="page-header">
            <h1>Administrator Dashboard</h1>
            <p><?php echo date("M d, Y"); ?></p>
        </div>

        <?php if ($msg): ?>
            <p style="background:#dcfce7; color:#166534; padding:10px 16px; border-radius:8px; margin-bottom:16px; font-weight:600;"><?php echo $msg; ?></p>
        <?php endif; ?>

  
        <div class="stats-grid">
            <div class="stat-box">
                <h3>Total Enrolled Students</h3>
                <p><?php echo $total_students; ?></p>
            </div>
            <div class="stat-box" style="border-left-color: #16a34a;">
                <h3>Total Teachers</h3>
                <p><?php echo $total_teachers; ?></p>
            </div>
            <div class="stat-box" style="border-left-color: #9333ea;">
                <h3>ROOMS</h3>
                <p id="realtime-rooms-count"><?php echo $total_rooms; ?></p>
            </div>
        </div>

        <div class="dashboard-grid">
            <!-- Left: Registered Users Table -->
            <div class="panel-card">
                <h2 class="section-title">Registered Accounts</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Course / Year_level</th>
                            <th>Role</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody">
                        <?php if ($all_users && $all_users->num_rows > 0): ?>
                            <?php while ($u = $all_users->fetch_assoc()): ?>
                                <?php $r_class = 'role-' . strtolower($u['role'] ?? 'student'); ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($u['full_name'] ?? $u['username'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($u['email'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars(trim(($u['program'] ?? '') . ' ' . ($u['block'] ?? ''))); ?></td>
                                    <td><span class="role-badge <?php echo $r_class; ?>"><?php echo htmlspecialchars($u['role'] ?? ''); ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Right: Add New Subject + Student ID Tally -->
            <div class="right-stack">
                <div class="panel-card">
                    <h2 class="section-title">Add New Subject</h2>
                    <form method="POST">
                        <div style="margin-bottom:10px;">
                            <label style="display:block; font-size:12px; margin-bottom:4px;">Subject Code (e.g. CAP101)</label>
                            <input type="text" name="code" required style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;">
                        </div>
                        <div style="margin-bottom:10px;">
                            <label style="display:block; font-size:12px; margin-bottom:4px;">Subject Title</label>
                            <input type="text" name="title" required style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;">
                        </div>
                        <div style="margin-bottom:10px;">
                            <label style="display:block; font-size:12px; margin-bottom:4px;">Instructor Name</label>
                            <input type="text" name="instructor" required style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;">
                        </div>
                        <div style="margin-bottom:14px;">
                            <label style="display:block; font-size:12px; margin-bottom:4px;">Card Color Theme</label>
                            <select name="card_theme" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px;">
                                <option value="blue">Blue</option>
                                <option value="green">Green</option>
                                <option value="purple">Purple</option>
                            </select>
                        </div>
                        <button type="submit" name="add_subject" style="background:#1e6091; color:white; border:none; padding:10px 16px; border-radius:8px; font-weight:700; cursor:pointer;">Add Subject</button>
                    </form>
                </div>

                <div class="panel-card">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <h2 class="section-title" style="margin:0;">Student ID Tally</h2>
                        <span style="font-size:12px; font-weight:800; background:#dbeafe; color:#1e40af; padding:3px 10px; border-radius:999px;">
                            Total: <?php echo count($student_tally); ?>
                        </span>
                    </div>
                    <?php if (!empty($student_tally)): ?>
                        <div id="studentTallyList">
                            <?php foreach ($student_tally as $st_item): ?>
                                <div class="tally-item">
                                    <div>
                                        <strong><?php echo htmlspecialchars($st_item['name']); ?></strong>
                                        <?php if ($st_item['block'] !== ''): ?>
                                            <div style="font-size:11px; color:#64748b;"><?php echo htmlspecialchars($st_item['block']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <span class="tally-no"><?php echo htmlspecialchars($st_item['student_no']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="font-size:13px; color:#64748b; margin:0;">No students have registered yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        function refreshRoomsCount() {
            fetch('admin_dashboard.php?ajax_rooms_count=1&_=' + Date.now())
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    var el = document.getElementById('realtime-rooms-count');
                    if (el && typeof data.rooms !== 'undefined') {
                        el.textContent = data.rooms;
                    }
                })
                .catch(function() {});
        }
        setInterval(refreshRoomsCount, 2000);

        (function() {
            var searchInput = document.getElementById('adminSearchInput');
            var tbody = document.getElementById('usersTableBody');
            var tallyList = document.getElementById('studentTallyList');
            if (!searchInput) return;

            searchInput.addEventListener('input', function() {
                var q = this.value.toLowerCase().trim();

                if (tbody) {
                    tbody.querySelectorAll('tr').forEach(function(row) {
                        var text = row.textContent.toLowerCase();
                        row.style.display = (q === '' || text.indexOf(q) !== -1) ? '' : 'none';
                    });
                }

                if (tallyList) {
                    tallyList.querySelectorAll('.tally-item').forEach(function(item) {
                        var text = item.textContent.toLowerCase();
                        item.style.display = (q === '' || text.indexOf(q) !== -1) ? 'flex' : 'none';
                    });
                }
            });
        })();
    </script>
</body>
</html>