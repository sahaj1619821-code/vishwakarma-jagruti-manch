<?php
include 'auth.php';
include_once 'surveyor-permission.php';

requireRole(['super_admin', 'matrimonial_admin', 'village_surveyor']);

if (function_exists('requireSection')) {
    requireSection($conn, 'matrimonial');
}

ob_start();
include 'includes/header.php';
include 'includes/sidebar.php';

$table = 'matrimonial_profiles'; // Admin se add hone wali profile ab matrimonial_profiles table me save hogi

// Profile photo folder project root me hai: VISHWAKARMA-JAGRUTI-MANCH/profile-photp/
$profile_photo_folder = 'profile-photp';
$profile_photo_fs_dir = realpath(__DIR__ . '/..') ? realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . $profile_photo_folder . DIRECTORY_SEPARATOR : __DIR__ . DIRECTORY_SEPARATOR . $profile_photo_folder . DIRECTORY_SEPARATOR;
$profile_photo_url_dir = '../' . $profile_photo_folder . '/';
$default_profile_img = '../images/default-profile.png';

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

function matri_add_column_if_missing($conn, $table, $column, $definition) {
    if (!matri_column_exists($conn, $table, $column)) {
        $conn->query("ALTER TABLE `$table` ADD `$column` $definition");
    }
}

function matri_column_exists($conn, $table, $column) {
    if (!function_exists('table_exists') || !table_exists($conn, $table)) {
        return false;
    }

    $table  = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($res && $res->num_rows > 0);
}

function matri_safe_status($status) {
    $allowed = ['pending', 'village_verified', 'admin_approved', 'rejected', 'correction_required'];
    return in_array($status, $allowed, true) ? $status : '';
}

function matri_profile_img_src($file_name, $url_dir, $default_img) {
    $file_name = trim($file_name ?? '');
    if ($file_name !== '') {
        return $url_dir . rawurlencode($file_name);
    }
    return $default_img;
}

function matri_admin_role() {
    return $_SESSION['admin_role'] ?? ($_SESSION['role'] ?? '');
}

function matri_admin_id() {
    return (int)($_SESSION['admin_id'] ?? ($_SESSION['id'] ?? 0));
}

function matri_is_super_admin() {
    return matri_admin_role() === 'super_admin';
}

function matri_is_village_surveyor() {
    return matri_admin_role() === 'village_surveyor';
}

function matri_village_columns($conn, $table) {
    $cols = [];
    foreach (['village_rajasthan', 'village', 'village_name'] as $col) {
        if (matri_column_exists($conn, $table, $col)) {
            $cols[] = $col;
        }
    }
    return $cols;
}

function matri_allowed_villages($conn) {
    if (!matri_is_village_surveyor()) {
        return [];
    }

    if (function_exists('sp_admin_id')) {
        $admin_id = (int)sp_admin_id();
    } else {
        $admin_id = matri_admin_id();
    }

    if ($admin_id <= 0) {
        return [];
    }

    if (function_exists('getSurveyorVillages')) {
        $villages = getSurveyorVillages($conn, $admin_id);
        if (is_array($villages)) {
            return array_values(array_filter(array_map('trim', $villages)));
        }
    }

    /*
        Fallback: agar surveyor-permission.php me getSurveyorVillages function nahi hai,
        to common permission table names se village/city nikalne ki koshish karega.
    */
    $villages = [];
    foreach (['admin_village_permissions', 'surveyor_permissions', 'admin_permissions'] as $perm_table) {
        if (!table_exists($conn, $perm_table)) {
            continue;
        }

        foreach (['village', 'village_name', 'city'] as $vcol) {
            if (!matri_column_exists($conn, $perm_table, $vcol)) {
                continue;
            }

            $q = $conn->query("SELECT `$vcol` AS village FROM `$perm_table` WHERE admin_id='$admin_id'");
            if ($q) {
                while ($r = $q->fetch_assoc()) {
                    $v = trim($r['village'] ?? '');
                    if ($v !== '') {
                        $villages[] = $v;
                    }
                }
            }
        }
    }

    return array_values(array_unique($villages));
}

function matri_village_where_sql($conn, $table, $alias = '') {
    if (!matri_is_village_surveyor()) {
        return '';
    }

    $allowed_villages = matri_allowed_villages($conn);
    if (empty($allowed_villages)) {
        return " AND 0";
    }

    $cols = matri_village_columns($conn, $table);
    if (empty($cols)) {
        return " AND 0";
    }

    $prefix = $alias !== '' ? "`$alias`." : '';
    $parts = [];

    foreach ($cols as $col) {
        $values = [];
        foreach ($allowed_villages as $village) {
            $values[] = "'" . $conn->real_escape_string($village) . "'";
        }

        if (!empty($values)) {
            $parts[] = $prefix . "`$col` IN (" . implode(',', $values) . ")";
        }
    }

    if (empty($parts)) {
        return " AND 0";
    }

    return " AND (" . implode(" OR ", $parts) . ")";
}

