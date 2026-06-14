<?php
include 'auth.php';
requireRole(['temple_admin']);
include 'includes/header.php';
include 'includes/sidebar.php';

$table = 'temples';

if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

function temple_column_exists($conn, $table, $column) {
    if (!table_exists($conn, $table)) return false;
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($res && $res->num_rows > 0);
}

if (!table_exists($conn, $table)) {
    echo '<div class="alert alert-danger">Table temples database में नहीं है।</div>';
}

$edit = [];

/* Active / Inactive */
if (isset($_GET['status'], $_GET['id']) && table_exists($conn, $table)) {
    $id = (int)$_GET['id'];
    $status = ($_GET['status'] == 'active') ? 'active' : 'inactive';
    if (temple_column_exists($conn, $table, 'status')) {
        $conn->query("UPDATE `$table` SET status='$status' WHERE id=$id");
    }
    redirect('temples.php');
}

/* Edit */
if (isset($_GET['edit']) && table_exists($conn, $table)) {
    $id = (int)$_GET['edit'];
    $res = $conn->query("SELECT * FROM `$table` WHERE id=$id");
    if ($res && $res->num_rows > 0) $edit = $res->fetch_assoc();
}

/* Delete */
if (isset($_GET['delete']) && table_exists($conn, $table)) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM `$table` WHERE id=$id");
    redirect('temples.php');
}

