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

function pickValue(array $row, array $keys, string $default = ''): string {
    foreach ($keys as $k) {
        if (isset($row[$k]) && trim((string)$row[$k]) !== '') {
            return trim((string)$row[$k]);
        }
    }
    return $default;
}

function normalizeMode(string $rawMode, string $room): string {
    $m = strtolower(trim($rawMode));
    $r = strtolower(trim($room));

    if (strpos($m, 'online') !== false || strpos($m, 'virtual') !== false || strpos($m, 'meet') !== false || strpos($m, 'zoom') !== false) {
        return 'Online';
    }
    if (strpos($m, 'face') !== false || strpos($m, 'f2f') !== false || strpos($m, 'onsite') !== false || strpos($m, 'in-person') !== false) {
        return 'Face to Face';
    }
    if ($rawMode !== '') {
        return trim($rawMode);
    }
    if (strpos($r, 'online') !== false || strpos($r, 'zoom') !== false || strpos($r, 'meet') !== false) {
        return 'Online';
    }
    return $r !== '' ? 'Face to Face' : 'Face to Face';
}

function normalizeYear(string $rawYear, string $blockText = ''): string {
    $src = strtolower(trim($rawYear . ' ' . $blockText));
    if (preg_match('/\b1(st)?\b|first/i', $src)) {
        return '1st Year';
    }
    if (preg_match('/\b2(nd)?\b|second/i', $src)) {
        return '2nd Year';
    }
    if (preg_match('/\b3(rd)?\b|third/i', $src)) {
        return '3rd Year';
    }
    if (preg_match('/\b4(th)?\b|fourth/i', $src)) {
        return '4th Year';
    }
    return trim($rawYear) !== '' ? trim($rawYear) : '1st Year';
}

function normalizeProgram(string $rawProg, string $blockText = ''): string {
    $src = strtoupper(trim($rawProg . ' ' . $blockText));
    foreach (['BSIT', 'BSCS', 'BSIS', 'BSOAD'] as $p) {
        if (strpos($src, $p) !== false) {
            return $p;
        }
    }
    return strtoupper(trim($rawProg)) !== '' ? strtoupper(trim($rawProg)) : 'BSIT';
}

function parseSubjectAndBlock(string $subjBlock, string $rawSubj, string $rawBlock): array {
    $subj = trim($rawSubj);
    $blk  = trim($rawBlock);

    if ($subj === '' && $subjBlock !== '') {
        if (preg_match('/^(.*?)\s*[-–—|]\s*(?:Block\s*)?([A-Za-z0-9\s]+)$/i', $subjBlock, $m)) {
            $subj = trim($m[1]);
            if ($blk === '') {
                $blk = trim($m[2]);
            }
        } else {
            $subj = trim($subjBlock);
        }
    }

    $clean_blk = preg_replace('/^block\s+/i', '', $blk);
    return [
        'subject' => $subj !== '' ? $subj : 'Subject',
        'block'   => $clean_blk !== '' ? $clean_blk : '—'
    ];
}

$avatar_pic = trim((string)($teacher['profile_pic'] ?? $_SESSION['profile_pic'] ?? ''));
$has_avatar_pic = ($avatar_pic !== '' && file_exists(__DIR__ . '/' . $avatar_pic));



if ($teacher_id > 0 && tableExists($conn, 'users')) {
    $u_stmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    if ($u_stmt) {
        $u_stmt->bind_param("i", $teacher_id);
        $u_stmt->execute();
        $u_res = $u_stmt->get_result();
        if ($u_row = $u_res->fetch_assoc()) {
            $db_name = pickValue($u_row, ['full_name', 'name', 'teacher_name', 'username'], '');
            if ($db_name !== '') {
                $teacher_name = $db_name;
            }
        }
        $u_stmt->close();
    }
}

$blocks = [];
$sessions = [];

