<?php
if (session_id() === '') {
    @session_start();
}
include 'db.php';

// Security check: only Admin can enter this page.
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

$user_cols = [];
$col_res = $conn->query("SHOW COLUMNS FROM users");
if ($col_res) {
    while ($c = $col_res->fetch_assoc()) {
        $user_cols[] = $c['Field'];
    }
}
$stu_id_col = '';
foreach (['student_id', 'student_number', 'student_no', 'id_number', 'school_id'] as $cand) {
    if (in_array($cand, $user_cols, true)) {
        $stu_id_col = $cand;
        break;
    }
}
$prog_col = in_array('program', $user_cols, true) ? 'program' : (in_array('course', $user_cols, true) ? 'course' : '');

$msg = "";
$err = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_student'])) {
    $edit_id = intval($_POST['edit_student_id'] ?? 0);
    $new_name = trim($_POST['edit_student_name'] ?? '');
    $new_email = trim($_POST['edit_student_email'] ?? '');
    $new_prog = trim($_POST['edit_student_program'] ?? '');
    $new_block = trim($_POST['edit_student_block'] ?? '');
    $new_sid = trim($_POST['edit_student_code'] ?? '');

    if ($edit_id <= 0 || $new_name === '') {
        $err = "Student must have a name.";
    } elseif ($stu_id_col !== '' && $new_sid !== '') {
        $esc_sid = $conn->real_escape_string($new_sid);
        $dup_q = $conn->query("SELECT id FROM `users` 
                               WHERE LOWER(TRIM(`{$stu_id_col}`)) = LOWER(TRIM('{$esc_sid}')) 
                               AND `id` <> {$edit_id} 
                               LIMIT 1");
        if ($dup_q && $dup_q->num_rows > 0) {
            $err = "Student IDs cannot be duplicated. '{$new_sid}'. That has already been assigned to another student..";
        }
    }

    if ($err === '') {
        $sets = [];
        if (in_array('full_name', $user_cols, true)) {
            $sets[] = "`full_name` = '" . $conn->real_escape_string($new_name) . "'";
        }
        if (in_array('username', $user_cols, true)) {
            $sets[] = "`username` = '" . $conn->real_escape_string($new_name) . "'";
        }
        if (in_array('email', $user_cols, true)) {
            $sets[] = "`email` = '" . $conn->real_escape_string($new_email) . "'";
        }
        if ($prog_col !== '') {
            $sets[] = "`{$prog_col}` = '" . $conn->real_escape_string($new_prog) . "'";
        }
        if (in_array('block', $user_cols, true)) {
            $sets[] = "`block` = '" . $conn->real_escape_string($new_block) . "'";
        }
        if ($stu_id_col !== '') {
            $sid_sql = ($new_sid !== '') ? "'" . $conn->real_escape_string($new_sid) . "'" : "NULL";
            $sets[] = "`{$stu_id_col}` = {$sid_sql}";
        }

        if (!empty($sets)) {
            $sql_upd = "UPDATE `users` SET " . implode(", ", $sets) . " WHERE `id` = {$edit_id} AND LOWER(TRIM(`role`)) = 'student' LIMIT 1";
            try {
                if ($conn->query($sql_upd)) {
                    $msg = "The student's information has been updated..";
                } else {
                    $err = "The student was not updated.: " . $conn->error;
                }
            } catch (mysqli_sql_exception $e) {
                if ((int)$e->getCode() === 1062 || stripos($e->getMessage(), 'Duplicate entry') !== false) {
                    $err = "Student IDs cannot be duplicated. '{$new_sid}'. That has already been assigned to another student..";
                } else {
                    $err = "Database error: " . $e->getMessage();
                }
            }
        }
    }
}


if (isset($_GET['delete_student_id'])) {
    $del_id = intval($_GET['delete_student_id']);
    if ($del_id > 0) {
        if ($conn->query("DELETE FROM `users` WHERE `id` = {$del_id} AND LOWER(TRIM(`role`)) = 'student' LIMIT 1")) {
            $msg = "Nabura na ang student account.";
        } else {
            $err = "Hindi nabura ang student: " . $conn->error;
        }
    }
}

