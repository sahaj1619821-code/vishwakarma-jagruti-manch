<?php
$conn = new mysqli("127.0.0.1", "root", "", "vjm_db", 3307);

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $full_name         = trim($_POST['full_name'] ?? '');
    $mobile            = trim($_POST['mobile'] ?? '');
    $email             = trim($_POST['email'] ?? '');
    $password          = trim($_POST['password'] ?? '');
    $confirm_password  = trim($_POST['confirm_password'] ?? '');
    $gender            = trim($_POST['gender'] ?? '');
    $dob               = trim($_POST['dob'] ?? '');
    $gotra             = trim($_POST['gotra'] ?? '');
    $state             = trim($_POST['state'] ?? '');
    $city              = trim($_POST['city'] ?? '');
    $district          = trim($_POST['district'] ?? '');
    $tahsil            = trim($_POST['tahsil'] ?? '');
    $village_rajasthan = trim($_POST['village_rajasthan'] ?? '');
    $current_address   = trim($_POST['current_address'] ?? '');
    $marital_status    = trim($_POST['marital_status'] ?? '');

    if (
        empty($full_name) ||
        empty($mobile) ||
        empty($email) ||
        empty($password) ||
        empty($confirm_password) ||
        empty($gender) ||
        empty($dob) ||
        empty($gotra) ||
        empty($state) ||
        empty($city) ||
        empty($district) ||
        empty($tahsil) ||
        empty($village_rajasthan) ||
        empty($current_address) ||
        empty($marital_status)
    ) {
        $message = "सभी field भरना जरूरी है";
    } elseif (strlen($full_name) < 6) {
        $message = "Full Name minimum 6 characters ka hona chahiye";
    } elseif (!preg_match('/^[0-9]{10}$/', $mobile)) {
        $message = "Mobile number 10 digit ka hona chahiye";
    } elseif ($password !== $confirm_password) {
        $message = "Password aur Confirm Password same hone chahiye";
    } else {

        $mobile_email = $email;
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $profile_photo = "";

        /* Profile photo upload */
        if (!empty($_FILES['profile_photo']['name'])) {

            $target_dir = "profile-photo/";

            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }

            $original_name = basename($_FILES["profile_photo"]["name"]);
            $file_type = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

            $allowed_types = ["jpg", "jpeg", "png", "webp"];

            if (!in_array($file_type, $allowed_types)) {
                $message = "Sirf JPG, JPEG, PNG, WEBP photo allowed hai";
            } else {

                $safe_name = preg_replace("/[^a-zA-Z0-9._-]/", "_", $original_name);
                $file_name = time() . "_" . $safe_name;
                $target_file = $target_dir . $file_name;

                if (move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $target_file)) {
                    $profile_photo = $file_name;
                } else {
                    $message = "Profile photo upload nahi ho payi";
                }
            }
        }

        if (empty($message)) {

            $check = $conn->prepare("SELECT id FROM matrimonial_users WHERE email = ? OR mobile = ?");
            $check->bind_param("ss", $email, $mobile);
            $check->execute();
            $check_result = $check->get_result();

            if ($check_result->num_rows > 0) {
                $message = "यह Email या Mobile पहले से registered है";
            } else {

                $stmt = $conn->prepare("INSERT INTO matrimonial_users 
                    (
                        full_name,
                        mobile,
                        email,
                        mobile_email,
                        password,
                        gender,
                        dob,
                        gotra,
                        state,
                        city,
                        district,
                        tahsil,
                        village_rajasthan,
                        current_address,
                        marital_status,
                        profile_photo
                    ) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $stmt->bind_param(
                    "ssssssssssssssss",
                    $full_name,
                    $mobile,
                    $email,
                    $mobile_email,
                    $hashed_password,
                    $gender,
                    $dob,
                    $gotra,
                    $state,
                    $city,
                    $district,
                    $tahsil,
                    $village_rajasthan,
                    $current_address,
                    $marital_status,
                    $profile_photo
                );

                if ($stmt->execute()) {
                    echo "<script>
                        alert('Registration Successful');
                        window.location.href='login-m.php';
                    </script>";
                    exit;
                } else {
                    $message = "Registration Error: " . $stmt->error;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <title>Matrimonial Register</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="./css/register-m.css?v=12">
</head>
<body>

    <nav class="navbar">
        <div class="nav-left">
            <img src="images/logo.png" alt="Logo" class="nav-logo">
            <span class="brand-name">Vishwakarma Jagruti Manch</span>
        </div>

        <ul class="nav-links">
            <li><a href="index.php">Home</a></li>
            <li><a href="matrimonial-entry.php">Matrimonial</a></li>
            <li><a href="login-m.php">Login</a></li>
            <li><a href="register-m.php" class="active">Register</a></li>
        </ul>
    </nav>

    <section class="register-page">

        <div class="left-panel">
            <div class="left-content">
                <img src="images/logo.png" alt="Logo" class="hero-logo">

                <h1>विवाह • जीवन साथी</h1>
                <h2>एकता • सेवा • संस्कार • समृद्धि</h2>

                <p class="welcome-line">स्वागत है! Welcome to Matrimonial</p>
                <p class="sub-line">बिना किसी बाधा के सही जोड़ी जोड़ने का मंच।</p>
            </div>
        </div>

        <div class="right-panel">
            <div class="register-card">
                <h3>Register Matrimonial</h3>

                <?php if (!empty($message)) { ?>
                    <div class="message-box"><?php echo $message; ?></div>
                <?php } ?>

                <form action="register-m.php" method="POST" enctype="multipart/form-data" class="matri-form">

                    <div class="form-group">
    <label>Full Name *</label>
    <input type="text" name="full_name" placeholder="Enter your full name" minlength="10" maxlength="25" required>
</div>

<div class="form-group">
    <label>Mobile Number *</label>
    <input type="text" name="mobile" placeholder="Enter your mobile number" minlength="10" maxlength="10" pattern="[0-9]{10}" required>
</div>

<div class="form-group">
    <label>Email Address *</label>
    <input type="email" name="email" placeholder="Enter your email address" required>
</div>

<div class="form-group">
    <label>Password *</label>
    <input type="password" name="password" placeholder="Create a password" required>
</div>

<div class="form-group">
    <label>Confirm Password *</label>
    <input type="password" name="confirm_password" placeholder="Confirm your password" required>
</div>

<div class="form-group">
    <label>Gender *</label>
    <select name="gender" required>
        <option value="">Gender Select</option>
        <option value="Male">Male</option>
        <option value="Female">Female</option>
    </select>
</div>

<div class="form-group">
    <label>Date of Birth *</label>
    <input type="date" name="dob" required>
</div>

<div class="form-group">
    <label>Gotra *</label>
    <select name="gotra" required>
        <option value="">Gotra Select</option>
        <option value="Solanki">Solanki</option>
        <option value="Chouhan">Chouhan</option>
        <option value="Parmar">Parmar</option>
        <option value="Tripasa">Tripasa</option>
        <option value="Nibjiya">Nibjiya</option>
        <option value="Parihar">Parihar</option>
        <option value="seuha">sehua</option>
    </select>
</div>

<div class="form-group">
    <label>Marital Status *</label>
    <select name="marital_status" required>
        <option value="">Marital Status</option>
        <option value="Unmarried">Unmarried</option>
        <option value="Divorced">Divorced</option>
        <option value="Widow">Widow</option>
        <option value="Widower">Widower</option>
    </select>
</div>

<div class="form-group">
    <label>State *</label>
    <select name="state" required>
        <option value="">State Select</option>
        <option value="Rajasthan">Rajasthan</option>
        <option value="Gujarat">Gujarat</option>
        <option value="Maharashtra">Maharashtra</option>
        <option value="Madhya Pradesh">Madhya Pradesh</option>
        <option value="Delhi">Delhi</option>
        <option value="Other">Other</option>
    </select>
</div>

<div class="form-group">
    <label>City *</label>
    <select name="city" required>
        <option value="">City Select</option>
        <option value="Sirohi">Sirohi</option>
        <option value="Abu Road">Abu Road</option>
        <option value="Pindwara">Pindwara</option>
        <option value="Sheoganj">Sheoganj</option>
        <option value="Jodhpur">Jodhpur</option>
        <option value="Jaipur">Jaipur</option>
        <option value="Other">Other</option>
    </select>
</div>

<div class="form-group">
    <label>District *</label>
    <select name="district" required>
        <option value="">District Select</option>
        <option value="Sirohi">Sirohi</option>
        <option value="Pali">Pali</option>
        <option value="Jalore">Jalore</option>
        <option value="Udaipur">Udaipur</option>
        <option value="Jodhpur">Jodhpur</option>
        <option value="Jaipur">Jaipur</option>
        <option value="Other">Other</option>
    </select>
</div>

<div class="form-group">
    <label>Tahsil *</label>
    <select name="tahsil" required>
        <option value="">Tahsil Select</option>
        <option value="Sirohi">Sirohi</option>
        <option value="Sheoganj">Sheoganj</option>
        <option value="Pindwara">Pindwara</option>
        <option value="Abu Road">Abu Road</option>
        <option value="Reodar">Reodar</option>
        <option value="Other">Other</option>
    </select>
</div>

<div class="form-group">
    <label>Village in Rajasthan *</label>
    <select name="village_rajasthan" required>
        <option value="">Village Select</option>
        <option value="Sirohi">Sirohi</option>
        <option value="Kalandri">Kalandri</option>
        <option value="Mandar">Mandar</option>
        <option value="Pindwara">Pindwara</option>
        <option value="Jawal">Jawal</option>
    </select>
</div>

<div class="form-group full-width">
    <label>Current Address *</label>
    <textarea name="current_address" placeholder="Enter current address" required></textarea>
</div>

<div class="form-group full-width">
    <label>Profile Photo</label>
    <input type="file" name="profile_photo" accept="image/*">
</div>

<button type="submit" class="register-btn">Register Now</button>

                    <p class="bottom-text">
                        Already account hai?
                        <a href="login-m.php">Login</a>
                    </p>
                </form>
            </div>
        </div>

    </section>

</body>
</html>