if (tableExists($conn, 'room_schedules')) {
    $cols = getColumns($conn, 'room_schedules');
    $rows = [];

    if (in_array('teacher_id', $cols, true)) {
        $stmt = $conn->prepare("SELECT * FROM room_schedules WHERE teacher_id = ? ORDER BY id DESC");
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

    foreach ($rows as $r) {
        $room      = pickValue($r, ['room_name', 'room', 'room_number', 'room_code', 'name', 'location'], '');
        $day       = pickValue($r, ['day_of_week', 'day_name', 'day', 'days', 'schedule_day'], '');
        $subjBlock = pickValue($r, ['subject_block'], '');
        $rawSubj   = pickValue($r, ['subject_name', 'subject', 'subject_code', 'title', 'course_name'], '');
        $rawBlock  = pickValue($r, ['block_name', 'block', 'section'], '');
        $rawProg   = pickValue($r, ['program_code', 'program', 'course_code', 'course', 'department'], '');
        $rawYear   = pickValue($r, ['year_level', 'year'], '');
        $timeSlot  = pickValue($r, ['time_slot', 'timeslot', 'time', 'session_time', 'schedule_time', 'schedule'], '');
        $rawMode   = pickValue($r, ['session_mode', 'mode', 'class_mode', 'delivery_mode', 'setup', 'type'], '');

        $parsed = parseSubjectAndBlock($subjBlock, $rawSubj, $rawBlock);
        $subj   = $parsed['subject'];
        $blk    = $parsed['block'];
        $prog   = normalizeProgram($rawProg, $blk . ' ' . $subjBlock);
        $yr     = normalizeYear($rawYear, $blk . ' ' . $subjBlock);
        $mode   = normalizeMode($rawMode, $room);

        if ($blk !== '—' && !in_array($blk, $blocks, true)) {
            $blocks[] = $blk;
        }

        $sessions[] = [
            'subject'   => $subj,
            'program'   => $prog,
            'year'      => $yr,
            'block'     => $blk,
            'day'       => $day !== '' ? $day : '—',
            'time_slot' => $timeSlot !== '' ? $timeSlot : '—',
            'room'      => $room !== '' ? $room : ($mode === 'Online' ? 'Online Room' : '—'),
            'mode'      => $mode
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCDI Teacher Portal - Live Sessions</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .live-header-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 18px 22px;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
            margin-bottom: 16px;
        }
        .live-teacher-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }
        .live-teacher-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #0b2545;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 700;
        }
        .live-teacher-name {
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
        }
        .live-subnote {
            font-size: 12px;
            color: #64748b;
            margin: 2px 0 0 0;
        }
        .block-filter-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .block-pill {
            background: #e2e8f0;
            color: #1e293b;
            border: none;
            border-radius: 999px;
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }
        .block-pill.active,
        .block-pill:hover {
            background: #1e6091;
            color: #ffffff;
        }
        .live-sessions-panel {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 20px;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);
        }
        .live-sessions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(265px, 1fr));
            gap: 16px;
        }
        .live-session-card {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-top: 4px solid #1e6091;
            border-radius: 14px;
            padding: 16px 18px;
            text-align: left;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .live-session-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
        }
        .live-card-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 8px;
            margin-bottom: 10px;
        }
        .live-session-title {
            font-size: 16px;
            font-weight: 800;
            color: #0b2545;
            margin: 0;
        }
        .mode-badge {
            font-size: 11px;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 999px;
            white-space: nowrap;
        }
        .mode-badge.f2f {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #86efac;
        }
        .mode-badge.online {
            background: #e0f2fe;
            color: #0369a1;
            border: 1px solid #7dd3fc;
        }
        .live-detail-list {
            font-size: 13px;
            color: #334155;
            line-height: 1.65;
        }
        .live-detail-list strong {
            color: #0f172a;
        }
    </style>
