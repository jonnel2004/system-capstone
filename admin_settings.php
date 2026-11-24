<?php
if (session_id() === '') {
    @session_start();
}
include 'db.php';

// Security check: only Admin can enter this pages.
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

$admin_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCDI Admin Portal - System Settings</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .settings-wrapper {
            max-width: 680px;
            margin: 16px auto 0 auto;
        }
        .settings-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 100px 28px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            text-align: center;
        }
        .settings-avatar-ring {
            width: 96px;
            height: 96px;
            margin: 0 auto 14px auto;
            border-radius: 50%;
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            border: 2px solid #93c5fd;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0b2545;
            font-size: 40px;
            box-shadow: 0 6px 16px rgba(30, 96, 145, 0.12);
        }
        .settings-role-title {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 4px 0;
        }
        .settings-subtitle {
            font-size: 13px;
            color: #64748b;
            margin: 0 0 24px 0;
        }
        .settings-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
            max-width: 480px;
            margin: 0 auto;
        }
        .settings-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 13px 18px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
        }
        .settings-btn-primary {
            background: #1e6091;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(30, 96, 145, 0.22);
        }
        .settings-btn-primary:hover {
            background: #0b2545;
            transform: translateY(-1px);
        }
        .settings-btn-secondary {
            background: #f8fafc;
            color: #0b2545;
            border: 1px solid #cbd5e1;
        }
        .settings-btn-secondary:hover {
            background: #e2e8f0;
            transform: translateY(-1px);
        }
        @media (max-width: 600px) {
            .settings-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <header class="topbar" style="background-color: #0b2545;">
        <div class="brand">
            <div class="brand-logo"><img src="logo.jpg" alt="logo" style="width:100%; height:100%; object-fit:contain;"></div>
            <span>CCDI Admin Portal</span>
        </div>
        <input type="text" id="settingsSearchInput" class="search-bar" placeholder="Search">
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
        <a href="admin_program.php">Manage Programs</a>
        <a href="admin_rooms.php">Manage Rooms</a>
        <a href="admin_settings.php" class="active">System Settings</a>
    </nav>

    <main class="main-wrapper">
        <div class="page-header">
            <h1>SETTINGS</h1>
            <p>Account &amp; system preferences</p>
        </div>

        <div class="settings-wrapper">
            <div class="settings-card">
                <div class="settings-avatar-ring">&#128100;</div>
                <h2 class="settings-role-title">Admin</h2>
                <p class="settings-subtitle">Administrator Account Controls</p>

                <div class="settings-actions" id="settingsActionsList">
                    <a href="admin_dashboard.php" class="settings-btn settings-btn-primary">Manage</a>
                    <a href="admin_students.php" class="settings-btn settings-btn-secondary">Report</a>
                </div>
            </div>
        </div>
    </main>

    <script>
        (function() {
            var searchInput = document.getElementById('settingsSearchInput');
            var actionsList = document.getElementById('settingsActionsList');
            if (!searchInput || !actionsList) return;

            searchInput.addEventListener('input', function() {
                var q = this.value.toLowerCase().trim();
                actionsList.querySelectorAll('.settings-btn').forEach(function(btn) {
                    var text = btn.textContent.toLowerCase();
                    btn.style.display = (q === '' || text.indexOf(q) !== -1) ? 'flex' : 'none';
                });
            });
        })();
    </script>
</body>
</html>