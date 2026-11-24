<?php
if (session_id() === '') {
    @session_start();
}
include 'db.php';

if (!isset($_SESSION['user_id']) || strtolower((string)($_SESSION['role'] ?? '')) !== 'admin') {
    header("Location: login.php");
    exit();
}

$admin_id = intval($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
$u_pic_q = @$conn->query("SELECT profile_pic FROM users WHERE id = {$admin_id} LIMIT 1");
$u_pic_row = ($u_pic_q && $u_pic_q->num_rows > 0) ? $u_pic_q->fetch_assoc() : [];
$avatar_pic = trim((string)($u_pic_row['profile_pic'] ?? $_SESSION['profile_pic'] ?? ''));
$has_avatar_pic = ($avatar_pic !== '' && file_exists(__DIR__ . '/' . $avatar_pic));

$msg = "";
$error = "";


$conn->query("CREATE TABLE IF NOT EXISTS room_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NULL,
    room_name VARCHAR(100),
    day_of_week VARCHAR(30),
    program_code VARCHAR(30) DEFAULT 'BSIT',
    year_level VARCHAR(30) DEFAULT '1st Year',
    subject_name VARCHAR(120),
    block_name VARCHAR(60),
    subject_block VARCHAR(180),
    time_slot VARCHAR(100),
    session_mode VARCHAR(30) DEFAULT 'Face to Face'
)");

function ensureColumn(mysqli $conn, string $table, string $column, string $definition): void {
    $safe_col = $conn->real_escape_string($column);
    $chk = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$safe_col}'");
    if ($chk && $chk->num_rows === 0) {
        $conn->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }
}

ensureColumn($conn, 'room_schedules', 'teacher_id', 'INT NULL AFTER id');
ensureColumn($conn, 'room_schedules', 'program_code', "VARCHAR(30) DEFAULT 'BSIT' AFTER day_of_week");
ensureColumn($conn, 'room_schedules', 'year_level', "VARCHAR(30) DEFAULT '1st Year' AFTER program_code");
ensureColumn($conn, 'room_schedules', 'subject_name', 'VARCHAR(120) NULL AFTER year_level');
ensureColumn($conn, 'room_schedules', 'block_name', 'VARCHAR(60) NULL AFTER subject_name');
ensureColumn($conn, 'room_schedules', 'subject_block', 'VARCHAR(180) NULL AFTER block_name');
ensureColumn($conn, 'room_schedules', 'session_mode', "VARCHAR(30) DEFAULT 'Face to Face'");

function normalizeYearLabel(string $rawYear): string {
    $y = strtolower(trim($rawYear));
    if ($y === '' || $y === 'all') {
        return '1st Year';
    }
    if (strpos($y, '1') !== false || strpos($y, 'first') !== false) {
        return '1st Year';
    }
    if (strpos($y, '2') !== false || strpos($y, 'second') !== false) {
        return '2nd Year';
    }
    if (strpos($y, '3') !== false || strpos($y, 'third') !== false) {
        return '3rd Year';
    }
    if (strpos($y, '4') !== false || strpos($y, 'fourth') !== false) {
        return '4th Year';
    }
    return trim($rawYear);
}

