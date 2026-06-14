<?php
/*
|--------------------------------------------------------------------------
| File: admin/testimonial.php
|--------------------------------------------------------------------------
| Features:
| - Agar testimonials table nahi hai to auto create karega
| - Add / Edit / Delete testimonial
| - Search / Filter
| - Village Surveyor sirf allotted gaon ka testimonial dekhega
| - Village Surveyor: Pending / Village Approve / Reject kar sakta hai
| - Super Admin: Admin Final Approve / Reject / Pending kar sakta hai
| - Front website par sirf village_status='approved' AND admin_status='approved' records show karna
|--------------------------------------------------------------------------
*/

include 'auth.php';
include 'surveyor-permission.php';

if (function_exists('requireRole')) {
    requireRole(['super_admin', 'village_surveyor']);
}

/*
    Section permission agar use karna hai to DB me permission add kare:
    INSERT INTO user_section_permissions (admin_id, section_name) VALUES (5, 'testimonial');

    Agar permission entry nahi hai aur page block ho raha hai, neeche wali line comment kar dena.
*/
if (function_exists('requireSection')) {
    // requireSection($conn, 'testimonial');
}

include 'includes/header.php';
include 'includes/sidebar.php';

$table = 'testimonials';
$upload_dir = '../uploads/testimonials/';
$upload_db_dir = 'uploads/testimonials/';

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

if (!function_exists('t_table_exists')) {
    function t_table_exists($conn, $table) {
        $table = $conn->real_escape_string($table);
        $res = $conn->query("SHOW TABLES LIKE '$table'");
        return ($res && $res->num_rows > 0);
    }
}

if (!function_exists('t_column_exists')) {
    function t_column_exists($conn, $table, $column) {
        if (!t_table_exists($conn, $table)) {
            return false;
        }

        $table = $conn->real_escape_string($table);
        $column = $conn->real_escape_string($column);

        $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return ($res && $res->num_rows > 0);
    }
}

if (!function_exists('t_add_column_if_missing')) {
    function t_add_column_if_missing($conn, $table, $column, $definition) {
        if (!t_column_exists($conn, $table, $column)) {
            $conn->query("ALTER TABLE `$table` ADD `$column` $definition");
        }
    }
}

if (!function_exists('t_role')) {
    function t_role() {
        return $_SESSION['admin_role'] ?? ($_SESSION['role'] ?? '');
    }
}

if (!function_exists('t_is_super_admin')) {
    function t_is_super_admin() {
        return t_role() === 'super_admin';
    }
}

if (!function_exists('t_is_village_surveyor')) {
    function t_is_village_surveyor() {
        return t_role() === 'village_surveyor';
    }
}

if (!function_exists('t_safe_status')) {
    function t_safe_status($status) {
        return in_array($status, ['pending', 'approved', 'rejected'], true) ? $status : '';
    }
}

if (!function_exists('safe_upload_testimonial_photo')) {
    function safe_upload_testimonial_photo($file, $upload_dir, $upload_db_dir) {
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return '';
        }

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed, true)) {
            return '';
        }

        if ($file['size'] > 2 * 1024 * 1024) {
            return '';
        }

        $new_name = 'testimonial_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $target = $upload_dir . $new_name;

        if (move_uploaded_file($file['tmp_name'], $target)) {
            return $upload_db_dir . $new_name;
        }

        return '';
    }
}

