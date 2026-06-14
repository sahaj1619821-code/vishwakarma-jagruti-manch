<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* Database connection */
$conn = mysqli_connect("127.0.0.1", "root", "", "vjm_db", 3307);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

/* Safe output function */
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/* Role ke hisab se redirect page */
function roleRedirect($role) {
    $redirects = [
        'super_admin'         => 'dashboard.php',
        'hostel_admin'        => 'hostel-booking.php',
        'matrimonial_admin'   => 'matrimonial.php',
        'ebook_admin'         => 'ebook.php',
        'gallery_admin'       => 'gallery.php',
        'temple_admin'        => 'temple.php',
        'address_admin'       => 'address-book.php',
        'village_surveyor'    => 'village-survey.php'
    ];

    return $redirects[$role] ?? '';
}

$error = "";

/* अगर पहले से login है तो role के हिसाब से redirect */
if (!empty($_SESSION['admin_logged_in']) && !empty($_SESSION['admin_id']) && !empty($_SESSION['admin_role'])) {
    $redirect_page = roleRedirect($_SESSION['admin_role']);

    if ($redirect_page != "") {
        header("Location: " . $redirect_page);
        exit;
    }

    session_unset();
    session_destroy();
}

/* Roles table check/create */
$roles_table = mysqli_query($conn, "SHOW TABLES LIKE 'roles'");

