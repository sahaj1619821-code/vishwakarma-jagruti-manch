<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn = mysqli_connect("127.0.0.1", "root", "", "vjm_db", 3307);
if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}
mysqli_set_charset($conn, "utf8mb4");

function vjm_e($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function vjm_col_exists($conn, $table, $column) {
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $q = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $q && mysqli_num_rows($q) > 0;
}

function vjm_table_exists($conn, $table) {
    $table = mysqli_real_escape_string($conn, $table);
    $q = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    return $q && mysqli_num_rows($q) > 0;
}

function vjm_redirect($url) {
    header("Location: $url");
    exit;
}

/*
    IMPORTANT FIX:
    Normal website login user ko primary rakha hai.
    Purani matrimonial session id se kisi aur profile ka pending page nahi dikhega.
*/
$site_user_id = (int)($_SESSION['site_user_id'] ?? $_SESSION['user_id'] ?? 0);

if ($site_user_id <= 0) {
    vjm_redirect("login.php");
}

if (!vjm_table_exists($conn, 'matrimonial_users')) {
    vjm_redirect("plan.php");
}

/* Agar old table me user_id column nahi hai to add karo */
if (!vjm_col_exists($conn, 'matrimonial_users', 'user_id')) {
    mysqli_query($conn, "ALTER TABLE matrimonial_users ADD user_id INT NULL AFTER id");
}

/* Current logged-in user ki hi matrimonial profile check hogi */
$profile_q = mysqli_query($conn, "
    SELECT *
    FROM matrimonial_users
    WHERE user_id = $site_user_id
    ORDER BY id DESC
    LIMIT 1
");

$profile = ($profile_q && mysqli_num_rows($profile_q) > 0) ? mysqli_fetch_assoc($profile_q) : null;

/* Current user ki matrimonial profile nahi hai to plan page */
if (!$profile) {
    unset($_SESSION['matrimonial_user_id'], $_SESSION['matrimonial_user_name'], $_SESSION['matrimonial_logged_in']);
    vjm_redirect("plan.php");
}

$profile_id   = (int)($profile['id'] ?? 0);
$profile_name = $profile['full_name'] ?? ($profile['name'] ?? '');

/* Ab matrimonial session current user/profile ke hisab se set hogi */
$_SESSION['matrimonial_user_id'] = $profile_id;
$_SESSION['matrimonial_user_name'] = $profile_name;
$_SESSION['matrimonial_logged_in'] = true;

$status = strtolower(trim((string)($profile['status'] ?? '')));
$verification_status = strtolower(trim((string)($profile['verification_status'] ?? '')));

$is_profile_approved = false;
if ($status === 'approved' || $status === 'active' || $verification_status === 'admin_approved') {
    $is_profile_approved = true;
}

$is_profile_rejected = false;
if ($status === 'rejected' || $verification_status === 'rejected') {
    $is_profile_rejected = true;
}

/* Plan current matrimonial profile id se check hoga, site user id se nahi */
$plan = null;
if (vjm_table_exists($conn, 'matrimonial_user_plans') && $profile_id > 0) {
    $plan_q = mysqli_query($conn, "
        SELECT mup.*, mp.plan_name, mp.price, mp.duration_days, mp.profile_view_limit, mp.chat_limit
        FROM matrimonial_user_plans mup
        LEFT JOIN matrimonial_plans mp ON mp.id = mup.plan_id
        WHERE mup.user_id = $profile_id
        ORDER BY mup.id DESC
        LIMIT 1
    ");
    $plan = ($plan_q && mysqli_num_rows($plan_q) > 0) ? mysqli_fetch_assoc($plan_q) : null;
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

/*
Flow:
- Current login user ki profile nahi: plan.php
- Profile approved + active plan: matrimonial.php
- Profile approved + payment pending: plan approval pending
- Profile approved + no plan: plan.php
- Profile pending: profile approval pending
*/
if ($is_profile_approved) {
    if (vjm_active_plan($conn, $plan)) {
        vjm_redirect("matrimonial.php");
    }

    $plan_status = strtolower(trim((string)($plan['payment_status'] ?? '')));

    if ($plan && $plan_status === 'pending') {
        $heading = "Plan Approval Pending";
        $message = "आपका " . ($plan['plan_name'] ?? 'selected') . " plan admin approval के लिए pending है।";
        $box_class = "pending-box";
        $button_text = "Back to Home";
        $button_link = "index.php";
    } else {
        vjm_redirect("plan.php");
    }
} else {
    $heading = "Profile Approval Pending";
    $message = "आपकी matrimonial profile admin approval के लिए pending है। Admin approve करने के बाद matrimonial page open होगा।";
    $box_class = "pending-box";
    $button_text = "Back to Home";
    $button_link = "index.php";
}

if ($is_profile_rejected) {
    $heading = "Profile Rejected";
    $message = "आपकी matrimonial profile reject हो गई है। कृपया plan select करके profile दुबारा register/update करें।";
    $box_class = "rejected-box";
    $button_text = "Select Plan Again";
    $button_link = "plan.php";
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <title>Matrimonial Status</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/matrimonial-entry.css?v=31">
</head>
<body>
<?php include 'header.php'; ?>

<div class="entry-page">
    <div class="status-card <?= vjm_e($box_class); ?>">
        <div class="icon-circle"><?= ($is_profile_rejected) ? '!' : '⏳'; ?></div>
        <h1><?= vjm_e($heading); ?></h1>
        <p><?= vjm_e($message); ?></p>

        <?php if (!empty($profile_name)) { ?>
            <div class="profile-name">Profile Name: <b><?= vjm_e($profile_name); ?></b></div>
        <?php } ?>

        <a href="<?= vjm_e($button_link); ?>" class="entry-btn"><?= vjm_e($button_text); ?></a>
    </div>
</div>
</body>
</html>
