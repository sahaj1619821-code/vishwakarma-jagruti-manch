<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['site_logged_in']) || empty($_SESSION['site_user_id'])) {
    header("Location: login.php");
    exit;
}

$conn = mysqli_connect("127.0.0.1", "root", "", "vjm_db", 3307);
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
mysqli_set_charset($conn, "utf8mb4");
$defaultAvatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200' viewBox='0 0 200 200'%3E%3Crect width='200' height='200' fill='%23fff3d0'/%3E%3Ccircle cx='100' cy='72' r='38' fill='%23ffbf22'/%3E%3Cpath d='M36 178c8-45 40-68 64-68s56 23 64 68' fill='%23ff8a00'/%3E%3Ctext x='100' y='194' font-size='18' text-anchor='middle' fill='%23700018' font-family='Arial'%3EUser%3C/text%3E%3C/svg%3E";

function pf_e($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function pf_col_exists($conn, $table, $column) {
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $q = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $q && mysqli_num_rows($q) > 0;
}

function pf_photo_src($row, $defaultAvatar) {
    $p = '';
    foreach (['photo', 'profile_photo', 'image', 'avatar'] as $key) {
        if (!empty($row[$key])) {
            $p = $row[$key];
            break;
        }
    }

    if ($p !== '') {
        $p = ltrim(str_replace('\\', '/', $p), '/');

        if (preg_match('#^https?://#i', $p)) {
            return $p;
        }

        if (file_exists(__DIR__ . '/' . $p)) {
            return $p;
        }

        $base = basename($p);
        foreach (['profile-photo/', 'uploads/', 'images/', ''] as $dir) {
            $try = $dir . $base;
            if ($base !== '' && file_exists(__DIR__ . '/' . $try)) {
                return $try;
            }
        }
    }

    if (file_exists(__DIR__ . '/images/default-profile.png')) {
        return 'images/default-profile.png';
    }
    if (file_exists(__DIR__ . '/images/user.png')) {
        return 'images/user.png';
    }
    return $defaultAvatar;
}


function pf_matrimonial_photo_src($conn, $site_user_id, $defaultAvatar) {
    $site_user_id = (int)$site_user_id;
    if ($site_user_id <= 0) return '';

    $tq = mysqli_query($conn, "SHOW TABLES LIKE 'matrimonial_users'");
    if (!$tq || mysqli_num_rows($tq) == 0) return '';

    $where = "id=$site_user_id";
    if (pf_col_exists($conn, 'matrimonial_users', 'user_id')) {
        $where = "user_id=$site_user_id";
    }

    $q = mysqli_query($conn, "SELECT * FROM matrimonial_users WHERE $where ORDER BY id DESC LIMIT 1");
    if (!$q || mysqli_num_rows($q) == 0) return '';

    $row = mysqli_fetch_assoc($q);
    $p = pf_photo_src($row, $defaultAvatar);

    // default avatar tab return na karein; sirf actual uploaded file mile to return karein
    if ($p === $defaultAvatar || $p === 'images/default-profile.png' || $p === 'images/user.png') {
        return '';
    }
    return $p;
}

if (!pf_col_exists($conn, 'users', 'photo')) {
    mysqli_query($conn, "ALTER TABLE users ADD photo VARCHAR(255) NULL AFTER password");
}

$user_id = (int)$_SESSION['site_user_id'];
$user_q = mysqli_query($conn, "SELECT * FROM users WHERE id=$user_id LIMIT 1");
if (!$user_q || mysqli_num_rows($user_q) == 0) {
    session_destroy();
    header("Location: login.php");
    exit;
}
$user = mysqli_fetch_assoc($user_q);

$message = "";

/* Optional profile update */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name   = trim($_POST['name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $city   = trim($_POST['city'] ?? '');
    $state  = trim($_POST['state'] ?? '');
    $district = trim($_POST['district'] ?? '');

    if ($name === '') {
        $message = "Name जरूरी है";
    } else {
        $updates = [];

        if (pf_col_exists($conn, 'users', 'name')) {
            $updates[] = "`name`='" . mysqli_real_escape_string($conn, $name) . "'";
        }
        if (pf_col_exists($conn, 'users', 'mobile')) {
            $updates[] = "`mobile`='" . mysqli_real_escape_string($conn, $mobile) . "'";
        }
        if (pf_col_exists($conn, 'users', 'email')) {
            $updates[] = "`email`='" . mysqli_real_escape_string($conn, $email) . "'";
        }
        if (pf_col_exists($conn, 'users', 'city')) {
            $updates[] = "`city`='" . mysqli_real_escape_string($conn, $city) . "'";
        }
        if (pf_col_exists($conn, 'users', 'state')) {
            $updates[] = "`state`='" . mysqli_real_escape_string($conn, $state) . "'";
        }
        if (pf_col_exists($conn, 'users', 'district')) {
            $updates[] = "`district`='" . mysqli_real_escape_string($conn, $district) . "'";
        }

        $photo_col = '';
        foreach (['photo', 'profile_photo', 'image', 'avatar'] as $pc) {
            if (pf_col_exists($conn, 'users', $pc)) {
                $photo_col = $pc;
                break;
            }
        }

        if ($photo_col !== '' && !empty($_FILES['profile_photo']['name'])) {
            $target_dir = "profile-photo/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }

            $original = basename($_FILES['profile_photo']['name']);
            $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            if (in_array($ext, $allowed, true)) {
                $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $original);
                $file = time() . '_' . $safe;
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target_dir . $file)) {
                    $updates[] = "`$photo_col`='" . mysqli_real_escape_string($conn, $file) . "'";
                    $_SESSION['site_user_photo'] = $target_dir . $file;
                }
            }
        }

        if (!empty($updates)) {
            mysqli_query($conn, "UPDATE users SET " . implode(',', $updates) . " WHERE id=$user_id");
            $_SESSION['site_user_name'] = $name;
            $_SESSION['site_user_mobile'] = $mobile;
            $_SESSION['site_user_email'] = $email;
            echo "<script>alert('Profile updated successfully'); window.location.href='profile.php';</script>";
            exit;
        }
    }
}

