<?php
include 'auth.php';

requireRole(['super_admin', 'hostel_manager', 'hostel_admin']);


/*
File: admin/hostel-booking.php
Tables: hostels, hostel_bookings
Upload folders:
- ../uploads/hostels/
- ../uploads/payments/
*/

$hostel_table  = 'hostels';
$booking_table = 'hostel_bookings';
$upload_dir    = '../uploads/hostels/';
$upload_db_dir = 'uploads/hostels/';

if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: $url");
        exit;
    }
}

if (!function_exists('table_exists')) {
    function table_exists($conn, $table) {
        $table = $conn->real_escape_string($table);
        $res = $conn->query("SHOW TABLES LIKE '$table'");
        return ($res && $res->num_rows > 0);
    }
}

if (!function_exists('h_column_exists')) {
    function h_column_exists($conn, $table, $column) {
        if (!table_exists($conn, $table)) {
            return false;
        }

        $table = $conn->real_escape_string($table);
        $column = $conn->real_escape_string($column);

        $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return ($res && $res->num_rows > 0);
    }
}

if (!function_exists('set_if_column')) {
    function set_if_column($conn, $table, $column, $value) {
        if (!h_column_exists($conn, $table, $column)) {
            return '';
        }

        $value = $conn->real_escape_string($value);
        return "`$column`='$value'";
    }
}

if (!function_exists('safe_upload_image')) {
    function safe_upload_image($file, $upload_dir, $upload_db_dir) {
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return '';
        }

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            return '';
        }

        if ($file['size'] > 2 * 1024 * 1024) {
            return '';
        }

        $new_name = 'hostel_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $target = $upload_dir . $new_name;

        if (move_uploaded_file($file['tmp_name'], $target)) {
            return $upload_db_dir . $new_name;
        }

        return '';
    }
}

if (!function_exists('booking_value')) {
    function booking_value($row, $new_col, $old_col = '') {
        if (isset($row[$new_col]) && $row[$new_col] !== '') {
            return $row[$new_col];
        }

        if ($old_col != '' && isset($row[$old_col]) && $row[$old_col] !== '') {
            return $row[$old_col];
        }

        return '';
    }
}

/* =========================
   HOSTEL ROLE PERMISSION
   super_admin      = sab hostels/bookings
   hostel_manager   = sirf allotted hostel
   Permission table: admin_hostel_permissions(admin_id, hostel_id)
========================= */

$admin_id = (int)($_SESSION['admin_id'] ?? ($_SESSION['user_id'] ?? ($_SESSION['id'] ?? 0)));
$admin_role = $_SESSION['admin_role'] ?? ($_SESSION['role'] ?? '');
$permission_table = 'admin_hostel_permissions';

if (!function_exists('is_super_admin_role')) {
    function is_super_admin_role() {
        global $admin_role;
        return $admin_role === 'super_admin';
    }
}

if (!function_exists('is_hostel_manager_role')) {
    function is_hostel_manager_role() {
        global $admin_role;
        return in_array($admin_role, ['hostel_manager','hostel_admin'], true);
    }
}

