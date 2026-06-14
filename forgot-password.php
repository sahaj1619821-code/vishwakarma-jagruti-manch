<?php
session_start();

$conn = mysqli_connect("127.0.0.1", "root", "", "vjm_db", 3307);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$message = "";
$error = "";
$step = 1;
$found_user = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (isset($_POST['find_user'])) {

        $mobile_email = trim($_POST['mobile_email'] ?? '');
        $role = trim($_POST['role'] ?? '');

        if ($mobile_email == "" || $role == "") {
            $error = "Mobile/email aur role select kare";
        } else {

            $stmt = $conn->prepare("
                SELECT id, name, mobile, email, role 
                FROM users 
                WHERE (mobile = ? OR email = ? OR name = ?) 
                AND role = ? 
                LIMIT 1
            ");

            $stmt->bind_param("ssss", $mobile_email, $mobile_email, $mobile_email, $role);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 0) {
                $error = "User not found or role wrong";
            } else {
                $found_user = $result->fetch_assoc();
                $step = 2;
            }
        }
    }

    if (isset($_POST['reset_password'])) {

        $user_id = intval($_POST['user_id'] ?? 0);
        $new_password = trim($_POST['new_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');

        if ($user_id <= 0 || $new_password == "" || $confirm_password == "") {
            $error = "All fields are required";
            $step = 2;
        } elseif ($new_password != $confirm_password) {
            $error = "New password and confirm password do not match";
            $step = 2;
        } elseif (strlen($new_password) < 6) {
            $error = "Password minimum 6 characters ka hona chahiye";
            $step = 2;
        } else {

            $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashedPassword, $user_id);

            if ($stmt->execute()) {
                $message = "Password reset successfully. Ab login kare.";
                $step = 1;
            } else {
                $error = "Password reset failed";
                $step = 2;
            }
        }
    }
}

/* Active roles */
$roles = mysqli_query($conn, "SELECT * FROM roles WHERE status='active' ORDER BY role_name ASC");
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password - VJM Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            background: radial-gradient(circle at top, #3b0010, #070000 70%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 18px;
        }

        .password-card {
            width: 100%;
            max-width: 520px;
            background: #260006;
            border: 1px solid #ff9800;
            border-radius: 22px;
            padding: 35px;
            box-shadow: 0 25px 70px rgba(0,0,0,.45);
        }

        h2 {
            margin: 0 0 25px;
            color: #ffad3b;
            text-align: center;
            font-size: 34px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            font-size: 17px;
        }

        input,
        select {
            width: 100%;
            padding: 15px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,.25);
            background: #fffaf3;
            color: #fff;
            font-size: 16px;
            outline: none;
            margin-bottom: 18px;
        }

        select option {
            background: #101827;
            color: #fff;
        }

        input:focus,
        select:focus {
            border-color: #ff9800;
            box-shadow: 0 0 0 3px rgba(255,152,0,.18);
        }

        .btn {
            width: 100%;
            border: none;
            padding: 15px;
            border-radius: 10px;
            background: linear-gradient(135deg, #ffc328, #ff8a00);
            color: #fff;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
        }

        .back-btn {
            display: block;
            text-align: center;
            margin-top: 18px;
            color: #ffad3b;
            text-decoration: none;
            font-weight: bold;
        }

        .message {
            background: rgba(0,180,90,.2);
            border: 1px solid #00c46a;
            color: #d8ffe9;
            padding: 13px;
            border-radius: 8px;
            margin-bottom: 18px;
        }

        .error {
            background: #f8d0d5;
            color: #79000b;
            padding: 13px;
            border-radius: 8px;
            margin-bottom: 18px;
        }

        .user-box {
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.18);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 18px;
        }

        .user-box p {
            margin: 5px 0;
        }
    </style>
    <link rel="stylesheet" href="css/style.css?v=10">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<div class="password-card">

    <h2>Forgot Password</h2>

    <?php if ($message != "") { ?>
        <div class="message"><?= e($message); ?></div>
    <?php } ?>

    <?php if ($error != "") { ?>
        <div class="error"><?= e($error); ?></div>
    <?php } ?>

    <?php if ($step == 1) { ?>

        <form method="POST">

            <label>User Role</label>
            <select name="role" required>
                <option value="">Select Role</option>

                <?php
                if ($roles && mysqli_num_rows($roles) > 0) {
                    while ($roleRow = mysqli_fetch_assoc($roles)) {
                ?>
                    <option value="<?= e($roleRow['role_key']); ?>">
                        <?= e($roleRow['role_name']); ?>
                    </option>
                <?php
                    }
                }
                ?>
            </select>

            <label>Username / Mobile / Email</label>
            <input type="text" name="mobile_email" placeholder="Enter username, mobile or email" required>

            <button type="submit" name="find_user" class="btn">Find Account</button>

        </form>

    <?php } ?>

    <?php if ($step == 2 && $found_user) { ?>

        <div class="user-box">
            <p><b>Name:</b> <?= e($found_user['name']); ?></p>
            <p><b>Mobile:</b> <?= e($found_user['mobile']); ?></p>
            <p><b>Email:</b> <?= e($found_user['email']); ?></p>
            <p><b>Role:</b> <?= e($found_user['role']); ?></p>
        </div>

        <form method="POST">

            <input type="hidden" name="user_id" value="<?= intval($found_user['id']); ?>">

            <label>New Password</label>
            <input type="password" name="new_password" placeholder="Enter new password" required>

            <label>Confirm Password</label>
            <input type="password" name="confirm_password" placeholder="Confirm password" required>

            <button type="submit" name="reset_password" class="btn">Reset Password</button>

        </form>

    <?php } ?>

    <a href="index.php" class="back-btn">Back to Login</a>

</div>

</body>
</html>