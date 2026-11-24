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


function cleanProgramTitle($raw_text) {
    $text = trim((string)$raw_text);
    $text = preg_replace('/\b4[\s\-]*yr\.?\b/i', '', $text);
    $text = preg_replace('/\b(1st|2nd|3rd|4th|first|second|third|fourth)\s*(yr\.?|year)\b/i', '', $text);
    $text = preg_replace('/\band\s+soon\b/i', '', $text);
    $text = preg_replace('/(\([A-Za-z0-9]+\))(?:\s*\1)+/i', '$1', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text, " \t\n\r\0\x0B,-/");
}


$program_counts = [
    'BSCS'  => 0,
    'BSIT'  => 0,
    'BSIS'  => 0,
    'BSOAD' => 0,
];

$q_stu = $conn->query("SELECT program, course FROM users WHERE LOWER(TRIM(role)) = 'student'");
if ($q_stu && $q_stu->num_rows > 0) {
    while ($row = $q_stu->fetch_assoc()) {
        $p = strtoupper(trim(($row['program'] ?? '') . ' ' . ($row['course'] ?? '')));
        if (strpos($p, 'BSCS') !== false || strpos($p, 'COMPUTER SCIENCE') !== false || strpos($p, 'COMPUTER SYSTEM') !== false) {
            $program_counts['BSCS']++;
        }
        if (strpos($p, 'BSIT') !== false || strpos($p, 'INFORMATION TECHNOLOGY') !== false) {
            $program_counts['BSIT']++;
        }
        if (strpos($p, 'BSIS') !== false || strpos($p, 'INFORMATION SYSTEM') !== false) {
            $program_counts['BSIS']++;
        }
        if (strpos($p, 'BSOAD') !== false || strpos($p, 'BSOA') !== false || strpos($p, 'OFFICE ADMINISTRATION') !== false) {
            $program_counts['BSOAD']++;
        }
    }
}

$programs = [
    [
        'code'  => 'BSCS',
        'title' => cleanProgramTitle('BS Computer Science (BSCS)'),
        'count' => $program_counts['BSCS'],
        'url'   => 'admin_students.php?search=BSCS',
    ],
    [
        'code'  => 'BSIT',
        'title' => cleanProgramTitle('BS Information Technology (BSIT)'),
        'count' => $program_counts['BSIT'],
        'url'   => 'admin_students.php?search=BSIT',
    ],
    [
        'code'  => 'BSIS',
        'title' => cleanProgramTitle('BS Information Systems (BSIS)'),
        'count' => $program_counts['BSIS'],
        'url'   => 'admin_students.php?search=BSIS',
    ],
    [
        'code'  => 'BSOAD',
        'title' => cleanProgramTitle('BS Office Administration (BSOAD)'),
        'count' => $program_counts['BSOAD'],
        'url'   => 'admin_students.php?search=BSOAD',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCDI Admin Portal - Manage Programs</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .programs-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }
        .programs-header h1 {
            font-size: 24px;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
        }
        .program-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .program-title {
            font-size: 16px;
            font-weight: 800;
            color: #0b2545;
            margin: 0 0 6px 0;
            letter-spacing: 0.2px;
        }
        .program-meta {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            font-size: 13px;
            color: #475569;
        }
        .program-code-pill {
            background: #eff6ff;
            color: #1d4ed8;
            font-weight: 700;
            font-size: 12px;
            padding: 3px 10px;
            border-radius: 999px;
        }
        .program-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 18px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            transition: box-shadow 0.15s ease, transform 0.15s ease;
        }
        .program-link-hint {
            font-size: 12px;
            font-weight: 700;
            color: #1e6091;
            background: #f1f5f9;
            padding: 8px 14px;
            border-radius: 10px;
            white-space: nowrap;
            transition: background-color 0.15s ease, color 0.15s ease;
        }
        .program-card:hover .program-link-hint {
            background: #1e6091;
            color: #ffffff;
        }
        a.program-card {
            text-decoration: none;
            color: inherit;
        }
    </style>
</head>
<body>

    <header class="topbar" style="background-color: #0b2545;">
        <div class="brand">
            <div class="brand-logo"><img src="logo.jpg" alt="logo" style="width:100%; height:100%; object-fit:contain;"></div>
            <span>CCDI Admin Portal</span>
        </div>
        <input type="text" id="programSearchInput" class="search-bar" placeholder="Search">
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
        <a href="admin_faculty.php">Manage Facultys</a>
        <a href="admin_program.php" class="active">Manage Programs</a>
        <a href="admin_rooms.php">Manage Rooms</a>
        <a href="admin_settings.php">System Settings</a>
    </nav>

    <main class="main-wrapper">
        <div class="programs-header">
            <div>
                <h1>Courses Offer</h1>
                <p style="font-size:13px; color:#64748b; margin-top:4px;">Academic programs offered in CCDI</p>
            </div>
        </div>

        <div class="program-list" id="programListContainer">
            <?php foreach ($programs as $prog): ?>
                <a href="<?php echo htmlspecialchars($prog['url']); ?>" class="program-card">
                    <div>
                        <h2 class="program-title"><?php echo htmlspecialchars($prog['title']); ?></h2>
                        <div class="program-meta">
                            <span class="program-code-pill"><?php echo htmlspecialchars($prog['code']); ?></span>
                            <span>Total Students: <strong><?php echo intval($prog['count']); ?></strong> (click to full list)</span>
                        </div>
                    </div>
                    <span class="program-link-hint">View Students &rarr;</span>
                </a>
            <?php endforeach; ?>
        </div>
    </main>

    <script>
        (function() {
            var searchInput = document.getElementById('programSearchInput');
            var listContainer = document.getElementById('programListContainer');
            if (!searchInput || !listContainer) return;

            searchInput.addEventListener('input', function() {
                var q = this.value.toLowerCase().trim();
                listContainer.querySelectorAll('.program-card').forEach(function(card) {
                    var text = card.textContent.toLowerCase();
                    card.style.display = (q === '' || text.indexOf(q) !== -1) ? 'flex' : 'none';
                });
            });
        })();
    </script>
</body>
</html>