if (!function_exists('ensure_hostel_permission_table')) {
    function ensure_hostel_permission_table($conn) {
        $conn->query("
            CREATE TABLE IF NOT EXISTS `admin_hostel_permissions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `admin_id` INT NOT NULL,
                `hostel_id` INT NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `admin_hostel_unique` (`admin_id`, `hostel_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
}

if (!function_exists('get_assigned_hostel_ids')) {
    function get_assigned_hostel_ids($conn) {
        global $admin_id, $permission_table;

        $ids = [];

        if ($admin_id <= 0 || !table_exists($conn, $permission_table)) {
            return $ids;
        }

        $q = $conn->query("
            SELECT hostel_id 
            FROM `$permission_table` 
            WHERE admin_id = $admin_id
        ");

        if ($q) {
            while ($row = $q->fetch_assoc()) {
                $hid = (int)($row['hostel_id'] ?? 0);
                if ($hid > 0) {
                    $ids[] = $hid;
                }
            }
        }

        return array_values(array_unique($ids));
    }
}

if (!function_exists('ids_sql')) {
    function ids_sql($ids) {
        $clean = [];

        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $clean[] = $id;
            }
        }

        return implode(',', $clean);
    }
}

if (!function_exists('hostel_access_condition')) {
    function hostel_access_condition($alias = '') {
        global $assigned_hostel_ids;

        if (is_super_admin_role()) {
            return "1";
        }

        if (!is_hostel_manager_role()) {
            return "0";
        }

        $ids = ids_sql($assigned_hostel_ids);

        if ($ids == '') {
            return "0";
        }

        $prefix = $alias != '' ? "`$alias`." : '';
        return $prefix . "`id` IN ($ids)";
    }
}

if (!function_exists('booking_access_condition')) {
    function booking_access_condition($conn, $booking_table, $hostel_table, $booking_alias = '') {
        global $assigned_hostel_ids;

        if (is_super_admin_role()) {
            return "1";
        }

        if (!is_hostel_manager_role()) {
            return "0";
        }

        $ids = ids_sql($assigned_hostel_ids);

        if ($ids == '') {
            return "0";
        }

        $b = $booking_alias != '' ? "`$booking_alias`." : '';

        if (h_column_exists($conn, $booking_table, 'hostel_id')) {
            return $b . "`hostel_id` IN ($ids)";
        }

        /*
            Agar booking table me hostel_id nahi hai aur hostel_name column hai,
            to assigned hostel names se filter karega.
        */
        if (h_column_exists($conn, $booking_table, 'hostel_name') && h_column_exists($conn, $hostel_table, 'hostel_name')) {
            $names = [];
            $q = $conn->query("SELECT hostel_name FROM `$hostel_table` WHERE id IN ($ids)");

            if ($q) {
                while ($r = $q->fetch_assoc()) {
                    if (!empty($r['hostel_name'])) {
                        $names[] = "'" . $conn->real_escape_string($r['hostel_name']) . "'";
                    }
                }
            }

            if (!empty($names)) {
                return $b . "`hostel_name` IN (" . implode(',', $names) . ")";
            }
        }

        return "0";
    }
}

if (!function_exists('hostel_manager_can_access_hostel')) {
    function hostel_manager_can_access_hostel($hostel_id) {
        global $assigned_hostel_ids;

        if (is_super_admin_role()) {
            return true;
        }

        if (!is_hostel_manager_role()) {
            return false;
        }

        return in_array((int)$hostel_id, $assigned_hostel_ids, true);
    }
}

if (!function_exists('hostel_manager_can_access_booking')) {
    function hostel_manager_can_access_booking($conn, $booking_id, $booking_table, $hostel_table) {
        $booking_id = (int)$booking_id;

        if ($booking_id <= 0) {
            return false;
        }

        if (is_super_admin_role()) {
            return true;
        }

        $condition = booking_access_condition($conn, $booking_table, $hostel_table, '');
        $q = $conn->query("
            SELECT id 
            FROM `$booking_table`
            WHERE id = $booking_id
            AND $condition
            LIMIT 1
        ");

        return ($q && $q->num_rows > 0);
    }
}

ensure_hostel_permission_table($conn);
$assigned_hostel_ids = get_assigned_hostel_ids($conn);


/* =========================
   BOOKING ACTIONS
========================= */
if (isset($_GET['booking_action'], $_GET['booking_id']) && table_exists($conn, $booking_table)) {
    $booking_id = (int)$_GET['booking_id'];
    $action = $_GET['booking_action'];

    if (!hostel_manager_can_access_booking($conn, $booking_id, $booking_table, $hostel_table)) {
        die("Access Denied: Aapko is hostel ki booking access nahi hai.");
    }

    if ($action == 'approve') {
        $set = [];

        if (h_column_exists($conn, $booking_table, 'booking_status')) {
            $set[] = "booking_status='approved'";
        }

        /*
           Booking approve karte hi payment_status bhi paid kar rahe hain,
           taaki admin panel me approved booking ke saamne payment pending na dikhe.
        */
        if (h_column_exists($conn, $booking_table, 'payment_status')) {
            $set[] = "payment_status='paid'";
        }

        if (h_column_exists($conn, $booking_table, 'approved_at')) {
            $set[] = "approved_at=NOW()";
        }

        if (!empty($set)) {
            $conn->query("UPDATE `$booking_table` SET " . implode(',', $set) . " WHERE id=$booking_id");
        }
    }

    if ($action == 'reject') {
        if (h_column_exists($conn, $booking_table, 'booking_status')) {
            $conn->query("UPDATE `$booking_table` SET booking_status='rejected' WHERE id=$booking_id");
        }
    }

    if ($action == 'cancel') {
        if (h_column_exists($conn, $booking_table, 'booking_status')) {
            $conn->query("UPDATE `$booking_table` SET booking_status='cancelled' WHERE id=$booking_id");
        }
    }

    if ($action == 'mark_paid') {
        if (h_column_exists($conn, $booking_table, 'payment_status')) {
            $conn->query("UPDATE `$booking_table` SET payment_status='paid' WHERE id=$booking_id");
        }
    }

    if ($action == 'mark_failed') {
        if (h_column_exists($conn, $booking_table, 'payment_status')) {
            $conn->query("UPDATE `$booking_table` SET payment_status='failed' WHERE id=$booking_id");
        }
    }

    if ($action == 'delete') {
        $conn->query("DELETE FROM `$booking_table` WHERE id=$booking_id");
    }

    redirect('hostel-booking.php');
}

/* =========================
   HOSTEL EDIT / DELETE
========================= */
$edit = [];

if (isset($_GET['edit']) && table_exists($conn, $hostel_table)) {
    $id = (int)$_GET['edit'];

    if (!hostel_manager_can_access_hostel($id)) {
        die("Access Denied: Aapko is hostel ka access nahi hai.");
    }

    $res = $conn->query("SELECT * FROM `$hostel_table` WHERE id=$id");

    if ($res && $res->num_rows > 0) {
        $edit = $res->fetch_assoc();
    }
}

if (isset($_GET['delete']) && table_exists($conn, $hostel_table)) {
    $id = (int)$_GET['delete'];

    if (!is_super_admin_role()) {
        die("Access Denied: Hostel delete sirf super admin kar sakta hai.");
    }

    if (!hostel_manager_can_access_hostel($id)) {
        die("Access Denied: Aapko is hostel ka access nahi hai.");
    }

    $conn->query("DELETE FROM `$hostel_table` WHERE id=$id");
    redirect('hostel-booking.php');
}

/* =========================
   HOSTEL SAVE
========================= */
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_hostel']) && table_exists($conn, $hostel_table)) {
    $allowed_cols = [
        'hostel_name',
        'manager_name',
        'manager_mobile',
        'address',
        'city',
        'district',
        'state',
        'price_per_day',
        'total_rooms',
        'available_rooms',
        'description',
        'facilities',
        'rating',
        'rules',
        'status'
    ];

    $data = [];

    foreach ($allowed_cols as $col) {
        if (h_column_exists($conn, $hostel_table, $col) && isset($_POST[$col])) {
            $data[$col] = $_POST[$col];
        }
    }

    $uploaded_photo = safe_upload_image($_FILES['photo'] ?? null, $upload_dir, $upload_db_dir);

    if ($uploaded_photo != '') {
        if (h_column_exists($conn, $hostel_table, 'photo')) {
            $data['photo'] = $uploaded_photo;
        }

        if (h_column_exists($conn, $hostel_table, 'hostel_image')) {
            $data['hostel_image'] = basename($uploaded_photo);
        }
    } else {
        if (h_column_exists($conn, $hostel_table, 'photo') && isset($_POST['old_photo'])) {
            $data['photo'] = $_POST['old_photo'];
        }

        if (h_column_exists($conn, $hostel_table, 'hostel_image') && isset($_POST['old_hostel_image'])) {
            $data['hostel_image'] = $_POST['old_hostel_image'];
        }
    }

    if (isset($_POST['id']) && $_POST['id'] != '') {
        $id = (int)$_POST['id'];

        if (!surveyorCanAccessHostel($conn, $id)) {
            die("Access Denied: Aapko is gaon/city ka hostel edit access nahi hai.");
        }

        $set = [];

        foreach ($data as $k => $v) {
            $v = $conn->real_escape_string($v);
            $set[] = "`$k`='$v'";
        }

        if (!empty($set)) {
            $conn->query("UPDATE `$hostel_table` SET " . implode(',', $set) . " WHERE id=$id");
        }
    } else {
        if (!is_super_admin_role()) {
            die("Access Denied: Naya hostel add sirf super admin kar sakta hai.");
        }

        $keys = array_keys($data);
        $vals = [];

        foreach ($data as $v) {
            $vals[] = "'" . $conn->real_escape_string($v) . "'";
        }

        if (!empty($keys)) {
            $conn->query("INSERT INTO `$hostel_table` (`" . implode('`,`', $keys) . "`) VALUES (" . implode(',', $vals) . ")");
        }
    }

    redirect('hostel-booking.php');
}

/* =========================
   FILTERS
========================= */
$hostel_search  = $_GET['hostel_search'] ?? '';
$booking_search = $_GET['booking_search'] ?? '';
$booking_status = $_GET['booking_status'] ?? '';
$payment_status = $_GET['payment_status'] ?? '';

$hostel_where = "WHERE " . hostel_access_condition();

if ($hostel_search != '') {
    $s = $conn->real_escape_string($hostel_search);
    $hostel_where .= " AND (
        hostel_name LIKE '%$s%' 
        OR manager_name LIKE '%$s%' 
        OR manager_mobile LIKE '%$s%' 
        OR city LIKE '%$s%' 
        OR district LIKE '%$s%'
    )";
}