/*
|--------------------------------------------------------------------------
| Create table if not exists
|--------------------------------------------------------------------------
*/
$conn->query("
    CREATE TABLE IF NOT EXISTS `$table` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(150) NOT NULL,
        `mobile` VARCHAR(30) NULL,
        `email` VARCHAR(150) NULL,
        `village` VARCHAR(150) NULL,
        `district` VARCHAR(150) NULL,
        `message` TEXT NOT NULL,
        `rating` INT DEFAULT 5,
        `photo` VARCHAR(255) NULL,
        `village_status` ENUM('pending','approved','rejected') DEFAULT 'pending',
        `admin_status` ENUM('pending','approved','rejected') DEFAULT 'pending',
        `status` ENUM('active','inactive') DEFAULT 'inactive',
        `village_approved_by` INT NULL,
        `admin_approved_by` INT NULL,
        `village_approved_at` DATETIME NULL,
        `admin_approved_at` DATETIME NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

/*
|--------------------------------------------------------------------------
| Repair old table columns if table already exists
|--------------------------------------------------------------------------
*/
t_add_column_if_missing($conn, $table, 'name', "VARCHAR(150) NULL");
t_add_column_if_missing($conn, $table, 'mobile', "VARCHAR(30) NULL");
t_add_column_if_missing($conn, $table, 'email', "VARCHAR(150) NULL");
t_add_column_if_missing($conn, $table, 'village', "VARCHAR(150) NULL");
t_add_column_if_missing($conn, $table, 'district', "VARCHAR(150) NULL");
t_add_column_if_missing($conn, $table, 'message', "TEXT NULL");
t_add_column_if_missing($conn, $table, 'rating', "INT DEFAULT 5");
t_add_column_if_missing($conn, $table, 'photo', "VARCHAR(255) NULL");
t_add_column_if_missing($conn, $table, 'village_status', "ENUM('pending','approved','rejected') DEFAULT 'pending'");
t_add_column_if_missing($conn, $table, 'admin_status', "ENUM('pending','approved','rejected') DEFAULT 'pending'");
t_add_column_if_missing($conn, $table, 'status', "ENUM('active','inactive') DEFAULT 'inactive'");
t_add_column_if_missing($conn, $table, 'village_approved_by', "INT NULL");
t_add_column_if_missing($conn, $table, 'admin_approved_by', "INT NULL");
t_add_column_if_missing($conn, $table, 'village_approved_at', "DATETIME NULL");
t_add_column_if_missing($conn, $table, 'admin_approved_at', "DATETIME NULL");
t_add_column_if_missing($conn, $table, 'updated_at', "DATETIME NULL");

/*
|--------------------------------------------------------------------------
| Testimonial access check
|--------------------------------------------------------------------------
*/
if (!function_exists('surveyorCanAccessTestimonial')) {
    function surveyorCanAccessTestimonial($conn, $testimonial_id) {
        global $table;

        $testimonial_id = (int)$testimonial_id;

        if ($testimonial_id <= 0) {
            return false;
        }

        if (t_is_super_admin()) {
            return true;
        }

        if (!t_is_village_surveyor()) {
            return false;
        }

        if (!function_exists('getSurveyorVillages')) {
            return false;
        }

        $admin_id = function_exists('sp_admin_id') ? sp_admin_id() : (int)($_SESSION['admin_id'] ?? 0);
        $allowed_villages = getSurveyorVillages($conn, $admin_id);

        if (empty($allowed_villages)) {
            return false;
        }

        $q = $conn->query("SELECT village FROM `$table` WHERE id='$testimonial_id' LIMIT 1");

        if (!$q || $q->num_rows == 0) {
            return false;
        }

        $row = $q->fetch_assoc();
        $village = trim($row['village'] ?? '');

        return in_array($village, $allowed_villages, true);
    }
}

if (!function_exists('testimonialVillageWhere')) {
    function testimonialVillageWhere($conn, $alias = '') {
        if (!t_is_village_surveyor()) {
            return '';
        }

        if (!function_exists('getSurveyorVillages')) {
            return " AND 0 ";
        }

        $admin_id = function_exists('sp_admin_id') ? sp_admin_id() : (int)($_SESSION['admin_id'] ?? 0);
        $allowed_villages = getSurveyorVillages($conn, $admin_id);

        if (empty($allowed_villages)) {
            return " AND 0 ";
        }

        $escaped = [];
        foreach ($allowed_villages as $v) {
            $escaped[] = "'" . $conn->real_escape_string($v) . "'";
        }

        $prefix = '';
        if ($alias !== '') {
            $prefix = "`" . str_replace('`', '', $alias) . "`.";
        }

        return " AND {$prefix}`village` IN (" . implode(',', $escaped) . ") ";
    }
}

/*
|--------------------------------------------------------------------------
| Status actions
|--------------------------------------------------------------------------
*/
if (isset($_GET['action'], $_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];

    if (!surveyorCanAccessTestimonial($conn, $id)) {
        die("Access Denied: Aapko is gaon ka testimonial access nahi hai.");
    }

    $admin_id = function_exists('sp_admin_id') ? sp_admin_id() : (int)($_SESSION['admin_id'] ?? 0);

    if ($action === 'village_pending' && t_is_village_surveyor()) {
        $conn->query("UPDATE `$table` SET village_status='pending', admin_status='pending', status='inactive', updated_at=NOW() WHERE id=$id");
    }

    if ($action === 'village_approve' && t_is_village_surveyor()) {
        $conn->query("UPDATE `$table` SET village_status='approved', admin_status='pending', status='inactive', village_approved_by='$admin_id', village_approved_at=NOW(), updated_at=NOW() WHERE id=$id");
    }

    if ($action === 'village_reject' && t_is_village_surveyor()) {
        $conn->query("UPDATE `$table` SET village_status='rejected', admin_status='rejected', status='inactive', village_approved_by='$admin_id', village_approved_at=NOW(), updated_at=NOW() WHERE id=$id");
    }

    if ($action === 'admin_pending' && t_is_super_admin()) {
        $conn->query("UPDATE `$table` SET admin_status='pending', status='inactive', updated_at=NOW() WHERE id=$id");
    }

    if ($action === 'admin_approve' && t_is_super_admin()) {
        $conn->query("UPDATE `$table` SET village_status='approved', admin_status='approved', status='active', admin_approved_by='$admin_id', admin_approved_at=NOW(), updated_at=NOW() WHERE id=$id");
    }

    if ($action === 'admin_reject' && t_is_super_admin()) {
        $conn->query("UPDATE `$table` SET admin_status='rejected', status='inactive', admin_approved_by='$admin_id', admin_approved_at=NOW(), updated_at=NOW() WHERE id=$id");
    }

    redirect('testimonial.php');
}

/*
|--------------------------------------------------------------------------
| Delete
|--------------------------------------------------------------------------
*/
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    if (!surveyorCanAccessTestimonial($conn, $id)) {
        die("Access Denied: Aapko is gaon ka testimonial delete access nahi hai.");
    }

    $conn->query("DELETE FROM `$table` WHERE id=$id");
    redirect('testimonial.php');
}

