<?php
session_start();
$defaultAvatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200' viewBox='0 0 200 200'%3E%3Crect width='200' height='200' fill='%23fff3d0'/%3E%3Ccircle cx='100' cy='72' r='38' fill='%23ffbf22'/%3E%3Cpath d='M36 178c8-45 40-68 64-68s56 23 64 68' fill='%23ff8a00'/%3E%3Ctext x='100' y='194' font-size='18' text-anchor='middle' fill='%23700018' font-family='Arial'%3EUser%3C/text%3E%3C/svg%3E";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $conn = mysqli_connect("127.0.0.1", "root", "", "vjm_db", 3307);

    if (!$conn) {
        die("Database connection failed: " . mysqli_connect_error());
    }

    $email_mobile = mysqli_real_escape_string($conn, $_POST['email_mobile'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email_mobile == "" || $password == "") {
        echo "<script>alert('Email/Mobile aur Password required hai'); window.location.href='login.php';</script>";
        exit();
    }

    $sql = "SELECT * FROM users 
            WHERE email='$email_mobile' OR mobile='$email_mobile' 
            LIMIT 1";

    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) == 1) {

        $user = mysqli_fetch_assoc($result);

        /*
            Password hash aur plain text dono check karega
            Hash password: password_verify()
            Plain password: $password == $user['password']
        */
        if (password_verify($password, $user['password']) || $password == $user['password']) {

            /* Old session bhi rakhe hain, taki purane pages bhi chal sake */
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];

            /* New common website session */
            $_SESSION['site_logged_in'] = true;
            $_SESSION['site_user_id'] = $user['id'];
            $_SESSION['site_user_name'] = $user['name'];
            $_SESSION['site_user_mobile'] = $user['mobile'];
            $_SESSION['site_user_email'] = $user['email'];

            header("Location: index.php");
            exit();

        } else {
            echo "<script>alert('Wrong Password'); window.location.href='login.php';</script>";
            exit();
        }

    } else {
        echo "<script>alert('User not found'); window.location.href='login.php';</script>";
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Vishwakarma Jagruti Manch</title>

  <link rel="stylesheet" href="css/login.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>


<?php include 'header.php'; ?>
<div class="login-page">

  <div class="left-box">
    <img src="images/logo.png" class="logo" alt="Logo">

    <h1>VISHWAKARMA<br><span>JAGRUTI MANCH</span></h1>
    <h3>एकता • सेवा • संस्कार • समृद्धि</h3>

    <p class="welcome">Welcome to Vishwakarma Jagruti Manch</p>
    <p>Join hands, stay connected and be a part of our growing community.</p>

    <div class="left-stats">
      <div><i class="fa-solid fa-users"></i><b>50K+</b><span>Members</span></div>
      <div><i class="fa-solid fa-place-of-worship"></i><b>1,245+</b><span>Temples</span></div>
      <div><i class="fa-solid fa-user-group"></i><b>22</b><span>States</span></div>
    </div>
  </div>

  <div class="login-card">
    <div class="corner"></div>

    <h2>Welcome Back!</h2>
    <p class="sub">Login to your account</p>

    <form action="login.php" method="POST" onsubmit="return checkLogin()">

      <label>Email / Mobile Number</label>
      <div class="input-box">
        <i class="fa-regular fa-user"></i>
        <input type="text" name="email_mobile" id="email_mobile" placeholder="Enter your email or mobile number">
      </div>

      <label>Password</label>
      <div class="input-box">
        <i class="fa-solid fa-lock"></i>
        <input type="password" name="password" id="password" placeholder="Enter your password" required>
        <i class="fa-regular fa-eye eye" id="toggleEye" onclick="showPassword()"></i>
      </div>

      <div class="options">
        <label class="remember">
          <input type="checkbox" checked> Remember Me
        </label>
        <a href="forgot-password.php" style="color:#92001d;font-weight:bold;text-decoration:none;">Forgot Password?</a>
      </div>

      <button type="submit" class="login-btn">Login <i class="fa-solid fa-arrow-right"></i></button>

      <div class="or"><span></span>or continue with<span></span></div>

      <div class="social-login">
        <button type="button"><i class="fa-brands fa-google"></i> Google</button>
        <button type="button"><i class="fa-brands fa-facebook"></i> Facebook</button>
        <button type="button"><i class="fa-brands fa-apple"></i> Apple</button>
      </div>

      <p class="register-text">Don't have an account? <a href="register.php">Register Now</a></p>

    </form>
  </div>

</div>

<footer>
  <p>© 2026 Vishwakarma Jagruti Manch. All Rights Reserved.</p>
  <p>Privacy Policy | Terms & Conditions | Help & Support</p>
</footer>

<script src="js/login.js"></script>
<script>
function showPassword() {
    var pass = document.getElementById("password");
    var eye = document.getElementById("toggleEye");

    if (pass.type === "password") {
        pass.type = "text";
        eye.classList.remove("fa-eye");
        eye.classList.add("fa-eye-slash");
    } else {
        pass.type = "password";
        eye.classList.remove("fa-eye-slash");
        eye.classList.add("fa-eye");
    }
}
</script>
</body>
</html>