$booking_where = "WHERE " . booking_access_condition($conn, $booking_table, $hostel_table, 'b');

if ($booking_search != '') {
    $s = $conn->real_escape_string($booking_search);

    $search_parts = [];

    foreach (['name', 'guest_name', 'mobile', 'email', 'city', 'village', 'transaction_id', 'hostel_name'] as $col) {
        if (h_column_exists($conn, $booking_table, $col)) {
            $search_parts[] = "b.`$col` LIKE '%$s%'";
        }
    }

    if (!empty($search_parts)) {
        $booking_where .= " AND (" . implode(" OR ", $search_parts) . ")";
    }
}

if ($booking_status != '' && h_column_exists($conn, $booking_table, 'booking_status')) {
    $st = $conn->real_escape_string($booking_status);
    $booking_where .= " AND b.booking_status='$st'";
}

if ($payment_status != '' && h_column_exists($conn, $booking_table, 'payment_status')) {
    $pst = $conn->real_escape_string($payment_status);
    $booking_where .= " AND b.payment_status='$pst'";
}

/* =========================
   STATS
========================= */
$total_hostels = $total_bookings = $pending_bookings = $approved_bookings = 0;

if (table_exists($conn, $hostel_table)) {
    $stats_hostel_where = "WHERE " . hostel_access_condition();
    $q = $conn->query("SELECT COUNT(*) c FROM `$hostel_table` $stats_hostel_where");
    $total_hostels = $q ? (int)$q->fetch_assoc()['c'] : 0;
}

