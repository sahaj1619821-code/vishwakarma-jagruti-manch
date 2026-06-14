<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

/* Database Connection */
$host = "127.0.0.1";
$username = "root";
$password = "";
$database = "vjm_db";
$port = 3307; // XAMPP MySQL port

$conn = mysqli_connect($host, $username, $password, $database, $port);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name           = trim($_POST['name'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $mobile         = trim($_POST['mobile'] ?? '');
    $plainPassword  = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');
    $dob            = trim($_POST['dob'] ?? '');
    $gender         = trim($_POST['gender'] ?? '');
    $marital_status = trim($_POST['marital_status'] ?? '');
    $state          = trim($_POST['state'] ?? '');
    $city           = trim($_POST['city'] ?? '');

    if ($name == "" || $email == "" || $mobile == "" || $plainPassword == "" || $confirmPassword == "") {
        echo "<script>alert('Please fill all required fields'); window.history.back();</script>";
        exit;
    }

    if (strlen($name) < 6) {
        echo "<script>alert('Name minimum 6 characters ka hona chahiye'); window.history.back();</script>";
        exit;
    }

    if (!preg_match('/^[0-9]{10}$/', $mobile)) {
        echo "<script>alert('Mobile number 10 digit ka hona chahiye'); window.history.back();</script>";
        exit;
    }

    if ($plainPassword !== $confirmPassword) {
        echo "<script>alert('Password aur Confirm Password same hone chahiye'); window.history.back();</script>";
        exit;
    }

    if (strlen($plainPassword) < 6) {
        echo "<script>alert('Password minimum 6 characters ka hona chahiye'); window.history.back();</script>";
        exit;
    }

    $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

    /* Check duplicate user */
    $check = $conn->prepare("SELECT id FROM users WHERE email = ? OR mobile = ? LIMIT 1");
    $check->bind_param("ss", $email, $mobile);
    $check->execute();
    $check_result = $check->get_result();

    if ($check_result && $check_result->num_rows > 0) {
        echo "<script>alert('Email or Mobile already exists'); window.history.back();</script>";
        exit;
    }

    /* Insert user */
    $role = "user";

    $sql = $conn->prepare("
        INSERT INTO users 
        (name, email, mobile, password, dob, gender, marital_status, state, city, role) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $sql->bind_param(
        "ssssssssss",
        $name,
        $email,
        $mobile,
        $hashedPassword,
        $dob,
        $gender,
        $marital_status,
        $state,
        $city,
        $role
    );

    if ($sql->execute()) {
        echo "<script>
                alert('Registration Successful');
                window.location.href='login.php';
              </script>";
        exit;
    } else {
        echo "Insert Error: " . $sql->error;
    }
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register - Vishwakarma Jagruti Manch</title>

  <link rel="stylesheet" href="css/register.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>


<?php include 'header.php'; ?>
<div class="top-login">
  Already have an account?
  <a href="login.php">Login <i class="fa-solid fa-arrow-right"></i></a>
</div>

<section class="register-page">

  <div class="left-panel">
    <img src="images/logo.png" class="logo">

    <h1>VISHWAKARMA<br><span>JAGRUTI MANCH</span></h1>
    <h3>एकता • सेवा • संस्कार • समृद्धि</h3>

    <p>Join our growing community and be a part of Vishwakarma Jagruti Manch.</p>

    <div class="feature-box">
      <div><i class="fa-solid fa-users"></i><b>Connect with Verified Members</b><span>Build meaningful connections within the community.</span></div>
      <div><i class="fa-solid fa-certificate"></i><b>Access Exclusive Features</b><span>Use all community services and resources.</span></div>
      <div><i class="fa-solid fa-bell"></i><b>Stay Updated</b><span>Get latest updates on events and activities.</span></div>
      <div><i class="fa-solid fa-hands-holding-heart"></i><b>Be a Part of Change</b><span>Contribute to community growth and development.</span></div>
    </div>
  </div>

  <div class="register-card">
    <div class="icon-head"><i class="fa-solid fa-user-plus"></i></div>
    <h2>Create Your Account</h2>
    <p class="sub">Register to become a part of our community</p>

    <form action="register.php" method="POST" onsubmit="return validateRegister()">

      <div class="grid">
        <div>
          <label>Full Name *</label>
          <div class="input-box"><i class="fa-regular fa-user"></i><input type="text" name="name" id="name" placeholder="Enter your full name" minlength="6" required></div>
        </div>

        <div>
          <label>Mobile Number *</label>
          <div class="input-box"><i class="fa-solid fa-phone"></i><input type="text" name="mobile" id="mobile" placeholder="Enter your mobile number" minlength="10" maxlength="10" required></div>
        </div>

        <div>
          <label>Email Address *</label>
          <div class="input-box"><i class="fa-regular fa-envelope"></i><input type="email" name="email" id="email" placeholder="Enter your email address" required></div>
        </div>

        <div>
          <label>Password *</label>
          <div class="input-box"><i class="fa-solid fa-lock"></i><input type="password" name="password" id="password" placeholder="Create a password" minlength="6" required><i class="fa-regular fa-eye eye" onclick="showPass('password')"></i></div>
        </div>

        <div>
          <label>Confirm Password *</label>
          <div class="input-box"><i class="fa-solid fa-lock"></i><input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm your password" minlength="6" required><i class="fa-regular fa-eye eye" onclick="showPass('confirm_password')"></i></div>
        </div>

        <div>
          <label>Date of Birth *</label>
          <div class="input-box"><i class="fa-regular fa-calendar-days"></i><input type="date" name="dob" id="dob" required></div>
        </div>

        <div>
          <label>Gender *</label>
          <select name="gender" id="gender" required>
            <option value="">Select Gender</option>
            <option>Male</option>
            <option>Female</option>
          </select>
        </div>

        <div>
          <label>Marital Status</label>
          <select name="marital_status">
            <option value="">Select Status</option>
            <option>Single</option>
            <option>Married</option>
            <option>Divorced</option>
          </select>
        </div>

        <div>
          <label>State *</label>
          <select name="state" id="state" required>
            <option value="">Select State</option>
            <option>Rajasthan</option>
            <option>Gujarat</option>
            <option>Maharashtra</option>
            <option>Madhya Pradesh</option>
          </select>
        </div>

        <div>
          <label>City *</label>
          <select name="city" id="city" required>
            <option value="">Select City</option>
            <option>Sirohi</option>
            <option>Udaipur</option>
            <option>Jaipur</option>
            <option>Mumbai</option>
            <option>Ahmedabad</option>
          </select>
        </div>
      </div>

      <label class="terms">
        <input type="checkbox" id="terms" required>
        I agree to the <b>Terms & Conditions</b> and <b>Privacy Policy</b>.
      </label>

      <button type="submit" class="create-btn">
        <i class="fa-solid fa-user-plus"></i> Create Account
      </button>

      <div class="or"><span></span>or register with<span></span></div>

      <div class="social-login">
        <button type="button"><i class="fa-brands fa-google"></i> Google</button>
        <button type="button"><i class="fa-brands fa-facebook"></i> Facebook</button>
        <button type="button"><i class="fa-brands fa-apple"></i> Apple</button>
      </div>

      <p class="secure"><i class="fa-solid fa-shield-halved"></i> Your information is 100% secure and will never be shared.</p>

    </form>
  </div>

</section>

<div class="bottom-stats">
  <div><i class="fa-solid fa-shield-halved"></i><b>100% Secure</b><span>Your data is protected</span></div>
  <div><i class="fa-solid fa-headset"></i><b>24/7 Support</b><span>We are here to help</span></div>
  <div><i class="fa-solid fa-lock"></i><b>Privacy First</b><span>Your privacy matters</span></div>
  <div><i class="fa-solid fa-users"></i><b>50K+ Members</b><span>Trusted community</span></div>
</div>

<footer>© 2026 Vishwakarma Jagruti Manch. All Rights Reserved.</footer>

<script src="js/register.js"></script>
</body>
</html>