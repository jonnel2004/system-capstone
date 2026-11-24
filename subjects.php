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
$student_program = strtoupper(trim((string)($_SESSION['program'] ?? $_SESSION['course'] ?? $_SESSION['course_code'] ?? $_SESSION['department'] ?? '')));
$student_block = trim((string)($_SESSION['block'] ?? $_SESSION['block_name'] ?? $_SESSION['section'] ?? ''));

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
        if (preg_match('/\b' . preg_quote($p, '/') . '\b/i', $src) || strpos($src, $p) !== false) {
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
    $course = extractProgram($course);
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
                $student_program = $db_prog;
            }
            if ($db_blk !== '') {
                $student_block = $db_blk;
            }
        }
        $stmt->close();
    }
}

$norm_student_prog = extractProgram($student_program, $student_block);
$norm_student_blk  = normalizeBlock($student_block);
$has_student_filter = ($norm_student_prog !== '' || $norm_student_blk !== '');


$teachers_map = [];
if (tableExists($conn, 'users')) {
    $tq = $conn->query("SELECT * FROM users WHERE LOWER(role) = 'teacher'");
    if ($tq) {
        while ($tr = $tq->fetch_assoc()) {
            $tid = (int)$tr['id'];
            $tname = pickField($tr, ['full_name', 'name', 'teacher_name', 'username'], 'Instructor');
            $teachers_map[$tid] = $tname;
        }
    }
}

$matched_subjects = [];

if (tableExists($conn, 'teacher_assignments')) {
    $q = $conn->query("SELECT * FROM teacher_assignments ORDER BY id ASC");
    if ($q) {
        while ($r = $q->fetch_assoc()) {
            $subj      = pickField($r, ['subject_name', 'subject', 'subject_code', 'title', 'name'], 'Subject');
            $raw_course = pickField($r, ['course_code', 'program', 'course', 'course_name', 'department'], '');
            $raw_blk    = pickField($r, ['block_name', 'block', 'section'], '');

            $item_prog = extractProgram($raw_course, $raw_blk);
            $item_blk  = normalizeBlock($raw_blk);

            $tid   = (int)($r['teacher_id'] ?? 0);
            $tname = $teachers_map[$tid] ?? 'Assigned Instructor';

            if (!$has_student_filter) {
                $matched_subjects[] = [
                    'id'         => (int)($r['id'] ?? 0),
                    'subject'    => $subj,
                    'prog_block' => formatCourseBlock($item_prog ?: $raw_course, $raw_blk),
                    'teacher'    => $tname
                ];
            } else {
                $prog_ok = ($norm_student_prog === '' || $item_prog === $norm_student_prog);
                $blk_ok  = ($norm_student_blk === '' || $item_blk === $norm_student_blk);

                if ($prog_ok && $blk_ok) {
                    $matched_subjects[] = [
                        'id'         => (int)($r['id'] ?? 0),
                        'subject'    => $subj,
                        'prog_block' => formatCourseBlock($item_prog ?: $raw_course, $raw_blk),
                        'teacher'    => $tname
                    ];
                }
            }
        }
    }
}

$student_section_label = formatCourseBlock($norm_student_prog ?: $student_program, $student_block);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCDI Student Portal - My Subjects</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .subjects-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }
        .subjects-header h1 {
            font-size: 24px;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
        }
        .subjects-header p {
            font-size: 13px;
            color: #64748b;
            margin: 4px 0 0 0;
        }
        .section-badge {
            background: #e0f2fe;
            color: #0369a1;
            border: 1px solid #7dd3fc;
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
        }
        .subject-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 16px;
        }
        .subject-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-top: 5px solid #1e6091;
            border-radius: 14px;
            padding: 18px 20px;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
        }
        .subject-title {
            font-size: 17px;
            font-weight: 800;
            color: #0b2545;
            margin: 0 0 6px 0;
        }
        .subject-prog-block {
            font-size: 13px;
            color: #1e6091;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .subject-teacher {
            font-size: 12px;
            color: #475569;
            font-weight: 600;
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
            <img src="<?php echo htmlspecialchars($avatar_pic); ?>" alt="Profile Picture" style="width:100%; height:100%; object-fit:cover; display:block;">
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
        <a href="subjects.php" class="active">My Subjects</a>
        <a href="activity_feed.php">Activities</a>
        <a href="live_sessions.php">Live Sessions</a>
        <a href="settings.php">Settings</a>
    </nav>

    <main class="main-wrapper">
        <div class="subjects-header">
            <div>
                <h1>My Subjects</h1>
                <p>Subjects assigned by Admin to teachers for your program and block</p>
            </div>
            <?php if ($student_section_label !== ''): ?>
                <span class="section-badge"><?php echo htmlspecialchars($student_section_label); ?></span>
            <?php endif; ?>
        </div>

        <div class="subject-list" id="studentSubjectList">
            <?php foreach ($matched_subjects as $item): ?>
                <div class="subject-card js-student-subject-card">
                    <h2 class="subject-title"><?php echo htmlspecialchars($item['subject']); ?></h2>
                    <?php if ($item['prog_block'] !== ''): ?>
                        <div class="subject-prog-block"><?php echo htmlspecialchars($item['prog_block']); ?></div>
                    <?php endif; ?>
                    <div class="subject-teacher">Instructor: <?php echo htmlspecialchars($item['teacher']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <script>
        (function() {
            var searchInput = document.getElementById('studentSubjectSearch');
            var cards = document.querySelectorAll('.js-student-subject-card');
            if (searchInput && cards.length > 0) {
                searchInput.addEventListener('input', function() {
                    var q = this.value.toLowerCase().trim();
                    cards.forEach(function(card) {
                        var txt = card.textContent.toLowerCase();
                        card.style.display = (q === '' || txt.indexOf(q) !== -1) ? 'block' : 'none';
                    });
                });
            }
        })();
    </script>
</body>
</html>