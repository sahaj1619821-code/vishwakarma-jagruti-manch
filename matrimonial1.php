<?php
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

$where = "WHERE 1";

if ($search != '') {
    $s = $conn->real_escape_string($search);
    $where .= " AND (name LIKE '%$s%' OR mobile LIKE '%$s%' OR village_name LIKE '%$s%' OR gotra LIKE '%$s%')";
}

if ($status != '') {
    $st = $conn->real_escape_string($status);
    $where .= " AND verification_status='$st'";
}

$result = $conn->query("SELECT * FROM matrimonial_users $where ORDER BY id DESC");
if (isset($_GET['action'], $_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];

    if ($action == 'village_verify') {
        $conn->query("UPDATE matrimonial_users 
                      SET verification_status='village_verified',
                          verified_at=NOW()
                      WHERE id=$id");
    }

    if ($action == 'approve') {
        $conn->query("UPDATE matrimonial_users 
                      SET verification_status='admin_approved'
                      WHERE id=$id");
    }

    if ($action == 'reject') {
        $conn->query("UPDATE matrimonial_users 
                      SET verification_status='rejected'
                      WHERE id=$id");
    }

    if ($action == 'correction') {
        $conn->query("UPDATE matrimonial_users 
                      SET verification_status='correction_required'
                      WHERE id=$id");
    }

    header("Location: matrimonial.php");
    exit;
}
include 'includes/header.php';
include 'includes/sidebar.php';

$table = 'matrimonial_users'; // database me matrimonial table ka exact naam

if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

function matri_column_exists($conn, $table, $column) {
    if (!table_exists($conn, $table)) {
        return false;
    }

    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);

    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($res && $res->num_rows > 0);
}

if (!table_exists($conn, $table)) {
    echo '<div class="alert alert-danger">Table matrimonial_users database में नहीं है।</div>';
}

$edit = [];

if (isset($_GET['edit']) && table_exists($conn, $table)) {
    $id = (int)$_GET['edit'];
    $res = $conn->query("SELECT * FROM `$table` WHERE id=$id");

    if ($res && $res->num_rows > 0) {
        $edit = $res->fetch_assoc();
    }
}

if (isset($_GET['delete']) && table_exists($conn, $table)) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM `$table` WHERE id=$id");
    redirect('matrimonial.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && table_exists($conn, $table)) {

    $allowed_cols = [
        'name',
        'gender',
        'mobile',
        'phone',
        'email',
        'password',
        'gotra',
        'state',
        'district',
        'tahsil',
        'village',
        'current_address',
        'address',
        'marital_status',
        'education',
        'occupation',
        'dob',
        'age',
        'status',
        'verified'
    ];

    $data = [];

    foreach ($allowed_cols as $col) {
        if (matri_column_exists($conn, $table, $col) && isset($_POST[$col])) {
            if ($col == 'password') {
                if ($_POST[$col] != '') {
                    $data[$col] = $_POST[$col];
                }
            } else {
                $data[$col] = $_POST[$col];
            }
        }
    }

    if (isset($_POST['id']) && $_POST['id'] != '') {
        $id = (int)$_POST['id'];
        $set = [];

        foreach ($data as $k => $v) {
            $v = $conn->real_escape_string($v);
            $set[] = "`$k`='$v'";
        }

        if (!empty($set)) {
            $conn->query("UPDATE `$table` SET " . implode(',', $set) . " WHERE id=$id");
        }

    } else {
        $keys = array_keys($data);
        $vals = [];

        foreach ($data as $v) {
            $vals[] = "'" . $conn->real_escape_string($v) . "'";
        }

        if (!empty($keys)) {
            $conn->query("INSERT INTO `$table` (`" . implode('`,`', $keys) . "`) VALUES (" . implode(',', $vals) . ")");
        }
    }

    redirect('matrimonial.php');
}
?>

<h2 class="page-title">Matrimonial Management</h2>

<div class="card-dark mb-4">
    <form method="post">
        <input type="hidden" name="id" value="<?= e($edit['id'] ?? '') ?>">

        <div class="row">

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
                    <option value="Male" <?= (($edit['gender'] ?? '') == 'Male') ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= (($edit['gender'] ?? '') == 'Female') ? 'selected' : '' ?>>Female</option>
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

            <?php if (matri_column_exists($conn, $table, 'village')): ?>
            <div class="col-md-4">
                <label>Village</label>
                <input type="text" name="village" class="form-control mb-2" value="<?= e($edit['village'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (matri_column_exists($conn, $table, 'marital_status')): ?>
            <div class="col-md-4">
                <label>Marital Status</label>
                <select name="marital_status" class="form-select mb-2">
                    <option value="">Select</option>
                    <option value="Unmarried" <?= (($edit['marital_status'] ?? '') == 'Unmarried') ? 'selected' : '' ?>>Unmarried</option>
                    <option value="Divorced" <?= (($edit['marital_status'] ?? '') == 'Divorced') ? 'selected' : '' ?>>Divorced</option>
                    <option value="Widow" <?= (($edit['marital_status'] ?? '') == 'Widow') ? 'selected' : '' ?>>Widow</option>
                    <option value="Widower" <?= (($edit['marital_status'] ?? '') == 'Widower') ? 'selected' : '' ?>>Widower</option>
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

            <?php if (matri_column_exists($conn, $table, 'status')): ?>
            <div class="col-md-4">
                <label>Status</label>
                <select name="status" class="form-select mb-2">
                    <option value="active" <?= (($edit['status'] ?? '') == 'active') ? 'selected' : '' ?>>active</option>
                    <option value="inactive" <?= (($edit['status'] ?? '') == 'inactive') ? 'selected' : '' ?>>inactive</option>
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

<div class="card-dark">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <?php if (matri_column_exists($conn, $table, 'name')) echo '<th>Name</th>'; ?>
                    <?php if (matri_column_exists($conn, $table, 'gender')) echo '<th>Gender</th>'; ?>
                    <?php if (matri_column_exists($conn, $table, 'mobile')) echo '<th>Mobile</th>'; ?>
                    <?php if (matri_column_exists($conn, $table, 'phone')) echo '<th>Phone</th>'; ?>
                    <?php if (matri_column_exists($conn, $table, 'email')) echo '<th>Email</th>'; ?>
                    <?php if (matri_column_exists($conn, $table, 'gotra')) echo '<th>Gotra</th>'; ?>
                    <?php if (matri_column_exists($conn, $table, 'district')) echo '<th>District</th>'; ?>
                    <?php if (matri_column_exists($conn, $table, 'village')) echo '<th>Village</th>'; ?>
                    <?php if (matri_column_exists($conn, $table, 'verified')) echo '<th>Verified</th>'; ?>
                    <?php if (matri_column_exists($conn, $table, 'status')) echo '<th>Status</th>'; ?>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                <?php
                if (table_exists($conn, $table)) {
                    $res = $conn->query("SELECT * FROM `$table` ORDER BY id DESC LIMIT 100");

                    if ($res && $res->num_rows > 0) {
                        while ($r = $res->fetch_assoc()) {
                ?>
                            <tr>
                                <td><?= e($r['id'] ?? '') ?></td>
                                <?php if (matri_column_exists($conn, $table, 'name')) echo '<td>' . e($r['name'] ?? '') . '</td>'; ?>
                                <?php if (matri_column_exists($conn, $table, 'gender')) echo '<td>' . e($r['gender'] ?? '') . '</td>'; ?>
                                <?php if (matri_column_exists($conn, $table, 'mobile')) echo '<td>' . e($r['mobile'] ?? '') . '</td>'; ?>
                                <?php if (matri_column_exists($conn, $table, 'phone')) echo '<td>' . e($r['phone'] ?? '') . '</td>'; ?>
                                <?php if (matri_column_exists($conn, $table, 'email')) echo '<td>' . e($r['email'] ?? '') . '</td>'; ?>
                                <?php if (matri_column_exists($conn, $table, 'gotra')) echo '<td>' . e($r['gotra'] ?? '') . '</td>'; ?>
                                <?php if (matri_column_exists($conn, $table, 'district')) echo '<td>' . e($r['district'] ?? '') . '</td>'; ?>
                                <?php if (matri_column_exists($conn, $table, 'village')) echo '<td>' . e($r['village'] ?? '') . '</td>'; ?>
                                <?php if (matri_column_exists($conn, $table, 'verified')) echo '<td>' . (($r['verified'] ?? '') == '1' ? 'Yes' : 'No') . '</td>'; ?>
                                <?php if (matri_column_exists($conn, $table, 'status')) echo '<td>' . e($r['status'] ?? '') . '</td>'; ?>

                                <td>
                                    <a class="btn btn-sm btn-warning" href="?edit=<?= $r['id'] ?>">Edit</a>
                                    <a class="btn btn-sm btn-danger" onclick="return confirm('Delete?')" href="?delete=<?= $r['id'] ?>">Delete</a>
                                </td>
                            </tr>
                <?php
                        }
                    } else {
                        echo '<tr><td colspan="12">No matrimonial record found</td></tr>';
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>