function formatProgramOnly($raw_text) {
    $text = trim((string)$raw_text);

   
    $text = preg_replace('/\b4[\s\-]*yr\.?\b/i', '', $text);
    $text = preg_replace('/\b(1st|2nd|3rd|4th|first|second|third|fourth)\s*(yr\.?|year)\b/i', '', $text);
    $text = preg_replace('/\band\s+soon\b/i', '', $text);

    $text = preg_replace('/\s*\([A-Za-z0-9\-\/]+\)/', '', $text);

    $replacements = [
        '/\bBachelor of Science in Information Technology\b/i' => 'BS Information Technology (BSIT)',
        '/\bBS Information Technology\b/i' => 'BS Information Technology (BSIT)',
        '/\bBachelor of Science in Computer Science\b/i' => 'BS Computer Science (BSCS)',
        '/\bBS Computer Science\b/i' => 'BS Computer Science (BSCS)',
        '/\bBachelor of Science in Office Administration\b/i' => 'BS Office Administration (BSOA)',
        '/\bBS Office Administration\b/i' => 'BS Office Administration (BSOA)',
        '/\bBS Information Systems\b/i' => 'BS Information Systems (BSIS)',
    ];

    foreach ($replacements as $pattern => $replacement) {
        $text = preg_replace($pattern, $replacement, $text);
    }

    $text = preg_replace('/(\([A-Za-z0-9]+\))(?:\s*\1)+/i', '$1', $text);

    
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text, " \t\n\r\0\x0B,-/");

    return $text;
}


