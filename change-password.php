<?php
session_start();

$conn = mysqli_connect("127.0.0.1", "root", "", "vjm_db", 3307);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/* Login check */
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$admin_id = intval($_SESSION['admin_id']);
$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $old_password = trim($_POST['old_password'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if ($old_password == "" || $new_password == "" || $confirm_password == "") {
        $error = "All fields are required";
    } elseif ($new_password != $confirm_password) {
        $error = "New password and confirm password do not match";
    } elseif (strlen($new_password) < 6) {
        $error = "Password minimum 6 characters ka hona chahiye";
    } else {

        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 0) {
            $error = "Admin user not found";
        } else {
            $user = $result->fetch_assoc();
            $db_password = $user['password'];

            if (password_verify($old_password, $db_password) || $old_password === $db_password) {

                $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);

                $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update->bind_param("si", $hashedPassword, $admin_id);

                if ($update->execute()) {
                    $message = "Password changed successfully";
                } else {
                    $error = "Password not changed";
                }

            } else {
                $error = "Old password is wrong";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <title>Change Password - VJM Admin</title>
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
            max-width: 500px;
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

        input {
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

        input:focus {
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
    </style>
        <link rel="stylesheet" href="css/style.css?v=10">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<div class="password-card">

    <h2>Change Password</h2>

    <?php if ($message != "") { ?>
        <div class="message"><?= e($message); ?></div>
    <?php } ?>

    <?php if ($error != "") { ?>
        <div class="error"><?= e($error); ?></div>
    <?php } ?>

    <form method="POST">

        <label>Old Password</label>
        <input type="password" name="old_password" placeholder="Enter old password" required>

        <label>New Password</label>
        <input type="password" name="new_password" placeholder="Enter new password" required>

        <label>Confirm New Password</label>
        <input type="password" name="confirm_password" placeholder="Confirm new password" required>

        <button type="submit" class="btn">Change Password</button>

    </form>

    <a href="dashboard.php" class="back-btn">Back to Dashboard</a>

</div>

</body>
</html>