/* Save / Update */
if ($_SERVER['REQUEST_METHOD'] == 'POST' && table_exists($conn, $table)) {
    $allowed_cols = [
        'temple_name','location','address','history','description',
        'president_name','secretary_name','treasurer_name','committee_members',
        'contact_number','event_title','event_details','image','status'
    ];

    $data = [];

    foreach ($allowed_cols as $col) {
        if (temple_column_exists($conn, $table, $col) && isset($_POST[$col])) {
            $data[$col] = trim($_POST[$col]);
        }
    }

    if (temple_column_exists($conn, $table, 'image') && isset($_FILES['image_file']) && $_FILES['image_file']['error'] == 0) {
        $upload_dir = '../uploads/temples/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        $allowed_ext = ['jpg','jpeg','png','webp'];
        $ext = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));

        if (in_array($ext, $allowed_ext)) {
            $file_name = 'temple_' . time() . '_' . rand(1000,9999) . '.' . $ext;
            $target = $upload_dir . $file_name;
            if (move_uploaded_file($_FILES['image_file']['tmp_name'], $target)) {
                $data['image'] = 'uploads/temples/' . $file_name;
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
        if (!empty($set)) $conn->query("UPDATE `$table` SET " . implode(',', $set) . " WHERE id=$id");
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

/* Search */
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';
$where = "WHERE 1";

if ($search != '' && table_exists($conn, $table)) {
    $s = $conn->real_escape_string($search);
    $search_parts = [];

    foreach (['temple_name','location','address','president_name','secretary_name','contact_number','event_title'] as $col) {
        if (temple_column_exists($conn, $table, $col)) {
            $search_parts[] = "`$col` LIKE '%$s%'";
        }
    }

    if (!empty($search_parts)) $where .= " AND (" . implode(" OR ", $search_parts) . ")";
}

if ($status_filter != '' && table_exists($conn, $table) && temple_column_exists($conn, $table, 'status')) {
    $st = $conn->real_escape_string($status_filter);
    $where .= " AND status='$st'";
}

$list_sql = "SELECT * FROM `$table` $where ORDER BY id DESC LIMIT 200";
?>

<style>
.temple-page .card-dark{border-radius:14px;box-shadow:0 8px 22px rgba(0,0,0,.15);}
.temple-page label{font-weight:600;margin-bottom:5px;}
.temple-page .temple-img{width:72px;height:72px;object-fit:cover;border-radius:10px;border:2px solid #d6a94c;}
.temple-page .section-title{font-size:20px;font-weight:700;margin-bottom:15px;}
.temple-page .form-control,.temple-page .form-select{border-radius:9px;}
.temple-page .btn{border-radius:8px;}
.temple-page .table td,.temple-page .table th{vertical-align:middle;white-space:nowrap;}
.temple-page textarea{resize:vertical;}
@media(max-width:768px){.temple-page .page-title{font-size:22px;}.temple-page .table{font-size:13px;}}
</style>

<div class="temple-page">
<h2 class="page-title">Temples Management</h2>

<div class="card-dark mb-4">
    <div class="section-title"><?= isset($edit['id']) ? 'Edit Temple' : 'Add Temple' ?></div>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= e($edit['id'] ?? '') ?>">

        <div class="row">
            <?php if (temple_column_exists($conn, $table, 'temple_name')): ?>
            <div class="col-md-4"><label>Temple Name</label><input type="text" name="temple_name" class="form-control mb-3" required value="<?= e($edit['temple_name'] ?? '') ?>"></div>
            <?php endif; ?>

            <?php if (temple_column_exists($conn, $table, 'location')): ?>
            <div class="col-md-4"><label>Location</label><input type="text" name="location" class="form-control mb-3" value="<?= e($edit['location'] ?? '') ?>"></div>
            <?php endif; ?>

            <?php if (temple_column_exists($conn, $table, 'contact_number')): ?>
            <div class="col-md-4"><label>Contact Number</label><input type="text" name="contact_number" class="form-control mb-3" value="<?= e($edit['contact_number'] ?? '') ?>"></div>
            <?php endif; ?>

            <?php if (temple_column_exists($conn, $table, 'address')): ?>
            <div class="col-md-12"><label>Address</label><textarea name="address" class="form-control mb-3" rows="2"><?= e($edit['address'] ?? '') ?></textarea></div>
            <?php endif; ?>

            <?php if (temple_column_exists($conn, $table, 'history')): ?>
            <div class="col-md-12"><label>Temple History</label><textarea name="history" class="form-control mb-3" rows="4"><?= e($edit['history'] ?? '') ?></textarea></div>
            <?php endif; ?>

            <?php if (temple_column_exists($conn, $table, 'description')): ?>
            <div class="col-md-12"><label>Description</label><textarea name="description" class="form-control mb-3" rows="3"><?= e($edit['description'] ?? '') ?></textarea></div>
            <?php endif; ?>

            <?php if (temple_column_exists($conn, $table, 'president_name')): ?>
            <div class="col-md-4"><label>President Name</label><input type="text" name="president_name" class="form-control mb-3" value="<?= e($edit['president_name'] ?? '') ?>"></div>
            <?php endif; ?>

            <?php if (temple_column_exists($conn, $table, 'secretary_name')): ?>
            <div class="col-md-4"><label>Secretary Name</label><input type="text" name="secretary_name" class="form-control mb-3" value="<?= e($edit['secretary_name'] ?? '') ?>"></div>
            <?php endif; ?>

            <?php if (temple_column_exists($conn, $table, 'treasurer_name')): ?>
            <div class="col-md-4"><label>Treasurer Name</label><input type="text" name="treasurer_name" class="form-control mb-3" value="<?= e($edit['treasurer_name'] ?? '') ?>"></div>
            <?php endif; ?>

            <?php if (temple_column_exists($conn, $table, 'committee_members')): ?>
            <div class="col-md-12"><label>Committee Members</label><textarea name="committee_members" class="form-control mb-3" rows="3"><?= e($edit['committee_members'] ?? '') ?></textarea></div>
            <?php endif; ?>

            <?php if (temple_column_exists($conn, $table, 'event_title')): ?>
            <div class="col-md-4"><label>Event Title</label><input type="text" name="event_title" class="form-control mb-3" value="<?= e($edit['event_title'] ?? '') ?>"></div>
            <?php endif; ?>

            <?php if (temple_column_exists($conn, $table, 'event_details')): ?>
            <div class="col-md-8"><label>Event Details</label><textarea name="event_details" class="form-control mb-3" rows="2"><?= e($edit['event_details'] ?? '') ?></textarea></div>
            <?php endif; ?>

            <?php if (temple_column_exists($conn, $table, 'image')): ?>
            <div class="col-md-4">
                <label>Upload Temple Photo</label>
                <input type="file" name="image_file" class="form-control mb-2" accept="image/*">
                <label>Image Path</label>
                <input type="text" name="image" class="form-control mb-3" value="<?= e($edit['image'] ?? '') ?>" placeholder="uploads/temples/photo.jpg">
            </div>
            <?php endif; ?>

            <?php if (temple_column_exists($conn, $table, 'status')): ?>
            <div class="col-md-4"><label>Status</label><select name="status" class="form-select mb-3"><option value="active" <?= (($edit['status'] ?? '') == 'active') ? 'selected' : '' ?>>Active</option><option value="inactive" <?= (($edit['status'] ?? '') == 'inactive') ? 'selected' : '' ?>>Inactive</option></select></div>
            <?php endif; ?>
        </div>

        <button class="btn btn-gold mt-2"><?= isset($edit['id']) ? 'Update Temple' : 'Add Temple' ?></button>
        <a href="temples.php" class="btn btn-secondary mt-2">Clear</a>
    </form>
</div>

<div class="card-dark mb-4">
    <div class="section-title">Search Temple</div>
    <form method="get">
        <div class="row">
            <div class="col-md-6"><input type="text" name="search" class="form-control mb-2" placeholder="Temple Name / Location / President / Contact / Event" value="<?= e($search) ?>"></div>
            <div class="col-md-3"><select name="status_filter" class="form-select mb-2"><option value="">All Status</option><option value="active" <?= ($status_filter == 'active') ? 'selected' : '' ?>>Active</option><option value="inactive" <?= ($status_filter == 'inactive') ? 'selected' : '' ?>>Inactive</option></select></div>
            <div class="col-md-3"><button class="btn btn-gold w-100 mb-2">Search</button></div>
        </div>
    </form>
</div>

<div class="card-dark">
    <div class="section-title">Temple List</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <?php if (temple_column_exists($conn, $table, 'image')) echo '<th>Photo</th>'; ?>
                    <?php if (temple_column_exists($conn, $table, 'temple_name')) echo '<th>Temple Name</th>'; ?>
                    <?php if (temple_column_exists($conn, $table, 'location')) echo '<th>Location</th>'; ?>
                    <?php if (temple_column_exists($conn, $table, 'president_name')) echo '<th>President</th>'; ?>
                    <?php if (temple_column_exists($conn, $table, 'secretary_name')) echo '<th>Secretary</th>'; ?>
                    <?php if (temple_column_exists($conn, $table, 'contact_number')) echo '<th>Contact</th>'; ?>
                    <?php if (temple_column_exists($conn, $table, 'event_title')) echo '<th>Event</th>'; ?>
                    <?php if (temple_column_exists($conn, $table, 'status')) echo '<th>Status</th>'; ?>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (table_exists($conn, $table)) {
                    $res = $conn->query($list_sql);
                    if ($res && $res->num_rows > 0) {
                        while ($r = $res->fetch_assoc()) {
                ?>
                <tr>
                    <td><?= e($r['id'] ?? '') ?></td>
                    <?php if (temple_column_exists($conn, $table, 'image')): ?>
                    <td><?php if (!empty($r['image'])): ?><img src="../<?= e($r['image']) ?>" class="temple-img" alt="Temple"><?php else: ?><span class="text-muted">No Image</span><?php endif; ?></td>
                    <?php endif; ?>
                    <?php if (temple_column_exists($conn, $table, 'temple_name')) echo '<td>' . e($r['temple_name'] ?? '') . '</td>'; ?>
                    <?php if (temple_column_exists($conn, $table, 'location')) echo '<td>' . e($r['location'] ?? '') . '</td>'; ?>
                    <?php if (temple_column_exists($conn, $table, 'president_name')) echo '<td>' . e($r['president_name'] ?? '') . '</td>'; ?>
                    <?php if (temple_column_exists($conn, $table, 'secretary_name')) echo '<td>' . e($r['secretary_name'] ?? '') . '</td>'; ?>
                    <?php if (temple_column_exists($conn, $table, 'contact_number')) echo '<td>' . e($r['contact_number'] ?? '') . '</td>'; ?>
                    <?php if (temple_column_exists($conn, $table, 'event_title')) echo '<td>' . e($r['event_title'] ?? '') . '</td>'; ?>
                    <?php if (temple_column_exists($conn, $table, 'status')): ?>
                    <td><?php if (($r['status'] ?? '') == 'active'): ?><span class="badge bg-success">Active</span><?php else: ?><span class="badge bg-danger">Inactive</span><?php endif; ?></td>
                    <?php endif; ?>
                    <td>
                        <a class="btn btn-sm btn-warning mb-1" href="?edit=<?= $r['id'] ?>">Edit</a>
                        <?php if (temple_column_exists($conn, $table, 'status')): ?>
                            <?php if (($r['status'] ?? '') == 'active'): ?>
                                <a class="btn btn-sm btn-secondary mb-1" href="?id=<?= $r['id'] ?>&status=inactive">Inactive</a>
                            <?php else: ?>
                                <a class="btn btn-sm btn-success mb-1" href="?id=<?= $r['id'] ?>&status=active">Active</a>
                            <?php endif; ?>
                        <?php endif; ?>
                        <a class="btn btn-sm btn-danger mb-1" onclick="return confirm('Delete this temple?')" href="?delete=<?= $r['id'] ?>">Delete</a>
                    </td>
                </tr>
                <?php
                        }
                    } else {
                        echo '<tr><td colspan="10">No temple record found</td></tr>';
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<?php include 'includes/footer.php'; ?>