</head>
<body>

    <header class="topbar" style="background-color: #0b2545;">
        <div class="brand">
            <div class="brand-logo"><img src="logo.jpg" alt="logo" style="width:100%; height:100%; object-fit:contain;"></div>
            <span>CCDI Teacher Portal</span>
        </div>
        <input type="text" id="liveSessionSearch" class="search-bar" placeholder="Search">
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
        <a href="teacher_live.php" class="active">Live Sessions</a>
        <a href="teacher_settings.php">Settings</a>
    </nav>

    <main class="main-wrapper">
        <div class="live-header-card">
            <div class="live-teacher-row">
                <div class="live-teacher-avatar">&#128100;</div>
                <div>
                    <h1 class="live-teacher-name"><?php echo htmlspecialchars($teacher_name); ?></h1>
                    <p class="live-subnote">Assigned live sessions from Admin Manage Rooms</p>
                </div>
            </div>

            <?php if (!empty($blocks)): ?>
                <div class="block-filter-pills" id="liveBlockPills">
                    <button type="button" class="block-pill active" data-block="all">All Blocks</button>
                    <?php foreach ($blocks as $b): ?>
                        <button type="button" class="block-pill" data-block="<?php echo htmlspecialchars(strtolower($b), ENT_QUOTES); ?>">
                            <?php echo htmlspecialchars($b); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="live-sessions-panel">
            <?php if (!empty($sessions)): ?>
                <div class="live-sessions-grid" id="liveSessionsGrid">
                    <?php foreach ($sessions as $s): ?>
                        <?php
                            $is_online = (strtolower($s['mode']) === 'online');
                            $badge_cls = $is_online ? 'online' : 'f2f';
                        ?>
                        <div class="live-session-card js-live-card" data-block="<?php echo htmlspecialchars(strtolower($s['block']), ENT_QUOTES); ?>">
                            <div class="live-card-top">
                                <h2 class="live-session-title"><?php echo htmlspecialchars($s['subject']); ?></h2>
                                <span class="mode-badge <?php echo $badge_cls; ?>">
                                    <?php echo htmlspecialchars($s['mode']); ?>
                                </span>
                            </div>

                            <div class="live-detail-list">
                                <div><strong>Program:</strong> <?php echo htmlspecialchars($s['program']); ?></div>
                                <div><strong>Subject:</strong> <?php echo htmlspecialchars($s['subject']); ?></div>
                                <div><strong>Block:</strong> <?php echo htmlspecialchars($s['block']); ?></div>
                                <div><strong>Day:</strong> <?php echo htmlspecialchars($s['day']); ?></div>
                                <div><strong>Time Slot:</strong> <?php echo htmlspecialchars($s['time_slot']); ?></div>
                                <div><strong>Room:</strong> <?php echo htmlspecialchars($s['room']); ?></div>
                                <div><strong>Setup:</strong> <?php echo htmlspecialchars($s['mode']); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align:center; color:#64748b; padding:24px; font-size:13px;">
              No live session has been assigned to your account yet via Admin Manage Rooms.
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        (function() {
            var activeBlock = 'all';
            var searchInput = document.getElementById('liveSessionSearch');
            var pills = document.querySelectorAll('#liveBlockPills .block-pill');
            var cards = document.querySelectorAll('.js-live-card');

            function filterCards() {
                var q = searchInput ? searchInput.value.toLowerCase().trim() : '';
                cards.forEach(function(card) {
                    var blk = card.getAttribute('data-block') || '';
                    var txt = card.textContent.toLowerCase();
                    var matchesBlock = (activeBlock === 'all' || blk === activeBlock || txt.indexOf(activeBlock) !== -1);
                    var matchesSearch = (q === '' || txt.indexOf(q) !== -1);
                    card.style.display = (matchesBlock && matchesSearch) ? 'block' : 'none';
                });
            }

            pills.forEach(function(pill) {
                pill.addEventListener('click', function() {
                    pills.forEach(function(p) { p.classList.remove('active'); });
                    this.classList.add('active');
                    activeBlock = this.getAttribute('data-block') || 'all';
                    filterCards();
                });
            });

            if (searchInput) {
                searchInput.addEventListener('input', filterCards);
            }
        })();
    </script>
</body>
</html>