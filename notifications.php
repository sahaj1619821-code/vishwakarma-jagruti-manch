<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn = mysqli_connect("127.0.0.1", "root", "", "vjm_db", 3307);
if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}
mysqli_set_charset($conn, "utf8mb4");

function nf_e($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function nf_col_exists($conn, $table, $column) {
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $q = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($q && mysqli_num_rows($q) > 0);
}

function nf_table_exists($conn, $table) {
    $table = mysqli_real_escape_string($conn, $table);
    $q = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    return ($q && mysqli_num_rows($q) > 0);
}

function nf_photo_src($photo) {
    $default = "images/user.png";
    $photo = trim((string)$photo);
    if ($photo === '') return $default;

    $photo = str_replace('\\', '/', $photo);
    if (preg_match('#^https?://#i', $photo)) return $photo;

    $direct = ltrim($photo, '/');
    if (file_exists(__DIR__ . '/' . $direct)) return $direct;

    $base = basename($photo);
    foreach (["profile-photo/", "profile-photp/", "uploads/matrimonial/", "uploads/", "images/"] as $dir) {
        $try = $dir . $base;
        if (file_exists(__DIR__ . '/' . $try)) return $try;
    }
    return $default;
}

$site_user_id = (int)($_SESSION['site_user_id'] ?? $_SESSION['user_id'] ?? 0);
if ($site_user_id <= 0) {
    header("Location: login.php");
    exit;
}

if (!nf_table_exists($conn, 'matrimonial_notifications')) {
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

$my_profile_id = 0;
$my_q = mysqli_query($conn, "SELECT id FROM matrimonial_users WHERE user_id=$site_user_id ORDER BY id DESC LIMIT 1");
if ($my_q && mysqli_num_rows($my_q) > 0) {
    $my_profile_id = (int)mysqli_fetch_assoc($my_q)['id'];
}

if ($my_profile_id <= 0) {
    // Fallback: some old records may not have user_id properly linked
    $my_profile_id = (int)($_SESSION['matrimonial_user_id'] ?? 0);
}

if (isset($_GET['mark_read'])) {
    $nid = (int)$_GET['mark_read'];
    if ($nid > 0 && $my_profile_id > 0) {
        mysqli_query($conn, "UPDATE matrimonial_notifications SET is_read=1 WHERE id=$nid AND receiver_profile_id=$my_profile_id");
    }
    header("Location: notifications.php");
    exit;
}

if (isset($_GET['read_all']) && $my_profile_id > 0) {
    mysqli_query($conn, "UPDATE matrimonial_notifications SET is_read=1 WHERE receiver_profile_id=$my_profile_id");
    header("Location: notifications.php");
    exit;
}

$select_name = nf_col_exists($conn, 'matrimonial_users', 'full_name') ? 'mu.full_name' : "''";
$select_photo_parts = [];
if (nf_col_exists($conn, 'matrimonial_users', 'profile_photo')) $select_photo_parts[] = 'mu.profile_photo';
if (nf_col_exists($conn, 'matrimonial_users', 'photo')) $select_photo_parts[] = 'mu.photo';
if (nf_col_exists($conn, 'matrimonial_users', 'image')) $select_photo_parts[] = 'mu.image';
$select_photo = !empty($select_photo_parts) ? 'COALESCE(' . implode(',', $select_photo_parts) . ")" : "''";

$notifications = false;
if ($my_profile_id > 0) {
    $sql = "
        SELECT 
            n.id,
            n.receiver_profile_id,
            n.sender_profile_id,
            n.type,
            n.title,
            n.message,
            n.link,
            n.is_read,
            n.created_at,
            $select_name AS sender_name,
            $select_photo AS sender_photo
        FROM matrimonial_notifications n
        LEFT JOIN matrimonial_users mu ON mu.id = n.sender_profile_id
        WHERE n.receiver_profile_id = $my_profile_id
        ORDER BY n.id DESC
        LIMIT 100
    ";
    $notifications = mysqli_query($conn, $sql);
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
*{box-sizing:border-box}body{margin:0;background:#070000;color:#fff;font-family:Arial,sans-serif}.nf-wrap{max-width:1100px;margin:25px auto 60px;padding:0 18px}.nf-head{background:linear-gradient(90deg,#520010,#210004);border:1px solid #9b5b13;border-radius:16px;padding:22px 24px;display:flex;align-items:center;justify-content:space-between;gap:15px}.nf-head h1{margin:0;color:#ffc328;font-family:Georgia,serif}.nf-head a,.nf-btn{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;border:1px solid #ffb22c;border-radius:10px;background:linear-gradient(#ffd65b,#ff9d00);color:#220000;font-weight:900;padding:11px 16px}.nf-list{margin-top:18px;display:grid;gap:14px}.nf-card{background:#fffaf3;color:#240006;border:1px solid #ffb22c;border-radius:14px;padding:15px;display:grid;grid-template-columns:58px 1fr auto;gap:14px;align-items:center;box-shadow:0 12px 30px rgba(0,0,0,.35)}.nf-card.unread{box-shadow:0 0 18px rgba(255,195,40,.45);border-color:#ffc328}.nf-photo{width:58px;height:58px;border-radius:50%;object-fit:cover;background:#ffd35a}.nf-title{font-size:18px;font-weight:900;color:#700018;margin-bottom:5px}.nf-msg{line-height:1.45}.nf-time{font-size:12px;color:#6b4d20;margin-top:6px}.nf-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}.nf-empty{margin-top:18px;background:#260006;border:1px solid #9b5b13;border-radius:14px;padding:28px;text-align:center;color:#ffd35a;font-weight:800}@media(max-width:700px){.nf-head{flex-direction:column;text-align:center}.nf-card{grid-template-columns:48px 1fr}.nf-actions{grid-column:1/-1;justify-content:stretch}.nf-actions a{width:100%}}
</style>
</head>
<body>
<?php include 'header.php'; ?>
<div class="nf-wrap">
    <div class="nf-head">
        <h1><i class="fa-solid fa-bell"></i> Notifications</h1>
        <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:center;">
            <a href="notifications.php?read_all=1">Mark all read</a>
            <a href="index.php">Back Home</a>
        </div>
    </div>

    <?php if ($my_profile_id <= 0) { ?>
        <div class="nf-empty">Matrimonial profile नहीं मिली। पहले matrimonial profile बनाएं।</div>
    <?php } elseif ($notifications && mysqli_num_rows($notifications) > 0) { ?>
        <div class="nf-list">
            <?php while ($n = mysqli_fetch_assoc($notifications)) { 
                $link = trim((string)($n['link'] ?? '')) ?: 'matrimonial.php';
                $senderName = trim((string)($n['sender_name'] ?? '')) ?: 'User';
                $photo = nf_photo_src($n['sender_photo'] ?? '');
            ?>
                <div class="nf-card <?= ((int)$n['is_read'] === 0) ? 'unread' : ''; ?>">
                    <img class="nf-photo" src="<?= nf_e($photo); ?>" onerror="this.onerror=null;this.src='images/user.png';" alt="User">
                    <div>
                        <div class="nf-title"><?= nf_e($n['title'] ?? 'Notification'); ?></div>
                        <div class="nf-msg"><?= nf_e($n['message'] ?? ''); ?></div>
                        <div class="nf-time">From: <?= nf_e($senderName); ?> | <?= nf_e(date('d-m-Y h:i A', strtotime($n['created_at'] ?? 'now'))); ?></div>
                    </div>
                    <div class="nf-actions">
                        <a class="nf-btn" href="<?= nf_e($link); ?>">Open</a>
                        <?php if ((int)$n['is_read'] === 0) { ?>
                            <a class="nf-btn" href="notifications.php?mark_read=<?= (int)$n['id']; ?>">Mark Read</a>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>
    <?php } else { ?>
        <div class="nf-empty">कोई notification नहीं है।</div>
    <?php } ?>
</div>
</body>
</html>