if (!$roles_table || mysqli_num_rows($roles_table) == 0) {
    mysqli_query($conn, "
        CREATE TABLE roles (
            id INT(11) NOT NULL AUTO_INCREMENT,
            role_name VARCHAR(100) NOT NULL,
            role_key VARCHAR(100) NOT NULL UNIQUE,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

/* Default roles insert/update */
mysqli_query($conn, "
    INSERT INTO roles (role_name, role_key, status) VALUES
    ('Super Admin', 'super_admin', 'active'),
    ('Hostel Admin', 'hostel_admin', 'active'),
    ('Matrimonial Admin', 'matrimonial_admin', 'active'),
    ('eBook Admin', 'ebook_admin', 'active'),
    ('Gallery Admin', 'gallery_admin', 'active'),
    ('Temple Admin', 'temple_admin', 'active'),
    ('Address Book Admin', 'address_admin', 'active'),
    ('Village Surveyor', 'village_surveyor', 'active')
    ON DUPLICATE KEY UPDATE role_name = VALUES(role_name), status = VALUES(status)
");

/* Active roles dropdown ke liye */
$roles = mysqli_query($conn, "SELECT * FROM roles WHERE status='active' ORDER BY role_name ASC");

/* Login process only after form submit */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role     = trim($_POST['role'] ?? '');

    if ($username == "" || $password == "" || $role == "") {
        $error = "Please enter username, password and select role";
    } else {

        /* Selected role active hai ya nahi */
        $role_stmt = $conn->prepare("SELECT id FROM roles WHERE role_key = ? AND status = 'active' LIMIT 1");
        if (!$role_stmt) {
            $error = "Role SQL prepare error: " . $conn->error;
        } else {
            $role_stmt->bind_param("s", $role);
            $role_stmt->execute();
            $role_result = $role_stmt->get_result();

            if (!$role_result || $role_result->num_rows == 0) {
                $error = "Selected role active nahi hai";
            }
            $role_stmt->close();
        }

        if ($error == "") {
            /* Username में name / mobile / email तीनों से login */
            $sql = "SELECT id, name, mobile, email, password, role, status 
                    FROM users 
                    WHERE (name = ? OR mobile = ? OR email = ?) 
                    AND role = ? 
                    LIMIT 1";

            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                $error = "SQL prepare error: " . $conn->error;
            } else {
                $stmt->bind_param("ssss", $username, $username, $username, $role);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result && $result->num_rows > 0) {

                    $user = $result->fetch_assoc();
                    $db_password = $user['password'] ?? '';
                    $user_status = (int)($user['status'] ?? 0);

                    if ($user_status !== 1) {
                        $error = "Your account inactive hai. Admin se contact kare.";
                    } elseif (password_verify($password, $db_password) || $password === $db_password) {

                        $redirect_page = roleRedirect($user['role']);

                        if ($redirect_page == "") {
                            $error = "Invalid role permission";
                        } else {
                            session_regenerate_id(true);

                            $_SESSION['admin_logged_in'] = true;
                            $_SESSION['admin_id'] = $user['id'];
                            $_SESSION['admin_name'] = $user['name'];
                            $_SESSION['admin_role'] = $user['role'];

                            header("Location: " . $redirect_page);
                            exit;
                        }

                    } else {
                        $error = "Wrong password";
                    }

                } else {
                    $error = "Wrong username, password or role";
                }

                $stmt->close();
            }
        }
    }

    /* Login ke baad roles query dobara */
    $roles = mysqli_query($conn, "SELECT * FROM roles WHERE status='active' ORDER BY role_name ASC");
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <title>VJM Admin Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            background: radial-gradient(circle at top, #3b0010, #070000 70%);
            display: flex;
            justify-content: center;
            align-items: center;
            color: #fff;
            padding: 15px;
        }

        .card-dark {
            width: 100%;
            max-width: 520px;
            background: #260006;
            border: 1px solid #ff9800;
            border-radius: 22px;
            padding: 38px 36px;
            box-shadow: 0 25px 70px rgba(0,0,0,.45);
        }

        .page-title {
            margin: 0 0 30px;
            color: #ffad3b;
            font-size: 38px;
            font-weight: 800;
            text-align: center;
        }

        .alert {
            padding: 17px 20px;
            border-radius: 7px;
            margin-bottom: 22px;
            font-size: 18px;
            line-height: 1.5;
        }

        .alert-danger {
            background: #f8d0d5;
            color: #79000b;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 9px;
            font-size: 20px;
            font-weight: bold;
        }

        input,
        select {
            width: 100%;
            padding: 16px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,.22);
            background: #fffaf3;
            color: #fff;
            font-size: 18px;
            outline: none;
        }

        select {
            cursor: pointer;
        }

        select option {
            background: #101827;
            color: #fff;
        }

        input:focus,
        select:focus {
            border-color: #ff9800;
            box-shadow: 0 0 0 3px rgba(255,152,0,.18);
        }

        .login-btn {
            width: 100%;
            border: none;
            padding: 17px;
            border-radius: 11px;
            background: linear-gradient(135deg, #ffc328, #ff8a00);
            color: #fff;
            font-size: 19px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 2px;
        }

        .login-btn:hover {
            transform: translateY(-2px);
        }

        .forgot {
            text-align: center;
            margin-top: 23px;
        }

        .forgot a {
            color: #ffad3b;
            text-decoration: none;
            font-size: 20px;
        }

        .forgot a:hover {
            text-decoration: underline;
        }

        @media (max-width: 550px) {
            .card-dark {
                padding: 28px 22px;
            }

            .page-title {
                font-size: 30px;
            }

            label,
            .forgot a {
                font-size: 17px;
            }

            input,
            select {
                font-size: 16px;
                padding: 14px;
            }
        }
    </style>
    <link rel="stylesheet" href="css/style.css?v=10">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<div class="card-dark">

    <h2 class="page-title">VJM Admin Login</h2>

    <?php if ($error != "") { ?>
        <div class="alert alert-danger">
            <?= e($error); ?>
        </div>
    <?php } ?>

    <form method="POST" action="">

        <div class="form-group">
            <label>User Role</label>

            <select name="role" required>
                <option value="">Select Role</option>

                <?php
                if ($roles && mysqli_num_rows($roles) > 0) {
                    while ($roleRow = mysqli_fetch_assoc($roles)) {
                        $selected = "";
                        if (isset($_POST['role']) && $_POST['role'] == $roleRow['role_key']) {
                            $selected = "selected";
                        }
                        ?>
                        <option value="<?= e($roleRow['role_key']); ?>" <?= $selected; ?>>
                            <?= e($roleRow['role_name']); ?>
                        </option>
                        <?php
                    }
                }
                ?>
            </select>
        </div>

        <div class="form-group">
            <label>Username</label>
            <input 
                type="text" 
                name="username" 
                placeholder="Enter name, mobile or email"
                value="<?= e($_POST['username'] ?? ''); ?>"
                required
            >
        </div>

        <div class="form-group">
            <label>Password</label>
            <input 
                type="password" 
                name="password" 
                placeholder="Enter password"
                required
            >
        </div>

        <button type="submit" name="login" class="login-btn">Login</button>

    </form>

    <div class="forgot">
        <a href="forgot-password.php">Forgot Password?</a>
    </div>

</div>

</body>
</html>