$photo = pf_photo_src($user, $defaultAvatar);

// Agar normal users table me photo nahi hai to matrimonial profile ki photo dikhao
$normal_photo_missing = (
    $photo === $defaultAvatar ||
    $photo === 'images/default-profile.png' ||
    $photo === 'images/user.png' ||
    $photo === 'images/logo.png' ||
    $photo === ''
);

if ($normal_photo_missing) {
    $matri_photo = pf_matrimonial_photo_src($conn, $user_id, $defaultAvatar);
    if ($matri_photo !== '') {
        $photo = $matri_photo;
    }
}

if ($photo === 'images/logo.png' || $photo === '') {
    $photo = $defaultAvatar;
}
$_SESSION['site_user_photo'] = $photo;
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <title>My Profile - Vishwakarma Jagruti Manch</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        *{box-sizing:border-box}
        body{margin:0;background:#070000;color:#fff;font-family:Arial,sans-serif}
        .pf-wrap{max-width:1150px;margin:30px auto 55px;padding:0 18px}
        .pf-head{background:linear-gradient(90deg,#520010,#210004);border:1px solid #9b5b13;border-radius:16px;padding:22px 26px;margin-bottom:20px}
        .pf-head h1{margin:0;color:#ffc328;font-family:Georgia,serif;font-size:38px}
        .pf-box{display:grid;grid-template-columns:330px 1fr;gap:22px}
        .pf-left,.pf-right{background:#fffaf3;color:#270006;border:1px solid #ffb22c;border-radius:18px;box-shadow:0 18px 45px rgba(0,0,0,.45);overflow:hidden}
        .pf-img{width:100%;height:330px;object-fit:cover;background:#eee;display:block}
        .pf-left-body{padding:18px;text-align:center}
        .pf-left h2{margin:0 0 8px;color:#6b0018;font-size:28px}
        .pf-badge{display:inline-block;margin-top:12px;background:linear-gradient(#ffd65b,#ff9d00);color:#210000;border-radius:30px;padding:10px 18px;font-weight:900}
        .pf-right{padding:24px}
        .pf-title{margin:0 0 18px;color:#6b0018;font-size:28px;border-bottom:2px solid #ffb22c;padding-bottom:10px}
        .pf-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
        .pf-field label{display:block;color:#7b001c;font-weight:900;margin-bottom:6px}
        .pf-field input{width:100%;padding:13px;border:1px solid #e7c06a;border-radius:12px;background:#fff;color:#1f0006;font-size:15px}
        .pf-actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:20px}
        .pf-btn{border:none;text-decoration:none;cursor:pointer;background:linear-gradient(#ffd65b,#ff9d00);color:#210000;border:1px solid #ffb22c;border-radius:12px;padding:13px 22px;font-weight:900;box-shadow:0 0 18px rgba(255,191,34,.45)}
        .pf-msg{background:#ffe2e2;color:#750000;border:1px solid #ff8a8a;padding:12px;border-radius:10px;margin-bottom:15px;font-weight:800}
        @media(max-width:850px){.pf-box{grid-template-columns:1fr}.pf-grid{grid-template-columns:1fr}.pf-head h1{font-size:30px}}
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="pf-wrap">
    <div class="pf-head">
        <h1>My Profile</h1>
        <p>अपनी normal website profile details देखें और update करें।</p>
    </div>

    <div class="pf-box">
        <div class="pf-left">
            <img class="pf-img" src="<?= pf_e($photo); ?>" alt="Profile Photo" onerror="this.onerror=null;this.src='<?= pf_e($defaultAvatar); ?>';">
            <div class="pf-left-body">
                <h2><?= pf_e($user['name'] ?? 'User'); ?></h2>
                <div><?= pf_e($user['mobile'] ?? ''); ?></div>
                <div><?= pf_e($user['email'] ?? ''); ?></div>
                <span class="pf-badge"><?= pf_e($user['role'] ?? 'user'); ?></span>
            </div>
        </div>

        <div class="pf-right">
            <h2 class="pf-title">Profile Information</h2>

            <?php if ($message !== '') { ?><div class="pf-msg"><?= pf_e($message); ?></div><?php } ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="pf-grid">
                    <div class="pf-field">
                        <label>Full Name</label>
                        <input type="text" name="name" value="<?= pf_e($user['name'] ?? ''); ?>" required>
                    </div>
                    <div class="pf-field">
                        <label>Mobile</label>
                        <input type="text" name="mobile" value="<?= pf_e($user['mobile'] ?? ''); ?>">
                    </div>
                    <div class="pf-field">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= pf_e($user['email'] ?? ''); ?>">
                    </div>
                    <div class="pf-field">
                        <label>Gender</label>
                        <input type="text" value="<?= pf_e($user['gender'] ?? ''); ?>" disabled>
                    </div>
                    <div class="pf-field">
                        <label>City</label>
                        <input type="text" name="city" value="<?= pf_e($user['city'] ?? ''); ?>">
                    </div>
                    <div class="pf-field">
                        <label>District</label>
                        <input type="text" name="district" value="<?= pf_e($user['district'] ?? ''); ?>">
                    </div>
                    <div class="pf-field">
                        <label>State</label>
                        <input type="text" name="state" value="<?= pf_e($user['state'] ?? ''); ?>">
                    </div>
                    <div class="pf-field">
                        <label>Profile Photo</label>
                        <input type="file" name="profile_photo" accept="image/*">
                    </div>
                </div>

                <div class="pf-actions">
                    <button type="submit" class="pf-btn">Save Profile</button>
                    <a href="change-password.php" class="pf-btn">Change Password</a>
                    <a href="index.php" class="pf-btn">Back to Home</a>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>
