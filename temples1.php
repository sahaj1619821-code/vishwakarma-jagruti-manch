<?php
include 'includes/header.php';
include 'includes/sidebar.php';

$table = 'temples'; // database me temple table ka exact naam

if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

function temple_column_exists($conn, $table, $column) {
    if (!table_exists($conn, $table)) {
        return false;
    }

    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);

    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($res && $res->num_rows > 0);
}

if (!table_exists($conn, $table)) {
    echo '<div class="alert alert-danger">Table temples database में नहीं है।</div>';
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
    redirect('temples.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && table_exists($conn, $table)) {

    $allowed_cols = [
        'temple_name',
        'name',
        'title',
        'location',
        'address',
        'history',
        'description',
        'image',
        'status'
    ];

    $data = [];

    foreach ($allowed_cols as $col) {
        if (temple_column_exists($conn, $table, $col) && isset($_POST[$col])) {
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

    redirect('temples.php');
}
?>

<h2 class="page-title">Temples Management</h2>

<div class="card-dark mb-4">
    <form method="post">
        <input type="hidden" name="id" value="<?= e($edit['id'] ?? '') ?>">

        <div class="row">

            <?php if (temple_column_exists($conn, $table, 'temple_name')): ?>
            <div class="col-md-4">
                <label>Temple Name</label>
                <input type="text" name="temple_name" class="form-control mb-2" value="<?= e($edit['temple_name'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (temple_column_exists($conn, $table, 'name')): ?>
            <div class="col-md-4">
                <label>Name</label>
                <input type="text" name="name" class="form-control mb-2" value="<?= e($edit['name'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (temple_column_exists($conn, $table, 'title')): ?>
            <div class="col-md-4">
                <label>Title</label>
                <input type="text" name="title" class="form-control mb-2" value="<?= e($edit['title'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (temple_column_exists($conn, $table, 'location')): ?>
            <div class="col-md-4">
                <label>Location</label>
                <input type="text" name="location" class="form-control mb-2" value="<?= e($edit['location'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (temple_column_exists($conn, $table, 'address')): ?>
            <div class="col-md-12">
                <label>Address</label>
                <textarea name="address" class="form-control mb-2"><?= e($edit['address'] ?? '') ?></textarea>
            </div>
            <?php endif; ?>

            <?php if (temple_column_exists($conn, $table, 'history')): ?>
            <div class="col-md-12">
                <label>History</label>
                <textarea name="history" class="form-control mb-2" rows="4"><?= e($edit['history'] ?? '') ?></textarea>
            </div>
            <?php endif; ?>

            <?php if (temple_column_exists($conn, $table, 'description')): ?>
            <div class="col-md-12">
                <label>Description</label>
                <textarea name="description" class="form-control mb-2" rows="4"><?= e($edit['description'] ?? '') ?></textarea>
            </div>
            <?php endif; ?>

            <?php if (temple_column_exists($conn, $table, 'image')): ?>
            <div class="col-md-4">
                <label>Image Name / Path</label>
                <input type="text" name="image" class="form-control mb-2" value="<?= e($edit['image'] ?? '') ?>" placeholder="images/temple.jpg">
            </div>
            <?php endif; ?>

            <?php if (temple_column_exists($conn, $table, 'status')): ?>
            <div class="col-md-4">
                <label>Status</label>
                <select name="status" class="form-select mb-2">
                    <option value="active" <?= (($edit['status'] ?? '') == 'active') ? 'selected' : '' ?>>active</option>
                    <option value="inactive" <?= (($edit['status'] ?? '') == 'inactive') ? 'selected' : '' ?>>inactive</option>
                </select>
            </div>
            <?php endif; ?>

        </div>

        <button class="btn btn-gold mt-2">Save</button>
        <a href="temples.php" class="btn btn-secondary mt-2">Clear</a>
    </form>
</div>

<div class="card-dark">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <?php if (temple_column_exists($conn, $table, 'temple_name')) echo '<th>Temple Name</th>'; ?>
                    <?php if (temple_column_exists($conn, $table, 'name')) echo '<th>Name</th>'; ?>
                    <?php if (temple_column_exists($conn, $table, 'title')) echo '<th>Title</th>'; ?>
                    <?php if (temple_column_exists($conn, $table, 'location')) echo '<th>Location</th>'; ?>
                    <?php if (temple_column_exists($conn, $table, 'address')) echo '<th>Address</th>'; ?>
                    <?php if (temple_column_exists($conn, $table, 'image')) echo '<th>Image</th>'; ?>
                    <?php if (temple_column_exists($conn, $table, 'status')) echo '<th>Status</th>'; ?>
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
                                <?php if (temple_column_exists($conn, $table, 'temple_name')) echo '<td>' . e($r['temple_name'] ?? '') . '</td>'; ?>
                                <?php if (temple_column_exists($conn, $table, 'name')) echo '<td>' . e($r['name'] ?? '') . '</td>'; ?>
                                <?php if (temple_column_exists($conn, $table, 'title')) echo '<td>' . e($r['title'] ?? '') . '</td>'; ?>
                                <?php if (temple_column_exists($conn, $table, 'location')) echo '<td>' . e($r['location'] ?? '') . '</td>'; ?>
                                <?php if (temple_column_exists($conn, $table, 'address')) echo '<td>' . e($r['address'] ?? '') . '</td>'; ?>
                                <?php if (temple_column_exists($conn, $table, 'image')) echo '<td>' . e($r['image'] ?? '') . '</td>'; ?>
                                <?php if (temple_column_exists($conn, $table, 'status')) echo '<td>' . e($r['status'] ?? '') . '</td>'; ?>

                                <td>
                                    <a class="btn btn-sm btn-warning" href="?edit=<?= $r['id'] ?>">Edit</a>
                                    <a class="btn btn-sm btn-danger" onclick="return confirm('Delete?')" href="?delete=<?= $r['id'] ?>">Delete</a>
                                </td>
                            </tr>
                <?php
                        }
                    } else {
                        echo '<tr><td colspan="9">No temple record found</td></tr>';
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>