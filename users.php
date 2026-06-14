<?php

include 'auth.php';
requireRole(['super_admin']);
include 'includes/header.php';
include 'includes/sidebar.php';

$conn = mysqli_connect("127.0.0.1", "root", "", "vjm_db", 3307);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$message = "";
$error = "";

/* Add User */
if (isset($_POST['add_user'])) {

    $name = trim($_POST['name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');

    if ($name == "" || $mobile == "" || $password == "" || $role == "") {
        $error = "Name, mobile, password and role are required";
    } else {

        $check = $conn->prepare("SELECT id FROM users WHERE mobile = ? OR email = ? LIMIT 1");
        $check->bind_param("ss", $mobile, $email);
        $check->execute();
        $checkResult = $check->get_result();

        if ($checkResult->num_rows > 0) {
            $error = "Mobile or email already exists";
        } else {

            /* Password hash */
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("
                INSERT INTO users 
                (name, mobile, email, password, role, gender, city, state) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "ssssssss",
                $name,
                $mobile,
                $email,
                $hashedPassword,
                $role,
                $gender,
                $city,
                $state
            );

            if ($stmt->execute()) {
                $message = "User created successfully";
            } else {
                $error = "User not created: " . $conn->error;
            }
        }
    }
}

/* Update User */
if (isset($_POST['update_user'])) {

    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');

    if ($id <= 0 || $name == "" || $mobile == "" || $role == "") {
        $error = "Invalid user details";
    } else {

        $check = $conn->prepare("SELECT id FROM users WHERE (mobile = ? OR email = ?) AND id != ? LIMIT 1");
        $check->bind_param("ssi", $mobile, $email, $id);
        $check->execute();
        $checkResult = $check->get_result();

        if ($checkResult->num_rows > 0) {
            $error = "Mobile or email already used by another user";
        } else {

            if ($password != "") {

                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("
                    UPDATE users 
                    SET name = ?, mobile = ?, email = ?, password = ?, role = ?, gender = ?, city = ?, state = ?
                    WHERE id = ?
                ");

                $stmt->bind_param(
                    "ssssssssi",
                    $name,
                    $mobile,
                    $email,
                    $hashedPassword,
                    $role,
                    $gender,
                    $city,
                    $state,
                    $id
                );

            } else {

                $stmt = $conn->prepare("
                    UPDATE users 
                    SET name = ?, mobile = ?, email = ?, role = ?, gender = ?, city = ?, state = ?
                    WHERE id = ?
                ");

                $stmt->bind_param(
                    "sssssssi",
                    $name,
                    $mobile,
                    $email,
                    $role,
                    $gender,
                    $city,
                    $state,
                    $id
                );
            }

            if ($stmt->execute()) {
                $message = "User updated successfully";
            } else {
                $error = "User not updated: " . $conn->error;
            }
        }
    }
}

/* Delete User */
if (isset($_GET['delete'])) {

    $deleteId = intval($_GET['delete']);

    if ($deleteId <= 0) {
        $error = "Invalid user";
    } elseif ($deleteId == $_SESSION['admin_id']) {
        $error = "You cannot delete your own account";
    } else {

        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $deleteId);

        if ($stmt->execute()) {
            $message = "User deleted successfully";
        } else {
            $error = "User not deleted";
        }
    }
}

/* Edit User Fetch */
$editUser = null;

if (isset($_GET['edit'])) {

    $editId = intval($_GET['edit']);

    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $editId);
    $stmt->execute();

    $editResult = $stmt->get_result();

    if ($editResult->num_rows > 0) {
        $editUser = $editResult->fetch_assoc();
    }
}

/* Roles fetch */
$roles = mysqli_query($conn, "SELECT * FROM roles WHERE status='active' ORDER BY role_name ASC");

/* Users fetch */
$users = mysqli_query($conn, "SELECT * FROM users ORDER BY id DESC");
?>

<h2 class="page-title">Admin Users</h2>

<div class="card-dark mb-4">
    <h4><?= !empty($editUser) ? 'Edit Admin User' : 'Create Admin User' ?></h4>

    <?php if ($message != "") { ?>
        <div class="alert alert-success"><?= e($message); ?></div>
    <?php } ?>

    <?php if ($error != "") { ?>
        <div class="alert alert-danger"><?= e($error); ?></div>
    <?php } ?>

    <form method="POST">
        <?php if (!empty($editUser)) { ?>
            <input type="hidden" name="id" value="<?= intval($editUser['id']); ?>">
        <?php } ?>

        <div class="row">
            <div class="col-md-4">
                <label>Name / Username</label>
                <input type="text" name="name" class="form-control mb-2" required value="<?= e($editUser['name'] ?? ''); ?>">
            </div>

            <div class="col-md-4">
                <label>Mobile</label>
                <input type="text" name="mobile" class="form-control mb-2" required value="<?= e($editUser['mobile'] ?? ''); ?>">
            </div>

            <div class="col-md-4">
                <label>Email</label>
                <input type="email" name="email" class="form-control mb-2" value="<?= e($editUser['email'] ?? ''); ?>">
            </div>

            <div class="col-md-4">
                <label>Password <?= !empty($editUser) ? '(blank rakhen to old rahega)' : '' ?></label>
                <input type="password" name="password" class="form-control mb-2" <?= empty($editUser) ? 'required' : ''; ?>>
            </div>

            <div class="col-md-4">
                <label>Fix Role</label>
                <select name="role" class="form-select mb-2" required>
                    <option value="">Select Role</option>
                    <?php
                    if ($roles && mysqli_num_rows($roles) > 0) {
                        mysqli_data_seek($roles, 0);
                        while ($r = mysqli_fetch_assoc($roles)) {
                            $roleKey = $r['role_key'] ?? $r['role_name'] ?? '';
                            $roleName = $r['role_name'] ?? $roleKey;
                            $selected = (($editUser['role'] ?? '') == $roleKey) ? 'selected' : '';
                            echo '<option value="' . e($roleKey) . '" ' . $selected . '>' . e($roleName) . '</option>';
                        }
                    }
                    ?>
                </select>
            </div>

            <div class="col-md-4">
                <label>Gender</label>
                <select name="gender" class="form-select mb-2">
                    <option value="">Select Gender</option>
                    <option value="Male" <?= (($editUser['gender'] ?? '') == 'Male') ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?= (($editUser['gender'] ?? '') == 'Female') ? 'selected' : ''; ?>>Female</option>
                    <option value="Other" <?= (($editUser['gender'] ?? '') == 'Other') ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>

            <div class="col-md-4">
                <label>City</label>
                <input type="text" name="city" class="form-control mb-2" value="<?= e($editUser['city'] ?? ''); ?>">
            </div>

            <div class="col-md-4">
                <label>State</label>
                <input type="text" name="state" class="form-control mb-2" value="<?= e($editUser['state'] ?? 'Rajasthan'); ?>">
            </div>
        </div>

        <?php if (!empty($editUser)) { ?>
            <button type="submit" name="update_user" class="btn btn-gold mt-3">Update User</button>
            <a href="users.php" class="btn btn-secondary mt-3">Cancel</a>
        <?php } else { ?>
            <button type="submit" name="add_user" class="btn btn-gold mt-3">Create User</button>
        <?php } ?>
    </form>
</div>

<div class="card-dark">
    <h4>User List</h4>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Mobile</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>City</th>
                    <th>State</th>
                    <th>Created</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($users && mysqli_num_rows($users) > 0) { ?>
                    <?php while ($user = mysqli_fetch_assoc($users)) { ?>
                        <tr>
                            <td><?= intval($user['id']); ?></td>
                            <td><?= e($user['name']); ?></td>
                            <td><?= e($user['mobile']); ?></td>
                            <td><?= e($user['email']); ?></td>
                            <td><span class="role-badge"><?= e($user['role']); ?></span></td>
                            <td><?= e($user['city']); ?></td>
                            <td><?= e($user['state']); ?></td>
                            <td><?= e($user['created_at']); ?></td>
                            <td>
                                <a href="users.php?edit=<?= intval($user['id']); ?>" class="btn btn-sm btn-warning">Edit Role</a>

                                <?php if ((int)$user['id'] != (int)($_SESSION['admin_id'] ?? 0)) { ?>
                                    <a href="users.php?delete=<?= intval($user['id']); ?>"
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Are you sure you want to delete this user?');">
                                       Delete
                                    </a>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                <?php } else { ?>
                    <tr><td colspan="9">No users found</td></tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
