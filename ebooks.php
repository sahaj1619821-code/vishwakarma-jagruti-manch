<?php
include 'auth.php';
requireRole(['ebook_admin']);
include 'includes/header.php';
include 'includes/sidebar.php';

$table = 'ebooks';

if (!function_exists('e')) {
    function e($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
}

function ebook_col($conn, $table, $col){
    if (!table_exists($conn, $table)) return false;
    $table = $conn->real_escape_string($table);
    $col = $conn->real_escape_string($col);
    $r = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
    return ($r && $r->num_rows > 0);
}

if (!table_exists($conn, $table)) {
    echo '<div class="alert alert-danger">ebooks table database में नहीं है।</div>';
}

$uploadPdf = '../uploads/ebooks/';
$uploadCover = '../uploads/ebooks/covers/';

if (!is_dir($uploadPdf)) mkdir($uploadPdf, 0777, true);
if (!is_dir($uploadCover)) mkdir($uploadCover, 0777, true);

$edit = [];

if (isset($_GET['edit']) && table_exists($conn, $table)) {
    $id = (int)$_GET['edit'];
    $res = $conn->query("SELECT * FROM `$table` WHERE id=$id");
    if ($res && $res->num_rows > 0) $edit = $res->fetch_assoc();
}

if (isset($_GET['delete']) && table_exists($conn, $table)) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM `$table` WHERE id=$id");
    redirect('ebooks.php');
}

if (isset($_GET['status'], $_GET['id']) && table_exists($conn, $table)) {
    $id = (int)$_GET['id'];
    $status = $conn->real_escape_string($_GET['status']);
    if (in_array($status, ['active','inactive','pending','rejected'])) {
        $conn->query("UPDATE `$table` SET status='$status' WHERE id=$id");
    }
    redirect('ebooks.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && table_exists($conn, $table)) {

    $data = [
        'title' => $_POST['title'] ?? '',
        'author' => $_POST['author'] ?? '',
        'category' => $_POST['category'] ?? '',
        'description' => $_POST['description'] ?? '',
        'status' => $_POST['status'] ?? 'active'
    ];

    if (!empty($_FILES['pdf_file']['name'])) {
        $ext = strtolower(pathinfo($_FILES['pdf_file']['name'], PATHINFO_EXTENSION));
        if ($ext == 'pdf') {
            $pdfName = 'ebook_' . time() . '_' . rand(1000,9999) . '.pdf';
            move_uploaded_file($_FILES['pdf_file']['tmp_name'], $uploadPdf . $pdfName);
            $data['pdf_file'] = 'uploads/ebooks/' . $pdfName;
        }
    }

    if (!empty($_FILES['cover_image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp'])) {
            $imgName = 'cover_' . time() . '_' . rand(1000,9999) . '.' . $ext;
            move_uploaded_file($_FILES['cover_image']['tmp_name'], $uploadCover . $imgName);
            $data['cover_image'] = 'uploads/ebooks/covers/' . $imgName;
        }
    }

    foreach ($data as $k => $v) {
        if (!ebook_col($conn, $table, $k)) unset($data[$k]);
    }

    if (!empty($_POST['id'])) {
        $id = (int)$_POST['id'];
        $set = [];
        foreach ($data as $k => $v) {
            $set[] = "`$k`='" . $conn->real_escape_string($v) . "'";
        }
        if ($set) $conn->query("UPDATE `$table` SET ".implode(',', $set)." WHERE id=$id");
    } else {
        $keys = array_keys($data);
        $vals = [];
        foreach ($data as $v) $vals[] = "'" . $conn->real_escape_string($v) . "'";
        if ($keys) $conn->query("INSERT INTO `$table` (`".implode('`,`',$keys)."`) VALUES (".implode(',',$vals).")");
    }

    redirect('ebooks.php');
}