function matri_can_access_profile($conn, $table, $profile_id) {
    if (matri_is_super_admin() || matri_admin_role() === 'matrimonial_admin') {
        return true;
    }

    if (!matri_is_village_surveyor()) {
        return false;
    }

    $profile_id = (int)$profile_id;
    if ($profile_id <= 0) {
        return false;
    }

    $allowed_villages = matri_allowed_villages($conn);
    if (empty($allowed_villages)) {
        return false;
    }

    $cols = matri_village_columns($conn, $table);
    if (empty($cols)) {
        return false;
    }

    $select_cols = [];
    foreach ($cols as $col) {
        $select_cols[] = "`$col`";
    }

    $q = $conn->query("SELECT " . implode(',', $select_cols) . " FROM `$table` WHERE id='$profile_id' LIMIT 1");
    if (!$q || $q->num_rows === 0) {
        return false;
    }

    $row = $q->fetch_assoc();

    foreach ($cols as $col) {
        $profile_village = trim($row[$col] ?? '');
        if ($profile_village !== '' && in_array($profile_village, $allowed_villages, true)) {
            return true;
        }
    }

    return false;
}

function matri_post_village_allowed($conn, $table, $data) {
    if (!matri_is_village_surveyor()) {
        return true;
    }

    $allowed_villages = matri_allowed_villages($conn);
    if (empty($allowed_villages)) {
        return false;
    }

    $cols = matri_village_columns($conn, $table);
    foreach ($cols as $col) {
        if (!empty($data[$col]) && in_array(trim($data[$col]), $allowed_villages, true)) {
            return true;
        }
    }

    return false;
}

function matri_status_update_sql($conn, $table, $status_action) {
    $set = [];

    if ($status_action === 'approved') {
        $set[] = "verification_status='admin_approved'";

        if (matri_column_exists($conn, $table, 'status')) {
            $set[] = "status='approved'";
        }

        if (matri_column_exists($conn, $table, 'verified')) {
            $set[] = "verified='1'";
        }

        if (matri_column_exists($conn, $table, 'verified_at')) {
            $set[] = "verified_at=NOW()";
        }
    }

    if ($status_action === 'rejected') {
        $set[] = "verification_status='rejected'";

        if (matri_column_exists($conn, $table, 'status')) {
            $set[] = "status='pending'";
        }

        if (matri_column_exists($conn, $table, 'verified')) {
            $set[] = "verified='0'";
        }
    }

    if ($status_action === 'pending') {
        $set[] = "verification_status='pending'";

        if (matri_column_exists($conn, $table, 'status')) {
            $set[] = "status='pending'";
        }

        if (matri_column_exists($conn, $table, 'verified')) {
            $set[] = "verified='0'";
        }
    }

    if ($status_action === 'village_verified') {
        $set[] = "verification_status='village_verified'";

        if (matri_column_exists($conn, $table, 'verified')) {
            $set[] = "verified='1'";
        }

        if (matri_column_exists($conn, $table, 'verified_at')) {
            $set[] = "verified_at=NOW()";
        }
    }

    if ($status_action === 'correction_required') {
        $set[] = "verification_status='correction_required'";

        if (matri_column_exists($conn, $table, 'status')) {
            $set[] = "status='pending'";
        }

        if (matri_column_exists($conn, $table, 'verified')) {
            $set[] = "verified='0'";
        }
    }

    return implode(',', $set);
}

if (!table_exists($conn, $table)) {
    echo '<div class="alert alert-danger">Table <b>matrimonial_profiles</b> database में नहीं है। पहले matrimonial_profiles table बनाएं।</div>';
    include 'includes/footer.php';
    exit;
}

/*
    Required approval columns repair:
    Agar old table me verification_status / verified / status columns nahi hain,
    to admin approve system work nahi karega. Isliye yaha columns auto add honge.
*/
matri_add_column_if_missing($conn, $table, 'user_id', "INT NULL AFTER id");
matri_add_column_if_missing($conn, $table, 'verification_status', "ENUM('pending','village_verified','admin_approved','rejected','correction_required') DEFAULT 'pending'");
matri_add_column_if_missing($conn, $table, 'verified', "TINYINT(1) DEFAULT 0");
matri_add_column_if_missing($conn, $table, 'status', "ENUM('pending','approved','rejected') DEFAULT 'pending'");
matri_add_column_if_missing($conn, $table, 'verified_at', "DATETIME DEFAULT NULL");

// Existing old records jisme status blank ho unko safe default de do
$conn->query("UPDATE `$table` SET verification_status='pending' WHERE verification_status IS NULL OR verification_status=''");
$conn->query("UPDATE `$table` SET status='pending' WHERE status IS NULL OR status=''");

