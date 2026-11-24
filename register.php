<?php
include 'db.php';
$msg = "";
$error = "";

if (isset($conn) && $conn instanceof mysqli) {
    $col_res = @$conn->query("SHOW COLUMNS FROM `users` LIKE 'student_id'");
    if (!$col_res || $col_res->num_rows === 0) {
        @$conn->query("ALTER TABLE `users` ADD COLUMN `student_id` VARCHAR(50) NULL DEFAULT NULL");
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $conn->real_escape_string(trim($_POST['username'] ?? ''));
    $email = $conn->real_escape_string(trim($_POST['email'] ?? ''));
    $password = $conn->real_escape_string(trim($_POST['password'] ?? ''));
    $role = $conn->real_escape_string(trim($_POST['role'] ?? ''));
    $student_id = $conn->real_escape_string(trim($_POST['student_id'] ?? ''));
    $course = $conn->real_escape_string($_POST['course'] ?? '');
    $year_raw = $_POST['year_level'] ?? ($_POST['year'] ?? '');
    $year = !empty($year_raw) ? "'" . $conn->real_escape_string($year_raw) . "'" : "NULL";
    $block = !empty($_POST['block']) ? "'" . $conn->real_escape_string($_POST['block']) . "'" : "NULL";

    $status = ($role === 'teacher') ? 'Pending' : 'Approved';

    if ($role === 'student' && $student_id === '') {
        $error = "Kailangang i-input ang iyong Student ID.";
    } else {
        $check_sql = "SELECT id FROM users WHERE username='$username'";
        if ($email !== '') {
            $check_sql .= " OR email='$email'";
        }
        if ($role === 'student' && $student_id !== '') {
            $check_sql .= " OR student_id='$student_id'";
        }
        
   
    if ($role === 'student' && $student_id !== '') {
        $sid_check_sql = "SELECT id FROM `users` 
                      WHERE LOWER(TRIM(`student_id`)) = LOWER(TRIM('{$student_id}')) 
                      LIMIT 1";
        $sid_check = $conn->query($sid_check_sql);
    if ($sid_check && $sid_check->num_rows > 0) {
        $error = "The Student ID '{$student_id}' is already being used by another student.";
        }
        }
        $check = $conn->query($check_sql);
        if ($check && $check->num_rows > 0) {
            $error = "The Username, Email, or Student ID you entered is already in use. Please choose a different one.";
        } else {
            $sid_sql = ($role === 'student') ? "'$student_id'" : "NULL";
            $sql = "INSERT INTO users (full_name, username, email, student_id, password, course, year_level, block, role, status) 
                    VALUES ('$username', '$username', '$email', $sid_sql, '$password', '$course', $year, $block, '$role', '$status')";
            
            if ($conn->query($sql) === TRUE) {
                if ($status === 'Pending') {
                    $msg = "Teacher registration submitted! Please wait for the Admin to approve your account before logging in..";
                } else {
                    $msg = "Account created successfully! <a href='login.php'>Login here</a>";
                }
            } else {
                $error = "Database Error: " . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCDI Learning Portal - Sign Up</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-body" style="background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('3.png') no-repeat center center/cover;">

    <div class="auth-card" style="padding: 20px 30px; max-height: 95vh; overflow-y: auto;">
        <h1>CCDI Learning Portal</h1>
        <div class="auth-logo" style="width:50px; height:50px; font-size:14px; margin-bottom:10px;"><img src="logo.jpg" alt="logo" style="width:100%; height:100%; object-fit:contain;"></div>

        <?php if($msg): ?>
            <p style="color: #16a34a; font-size:13px; margin-bottom:10px; font-weight:600;"><?php echo $msg; ?></p>
        <?php endif; ?>
        <?php if($error): ?>
            <p style="color: #dc2626; font-size:13px; margin-bottom:10px; font-weight:600;"><?php echo $error; ?></p>
        <?php endif; ?>

        <form method="POST">
            <div class="auth-group" style="margin-bottom:8px;">
                <label>Username / Full Name</label>
                <input type="text" name="username" placeholder="Username" required>
            </div>
            <div class="auth-group" style="margin-bottom:8px;">
                <label>Email address</label>
                <input type="email" name="email" placeholder="Enter Email address" >
            </div>
            <div class="auth-group" style="margin-bottom:8px;">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter Password" required>
            </div>
            <div class="auth-group" style="margin-bottom:8px;">
                <label>Role</label>
                <select name="role" id="roleSelect" onchange="toggleStudentFields()" required>
                    <option value="">Select Role</option>
                    <option value="student">Student</option>
                    <option value="teacher">Teacher</option>
                </select>
            </div>
            <div class="auth-group student-only" id="studentIdGroup" style="margin-bottom:8px;">
                <label>Student ID</label>
                <input type="text" name="student_id" id="studentIdInput" placeholder="Enter Student ID">
            </div>
            <div class="auth-group" style="margin-bottom:8px;">
                <label>Course / Department</label>
                <select name="course" required>
                    <option value="">Select Course</option>
                    <option value="Bachelor of Science in Information Technology (BSIT)">BSIT</option>
                    <option value="Bachelor of Science in Computer Science (BSCS)">BSCS</option>
                    <option value="BS Information Systems (BSIS)">BSIS</option>
                    <option value="BS office administration">BSOAd</option>
                </select>
            </div>
            <div class="auth-group student-only" style="margin-bottom:8px;">
                <label>Year Level</label>
                <select name="year_level" id="yearSelect" onchange="updateBlocks()">
                    <option value="">Select Year</option>
                    <option value="1st Year">1st Year</option>
                    <option value="2nd Year">2nd Year</option>
                    <option value="3rd Year">3rd Year</option>
                    <option value="4th Year">4th Year</option>
                </select>
            </div>
            <div class="auth-group student-only" style="margin-bottom:12px;">
                <label>Block</label>
                <select name="block" id="blockSelect">
                    <option value="">Select Year First</option>
                </select>
            </div>
            <button type="submit" class="auth-btn">Create Account</button>
        </form>

        <div class="auth-links" style="margin-top:10px;">
            <a href="login.php">Already have an account? Log in</a>
        </div>
    </div>

    <script>
       
        const blocksByYear = {
            "1st Year": ["Block - 1A", "Block - 1B", "Block - 1C"],
            "2nd Year": ["Block - 2A", "Block - 2B", "Block - 2C"],
            "3rd Year": ["Block - 3A", "Block - 3B", "Block - 3C"],
            "4th Year": ["Block - 4A", "Block - 4B", "Block - 4C"]
        };

        function updateBlocks() {
            const yearSelect = document.getElementById("yearSelect");
            const blockSelect = document.getElementById("blockSelect");
            const selectedYear = yearSelect.value;

            blockSelect.innerHTML = "";

            if (selectedYear && blocksByYear[selectedYear]) {
                const defaultOpt = document.createElement("option");
                defaultOpt.value = "";
                defaultOpt.textContent = "Select Block";
                blockSelect.appendChild(defaultOpt);

                blocksByYear[selectedYear].forEach(block => {
                    const opt = document.createElement("option");
                    opt.value = block;
                    opt.textContent = block;
                    blockSelect.appendChild(opt);
                });
            } else {
                const defaultOpt = document.createElement("option");
                defaultOpt.value = "";
                defaultOpt.textContent = "Select Year First";
                blockSelect.appendChild(defaultOpt);
            }
        }

        function toggleStudentFields() {
            const role = document.getElementById('roleSelect').value;
            const studentFields = document.querySelectorAll('.student-only');
            const sidInput = document.getElementById('studentIdInput');

            studentFields.forEach(field => {
                field.style.display = (role === 'teacher') ? 'none' : 'block';
            });

            if (sidInput) {
                sidInput.required = (role === 'student');
                if (role === 'teacher') {
                    sidInput.value = '';
                }
            }
        }
    </script>

</body>
</html>