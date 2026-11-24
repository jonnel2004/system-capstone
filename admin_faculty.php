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
$err = "";

$conn->query("CREATE TABLE IF NOT EXISTS teacher_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT,
    subject_name VARCHAR(100),
    course_code VARCHAR(20),
    block_name VARCHAR(20)
)");


$col_chk = $conn->query("SHOW COLUMNS FROM users LIKE 'department'");
if ($col_chk && $col_chk->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN department VARCHAR(50) DEFAULT 'BSIT'");
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['assign_subject'])) {
    $teacher_id = isset($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : 0;
    $subject_names = trim((string)($_POST['subject_name'] ?? ''));
    $course_code = trim((string)($_POST['course_code'] ?? ''));
    $block_name = trim((string)($_POST['block_name'] ?? ''));

    if ($teacher_id > 0 && $subject_names !== '') {
        $subs = preg_split('/[\r\n,]+/', $subject_names);
        $stmt_ins = $conn->prepare("INSERT INTO teacher_assignments (teacher_id, subject_name, course_code, block_name) VALUES (?, ?, ?, ?)");
        if ($stmt_ins) {
            foreach ($subs as $sub) {
                $sub = trim($sub);
                if ($sub !== '') {
                    $stmt_ins->bind_param("isss", $teacher_id, $sub, $course_code, $block_name);
                    $stmt_ins->execute();
                }
            }
            $stmt_ins->close();
            header("Location: admin_faculty.php?assigned=1");
            exit();
        } else {
            $err = "The thing was not prepared in insert query: " . htmlspecialchars($conn->error);
        }
    } else {
        $err = "Fill in the teacher and subject name.";
    }
}

// Kapag pinindot ang Remove button (POST)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['remove_subject_submit'])) {
    $del_id = isset($_POST['delete_assignment_id']) ? (int)$_POST['delete_assignment_id'] : 0;

    if ($del_id > 0) {
        $sql_del = "DELETE FROM `teacher_assignments` WHERE `id` = {$del_id} LIMIT 1";
        if ($conn->query($sql_del)) {
            if ($conn->affected_rows > 0) {
                header("Location: admin_faculty.php?deleted=1");
                exit();
            } else {
                $err = "No row deleted for the ID #{$del_id}. Check in phpMyAdmin what the value of `id` is in `teacher_assignments`.";
            }
        } else {
            $err = "Error sa DELETE query: " . htmlspecialchars($conn->error);
        }
    } else {
        $err = "Did not receive any valid ID (ID = {$del_id}).";
    }
}