// Verification action: approved / rejected / pending / village verified / correction
if (isset($_GET['action'], $_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];

    $action_map = [
        'approve' => 'approved',
        'reject' => 'rejected',
        'pending' => 'pending',
        'village_verify' => 'village_verified',
        'correction' => 'correction_required'
    ];

    if ($id <= 0 || !isset($action_map[$action])) {
        die("Invalid action");
    }

    if (!matri_can_access_profile($conn, $table, $id)) {
        die("Access Denied: Aap sirf apne allotted gaon ke profile ka status change kar sakte hain.");
    }

    if (matri_column_exists($conn, $table, 'verification_status')) {
        $set_sql = matri_status_update_sql($conn, $table, $action_map[$action]);

        if ($set_sql !== '') {
            $conn->query("UPDATE `$table` SET $set_sql WHERE id=$id");
        }
    }

    redirect('matrimonial.php');
}

// Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    if ($id > 0) {
        if (!matri_can_access_profile($conn, $table, $id)) {
            die("Access Denied: Aap sirf apne allotted gaon ka profile delete kar sakte hain.");
        }

        $conn->query("DELETE FROM `$table` WHERE id=$id");
    }

    redirect('matrimonial.php');
}

$edit = [];
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];

    if (!matri_can_access_profile($conn, $table, $id)) {
        die("Access Denied: Aap sirf apne allotted gaon ka profile edit kar sakte hain.");
    }

    $res = $conn->query("SELECT * FROM `$table` WHERE id=$id LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $edit = $res->fetch_assoc();
    }
}

// Save / Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $allowed_cols = [
        'user_id', 'full_name', 'name', 'gender', 'mobile', 'phone', 'email', 'mobile_email', 'password', 'gotra',
        'state', 'district', 'tahsil', 'village_rajasthan', 'village', 'village_name',
        'current_address', 'address', 'marital_status', 'education', 'occupation',
        'dob', 'age', 'height', 'income', 'father_name', 'mother_name',
        'status', 'verified', 'verification_status', 'profile_photo', 'photo'
    ];

    $data = [];
    foreach ($allowed_cols as $col) {
        if (matri_column_exists($conn, $table, $col) && isset($_POST[$col])) {
            if ($col === 'password' && $_POST[$col] === '') {
                continue;
            }

            if ($col === 'verification_status') {
                $data[$col] = matri_safe_status($_POST[$col]);
            } else {
                $data[$col] = trim($_POST[$col]);
            }
        }
    }

    if (!matri_post_village_allowed($conn, $table, $data)) {
        die("Access Denied: Aap sirf apne allotted gaon ka matrimonial profile add/edit kar sakte hain.");
    }

    // matrimonial_profiles table me user_id required ho sakta hai.
    // Admin manually add kare to user_id blank hone par 0 save hoga.
    if (matri_column_exists($conn, $table, 'user_id') && empty($data['user_id'])) {
        $data['user_id'] = '0';
    }

    // अगर mobile_email खाली है तो email या mobile से बना दो
    if (matri_column_exists($conn, $table, 'mobile_email')) {
        if (empty($data['mobile_email'])) {
            if (!empty($data['email'])) {
                $data['mobile_email'] = $data['email'];
            } elseif (!empty($data['mobile'])) {
                $data['mobile_email'] = $data['mobile'];
            }
        }
    }

    // Verification status ke hisab se status/verified auto set
    if (isset($data['verification_status']) && $data['verification_status'] === 'admin_approved') {
        if (matri_column_exists($conn, $table, 'status')) {
            $data['status'] = 'approved';
        }
        if (matri_column_exists($conn, $table, 'verified')) {
            $data['verified'] = '1';
        }
    }

    if (isset($data['verification_status']) && in_array($data['verification_status'], ['pending', 'rejected', 'correction_required'], true)) {
        if (matri_column_exists($conn, $table, 'verified')) {
            $data['verified'] = '0';
        }
    }

    // Profile photo upload / update
    // matrimonial_users me column profile_photo ho sakta hai, matrimonial_profiles me photo column hai.
    $photo_col = '';
    if (matri_column_exists($conn, $table, 'profile_photo')) {
        $photo_col = 'profile_photo';
    } elseif (matri_column_exists($conn, $table, 'photo')) {
        $photo_col = 'photo';
    }

    if ($photo_col !== '' && !empty($_FILES['profile_photo']['name'])) {
        if (!is_dir($profile_photo_fs_dir)) {
            mkdir($profile_photo_fs_dir, 0777, true);
        }

        $original_name = basename($_FILES['profile_photo']['name']);
        $file_type = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $allowed_types = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($file_type, $allowed_types, true)) {
            $safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $original_name);
            $file_name = time() . '_' . $safe_name;
            $target_file = $profile_photo_fs_dir . $file_name;

            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target_file)) {
                $data[$photo_col] = $file_name;
            }
        }
    }

    $id = !empty($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($id > 0 && !matri_can_access_profile($conn, $table, $id)) {
        die("Access Denied: Aap sirf apne allotted gaon ka matrimonial profile update kar sakte hain.");
    }

    /*
        Duplicate Check
        Mobile / Email / Mobile Email already exist ho to popup aayega
    */
    $duplicateParts = [];
    $duplicateValues = [];

    if (matri_column_exists($conn, $table, 'mobile') && !empty($data['mobile'])) {
        $duplicateParts[] = "mobile = ?";
        $duplicateValues[] = $data['mobile'];
    }

    if (matri_column_exists($conn, $table, 'email') && !empty($data['email'])) {
        $duplicateParts[] = "email = ?";
        $duplicateValues[] = $data['email'];
    }

    if (matri_column_exists($conn, $table, 'mobile_email') && !empty($data['mobile_email'])) {
        $duplicateParts[] = "mobile_email = ?";
        $duplicateValues[] = $data['mobile_email'];
    }

    if (!empty($duplicateParts)) {
        $duplicateSql = "SELECT id FROM `$table` WHERE (" . implode(" OR ", $duplicateParts) . ")";

        // Edit time par same record ko ignore karega
        if ($id > 0) {
            $duplicateSql .= " AND id != ?";
            $duplicateValues[] = $id;
        }

        $duplicateSql .= " LIMIT 1";

        $checkStmt = $conn->prepare($duplicateSql);

        if ($checkStmt) {
            $types = str_repeat("s", count($duplicateValues));

            if ($id > 0) {
                // last value id hai, isliye type integer karna hai
                $types = str_repeat("s", count($duplicateValues) - 1) . "i";
            }

            $checkStmt->bind_param($types, ...$duplicateValues);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult && $checkResult->num_rows > 0) {
                echo "<script>
                    alert('Mobile number ya Email already exists');
                    window.history.back();
                </script>";
                exit;
            }
        }
    }

    try {
        if ($id > 0) {
            $set = [];

            foreach ($data as $k => $v) {
                $v = $conn->real_escape_string($v);
                $set[] = "`$k`='$v'";
            }

            if (!empty($set)) {
                $conn->query("UPDATE `$table` SET " . implode(',', $set) . " WHERE id=$id");
            }

            echo "<script>
                alert('Matrimonial profile updated successfully');
                window.location.href='matrimonial.php';
            </script>";
            exit;

        } else {
            if (!empty($data)) {
                $keys = array_keys($data);
                $vals = [];

                foreach ($data as $v) {
                    $vals[] = "'" . $conn->real_escape_string($v) . "'";
                }

                $conn->query("INSERT INTO `$table` (`" . implode('`,`', $keys) . "`) VALUES (" . implode(',', $vals) . ")");
            }

            echo "<script>
                alert('Matrimonial profile added successfully');
                window.location.href='matrimonial.php';
            </script>";
            exit;
        }

    } catch (mysqli_sql_exception $e) {
        if ($conn->errno == 1062) {
            echo "<script>
                alert('Mobile number ya Email already exists');
                window.history.back();
            </script>";
            exit;
        } else {
            die("Database Error: " . $e->getMessage());
        }
    }
}