if (table_exists($conn, $booking_table)) {
    $stats_booking_where = "WHERE " . booking_access_condition($conn, $booking_table, $hostel_table, '');

    $q = $conn->query("SELECT COUNT(*) c FROM `$booking_table` $stats_booking_where");
    $total_bookings = $q ? (int)$q->fetch_assoc()['c'] : 0;

    if (h_column_exists($conn, $booking_table, 'booking_status')) {
        $q = $conn->query("SELECT COUNT(*) c FROM `$booking_table` $stats_booking_where AND booking_status='pending'");
        $pending_bookings = $q ? (int)$q->fetch_assoc()['c'] : 0;

        $q = $conn->query("SELECT COUNT(*) c FROM `$booking_table` $stats_booking_where AND booking_status='approved'");
        $approved_bookings = $q ? (int)$q->fetch_assoc()['c'] : 0;
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';

if (!table_exists($conn, $hostel_table)) {
    echo '<div class="alert alert-danger">Table <b>hostels</b> database में नहीं है।</div>';
}

if (!table_exists($conn, $booking_table)) {
    echo '<div class="alert alert-danger">Table <b>hostel_bookings</b> database में नहीं है।</div>';
}

if (is_hostel_manager_role() && empty($assigned_hostel_ids)) {
    echo '<div class="alert alert-warning">Aapko abhi koi hostel allot nahi hai. Super admin se hostel allot karwaye.</div>';
}
?>

<style>
.hostel-grid {
    display: grid;
    grid-template-columns: repeat(4,1fr);
    gap: 12px;
    margin-bottom: 18px;
}

.stat-card {
    background: #1d1d1d;
    border: 1px solid rgba(255,255,255,.12);
    border-radius: 14px;
    padding: 15px;
    color: #fff;
}

.stat-card h4 {
    margin: 0;
    font-size: 24px;
    color: #f4c542;
}

.stat-card p {
    margin: 4px 0 0;
    color: #ddd;
}

.img-thumb {
    width: 75px;
    height: 55px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid #ddd;
}

.filter-box {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 15px;
}

.filter-box input,
.filter-box select {
    max-width: 250px;
}

.badge-status {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

.badge-pending {
    background: #fff3cd;
    color: #856404;
}

.badge-approved,
.badge-paid {
    background: #d4edda;
    color: #155724;
}

.badge-rejected,
.badge-cancelled,
.badge-failed {
    background: #f8d7da;
    color: #721c24;
}

.badge-default {
    background: #d1ecf1;
    color: #0c5460;
}

.payment-shot {
    width: 65px;
    height: 50px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid #ddd;
}

@media(max-width: 768px) {
    .hostel-grid {
        grid-template-columns: repeat(2,1fr);
    }

    .filter-box input,
    .filter-box select {
        max-width: 100%;
        width: 100%;
    }
}

@media(max-width: 480px) {
    .hostel-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<h2 class="page-title">Hostel Management</h2>

<div class="hostel-grid">
    <div class="stat-card"><h4><?= $total_hostels ?></h4><p>Total Hostels</p></div>
    <div class="stat-card"><h4><?= $total_bookings ?></h4><p>Total Bookings</p></div>
    <div class="stat-card"><h4><?= $pending_bookings ?></h4><p>Pending Bookings</p></div>
    <div class="stat-card"><h4><?= $approved_bookings ?></h4><p>Approved Bookings</p></div>
</div>

<?php if (is_super_admin_role() || !empty($edit)): ?>
<div class="card-dark mb-4">
    <h4><?= !empty($edit) ? 'Edit Hostel' : 'Add Hostel' ?></h4>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="save_hostel" value="1">
        <input type="hidden" name="id" value="<?= e($edit['id'] ?? '') ?>">
        <input type="hidden" name="old_photo" value="<?= e($edit['photo'] ?? '') ?>">
        <input type="hidden" name="old_hostel_image" value="<?= e($edit['hostel_image'] ?? '') ?>">

        <div class="row">
            <div class="col-md-4 mb-2">
                <label>Hostel Name</label>
                <input type="text" name="hostel_name" class="form-control" value="<?= e($edit['hostel_name'] ?? '') ?>" required>
            </div>

            <div class="col-md-4 mb-2">
                <label>Manager / Committee Member</label>
                <input type="text" name="manager_name" class="form-control" value="<?= e($edit['manager_name'] ?? '') ?>">
            </div>

            <div class="col-md-4 mb-2">
                <label>Manager Mobile</label>
                <input type="text" name="manager_mobile" class="form-control" value="<?= e($edit['manager_mobile'] ?? '') ?>">
            </div>

            <div class="col-md-4 mb-2">
                <label>Price Per Day</label>
                <input type="number" step="0.01" name="price_per_day" class="form-control" value="<?= e($edit['price_per_day'] ?? '') ?>">
            </div>

            <div class="col-md-4 mb-2">
                <label>Total Rooms</label>
                <input type="number" name="total_rooms" class="form-control" value="<?= e($edit['total_rooms'] ?? '') ?>">
            </div>

            <div class="col-md-4 mb-2">
                <label>Available Rooms</label>
                <input type="number" name="available_rooms" class="form-control" value="<?= e($edit['available_rooms'] ?? '') ?>">
            </div>

            <div class="col-md-4 mb-2">
                <label>City</label>
                <input type="text" name="city" class="form-control" value="<?= e($edit['city'] ?? '') ?>">
            </div>

            <div class="col-md-4 mb-2">
                <label>District</label>
                <input type="text" name="district" class="form-control" value="<?= e($edit['district'] ?? '') ?>">
            </div>

            <div class="col-md-4 mb-2">
                <label>State</label>
                <input type="text" name="state" class="form-control" value="<?= e($edit['state'] ?? 'Rajasthan') ?>">
            </div>

            <div class="col-md-4 mb-2">
                <label>Facilities</label>
                <input type="text" name="facilities" class="form-control" placeholder="Wi-Fi, Food, Parking" value="<?= e($edit['facilities'] ?? '') ?>">
            </div>

            <div class="col-md-4 mb-2">
                <label>Rating</label>
                <input type="number" step="0.1" min="0" max="5" name="rating" class="form-control" value="<?= e($edit['rating'] ?? '4.5') ?>">
            </div>

            <div class="col-md-4 mb-2">
                <label>Status</label>
                <select name="status" class="form-select">
                    <option value="active" <?= (($edit['status'] ?? '') == 'active') ? 'selected' : '' ?>>active</option>
                    <option value="inactive" <?= (($edit['status'] ?? '') == 'inactive') ? 'selected' : '' ?>>inactive</option>
                </select>
            </div>

            <div class="col-md-6 mb-2">
                <label>Room / Hostel Photo</label>
                <input type="file" name="photo" class="form-control" accept="image/*">

                <?php
                $current_photo = $edit['photo'] ?? '';
                if ($current_photo == '' && !empty($edit['hostel_image'])) {
                    $current_photo = 'uploads/hostels/' . basename($edit['hostel_image']);
                }
                ?>

                <?php if (!empty($current_photo)): ?>
                    <small>Current: <?= e($current_photo) ?></small><br>
                    <img class="img-thumb mt-1" src="../<?= e($current_photo) ?>">
                <?php endif; ?>
            </div>

            <div class="col-md-12 mb-2">
                <label>Address</label>
                <textarea name="address" class="form-control" rows="2"><?= e($edit['address'] ?? '') ?></textarea>
            </div>

            <div class="col-md-12 mb-2">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="3"><?= e($edit['description'] ?? '') ?></textarea>
            </div>

            <div class="col-md-12 mb-2">
                <label>Rules</label>
                <textarea name="rules" class="form-control" rows="3"><?= e($edit['rules'] ?? '') ?></textarea>
            </div>
        </div>

        <button class="btn btn-gold mt-2">Save Hostel</button>
        <a href="hostel-booking.php" class="btn btn-secondary mt-2">Clear</a>
    </form>
</div>
<?php elseif (is_hostel_manager_role()): ?>
<div class="card-dark mb-4">
    <h4>Hostel Detail</h4>
    <p>Aap sirf apne allotted hostel ki details aur bookings dekh sakte hain. Edit ke liye Hostel List me Edit button use karein.</p>
</div>
<?php endif; ?>

<div class="card-dark mb-4">
    <h4>Hostel List</h4>

    <form method="get" class="filter-box">
        <input type="text" name="hostel_search" class="form-control" placeholder="Hostel / Manager / City" value="<?= e($hostel_search) ?>">
        <button class="btn btn-primary">Search</button>
        <a href="hostel-booking.php" class="btn btn-secondary">Reset</a>
    </form>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Photo</th>
                    <th>Hostel</th>
                    <th>Manager</th>
                    <th>Mobile</th>
                    <th>Price</th>
                    <th>Rooms</th>
                    <th>Available</th>
                    <th>City</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
            <?php
            if (table_exists($conn, $hostel_table)) {
                $res = $conn->query("SELECT * FROM `$hostel_table` $hostel_where ORDER BY id DESC LIMIT 100");

                if ($res && $res->num_rows > 0) {
                    while ($r = $res->fetch_assoc()) {
                        $photo = $r['photo'] ?? '';

                        if ($photo == '' && !empty($r['hostel_image'])) {
                            $photo = 'uploads/hostels/' . basename($r['hostel_image']);
                        }
            ?>
                <tr>
                    <td><?= e($r['id']) ?></td>

                    <td>
                        <?php if (!empty($photo)): ?>
                            <img class="img-thumb" src="../<?= e($photo) ?>">
                        <?php endif; ?>
                    </td>

                    <td>
                        <b><?= e($r['hostel_name']) ?></b><br>
                        <small><?= e($r['address'] ?? '') ?></small>
                    </td>

                    <td><?= e($r['manager_name']) ?></td>
                    <td><?= e($r['manager_mobile']) ?></td>
                    <td>₹<?= e($r['price_per_day']) ?></td>
                    <td><?= e($r['total_rooms']) ?></td>
                    <td><?= e($r['available_rooms']) ?></td>
                    <td><?= e($r['city']) ?></td>
                    <td><?= e($r['status']) ?></td>

                    <td>
                        <a class="btn btn-sm btn-warning" href="?edit=<?= (int)$r['id'] ?>">Edit</a>
                        <?php if (is_super_admin_role()): ?>
                            <a class="btn btn-sm btn-danger" onclick="return confirm('Delete hostel?')" href="?delete=<?= (int)$r['id'] ?>">Delete</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php
                    }
                } else {
                    echo '<tr><td colspan="11">No hostel found</td></tr>';
                }
            }
            ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card-dark">
    <h4>Booking Requests</h4>

    <form method="get" class="filter-box">
        <input type="text" name="booking_search" class="form-control" placeholder="Name / Mobile / Email / Transaction ID" value="<?= e($booking_search) ?>">

        <select name="booking_status" class="form-select">
            <option value="">All Booking Status</option>
            <?php foreach (['pending','approved','rejected','cancelled'] as $st): ?>
                <option value="<?= $st ?>" <?= ($booking_status == $st) ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="payment_status" class="form-select">
            <option value="">All Payment Status</option>
            <?php foreach (['pending','paid','failed'] as $pst): ?>
                <option value="<?= $pst ?>" <?= ($payment_status == $pst) ? 'selected' : '' ?>><?= ucfirst($pst) ?></option>
            <?php endforeach; ?>
        </select>

        <button class="btn btn-primary">Search</button>
        <a href="hostel-booking.php" class="btn btn-secondary">Reset</a>
    </form>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Hostel</th>
                    <th>Guest</th>
                    <th>Contact</th>
                    <th>City</th>
                    <th>Check-in</th>
                    <th>Check-out</th>
                    <th>Persons</th>
                    <th>Rooms</th>
                    <th>Nights</th>
                    <th>Amount</th>
                    <th>Payment</th>
                    <th>Txn ID</th>
                    <th>Screenshot</th>
                    <th>Booking Status</th>
                    <th>Created</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
            <?php
            if (table_exists($conn, $booking_table)) {
                $join_sql = "";

                if (table_exists($conn, $hostel_table) && h_column_exists($conn, $booking_table, 'hostel_id')) {
                    $join_sql = "LEFT JOIN `$hostel_table` h ON b.hostel_id=h.id";
                }

                $sql = "
                    SELECT b.*, " . ($join_sql ? "h.hostel_name AS joined_hostel_name" : "NULL AS joined_hostel_name") . "
                    FROM `$booking_table` b
                    $join_sql
                    $booking_where
                    ORDER BY b.id DESC
                    LIMIT 200
                ";

                $res = $conn->query($sql);

                if (!$res) {
                    echo '<tr><td colspan="17">Query Error: ' . e($conn->error) . '</td></tr>';
                } elseif ($res->num_rows > 0) {
                    while ($b = $res->fetch_assoc()) {
                        $name = booking_value($b, 'name', 'guest_name');
                        $check_in = booking_value($b, 'check_in', 'checkin_date');
                        $check_out = booking_value($b, 'check_out', 'checkout_date');
                        $persons = booking_value($b, 'total_persons', 'total_person');
                        $rooms = booking_value($b, 'number_of_rooms');
                        $nights = booking_value($b, 'nights');
                        $amount = booking_value($b, 'total_amount');
                        $message = booking_value($b, 'message', 'purpose');
                        $hostel_name = booking_value($b, 'hostel_name');
                        $booking_st = booking_value($b, 'booking_status');
                        $payment_st = booking_value($b, 'payment_status');
                        $payment_method = booking_value($b, 'payment_method');
                        $txn_id = booking_value($b, 'transaction_id');
                        $screenshot = booking_value($b, 'payment_screenshot');

                        if ($hostel_name == '') {
                            $hostel_name = $b['joined_hostel_name'] ?? '';
                        }

                        if ($rooms == '') {
                            $rooms = 1;
                        }

                        if ($nights == '') {
                            $nights = 1;
                        }

                        if ($booking_st == '') {
                            $booking_st = 'pending';
                        }

                        if ($payment_st == '') {
                            $payment_st = 'pending';
                        }

                        /*
                           Agar booking approved hai to display me payment paid dikhayenge.
                           Naye approve action me DB me bhi paid update ho jayega.
                        */
                        if ($booking_st == 'approved' && $payment_st == 'pending') {
                            $payment_st = 'paid';
                        }

                        $booking_badge = in_array($booking_st, ['pending','approved','rejected','cancelled']) ? 'badge-' . $booking_st : 'badge-default';
                        $payment_badge = in_array($payment_st, ['pending','paid','failed']) ? 'badge-' . $payment_st : 'badge-default';
            ?>
                <tr>
                    <td><?= e($b['id']) ?></td>

                    <td>
                        <b><?= e($hostel_name ?: '-') ?></b><br>
                        <small><?= e($b['room_type'] ?? '') ?></small>
                    </td>

                    <td>
                        <b><?= e($name ?: '-') ?></b><br>
                        <small><?= e($b['email'] ?? '') ?></small>
                    </td>

                    <td><?= e($b['mobile'] ?? '-') ?></td>
                    <td><?= e($b['city'] ?? '-') ?></td>

                    <td>
                        <?= !empty($check_in) ? e(date('d M Y', strtotime($check_in))) : '-' ?><br>
                        <small><?= e($b['check_in_time'] ?? '') ?></small>
                    </td>

                    <td>
                        <?= !empty($check_out) ? e(date('d M Y', strtotime($check_out))) : '-' ?><br>
                        <small><?= e($b['check_out_time'] ?? '') ?></small>
                    </td>

                    <td><?= e($persons ?: '-') ?></td>
                    <td><?= e($rooms ?: '-') ?></td>
                    <td><?= e($nights ?: '-') ?></td>
                    <td>₹<?= number_format((float)$amount) ?></td>

                    <td>
                        <span class="badge-status <?= e($payment_badge) ?>"><?= e($payment_st) ?></span><br>
                        <small><?= e($payment_method) ?></small>
                    </td>

                    <td><?= e($txn_id ?: '-') ?></td>

                    <td>
                        <?php if (!empty($screenshot)): ?>
                            <a href="../uploads/payments/<?= e($screenshot) ?>" target="_blank">
                                <img class="payment-shot" src="../uploads/payments/<?= e($screenshot) ?>">
                            </a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>

                    <td>
                        <span class="badge-status <?= e($booking_badge) ?>"><?= e($booking_st) ?></span>
                        <?php if (!empty($message)): ?>
                            <br><small><?= e($message) ?></small>
                        <?php endif; ?>
                    </td>

                    <td>
                        <?= !empty($b['created_at']) ? e(date('d M Y h:i A', strtotime($b['created_at']))) : '-' ?>
                    </td>

                    <td style="min-width:260px">
                        <a class="btn btn-sm btn-success" href="?booking_action=approve&booking_id=<?= (int)$b['id'] ?>">Approve</a>
                        <a class="btn btn-sm btn-info" href="?booking_action=mark_paid&booking_id=<?= (int)$b['id'] ?>">Paid</a>
                        <a class="btn btn-sm btn-warning" href="?booking_action=reject&booking_id=<?= (int)$b['id'] ?>">Reject</a>
                        <a class="btn btn-sm btn-secondary" href="?booking_action=cancel&booking_id=<?= (int)$b['id'] ?>">Cancel</a>
                        <a class="btn btn-sm btn-danger" onclick="return confirm('Delete booking?')" href="?booking_action=delete&booking_id=<?= (int)$b['id'] ?>">Delete</a>
                    </td>
                </tr>
            <?php
                    }
                } else {
                    echo '<tr><td colspan="17">No booking found</td></tr>';
                }
            }
            ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