/*
|--------------------------------------------------------------------------
| Edit load
|--------------------------------------------------------------------------
*/
$edit = [];
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];

    if (!surveyorCanAccessTestimonial($conn, $id)) {
        die("Access Denied: Aapko is gaon ka testimonial edit access nahi hai.");
    }

    $res = $conn->query("SELECT * FROM `$table` WHERE id=$id LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $edit = $res->fetch_assoc();
    }
}

/*
|--------------------------------------------------------------------------
| Save
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_testimonial'])) {
    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0 && !surveyorCanAccessTestimonial($conn, $id)) {
        die("Access Denied: Aapko is gaon ka testimonial edit access nahi hai.");
    }

    $name = trim($_POST['name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $village = trim($_POST['village'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $rating = (int)($_POST['rating'] ?? 5);

    if ($rating < 1) {
        $rating = 1;
    }
    if ($rating > 5) {
        $rating = 5;
    }

    if ($name === '' || $message === '') {
        echo "<script>alert('Name aur Message required hai'); window.history.back();</script>";
        exit;
    }

    if (t_is_village_surveyor()) {
        $admin_id = function_exists('sp_admin_id') ? sp_admin_id() : (int)($_SESSION['admin_id'] ?? 0);
        $allowed_villages = getSurveyorVillages($conn, $admin_id);

        if (empty($allowed_villages)) {
            die("Access Denied: Aapko koi village allot nahi hai.");
        }

        if ($village === '') {
            $village = $allowed_villages[0];
        }

        if (!in_array($village, $allowed_villages, true)) {
            die("Access Denied: Aap sirf allotted gaon ka testimonial add/edit kar sakte hain.");
        }
    }

    $photo = $_POST['old_photo'] ?? '';
    $uploaded = safe_upload_testimonial_photo($_FILES['photo'] ?? null, $upload_dir, $upload_db_dir);

    if ($uploaded !== '') {
        $photo = $uploaded;
    }

    $name_sql = $conn->real_escape_string($name);
    $mobile_sql = $conn->real_escape_string($mobile);
    $email_sql = $conn->real_escape_string($email);
    $village_sql = $conn->real_escape_string($village);
    $district_sql = $conn->real_escape_string($district);
    $message_sql = $conn->real_escape_string($message);
    $photo_sql = $conn->real_escape_string($photo);

    if ($id > 0) {
        $conn->query("
            UPDATE `$table`
            SET name='$name_sql',
                mobile='$mobile_sql',
                email='$email_sql',
                village='$village_sql',
                district='$district_sql',
                message='$message_sql',
                rating='$rating',
                photo='$photo_sql',
                updated_at=NOW()
            WHERE id=$id
        ");
    } else {
        $conn->query("
            INSERT INTO `$table`
            (name, mobile, email, village, district, message, rating, photo, village_status, admin_status, status)
            VALUES
            ('$name_sql', '$mobile_sql', '$email_sql', '$village_sql', '$district_sql', '$message_sql', '$rating', '$photo_sql', 'pending', 'pending', 'inactive')
        ");
    }

    redirect('testimonial.php');
}

/*
|--------------------------------------------------------------------------
| Search / Filter
|--------------------------------------------------------------------------
*/
$search = $_GET['search'] ?? '';
$village_status_filter = t_safe_status($_GET['village_status'] ?? '');
$admin_status_filter = t_safe_status($_GET['admin_status'] ?? '');

