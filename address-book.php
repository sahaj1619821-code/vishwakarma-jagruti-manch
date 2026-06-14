<?php
include 'auth.php';
requireRole(['address_admin']);

include 'includes/header.php';
include 'includes/sidebar.php';

$table = 'address_book';

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

if (isset($_GET['toggle'], $_GET['id']) && table_exists($conn, $table) && address_column_exists($conn, $table, 'status')) {
    $id = (int)$_GET['id'];
    $newStatus = ($_GET['toggle'] == 'active') ? 'active' : 'inactive';
    $conn->query("UPDATE `$table` SET status='" . $conn->real_escape_string($newStatus) . "' WHERE id=$id");
    redirect('address-book.php');
}

if (isset($_GET['delete']) && table_exists($conn, $table)) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM `$table` WHERE id=$id");
    redirect('address-book.php');
}

$edit = [];

if (isset($_GET['edit']) && table_exists($conn, $table)) {
    $id = (int)$_GET['edit'];
    $res = $conn->query("SELECT * FROM `$table` WHERE id=$id");

    if ($res && $res->num_rows > 0) {
        $edit = $res->fetch_assoc();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && table_exists($conn, $table)) {

    $allowed_cols = [
        'name',
        'father_name',
        'mobile',
        'village',
        'district',
        'state',
        'current_address',
        'address',
        'profession',
        'status'
    ];

    $data = [];

    foreach ($allowed_cols as $col) {
        if (address_column_exists($conn, $table, $col) && isset($_POST[$col])) {
            $data[$col] = trim($_POST[$col]);
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

    redirect('address-book.php');
}

$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$where = "WHERE 1";

if ($search != '' && table_exists($conn, $table)) {
    $s = $conn->real_escape_string($search);
    $parts = [];

    $search_cols = [
        'name',
        'father_name',
        'mobile',
        'village',
        'district',
        'state',
        'profession',
        'current_address',
        'address'
    ];

    foreach ($search_cols as $col) {
        if (address_column_exists($conn, $table, $col)) {
            $parts[] = "`$col` LIKE '%$s%'";
        }
    }

    if (!empty($parts)) {
        $where .= " AND (" . implode(" OR ", $parts) . ")";
    }
}

if ($status_filter != '' && table_exists($conn, $table) && address_column_exists($conn, $table, 'status')) {
    $st = $conn->real_escape_string($status_filter);
    $where .= " AND status='$st'";
}
?>

<style>
.address-form-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}
.address-full {
    grid-column: 1 / -1;
}
.address-actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}
.status-active {
    background: #198754;
    color: white;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
}
.status-inactive {
    background: #dc3545;
    color: white;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
}
@media (max-width: 768px) {
    .address-form-grid {
        grid-template-columns: 1fr;
    }
    .address-actions .btn {
        width: 100%;
    }
}
</style>

<h2 class="page-title">Address Book Management</h2>

<div class="card-dark mb-4">
    <h4><?= !empty($edit) ? 'Edit Member' : 'Add Member' ?></h4>

    <form method="post">
        <input type="hidden" name="id" value="<?= e($edit['id'] ?? '') ?>">

        <div class="address-form-grid">

            <?php if (address_column_exists($conn, $table, 'name')): ?>
            <div>
                <label>Name *</label>
                <input type="text" name="name" class="form-control" required value="<?= e($edit['name'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (address_column_exists($conn, $table, 'father_name')): ?>
            <div>
                <label>Father Name *</label>
                <input type="text" name="father_name" class="form-control" required value="<?= e($edit['father_name'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (address_column_exists($conn, $table, 'mobile')): ?>
            <div>
                <label>Mobile Number *</label>
                <input type="text" name="mobile" class="form-control" required value="<?= e($edit['mobile'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (address_column_exists($conn, $table, 'village')): ?>
            <div>
                <label>Village</label>
                <input type="text" name="village" class="form-control" value="<?= e($edit['village'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (address_column_exists($conn, $table, 'district')): ?>
            <div>
                <label>District</label>
                <input type="text" name="district" class="form-control" value="<?= e($edit['district'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (address_column_exists($conn, $table, 'state')): ?>
            <div>
                <label>State</label>
                <input type="text" name="state" class="form-control" value="<?= e($edit['state'] ?? 'Rajasthan') ?>">
            </div>
            <?php endif; ?>

            <?php if (address_column_exists($conn, $table, 'profession')): ?>
            <div>
                <label>Profession</label>
                <input type="text" name="profession" class="form-control" value="<?= e($edit['profession'] ?? '') ?>">
            </div>
            <?php endif; ?>

            <?php if (address_column_exists($conn, $table, 'status')): ?>
            <div>
                <label>Status</label>
                <select name="status" class="form-select">
                    <option value="active" <?= (($edit['status'] ?? 'active') == 'active') ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= (($edit['status'] ?? '') == 'inactive') ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <?php endif; ?>

            <?php if (address_column_exists($conn, $table, 'current_address')): ?>
            <div class="address-full">
                <label>Current Address</label>
                <textarea name="current_address" class="form-control" rows="3"><?= e($edit['current_address'] ?? '') ?></textarea>
            </div>
            <?php elseif (address_column_exists($conn, $table, 'address')): ?>
            <div class="address-full">
                <label>Current Address</label>
                <textarea name="address" class="form-control" rows="3"><?= e($edit['address'] ?? '') ?></textarea>
            </div>
            <?php endif; ?>

        </div>

        <button class="btn btn-gold mt-3">
            <?= !empty($edit) ? 'Update Member' : 'Add Member' ?>
        </button>

        <a href="address-book.php" class="btn btn-secondary mt-3">Clear</a>
    </form>
</div>

<div class="card-dark mb-4">
    <h4>Search Member</h4>

    <form method="get" class="row g-2 align-items-end">
        <div class="col-md-6">
            <label>Search</label>
            <input type="text" name="search" class="form-control"
                   placeholder="Name / Father Name / Mobile / Village / District / Profession"
                   value="<?= e($search) ?>">
        </div>

        <?php if (address_column_exists($conn, $table, 'status')): ?>
        <div class="col-md-3">
            <label>Status</label>
            <select name="status" class="form-select">
                <option value="">All</option>
                <option value="active" <?= ($status_filter == 'active') ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= ($status_filter == 'inactive') ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        <?php endif; ?>

        <div class="col-md-3">
            <button class="btn btn-primary w-100">Search</button>
        </div>
    </form>
</div>

<div class="card-dark">
    <h4>Member List</h4>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <?php if (address_column_exists($conn, $table, 'name')) echo '<th>Name</th>'; ?>
                    <?php if (address_column_exists($conn, $table, 'father_name')) echo '<th>Father Name</th>'; ?>
                    <?php if (address_column_exists($conn, $table, 'mobile')) echo '<th>Mobile</th>'; ?>
                    <?php if (address_column_exists($conn, $table, 'village')) echo '<th>Village</th>'; ?>
                    <?php if (address_column_exists($conn, $table, 'district')) echo '<th>District</th>'; ?>
                    <?php if (address_column_exists($conn, $table, 'state')) echo '<th>State</th>'; ?>
                    <?php if (address_column_exists($conn, $table, 'profession')) echo '<th>Profession</th>'; ?>
                    <?php if (address_column_exists($conn, $table, 'status')) echo '<th>Status</th>'; ?>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                <?php
                if (table_exists($conn, $table)) {
                    $res = $conn->query("SELECT * FROM `$table` $where ORDER BY id DESC LIMIT 200");

                    if ($res && $res->num_rows > 0) {
                        while ($r = $res->fetch_assoc()) {
                            $rowStatus = $r['status'] ?? 'active';
                ?>
                            <tr>
                                <td><?= e($r['id'] ?? '') ?></td>

                                <?php if (address_column_exists($conn, $table, 'name')) echo '<td>' . e($r['name'] ?? '') . '</td>'; ?>
                                <?php if (address_column_exists($conn, $table, 'father_name')) echo '<td>' . e($r['father_name'] ?? '') . '</td>'; ?>
                                <?php if (address_column_exists($conn, $table, 'mobile')) echo '<td>' . e($r['mobile'] ?? '') . '</td>'; ?>
                                <?php if (address_column_exists($conn, $table, 'village')) echo '<td>' . e($r['village'] ?? '') . '</td>'; ?>
                                <?php if (address_column_exists($conn, $table, 'district')) echo '<td>' . e($r['district'] ?? '') . '</td>'; ?>
                                <?php if (address_column_exists($conn, $table, 'state')) echo '<td>' . e($r['state'] ?? '') . '</td>'; ?>
                                <?php if (address_column_exists($conn, $table, 'profession')) echo '<td>' . e($r['profession'] ?? '') . '</td>'; ?>

                                <?php if (address_column_exists($conn, $table, 'status')): ?>
                                <td>
                                    <span class="<?= $rowStatus == 'active' ? 'status-active' : 'status-inactive' ?>">
                                        <?= e(ucfirst($rowStatus)) ?>
                                    </span>
                                </td>
                                <?php endif; ?>

                                <td>
                                    <div class="address-actions">
                                        <a class="btn btn-sm btn-warning" href="?edit=<?= (int)$r['id'] ?>">Edit</a>

                                        <?php if (address_column_exists($conn, $table, 'status')): ?>
                                            <?php if ($rowStatus == 'active'): ?>
                                                <a class="btn btn-sm btn-secondary" href="?toggle=inactive&id=<?= (int)$r['id'] ?>">Inactive</a>
                                            <?php else: ?>
                                                <a class="btn btn-sm btn-success" href="?toggle=active&id=<?= (int)$r['id'] ?>">Active</a>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <a class="btn btn-sm btn-danger" onclick="return confirm('Delete this member?')" href="?delete=<?= (int)$r['id'] ?>">Delete</a>
                                    </div>
                                </td>
                            </tr>
                <?php
                        }
                    } else {
                        echo '<tr><td colspan="10">No address book record found</td></tr>';
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