$backfill_q = $conn->query("SELECT id, subject_name, block_name, subject_block FROM room_schedules WHERE (subject_name IS NULL OR subject_name = '') AND subject_block IS NOT NULL AND subject_block != ''");
if ($backfill_q) {
    while ($br = $backfill_q->fetch_assoc()) {
        $bid = (int)$br['id'];
        $sb = trim((string)$br['subject_block']);
        $sub_val = $sb;
        $blk_val = '';
        if (preg_match('/^(.*?)\s*[-–—|]\s*(?:Block\s*)?([A-Za-z0-9\s]+)$/i', $sb, $m)) {
            $sub_val = trim($m[1]);
            if ($blk_val === '') {
                $blk_val = trim($m[2]);
            }
        }
        $e_sub = $conn->real_escape_string($sub_val);
        $e_blk = $conn->real_escape_string($blk_val);
        $conn->query("UPDATE room_schedules SET subject_name = '{$e_sub}', block_name = '{$e_blk}' WHERE id = {$bid}");
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_schedule'])) {
    $teacher_id   = isset($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : 0;
    $room_name    = trim((string)($_POST['room_name'] ?? ''));
    $day_of_week  = trim((string)($_POST['day_of_week'] ?? ''));
    $program_code = strtoupper(trim((string)($_POST['program_code'] ?? 'BSIT')));
    $year_level   = normalizeYearLabel((string)($_POST['year_level'] ?? '1st Year'));
    $subject_name = trim((string)($_POST['subject_name'] ?? ''));
    $block_name   = trim((string)($_POST['block_name'] ?? ''));
    $time_slot    = trim((string)($_POST['time_slot'] ?? ''));
    $session_mode = trim((string)($_POST['session_mode'] ?? 'Face to Face'));
    if ($session_mode === '') {
        $session_mode = 'Face to Face';
    }

    if ($teacher_id > 0 && $room_name !== '' && $program_code !== '' && $year_level !== '' && $subject_name !== '' && $block_name !== '' && $time_slot !== '') {
        $clean_blk = preg_replace('/^block\s+/i', '', $block_name);
        $combined_sb = $subject_name . ' - ' . $program_code . ' ' . $year_level . ' - Block ' . $clean_blk;

        $safe_room = $conn->real_escape_string($room_name);
        $safe_day  = $conn->real_escape_string($day_of_week);
        $safe_prog = $conn->real_escape_string($program_code);
        $safe_year = $conn->real_escape_string($year_level);
        $safe_sub  = $conn->real_escape_string($subject_name);
        $safe_blk  = $conn->real_escape_string($clean_blk);
        $safe_sb   = $conn->real_escape_string($combined_sb);
        $safe_time = $conn->real_escape_string($time_slot);
        $safe_mode = $conn->real_escape_string($session_mode);

        
        $check_conflict = $conn->query("SELECT id FROM room_schedules WHERE room_name='$safe_room' AND day_of_week='$safe_day' AND time_slot='$safe_time'");

        if ($check_conflict && $check_conflict->num_rows > 0) {
            $error = "Conflict Error! Ang <strong>" . htmlspecialchars($room_name) . "</strong> every time <strong>" . htmlspecialchars($day_of_week) . "</strong> at the time that <strong>" . htmlspecialchars($time_slot) . "</strong>is already reserved and being used by another subject.";
        } else {
            $sql = "INSERT INTO room_schedules (teacher_id, room_name, day_of_week, program_code, year_level, subject_name, block_name, subject_block, time_slot, session_mode) 
                    VALUES ($teacher_id, '$safe_room', '$safe_day', '$safe_prog', '$safe_year', '$safe_sub', '$safe_blk', '$safe_sb', '$safe_time', '$safe_mode')";

            if ($conn->query($sql) === TRUE) {
                $msg = "Successfully added! Has been saved <strong>" . htmlspecialchars($subject_name) . " (" . htmlspecialchars($program_code) . " &bull; " . htmlspecialchars($year_level) . " &bull; Block " . htmlspecialchars($clean_blk) . ")</strong> sa <strong>" . htmlspecialchars($room_name) . "</strong>.";
            } else {
                $error = "Database Error: " . htmlspecialchars($conn->error);
            }
        }
    } else {
        $error = "Please select the Teacher, Program, and Year, and fill in all fields before saving..";
    }
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_schedule'])) {
    $sched_id     = isset($_POST['schedule_id']) ? (int)$_POST['schedule_id'] : 0;
    $teacher_id   = isset($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : 0;
    $room_name    = trim((string)($_POST['room_name'] ?? ''));
    $day_of_week  = trim((string)($_POST['day_of_week'] ?? ''));
    $program_code = strtoupper(trim((string)($_POST['program_code'] ?? 'BSIT')));
    $year_level   = normalizeYearLabel((string)($_POST['year_level'] ?? '1st Year'));
    $subject_name = trim((string)($_POST['subject_name'] ?? ''));
    $block_name   = trim((string)($_POST['block_name'] ?? ''));
    $time_slot    = trim((string)($_POST['time_slot'] ?? ''));
    $session_mode = trim((string)($_POST['session_mode'] ?? 'Face to Face'));
    if ($session_mode === '') {
        $session_mode = 'Face to Face';
    }

    if ($sched_id > 0 && $teacher_id > 0 && $room_name !== '' && $program_code !== '' && $year_level !== '' && $subject_name !== '' && $block_name !== '' && $time_slot !== '') {
        $clean_blk = preg_replace('/^block\s+/i', '', $block_name);
        $combined_sb = $subject_name . ' - ' . $program_code . ' ' . $year_level . ' - Block ' . $clean_blk;

        $safe_room = $conn->real_escape_string($room_name);
        $safe_day  = $conn->real_escape_string($day_of_week);
        $safe_prog = $conn->real_escape_string($program_code);
        $safe_year = $conn->real_escape_string($year_level);
        $safe_sub  = $conn->real_escape_string($subject_name);
        $safe_blk  = $conn->real_escape_string($clean_blk);
        $safe_sb   = $conn->real_escape_string($combined_sb);
        $safe_time = $conn->real_escape_string($time_slot);
        $safe_mode = $conn->real_escape_string($session_mode);

        $check_conflict = $conn->query("SELECT id FROM room_schedules WHERE room_name='$safe_room' AND day_of_week='$safe_day' AND time_slot='$safe_time' AND id != $sched_id");

        if ($check_conflict && $check_conflict->num_rows > 0) {
            $error = "Conflict Error! The <strong>" . htmlspecialchars($room_name) . "</strong> every time <strong>" . htmlspecialchars($day_of_week) . "</strong> at the time that <strong>" . htmlspecialchars($time_slot) . "</strong>already reserved for another schedule.";
        } else {
            $sql_upd = "UPDATE room_schedules 
                        SET teacher_id = $teacher_id,
                            room_name = '$safe_room',
                            day_of_week = '$safe_day',
                            program_code = '$safe_prog',
                            year_level = '$safe_year',
                            subject_name = '$safe_sub',
                            block_name = '$safe_blk',
                            subject_block = '$safe_sb',
                            time_slot = '$safe_time',
                            session_mode = '$safe_mode'
                        WHERE id = $sched_id LIMIT 1";

            if ($conn->query($sql_upd) === TRUE) {
                $msg = "Successfully updated! Has been updated <strong>" . htmlspecialchars($subject_name) . " (" . htmlspecialchars($program_code) . " &bull; " . htmlspecialchars($year_level) . " &bull; Block " . htmlspecialchars($clean_blk) . ")</strong> in <strong>" . htmlspecialchars($room_name) . "</strong>.";
            } else {
                $error = "Database Error: " . htmlspecialchars($conn->error);
            }
        }
    } else {
        $error = "Please complete all fields before updating.";
    }
}

if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    if ($del_id > 0) {
        $conn->query("DELETE FROM room_schedules WHERE id = $del_id");
        header("Location: admin_rooms.php?deleted=1");
        exit();
    }
}

if (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
    $msg = "Successfully deleted! Nabura na ang schedule.";
}


$teachers_map = [];
$teachers_list = [];
$t_query = $conn->query("SELECT * FROM users WHERE LOWER(role) = 'teacher' ORDER BY id ASC");
if ($t_query) {
    while ($tr = $t_query->fetch_assoc()) {
        $tid = (int)$tr['id'];
        $tlabel = trim((string)($tr['full_name'] ?? $tr['name'] ?? $tr['username'] ?? 'Teacher'));
        $dept = trim((string)($tr['department'] ?? ''));
        $display = $tlabel . ($dept !== '' ? " ({$dept})" : "");
        $teachers_map[$tid] = $tlabel;
        $teachers_list[] = [
            'id' => $tid,
            'display' => $display
        ];
    }
}

$rooms_query = $conn->query("SELECT DISTINCT room_name FROM room_schedules WHERE room_name IS NOT NULL AND room_name != '' ORDER BY room_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCDI Learning Portal - Room & Schedule</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .room-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .room-card {
            background-color: #ffffff;
            border-radius: 18px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            border-top: 6px solid #1e6091;
        }
        .room-card h3 {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 12px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 8px;
        }
        .sched-item {
            background-color: #1e6091;
            color: #ffffff;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }
        .sched-info strong {
            display: block;
            font-size: 15px;
        }
        .sched-info span {
            display: block;
            font-size: 13px;
            opacity: 0.92;
        }
        .sched-teacher-tag {
            display: inline-block;
            margin-top: 5px;
            background: rgba(255, 255, 255, 0.16);
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
        }
        .sched-actions {
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex-shrink: 0;
        }
        .update-btn {
            background: #f59e0b;
            color: #0f172a;
            border: none;
            padding: 6px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }
        .update-btn:hover {
            background: #d97706;
            color: #ffffff;
        }
        .delete-btn {
            background: #ef4444;
            color: white;
            border: none;
            padding: 6px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
        }
        .delete-btn:hover {
            background: #dc2626;
        }
        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 16px;
        }
        .modal-box {
            background: #ffffff;
            padding: 24px 28px;
            border-radius: 18px;
            width: 100%;
            max-width: 440px;
            max-height: 92vh;
            overflow-y: auto;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        .modal-box label {
            display: block;
            font-weight: 700;
            margin-bottom: 6px;
            margin-top: 12px;
            font-size: 14px;
        }
        .modal-box input, .modal-box select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 14px;
        }
    </style>
</head>
<body>

    <header class="topbar" style="background-color: #0b2545;">
        <div class="brand">
            <div class="brand-logo"><img src="logo.jpg" alt="Logo" style="width:100%; height:100%; object-fit:cover;"></div>
            <span>CCDI Admin Portal</span>
        </div>
        <input type="text" class="search-bar" placeholder="Search">
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
        <a href="admin_dashboard.php">Admin Overview</a>
        <a href="admin_courses.php">Manage Courses</a>
        <a href="admin_students.php">Manage Students</a>
        <a href="admin_faculty.php">Manage Faculty</a>
        <a href="admin_program.php">Manage Programs</a>
        <a href="admin_rooms.php" class="active">Manage Rooms</a>
        <a href="admin_settings.php">System Settings</a>
    </nav>

    <main class="main-wrapper">
        <div class="page-header" style="display:flex; justify-content:space-between; align-items:center;">
            <h1 style="font-size: 32px; font-weight: 800;">ROOMS</h1>
            <button type="button" onclick="document.getElementById('addRoomModal').style.display='flex'" style="background:#1e6091; color:white; border:none; padding:12px 24px; border-radius:20px; font-weight:700; cursor:pointer; font-size:15px;">
                + Add Room Schedule
            </button>
        </div>

        <!-- Notification Alerts -->
        <?php if ($msg): ?>
            <div style="background:#dcfce7; color:#166534; padding:14px 20px; border-radius:12px; margin-bottom:20px; border-left: 6px solid #16a34a; font-size:15px;">
                &#9989; <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div style="background:#fee2e2; color:#991b1b; padding:14px 20px; border-radius:12px; margin-bottom:20px; border-left: 6px solid #dc2626; font-size:15px;">
                &#10060; <?php echo $error; ?>
            </div>
        <?php endif; ?>

        
        <div class="room-cards-grid">
            <?php if ($rooms_query && $rooms_query->num_rows > 0): ?>
                <?php while ($rm = $rooms_query->fetch_assoc()):
                    $current_room = $rm['room_name'];
                    $safe_room = $conn->real_escape_string($current_room);
                    $scheds = $conn->query("SELECT * FROM room_schedules WHERE room_name='$safe_room' ORDER BY id DESC");
                ?>
                    <div class="room-card">
                        <h3><?php echo htmlspecialchars($current_room); ?></h3>
                        <?php while ($sc = $scheds->fetch_assoc()):
                            $sc_id   = (int)$sc['id'];
                            $sc_tid  = (int)($sc['teacher_id'] ?? 0);
                            $sc_tname = $teachers_map[$sc_tid] ?? '';
                            $sc_prog = strtoupper(trim((string)($sc['program_code'] ?? 'BSIT')));
                            $sc_year = normalizeYearLabel((string)($sc['year_level'] ?? '1st Year'));
                            $sc_sub  = trim((string)($sc['subject_name'] ?? ''));
                            $sc_blk  = trim((string)($sc['block_name'] ?? ''));
                            $sc_sb_raw = trim((string)($sc['subject_block'] ?? ''));

                            if ($sc_sub === '' && $sc_sb_raw !== '') {
                                if (preg_match('/^(.*?)\s*[-–—|]\s*(?:Block\s*)?([A-Za-z0-9\s]+)$/i', $sc_sb_raw, $m)) {
                                    $sc_sub = trim($m[1]);
                                    if ($sc_blk === '') {
                                        $sc_blk = trim($m[2]);
                                    }
                                } else {
                                    $sc_sub = $sc_sb_raw;
                                }
                            }
                            if ($sc_sub === '') {
                                $sc_sub = 'Subject';
                            }

                            $clean_sc_blk = preg_replace('/^block\s+/i', '', $sc_blk);
                            $display_sub_blk = $clean_sc_blk !== ''
                                ? ($sc_sub . ' - Block ' . $clean_sc_blk)
                                : ($sc_sb_raw !== '' ? $sc_sb_raw : $sc_sub);

                            $sc_mode = trim((string)($sc['session_mode'] ?? 'Face to Face'));
                            if ($sc_mode === '') {
                                $sc_mode = 'Face to Face';
                            }
                        ?>
                            <div class="sched-item">
                                <div class="sched-info">
                                    <strong><?php echo htmlspecialchars($display_sub_blk); ?></strong>
                                    <span><?php echo htmlspecialchars($sc_prog . ' • ' . $sc_year); ?></span>
                                    <span><?php echo htmlspecialchars($sc['day_of_week']); ?> &bull; <?php echo htmlspecialchars($sc['time_slot']); ?></span>
                                    <?php if ($sc_tname !== ''): ?>
                                        <span class="sched-teacher-tag">Teacher: <?php echo htmlspecialchars($sc_tname); ?> &bull; <?php echo htmlspecialchars($sc_mode); ?></span>
                                    <?php else: ?>
                                        <span class="sched-teacher-tag"><?php echo htmlspecialchars($sc_mode); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="sched-actions">
                                    <button
                                        type="button"
                                        class="update-btn js-open-update-modal"
                                        data-id="<?php echo $sc_id; ?>"
                                        data-teacher-id="<?php echo $sc_tid; ?>"
                                        data-room="<?php echo htmlspecialchars($current_room, ENT_QUOTES); ?>"
                                        data-day="<?php echo htmlspecialchars((string)$sc['day_of_week'], ENT_QUOTES); ?>"
                                        data-program="<?php echo htmlspecialchars($sc_prog, ENT_QUOTES); ?>"
                                        data-year="<?php echo htmlspecialchars($sc_year, ENT_QUOTES); ?>"
                                        data-subject="<?php echo htmlspecialchars($sc_sub, ENT_QUOTES); ?>"
                                        data-block="<?php echo htmlspecialchars($clean_sc_blk, ENT_QUOTES); ?>"
                                        data-time="<?php echo htmlspecialchars((string)$sc['time_slot'], ENT_QUOTES); ?>"
                                        data-mode="<?php echo htmlspecialchars($sc_mode, ENT_QUOTES); ?>"
                                    >
                                        Update
                                    </button>
                                    <a href="admin_rooms.php?delete_id=<?php echo $sc_id; ?>" class="delete-btn" onclick="return confirm('Sigurado ka bang gusto mong burahin ang sched na ito?');">Delete</a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="panel-card" style="grid-column: 1 / -1; text-align:center; padding:40px;">
                    <p style="color:#64748b; font-size:16px; font-weight:600;">No room schedule has been saved yet. Click the<strong>+ Add Room Schedule</strong> at the top to add.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

   
    <div id="addRoomModal" class="modal-overlay">
        <div class="modal-box">
            <h2 style="font-size:22px; font-weight:800; margin-bottom:10px;">Add Room Schedule</h2>
            <form method="POST">
                <label>Teacher</label>
                <select name="teacher_id" required>
                    <option value="">-- Choose Teacher --</option>
                    <?php foreach ($teachers_list as $tl): ?>
                        <option value="<?php echo $tl['id']; ?>"><?php echo htmlspecialchars($tl['display']); ?></option>
                    <?php endforeach; ?>
                </select>

                <label>Room Name</label>
                <input type="text" name="room_name" placeholder="hal. ROOM 101 o LAB 1" required>

                <label>Day of Week</label>
                <select name="day_of_week" required>
                    <option value="Monday">Monday</option>
                    <option value="Tuesday">Tuesday</option>
                    <option value="Wednesday">Wednesday</option>
                    <option value="Thursday">Thursday</option>
                    <option value="Friday">Friday</option>
                    <option value="Saturday">Saturday</option>
                </select>

                <label>Program</label>
                <select name="program_code" required>
                    <option value="BSIT">BSIT</option>
                    <option value="BSCS">BSCS</option>
                    <option value="BSIS">BSIS</option>
                    <option value="BSOAD">BSOAD</option>
                </select>

                <label>Year Level</label>
                <select name="year_level" required>
                    <option value="1st Year">1st Year</option>
                    <option value="2nd Year">2nd Year</option>
                    <option value="3rd Year">3rd Year</option>
                    <option value="4th Year">4th Year</option>
                </select>

                <label>Subject</label>
                <input type="text" name="subject_name" placeholder="ex. Software Engineering" required>

                <label>Block</label>
                <input type="text" name="block_name" placeholder="ex. 3A or 3B" required>

                <label>Time Slot</label>
                <input type="text" name="time_slot" placeholder="ex. 7:00 AM - 10:00 AM" required>

                <label>Class Setup / Mode</label>
                <select name="session_mode" required>
                    <option value="Face to Face">Face to Face</option>
                    <option value="Online">Online</option>
                </select>

                <div style="display:flex; gap:10px; margin-top:20px;">
                    <button type="submit" name="add_schedule" style="flex:1; background:#16a34a; color:white; border:none; padding:12px; border-radius:10px; font-weight:700; cursor:pointer;">Save Schedule</button>
                    <button type="button" onclick="document.getElementById('addRoomModal').style.display='none'" style="flex:1; background:#e2e8f0; color:#0f172a; border:none; padding:12px; border-radius:10px; font-weight:700; cursor:pointer;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    
    <div id="updateRoomModal" class="modal-overlay">
        <div class="modal-box">
            <h2 style="font-size:22px; font-weight:800; margin-bottom:10px;">Update Room Schedule</h2>
            <form method="POST">
                <input type="hidden" name="schedule_id" id="upd_schedule_id">

                <label>Teacher</label>
                <select name="teacher_id" id="upd_teacher_id" required>
                    <option value="">-- Choose Teacher --</option>
                    <?php foreach ($teachers_list as $tl): ?>
                        <option value="<?php echo $tl['id']; ?>"><?php echo htmlspecialchars($tl['display']); ?></option>
                    <?php endforeach; ?>
                </select>

                <label>Room Name</label>
                <input type="text" name="room_name" id="upd_room_name" required>

                <label>Day of Week</label>
                <select name="day_of_week" id="upd_day_of_week" required>
                    <option value="Monday">Monday</option>
                    <option value="Tuesday">Tuesday</option>
                    <option value="Wednesday">Wednesday</option>
                    <option value="Thursday">Thursday</option>
                    <option value="Friday">Friday</option>
                    <option value="Saturday">Saturday</option>
                </select>

                <label>Program</label>
                <select name="program_code" id="upd_program_code" required>
                    <option value="BSIT">BSIT</option>
                    <option value="BSCS">BSCS</option>
                    <option value="BSIS">BSIS</option>
                    <option value="BSOAD">BSOAD</option>
                </select>

                <label>Year Level</label>
                <select name="year_level" id="upd_year_level" required>
                    <option value="1st Year">1st Year</option>
                    <option value="2nd Year">2nd Year</option>
                    <option value="3rd Year">3rd Year</option>
                    <option value="4th Year">4th Year</option>
                </select>

                <label>Subject</label>
                <input type="text" name="subject_name" id="upd_subject_name" required>

                <label>Block</label>
                <input type="text" name="block_name" id="upd_block_name" required>

                <label>Time Slot</label>
                <input type="text" name="time_slot" id="upd_time_slot" required>

                <label>Class Setup / Mode</label>
                <select name="session_mode" id="upd_session_mode" required>
                    <option value="Face to Face">Face to Face</option>
                    <option value="Online">Online</option>
                </select>

                <div style="display:flex; gap:10px; margin-top:20px;">
                    <button type="submit" name="update_schedule" style="flex:1; background:#1e6091; color:white; border:none; padding:12px; border-radius:10px; font-weight:700; cursor:pointer;">Save Changes</button>
                    <button type="button" onclick="document.getElementById('updateRoomModal').style.display='none'" style="flex:1; background:#e2e8f0; color:#0f172a; border:none; padding:12px; border-radius:10px; font-weight:700; cursor:pointer;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function() {
            var updModal = document.getElementById('updateRoomModal');
            document.querySelectorAll('.js-open-update-modal').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    document.getElementById('upd_schedule_id').value = this.getAttribute('data-id') || '';
                    document.getElementById('upd_teacher_id').value = this.getAttribute('data-teacher-id') || '';
                    document.getElementById('upd_room_name').value = this.getAttribute('data-room') || '';
                    document.getElementById('upd_day_of_week').value = this.getAttribute('data-day') || 'Monday';
                    document.getElementById('upd_program_code').value = this.getAttribute('data-program') || 'BSIT';
                    document.getElementById('upd_year_level').value = this.getAttribute('data-year') || '1st Year';
                    document.getElementById('upd_subject_name').value = this.getAttribute('data-subject') || '';
                    document.getElementById('upd_block_name').value = this.getAttribute('data-block') || '';
                    document.getElementById('upd_time_slot').value = this.getAttribute('data-time') || '';
                    document.getElementById('upd_session_mode').value = this.getAttribute('data-mode') || 'Face to Face';
                    if (updModal) {
                        updModal.style.display = 'flex';
                    }
                });
            });
        })();
    </script>

</body>
</html>