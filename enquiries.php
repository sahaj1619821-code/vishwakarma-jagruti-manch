<?php
include 'auth.php';
requireRole(['enquiry_admin']);
include 'includes/header.php';
include 'includes/sidebar.php';

$table = 'enquires'; // database me table ka exact naam

if (!table_exists($conn, $table)) {
    echo '<div class="alert alert-danger">Table enquires database में नहीं है.</div>';
}

// Edit data get
$edit = [];
if (isset($_GET['edit']) && table_exists($conn, $table)) {
    $id = (int)$_GET['edit'];
    $res = $conn->query("SELECT * FROM `$table` WHERE id=$id");
    if ($res && $res->num_rows > 0) {
        $edit = $res->fetch_assoc();
    }
}

// Delete
if (isset($_GET['delete']) && table_exists($conn, $table)) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM `$table` WHERE id=$id");
    redirect('enquiries.php');
}

// Insert / Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && table_exists($conn, $table)) {

    $name = $conn->real_escape_string($_POST['name'] ?? '');
    $mobile = $conn->real_escape_string($_POST['mobile'] ?? '');
    $email = $conn->real_escape_string($_POST['email'] ?? '');
    $subject = $conn->real_escape_string($_POST['subject'] ?? '');
    $message = $conn->real_escape_string($_POST['message'] ?? '');

    if (isset($_POST['id']) && $_POST['id'] != '') {
        $id = (int)$_POST['id'];

        $conn->query("UPDATE `$table` SET 
            name='$name',
            mobile='$mobile',
            email='$email',
            subject='$subject',
            message='$message'
            WHERE id=$id
        ");
    } else {
        $conn->query("INSERT INTO `$table` 
            (name, mobile, email, subject, message) 
            VALUES 
            ('$name', '$mobile', '$email', '$subject', '$message')
        ");
    }

    redirect('enquiries.php');
}
?>

<h2 class="page-title">Enquiries</h2>

<div class="card-dark mb-4">
    <form method="post">
        <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">

        <div class="row">
            <div class="col-md-4">
                <label>Name</label>
                <input type="text" name="name" class="form-control mb-2"
                       value="<?= htmlspecialchars($edit['name'] ?? '') ?>">
            </div>

            <div class="col-md-4">
                <label>Mobile</label>
                <input type="text" name="mobile" class="form-control mb-2"
                       value="<?= htmlspecialchars($edit['mobile'] ?? '') ?>">
            </div>

            <div class="col-md-4">
                <label>Email</label>
                <input type="email" name="email" class="form-control mb-2"
                       value="<?= htmlspecialchars($edit['email'] ?? '') ?>">
            </div>

            <div class="col-md-4">
                <label>Subject</label>
                <input type="text" name="subject" class="form-control mb-2"
                       value="<?= htmlspecialchars($edit['subject'] ?? '') ?>">
            </div>

            <div class="col-md-12">
                <label>Message</label>
                <textarea name="message" class="form-control mb-2"><?= htmlspecialchars($edit['message'] ?? '') ?></textarea>
            </div>
        </div>

        <button class="btn btn-gold mt-2">Save</button>
        <a href="enquiries.php" class="btn btn-secondary mt-2">Clear</a>
    </form>
</div>

<div class="card-dark">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Mobile</th>
                    <th>Email</th>
                    <th>Subject</th>
                    <th>Message</th>
                    <th>Created At</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                <?php
                if (table_exists($conn, $table)) {
                    $res = $conn->query("SELECT * FROM `$table` ORDER BY id DESC");

                    if ($res && $res->num_rows > 0) {
                        while ($r = $res->fetch_assoc()) {
                ?>
                            <tr>
                                <td><?= htmlspecialchars($r['name'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['mobile'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['email'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['subject'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['message'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['created_at'] ?? '') ?></td>
                                <td>
                                    <a class="btn btn-sm btn-warning" href="?edit=<?= $r['id'] ?>">Edit</a>
                                    <a class="btn btn-sm btn-danger"
                                       href="?delete=<?= $r['id'] ?>"
                                       onclick="return confirm('Delete?')">Delete</a>
                                </td>
                            </tr>
                <?php
                        }
                    } else {
                        echo '<tr><td colspan="7">No enquiry found</td></tr>';
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>