// Search / Filter
$search = $_GET['search'] ?? '';
$status_filter = matri_safe_status($_GET['status_filter'] ?? '');
$where = "WHERE 1";
$where .= matri_village_where_sql($conn, $table);

if ($search !== '') {
    $s = $conn->real_escape_string($search);
    $searchParts = [];
    foreach (['full_name', 'name', 'mobile', 'phone', 'email', 'gotra', 'district', 'village_rajasthan', 'village', 'village_name'] as $col) {
        if (matri_column_exists($conn, $table, $col)) {
            $searchParts[] = "`$col` LIKE '%$s%'";
        }
    }
    if (!empty($searchParts)) {
        $where .= " AND (" . implode(' OR ', $searchParts) . ")";
    }
}

if ($status_filter !== '' && matri_column_exists($conn, $table, 'verification_status')) {
    $st = $conn->real_escape_string($status_filter);
    $where .= " AND verification_status='$st'";
}

$list_res = $conn->query("SELECT * FROM `$table` $where ORDER BY id DESC LIMIT 200");

$stats_where = "WHERE 1";
$stats_where .= matri_village_where_sql($conn, $table);

$total_profiles = (int)($conn->query("SELECT COUNT(*) AS total FROM `$table` $stats_where")->fetch_assoc()['total'] ?? 0);
$pending_profiles = matri_column_exists($conn, $table, 'verification_status') ? (int)($conn->query("SELECT COUNT(*) AS total FROM `$table` $stats_where AND verification_status='pending'")->fetch_assoc()['total'] ?? 0) : 0;
$approved_profiles = matri_column_exists($conn, $table, 'verification_status') ? (int)($conn->query("SELECT COUNT(*) AS total FROM `$table` $stats_where AND verification_status='admin_approved'")->fetch_assoc()['total'] ?? 0) : 0;
$rejected_profiles = matri_column_exists($conn, $table, 'verification_status') ? (int)($conn->query("SELECT COUNT(*) AS total FROM `$table` $stats_where AND verification_status='rejected'")->fetch_assoc()['total'] ?? 0) : 0;
?>