$search = $_GET['search'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';
$where = "WHERE 1";

if ($search != '') {
    $s = $conn->real_escape_string($search);
    $where .= " AND (title LIKE '%$s%' OR author LIKE '%$s%' OR category LIKE '%$s%')";
}

if ($status_filter != '') {
    $st = $conn->real_escape_string($status_filter);
    $where .= " AND status='$st'";
}

$list = table_exists($conn, $table)
    ? $conn->query("SELECT * FROM `$table` $where ORDER BY id DESC LIMIT 200")
    : false;
?>

<style>
.ebook-img{width:55px;height:70px;object-fit:cover;border-radius:6px}
</style>

<h2 class="page-title">E-Book Management</h2>

<div class="card-dark mb-4">
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="id" value="<?= e($edit['id'] ?? '') ?>">

<div class="row">
    <div class="col-md-4">
        <label>Book Title</label>
        <input type="text" name="title" class="form-control mb-2" required value="<?= e($edit['title'] ?? '') ?>">
    </div>

    <div class="col-md-4">
        <label>Author</label>
        <input type="text" name="author" class="form-control mb-2" value="<?= e($edit['author'] ?? '') ?>">
    </div>

    <div class="col-md-4">
        <label>Category</label>
        <select name="category" class="form-select mb-2">
            <?php
            $cats = ['Religious Books','Educational Books','Competitive Exam Books','Vishwakarma History','Society Documents','Hostel Documents','Temple Information','Skill Development'];
            foreach($cats as $c){
                $sel = (($edit['category'] ?? '') == $c) ? 'selected' : '';
                echo "<option $sel value='".e($c)."'>".e($c)."</option>";
            }
            ?>
        </select>
    </div>

    <div class="col-md-4">
        <label>PDF File</label>
        <input type="file" name="pdf_file" class="form-control mb-2" accept="application/pdf">
    </div>

    <div class="col-md-4">
        <label>Cover Image</label>
        <input type="file" name="cover_image" class="form-control mb-2" accept="image/*">
    </div>

    <div class="col-md-4">
        <label>Status</label>
        <select name="status" class="form-select mb-2">
            <option value="active" <?= (($edit['status'] ?? '')=='active')?'selected':'' ?>>Active</option>
            <option value="inactive" <?= (($edit['status'] ?? '')=='inactive')?'selected':'' ?>>Inactive</option>
            <option value="pending" <?= (($edit['status'] ?? '')=='pending')?'selected':'' ?>>Pending</option>
            <option value="rejected" <?= (($edit['status'] ?? '')=='rejected')?'selected':'' ?>>Rejected</option>
        </select>
    </div>

    <div class="col-md-12">
        <label>Description</label>
        <textarea name="description" class="form-control mb-2" rows="3"><?= e($edit['description'] ?? '') ?></textarea>
    </div>
</div>

<button class="btn btn-gold mt-2"><?= isset($edit['id']) ? 'Update Book' : 'Add Book' ?></button>
<a href="ebooks.php" class="btn btn-secondary mt-2">Clear</a>
</form>
</div>

<div class="card-dark mb-4">
<form method="get" class="row">
    <div class="col-md-6">
        <input type="text" name="search" class="form-control mb-2" placeholder="Title / Author / Category" value="<?= e($search) ?>">
    </div>
    <div class="col-md-3">
        <select name="status_filter" class="form-select mb-2">
            <option value="">All Status</option>
            <option value="active" <?= $status_filter=='active'?'selected':'' ?>>Active</option>
            <option value="inactive" <?= $status_filter=='inactive'?'selected':'' ?>>Inactive</option>
            <option value="pending" <?= $status_filter=='pending'?'selected':'' ?>>Pending</option>
            <option value="rejected" <?= $status_filter=='rejected'?'selected':'' ?>>Rejected</option>
        </select>
    </div>
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
    <th>Cover</th>
    <th>Title</th>
    <th>Author</th>
    <th>Category</th>
    <th>Downloads</th>
    <th>Status</th>
    <th>Action</th>
</tr>
</thead>
<tbody>
<?php if($list && $list->num_rows > 0): ?>
<?php while($r = $list->fetch_assoc()): ?>
<tr>
    <td><?= e($r['id']) ?></td>
    <td>
        <?php if(!empty($r['cover_image'])): ?>
            <img src="../<?= e($r['cover_image']) ?>" class="ebook-img">
        <?php else: ?>
            No Cover
        <?php endif; ?>
    </td>
    <td><?= e($r['title']) ?></td>
    <td><?= e($r['author']) ?></td>
    <td><?= e($r['category']) ?></td>
    <td><?= e($r['downloads'] ?? 0) ?></td>
    <td><span class="badge bg-info"><?= e($r['status']) ?></span></td>
    <td>
        <a href="?edit=<?= $r['id'] ?>" class="btn btn-sm btn-warning mb-1">Edit</a>

        <?php if(!empty($r['pdf_file'])): ?>
            <a href="../<?= e($r['pdf_file']) ?>" target="_blank" class="btn btn-sm btn-primary mb-1">View PDF</a>
        <?php endif; ?>

        <a href="?id=<?= $r['id'] ?>&status=active" class="btn btn-sm btn-success mb-1">Active</a>
        <a href="?id=<?= $r['id'] ?>&status=inactive" class="btn btn-sm btn-secondary mb-1">Inactive</a>
        <a href="?id=<?= $r['id'] ?>&status=rejected" class="btn btn-sm btn-danger mb-1">Reject</a>

        <a href="?delete=<?= $r['id'] ?>" onclick="return confirm('Delete this book?')" class="btn btn-sm btn-danger mb-1">Delete</a>
    </td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr><td colspan="8">No E-Book found</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>

<?php include 'includes/footer.php'; ?>