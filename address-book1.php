<?php
include 'includes/header.php';
include 'includes/sidebar.php';

$table = 'address_book'; // database me address book table ka exact naam

if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

function address_column_exists($conn, $table, $column) {
    if (!table_exists($conn, $table)) {
        return false;
    }

    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);

    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($res && $res->num_rows > 0);
}

if (!table_exists($conn, $table)) {
    echo '<div class="alert alert-danger">Table address_book database में नहीं है।</div>';
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
    redirect('addressbook.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && table_exists($conn, $table)) {

    $allowed_cols = [
        'name',
        'mobile',
        'phone',
        'email',
        'address',
        'city',
        'village',
        'district',
        'state',
        'pincode',
        'category',
        'status'
    ];

    $data = [];

    foreach ($allowed_cols as $col) {
        if (address_column_exists($conn, $table, $col) && isset($_POST[$col])) {
            $data[$col] = $_POST[$col];
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

    redirect('addressbook.php');
}
?>

<h2 class="page-title">Address Book Management</h2>

<div class="card-dark mb-4">
    <form method="post">
        <input type="hidden" name="id" value="<?= e($edit['id'] ?? '') ?>">

        <div class="row">

            <?php if (address_column_exists($conn, $table, 'name')): ?>
            <div class="col-md-4">
                <label>Name</label>
                <input type="text" name="name" class="form-control mb-2" value="<?= e($edit['name'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (address_column_exists($conn, $table, 'mobile')): ?>
            <div class="col-md-4">
                <label>Mobile</label>
                <input type="text" name="mobile" class="form-control mb-2" value="<?= e($edit['mobile'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (address_column_exists($conn, $table, 'phone')): ?>
            <div class="col-md-4">
                <label>Phone</label>
                <input type="text" name="phone" class="form-control mb-2" value="<?= e($edit['phone'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (address_column_exists($conn, $table, 'email')): ?>
            <div class="col-md-4">
                <label>Email</label>
                <input type="email" name="email" class="form-control mb-2" value="<?= e($edit['email'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (address_column_exists($conn, $table, 'city')): ?>
            <div class="col-md-4">
                <label>City</label>
                <input type="text" name="city" class="form-control mb-2" value="<?= e($edit['city'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (address_column_exists($conn, $table, 'village')): ?>
            <div class="col-md-4">
                <label>Village</label>
                <input type="text" name="village" class="form-control mb-2" value="<?= e($edit['village'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (address_column_exists($conn, $table, 'district')): ?>
            <div class="col-md-4">
                <label>District</label>
                <input type="text" name="district" class="form-control mb-2" value="<?= e($edit['district'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (address_column_exists($conn, $table, 'state')): ?>
            <div class="col-md-4">
                <label>State</label>
                <input type="text" name="state" class="form-control mb-2" value="<?= e($edit['state'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (address_column_exists($conn, $table, 'pincode')): ?>
            <div class="col-md-4">
                <label>Pincode</label>
                <input type="text" name="pincode" class="form-control mb-2" value="<?= e($edit['pincode'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (address_column_exists($conn, $table, 'category')): ?>
            <div class="col-md-4">
                <label>Category</label>
                <input type="text" name="category" class="form-control mb-2" value="<?= e($edit['category'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (address_column_exists($conn, $table, 'status')): ?>
            <div class="col-md-4">
                <label>Status</label>
                <select name="status" class="form-select mb-2">
                    <option value="active" <?= (($edit['status'] ?? '') == 'active') ? 'selected' : '' ?>>active</option>
                    <option value="inactive" <?= (($edit['status'] ?? '') == 'inactive') ? 'selected' : '' ?>>inactive</option>
                </select>
            </div>
            <?php endif; ?>

            <?php if (address_column_exists($conn, $table, 'address')): ?>
            <div class="col-md-12">
                <label>Address</label>
                <textarea name="address" class="form-control mb-2" rows="3"><?= e($edit['address'] ?? '') ?></textarea>
            </div>
            <?php endif; ?>

        </div>

        <button class="btn btn-gold mt-2">Save</button>
        <a href="addressbook.php" class="btn btn-secondary mt-2">Clear</a>
    </form>
</div>

<div class="card-dark">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <?php if (address_column_exists($conn, $table, 'name')) echo '<th>Name</th>'; ?>
                    <?php if (address_column_exists($conn, $table, 'mobile')) echo '<th>Mobile</th>'; ?>
                    <?php if (address_column_exists($conn, $table, 'phone')) echo '<th>Phone</th>'; ?>
                    <?php if (address_column_exists($conn, $table, 'email')) echo '<th>Email</th>'; ?>
                    <?php if (address_column_exists($conn, $table, 'city')) echo '<th>City</th>'; ?>
                    <?php if (address_column_exists($conn, $table, 'village')) echo '<th>Village</th>'; ?>
                    <?php if (address_column_exists($conn, $table, 'district')) echo '<th>District</th>'; ?>
                    <?php if (address_column_exists($conn, $table, 'state')) echo '<th>State</th>'; ?>
                    <?php if (address_column_exists($conn, $table, 'status')) echo '<th>Status</th>'; ?>
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
                                <?php if (address_column_exists($conn, $table, 'name')) echo '<td>' . e($r['name'] ?? '') . '</td>'; ?>
                                <?php if (address_column_exists($conn, $table, 'mobile')) echo '<td>' . e($r['mobile'] ?? '') . '</td>'; ?>
                                <?php if (address_column_exists($conn, $table, 'phone')) echo '<td>' . e($r['phone'] ?? '') . '</td>'; ?>
                                <?php if (address_column_exists($conn, $table, 'email')) echo '<td>' . e($r['email'] ?? '') . '</td>'; ?>
                                <?php if (address_column_exists($conn, $table, 'city')) echo '<td>' . e($r['city'] ?? '') . '</td>'; ?>
                                <?php if (address_column_exists($conn, $table, 'village')) echo '<td>' . e($r['village'] ?? '') . '</td>'; ?>
                                <?php if (address_column_exists($conn, $table, 'district')) echo '<td>' . e($r['district'] ?? '') . '</td>'; ?>
                                <?php if (address_column_exists($conn, $table, 'state')) echo '<td>' . e($r['state'] ?? '') . '</td>'; ?>
                                <?php if (address_column_exists($conn, $table, 'status')) echo '<td>' . e($r['status'] ?? '') . '</td>'; ?>

                                <td>
                                    <a class="btn btn-sm btn-warning" href="?edit=<?= $r['id'] ?>">Edit</a>
                                    <a class="btn btn-sm btn-danger" onclick="return confirm('Delete?')" href="?delete=<?= $r['id'] ?>">Delete</a>
                                </td>
                            </tr>
                <?php
                        }
                    } else {
                        echo '<tr><td colspan="11">No address record found</td></tr>';
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>