if (isset($_GET['assigned']) && $_GET['assigned'] == '1') {
    $msg = "Successfully assigned the subjects to the teacher!";
}
if (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
    $msg = "The assignment for the subject has been deleted.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCDI Learning Portal - Faculty Management</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .department-section {
            margin-bottom: 35px;
        }
        .department-title {
            font-size: 20px;
            font-weight: 800;
            color: #1e6091;
            border-bottom: 3px solid #1e6091;
            padding-bottom: 8px;
            margin-bottom: 16px;
            text-transform: uppercase;
        }
        .faculty-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }
        .faculty-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-top: 6px solid #1e6091;
        }
        .faculty-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .faculty-card h3 {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 4px 0;
        }
        .dept-badge {
            background: #e0f2fe;
            color: #0369a1;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .subject-item {
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            gap: 10px;
        }
        .subject-item:last-child {
            border-bottom: none;
        }
        .trash-btn {
            background: #fee2e2;
            color: #dc2626;
            border: none;
            padding: 6px 10px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            position: relative;
            z-index: 5;
        }
        .trash-btn:hover {
            background: #dc2626;
            color: white;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .modal-box {
            background: #ffffff;
            padding: 24px;
            border-radius: 16px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        .modal-box label {
            display: block;
            font-weight: 700;
            margin-bottom: 5px;
            margin-top: 10px;
            font-size: 13px;
        }
        .modal-box input, .modal-box select, .modal-box textarea {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13px;
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
        <a href="admin_faculty.php" class="active">Manage Faculty</a>
        <a href="admin_program.php">Manage Programs</a>
        <a href="admin_rooms.php">Manage Rooms</a>
        <a href="admin_settings.php">System Settings</a>
    </nav>

    <main class="main-wrapper">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 25px;">
            <h1 style="font-size: 28px; font-weight: 800;">Faculty &amp; Department Assignments</h1>
            <button type="button" onclick="document.getElementById('assignModal').style.display='flex'" style="background:#1e6091; color:white; border:none; padding:10px 18px; border-radius:14px; font-weight:700; cursor:pointer; font-size:13px;">
                + Assign Subject to Teacher
            </button>
        </div>

        <?php if (!empty($msg)): ?>
            <div style="background:#dcfce7; color:#166534; padding:10px 16px; border-radius:10px; margin-bottom:16px; border-left:5px solid #16a34a; font-size:13px; font-weight:700;">
                &#9989; <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($err)): ?>
            <div style="background:#fee2e2; color:#991b1b; padding:10px 16px; border-radius:10px; margin-bottom:16px; border-left:5px solid #dc2626; font-size:13px; font-weight:700;">
                &#9888; <?php echo htmlspecialchars($err); ?>
            </div>
        <?php endif; ?>

        <?php
        $departments = ['BSIT', 'BSCS', 'BSIS', 'BSOAD'];

        foreach ($departments as $dept):
            $safe_dept = $conn->real_escape_string($dept);
        ?>
            <div class="department-section">
                <div class="department-title"><?php echo htmlspecialchars($dept); ?> Department</div>

                <div class="faculty-grid">
                    <?php
                    $teachers = $conn->query("SELECT * FROM users WHERE LOWER(role) = 'teacher' AND UPPER(department) LIKE '%{$safe_dept}%'");
                    if ($teachers && $teachers->num_rows > 0):
                        while ($t = $teachers->fetch_assoc()):
                            $t_id = (int)$t['id'];
                    ?>
                        <div class="faculty-card">
                            <div class="faculty-header">
                                <div>
                                    <h3><?php echo htmlspecialchars($t['username']); ?></h3>
                                    <span class="dept-badge"><?php echo htmlspecialchars($t['department']); ?></span>
                                </div>
                            </div>

                            <p style="font-size: 11px; color: #64748b; margin-bottom: 8px; text-transform: uppercase; font-weight: 700;">Assigned Subject:</p>

                            <div>
                                <?php
                                $assignments = $conn->query("SELECT * FROM teacher_assignments WHERE teacher_id = {$t_id} ORDER BY id ASC");
                                if ($assignments && $assignments->num_rows > 0):
                                    while ($asg = $assignments->fetch_assoc()):
                                        $asg_id = (int)$asg['id'];
                                ?>
                                    <div class="subject-item">
                                        <div>
                                            <strong><?php echo htmlspecialchars($asg['subject_name']); ?></strong><br>
                                            <span style="color: #1e6091; font-weight: 600; font-size: 12px;">
                                                <?php echo htmlspecialchars($asg['course_code'] . ' - Block ' . $asg['block_name']); ?>
                                            </span>
                                        </div>
                                        <form method="POST" action="admin_faculty.php" style="margin:0; display:inline;">
                                            <input type="hidden" name="delete_assignment_id" value="<?php echo $asg_id; ?>">
                                            <button type="submit" name="remove_subject_submit" value="1" class="trash-btn" title="Remove">
                                                &#128465; Remove
                                            </button>
                                        </form>
                                    </div>
                                <?php
                                    endwhile;
                                else:
                                ?>
                                    <p style="font-size: 12px; color: #94a3b8; font-style: italic; padding: 6px 0;">No teacher has been assigned to the subject yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php
                        endwhile;
                    else:
                    ?>
                        <p style="color: #94a3b8; font-size: 13px; font-style: italic; grid-column: 1 / -1;">No teacher in <?php echo htmlspecialchars($dept); ?> department.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </main>

    <!-- Modal para sa Pag-assign ng Subject -->
    <div id="assignModal" class="modal-overlay">
        <div class="modal-box">
            <h2 style="font-size:18px; font-weight:800; margin-bottom:10px;">Assign Subject to Teacher</h2>
            <form method="POST" action="admin_faculty.php">
                <label>Choose Teacher</label>
                <select name="teacher_id" required>
                    <option value="">Choose teacher</option>
                    <?php
                    $t_list = $conn->query("SELECT * FROM users WHERE LOWER(role) = 'teacher'");
                    if ($t_list):
                        while ($tl = $t_list->fetch_assoc()):
                    ?>
                        <option value="<?php echo (int)$tl['id']; ?>">
                            <?php echo htmlspecialchars($tl['username'] . ' (' . $tl['department'] . ')'); ?>
                        </option>
                    <?php
                        endwhile;
                    endif;
                    ?>
                </select>

                <label>Name of Subject (can be a single subject or multiple subjects)</label>
                <textarea name="subject_name" rows="3" placeholder="hal. Software Engineering, Networking" required style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px;"></textarea>

                <label>Kurso</label>
                <select name="course_code" required>
                    <option value="BSIT">BSIT</option>
                    <option value="BSCS">BSCS</option>
                    <option value="BSIS">BSIS</option>
                    <option value="BSOAD">BSOAD</option>
                </select>

                <label>Block (hal. 1A, 3B)</label>
                <input type="text" name="block_name" placeholder="hal. 1A" required>

                <div style="display:flex; gap:10px; margin-top:16px;">
                    <button type="submit" name="assign_subject" style="flex:1; background:#16a34a; color:white; border:none; padding:10px; border-radius:8px; font-weight:700; cursor:pointer; font-size:13px;">Save Assignment</button>
                    <button type="button" onclick="document.getElementById('assignModal').style.display='none'" style="flex:1; background:#e2e8f0; color:#0f172a; border:none; padding:10px; border-radius:8px; font-weight:700; cursor:pointer; font-size:13px;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>