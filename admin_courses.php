<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$admin_id = intval($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
$u_pic_q = @$conn->query("SELECT profile_pic FROM users WHERE id = {$admin_id} LIMIT 1");
$u_pic_row = ($u_pic_q && $u_pic_q->num_rows > 0) ? $u_pic_q->fetch_assoc() : [];
$avatar_pic = trim((string)($u_pic_row['profile_pic'] ?? $_SESSION['profile_pic'] ?? ''));
$has_avatar_pic = ($avatar_pic !== '' && file_exists(__DIR__ . '/' . $avatar_pic));

$msg = "";

// table of block_subjects
$conn->query("CREATE TABLE IF NOT EXISTS block_subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(20),
    year_level VARCHAR(20),
    block_name VARCHAR(20),
    subject_name VARCHAR(100),
    subject_type VARCHAR(20)
)");


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_block_subject'])) {
    $course_code = $conn->real_escape_string($_POST['course_code']);
    $year_level = $conn->real_escape_string(trim($_POST['year_level']));
    $block_name = $conn->real_escape_string(trim($_POST['block_name']));
    $subject_type = $conn->real_escape_string(trim($_POST['subject_type']));
    $raw_subjects = $_POST['subjects_list'];

    $subjects_array = preg_split('/[\r\n,]+/', $raw_subjects);
    $added_count = 0;

    foreach ($subjects_array as $sub) {
        $clean_sub = $conn->real_escape_string(trim($sub));
        if (!empty($clean_sub)) {
            $sql = "INSERT INTO block_subjects (course_code, year_level, block_name, subject_name, subject_type) 
                    VALUES ('$course_code', '$year_level', '$block_name', '$clean_sub', '$subject_type')";
            if ($conn->query($sql) === TRUE) {
                $added_count++;
            }
        }
    }

    if ($added_count > 0) {
        $msg = "Successfully added <strong>$added_count subject(s)</strong> para sa <strong>$course_code - $year_level, Block $block_name</strong>!";
    }
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_block_subject'])) {
    $edit_id = intval($_POST['edit_subject_id'] ?? 0);
    $new_name = $conn->real_escape_string(trim($_POST['edit_subject_name'] ?? ''));
    $new_type = $conn->real_escape_string(trim($_POST['edit_subject_type'] ?? 'Major'));

    if ($edit_id > 0 && $new_name !== '') {
        $conn->query("UPDATE block_subjects SET subject_name = '$new_name', subject_type = '$new_type' WHERE id = $edit_id LIMIT 1");
        $msg = "Na-update na ang subject sa <strong>" . htmlspecialchars($new_name) . "</strong>.";
    }
}

