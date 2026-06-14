<?php
session_start();

$conn = mysqli_connect("127.0.0.1", "root", "", "vjm_db", 3307);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $login_value = trim($_POST['mobile_email'] ?? '');
    $password    = trim($_POST['password'] ?? '');

    $sql = "SELECT * FROM matrimonial_users 
            WHERE id = ? OR mobile_email = ? OR mobile = ? OR email = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $login_value, $login_value, $login_value, $login_value);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result && $result->num_rows == 1) {

        $user = $result->fetch_assoc();

        if (
            password_verify($password, $user['password']) ||
            $password === $user['password'] ||
            md5($password) === $user['password']
        ) {
            $_SESSION['matrimonial_user_id'] = $user['id'];
            $_SESSION['matrimonial_user_name'] = $user['full_name'];

            header("Location: matrimonial.php");
            exit();

        } else {
            echo "<script>
                alert('Wrong password');
                window.location.href='login-m.php';
            </script>";
            exit();
        }

    } else {
        echo "<script>
            alert('User not found');
            window.location.href='login-m.php';
        </script>";
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login - Matrimonial</title>

  <!-- CSS -->
  <link rel="stylesheet" href="css/login-m.css">

  <!-- Font Awesome for Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>



<body>

<div class="login-page">
  <!-- Left Hero Section -->
  <div class="hero">
    <!-- Placeholder hero image -->
    <img src="images/login-bg.jpg" alt="Wedding Hero Image" class="hero-image">

    <div class="hero-overlay">
      <img src="images/logo.png" alt="Site Logo" class="logo">
      <h1>विवाह • जीवन साथी</h1>
      <h3>एकता • सेवा • संस्कार • समृद्धि</h3>
      <p class="welcome">स्वागत है! Welcome to Matrimonial</p>
      <p>बिना किसी बाधा के सही जोड़ी जोड़ने का मंच।</p>
    </div>
  </div>

  <!-- Right Login Form -->
  <div class="login-card">
    <h2>लॉग इन करें / Login</h2>

      <!-- Profile ID Input (hidden by default) -->
      <form action="login-m.php" method="POST">

    <label for="mobile_email">Email / Mobile / ID</label>
    <div class="input-box">
        <i class="fa-regular fa-envelope"></i>
        <input 
            type="text" 
            name="mobile_email" 
            id="mobile_email" 
            placeholder="Enter Email / Mobile / ID" 
            required
        >
    </div>

    <label for="password">पासवर्ड / Password</label>
    <div class="input-box">
        <i class="fa-solid fa-lock"></i>
        <input 
            type="password" 
            name="password" 
            id="password" 
            placeholder="Enter your password" 
            required
        >
        <i class="fa-regular fa-eye toggle-password" onclick="togglePassword()"></i>
    </div>
      <!-- Remember Me -->
      <div class="options">
        <label class="remember">
          <input type="checkbox" name="remember_me" checked> Remember Me
        </label>
        <a href="forgot-password.php">Forgot Password?</a>
      </div>

      <!-- Login Button -->
      <button type="submit" class="login-btn">Login <i class="fa-solid fa-arrow-right"></i></button>

      <!-- Divider -->
      <div class="or"><span></span> or continue with <span></span></div>

      <!-- Social Buttons (non-functional) -->
      <div class="social-login">
        <button type="button"><i class="fa-brands fa-google"></i> Google</button>
        <button type="button"><i class="fa-brands fa-facebook"></i> Facebook</button>
        <button type="button"><i class="fa-brands fa-apple"></i> Apple</button>
      </div>

      <!-- Registration Link -->
      <p class="register-text">कोई खाता नहीं है? <a href="register-m.php">Register Now</a></p>
    </form>
  </div>
</div>

<footer class="footer">
  <p>© 2026 Matrimonial. All Rights Reserved.</p>
</footer>
</body>
</html>