<?php
$conn = mysqli_connect("127.0.0.1", "root", "", "vjm_db", 3307);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

$user_value = trim(
    $_GET['user'] 
    ?? $_POST['user'] 
    ?? $_GET['mobile_email'] 
    ?? $_POST['mobile_email'] 
    ?? $_GET['email'] 
    ?? $_POST['email'] 
    ?? ''
);
$message = "";

if ($user_value == "") {
    echo "<script>
        alert('Please enter email or mobile number first');
        window.location.href='forgot-password.php';
    </script>";
    exit;
}

/* User check */
$stmt = $conn->prepare("SELECT id, full_name, email, mobile, mobile_email 
                        FROM matrimonial_users 
                        WHERE email = ? OR mobile = ? OR mobile_email = ?
                        LIMIT 1");
$stmt->bind_param("sss", $user_value, $user_value, $user_value);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "<script>
        alert('User not found');
        window.location.href='forgot-password.php';
    </script>";
    exit;
}

$user = $result->fetch_assoc();

/* Password update */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

   if ($new_password == "" || $confirm_password == "") {
    $message = "New password aur confirm password bharna जरूरी है";

} elseif ($new_password !== $confirm_password) {
    $message = "Password aur confirm password same hona chahiye";

} elseif (strlen($new_password) < 6) {
    $message = "Password minimum 6 characters ka hona chahiye";

} else {
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    $user_id = (int)$user['id'];

    $update = $conn->prepare("UPDATE matrimonial_users SET password = ? WHERE id = ?");
    $update->bind_param("si", $hashed_password, $user_id);

    if ($update->execute()) {
        echo "<script>
            alert('Password reset successfully');
            window.location.href='register.php';
        </script>";
        exit;
    } else {
        $message = "Password reset nahi hua";
    }
}
    }
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="css/reset-password.css">
</head>
<body>

<div class="reset-card">
    <h2>Reset Password</h2>

    <div class="user-info">
        <?= htmlspecialchars($user['full_name'] ?? 'User'); ?>
    </div>

    <?php if ($message != "") { ?>
        <div class="msg"><?= htmlspecialchars($message); ?></div>
    <?php } ?>

    <form method="POST">
        <input type="hidden" name="user" value="<?= htmlspecialchars($user_value); ?>">

        <label>New Password</label>
        <input type="password" name="new_password" placeholder="Enter new password" required>

        <label>Confirm Password</label>
        <input type="password" name="confirm_password" placeholder="Confirm new password" required>

        <button type="submit">Reset Password</button>
    </form>

    <a href="login.php">Back to Login</a>
</div>

</body>
</html>