$where = "WHERE 1";
$where .= testimonialVillageWhere($conn);

if ($search !== '') {
    $s = $conn->real_escape_string($search);
    $where .= " AND (
        name LIKE '%$s%'
        OR mobile LIKE '%$s%'
        OR email LIKE '%$s%'
        OR village LIKE '%$s%'
        OR district LIKE '%$s%'
        OR message LIKE '%$s%'
    )";
}

if ($village_status_filter !== '') {
    $vs = $conn->real_escape_string($village_status_filter);
    $where .= " AND village_status='$vs'";
}

if ($admin_status_filter !== '') {
    $as = $conn->real_escape_string($admin_status_filter);
    $where .= " AND admin_status='$as'";
}

$list_res = $conn->query("SELECT * FROM `$table` $where ORDER BY id DESC LIMIT 300");

/*
|--------------------------------------------------------------------------
| Stats
|--------------------------------------------------------------------------
*/
$stats_where = "WHERE 1";
$stats_where .= testimonialVillageWhere($conn);

$total = $pending = $village_approved = $admin_approved = $rejected = 0;

$q = $conn->query("SELECT COUNT(*) c FROM `$table` $stats_where");
$total = $q ? (int)$q->fetch_assoc()['c'] : 0;

$q = $conn->query("SELECT COUNT(*) c FROM `$table` $stats_where AND village_status='pending'");
$pending = $q ? (int)$q->fetch_assoc()['c'] : 0;

$q = $conn->query("SELECT COUNT(*) c FROM `$table` $stats_where AND village_status='approved'");
$village_approved = $q ? (int)$q->fetch_assoc()['c'] : 0;

$q = $conn->query("SELECT COUNT(*) c FROM `$table` $stats_where AND admin_status='approved'");
$admin_approved = $q ? (int)$q->fetch_assoc()['c'] : 0;

$q = $conn->query("SELECT COUNT(*) c FROM `$table` $stats_where AND (village_status='rejected' OR admin_status='rejected')");
$rejected = $q ? (int)$q->fetch_assoc()['c'] : 0;
?>

