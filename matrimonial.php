<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$conn = mysqli_connect("127.0.0.1", "root", "", "vjm_db", 3307);
if (!$conn) die("Database Connection Failed: " . mysqli_connect_error());
mysqli_set_charset($conn, "utf8mb4");
$defaultAvatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200' viewBox='0 0 200 200'%3E%3Crect width='200' height='200' fill='%23fff3d0'/%3E%3Ccircle cx='100' cy='72' r='38' fill='%23ffbf22'/%3E%3Cpath d='M36 178c8-45 40-68 64-68s56 23 64 68' fill='%23ff8a00'/%3E%3Ctext x='100' y='194' font-size='18' text-anchor='middle' fill='%23700018' font-family='Arial'%3EUser%3C/text%3E%3C/svg%3E";

function vjm_user_id() {
    if (isset($_SESSION['site_user_id'])) return (int)$_SESSION['site_user_id'];
    if (isset($_SESSION['user_id'])) return (int)$_SESSION['user_id'];
    return 0;
}

function vjm_e($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function vjm_col_exists($conn, $table, $column) {
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $q = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $q && mysqli_num_rows($q) > 0;
}

function vjm_profile($conn, $site_user_id) {
    $site_user_id = (int)$site_user_id;
    if ($site_user_id <= 0) return null;

    if (vjm_col_exists($conn, 'matrimonial_users', 'user_id')) {
        $q = mysqli_query($conn, "SELECT * FROM matrimonial_users WHERE user_id = $site_user_id ORDER BY id DESC LIMIT 1");
    } else {
        $q = mysqli_query($conn, "SELECT * FROM matrimonial_users WHERE id = $site_user_id ORDER BY id DESC LIMIT 1");
    }

    return ($q && mysqli_num_rows($q) > 0) ? mysqli_fetch_assoc($q) : null;
}

function vjm_latest_plan($conn, $profile, $site_user_id) {
    $site_user_id = (int)$site_user_id;
    $profile_id = (int)($profile['id'] ?? 0);

    $ids = [];
    if ($profile_id > 0) $ids[] = $profile_id;
    if ($site_user_id > 0) $ids[] = $site_user_id;
    if (!empty($profile['user_id'])) $ids[] = (int)$profile['user_id'];

    $ids = array_values(array_unique(array_filter($ids)));
    if (empty($ids)) return null;

    $id_sql = implode(',', array_map('intval', $ids));

    $q = mysqli_query($conn, "
        SELECT mup.*, mp.plan_name, mp.price, mp.duration_days, mp.profile_view_limit, mp.chat_limit
        FROM matrimonial_user_plans mup
        JOIN matrimonial_plans mp ON mp.id = mup.plan_id
        WHERE mup.user_id IN ($id_sql)
        ORDER BY mup.id DESC
        LIMIT 1
    ");

    return ($q && mysqli_num_rows($q) > 0) ? mysqli_fetch_assoc($q) : null;
}

function vjm_active_plan($conn, $plan) {
    if (!$plan) return false;

    $status = strtolower(trim((string)($plan['payment_status'] ?? '')));
    if ($status !== 'approved') return false;

    $id = (int)($plan['id'] ?? 0);
    $end = $plan['end_date'] ?? '';
    $used = (int)($plan['used_profile_views'] ?? 0);
    $limit = (int)($plan['profile_view_limit'] ?? 0);

    if ($end !== '' && strtotime($end) < time()) {
        if ($id > 0) mysqli_query($conn, "UPDATE matrimonial_user_plans SET payment_status='expired' WHERE id=$id");
        return false;
    }

    if ($limit > 0 && $used >= $limit) {
        if ($id > 0) mysqli_query($conn, "UPDATE matrimonial_user_plans SET payment_status='expired' WHERE id=$id");
        return false;
    }

    return true;
}

function vjm_redirect($url) {
    header("Location: $url");
    exit;
}

function vjm_name($r) {
    if (!empty($r['full_name'])) return $r['full_name'];
    if (!empty($r['name'])) return $r['name'];
    return 'Profile';
}

function vjm_first($row, $keys, $default = '') {
    foreach ($keys as $k) {
        if (isset($row[$k]) && trim((string)$row[$k]) !== '') return $row[$k];
    }
    return $default;
}

function vjm_age($row) {
    if (!empty($row['age'])) return $row['age'];
    if (!empty($row['dob'])) {
        try {
            $dob = new DateTime($row['dob']);
            $today = new DateTime();
            return $today->diff($dob)->y;
        } catch (Exception $e) {}
    }
    return '';
}

function vjm_photo($r, $defaultAvatar) {
    $p = trim((string)($r['profile_photo'] ?? $r['photo'] ?? $r['image'] ?? ''));
    $default = 'images/default-profile.png';

    if ($p === '') {
        return $defaultAvatar;
    }

    $p = str_replace('\\', '/', $p);

    if (preg_match('#^https?://#i', $p)) {
        return $p;
    }

    $direct = ltrim($p, '/');
    if (file_exists(__DIR__ . '/' . $direct)) {
        return $direct;
    }

    $base = basename($p);
    $folders = [
        'profile-photo/',
        'profile-photp/',
        'uploads/matrimonial/',
        'uploads/',
        'images/',
        ''
    ];

    foreach ($folders as $folder) {
        $try = $folder . $base;
        if ($try !== '' && file_exists(__DIR__ . '/' . $try)) {
            return $try;
        }
    }

    return $defaultAvatar;
}

$site_user_id = vjm_user_id();
if ($site_user_id <= 0) vjm_redirect("login.php");

$my_profile = vjm_profile($conn, $site_user_id);
if (!$my_profile) vjm_redirect("matrimonial-entry.php");

$profile_status = strtolower(trim((string)($my_profile['status'] ?? '')));
if ($profile_status !== 'approved') vjm_redirect("matrimonial-entry.php");

$plan = vjm_latest_plan($conn, $my_profile, $site_user_id);
if (!vjm_active_plan($conn, $plan)) vjm_redirect("matrimonial-entry.php");

/* Interest request system: chat tabhi open hoga jab dono side interest ho */
mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS matrimonial_interests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        interested_user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_interest (user_id, interested_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

function vjm_create_notification_table($conn) {
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

function vjm_profile_display_name($conn, $profile_id) {
    $profile_id = (int)$profile_id;
    if ($profile_id <= 0) return 'Someone';
    $q = mysqli_query($conn, "SELECT * FROM matrimonial_users WHERE id=$profile_id LIMIT 1");
    if ($q && mysqli_num_rows($q) > 0) {
        $r = mysqli_fetch_assoc($q);
        if (!empty($r['full_name'])) return $r['full_name'];
        if (!empty($r['name'])) return $r['name'];
    }
    return 'Someone';
}

function vjm_add_matrimonial_notification($conn, $receiver_profile_id, $sender_profile_id, $type, $title, $message, $link) {
    vjm_create_notification_table($conn);
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


function vjm_interest_exists($conn, $from_id, $to_id) {
    $from_id = (int)$from_id;
    $to_id = (int)$to_id;
    if ($from_id <= 0 || $to_id <= 0) return false;
    $q = mysqli_query($conn, "SELECT id FROM matrimonial_interests WHERE user_id=$from_id AND interested_user_id=$to_id LIMIT 1");
    return ($q && mysqli_num_rows($q) > 0);
}

function vjm_add_interest($conn, $from_id, $to_id) {
    $from_id = (int)$from_id;
    $to_id = (int)$to_id;
    if ($from_id <= 0 || $to_id <= 0 || $from_id === $to_id) return false;
    mysqli_query($conn, "INSERT IGNORE INTO matrimonial_interests (user_id, interested_user_id, created_at) VALUES ($from_id, $to_id, NOW())");
    return mysqli_affected_rows($conn) > 0;
}

$my_profile_id = (int)($my_profile['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['interest_action'], $_POST['profile_id'])) {
    $target_id = (int)$_POST['profile_id'];
    $action = $_POST['interest_action'];

    if ($target_id > 0 && $target_id !== $my_profile_id) {
        if ($action === 'send_interest' || $action === 'accept_interest') {
            $new_interest = vjm_add_interest($conn, $my_profile_id, $target_id);
            if ($new_interest) {
                $sender_name = vjm_profile_display_name($conn, $my_profile_id);
                if ($action === 'accept_interest') {
                    vjm_add_matrimonial_notification($conn, $target_id, $my_profile_id, 'interest_accept', 'Interest Accepted', $sender_name . ' ने आपकी interest request accept कर ली है. अब आप chat कर सकते हैं.', 'matrimonial-chat.php?id=' . $my_profile_id);
                } else {
                    vjm_add_matrimonial_notification($conn, $target_id, $my_profile_id, 'interest_request', 'New Interest Request', $sender_name . ' ने आपको matrimonial interest request भेजी है.', 'view-profile.php?id=' . $my_profile_id);
                }
            }
        }
    }

    $back = 'matrimonial.php';
    if (!empty($_POST['search'])) {
        $back .= '?search=' . urlencode($_POST['search']);
    }
    header("Location: $back");
    exit;
}

$search = trim($_GET['search'] ?? '');

$where = "LOWER(TRIM(status))='approved'";
if ($my_profile_id > 0) {
    $where .= " AND id != $my_profile_id";
}

if ($search !== '') {
    $s = mysqli_real_escape_string($conn, $search);
    $search_parts = [];
    foreach (['full_name','name','city','current_city','district','tahsil','village','village_name','village_rajasthan','profession','occupation','gotra'] as $col) {
        if (vjm_col_exists($conn, 'matrimonial_users', $col)) {
            $search_parts[] = "`$col` LIKE '%$s%'";
        }
    }
    if (!empty($search_parts)) {
        $where .= " AND (" . implode(" OR ", $search_parts) . ")";
    }
}

$profiles = mysqli_query($conn, "SELECT * FROM matrimonial_users WHERE $where ORDER BY id DESC LIMIT 100");
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <title>Matrimonial Profiles</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/matrimonial.css?v=80">
</head>
<body>
<?php include 'header.php'; ?>

<div class="vjm-matri-wrap">
    <div class="vjm-matri-head">
        <div>
            <h1>Matrimonial Profiles</h1>
            <p>Approved profiles only. आपका plan active है।</p>
        </div>
        <div class="vjm-plan-badge">Plan: <?= vjm_e($plan['plan_name'] ?? 'Active'); ?></div>
    </div>

    <form class="vjm-matri-filter" method="GET">
        <input type="text" name="search" value="<?= vjm_e($search); ?>" placeholder="Name, City, Village, Profession search">
        <button type="submit">Search</button>
    </form>

    <?php if ($profiles && mysqli_num_rows($profiles) > 0) { ?>
        <div class="vjm-profile-grid">
            <?php while ($r = mysqli_fetch_assoc($profiles)) {
                $pid = (int)($r['id'] ?? 0);
                $img = vjm_photo($r, $defaultAvatar);
                $age = vjm_age($r);
                $city = vjm_first($r, ['current_city', 'city']);
                $village = vjm_first($r, ['village_rajasthan', 'village', 'village_name']);
                $profession = vjm_first($r, ['profession', 'occupation']);
            ?>
                <div class="vjm-profile-card">
                    <div class="vjm-photo-box">
                        <img src="<?= vjm_e($img); ?>" alt="Profile" onerror="this.onerror=null;this.src='<?= vjm_e($defaultAvatar); ?>';"  onerror="this.onerror=null;this.src='images/default-profile.png';">
                    </div>

                    <div class="vjm-profile-body">
                        <h3><?= vjm_e(vjm_name($r)); ?></h3>

                        <div class="vjm-profile-meta">
                            <div><b>Gender:</b><span><?= vjm_e($r['gender'] ?? 'Not Added'); ?></span></div>
                            <div><b>Age/DOB:</b><span><?= vjm_e($age ?: ($r['dob'] ?? 'Not Added')); ?></span></div>
                            <div><b>City:</b><span><?= vjm_e($city ?: 'Not Added'); ?></span></div>
                            <div><b>Village:</b><span><?= vjm_e($village ?: 'Not Added'); ?></span></div>
                            <div><b>Gotra:</b><span><?= vjm_e($r['gotra'] ?? 'Not Added'); ?></span></div>
                            <div><b>Profession:</b><span><?= vjm_e($profession ?: 'Not Added'); ?></span></div>
                            <div><b>Marital:</b><span><?= vjm_e($r['marital_status'] ?? 'Not Added'); ?></span></div>
                        </div>

                        <?php
                            $my_sent = vjm_interest_exists($conn, $my_profile_id, $pid);
                            $they_sent = vjm_interest_exists($conn, $pid, $my_profile_id);
                            $is_match = ($my_sent && $they_sent);
                        ?>

                        <div class="vjm-profile-actions">
                            <a class="vjm-view-btn" href="view-profile.php?id=<?= $pid; ?>">👁 View Profile</a>

                            <?php if ($is_match) { ?>
                                <a class="vjm-chat-btn" href="matrimonial-chat.php?id=<?= $pid; ?>">💬 Chat Now</a>
                            <?php } elseif ($they_sent) { ?>
                                <form method="POST" class="vjm-interest-form">
                                    <input type="hidden" name="profile_id" value="<?= $pid; ?>">
                                    <input type="hidden" name="interest_action" value="accept_interest">
                                    <input type="hidden" name="search" value="<?= vjm_e($search); ?>">
                                    <button type="submit" class="vjm-accept-btn">✅ Accept Interest</button>
                                </form>
                            <?php } elseif ($my_sent) { ?>
                                <button type="button" class="vjm-sent-btn" disabled>⏳ Request Sent</button>
                            <?php } else { ?>
                                <form method="POST" class="vjm-interest-form">
                                    <input type="hidden" name="profile_id" value="<?= $pid; ?>">
                                    <input type="hidden" name="interest_action" value="send_interest">
                                    <input type="hidden" name="search" value="<?= vjm_e($search); ?>">
                                    <button type="submit" class="vjm-interest-btn">💛 Send Interest</button>
                                </form>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
    <?php } else { ?>
        <div class="vjm-empty-box">अभी कोई approved matrimonial profile उपलब्ध नहीं है।</div>
    <?php } ?>
</div>

</body>
</html>