$students = [];
$q_stu = $conn->query("SELECT * FROM users WHERE LOWER(TRIM(role)) = 'student' ORDER BY id ASC");
if ($q_stu && $q_stu->num_rows > 0) {
    while ($row = $q_stu->fetch_assoc()) {
        $raw_sid = ($stu_id_col !== '' && !empty(trim((string)($row[$stu_id_col] ?? ''))))
            ? trim((string)$row[$stu_id_col])
            : '';
        $row['raw_student_id'] = $raw_sid;
        $row['display_student_id'] = ($raw_sid !== '') ? $raw_sid : '—';
        $students[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCDI Admin Portal - Manage Students</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .stu-badge-no {
            background: #e0f2fe;
            color: #0369a1;
            font-weight: 800;
            padding: 3px 9px;
            border-radius: 6px;
            font-size: 12px;
            white-space: nowrap;
        }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
        th { background-color: #f8fafc; color: #334155; }
        .action-btns {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .act-btn {
            border: none;
            border-radius: 7px;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .act-edit {
            background: #e0f2fe;
            color: #0369a1;
        }
        .act-edit:hover {
            background: #bae6fd;
        }
        .act-delete {
            background: #fee2e2;
            color: #b91c1c;
        }
        .act-delete:hover {
            background: #fecaca;
        }
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .modal-box {
            background: #ffffff;
            padding: 22px 24px;
            border-radius: 14px;
            width: 100%;
            max-width: 430px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
        .modal-box label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #334155;
            margin-top: 10px;
            margin-bottom: 4px;
        }
        .modal-box input {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13px;
        }
    </style>
</head>
<body>

    <header class="topbar" style="background-color: #0b2545;">
        <div class="brand">
            <div class="brand-logo"><img src="logo.jpg" alt="logo" style="width:100%; height:100%; object-fit:contain;"></div>
            <span>CCDI Admin Portal</span>
        </div>
        <input type="text" id="studentSearchInput" class="search-bar" placeholder="Search">
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
        <a href="admin_students.php" class="active">Manage Students</a>
        <a href="admin_faculty.php">Manage Facultys</a>
        <a href="admin_program.php">Manage Programs</a>
        <a href="admin_rooms.php">Manage Rooms</a>
        <a href="admin_settings.php">System Settings</a>
    </nav>

    <main class="main-wrapper">
        <div class="page-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <div>
                <h1>Manage Students</h1>
                <p>Total Students: <strong><?php echo count($students); ?></strong></p>
            </div>
        </div>

        <?php if ($msg !== ''): ?>
            <div style="background:#dcfce7; color:#166534; padding:10px 16px; border-radius:8px; margin-bottom:14px; font-weight:600; font-size:13px;">
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>
        <?php if ($err !== ''): ?>
            <div style="background:#fee2e2; color:#991b1b; padding:10px 16px; border-radius:8px; margin-bottom:14px; font-weight:600; font-size:13px;">
                <?php echo htmlspecialchars($err); ?>
            </div>
        <?php endif; ?>

        <div class="panel-card">
            <h2 class="section-title">Student Directory & Student IDs</h2>
            <table>
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Program</th>
                        <th>Block</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="studentsTableBody">
                    <?php if (!empty($students)): ?>
                        <?php foreach ($students as $s): ?>
                            <?php
                                $s_name = $s['full_name'] ?? $s['username'] ?? '';
                                $s_email = $s['email'] ?? '';
                                $raw_prog = ($prog_col !== '' ? ($s[$prog_col] ?? '') : ($s['program'] ?? ($s['course'] ?? '')));
                                $prog_disp = formatProgramOnly($raw_prog);
                                $s_block = $s['block'] ?? '';
                            ?>
                            <tr>
                                <td><span class="stu-badge-no"><?php echo htmlspecialchars($s['display_student_id'] ?? '—'); ?></span></td>
                                <td><?php echo htmlspecialchars($s_name); ?></td>
                                <td><?php echo htmlspecialchars($s_email); ?></td>
                                <td><?php echo htmlspecialchars($prog_disp); ?></td>
                                <td><?php echo htmlspecialchars($s_block); ?></td>
                                <td>
                                    <div class="action-btns">
                                        <button type="button"
                                            class="act-btn act-edit"
                                            title="Edit Student"
                                            data-id="<?php echo intval($s['id']); ?>"
                                            data-sid="<?php echo htmlspecialchars($s['raw_student_id'] ?? '', ENT_QUOTES); ?>"
                                            data-name="<?php echo htmlspecialchars($s_name, ENT_QUOTES); ?>"
                                            data-email="<?php echo htmlspecialchars($s_email, ENT_QUOTES); ?>"
                                            data-program="<?php echo htmlspecialchars($raw_prog, ENT_QUOTES); ?>"
                                            data-block="<?php echo htmlspecialchars($s_block, ENT_QUOTES); ?>"
                                            onclick="openEditStudentModal(this)">&#9998; Edit</button>
                                        <a href="admin_students.php?delete_student_id=<?php echo intval($s['id']); ?>"
                                            class="act-btn act-delete"
                                            title="Delete Student"
                                            onclick="return confirm('Are you sure you want to delete this student?');">&#128465; Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align:center; color:#64748b; padding:20px;">No students have registered yet..</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

  
    <div id="editStudentModal" class="modal-overlay">
        <div class="modal-box">
            <h2 style="font-size:18px; font-weight:800; margin:0 0 6px 0;">Edit Student</h2>
            <form method="POST">
                <input type="hidden" name="edit_student_id" id="editStudentId">

                <label>Student ID</label>
                <input type="text" name="edit_student_code" id="editStudentCode" placeholder="Enter Student ID">

                <label>Name</label>
                <input type="text" name="edit_student_name" id="editStudentName" required>

                <label>Email</label>
                <input type="email" name="edit_student_email" id="editStudentEmail">

                <label>Program</label>
                <input type="text" name="edit_student_program" id="editStudentProgram">

                <label>Block</label>
                <input type="text" name="edit_student_block" id="editStudentBlock">

                <div style="display:flex; gap:10px; margin-top:16px;">
                    <button type="submit" name="edit_student" style="flex:1; background:#1e6091; color:#fff; border:none; padding:9px 12px; border-radius:8px; font-weight:700; cursor:pointer;">Save Changes</button>
                    <button type="button" onclick="closeEditStudentModal()" style="flex:1; background:#e2e8f0; color:#0f172a; border:none; padding:9px 12px; border-radius:8px; font-weight:700; cursor:pointer;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditStudentModal(btn) {
            document.getElementById('editStudentId').value = btn.getAttribute('data-id') || '';
            document.getElementById('editStudentCode').value = btn.getAttribute('data-sid') || '';
            document.getElementById('editStudentName').value = btn.getAttribute('data-name') || '';
            document.getElementById('editStudentEmail').value = btn.getAttribute('data-email') || '';
            document.getElementById('editStudentProgram').value = btn.getAttribute('data-program') || '';
            document.getElementById('editStudentBlock').value = btn.getAttribute('data-block') || '';
            document.getElementById('editStudentModal').style.display = 'flex';
        }

        function closeEditStudentModal() {
            document.getElementById('editStudentModal').style.display = 'none';
        }

        (function() {
            var searchInput = document.getElementById('studentSearchInput');
            var tbody = document.getElementById('studentsTableBody');
            if (!searchInput || !tbody) return;

            searchInput.addEventListener('input', function() {
                var q = this.value.toLowerCase().trim();
                tbody.querySelectorAll('tr').forEach(function(row) {
                    var text = row.textContent.toLowerCase();
                    row.style.display = (q === '' || text.indexOf(q) !== -1) ? '' : 'none';
                });
            });
        })();
    </script>
</body>
</html>