<style>
.testimonial-grid{
    display:grid;
    grid-template-columns:repeat(5,1fr);
    gap:14px;
    margin-bottom:18px;
}
.testimonial-card{
    background:linear-gradient(135deg,#a33b25,#5929c8);
    border:1px solid rgba(255,255,255,.18);
    border-radius:18px;
    padding:18px;
    color:#fff;
    box-shadow:0 12px 28px rgba(0,0,0,.24);
}
.testimonial-card span{
    display:block;
    color:#ffd36a;
    font-weight:800;
    margin-bottom:8px;
}
.testimonial-card b{
    font-size:30px;
}
.testimonial-thumb{
    width:58px;
    height:58px;
    object-fit:cover;
    border-radius:50%;
    border:1px solid rgba(255,211,106,.5);
    background:#180404;
}
.testimonial-action-box{
    display:flex;
    flex-wrap:wrap;
    gap:6px;
    min-width:260px;
}
.badge-status{
    display:inline-block;
    padding:5px 9px;
    border-radius:20px;
    font-size:12px;
    font-weight:700;
}
.badge-pending{background:#fff3cd;color:#856404;}
.badge-approved{background:#d4edda;color:#155724;}
.badge-rejected{background:#f8d7da;color:#721c24;}
@media(max-width:1000px){
    .testimonial-grid{grid-template-columns:repeat(2,1fr);}
}
@media(max-width:600px){
    .testimonial-grid{grid-template-columns:1fr;}
}
</style>

<h2 class="page-title">Testimonial Management</h2>

<div class="testimonial-grid">
    <div class="testimonial-card"><span>Total</span><b><?= $total ?></b></div>
    <div class="testimonial-card"><span>Pending</span><b><?= $pending ?></b></div>
    <div class="testimonial-card"><span>Village Approved</span><b><?= $village_approved ?></b></div>
    <div class="testimonial-card"><span>Admin Approved</span><b><?= $admin_approved ?></b></div>
    <div class="testimonial-card"><span>Rejected</span><b><?= $rejected ?></b></div>
</div>

<div class="card-dark mb-4">
    <h4><?= !empty($edit) ? 'Edit Testimonial' : 'Add Testimonial' ?></h4>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="save_testimonial" value="1">
        <input type="hidden" name="id" value="<?= e($edit['id'] ?? '') ?>">
        <input type="hidden" name="old_photo" value="<?= e($edit['photo'] ?? '') ?>">

        <div class="row">
            <div class="col-md-4 mb-2">
                <label>Name</label>
                <input type="text" name="name" class="form-control" value="<?= e($edit['name'] ?? '') ?>" required>
            </div>

            <div class="col-md-4 mb-2">
                <label>Mobile</label>
                <input type="text" name="mobile" class="form-control" value="<?= e($edit['mobile'] ?? '') ?>">
            </div>

            <div class="col-md-4 mb-2">
                <label>Email</label>
                <input type="email" name="email" class="form-control" value="<?= e($edit['email'] ?? '') ?>">
            </div>

            <div class="col-md-4 mb-2">
                <label>Village</label>
                <input type="text" name="village" class="form-control" value="<?= e($edit['village'] ?? '') ?>" required>
            </div>

            <div class="col-md-4 mb-2">
                <label>District</label>
                <input type="text" name="district" class="form-control" value="<?= e($edit['district'] ?? '') ?>">
            </div>

            <div class="col-md-4 mb-2">
                <label>Rating</label>
                <select name="rating" class="form-select">
                    <?php for ($i=5; $i>=1; $i--): ?>
                        <option value="<?= $i ?>" <?= ((int)($edit['rating'] ?? 5) === $i) ? 'selected' : '' ?>><?= $i ?> Star</option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="col-md-6 mb-2">
                <label>Photo</label>
                <input type="file" name="photo" class="form-control" accept="image/*">
                <?php if (!empty($edit['photo'])): ?>
                    <small>Current: <?= e($edit['photo']) ?></small><br>
                    <img src="../<?= e($edit['photo']) ?>" class="testimonial-thumb mt-1">
                <?php endif; ?>
            </div>

            <div class="col-md-12 mb-2">
                <label>Message</label>
                <textarea name="message" class="form-control" rows="4" required><?= e($edit['message'] ?? '') ?></textarea>
            </div>
        </div>

        <button class="btn btn-gold mt-2">Save Testimonial</button>
        <a href="testimonial.php" class="btn btn-secondary mt-2">Clear</a>
    </form>
</div>

<div class="card-dark mb-4">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-md-4">
            <label>Search</label>
            <input type="text" name="search" class="form-control" placeholder="Name / Mobile / Village" value="<?= e($search) ?>">
        </div>

        <div class="col-md-3">
            <label>Village Status</label>
            <select name="village_status" class="form-select">
                <option value="">All</option>
                <option value="pending" <?= $village_status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="approved" <?= $village_status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                <option value="rejected" <?= $village_status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>
        </div>

        <div class="col-md-3">
            <label>Admin Status</label>
            <select name="admin_status" class="form-select">
                <option value="">All</option>
                <option value="pending" <?= $admin_status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="approved" <?= $admin_status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                <option value="rejected" <?= $admin_status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>
        </div>

        <div class="col-md-2">
            <button class="btn btn-gold w-100">Search</button>
        </div>
    </form>
</div>

<div class="card-dark">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Photo</th>
                    <th>Name</th>
                    <th>Village</th>
                    <th>Message</th>
                    <th>Rating</th>
                    <th>Village Status</th>
                    <th>Admin Status</th>
                    <th>Show</th>
                    <th>Created</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
            <?php if ($list_res && $list_res->num_rows > 0): ?>
                <?php while ($r = $list_res->fetch_assoc()): ?>
                    <?php
                    $vst = $r['village_status'] ?? 'pending';
                    $ast = $r['admin_status'] ?? 'pending';
                    $show_status = (($vst === 'approved') && ($ast === 'approved') && (($r['status'] ?? '') === 'active')) ? 'Yes' : 'No';
                    ?>
                    <tr>
                        <td><?= e($r['id']) ?></td>

                        <td>
                            <?php if (!empty($r['photo'])): ?>
                                <img src="../<?= e($r['photo']) ?>" class="testimonial-thumb">
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>

                        <td>
                            <b><?= e($r['name']) ?></b><br>
                            <small><?= e($r['mobile'] ?? '') ?></small>
                        </td>

                        <td>
                            <?= e($r['village']) ?><br>
                            <small><?= e($r['district'] ?? '') ?></small>
                        </td>

                        <td><?= e(mb_strimwidth($r['message'] ?? '', 0, 90, '...')) ?></td>
                        <td><?= (int)($r['rating'] ?? 5) ?> ⭐</td>

                        <td>
                            <span class="badge-status badge-<?= e($vst) ?>"><?= e($vst) ?></span>
                        </td>

                        <td>
                            <span class="badge-status badge-<?= e($ast) ?>"><?= e($ast) ?></span>
                        </td>

                        <td><?= $show_status ?></td>

                        <td><?= !empty($r['created_at']) ? e(date('d M Y', strtotime($r['created_at']))) : '-' ?></td>

                        <td>
                            <div class="testimonial-action-box">
                                <?php if (t_is_village_surveyor()): ?>
                                    <a class="btn btn-sm btn-warning" href="?action=village_pending&id=<?= (int)$r['id'] ?>">Pending</a>
                                    <a class="btn btn-sm btn-success" href="?action=village_approve&id=<?= (int)$r['id'] ?>">Village Approve</a>
                                    <a class="btn btn-sm btn-dark" onclick="return confirm('Reject testimonial?')" href="?action=village_reject&id=<?= (int)$r['id'] ?>">Reject</a>
                                <?php endif; ?>

                                <?php if (t_is_super_admin()): ?>
                                    <a class="btn btn-sm btn-warning" href="?action=admin_pending&id=<?= (int)$r['id'] ?>">Admin Pending</a>
                                    <a class="btn btn-sm btn-primary" href="?action=admin_approve&id=<?= (int)$r['id'] ?>">Final Approve</a>
                                    <a class="btn btn-sm btn-dark" onclick="return confirm('Admin reject?')" href="?action=admin_reject&id=<?= (int)$r['id'] ?>">Admin Reject</a>
                                <?php endif; ?>

                                <a class="btn btn-sm btn-info" href="?edit=<?= (int)$r['id'] ?>">Edit</a>
                                <a class="btn btn-sm btn-danger" onclick="return confirm('Delete testimonial?')" href="?delete=<?= (int)$r['id'] ?>">Delete</a>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="11">No testimonial found</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
