<?php
include 'auth.php';
requireRole(['gallery_admin']);
include 'includes/header.php';
include 'includes/sidebar.php';

/*
    Admin Gallery Management
    Database tables as per public gallery.php:
    gallery: id, album_id, title, image, description, status, created_at
    gallery_albums: id, album_name, description, cover_image, status, created_at

    Important:
    - Photo files upload folder: /uploads/gallery/
    - Database me image ke liye sirf file name save hoga, jaise: download.jpg
    - Purane records me agar uploads/gallery/download.jpg save hai to bhi image show hogi.
*/

if (!isset($conn) || !$conn) {
    die("Database connection not found. includes/header.php me \$conn connection check karo.");
}

$conn->set_charset("utf8mb4");

if (!function_exists('redirect')) {
    function redirect($url) {
        echo "<script>window.location.href='" . addslashes($url) . "';</script>";
        exit;
    }
}

function e($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function tableHasColumn($conn, $table, $column) {
    $table  = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $q = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $q && $q->num_rows > 0;
}

function cleanFileName($name) {
    $name = basename((string)$name);
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $base = pathinfo($name, PATHINFO_FILENAME);
    $base = preg_replace('/[^A-Za-z0-9._-]/', '_', $base);
    return time() . '_' . rand(1000, 9999) . '_' . $base . '.' . $ext;
}

/*
    Admin page admin/gallery.php se chalti hai, isliye browser path me ../ lagta hai.
    Ye helper old/new dono type DB value ko support karta hai:
    - download.jpg
    - uploads/gallery/download.jpg
    - images/temple.jpg
*/
function adminImgSrc($file, $default = '../images/gallery-default.jpg') {
    $file = trim((string)$file);
    $file = str_replace('\\', '/', $file);

    if ($file === '') {
        return $default;
    }

    if (preg_match('/^(https?:)?\/\//i', $file) || str_starts_with($file, 'data:image')) {
        return $file;
    }

    $base = basename($file);
    $candidates = [];

    if (strpos($file, '/') !== false) {
        $candidates[] = '../' . ltrim($file, '/');
        $candidates[] = '../uploads/gallery/' . $base;
        $candidates[] = '../uploads/' . $base;
        $candidates[] = '../images/' . $base;
    } else {
        $candidates[] = '../uploads/gallery/' . $file;
        $candidates[] = '../uploads/' . $file;
        $candidates[] = '../images/' . $file;
        $candidates[] = '../' . $file;
    }

    foreach ($candidates as $path) {
        if (is_file(__DIR__ . '/' . $path)) {
            return $path;
        }
    }

    return $default;
}

/* Tables create / repair */
$conn->query("
    CREATE TABLE IF NOT EXISTS gallery_albums (
        id INT AUTO_INCREMENT PRIMARY KEY,
        album_name VARCHAR(150) NOT NULL,
        description TEXT DEFAULT NULL,
        cover_image VARCHAR(255) DEFAULT NULL,
        status ENUM('active','inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$conn->query("
    CREATE TABLE IF NOT EXISTS gallery (
        id INT AUTO_INCREMENT PRIMARY KEY,
        album_id INT DEFAULT NULL,
        title VARCHAR(150) DEFAULT NULL,
        image VARCHAR(255) NOT NULL,
        description TEXT DEFAULT NULL,
        status ENUM('active','inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

if (!tableHasColumn($conn, 'gallery', 'album_id')) {
    @$conn->query("ALTER TABLE gallery ADD album_id INT DEFAULT NULL AFTER id");
}
if (!tableHasColumn($conn, 'gallery', 'description')) {
    @$conn->query("ALTER TABLE gallery ADD description TEXT DEFAULT NULL AFTER image");
}
if (!tableHasColumn($conn, 'gallery', 'status')) {
    @$conn->query("ALTER TABLE gallery ADD status ENUM('active','inactive') DEFAULT 'active' AFTER description");
}
if (!tableHasColumn($conn, 'gallery', 'created_at')) {
    @$conn->query("ALTER TABLE gallery ADD created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
}
if (!tableHasColumn($conn, 'gallery_albums', 'cover_image')) {
    @$conn->query("ALTER TABLE gallery_albums ADD cover_image VARCHAR(255) DEFAULT NULL AFTER description");
}
if (!tableHasColumn($conn, 'gallery_albums', 'status')) {
    @$conn->query("ALTER TABLE gallery_albums ADD status ENUM('active','inactive') DEFAULT 'active' AFTER cover_image");
}
if (!tableHasColumn($conn, 'gallery_albums', 'created_at')) {
    @$conn->query("ALTER TABLE gallery_albums ADD created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
}

$upload_dir = __DIR__ . '/../uploads/gallery/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$msg = '';
$msg_type = 'success';

/* Album Save */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_album'])) {
    $id = (int)($_POST['album_id'] ?? 0);
    $album_name = trim($_POST['album_name'] ?? '');
    $description = trim($_POST['album_description'] ?? '');
    $status = ($_POST['album_status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
    $cover_image = trim($_POST['old_cover'] ?? '');

    if ($album_name === '') {
        $msg = 'Album name bharna जरूरी है.';
        $msg_type = 'danger';
    } else {
        if (!empty($_FILES['cover_image']['name']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));

            if (in_array($ext, $allowed_ext)) {
                $file = cleanFileName($_FILES['cover_image']['name']);

                if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $upload_dir . $file)) {
                    // Public gallery.php filename ko uploads/gallery/ se read kar lega.
                    $cover_image = $file;
                }
            }
        }

        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE gallery_albums SET album_name=?, description=?, cover_image=?, status=? WHERE id=?");
            $stmt->bind_param("ssssi", $album_name, $description, $cover_image, $status, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO gallery_albums (album_name, description, cover_image, status) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $album_name, $description, $cover_image, $status);
        }

        if ($stmt->execute()) {
            redirect('gallery.php?msg=album_saved');
        } else {
            $msg = 'Album save nahi hua: ' . $conn->error;
            $msg_type = 'danger';
        }
        $stmt->close();
    }
}

/* Photo Save */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_photo'])) {
    $id = (int)($_POST['photo_id'] ?? 0);
    $album_id = (int)($_POST['album_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
    $image = trim($_POST['old_image'] ?? '');

    if ($album_id <= 0 || $title === '') {
        $msg = 'Album aur photo title bharna जरूरी है.';
        $msg_type = 'danger';
    } else {
        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

            if (in_array($ext, $allowed_ext)) {
                $file = cleanFileName($_FILES['image']['name']);

                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $file)) {
                    // Public gallery.php filename ko uploads/gallery/ se read kar lega.
                    $image = $file;
                }
            }
        }

        if ($id <= 0 && $image === '') {
            $msg = 'Photo select karna जरूरी है.';
            $msg_type = 'danger';
        } else {
            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE gallery SET album_id=?, title=?, image=?, description=?, status=? WHERE id=?");
                $stmt->bind_param("issssi", $album_id, $title, $image, $description, $status, $id);
            } else {
                $stmt = $conn->prepare("INSERT INTO gallery (album_id, title, image, description, status) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("issss", $album_id, $title, $image, $description, $status);
            }

            if ($stmt->execute()) {
                redirect('gallery.php?msg=photo_saved');
            } else {
                $msg = 'Photo save nahi hui: ' . $conn->error;
                $msg_type = 'danger';
            }
            $stmt->close();
        }
    }
}

/* Delete Photo */
if (isset($_GET['delete_photo'])) {
    $id = (int)$_GET['delete_photo'];
    $stmt = $conn->prepare("DELETE FROM gallery WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    redirect('gallery.php?msg=photo_deleted');
}

/* Delete Album */
if (isset($_GET['delete_album'])) {
    $id = (int)$_GET['delete_album'];

    $check = $conn->prepare("SELECT COUNT(*) AS total FROM gallery WHERE album_id=?");
    $check->bind_param("i", $id);
    $check->execute();
    $total_photos_in_album = (int)($check->get_result()->fetch_assoc()['total'] ?? 0);
    $check->close();

    if ($total_photos_in_album > 0) {
        $msg = 'Is album me photos hain. Pehle photos delete ya dusre album me move karo.';
        $msg_type = 'danger';
    } else {
        $stmt = $conn->prepare("DELETE FROM gallery_albums WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        redirect('gallery.php?msg=album_deleted');
    }
}

/* Edit Fetch */
$edit_photo = [];
$edit_album = [];

if (isset($_GET['edit_photo'])) {
    $id = (int)$_GET['edit_photo'];
    $stmt = $conn->prepare("SELECT * FROM gallery WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows) {
        $edit_photo = $res->fetch_assoc();
    }
    $stmt->close();
}

if (isset($_GET['edit_album'])) {
    $id = (int)$_GET['edit_album'];
    $stmt = $conn->prepare("SELECT * FROM gallery_albums WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows) {
        $edit_album = $res->fetch_assoc();
    }
    $stmt->close();
}

if (isset($_GET['msg'])) {
    $map = [
        'album_saved' => 'Album successfully save ho gaya.',
        'photo_saved' => 'Photo successfully save ho gayi.',
        'photo_deleted' => 'Photo delete ho gayi.',
        'album_deleted' => 'Album delete ho gaya.'
    ];
    $msg = $map[$_GET['msg']] ?? '';
    $msg_type = 'success';
}

/* Filters */
$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status_filter'] ?? '');
$album_filter = trim($_GET['album_filter'] ?? '');

$where = "WHERE 1";
$params = [];
$types = "";

if ($search !== '') {
    $where .= " AND (g.title LIKE ? OR g.description LIKE ? OR a.album_name LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}

if ($status_filter !== '') {
    $where .= " AND g.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($album_filter !== '') {
    $where .= " AND g.album_id = ?";
    $params[] = (int)$album_filter;
    $types .= "i";
}

$albums = $conn->query("
    SELECT a.*,
           (SELECT COUNT(*) FROM gallery g WHERE g.album_id = a.id) AS photo_total
    FROM gallery_albums a
    ORDER BY a.id DESC
");

$albums2 = $conn->query("SELECT id, album_name FROM gallery_albums WHERE status='active' ORDER BY album_name ASC");

$photo_sql = "
    SELECT g.*, a.album_name
    FROM gallery g
    LEFT JOIN gallery_albums a ON g.album_id = a.id
    $where
    ORDER BY g.id DESC
";

$stmt = $conn->prepare($photo_sql);
if ($types !== "") {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$photos = $stmt->get_result();

$total_photos = (int)($conn->query("SELECT COUNT(*) AS total FROM gallery")->fetch_assoc()['total'] ?? 0);
$total_albums = (int)($conn->query("SELECT COUNT(*) AS total FROM gallery_albums")->fetch_assoc()['total'] ?? 0);
$active_photos = (int)($conn->query("SELECT COUNT(*) AS total FROM gallery WHERE status='active'")->fetch_assoc()['total'] ?? 0);
$inactive_photos = (int)($conn->query("SELECT COUNT(*) AS total FROM gallery WHERE status='inactive'")->fetch_assoc()['total'] ?? 0);
?>

<style>
.gallery-admin-wrap{
    color:#fff;
}
.gallery-stats{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:14px;
    margin-bottom:20px;
}
.gallery-stat-card{
    background:#1c0505;
    border:1px solid #5b2200;
    border-radius:12px;
    padding:16px;
}
.gallery-stat-card span{
    display:block;
    color:#ffc328;
    font-weight:700;
    margin-bottom:6px;
}
.gallery-stat-card b{
    font-size:24px;
}
.gallery-img{
    width:74px;
    height:74px;
    object-fit:cover;
    border-radius:9px;
    border:1px solid #7b2d00;
    background:#100;
}
.gallery-box{
    background:#160303;
    border:1px solid #4c1b00;
    padding:18px;
    border-radius:12px;
    margin-bottom:20px;
}
.gallery-box h4{
    color:#ffc328;
    margin-bottom:14px;
}
.badge-active{background:#198754;}
.badge-inactive{background:#dc3545;}
.current-preview{
    margin-top:8px;
}
.current-preview img{
    width:90px;
    height:70px;
    object-fit:cover;
    border-radius:8px;
    border:1px solid #7b2d00;
}
.table td, .table th{
    vertical-align:middle;
}
.action-btns{
    display:flex;
    gap:6px;
    flex-wrap:wrap;
}
.btn-gold{
    background:linear-gradient(145deg,#ffd36a,#f6a400);
    color:#120202;
    border:0;
    font-weight:700;
}
@media(max-width:1000px){
    .gallery-stats{
        grid-template-columns:repeat(2,1fr);
    }
}
@media(max-width:600px){
    .gallery-stats{
        grid-template-columns:1fr;
    }
}
</style>

<div class="gallery-admin-wrap">

<h2 class="page-title">Gallery Management</h2>

<?php if ($msg !== ''): ?>
    <div class="alert alert-<?= e($msg_type) ?>"><?= e($msg) ?></div>
<?php endif; ?>

<div class="gallery-stats">
    <div class="gallery-stat-card">
        <span>Total Photos</span>
        <b><?= $total_photos ?></b>
    </div>
    <div class="gallery-stat-card">
        <span>Active Photos</span>
        <b><?= $active_photos ?></b>
    </div>
    <div class="gallery-stat-card">
        <span>Inactive Photos</span>
        <b><?= $inactive_photos ?></b>
    </div>
    <div class="gallery-stat-card">
        <span>Total Albums</span>
        <b><?= $total_albums ?></b>
    </div>
</div>

<div class="gallery-box">
    <h4><?= isset($edit_album['id']) ? 'Edit Album' : 'Add Album' ?></h4>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="album_id" value="<?= e($edit_album['id'] ?? '') ?>">
        <input type="hidden" name="old_cover" value="<?= e($edit_album['cover_image'] ?? '') ?>">

        <div class="row">
            <div class="col-md-4">
                <label>Album Name</label>
                <input type="text" name="album_name" class="form-control mb-2" required value="<?= e($edit_album['album_name'] ?? '') ?>">
            </div>

            <div class="col-md-4">
                <label>Cover Image</label>
                <input type="file" name="cover_image" accept="image/*" class="form-control mb-2">
                <?php if (!empty($edit_album['cover_image'])): ?>
                    <div class="current-preview">
                        <img src="<?= e(adminImgSrc($edit_album['cover_image'])) ?>" onerror="this.onerror=null;this.src='../images/gallery-default.jpg';">
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-md-4">
                <label>Status</label>
                <select name="album_status" class="form-select mb-2">
                    <option value="active" <?= (($edit_album['status'] ?? 'active')=='active')?'selected':'' ?>>Active</option>
                    <option value="inactive" <?= (($edit_album['status'] ?? '')=='inactive')?'selected':'' ?>>Inactive</option>
                </select>
            </div>

            <div class="col-md-12">
                <label>Description</label>
                <textarea name="album_description" class="form-control mb-2" rows="3"><?= e($edit_album['description'] ?? '') ?></textarea>
            </div>
        </div>

        <button name="save_album" class="btn btn-gold">Save Album</button>
        <a href="gallery.php" class="btn btn-secondary">Clear</a>
    </form>
</div>

<div class="gallery-box">
    <h4><?= isset($edit_photo['id']) ? 'Edit Photo' : 'Add Photo' ?></h4>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="photo_id" value="<?= e($edit_photo['id'] ?? '') ?>">
        <input type="hidden" name="old_image" value="<?= e($edit_photo['image'] ?? '') ?>">

        <div class="row">
            <div class="col-md-4">
                <label>Album</label>
                <select name="album_id" class="form-select mb-2" required>
                    <option value="">Select Album</option>
                    <?php if ($albums2): while($a = $albums2->fetch_assoc()): ?>
                        <option value="<?= (int)$a['id'] ?>" <?= ((int)($edit_photo['album_id'] ?? 0) === (int)$a['id']) ? 'selected' : '' ?>>
                            <?= e($a['album_name']) ?>
                        </option>
                    <?php endwhile; endif; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label>Photo Title</label>
                <input type="text" name="title" class="form-control mb-2" required value="<?= e($edit_photo['title'] ?? '') ?>">
            </div>

            <div class="col-md-4">
                <label>Image</label>
                <input type="file" name="image" accept="image/*" class="form-control mb-2" <?= isset($edit_photo['id']) ? '' : 'required' ?>>
                <?php if (!empty($edit_photo['image'])): ?>
                    <div class="current-preview">
                        <img src="<?= e(adminImgSrc($edit_photo['image'])) ?>" onerror="this.onerror=null;this.src='../images/gallery-default.jpg';">
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-md-4">
                <label>Status</label>
                <select name="status" class="form-select mb-2">
                    <option value="active" <?= (($edit_photo['status'] ?? 'active')=='active')?'selected':'' ?>>Active</option>
                    <option value="inactive" <?= (($edit_photo['status'] ?? '')=='inactive')?'selected':'' ?>>Inactive</option>
                </select>
            </div>

            <div class="col-md-12">
                <label>Description</label>
                <textarea name="description" class="form-control mb-2" rows="3"><?= e($edit_photo['description'] ?? '') ?></textarea>
            </div>
        </div>

        <button name="save_photo" class="btn btn-gold">Save Photo</button>
        <a href="gallery.php" class="btn btn-secondary">Clear</a>
    </form>
</div>

<div class="gallery-box">
    <h4>Search Photo</h4>

    <form method="get">
        <div class="row">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control mb-2" placeholder="Title / Album / Description" value="<?= e($search) ?>">
            </div>

            <div class="col-md-3">
                <select name="album_filter" class="form-select mb-2">
                    <option value="">All Albums</option>
                    <?php
                    $album_filter_list = $conn->query("SELECT id, album_name FROM gallery_albums ORDER BY album_name ASC");
                    if ($album_filter_list):
                    while($af = $album_filter_list->fetch_assoc()):
                    ?>
                        <option value="<?= (int)$af['id'] ?>" <?= ((string)$album_filter === (string)$af['id']) ? 'selected' : '' ?>>
                            <?= e($af['album_name']) ?>
                        </option>
                    <?php endwhile; endif; ?>
                </select>
            </div>

            <div class="col-md-3">
                <select name="status_filter" class="form-select mb-2">
                    <option value="">All Status</option>
                    <option value="active" <?= ($status_filter=='active')?'selected':'' ?>>Active</option>
                    <option value="inactive" <?= ($status_filter=='inactive')?'selected':'' ?>>Inactive</option>
                </select>
            </div>

            <div class="col-md-2">
                <button class="btn btn-gold w-100">Search</button>
            </div>
        </div>
    </form>
</div>

<div class="gallery-box">
    <h4>Albums List</h4>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Cover</th>
                    <th>Album Name</th>
                    <th>Photos</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($albums && $albums->num_rows > 0): ?>
                <?php while($al = $albums->fetch_assoc()): ?>
                    <tr>
                        <td><?= (int)$al['id'] ?></td>
                        <td>
                            <img src="<?= e(adminImgSrc($al['cover_image'] ?? '')) ?>" class="gallery-img" onerror="this.onerror=null;this.src='../images/gallery-default.jpg';">
                        </td>
                        <td><?= e($al['album_name']) ?></td>
                        <td><?= (int)($al['photo_total'] ?? 0) ?></td>
                        <td>
                            <span class="badge <?= ($al['status']=='active')?'bg-success':'bg-danger' ?>">
                                <?= e($al['status']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-btns">
                                <a href="?edit_album=<?= (int)$al['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                <a href="?delete_album=<?= (int)$al['id'] ?>" onclick="return confirm('Delete album? Agar album me photos hain to delete nahi hoga.')" class="btn btn-sm btn-danger">Delete</a>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="6">No album found</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="gallery-box">
    <h4>Photos List</h4>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Photo</th>
                    <th>Title</th>
                    <th>Album</th>
                    <th>Status</th>
                    <th>Image DB Value</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
            <?php if($photos && $photos->num_rows > 0): ?>
                <?php while($p = $photos->fetch_assoc()): ?>
                    <tr>
                        <td><?= (int)$p['id'] ?></td>
                        <td>
                            <img src="<?= e(adminImgSrc($p['image'] ?? '')) ?>" class="gallery-img" onerror="this.onerror=null;this.src='../images/gallery-default.jpg';">
                        </td>
                        <td><?= e($p['title']) ?></td>
                        <td><?= e($p['album_name'] ?: 'No Album') ?></td>
                        <td>
                            <span class="badge <?= ($p['status']=='active')?'bg-success':'bg-danger' ?>">
                                <?= e($p['status']) ?>
                            </span>
                        </td>
                        <td><small><?= e($p['image']) ?></small></td>
                        <td>
                            <div class="action-btns">
                                <a href="?edit_photo=<?= (int)$p['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                <a href="?delete_photo=<?= (int)$p['id'] ?>" onclick="return confirm('Delete photo?')" class="btn btn-sm btn-danger">Delete</a>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="7">No photo found</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div>

<?php
if (isset($stmt) && $stmt instanceof mysqli_stmt) {
    $stmt->close();
}
include 'includes/footer.php';
?>
