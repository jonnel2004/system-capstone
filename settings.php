<?php
if (session_id() === '') {
    @session_start();
}
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);


$user_cols = [];
$col_res = $conn->query("SHOW COLUMNS FROM users");
if ($col_res) {
    while ($c = $col_res->fetch_assoc()) {
        $user_cols[] = $c['Field'];
    }
}
if (!in_array('student_id', $user_cols, true)) {
    $conn->query("ALTER TABLE users ADD COLUMN student_id VARCHAR(50) NULL DEFAULT NULL");
    @$conn->query("ALTER TABLE users ADD UNIQUE KEY uq_users_student_id (student_id)");
    $user_cols[] = 'student_id';
}
if (!in_array('profile_pic', $user_cols, true)) {
    $conn->query("ALTER TABLE users ADD COLUMN profile_pic VARCHAR(255) NULL DEFAULT NULL");
    $user_cols[] = 'profile_pic';
}

$name_col = in_array('full_name', $user_cols, true) ? 'full_name' : (in_array('username', $user_cols, true) ? 'username' : '');


$student = null;
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows > 0) {
    $student = $res->fetch_assoc();
}
$stmt->close();

if (!$student) {
    header("Location: login.php");
    exit();
}

$save_msg = '';
$save_err = '';
$open_edit_form = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile_basic'])) {
    $open_edit_form = true;
    $new_name = trim((string)($_POST['student_name'] ?? ''));
    $new_email = trim((string)($_POST['student_email'] ?? ''));
    $new_sid = trim((string)($_POST['student_id'] ?? ''));
    $new_pic_path = trim((string)($student['profile_pic'] ?? ''));

    if ($new_name === '') {
        $save_err = 'Ilagay ang pangalan mo.';
    } elseif ($new_email === '' || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $save_err = 'Maglagay ng valid na email address.';
    } elseif ($new_sid !== '') {
        $dup_stmt = $conn->prepare("SELECT id FROM users WHERE LOWER(TRIM(student_id)) = LOWER(TRIM(?)) AND id <> ? LIMIT 1");
        $dup_stmt->bind_param("si", $new_sid, $user_id);
        $dup_stmt->execute();
        $dup_exists = ($dup_stmt->get_result()->num_rows > 0);
        $dup_stmt->close();

        if ($dup_exists) {
            $save_err = "Student IDs cannot be duplicated. The Student ID '{$new_sid}' is already being used by another student.";
        }
    }

    if ($save_err === '' && isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $tmp_name = $_FILES['profile_pic']['tmp_name'];
            $orig_name = $_FILES['profile_pic']['name'];
            $file_size = intval($_FILES['profile_pic']['size']);
            $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];

            if (!in_array($ext, $allowed_ext, true)) {
                $save_err = 'Only JPG, JPEG, PNG, or WEBP files are allowed for the profile picture.';
            } elseif ($file_size > 2 * 1024 * 1024) {
                $save_err = 'The maximum file size for the profile picture is 2MB.';
            } else {
                $upload_dir = __DIR__ . '/uploads/profiles';
                if (!is_dir($upload_dir)) {
                    @mkdir($upload_dir, 0777, true);
                }
                $new_filename = 'student_' . $user_id . '_' . time() . '.' . $ext;
                $target_path = $upload_dir . '/' . $new_filename;
                $db_path = 'uploads/profiles/' . $new_filename;

                if (move_uploaded_file($tmp_name, $target_path)) {
                    $new_pic_path = $db_path;
                } else {
                    $save_err = 'There is a problem uploading the profile picture..';
                }
            }
        } else {
            $save_err = 'There is a problem uploading the profile picture..';
        }
    }

    if ($save_err === '') {
        $sid_val = ($new_sid !== '') ? $new_sid : null;

        if ($name_col !== '') {
            $sql = "UPDATE users SET {$name_col} = ?, email = ?, student_id = ?, profile_pic = ? WHERE id = ?";
            $upd = $conn->prepare($sql);
            $upd->bind_param("ssssi", $new_name, $new_email, $sid_val, $new_pic_path, $user_id);
        } else {
            $sql = "UPDATE users SET email = ?, student_id = ?, profile_pic = ? WHERE id = ?";
            $upd = $conn->prepare($sql);
            $upd->bind_param("sssi", $new_email, $sid_val, $new_pic_path, $user_id);
        }

        try {
            if ($upd->execute()) {
                if ($name_col !== '') {
                    $student[$name_col] = $new_name;
                }
                $student['email'] = $new_email;
                $student['student_id'] = $sid_val ?? '';
                $student['profile_pic'] = $new_pic_path;
                $_SESSION['profile_pic'] = $new_pic_path;
                $save_msg = 'Na-update na ang impormasyon mo!';
                $open_edit_form = false;
            } else {
                $save_err = 'An error occurred while saving the changes.';
            }
        } catch (mysqli_sql_exception $e) {
            if ((int)$e->getCode() === 1062 || stripos($e->getMessage(), 'Duplicate entry') !== false) {
                $save_err = "Student IDs cannot be duplicated. '{$new_sid}'. That is already being used by another student..";
            } else {
                $save_err = 'An error occurred while saving the changes.';
            }
        }
        $upd->close();
    }
}

