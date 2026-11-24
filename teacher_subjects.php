<?php
if (session_id() === '') {
    @session_start();
}
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$teacher_id = intval($_SESSION['user_id']);
$teacher_name = trim((string)($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Teacher'));

/* === PART 1 (PHP): Kunin ang picture path ng may-ari ng account === */
$avatar_pic = trim((string)($teacher['profile_pic'] ?? $_SESSION['profile_pic'] ?? ''));
$has_avatar_pic = ($avatar_pic !== '' && file_exists(__DIR__ . '/' . $avatar_pic));

$upload_msg = '';
$upload_err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_subject_file'])) {
    $subj_id = intval($_POST['subject_id'] ?? 0);
    $subj_title = trim((string)($_POST['subject_title'] ?? 'Subject'));
    $subj_prog_block = trim((string)($_POST['subject_prog_block'] ?? ''));

    if ($subj_id <= 0) {
        $upload_err = 'Invalid subject selected.';
    } elseif (!isset($_FILES['subject_file']) || $_FILES['subject_file']['error'] !== UPLOAD_ERR_OK) {
        $upload_err = 'Select a file to upload first.';
    } else {
        $upload_dir = __DIR__ . '/uploads/subjects';
        if (!is_dir($upload_dir)) {
            @mkdir($upload_dir, 0777, true);
        }

        $orig_name = basename($_FILES['subject_file']['name']);
        $safe_name = 'subj_' . $subj_id . '_' . time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $orig_name);
        $target_path = $upload_dir . '/' . $safe_name;

        if (move_uploaded_file($_FILES['subject_file']['tmp_name'], $target_path)) {
            $suffix = $subj_prog_block !== '' ? ' (' . $subj_prog_block . ')' : '';
            $upload_msg = 'Has been uploaded "' . $orig_name . '" for' . $subj_title . $suffix . '!';
        } else {
            $upload_err = 'The file has not been ploaded yet.';
        }
    }
}

function tableExists(mysqli $conn, string $table): bool {
    $safe = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return ($res && $res->num_rows > 0);
}

function getColumns(mysqli $conn, string $table): array {
    $cols = [];
    $res = $conn->query("SHOW COLUMNS FROM `{$table}`");
    if ($res) {
        while ($c = $res->fetch_assoc()) {
            $cols[] = $c['Field'];
        }
    }
    return $cols;
}

function pickField(array $row, array $keys, string $default = ''): string {
    foreach ($keys as $k) {
        if (isset($row[$k]) && trim((string)$row[$k]) !== '') {
            return trim((string)$row[$k]);
        }
    }
    return $default;
}


function formatAdminProgramBlock(string $program, string $block): string {
    $program = trim($program);
    $block = trim($block);

   
    $clean_block = preg_replace('/^block\s+/i', '', $block);

    if ($program !== '' && $clean_block !== '') {
        return $program . ' - Block ' . $clean_block;
    }
    if ($program !== '') {
        return $program;
    }
    if ($clean_block !== '') {
        return 'Block ' . $clean_block;
    }
    return '';
}

$assigned_items = [];


if (tableExists($conn, 'teacher_assignments')) {
    $ta_cols = getColumns($conn, 'teacher_assignments');
    $rows = [];

    if (in_array('teacher_id', $ta_cols, true)) {
        $stmt = $conn->prepare("SELECT * FROM teacher_assignments WHERE teacher_id = ? ORDER BY id ASC");
        if ($stmt) {
            $stmt->bind_param("i", $teacher_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $rows[] = $r;
            }
            $stmt->close();
        }
    }
    if (empty($rows)) {
        $q = $conn->query("SELECT * FROM teacher_assignments ORDER BY id ASC");
        if ($q) {
            while ($r = $q->fetch_assoc()) {
                $rows[] = $r;
            }
        }
    }

    foreach ($rows as $idx => $r) {
        $subj = pickField($r, ['subject_name', 'subject', 'subject_code', 'title', 'name'], '');
       
        $program = pickField($r, ['program', 'course_code', 'course', 'course_name', 'department'], '');
        $blk = pickField($r, ['block_name', 'block', 'section'], '');

        if ($subj === '' && isset($r['subject_id']) && tableExists($conn, 'subjects')) {
            $sid = intval($r['subject_id']);
            $qs = $conn->query("SELECT * FROM subjects WHERE id = {$sid} LIMIT 1");
            if ($qs && $srow = $qs->fetch_assoc()) {
                $subj = pickField($srow, ['subject_name', 'name', 'title', 'subject'], '');
                if ($program === '') $program = pickField($srow, ['program', 'course_code', 'course', 'course_name', 'department'], '');
                if ($blk === '')     $blk     = pickField($srow, ['block_name', 'block', 'section'], '');
            }
        }

        if ($subj !== '' || $program !== '' || $blk !== '') {
            $assigned_items[] = [
                'id' => intval($r['id'] ?? ($idx + 1)),
                'subject' => $subj !== '' ? $subj : 'Subject',
                'prog_block' => formatAdminProgramBlock($program, $blk)
            ];
        }
    }
}


