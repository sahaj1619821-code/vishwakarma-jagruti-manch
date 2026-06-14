<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
    Single matrimonial registration form.
    Flow: plan.php -> register-m-m.php?plan_id=ID -> payment-m.php
    Is file me hi registration save hoga, register-m.php ki zarurat nahi.
*/

$conn = mysqli_connect("127.0.0.1", "root", "", "vjm_db", 3307);
if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}
mysqli_set_charset($conn, "utf8mb4");

$site_user_id = (int)($_SESSION['site_user_id'] ?? $_SESSION['user_id'] ?? 0);
if ($site_user_id <= 0) {
    header("Location: login.php");
    exit;
}


function e_m($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function column_exists_m($conn, $table, $column) {
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $q = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($q && mysqli_num_rows($q) > 0);
}

function table_exists_m($conn, $table) {
    $table = mysqli_real_escape_string($conn, $table);
    $q = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    return ($q && mysqli_num_rows($q) > 0);
}

function add_column_if_missing_m($conn, $table, $column, $definition) {
    if (table_exists_m($conn, $table) && !column_exists_m($conn, $table, $column)) {
        mysqli_query($conn, "ALTER TABLE `$table` ADD `$column` $definition");
    }
}

function make_option_table_m($conn) {
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS matrimonial_dropdown_options (
        id INT AUTO_INCREMENT PRIMARY KEY,
        field_name VARCHAR(100) NOT NULL,
        option_value VARCHAR(150) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_field_value (field_name, option_value)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function save_dropdown_option_m($conn, $field, $value) {
    $field = trim($field);
    $value = trim($value);
    if ($field === '' || $value === '' || strtolower($value) === 'other') return;

    make_option_table_m($conn);

    $stmt = mysqli_prepare($conn, "INSERT IGNORE INTO matrimonial_dropdown_options (field_name, option_value) VALUES (?, ?)");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ss", $field, $value);
        mysqli_stmt_execute($stmt);
    }
}

function dropdown_options_m($conn, $field, $defaults = []) {
    make_option_table_m($conn);

    $options = [];
    foreach ($defaults as $v) {
        $v = trim((string)$v);
        if ($v !== '') $options[$v] = $v;
    }

    $field_safe = mysqli_real_escape_string($conn, $field);
    $q = mysqli_query($conn, "SELECT option_value FROM matrimonial_dropdown_options WHERE field_name='$field_safe' ORDER BY option_value ASC");
    if ($q) {
        while ($r = mysqli_fetch_assoc($q)) {
            $v = trim($r['option_value'] ?? '');
            if ($v !== '') $options[$v] = $v;
        }
    }

    return array_values($options);
}

function post_dropdown_value_m($conn, $field) {
    $value = trim($_POST[$field] ?? '');
    $other = trim($_POST[$field . '_other'] ?? '');

    if ($value === '__other__') {
        $value = $other;
        save_dropdown_option_m($conn, $field, $value);
    }

    return $value;
}

make_option_table_m($conn);

/* Required extra columns auto add */
if (table_exists_m($conn, 'matrimonial_users')) {
    add_column_if_missing_m($conn, 'matrimonial_users', 'user_id', "INT NULL AFTER id");
    add_column_if_missing_m($conn, 'matrimonial_users', 'current_city', "VARCHAR(150) NULL AFTER village_rajasthan");
    add_column_if_missing_m($conn, 'matrimonial_users', 'current_address', "TEXT NULL AFTER current_city");
    add_column_if_missing_m($conn, 'matrimonial_users', 'city', "VARCHAR(150) NULL");
}

$message = "";
$selected_plan_id = 0;

if (isset($_POST['selected_plan_id'])) {
    $selected_plan_id = (int)$_POST['selected_plan_id'];
    if ($selected_plan_id > 0) {
        $_SESSION['matrimonial_selected_plan_id'] = $selected_plan_id;
        $_SESSION['selected_matrimonial_plan_id'] = $selected_plan_id;
    }
} elseif (isset($_GET['plan_id'])) {
    $selected_plan_id = (int)$_GET['plan_id'];
    if ($selected_plan_id > 0) {
        $_SESSION['matrimonial_selected_plan_id'] = $selected_plan_id;
        $_SESSION['selected_matrimonial_plan_id'] = $selected_plan_id;
    }
} elseif (isset($_SESSION['matrimonial_selected_plan_id'])) {
    $selected_plan_id = (int)$_SESSION['matrimonial_selected_plan_id'];
} elseif (isset($_SESSION['selected_matrimonial_plan_id'])) {
    $selected_plan_id = (int)$_SESSION['selected_matrimonial_plan_id'];
}

/* Plan select kiye bina register page open na ho */
if ($selected_plan_id <= 0) {
    header("Location: plan.php");
    exit;
}

$plan_q = mysqli_query($conn, "
    SELECT * FROM matrimonial_plans
    WHERE id = $selected_plan_id AND status = 'active'
    LIMIT 1
");

$plan_data = $plan_q ? mysqli_fetch_assoc($plan_q) : null;

if (!$plan_data) {
    unset($_SESSION['matrimonial_selected_plan_id']);
    unset($_SESSION['selected_matrimonial_plan_id']);
    header("Location: plan.php");
    exit;
}

$plan_name = $plan_data['plan_name'] ?? 'Selected Plan';
$plan_price = (float)($plan_data['price'] ?? 0);
$plan_days = (int)($plan_data['duration_days'] ?? 0);
$plan_views = (int)($plan_data['profile_view_limit'] ?? 0);
$plan_chat = (int)($plan_data['chat_limit'] ?? 0);

$gotra_options = dropdown_options_m($conn, 'gotra', ['solanki', 'parmar', 'chauhan', 'tripasa', 'fodar']);
$state_options = dropdown_options_m($conn, 'state', ['Rajasthan', 'Maharashtra', 'Gujarat', 'Madhya Pradesh', 'Delhi']);
$city_options = dropdown_options_m($conn, 'city', ['Sirohi', 'Abu Road', 'Pindwara', 'Sheoganj', 'Reodar', 'Jalore', 'Pali', 'Udaipur', 'Jaipur', 'Mumbai', 'Ahmedabad', 'Surat', 'Pune', 'Delhi']);
$tahsil_options = dropdown_options_m($conn, 'tahsil', ['Sirohi', 'Sheoganj', 'Pindwara', 'Abu Road', 'Reodar']);
$village_options = dropdown_options_m($conn, 'village_rajasthan', ['Sirohi', 'Mandar', 'Reodar', 'Pindwara', 'Sheoganj']);
$current_city_options = dropdown_options_m($conn, 'current_city', ['Rajasthan', 'Mumbai', 'Ahmedabad', 'Surat', 'Pune', 'Delhi']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $full_name         = trim($_POST['full_name'] ?? '');
    $mobile            = trim($_POST['mobile'] ?? '');
    $email             = trim($_POST['email'] ?? '');
    $password          = trim($_POST['password'] ?? '');
    $confirm_password  = trim($_POST['confirm_password'] ?? '');
    $gender            = trim($_POST['gender'] ?? '');
    $dob               = trim($_POST['dob'] ?? '');
    $height            = trim($_POST['height'] ?? '');
    $profession        = trim($_POST['profession'] ?? '');
    $gotra             = post_dropdown_value_m($conn, 'gotra');
    $state             = post_dropdown_value_m($conn, 'state');
    $city              = post_dropdown_value_m($conn, 'city');
    $district          = trim($_POST['district'] ?? '');
    $tahsil            = post_dropdown_value_m($conn, 'tahsil');
    $village_rajasthan = post_dropdown_value_m($conn, 'village_rajasthan');
    $current_city      = post_dropdown_value_m($conn, 'current_city');
    $current_address   = trim($_POST['current_address'] ?? '');
    $marital_status    = trim($_POST['marital_status'] ?? '');

    if ($full_name === '' || $mobile === '' || $email === '' || $password === '' || $confirm_password === '' || $gender === '' || $dob === '' || $gotra === '' || $state === '' || $city === '' || $district === '' || $tahsil === '' || $village_rajasthan === '' || $current_city === '' || $current_address === '' || $marital_status === '') {
        $message = "सभी जरूरी field भरना जरूरी है";
    } elseif (strlen($full_name) < 3) {
        $message = "Full Name minimum 3 characters ka hona chahiye";
    } elseif (!preg_match('/^[0-9]{10}$/', $mobile)) {
        $message = "Mobile number 10 digit ka hona chahiye";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Valid email address डालें";
    } elseif (strlen($password) < 6) {
        $message = "Password minimum 6 characters ka hona chahiye";
    } elseif ($password !== $confirm_password) {
        $message = "Password aur Confirm Password same hone chahiye";
    } else {

        $profile_photo = "";

        if (!empty($_FILES['profile_photo']['name'])) {
            $target_dir = "profile-photo/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }

            $original_name = basename($_FILES['profile_photo']['name']);
            $file_type = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            $allowed_types = ["jpg", "jpeg", "png", "webp"];

            if (!in_array($file_type, $allowed_types, true)) {
                $message = "Sirf JPG, JPEG, PNG, WEBP photo allowed hai";
            } else {
                $safe_name = preg_replace("/[^a-zA-Z0-9._-]/", "_", $original_name);
                $file_name = time() . "_" . $safe_name;
                $target_file = $target_dir . $file_name;

                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target_file)) {
                    $profile_photo = $file_name;
                } else {
                    $message = "Profile photo upload nahi ho payi";
                }
            }
        }

        if ($message === '') {
            if (!table_exists_m($conn, 'matrimonial_users')) {
                die("Table matrimonial_users database में नहीं है");
            }

            if (column_exists_m($conn, 'matrimonial_users', 'user_id')) {
                $check = mysqli_prepare($conn, "SELECT id FROM matrimonial_users WHERE user_id = ? OR email = ? OR mobile = ? LIMIT 1");
                mysqli_stmt_bind_param($check, "iss", $site_user_id, $email, $mobile);
            } else {
                $check = mysqli_prepare($conn, "SELECT id FROM matrimonial_users WHERE email = ? OR mobile = ? LIMIT 1");
                mysqli_stmt_bind_param($check, "ss", $email, $mobile);
            }

            mysqli_stmt_execute($check);
            $check_result = mysqli_stmt_get_result($check);

            if ($check_result && mysqli_num_rows($check_result) > 0) {
                $message = "इस Login User / Email / Mobile से matrimonial profile पहले से registered है";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $mobile_email = $email;

                $insert_data = [
                    'user_id'           => $site_user_id,
                    'full_name'         => $full_name,
                    'mobile'            => $mobile,
                    'email'             => $email,
                    'mobile_email'      => $mobile_email,
                    'password'          => $hashed_password,
                    'gender'            => $gender,
                    'dob'               => $dob,
                    'height'            => $height,
                    'profession'        => $profession,
                    'occupation'        => $profession,
                    'gotra'             => $gotra,
                    'state'             => $state,
                    'city'              => $city,
                    'district'          => $district,
                    'tahsil'            => $tahsil,
                    'village_rajasthan' => $village_rajasthan,
                    'current_city'      => $current_city,
                    'current_address'   => $current_address,
                    'address'           => $current_address,
                    'marital_status'    => $marital_status,
                    'profile_photo'     => $profile_photo,
                    'photo'             => $profile_photo,
                    'selected_plan_id'  => $selected_plan_id,
                    'plan_id'           => $selected_plan_id,
                    'verification_status' => 'pending',
                    'verified'          => 0,
                    'status'            => 'pending'
                ];

                $cols = [];
                $vals = [];
                foreach ($insert_data as $col => $val) {
                    if (column_exists_m($conn, 'matrimonial_users', $col)) {
                        $cols[] = "`$col`";
                        $vals[] = "'" . mysqli_real_escape_string($conn, (string)$val) . "'";
                    }
                }

                if (empty($cols)) {
                    die("matrimonial_users table me matching column nahi mile");
                }

                $insert_sql = "INSERT INTO matrimonial_users (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";

                if (mysqli_query($conn, $insert_sql)) {
                    $new_user_id = mysqli_insert_id($conn);

                    unset($_SESSION['matrimonial_user_id'], $_SESSION['matrimonial_user_name'], $_SESSION['matrimonial_logged_in']);

                    $_SESSION['matrimonial_user_id'] = $new_user_id;
                    $_SESSION['matrimonial_logged_in'] = true;
                    $_SESSION['matrimonial_user_name'] = $full_name;
                    $_SESSION['matrimonial_selected_plan_id'] = $selected_plan_id;
                    $_SESSION['selected_matrimonial_plan_id'] = $selected_plan_id;

                    unset($_SESSION['site_user_id']); // old normal user id payment page par na lage

                    header("Location: payment-m.php?user_id=" . $new_user_id . "&plan_id=" . $selected_plan_id);
                    exit;
                } else {
                    $message = "Registration Error: " . mysqli_error($conn);
                }
            }
        }
    }
}

function render_select_m($name, $label, $options, $required = true) {
    $req = $required ? 'required' : '';
    echo '<div class="input-box dynamic-select-box">';
    echo '<label>' . e_m($label) . '</label>';
    echo '<select name="' . e_m($name) . '" id="' . e_m($name) . '" class="dynamic-select" data-other="' . e_m($name . '_other_wrap') . '" ' . $req . '>';
    echo '<option value="">Select ' . e_m($label) . '</option>';
    foreach ($options as $opt) {
        echo '<option value="' . e_m($opt) . '">' . e_m($opt) . '</option>';
    }
    echo '<option value="__other__">Other</option>';
    echo '</select>';
    echo '<div class="other-input-wrap" id="' . e_m($name . '_other_wrap') . '" style="display:none;">';
    echo '<input type="text" name="' . e_m($name) . '_other" id="' . e_m($name) . '_other" placeholder="Enter new ' . e_m($label) . '">';
    echo '<small>New value submit ke baad dropdown me save ho jayegi.</small>';
    echo '</div>';
    echo '</div>';
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Matrimonial Register</title>
  <link rel="stylesheet" href="css/register-m-m.css?v=15">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    .password-wrap{position:relative;}
    .password-wrap input{padding-right:42px !important;}
    .toggle-pass{
        position:absolute;
        right:13px;
        top:50%;
        transform:translateY(-50%);
        color:#ffc328;
        cursor:pointer;
        z-index:2;
    }
    .other-input-wrap{margin-top:8px;}
    .other-input-wrap small{display:block;color:#ffd36a;font-size:12px;margin-top:4px;}
    .input-box textarea{
        width:100%;
        min-height:95px;
        resize:vertical;
        padding:12px;
        border-radius:10px;
        border:1px solid #b8860b;
        background:#170006;
        color:#fff;
        outline:none;
    }
  </style>
</head>
<body>
<?php include 'header.php'; ?>
<div class="register-section">
  <div class="register-card">

    <h2>Matrimonial Register Profile</h2>
    <p class="sub-title">Vishwakarma community matrimonial registration</p>

    <div class="selected-plan-box">
      <span><b>Selected Plan:</b> <?= e_m($plan_name); ?></span>
      <span><b>Price:</b> ₹<?= number_format($plan_price); ?></span>
      <span><b>Days:</b> <?= $plan_days; ?></span>
      <span><b>Views:</b> <?= $plan_views; ?></span>
      <span><b>Chat:</b> <?= $plan_chat; ?></span>
    </div>

    <?php if (!empty($message)) { ?>
      <div class="message-box" style="background:#ffe2e2;color:#750000;border:1px solid #ff8a8a;padding:12px;border-radius:10px;margin:15px 0;font-weight:700;">
        <?= e_m($message); ?>
      </div>
    <?php } ?>

    <form action="register-m-m.php?plan_id=<?= (int)$selected_plan_id; ?>" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="selected_plan_id" value="<?= (int)$selected_plan_id; ?>">

      <div class="photo-upload">
        <img id="preview" src="images/default-user.png" alt="Profile">
        <label for="profile_photo"><i class="fa fa-camera"></i> Upload Photo</label>
        <input type="file" id="profile_photo" name="profile_photo" accept="image/*">
      </div>

      <div class="form-grid">

        <div class="input-box">
          <label>Full Name</label>
          <input type="text" name="full_name" minlength="3" required>
        </div>

        <div class="input-box">
          <label>Mobile Number</label>
          <input type="text" name="mobile" minlength="10" maxlength="10" pattern="[0-9]{10}" required>
        </div>

        <div class="input-box">
          <label>Email</label>
          <input type="email" name="email" required>
        </div>

        <div class="input-box">
          <label>Password</label>
          <div class="password-wrap">
            <input type="password" name="password" id="password" minlength="6" required>
            <i class="fa-regular fa-eye toggle-pass" onclick="togglePassword('password', this)"></i>
          </div>
        </div>

        <div class="input-box">
          <label>Confirm Password</label>
          <div class="password-wrap">
            <input type="password" name="confirm_password" id="confirm_password" minlength="6" required>
            <i class="fa-regular fa-eye toggle-pass" onclick="togglePassword('confirm_password', this)"></i>
          </div>
        </div>

        <div class="input-box">
          <label>Gender</label>
          <select name="gender" required>
            <option value="">Select</option>
            <option>Male</option>
            <option>Female</option>
          </select>
        </div>

        <div class="input-box">
          <label>Date of Birth</label>
          <input type="date" name="dob" required>
        </div>

        <div class="input-box">
          <label>Height</label>
          <input type="text" name="height" placeholder="5'7">
        </div>

        <div class="input-box">
          <label>Profession</label>
          <input type="text" name="profession">
        </div>

        <?php render_select_m('gotra', 'Gotra', $gotra_options); ?>
        <?php render_select_m('state', 'State', $state_options); ?>
        <?php render_select_m('city', 'City', $city_options); ?>

        <div class="input-box">
          <label>District</label>
          <select name="district" required>
            <option value="">Select District</option>
            <option>Sirohi</option>
            <option>Jalore</option>
            <option>Pali</option>
            <option>Udaipur</option>
            <option>Jaipur</option>
          </select>
        </div>

        <?php render_select_m('tahsil', 'Tahsil', $tahsil_options); ?>
        <?php render_select_m('village_rajasthan', 'Village in Rajasthan', $village_options); ?>
        <?php render_select_m('current_city', 'Current City', $current_city_options); ?>

        <div class="input-box">
          <label>Current Address</label>
          <textarea name="current_address" placeholder="Enter full current address" required></textarea>
        </div>

        <div class="input-box">
          <label>Marital Status</label>
          <select name="marital_status" required>
            <option value="">Select Marital Status</option>
            <option>Never Married</option>
            <option>Married</option>
            <option>Divorced</option>
          </select>
        </div>

      </div>

      <button type="submit" class="submit-btn">
        Register & Continue to Payment <i class="fa fa-arrow-right"></i>
      </button>

      <p class="bottom-text">
        Plan change करना है? <a href="plan.php">Back to Plans</a>
      </p>

    </form>
  </div>
</div>

<script>
var photoInput = document.getElementById('profile_photo');
var preview = document.getElementById('preview');

if (photoInput && preview) {
    photoInput.addEventListener('change', function () {
        var file = this.files && this.files[0];
        if (file) {
            preview.src = URL.createObjectURL(file);
        }
    });
}

function togglePassword(inputId, icon) {
    var input = document.getElementById(inputId);
    if (!input) return;

    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

document.querySelectorAll('.dynamic-select').forEach(function(sel) {
    sel.addEventListener('change', function() {
        var wrap = document.getElementById(this.dataset.other);
        var otherInput = wrap ? wrap.querySelector('input') : null;

        if (this.value === '__other__') {
            if (wrap) wrap.style.display = 'block';
            if (otherInput) {
                otherInput.required = true;
                otherInput.focus();
            }
        } else {
            if (wrap) wrap.style.display = 'none';
            if (otherInput) otherInput.required = false;
        }
    });
});
</script>

</body>
</html>
