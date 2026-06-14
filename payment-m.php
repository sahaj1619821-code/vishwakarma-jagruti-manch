<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$conn = mysqli_connect("127.0.0.1", "root", "", "vjm_db", 3307);
if (!$conn) die("Database Connection Failed: " . mysqli_connect_error());
mysqli_set_charset($conn, "utf8mb4");

function vjm_user_id() {
    // Payment par hamesha naye matrimonial user_id ko priority dena hai.
    // Isse normal/site old session ki wajah se galat profile name ya plan assign nahi hoga.
    if (isset($_GET['user_id']) && (int)$_GET['user_id'] > 0) return (int)$_GET['user_id'];
    if (isset($_SESSION['matrimonial_user_id'])) return (int)$_SESSION['matrimonial_user_id'];
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

if (isset($_GET['user_id']) && (int)$_GET['user_id'] > 0) {
    $_SESSION['matrimonial_user_id'] = $user_id;
}

$plan_id = (int)(($_GET['plan_id'] ?? 0) ?: ($_SESSION['selected_matrimonial_plan_id'] ?? $_SESSION['matrimonial_selected_plan_id'] ?? 0));
if ($plan_id <= 0) vjm_redirect("plan.php");

$_SESSION['selected_matrimonial_plan_id'] = $plan_id;
$_SESSION['matrimonial_selected_plan_id'] = $plan_id;

$plan_q = mysqli_query($conn, "SELECT * FROM matrimonial_plans WHERE id=$plan_id AND status='active' LIMIT 1");
$plan = $plan_q ? mysqli_fetch_assoc($plan_q) : null;
if (!$plan) {
    unset($_SESSION['selected_matrimonial_plan_id'], $_SESSION['matrimonial_selected_plan_id']);
    vjm_redirect("plan.php");
}

$message = "";
$price = (float)($plan['price'] ?? 0);
$duration_days = (int)($plan['duration_days'] ?? 30);
if ($duration_days <= 0) $duration_days = 30;

$qr_q = mysqli_query($conn, "SELECT * FROM payment_qr WHERE status='active' ORDER BY id DESC LIMIT 1");
$qr = $qr_q ? mysqli_fetch_assoc($qr_q) : null;
$qr_image = (!empty($qr['qr_image'])) ? "images/" . $qr['qr_image'] : "images/payment-qr.png";
$upi_id = $qr['upi_id'] ?? "";

if ($price <= 0) {
    mysqli_query($conn, "
        INSERT INTO matrimonial_user_plans
        (user_id, plan_id, start_date, end_date, payment_status, transaction_id, used_profile_views, used_chat_count)
        VALUES ($user_id, $plan_id, NOW(), DATE_ADD(NOW(), INTERVAL $duration_days DAY), 'approved', 'FREE', 0, 0)
    ");
    unset($_SESSION['selected_matrimonial_plan_id'], $_SESSION['matrimonial_selected_plan_id']);
    echo "<script>alert('Free plan activate हो गया है.'); window.location.href='matrimonial-entry.php';</script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transaction_id = trim($_POST['transaction_id'] ?? '');
    if ($transaction_id == '') {
        $message = "Transaction ID डालना जरूरी है.";
    } else {
        $tid = mysqli_real_escape_string($conn, $transaction_id);
        mysqli_query($conn, "
            INSERT INTO matrimonial_user_plans
            (user_id, plan_id, start_date, end_date, payment_status, transaction_id, used_profile_views, used_chat_count)
            VALUES ($user_id, $plan_id, NULL, NULL, 'pending', '$tid', 0, 0)
        ");
        unset($_SESSION['selected_matrimonial_plan_id'], $_SESSION['matrimonial_selected_plan_id']);
        echo "<script>alert('Payment request submit हो गई है. Admin approve करने के बाद matrimonial page open होगा.'); window.location.href='matrimonial-entry.php';</script>";
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
<meta charset="UTF-8">
<title>Matrimonial Payment</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
*{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;background:#070000;color:#fff}.payment-page{min-height:calc(100vh - 120px);padding:25px 15px;display:flex;justify-content:center;align-items:center}.payment-card{width:100%;max-width:520px;background:#260006;border:1px solid #9b5b13;border-radius:12px;padding:26px;text-align:center;box-shadow:0 18px 45px rgba(0,0,0,.55)}.payment-card h1{color:#ffc328;font-family:Georgia,serif;margin:0 0 10px}.plan-name{color:#ffd35a;margin-bottom:16px;line-height:1.6}.qr-img{width:210px;max-width:100%;background:#fffaf3;padding:8px;border-radius:8px;border:1px solid #ffb22c;margin:12px auto;display:block}.upi-id{color:#ffc328;font-weight:bold;margin:10px 0}input{width:100%;padding:13px;border-radius:6px;border:1px solid #9b5b13;margin:14px 0;outline:none;color:#3b0010}.btn{width:100%;border:0;border-radius:6px;padding:13px;background:linear-gradient(#ffd65b,#ff9d00);color:#220000;font-weight:bold;cursor:pointer;font-size:16px}.message{margin-bottom:12px;color:#ffb3b3;background:#3b0010;border:1px solid #ff4d4d;padding:10px;border-radius:6px}
</style>
</head>
<body>
<?php include 'header.php'; ?>
<div class="payment-page">
    <div class="payment-card">
        <h1>Payment QR Code</h1>
        <div class="plan-name">Selected Plan: <b><?= vjm_e($plan['plan_name']); ?></b><br>Amount: <b>₹<?= number_format((float)$plan['price']); ?></b></div>
        <?php if ($message != "") { ?><div class="message"><?= vjm_e($message); ?></div><?php } ?>
        <p>Payment करके Transaction ID नीचे डालें।</p>
        <img class="qr-img" src="<?= vjm_e($qr_image); ?>" alt="Payment QR Code">
        <?php if ($upi_id != "") { ?><div class="upi-id">UPI ID: <?= vjm_e($upi_id); ?></div><?php } ?>
        <form method="POST">
            <input type="text" name="transaction_id" placeholder="Enter Transaction ID" required>
            <button class="btn" type="submit">Submit Payment</button>
        </form>
    </div>
</div>
</body>
</html>