if (empty($assigned_items) && tableExists($conn, 'subjects')) {
    $s_cols = getColumns($conn, 'subjects');
    $subj_rows = [];
    if (in_array('teacher_id', $s_cols, true)) {
        $stmt = $conn->prepare("SELECT * FROM subjects WHERE teacher_id = ? ORDER BY id ASC");
        if ($stmt) {
            $stmt->bind_param("i", $teacher_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $subj_rows[] = $r;
            }
            $stmt->close();
        }
    }
    if (empty($subj_rows)) {
        $q = $conn->query("SELECT * FROM subjects ORDER BY id ASC");
        if ($q) {
            while ($r = $q->fetch_assoc()) {
                $subj_rows[] = $r;
            }
        }
    }

    foreach ($subj_rows as $idx => $s) {
        $subj = pickField($s, ['subject_name', 'name', 'title', 'subject'], 'Subject');
        $program = pickField($s, ['program', 'course_code', 'course', 'course_name', 'department'], '');
        $blk = pickField($s, ['block_name', 'block', 'section'], '');

        $assigned_items[] = [
            'id' => intval($s['id'] ?? ($idx + 1)),
            'subject' => $subj,
            'prog_block' => formatAdminProgramBlock($program, $blk)
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCDI Teacher Portal - My Assigned Subjects</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .subjects-header {
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
        .subject-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .subject-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-left: 5px solid #1e6091;
            border-radius: 14px;
            padding: 18px 22px;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
        }
        .subject-title {
            font-size: 17px;
            font-weight: 800;
            color: #0b2545;
            margin: 0 0 4px 0;
        }
        .subject-prog-block {
            font-size: 13px;
            color: #1e6091;
            font-weight: 700;
        }
        .btn-upload-file {
            background: #1e6091;
            color: #ffffff;
            border: none;
            border-radius: 10px;
            padding: 8px 14px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-upload-file:hover {
            background: #0b2545;
        }
        .modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .modal-backdrop.open {
            display: flex;
        }
        .modal-box {
            background: #ffffff;
            border-radius: 16px;
            padding: 22px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.2);
        }
    </style>
</head>
<body>

    <header class="topbar" style="background-color: #0b2545;">
        <div class="brand">
            <div class="brand-logo"><img src="logo.jpg" alt="logo" style="width:100%; height:100%; object-fit:contain;"></div>
            <span>CCDI Teacher Portal</span>
        </div>
        <input type="text" id="subjectSearchInput" class="search-bar" placeholder="Search">
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
        <a href="teacher_subjects.php" class="active">Subjects</a>
        <a href="teacher_activities.php">Activities</a>
        <a href="teacher_live.php">Live Sessions</a>
        <a href="teacher_settings.php">Settings</a>
    </nav>

    <main class="main-wrapper">
        <div class="subjects-header">
            <h1>My Assigned Subjects</h1>
            <p>Assigned subjects based on Admin program and block</p>
        </div>

        <?php if ($upload_msg !== ''): ?>
            <div style="background:#dcfce7; color:#15803d; border:1px solid #86efac; padding:10px 14px; border-radius:10px; font-size:13px; font-weight:700; margin-bottom:14px;">
                <?php echo htmlspecialchars($upload_msg); ?>
            </div>
        <?php endif; ?>
        <?php if ($upload_err !== ''): ?>
            <div style="background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; padding:10px 14px; border-radius:10px; font-size:13px; font-weight:700; margin-bottom:14px;">
                <?php echo htmlspecialchars($upload_err); ?>
            </div>
        <?php endif; ?>

        <div class="subject-list" id="subjectListContainer">
            <?php if (!empty($assigned_items)): ?>
                <?php foreach ($assigned_items as $item): ?>
                    <div class="subject-card">
                        <div>
                            <h2 class="subject-title"><?php echo htmlspecialchars($item['subject']); ?></h2>
                            <?php if ($item['prog_block'] !== ''): ?>
                                <div class="subject-prog-block"><?php echo htmlspecialchars($item['prog_block']); ?></div>
                            <?php endif; ?>
                        </div>

                        <div>
                            <button
                                type="button"
                                class="btn-upload-file js-open-upload-modal"
                                data-subject-id="<?php echo intval($item['id']); ?>"
                                data-subject-title="<?php echo htmlspecialchars($item['subject'], ENT_QUOTES); ?>"
                                data-subject-prog-block="<?php echo htmlspecialchars($item['prog_block'], ENT_QUOTES); ?>"
                            >
                                &#128228; Upload File
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="panel-card" style="text-align:center; color:#64748b; padding:24px;">
                    No subjects have been assigned yet..
                </div>
            <?php endif; ?>
        </div>
    </main>

    <div class="modal-backdrop" id="uploadSubjectModal">
        <div class="modal-box">
            <h2 style="font-size:18px; font-weight:800; color:#0f172a; margin:0 0 6px 0;">Upload Subject File</h2>
            <p id="uploadModalSubtext" style="font-size:12px; color:#64748b; margin:0 0 14px 0;"></p>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="subject_id" id="modalSubjectId">
                <input type="hidden" name="subject_title" id="modalSubjectTitle">
                <input type="hidden" name="subject_prog_block" id="modalSubjectProgBlock">

                <div style="margin-bottom:14px;">
                    <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:6px;">Select File (PPT, PDF, DOC, etc.)</label>
                    <input type="file" name="subject_file" required style="width:100%; font-size:13px;">
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px;">
                    <button type="button" id="closeUploadSubjectModal" style="background:#e2e8f0; color:#1e293b; border:none; border-radius:8px; padding:8px 14px; font-weight:700; cursor:pointer;">Cancel</button>
                    <button type="submit" name="upload_subject_file" class="btn-upload-file">Upload</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function() {
            var searchInput = document.getElementById('subjectSearchInput');
            var listContainer = document.getElementById('subjectListContainer');
            if (searchInput && listContainer) {
                searchInput.addEventListener('input', function() {
                    var q = this.value.toLowerCase().trim();
                    listContainer.querySelectorAll('.subject-card').forEach(function(card) {
                        var txt = card.textContent.toLowerCase();
                        card.style.display = (q === '' || txt.indexOf(q) !== -1) ? 'flex' : 'none';
                    });
                });
            }

            var modal = document.getElementById('uploadSubjectModal');
            var closeBtn = document.getElementById('closeUploadSubjectModal');
            var idField = document.getElementById('modalSubjectId');
            var titleField = document.getElementById('modalSubjectTitle');
            var pbField = document.getElementById('modalSubjectProgBlock');
            var subtext = document.getElementById('uploadModalSubtext');

            document.querySelectorAll('.js-open-upload-modal').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var sid = this.getAttribute('data-subject-id');
                    var stitle = this.getAttribute('data-subject-title');
                    var spb = this.getAttribute('data-subject-prog-block');
                    if (idField) idField.value = sid;
                    if (titleField) titleField.value = stitle;
                    if (pbField) pbField.value = spb;
                    if (subtext) subtext.textContent = stitle + (spb ? ' (' + spb + ')' : '');
                    if (modal) modal.classList.add('open');
                });
            });

            if (closeBtn && modal) {
                closeBtn.addEventListener('click', function() {
                    modal.classList.remove('open');
                });
            }
        })();
    </script>
</body>
</html>