// 2. Delete subject from block
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    $conn->query("DELETE FROM block_subjects WHERE id = $del_id");
    $msg = "Nabura na ang subject mula sa block.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCDI Learning Portal - Courses &amp; Block Subjects</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 20px;
        }
        .course-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border-top: 5px solid #1e6091;
        }
        .course-card h2 {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 14px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 8px;
        }
        .year-section-title {
            font-size: 14px;
            font-weight: 800;
            color: #1e6091;
            margin-top: 14px;
            margin-bottom: 6px;
            text-transform: uppercase;
            border-left: 3px solid #1e6091;
            padding-left: 6px;
        }
        .block-group {
            margin-bottom: 10px;
            background: #f8fafc;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .block-header {
            font-size: 12px;
            font-weight: 700;
            color: #475569;
            margin-bottom: 4px;
        }
        .subject-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .subject-pill {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            color: #1e293b;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .subject-pill .type-dot {
            font-size: 9px;
            padding: 1px 5px;
            border-radius: 8px;
            font-weight: 700;
        }
        .type-major { background: #dcfce7; color: #166534; }
        .type-minor { background: #fef3c7; color: #92400e; }
        .pill-icon-btn {
            background: none;
            border: none;
            padding: 0;
            cursor: pointer;
            font-size: 12px;
            line-height: 1;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        .edit-icon-btn {
            color: #1e6091;
        }
        .edit-icon-btn:hover {
            color: #0b2545;
        }
        .del-icon-btn {
            color: #ef4444;
        }
        .del-icon-btn:hover {
            color: #b91c1c;
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
            max-width: 440px;
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
            font-family: inherit;
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
        <a href="admin_courses.php" class="active">Manage Courses</a>
        <a href="admin_students.php">Manage Students</a>
        <a href="admin_faculty.php">Manage Facultys</a>
        <a href="admin_program.php">Manage Programs</a>
        <a href="admin_rooms.php">Manage Rooms</a>
        <a href="admin_settings.php">System Settings</a>
    </nav>

    <main class="main-wrapper">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 16px;">
            <h1 style="font-size: 26px; font-weight: 800;">Courses &amp; Block Subjects</h1>
            <button onclick="document.getElementById('addSubjectModal').style.display='flex'" style="background:#1e6091; color:white; border:none; padding:10px 18px; border-radius:16px; font-weight:700; cursor:pointer; font-size:13px;">
                + Assign Block Subjects
            </button>
        </div>

        <?php if ($msg): ?>
            <div style="background:#dcfce7; color:#166534; padding:10px 16px; border-radius:10px; margin-bottom:16px; border-left:5px solid #16a34a; font-size:13px;">
                &#9989; <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <div class="courses-grid">
            <?php
            $courses = [
                ["name" => "BS Information Technology (BSIT)", "code" => "BSIT"],
                ["name" => "BS Computer System (BSCS)", "code" => "BSCS"],
                ["name" => "BS Information System (BSIS)", "code" => "BSIS"],
                ["name" => "BS Office Administration (BSOAD)", "code" => "BSOAD"]
            ];

            // List of  year levels and blocks (A, B, C)
            $curriculum = [
                "1st Year" => ['A', 'B', 'C'],
                "2nd Year" => ['A', 'B', 'C'],
                "3rd Year" => ['A', 'B', 'C'],
                "4th Year" => ['A', 'B', 'C']
            ];

            foreach ($courses as $c):
                $c_code = $c['code'];
            ?>
                <div class="course-card">
                    <h2><?php echo $c['name']; ?></h2>
                    
                    <?php foreach ($curriculum as $year => $blocks): ?>
                        <div class="year-section-title"><?php echo $year; ?></div>
                        <?php foreach ($blocks as $b): 
                            $block_name = substr($year, 0, 1) . $b; 
                            $safe_y = $conn->real_escape_string($year);
                            $safe_b = $conn->real_escape_string($block_name);
                            
                            $subs = $conn->query("SELECT * FROM block_subjects WHERE course_code='$c_code' AND year_level='$safe_y' AND block_name='$safe_b' ORDER BY id ASC");
                        ?>
                            <div class="block-group">
                                <div class="block-header">Block <?php echo htmlspecialchars($block_name); ?></div>
                                <div class="subject-tags">
                                    <?php if ($subs && $subs->num_rows > 0): ?>
                                        <?php while ($s = $subs->fetch_assoc()): ?>
                                            <span class="subject-pill">
                                                <?php echo htmlspecialchars($s['subject_name']); ?>
                                                <span class="type-dot <?php echo ($s['subject_type'] == 'Major') ? 'type-major' : 'type-minor'; ?>">
                                                    <?php echo htmlspecialchars($s['subject_type']); ?>
                                                </span>
                                                <button type="button"
                                                    class="pill-icon-btn edit-icon-btn"
                                                    title="Edit Subject"
                                                    data-id="<?php echo intval($s['id']); ?>"
                                                    data-name="<?php echo htmlspecialchars($s['subject_name'], ENT_QUOTES); ?>"
                                                    data-type="<?php echo htmlspecialchars($s['subject_type'], ENT_QUOTES); ?>"
                                                    onclick="openEditSubjectModal(this)">&#9998;</button>
                                                <a href="admin_courses.php?delete_id=<?php echo intval($s['id']); ?>"
                                                    class="pill-icon-btn del-icon-btn"
                                                    title="Delete Subject"
                                                    onclick="return confirm('Remove this subject?');">&#128465;</a>
                                            </span>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <span style="font-size: 11px; color: #94a3b8; font-style: italic;">No subject has been assigned yet.</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </main>


    <div id="addSubjectModal" class="modal-overlay">
        <div class="modal-box">
            <h2 style="font-size:18px; font-weight:800; margin-bottom:6px;">Assign Subjects to Block</h2>
            <form method="POST">
                <div style="display:flex; gap:10px;">
                    <div style="flex:1;">
                        <label>Course</label>
                        <select name="course_code" required>
                            <option value="BSIT">BSIT</option>
                            <option value="BSCS">BSCS</option>
                            <option value="BSIS">BSIS</option>
                            <option value="BSOAD">BSOAD</option>
                        </select>
                    </div>
                    <div style="flex:1;">
                        <label>Year Level</label>
                        <select name="year_level" id="yearLevelSelect" onchange="updateBlocks()" required>
                            <option value="1st Year">1st Year</option>
                            <option value="2nd Year">2nd Year</option>
                            <option value="3rd Year">3rd Year</option>
                            <option value="4th Year">4th Year</option>
                        </select>
                    </div>
                    <div style="flex:1;">
                        <label>Block</label>
                        <select name="block_name" id="blockSelect" required>
                          
                        </select>
                    </div>
                </div>

                <label style="margin-top:10px;">Subject Type</label>
                <select name="subject_type" required>
                    <option value="Major">Major</option>
                    <option value="Minor">Minor</option>
                </select>

                <label>Subjects (Seperate using a comma or the Enter key.)</label>
                <textarea name="subjects_list" rows="4" placeholder="Example:&#10;Programming 1, Introduction to Computing, Discrete Math" required></textarea>

                <div style="display:flex; gap:10px; margin-top:16px;">
                    <button type="submit" name="add_block_subject" style="flex:1; background:#16a34a; color:white; border:none; padding:10px; border-radius:8px; font-weight:700; cursor:pointer; font-size:13px;">Save All Subjects</button>
                    <button type="button" onclick="document.getElementById('addSubjectModal').style.display='none'" style="flex:1; background:#e2e8f0; color:#0f172a; border:none; padding:10px; border-radius:8px; font-weight:700; cursor:pointer; font-size:13px;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

  
    <div id="editSubjectModal" class="modal-overlay">
        <div class="modal-box">
            <h2 style="font-size:18px; font-weight:800; margin-bottom:6px;">Edit Block Subject</h2>
            <form method="POST">
                <input type="hidden" name="edit_subject_id" id="editSubjectId">

                <label>Subject Name</label>
                <input type="text" name="edit_subject_name" id="editSubjectName" required>

                <label>Subject Type</label>
                <select name="edit_subject_type" id="editSubjectType" required>
                    <option value="Major">Major</option>
                    <option value="Minor">Minor</option>
                </select>

                <div style="display:flex; gap:10px; margin-top:16px;">
                    <button type="submit" name="edit_block_subject" style="flex:1; background:#1e6091; color:white; border:none; padding:10px; border-radius:8px; font-weight:700; cursor:pointer; font-size:13px;">Save Changes</button>
                    <button type="button" onclick="closeEditSubjectModal()" style="flex:1; background:#e2e8f0; color:#0f172a; border:none; padding:10px; border-radius:8px; font-weight:700; cursor:pointer; font-size:13px;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function updateBlocks() {
            const yearSelect = document.getElementById('yearLevelSelect');
            const blockSelect = document.getElementById('blockSelect');
            const selectedYear = yearSelect.value;

            blockSelect.innerHTML = '';

            let prefix = '1';
            if (selectedYear === '1st Year') prefix = '1';
            else if (selectedYear === '2nd Year') prefix = '2';
            else if (selectedYear === '3rd Year') prefix = '3';
            else if (selectedYear === '4th Year') prefix = '4';

            const letters = ['A', 'B', 'C'];
            letters.forEach(letter => {
                const blockCode = prefix + letter;
                const opt = document.createElement('option');
                opt.value = blockCode;
                opt.textContent = 'Block ' + blockCode;
                blockSelect.appendChild(opt);
            });
        }

        function openEditSubjectModal(btn) {
            const id = btn.getAttribute('data-id') || '';
            const name = btn.getAttribute('data-name') || '';
            const type = btn.getAttribute('data-type') || 'Major';

            document.getElementById('editSubjectId').value = id;
            document.getElementById('editSubjectName').value = name;
            document.getElementById('editSubjectType').value = type;
            document.getElementById('editSubjectModal').style.display = 'flex';
        }

        function closeEditSubjectModal() {
            document.getElementById('editSubjectModal').style.display = 'none';
        }

        window.onload = function() {
            updateBlocks();
        };
    </script>
</body>
</html>