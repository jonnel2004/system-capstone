<?php
session_start();
include 'db.php';
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input_user = $conn->real_escape_string(trim($_POST['username']));
    $input_pass = trim($_POST['password']);

    $sql = "SELECT * FROM users WHERE username = '$input_user' OR email = '$input_user' OR full_name = '$input_user'";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        if ($input_pass == $user['password']) {
            
            if (($user['status'] ?? 'Approved') === 'Pending') {
                $error = "Your Teacher account is not yet approved by the Admin. Please try again later.";
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];

                
                if ($user['role'] == 'admin') {
                    header("Location: admin_dashboard.php");
                } elseif ($user['role'] == 'teacher') {
                    header("Location: teacher_dashboard.php");
                } else {
                    header("Location: dashboard.php");
                }
                exit();
            }
        } else {
           
            $error = "Your password is incorrect.";
        }
    } else {
       
        $error = "No account found for this username or email.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCDI Learning Portal - Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-body">

    <div class="auth-card">
        <h1>CCDI Learning Portal</h1>
        <div class="auth-logo"><img src="logo.jpg" alt="logo" style="width:100%; height:100%; object-fit:contain;"></div>

        <?php if($error): ?>
            <p style="color: #dc2626; font-size:13px; margin-bottom:10px; font-weight:600;"><?php echo $error; ?></p>
        <?php endif; ?>

        <form method="POST">
            <div class="auth-group">
                <label>Username / Email address</label>
                <input type="text" name="username" placeholder="Username/Email address" required>
            </div>
            <div class="auth-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter Password" required>
            </div>
            <button type="submit" class="auth-btn">Login</button>
        </form>

        <div class="auth-links">
            don't have account <a href="register.php">Sign up</a><br>
            <a href="#" style="display:inline-block; margin-top:8px;">Forgot Password</a>
        </div>
    </div>

</body>
</html>