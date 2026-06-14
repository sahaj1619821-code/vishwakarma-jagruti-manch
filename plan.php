<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$conn = mysqli_connect("127.0.0.1", "root", "", "vjm_db", 3307);
if (!$conn) die("Database Connection Failed: " . mysqli_connect_error());
mysqli_set_charset($conn, "utf8mb4");

function vjm_user_id() {
    if (isset($_SESSION['site_user_id'])) return (int)$_SESSION['site_user_id'];
    if (isset($_SESSION['matrimonial_user_id'])) return (int)$_SESSION['matrimonial_user_id'];
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

function vjm_profile($conn, $user_id) {
    if (vjm_col_exists($conn, 'matrimonial_users', 'user_id')) {
        $where = "(user_id = $user_id OR id = $user_id)";
    } else {
        $where = "id = $user_id";
    }

    $q = mysqli_query($conn, "SELECT * FROM matrimonial_users WHERE $where ORDER BY id DESC LIMIT 1");
    return $q ? mysqli_fetch_assoc($q) : null;
}

function vjm_latest_plan($conn, $user_id) {
    $q = mysqli_query($conn, "
        SELECT mup.*, mp.plan_name, mp.price, mp.duration_days, mp.profile_view_limit, mp.chat_limit
        FROM matrimonial_user_plans mup
        JOIN matrimonial_plans mp ON mp.id = mup.plan_id
        WHERE mup.user_id = $user_id
        ORDER BY mup.id DESC
        LIMIT 1
    ");
    return $q ? mysqli_fetch_assoc($q) : null;
}

function vjm_active_plan($conn, $plan) {
    if (!$plan) return false;

    $status = strtolower(trim((string)($plan['payment_status'] ?? '')));
    if ($status !== 'approved') return false;

    $id = (int)($plan['id'] ?? 0);
    $end = $plan['end_date'] ?? '';
    $used = (int)($plan['used_profile_views'] ?? 0);
    $limit = (int)($plan['profile_view_limit'] ?? 0);

    if ($end == '' || strtotime($end) < time()) {
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


$user_id = vjm_user_id();
if ($user_id <= 0) vjm_redirect("login.php");

$profile = vjm_profile($conn, $user_id);
$current_plan = vjm_latest_plan($conn, $user_id);
$has_active_plan = vjm_active_plan($conn, $current_plan);

$message_html = "";

/*
IMPORTANT:
plan.php अब auto matrimonial.php redirect नहीं करेगा.
यही redirect loop रोकता है.
अगर active plan है और profile approved है, तो user को button दिखेगा.
*/

if ($has_active_plan) {
    $profile_status = strtolower(trim((string)($profile['status'] ?? '')));
    if ($profile && $profile_status == 'approved') {
        $message_html = '<div class="message pending">आपका plan active है। <a href="matrimonial-entry.php" style="color:#ffc328;font-weight:900;">Matrimonial Open करें</a></div>';
    } else {
        $message_html = '<div class="message pending">आपका plan active है, लेकिन profile approval pending है। Admin approve करने के बाद matrimonial open होगा।</div>';
    }
}

/* Plans list */
$plans = mysqli_query($conn, "
    SELECT *
    FROM matrimonial_plans
    WHERE status='active'
    ORDER BY price ASC
");
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <title>Matrimonial Plan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/plan.css?v=30">
</head>
<body>
<?php include 'header.php'; ?>

<div class="plan-page">

    <div class="top-box">
        <h1>Matrimonial Subscription Plan</h1>
        <p>Plan select करें। Profile/Register और payment process पूरा होने के बाद admin approval पर matrimonial open होगा।</p>
    </div>

    <?= $message_html; ?>

    <?php if ($current_plan && strtolower($current_plan['payment_status'] ?? '') == 'pending') { ?>
        <div class="message pending">आपका payment approval के लिए pending है। Admin approve करने के बाद matrimonial page open होगा।</div>
    <?php } ?>

    <?php if ($current_plan && strtolower($current_plan['payment_status'] ?? '') == 'expired') { ?>
        <div class="message expired">आपका plan expire हो गया है। कृपया नया plan select करें।</div>
    <?php } ?>

    <?php if ($current_plan && strtolower($current_plan['payment_status'] ?? '') == 'rejected') { ?>
        <div class="message expired">आपका payment rejected हो गया है। कृपया फिर से plan select करें।</div>
    <?php } ?>

    <div class="plans only-plans">
        <?php if ($plans && mysqli_num_rows($plans) > 0) { ?>
            <?php while ($row = mysqli_fetch_assoc($plans)) { ?>
                <div class="plan-card">
                    <?php if ((float)$row['price'] <= 0) { ?><div class="free-tag">FREE</div><?php } ?>

                    <h2><?= vjm_e($row['plan_name']); ?></h2>
                    <div class="price">₹<?= number_format((float)$row['price']); ?></div>

                    <ul class="features">
                        <li>Duration: <?= (int)$row['duration_days']; ?> Days</li>
                        <li>Profile Views: <?= (int)$row['profile_view_limit']; ?></li>
                        <li>Chat Limit: <?= (int)$row['chat_limit']; ?></li>
                    </ul>

                    <form method="GET" action="register-m-m.php" class="form-box">
                        <input type="hidden" name="plan_id" value="<?= (int)$row['id']; ?>">
                        <button type="submit" class="btn <?= ((float)$row['price'] <= 0) ? 'free-btn' : ''; ?>">Select Plan</button>
                    </form>
                </div>
            <?php } ?>
        <?php } else { ?>
            <div class="message expired">कोई active plan available नहीं है। Admin panel से plan add करें।</div>
        <?php } ?>
    </div>
</div>
</body>
</html>
