<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$conn = mysqli_connect("127.0.0.1", "root", "", "vjm_db", 3307);
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
mysqli_set_charset($conn, "utf8mb4");
$defaultAvatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200' viewBox='0 0 200 200'%3E%3Crect width='200' height='200' fill='%23fff3d0'/%3E%3Ccircle cx='100' cy='72' r='38' fill='%23ffbf22'/%3E%3Cpath d='M36 178c8-45 40-68 64-68s56 23 64 68' fill='%23ff8a00'/%3E%3Ctext x='100' y='194' font-size='18' text-anchor='middle' fill='%23700018' font-family='Arial'%3EUser%3C/text%3E%3C/svg%3E";

function safe($value, $default = "Not Added") {
    if (isset($value) && trim((string)$value) !== "") {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
    return $default;
}

function first_value($row, $keys, $default = "Not Added") {
    foreach ($keys as $key) {
        if (isset($row[$key]) && trim((string)$row[$key]) !== "") {
            return $row[$key];
        }
    }
    return $default;
}

function profile_photo_src($row, $defaultAvatar) {
    $default = $defaultAvatar;
    $p = trim((string)($row['profile_photo'] ?? $row['photo'] ?? $row['image'] ?? ''));

    if ($p === '') {
        return $defaultAvatar;
    }

    $p = str_replace("\\", "/", $p);

    if (preg_match('#^https?://#i', $p)) {
        return $p;
    }

    $direct = ltrim($p, '/');
    if (file_exists(__DIR__ . "/" . $direct)) {
        return $direct;
    }

    $base = basename($p);

    foreach (["profile-photo/", "profile-photp/", "uploads/matrimonial/", "uploads/", "images/", ""] as $dir) {
        $try = $dir . $base;
        if ($try !== "" && file_exists(__DIR__ . "/" . $try)) {
            return $try;
        }
    }

    return $defaultAvatar;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    die("Invalid Profile ID");
}

$sql = "SELECT * FROM matrimonial_users WHERE id = ? AND LOWER(TRIM(status))='approved' LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows == 0) {
    die("Profile not found or not approved");
}

$row = $result->fetch_assoc();

/* Current logged-in user's matrimonial profile and interest system */
$site_user_id = (int)($_SESSION['site_user_id'] ?? $_SESSION['user_id'] ?? 0);
$my_profile_id = 0;
if ($site_user_id > 0) {
    $my_q = mysqli_query($conn, "SELECT * FROM matrimonial_users WHERE user_id=$site_user_id ORDER BY id DESC LIMIT 1");
    if ($my_q && mysqli_num_rows($my_q) > 0) {
        $my_profile_row = mysqli_fetch_assoc($my_q);
        $my_profile_id = (int)$my_profile_row['id'];
    }
}

mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS matrimonial_interests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        interested_user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_interest (user_id, interested_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

function vp_create_notification_table($conn) {
    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS matrimonial_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            receiver_profile_id INT NOT NULL,
            sender_profile_id INT NOT NULL,
            type VARCHAR(50) NOT NULL,
            title VARCHAR(180) NOT NULL,
            message TEXT NULL,
            link VARCHAR(255) NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_receiver_read (receiver_profile_id, is_read),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function vp_profile_display_name($conn, $profile_id) {
    $profile_id = (int)$profile_id;
    if ($profile_id <= 0) return 'Someone';
    $q = mysqli_query($conn, "SELECT * FROM matrimonial_users WHERE id=$profile_id LIMIT 1");
    if ($q && mysqli_num_rows($q) > 0) {
        $r = mysqli_fetch_assoc($q);
        if (!empty($r['full_name'])) return $r['full_name'];
            }
    return 'Someone';
}

function vp_add_matrimonial_notification($conn, $receiver_profile_id, $sender_profile_id, $type, $title, $message, $link) {
    vp_create_notification_table($conn);
    $receiver_profile_id = (int)$receiver_profile_id;
    $sender_profile_id = (int)$sender_profile_id;
    if ($receiver_profile_id <= 0 || $sender_profile_id <= 0 || $receiver_profile_id === $sender_profile_id) return false;

    $type = mysqli_real_escape_string($conn, $type);
    $title = mysqli_real_escape_string($conn, $title);
    $message = mysqli_real_escape_string($conn, $message);
    $link = mysqli_real_escape_string($conn, $link);

    mysqli_query($conn, "
        INSERT INTO matrimonial_notifications
        (receiver_profile_id, sender_profile_id, type, title, message, link, is_read, created_at)
        VALUES ($receiver_profile_id, $sender_profile_id, '$type', '$title', '$message', '$link', 0, NOW())
    ");
    return true;
}


function vp_interest_exists($conn, $from_id, $to_id) {
    $from_id = (int)$from_id;
    $to_id = (int)$to_id;
    if ($from_id <= 0 || $to_id <= 0) return false;
    $q = mysqli_query($conn, "SELECT id FROM matrimonial_interests WHERE user_id=$from_id AND interested_user_id=$to_id LIMIT 1");
    return ($q && mysqli_num_rows($q) > 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['interest_action']) && $my_profile_id > 0) {
    $target_id = (int)($_POST['profile_id'] ?? $id);
    $action = $_POST['interest_action'];
    if ($target_id > 0 && $target_id !== $my_profile_id) {
        mysqli_query($conn, "INSERT IGNORE INTO matrimonial_interests (user_id, interested_user_id, created_at) VALUES ($my_profile_id, $target_id, NOW())");
        $new_interest = mysqli_affected_rows($conn) > 0;
        if ($new_interest) {
            $sender_name = vp_profile_display_name($conn, $my_profile_id);
            if ($action === 'accept_interest') {
                vp_add_matrimonial_notification($conn, $target_id, $my_profile_id, 'interest_accept', 'Interest Accepted', $sender_name . ' ने आपकी interest request accept कर ली है. अब आप chat कर सकते हैं.', 'matrimonial-chat.php?id=' . $my_profile_id);
            } else {
                vp_add_matrimonial_notification($conn, $target_id, $my_profile_id, 'interest_request', 'New Interest Request', $sender_name . ' ने आपको matrimonial interest request भेजी है.', 'view-profile.php?id=' . $my_profile_id);
            }
        }
    }
    header("Location: view-profile.php?id=" . $id);
    exit;
}

$my_sent_interest = vp_interest_exists($conn, $my_profile_id, $id);
$they_sent_interest = vp_interest_exists($conn, $id, $my_profile_id);
$is_interest_match = ($my_sent_interest && $they_sent_interest);

$age = "Not Added";
if (!empty($row['age'])) {
    $age = $row['age'];
} elseif (!empty($row['dob'])) {
    try {
        $dob = new DateTime($row['dob']);
        $today = new DateTime();
        $age = $today->diff($dob)->y;
    } catch (Exception $e) {
        $age = "Not Added";
    }
}

$profile_img = profile_photo_src($row, $defaultAvatar);
$profile_name = first_value($row, ['full_name', 'name'], 'Profile');
$city = first_value($row, ['current_city', 'city']);
$village = first_value($row, ['village_rajasthan', 'village', 'village_name']);
$profession = first_value($row, ['profession', 'occupation']);
$address = first_value($row, ['current_address', 'address']);
$verify_status = first_value($row, ['verification_status', 'status'], "Not Verified");
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <title>View Profile - Vishwakarma Jagruti Manch</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        *{box-sizing:border-box}
        body{margin:0;background:#070000;color:#fff;font-family:Arial,sans-serif}
        .vp-header{max-width:1280px;margin:25px auto 18px;padding:22px 25px;border:1px solid #9b5b13;border-radius:16px;background:linear-gradient(90deg,#520010,#210004)}
        .vp-header h1{margin:0;color:#ffc328;font-family:Georgia,serif;font-size:38px}
        .vp-header p{margin:6px 0 0;color:#fff}
        .vp-wrapper{max-width:1280px;margin:0 auto 50px;padding:0 18px;display:grid;grid-template-columns:360px 1fr;gap:22px}
        .vp-left,.vp-right{background:#fffaf3;color:#270006;border:1px solid #ffb22c;border-radius:18px;box-shadow:0 18px 45px rgba(0,0,0,.45);overflow:hidden}
        .vp-left{height:max-content;text-align:center}
        .vp-img{width:100%;height:390px;object-fit:cover;background:#eee;display:block}
        .vp-left-body{padding:18px}
        .vp-left h2{margin:0 0 10px;color:#6b0018;font-size:28px}
        .vp-short{font-weight:800;line-height:1.6;color:#2a0008}
        .vp-badge{display:inline-block;margin-top:14px;background:linear-gradient(#ffd65b,#ff9d00);color:#210000;border-radius:30px;padding:10px 18px;font-weight:900}
        .vp-right{padding:24px}
        .vp-section-title{margin:0 0 18px;color:#6b0018;font-size:28px;border-bottom:2px solid #ffb22c;padding-bottom:10px}
        .vp-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
        .vp-box{background:#fff;border:1px solid #e7c06a;border-radius:12px;padding:13px;min-height:74px}
        .vp-box label{display:block;color:#7b001c;font-weight:900;margin-bottom:6px}
        .vp-box span{display:block;color:#1f0006;word-break:break-word;line-height:1.4}
        .vp-full{grid-column:1/-1}
        .vp-actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:22px}
        .vp-btn{display:inline-flex;align-items:center;justify-content:center;min-width:170px;text-align:center;text-decoration:none;background:linear-gradient(#ffd65b,#ff9d00);color:#210000;border:1px solid #ffb22c;border-radius:12px;padding:13px 20px;font-weight:900;box-shadow:0 0 18px rgba(255,191,34,.45)}
        @media(max-width:900px){.vp-wrapper{grid-template-columns:1fr}.vp-img{height:320px}.vp-grid{grid-template-columns:1fr}.vp-header h1{font-size:30px}}
    
        .vp-btn-button{border:none;cursor:pointer;font-size:16px;}
        .vp-btn-button:disabled{background:#5b4a2c !important;color:#ffdf8a !important;cursor:not-allowed !important;}
</style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="vp-header">
    <h1>Profile Details</h1>
    <p>Home &gt; Matrimonial &gt; View Profile</p>
</div>

<div class="vp-wrapper">
    <div class="vp-left">
        <img src="<?= safe($profile_img, 'images/default-profile.png'); ?>" alt="Profile Photo" class="vp-img" onerror="this.onerror=null;this.src='<?= safe($defaultAvatar); ?>';">

        <div class="vp-left-body">
            <h2><?= safe($profile_name); ?></h2>

            <div class="vp-short">
                <?= safe($age); ?> Years<br>
                <?= safe($city); ?><br>
                <?= safe($village); ?>
            </div>

            <div class="vp-badge"><?= safe($verify_status, "Not Verified"); ?></div>
        </div>
    </div>

    <div class="vp-right">
        <h2 class="vp-section-title">Personal Information</h2>

        <div class="vp-grid">
            <div class="vp-box"><label>Profile ID</label><span><?= safe($row['id'] ?? ''); ?></span></div>
            <div class="vp-box"><label>Full Name</label><span><?= safe($profile_name); ?></span></div>
            <div class="vp-box"><label>Gender</label><span><?= safe($row['gender'] ?? ''); ?></span></div>
            <div class="vp-box"><label>Date of Birth</label><span><?= safe($row['dob'] ?? ''); ?></span></div>
            <div class="vp-box"><label>Age</label><span><?= safe($age); ?></span></div>
            <div class="vp-box"><label>Height</label><span><?= safe($row['height'] ?? ''); ?></span></div>
            <div class="vp-box"><label>Gotra</label><span><?= safe($row['gotra'] ?? ''); ?></span></div>
            <div class="vp-box"><label>Marital Status</label><span><?= safe($row['marital_status'] ?? ''); ?></span></div>
            <div class="vp-box"><label>State</label><span><?= safe($row['state'] ?? ''); ?></span></div>
            <div class="vp-box"><label>Current City</label><span><?= safe($city); ?></span></div>
            <div class="vp-box"><label>District</label><span><?= safe($row['district'] ?? ''); ?></span></div>
            <div class="vp-box"><label>Tahsil</label><span><?= safe($row['tahsil'] ?? ''); ?></span></div>
            <div class="vp-box"><label>Village In Rajasthan</label><span><?= safe($village); ?></span></div>
            <div class="vp-box"><label>Education</label><span><?= safe($row['education'] ?? ''); ?></span></div>
            <div class="vp-box"><label>Profession / Occupation</label><span><?= safe($profession); ?></span></div>
            <div class="vp-box"><label>Income</label><span><?= safe($row['income'] ?? ''); ?></span></div>
            <div class="vp-box"><label>Father Name</label><span><?= safe($row['father_name'] ?? ''); ?></span></div>
            <div class="vp-box"><label>Mother Name</label><span><?= safe($row['mother_name'] ?? ''); ?></span></div>
            <div class="vp-box"><label>Mobile</label><span><?= safe($row['mobile'] ?? $row['mobile_email'] ?? ''); ?></span></div>
            <div class="vp-box"><label>Email</label><span><?= safe($row['email'] ?? ''); ?></span></div>
            <div class="vp-box vp-full"><label>Current Address</label><span><?= safe($address); ?></span></div>
        </div>

        <div class="vp-actions">
            <a href="matrimonial.php" class="vp-btn">← Back to Matrimonial</a>

            <?php if ($my_profile_id <= 0) { ?>
                <a href="plan.php" class="vp-btn">Create Matrimonial Profile</a>
            <?php } elseif ($is_interest_match) { ?>
                <a href="matrimonial-chat.php?id=<?= (int)$row['id']; ?>" class="vp-btn">💬 Chat Now</a>
            <?php } elseif ($they_sent_interest) { ?>
                <form method="POST" style="display:inline-flex;flex:1;min-width:180px;">
                    <input type="hidden" name="profile_id" value="<?= (int)$row['id']; ?>">
                    <input type="hidden" name="interest_action" value="accept_interest">
                    <button type="submit" class="vp-btn vp-btn-button">✅ Accept Interest</button>
                </form>
            <?php } elseif ($my_sent_interest) { ?>
                <button type="button" class="vp-btn vp-btn-button" disabled>⏳ Request Sent</button>
            <?php } else { ?>
                <form method="POST" style="display:inline-flex;flex:1;min-width:180px;">
                    <input type="hidden" name="profile_id" value="<?= (int)$row['id']; ?>">
                    <input type="hidden" name="interest_action" value="send_interest">
                    <button type="submit" class="vp-btn vp-btn-button">💛 Send Interest</button>
                </form>
            <?php } ?>
        </div>
    </div>
</div>

</body>
</html>