<style>
.matri-stats-wrap{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:16px;
}
.matri-stat-card{
    background:linear-gradient(135deg,#a33b25,#5929c8);
    border:1px solid rgba(255,255,255,0.18);
    border-radius:18px;
    padding:20px;
    min-height:110px;
    box-shadow:0 12px 28px rgba(0,0,0,0.24);
}
.matri-stat-card span{
    color:#ffd36a;
    font-weight:800;
    display:block;
    margin-bottom:10px;
}
.matri-stat-card b{
    color:#fff;
    font-size:34px;
}
.matri-profile-thumb{
    width:55px;
    height:55px;
    object-fit:cover;
    border-radius:8px;
    border:1px solid rgba(255,211,106,.45);
    background:#160303;
}
.matri-action-box{
    display:flex;
    flex-wrap:wrap;
    gap:6px;
    min-width:260px;
}
@media(max-width:900px){
    .matri-stats-wrap{grid-template-columns:repeat(2,1fr);}
}
@media(max-width:600px){
    .matri-stats-wrap{grid-template-columns:1fr;}
}
</style>

<h2 class="page-title">Matrimonial Management</h2>

<?php if (matri_is_village_surveyor()): ?>
    <div class="alert alert-info">
        Aapko sirf allotted gaon ke matrimonial profiles dikh rahe hain.
    </div>
<?php endif; ?>

<div class="matri-stats-wrap mb-4">
    <div class="matri-stat-card"><span>Total Profiles</span><b><?= $total_profiles ?></b></div>
    <div class="matri-stat-card"><span>Pending</span><b><?= $pending_profiles ?></b></div>
    <div class="matri-stat-card"><span>Approved</span><b><?= $approved_profiles ?></b></div>
    <div class="matri-stat-card"><span>Rejected</span><b><?= $rejected_profiles ?></b></div>
</div>

<div class="card-dark mb-4">
    <h5 class="mb-3"><?= !empty($edit) ? 'Edit Matrimonial Profile' : 'Add Matrimonial Profile' ?></h5>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= e($edit['id'] ?? '') ?>">

        <div class="row">
            <?php if (matri_column_exists($conn, $table, 'profile_photo') || matri_column_exists($conn, $table, 'photo')): ?>
            <div class="col-md-4">
                <label>Profile Photo</label>
                <?php
                    $edit_photo = $edit['profile_photo'] ?? ($edit['photo'] ?? '');
                ?>
                <?php if (!empty($edit_photo)): ?>
                    <div class="mb-2">
                        <img src="<?= e(matri_profile_img_src($edit_photo, $profile_photo_url_dir, $default_profile_img)) ?>" class="matri-profile-thumb" alt="Profile">
                    </div>
                <?php endif; ?>
                <input type="file" name="profile_photo" class="form-control mb-2" accept="image/*">
            </div>
            <?php endif; ?>

            <?php if (matri_column_exists($conn, $table, 'full_name')): ?>
            <div class="col-md-4">
                <label>Full Name</label>
                <input type="text" name="full_name" class="form-control mb-2" value="<?= e($edit['full_name'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (matri_column_exists($conn, $table, 'name')): ?>
            <div class="col-md-4">
                <label>Name</label>
                <input type="text" name="name" class="form-control mb-2" value="<?= e($edit['name'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (matri_column_exists($conn, $table, 'gender')): ?>
            <div class="col-md-4">
                <label>Gender</label>
                <select name="gender" class="form-select mb-2">
                    <option value="">Select Gender</option>
                    <option value="Male" <?= (($edit['gender'] ?? '') === 'Male') ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= (($edit['gender'] ?? '') === 'Female') ? 'selected' : '' ?>>Female</option>
                </select>
            </div>
            <?php endif; ?>

            <?php if (matri_column_exists($conn, $table, 'mobile')): ?>
            <div class="col-md-4">
                <label>Mobile</label>
                <input type="text" name="mobile" class="form-control mb-2" value="<?= e($edit['mobile'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (matri_column_exists($conn, $table, 'phone')): ?>
            <div class="col-md-4">
                <label>Phone</label>
                <input type="text" name="phone" class="form-control mb-2" value="<?= e($edit['phone'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (matri_column_exists($conn, $table, 'email')): ?>
            <div class="col-md-4">
                <label>Email</label>
                <input type="email" name="email" class="form-control mb-2" value="<?= e($edit['email'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (matri_column_exists($conn, $table, 'mobile_email')): ?>
            <div class="col-md-4">
                <label>Mobile / Email Login</label>
                <input type="text" name="mobile_email" class="form-control mb-2" value="<?= e($edit['mobile_email'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (matri_column_exists($conn, $table, 'password')): ?>
            <div class="col-md-4">
                <label>Password</label>
                <input type="text" name="password" class="form-control mb-2" placeholder="Blank rakhen to same rahega">
            </div>
            <?php endif; ?>

            <?php if (matri_column_exists($conn, $table, 'gotra')): ?>
            <div class="col-md-4">
                <label>Gotra</label>
                <input type="text" name="gotra" class="form-control mb-2" value="<?= e($edit['gotra'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (matri_column_exists($conn, $table, 'state')): ?>
            <div class="col-md-4">
                <label>State</label>
                <input type="text" name="state" class="form-control mb-2" value="<?= e($edit['state'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (matri_column_exists($conn, $table, 'district')): ?>
            <div class="col-md-4">
                <label>District</label>
                <input type="text" name="district" class="form-control mb-2" value="<?= e($edit['district'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (matri_column_exists($conn, $table, 'tahsil')): ?>
            <div class="col-md-4">
                <label>Tahsil</label>
                <input type="text" name="tahsil" class="form-control mb-2" value="<?= e($edit['tahsil'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (matri_column_exists($conn, $table, 'village_rajasthan')): ?>
            <div class="col-md-4">
                <label>Village Rajasthan</label>
                <input type="text" name="village_rajasthan" class="form-control mb-2" value="<?= e($edit['village_rajasthan'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (matri_column_exists($conn, $table, 'village')): ?>
            <div class="col-md-4">
                <label>Village</label>
                <input type="text" name="village" class="form-control mb-2" value="<?= e($edit['village'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (matri_column_exists($conn, $table, 'village_name')): ?>
            <div class="col-md-4">
                <label>Village Name</label>
                <input type="text" name="village_name" class="form-control mb-2" value="<?= e($edit['village_name'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (matri_column_exists($conn, $table, 'marital_status')): ?>
            <div class="col-md-4">
                <label>Marital Status</label>
                <select name="marital_status" class="form-select mb-2">
                    <option value="">Select</option>
                    <option value="Unmarried" <?= (($edit['marital_status'] ?? '') === 'Unmarried') ? 'selected' : '' ?>>Unmarried</option>
                    <option value="Divorced" <?= (($edit['marital_status'] ?? '') === 'Divorced') ? 'selected' : '' ?>>Divorced</option>
                    <option value="Widow" <?= (($edit['marital_status'] ?? '') === 'Widow') ? 'selected' : '' ?>>Widow</option>
                    <option value="Widower" <?= (($edit['marital_status'] ?? '') === 'Widower') ? 'selected' : '' ?>>Widower</option>
                </select>
            </div>
            <?php endif; ?>

            <?php if (matri_column_exists($conn, $table, 'education')): ?>
            <div class="col-md-4">
                <label>Education</label>
                <input type="text" name="education" class="form-control mb-2" value="<?= e($edit['education'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (matri_column_exists($conn, $table, 'occupation')): ?>
            <div class="col-md-4">
                <label>Occupation</label>
                <input type="text" name="occupation" class="form-control mb-2" value="<?= e($edit['occupation'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (matri_column_exists($conn, $table, 'dob')): ?>
            <div class="col-md-4">
                <label>DOB</label>
                <input type="date" name="dob" class="form-control mb-2" value="<?= e($edit['dob'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (matri_column_exists($conn, $table, 'age')): ?>
            <div class="col-md-4">
                <label>Age</label>
                <input type="number" name="age" class="form-control mb-2" value="<?= e($edit['age'] ?? '') ?>">
            </div>
            <?php endif; ?>
            <?php if (matri_column_exists($conn, $table, 'height')): ?>
            <div class="col-md-4">
                <label>Height</label>
                <input type="text" name="height" class="form-control mb-2" value="<?= e($edit['height'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (matri_column_exists($conn, $table, 'income')): ?>
            <div class="col-md-4">
                <label>Income</label>
                <input type="text" name="income" class="form-control mb-2" value="<?= e($edit['income'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (matri_column_exists($conn, $table, 'father_name')): ?>
            <div class="col-md-4">
                <label>Father Name</label>
                <input type="text" name="father_name" class="form-control mb-2" value="<?= e($edit['father_name'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (matri_column_exists($conn, $table, 'mother_name')): ?>
            <div class="col-md-4">
                <label>Mother Name</label>
                <input type="text" name="mother_name" class="form-control mb-2" value="<?= e($edit['mother_name'] ?? '') ?>">
            </div>
            <?php endif; ?>


            <?php if (matri_column_exists($conn, $table, 'status')): ?>
            <div class="col-md-4">
                <label>Account Status</label>
                <select name="status" class="form-select mb-2">
                    <option value="pending" <?= (($edit['status'] ?? '') === 'pending') ? 'selected' : '' ?>>pending</option>
                    <option value="approved" <?= (($edit['status'] ?? '') === 'approved') ? 'selected' : '' ?>>approved</option>
                    <option value="rejected" <?= (($edit['status'] ?? '') === 'rejected') ? 'selected' : '' ?>>rejected</option>
                </select>
            </div>
            <?php endif; ?>

            <?php if (matri_column_exists($conn, $table, 'verified')): ?>
            <div class="col-md-4">
                <label>Verified</label>
                <select name="verified" class="form-select mb-2">
                    <option value="0" <?= (($edit['verified'] ?? '') == '0') ? 'selected' : '' ?>>No</option>
                    <option value="1" <?= (($edit['verified'] ?? '') == '1') ? 'selected' : '' ?>>Yes</option>
                </select>
            </div>
            <?php endif; ?>

            <?php if (matri_column_exists($conn, $table, 'verification_status')): ?>
            <div class="col-md-4">
                <label>Verification Status</label>
                <select name="verification_status" class="form-select mb-2">
                    <?php
                    $current_vs = $edit['verification_status'] ?? 'pending';
                    $statuses = [
                        'pending' => 'Pending',
                        'village_verified' => 'Village Verified',
                        'admin_approved' => 'Admin Approved',
                        'rejected' => 'Rejected',
                        'correction_required' => 'Correction Required'
                    ];
                    foreach ($statuses as $key => $label) {
                        $sel = ($current_vs === $key) ? 'selected' : '';
                        echo "<option value=\"$key\" $sel>$label</option>";
                    }
                    ?>
                </select>
            </div>
            <?php endif; ?>

            <?php if (matri_column_exists($conn, $table, 'current_address')): ?>
            <div class="col-md-12">
                <label>Current Address</label>
                <textarea name="current_address" class="form-control mb-2"><?= e($edit['current_address'] ?? '') ?></textarea>
            </div>
            <?php endif; ?>

            <?php if (matri_column_exists($conn, $table, 'address')): ?>
            <div class="col-md-12">
                <label>Address</label>
                <textarea name="address" class="form-control mb-2"><?= e($edit['address'] ?? '') ?></textarea>
            </div>
            <?php endif; ?>
        </div>

        <button class="btn btn-gold mt-2">Save</button>
        <a href="matrimonial.php" class="btn btn-secondary mt-2">Clear</a>
    </form>
</div>

<div class="card-dark mb-4">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-md-5">
            <label>Search</label>
            <input type="text" name="search" class="form-control" placeholder="Name / Mobile / Village / Gotra" value="<?= e($search) ?>">
        </div>

        <?php if (matri_column_exists($conn, $table, 'verification_status')): ?>
        <div class="col-md-4">
            <label>Verification Filter</label>
            <select name="status_filter" class="form-select">
                <option value="">All Status</option>
                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="village_verified" <?= $status_filter === 'village_verified' ? 'selected' : '' ?>>Village Verified</option>
                <option value="admin_approved" <?= $status_filter === 'admin_approved' ? 'selected' : '' ?>>Admin Approved</option>
                <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                <option value="correction_required" <?= $status_filter === 'correction_required' ? 'selected' : '' ?>>Correction Required</option>
            </select>
        </div>
        <?php endif; ?>

        <div class="col-md-3">
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
                    <?php if (matri_column_exists($conn, $table, 'profile_photo') || matri_column_exists($conn, $table, 'photo')) echo '<th>Photo</th>'; ?>
                    <?php if (matri_column_exists($conn, $table, 'full_name')) echo '<th>Full Name</th>'; ?>
                    <?php if (matri_column_exists($conn, $table, 'name')) echo '<th>Name</th>'; ?>
                    <?php if (matri_column_exists($conn, $table, 'gender')) echo '<th>Gender</th>'; ?>
                    <?php if (matri_column_exists($conn, $table, 'mobile')) echo '<th>Mobile</th>'; ?>
                    <?php if (matri_column_exists($conn, $table, 'gotra')) echo '<th>Gotra</th>'; ?>
                    <?php if (matri_column_exists($conn, $table, 'age')) echo '<th>Age</th>'; ?>
                    <?php if (matri_column_exists($conn, $table, 'education')) echo '<th>Education</th>'; ?>
                    <?php if (matri_column_exists($conn, $table, 'occupation')) echo '<th>Occupation</th>'; ?>
                    <?php if (matri_column_exists($conn, $table, 'district')) echo '<th>District</th>'; ?>
                    <?php if (matri_column_exists($conn, $table, 'village_rajasthan')) echo '<th>Village Rajasthan</th>'; ?>
                    <?php if (matri_column_exists($conn, $table, 'village')) echo '<th>Village</th>'; ?>
                    <?php if (matri_column_exists($conn, $table, 'village_name')) echo '<th>Village Name</th>'; ?>
                    <?php if (matri_column_exists($conn, $table, 'verification_status')) echo '<th>Verification</th>'; ?>
                    <?php if (matri_column_exists($conn, $table, 'verified')) echo '<th>Verified</th>'; ?>
                    <?php if (matri_column_exists($conn, $table, 'status')) echo '<th>Status</th>'; ?>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($list_res && $list_res->num_rows > 0): ?>
                    <?php while ($r = $list_res->fetch_assoc()): ?>
                        <?php $vs = $r['verification_status'] ?? 'pending'; ?>
                        <tr>
                            <td><?= e($r['id'] ?? '') ?></td>
                            <?php if (matri_column_exists($conn, $table, 'profile_photo') || matri_column_exists($conn, $table, 'photo')): ?>
                            <td>
                                <?php $row_photo = $r['profile_photo'] ?? ($r['photo'] ?? ''); ?>
                                <img src="<?= e(matri_profile_img_src($row_photo, $profile_photo_url_dir, $default_profile_img)) ?>" class="matri-profile-thumb" alt="Profile">
                            </td>
                            <?php endif; ?>
                            <?php if (matri_column_exists($conn, $table, 'full_name')) echo '<td>' . e($r['full_name'] ?? '') . '</td>'; ?>
                            <?php if (matri_column_exists($conn, $table, 'name')) echo '<td>' . e($r['name'] ?? '') . '</td>'; ?>
                            <?php if (matri_column_exists($conn, $table, 'gender')) echo '<td>' . e($r['gender'] ?? '') . '</td>'; ?>
                            <?php if (matri_column_exists($conn, $table, 'mobile')) echo '<td>' . e($r['mobile'] ?? '') . '</td>'; ?>
                            <?php if (matri_column_exists($conn, $table, 'gotra')) echo '<td>' . e($r['gotra'] ?? '') . '</td>'; ?>
                            <?php if (matri_column_exists($conn, $table, 'age')) echo '<td>' . e($r['age'] ?? '') . '</td>'; ?>
                            <?php if (matri_column_exists($conn, $table, 'education')) echo '<td>' . e($r['education'] ?? '') . '</td>'; ?>
                            <?php if (matri_column_exists($conn, $table, 'occupation')) echo '<td>' . e($r['occupation'] ?? '') . '</td>'; ?>
                            <?php if (matri_column_exists($conn, $table, 'district')) echo '<td>' . e($r['district'] ?? '') . '</td>'; ?>
                            <?php if (matri_column_exists($conn, $table, 'village_rajasthan')) echo '<td>' . e($r['village_rajasthan'] ?? '') . '</td>'; ?>
                            <?php if (matri_column_exists($conn, $table, 'village')) echo '<td>' . e($r['village'] ?? '') . '</td>'; ?>
                            <?php if (matri_column_exists($conn, $table, 'village_name')) echo '<td>' . e($r['village_name'] ?? '') . '</td>'; ?>

                            <?php if (matri_column_exists($conn, $table, 'verification_status')): ?>
                            <td>
                                <?php
                                $badge = 'secondary';
                                if ($vs === 'pending') $badge = 'warning';
                                if ($vs === 'village_verified') $badge = 'info';
                                if ($vs === 'admin_approved') $badge = 'success';
                                if ($vs === 'rejected') $badge = 'danger';
                                if ($vs === 'correction_required') $badge = 'primary';
                                ?>
                                <span class="badge bg-<?= $badge ?>"><?= e($vs) ?></span>
                            </td>
                            <?php endif; ?>

                            <?php if (matri_column_exists($conn, $table, 'verified')): ?>
                            <td><?= (($r['verified'] ?? '') == '1') ? 'Yes' : 'No' ?></td>
                            <?php endif; ?>

                            <?php if (matri_column_exists($conn, $table, 'status')): ?>
                            <td><?= e($r['status'] ?? '') ?></td>
                            <?php endif; ?>

                            <td><div class="matri-action-box">
                                <?php if (matri_column_exists($conn, $table, 'verification_status')): ?>
                                    <a class="btn btn-sm btn-success mb-1" href="?action=approve&id=<?= (int)$r['id'] ?>">Approve</a>
                                    <a class="btn btn-sm btn-warning mb-1" href="?action=pending&id=<?= (int)$r['id'] ?>">Pending</a>
                                    <a class="btn btn-sm btn-danger mb-1" onclick="return confirm('Reject this profile?')" href="?action=reject&id=<?= (int)$r['id'] ?>">Reject</a>
                                    <a class="btn btn-sm btn-info mb-1" href="?action=village_verify&id=<?= (int)$r['id'] ?>">Village Verify</a>
                                    <a class="btn btn-sm btn-dark mb-1" href="?action=correction&id=<?= (int)$r['id'] ?>">Correction</a>
                                <?php endif; ?>

                                <a class="btn btn-sm btn-warning mb-1" href="?edit=<?= (int)$r['id'] ?>">Edit</a>
                                <a class="btn btn-sm btn-danger mb-1" onclick="return confirm('Delete?')" href="?delete=<?= (int)$r['id'] ?>">Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="15">No matrimonial record found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