$student_id_raw = trim((string)($student['student_id'] ?? ''));
$student_no = ($student_id_raw !== '') ? $student_id_raw : '—';
$student_name = trim((string)($student['full_name'] ?? $student['username'] ?? 'Student'));
$student_email = trim((string)($student['email'] ?? ''));
$student_program = trim((string)($student['program'] ?? $student['course'] ?? ''));
$student_block = trim((string)($student['block'] ?? ''));
$profile_pic_path = trim((string)($student['profile_pic'] ?? ''));

$avatar_pic = trim((string)($profile_pic_path !== '' ? $profile_pic_path : ($_SESSION['profile_pic'] ?? '')));
$has_avatar_pic = ($avatar_pic !== '' && file_exists(__DIR__ . '/' . $avatar_pic));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCDI Learning Portal - Settings</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .settings-wrapper {
            max-width: 680px;
            margin: 16px auto 0 auto;
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
            font-size: 38px;
            overflow: hidden;
            box-shadow: 0 6px 16px rgba(30, 96, 145, 0.12);
        }
        .settings-avatar-ring img {
            width: 100%;
            height: 100%;
            object-fit: cover;
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
            margin: 0 0 16px 0;
        }
        .settings-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 40px 28px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            text-align: center;
        }
        .student-id-box {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
            padding: 8px 16px;
            border-radius: 999px;
            font-size: 14px;
            font-weight: 800;
            margin-bottom: 18px;
        }
        .edit-profile-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: #1e6091;
            color: #ffffff;
            border: none;
            border-radius: 10px;
            padding: 10px 18px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            margin-bottom: 18px;
            transition: background-color 0.15s ease, transform 0.15s ease;
        }
        .edit-profile-btn:hover {
            background: #0b2545;
            transform: translateY(-1px);
        }
        .edit-profile-panel {
            display: none;
            text-align: left;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 14px;
            padding: 18px;
            margin-bottom: 20px;
        }
        .edit-profile-panel.open {
            display: block;
        }
        .form-row-group {
            margin-bottom: 12px;
        }
        .form-row-group label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #334155;
            margin-bottom: 4px;
        }
        .form-row-group input[type="text"],
        .form-row-group input[type="email"],
        .form-row-group input[type="file"] {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            background: #ffffff;
        }
        .edit-actions {
            display: flex;
            gap: 10px;
            margin-top: 14px;
        }
        .btn-save-profile {
            background: #1e6091;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            padding: 9px 16px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }
        .btn-cancel-profile {
            background: #e2e8f0;
            color: #1e293b;
            border: none;
            border-radius: 8px;
            padding: 9px 14px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }
        .student-info-list {
            text-align: left;
            border-top: 1px solid #e2e8f0;
            padding-top: 16px;
            display: grid;
            gap: 10px;
            font-size: 14px;
        }
        .student-info-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 6px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .student-info-label {
            color: #64748b;
            font-weight: 600;
        }
        .student-info-value {
            color: #0f172a;
            font-weight: 700;
            text-align: right;
        }
    </style>
</head>
<body>

    <header class="topbar" style="background-color: #0b2545;">
        <div class="brand">
            <div class="brand-logo"><img src="logo.jpg" alt="logo" style="width:100%; height:100%; object-fit:contain;"></div>
            <span>CCDI Learning Portal</span>
        </div>
        <input type="text" class="search-bar" placeholder="Search">
        <div class="user-menu">
        <div class="user-avatar" style="overflow:hidden; padding:0; display:flex; align-items:center; justify-content:center;">
          <?php if ($has_avatar_pic): ?>
          <img src="<?php echo htmlspecialchars($avatar_pic); ?>" alt="Student Profile" style="width:100%; height:100%; object-fit:cover; display:block;">
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
        <a href="subjects.php">My Subjects</a>
        <a href="activity_feed.php">Activities</a>
        <a href="live_sessions.php">Live Sessions</a>
        <a href="settings.php" class="active">Settings</a>
    </nav>

    <main class="main-wrapper">
        <div class="page-header">
            <h1>SETTINGS</h1>
            <p>Student profile and account settings</p>
        </div>

        <div class="settings-wrapper">
            <div class="settings-card">
                <div class="settings-avatar-ring">
                    <?php if ($profile_pic_path !== '' && file_exists(__DIR__ . '/' . $profile_pic_path)): ?>
                        <img src="<?php echo htmlspecialchars($profile_pic_path); ?>" alt="Profile Picture">
                    <?php else: ?>
                        <span>&#128100;</span>
                    <?php endif; ?>
                </div>

                <h2 class="settings-role-title"><?php echo htmlspecialchars($student_name); ?></h2>
                <p class="settings-subtitle">Student Account</p>

                <div class="student-id-box">
                    <span>Student ID:</span>
                    <span><?php echo htmlspecialchars($student_no); ?></span>
                </div>

                <?php if ($save_msg !== ''): ?>
                    <p style="color:#15803d; font-size:13px; font-weight:700; margin-bottom:12px;"><?php echo htmlspecialchars($save_msg); ?></p>
                <?php endif; ?>
                <?php if ($save_err !== ''): ?>
                    <p style="color:#b91c1c; font-size:13px; font-weight:700; margin-bottom:12px;"><?php echo htmlspecialchars($save_err); ?></p>
                <?php endif; ?>

                <div>
                    <button type="button" id="toggleEditProfileBtn" class="edit-profile-btn">
                        Edit Student ID, Name, Email &amp; Profile
                    </button>
                </div>

                <form method="POST" enctype="multipart/form-data" id="editProfilePanel" class="edit-profile-panel<?php echo $open_edit_form ? ' open' : ''; ?>">
                    <div class="form-row-group">
                        <label for="student_id">Student ID</label>
                        <input type="text" id="student_id" name="student_id" value="<?php echo htmlspecialchars($student_id_raw); ?>" placeholder="Enter Student ID">
                    </div>

                    <div class="form-row-group">
                        <label for="student_name">Name</label>
                        <input type="text" id="student_name" name="student_name" value="<?php echo htmlspecialchars($student_name); ?>" required>
                    </div>

                    <div class="form-row-group">
                        <label for="student_email">Email</label>
                        <input type="email" id="student_email" name="student_email" value="<?php echo htmlspecialchars($student_email); ?>" required>
                    </div>

                    <div class="form-row-group">
                        <label for="profile_pic">Profile Picture (JPG, PNG, WEBP — max 2MB)</label>
                        <input type="file" id="profile_pic" name="profile_pic" accept=".jpg,.jpeg,.png,.webp">
                    </div>

                    <div class="edit-actions">
                        <button type="submit" name="update_profile_basic" class="btn-save-profile">Save Changes</button>
                        <button type="button" id="cancelEditProfileBtn" class="btn-cancel-profile">Cancel</button>
                    </div>
                </form>

                <div class="student-info-list">
                    <div class="student-info-row">
                        <span class="student-info-label">Student ID</span>
                        <span class="student-info-value"><?php echo htmlspecialchars($student_no); ?></span>
                    </div>
                    <div class="student-info-row">
                        <span class="student-info-label">Name</span>
                        <span class="student-info-value"><?php echo htmlspecialchars($student_name); ?></span>
                    </div>
                    <?php if ($student_email !== ''): ?>
                    <div class="student-info-row">
                        <span class="student-info-label">Email</span>
                        <span class="student-info-value"><?php echo htmlspecialchars($student_email); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($student_program !== ''): ?>
                    <div class="student-info-row">
                        <span class="student-info-label">Program</span>
                        <span class="student-info-value"><?php echo htmlspecialchars($student_program); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($student_block !== ''): ?>
                    <div class="student-info-row">
                        <span class="student-info-label">Block</span>
                        <span class="student-info-value"><?php echo htmlspecialchars($student_block); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        (function() {
            var toggleBtn = document.getElementById('toggleEditProfileBtn');
            var cancelBtn = document.getElementById('cancelEditProfileBtn');
            var panel = document.getElementById('editProfilePanel');
            if (!toggleBtn || !panel) return;

            toggleBtn.addEventListener('click', function() {
                panel.classList.toggle('open');
            });

            if (cancelBtn) {
                cancelBtn.addEventListener('click', function() {
                    panel.classList.remove('open');
                });
            }
        })();
    </script>
</body>
</html>