<?php
include 'auth.php';

$conn = mysqli_connect("localhost", "root", "", "vjm_db", 3307);
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
mysqli_set_charset($conn, "utf8mb4");

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function hasColumn($conn, $table, $column) {
    $table  = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $q = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $q && mysqli_num_rows($q) > 0;
}

/*
   Image path helper:
   - Gallery photos ko uploads/gallery/ se read karega.
   - Purane records agar uploads/ me save hain to unko bhi read karega.
   - Agar database me full relative path ho jaise uploads/gallery/photo.jpg, wo bhi chalega.
*/
function imgSrc($file, $default = 'images/gallery-default.jpg') {
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
        $candidates[] = $file;
        $candidates[] = 'uploads/gallery/' . $base;
        $candidates[] = 'uploads/' . $base;
        $candidates[] = 'images/' . $base;
    } else {
        $candidates[] = 'uploads/gallery/' . $file;
        $candidates[] = 'uploads/' . $file;
        $candidates[] = 'images/' . $file;
        $candidates[] = $file;
    }

    foreach ($candidates as $path) {
        if (is_file(__DIR__ . '/' . $path)) {
            return $path;
        }
    }

    return $default;
}

/* Aapke database ke hisab se tables:
   gallery: id, album_id, title, image, description, status, created_at
   gallery_albums: id, album_name, description, cover_image, status, created_at
   Is file me category column use nahi kiya gaya hai. */

mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS gallery_albums (
        id INT AUTO_INCREMENT PRIMARY KEY,
        album_name VARCHAR(150) NOT NULL,
        description TEXT DEFAULT NULL,
        cover_image VARCHAR(255) DEFAULT NULL,
        status ENUM('active','inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

mysqli_query($conn, "
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

/* Agar purani gallery table me required column missing ho to add kar de */
if (!hasColumn($conn, 'gallery', 'album_id')) {
    @mysqli_query($conn, "ALTER TABLE gallery ADD album_id INT DEFAULT NULL AFTER id");
}
if (!hasColumn($conn, 'gallery', 'description')) {
    @mysqli_query($conn, "ALTER TABLE gallery ADD description TEXT DEFAULT NULL AFTER image");
}
if (!hasColumn($conn, 'gallery', 'status')) {
    @mysqli_query($conn, "ALTER TABLE gallery ADD status ENUM('active','inactive') DEFAULT 'active' AFTER description");
}
if (!hasColumn($conn, 'gallery', 'created_at')) {
    @mysqli_query($conn, "ALTER TABLE gallery ADD created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
}
if (!hasColumn($conn, 'gallery_albums', 'cover_image')) {
    @mysqli_query($conn, "ALTER TABLE gallery_albums ADD cover_image VARCHAR(255) DEFAULT NULL AFTER description");
}
if (!hasColumn($conn, 'gallery_albums', 'status')) {
    @mysqli_query($conn, "ALTER TABLE gallery_albums ADD status ENUM('active','inactive') DEFAULT 'active' AFTER cover_image");
}
if (!hasColumn($conn, 'gallery_albums', 'created_at')) {
    @mysqli_query($conn, "ALTER TABLE gallery_albums ADD created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
}

/* Default albums first time */
$album_count_q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM gallery_albums");
$album_count_r = $album_count_q ? mysqli_fetch_assoc($album_count_q) : ['total' => 0];
if ((int)$album_count_r['total'] === 0) {
    $defaults = [
        ['Temples', 'Mandir photos and temple programs', 'images/temple.jpg'],
        ['Events', 'Community events and functions', 'images/event.jpg'],
        ['Community Programs', 'Social and community activities', 'images/community.jpg'],
        ['Webinars & Seminars', 'Online meetings and seminars', 'images/webinar.jpg'],
        ['Religious Activities', 'Religious activities and celebrations', 'images/religious.jpg']
    ];
    $stmt = mysqli_prepare($conn, "INSERT INTO gallery_albums (album_name, description, cover_image) VALUES (?, ?, ?)");
    foreach ($defaults as $d) {
        mysqli_stmt_bind_param($stmt, "sss", $d[0], $d[1], $d[2]);
        mysqli_stmt_execute($stmt);
    }
    mysqli_stmt_close($stmt);
}

$message = "";
$message_type = "success";

/* Add New Album */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_album') {
    $album_name  = trim($_POST['album_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $cover_image = trim($_POST['cover_image'] ?? '');
    if ($cover_image === '') {
        $cover_image = 'images/gallery-default.jpg';
    }

    if ($album_name === '') {
        $message = "Album name bharna जरूरी है.";
        $message_type = "error";
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO gallery_albums (album_name, description, cover_image) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sss", $album_name, $description, $cover_image);
        if (mysqli_stmt_execute($stmt)) {
            $message = "New album successfully add ho gaya.";
        } else {
            $message = "Album add nahi hua: " . mysqli_error($conn);
            $message_type = "error";
        }
        mysqli_stmt_close($stmt);
    }
}

/* Upload Photos */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_photos') {
    $title       = trim($_POST['title'] ?? '');
    $album_id    = (int)($_POST['album_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    if ($title === '' || $album_id <= 0 || empty($_FILES['photos']['name'][0])) {
        $message = "Title, album aur photo select करना जरूरी है.";
        $message_type = "error";
    } else {
        $upload_dir = __DIR__ . '/uploads/gallery/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $uploaded = 0;

        foreach ($_FILES['photos']['name'] as $key => $original_name) {
            if ($_FILES['photos']['error'][$key] !== UPLOAD_ERR_OK) {
                continue;
            }

            $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) {
                continue;
            }

            $clean_name = preg_replace('/[^A-Za-z0-9._-]/', '_', pathinfo($original_name, PATHINFO_FILENAME));
            $new_name = 'gallery_' . time() . '_' . rand(1000, 9999) . '_' . $clean_name . '.' . $ext;
            $target = $upload_dir . $new_name;

            if (move_uploaded_file($_FILES['photos']['tmp_name'][$key], $target)) {
                $photo_title = $title;
                if (count($_FILES['photos']['name']) > 1) {
                    $photo_title = $title . ' ' . ($uploaded + 1);
                }
                $stmt = mysqli_prepare($conn, "INSERT INTO gallery (album_id, title, image, description, status) VALUES (?, ?, ?, ?, 'active')");
                mysqli_stmt_bind_param($stmt, "isss", $album_id, $photo_title, $new_name, $description);
                if (mysqli_stmt_execute($stmt)) {
                    $uploaded++;
                }
                mysqli_stmt_close($stmt);
            }
        }

        if ($uploaded > 0) {
            $message = $uploaded . " photo upload ho gayi.";
        } else {
            $message = "Photo upload nahi hui. Sirf JPG, PNG, GIF, WEBP allow hai.";
            $message_type = "error";
        }
    }
}

$album_filter = (int)($_GET['album_id'] ?? 0);
$where = "g.status='active'";
if ($album_filter > 0) {
    $where .= " AND g.album_id = " . $album_filter;
}

$result = mysqli_query($conn, "
    SELECT g.*, a.album_name
    FROM gallery g
    LEFT JOIN gallery_albums a ON a.id = g.album_id
    WHERE $where
    ORDER BY g.id DESC
");

$albums = mysqli_query($conn, "
    SELECT a.*,
           (SELECT COUNT(*) FROM gallery g WHERE g.album_id = a.id AND g.status='active') AS photo_total
    FROM gallery_albums a
    WHERE a.status='active'
    ORDER BY a.id ASC
");

$album_options = mysqli_query($conn, "SELECT id, album_name FROM gallery_albums WHERE status='active' ORDER BY album_name ASC");
$album_options_for_modal = mysqli_query($conn, "SELECT id, album_name FROM gallery_albums WHERE status='active' ORDER BY album_name ASC");

$total_photos_q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM gallery WHERE status='active'");
$total_photos = (int)(mysqli_fetch_assoc($total_photos_q)['total'] ?? 0);

$total_albums_q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM gallery_albums WHERE status='active'");
$total_albums = (int)(mysqli_fetch_assoc($total_albums_q)['total'] ?? 0);

$total_categories = $total_albums;

$album_counts = mysqli_query($conn, "
    SELECT a.id, a.album_name, COUNT(g.id) AS total
    FROM gallery_albums a
    LEFT JOIN gallery g ON g.album_id = a.id AND g.status='active'
    WHERE a.status='active'
    GROUP BY a.id, a.album_name
    ORDER BY total DESC, a.album_name ASC
");

$recent_uploads = mysqli_query($conn, "
    SELECT g.*, a.album_name
    FROM gallery g
    LEFT JOIN gallery_albums a ON a.id = g.album_id
    WHERE g.status='active'
    ORDER BY g.id DESC
    LIMIT 5
");

$selected_album_name = '';
if ($album_filter > 0) {
    $sel_q = mysqli_query($conn, "SELECT album_name FROM gallery_albums WHERE id=$album_filter LIMIT 1");
    $sel_r = $sel_q ? mysqli_fetch_assoc($sel_q) : null;
    $selected_album_name = $sel_r['album_name'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery Management</title>
    <link rel="stylesheet" href="css/gallery.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
<?php include 'header.php'; ?>
<div class="dashboard">
    <aside class="sidebar">
        <h3>GALLERY MANAGEMENT</h3>
        <a href="gallery.php"><i class="fa-solid fa-house"></i> Gallery Dashboard</a>
        <a href="gallery.php#recentPhotos"><i class="fa-solid fa-image"></i> All Photos</a>
        <a href="gallery.php#photoAlbums"><i class="fa-solid fa-folder"></i> Photo Albums</a>
        <a href="#addAlbumModal" class="open-modal"><i class="fa-solid fa-plus"></i> Add New Album</a>
        <a href="#uploadPhotoModal" class="open-modal"><i class="fa-solid fa-upload"></i> Add New Photos</a>
        <a href="#albumCategories"><i class="fa-solid fa-tag"></i> Categories</a>
        <a href="#"><i class="fa-solid fa-video"></i> Video Gallery</a>

        <h3 id="albumCategories">ALBUM CATEGORIES</h3>
        <p><a href="gallery.php">All Categories</a> <span><?= $total_photos; ?></span></p>
        <?php if ($album_counts && mysqli_num_rows($album_counts) > 0) { ?>
            <?php while ($cat = mysqli_fetch_assoc($album_counts)) { ?>
                <p><a href="gallery.php?album_id=<?= (int)$cat['id']; ?>"><?= e($cat['album_name']); ?></a> <span><?= (int)$cat['total']; ?></span></p>
            <?php } ?>
        <?php } ?>
    </aside>

    <main class="main-content">
        <?php if ($message !== '') { ?>
            <div class="alert <?= e($message_type); ?>"><?= e($message); ?></div>
        <?php } ?>

        <div class="page-title">
            <div>
                <h2>Gallery</h2>
                <p>Explore moments, events and activities of Vishwakarma Jagruti Manch.</p>
            </div>
            <div class="page-actions">
                <a href="#addAlbumModal" class="dark-btn open-modal"><i class="fa-solid fa-plus"></i> Add New Album</a>
                <a href="#uploadPhotoModal" class="gold-btn open-modal"><i class="fa-solid fa-upload"></i> Upload Photos</a>
            </div>
        </div>

        <h3 id="photoAlbums">Photo Albums</h3>
        <div class="album-grid">
            <?php while ($album = mysqli_fetch_assoc($albums)) { ?>
                <a class="album-card" href="gallery.php?album_id=<?= (int)$album['id']; ?>">
                    <img src="<?= e(imgSrc($album['cover_image'], 'images/gallery-default.jpg')); ?>" onerror="this.onerror=null;this.src='images/gallery-default.jpg';" alt="<?= e($album['album_name']); ?>">
                    <h4><?= e($album['album_name']); ?></h4>
                    <p><?= (int)$album['photo_total']; ?> Photos • Album</p>
                </a>
            <?php } ?>
        </div>

        <div class="section-head" id="recentPhotos">
            <h3>Recent Photos <?= $selected_album_name !== '' ? ' - ' . e($selected_album_name) : ''; ?></h3>
            <form method="get">
                <select name="album_id" onchange="this.form.submit()">
                    <option value="">All Albums</option>
                    <?php if ($album_options) { while ($opt = mysqli_fetch_assoc($album_options)) { ?>
                        <option value="<?= (int)$opt['id']; ?>" <?= $album_filter === (int)$opt['id'] ? 'selected' : ''; ?>><?= e($opt['album_name']); ?></option>
                    <?php }} ?>
                </select>
            </form>
        </div>

        <div class="photo-grid">
            <?php if ($result && mysqli_num_rows($result) > 0) { ?>
                <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                    <div class="gallery-card">
                        <img src="<?= e(imgSrc($row['image'], 'images/gallery-default.jpg')); ?>" onerror="this.onerror=null;this.src='images/gallery-default.jpg';" alt="<?= e($row['title'] ?: 'Gallery'); ?>">
                        <div class="gallery-card-body">
                            <h4><?= e($row['title']); ?></h4>
                            <p><i class="fa-solid fa-folder"></i> <?= e($row['album_name'] ?: 'No Album'); ?></p>
                        </div>
                    </div>
                <?php } ?>
            <?php } else { ?>
                <div class="empty-box">Abhi is album me photo available nahi hai.</div>
            <?php } ?>
        </div>
    </main>

    <aside class="right-panel">
        <h3>QUICK STATS</h3>
        <p><span><i class="fa-solid fa-image"></i> Total Photos</span> <b><?= $total_photos; ?></b></p>
        <p><span><i class="fa-solid fa-folder"></i> Photo Albums</span> <b><?= $total_albums; ?></b></p>
        <p><span><i class="fa-solid fa-tag"></i> Categories</span> <b><?= $total_categories; ?></b></p>
        <p><span><i class="fa-solid fa-video"></i> Videos</span> <b>0</b></p>

        <h3>RECENT UPLOADS</h3>
        <?php if ($recent_uploads && mysqli_num_rows($recent_uploads) > 0) { ?>
            <?php while ($up = mysqli_fetch_assoc($recent_uploads)) { ?>
                <div class="upload">
                    <img src="<?= e(imgSrc($up['image'], 'images/gallery-default.jpg')); ?>" onerror="this.onerror=null;this.src='images/gallery-default.jpg';" alt="Recent Upload">
                    <div>
                        <b><?= e($up['title']); ?></b>
                        <small><?= e($up['album_name'] ?: 'No Album'); ?></small>
                    </div>
                </div>
            <?php } ?>
        <?php } else { ?>
            <div class="upload empty-upload">No recent uploads</div>
        <?php } ?>
    </aside>
</div>

<div id="addAlbumModal" class="modal-box">
    <div class="modal-content">
        <a href="#" class="close-modal">&times;</a>
        <h3><i class="fa-solid fa-folder-plus"></i> Add New Album</h3>
        <form method="post">
            <input type="hidden" name="action" value="add_album">
            <label>Album Name</label>
            <input type="text" name="album_name" placeholder="Example: Temple Opening Ceremony" required>

            <label>Cover Image Path</label>
            <input type="text" name="cover_image" placeholder="images/temple.jpg या uploads/gallery/download.jpg">

            <label>Description</label>
            <textarea name="description" rows="3" placeholder="Album details"></textarea>

            <button class="gold-btn" type="submit"><i class="fa-solid fa-save"></i> Save Album</button>
        </form>
    </div>
</div>

<div id="uploadPhotoModal" class="modal-box">
    <div class="modal-content">
        <a href="#" class="close-modal">&times;</a>
        <h3><i class="fa-solid fa-cloud-arrow-up"></i> Upload Photos</h3>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload_photos">
            <label>Photo Title</label>
            <input type="text" name="title" placeholder="Example: Community Function" required>

            <label>Album</label>
            <select name="album_id" required>
                <option value="">Select Album</option>
                <?php if ($album_options_for_modal) { while ($opt = mysqli_fetch_assoc($album_options_for_modal)) { ?>
                    <option value="<?= (int)$opt['id']; ?>"><?= e($opt['album_name']); ?></option>
                <?php }} ?>
            </select>

            <label>Description</label>
            <textarea name="description" rows="3" placeholder="Photo details"></textarea>

            <label>Select Photos</label>
            <input type="file" name="photos[]" accept="image/*" multiple required>

            <button class="gold-btn" type="submit"><i class="fa-solid fa-upload"></i> Upload Now</button>
        </form>
    </div>
</div>

<footer>
    © 2026 Vishwakarma Jagruti Manch. All Rights Reserved.
</footer>

</body>
</html>
