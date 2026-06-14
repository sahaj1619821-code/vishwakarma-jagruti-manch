<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['PHP_SELF']);
$isLoggedIn = isset($_SESSION['site_logged_in']) && $_SESSION['site_logged_in'] === true;
$userName  = $_SESSION['site_user_name'] ?? ($_SESSION['name'] ?? 'User');
$defaultAvatar = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200' viewBox='0 0 200 200'%3E%3Crect width='200' height='200' fill='%23fff3d0'/%3E%3Ccircle cx='100' cy='72' r='38' fill='%23ffbf22'/%3E%3Cpath d='M36 178c8-45 40-68 64-68s56 23 64 68' fill='%23ff8a00'/%3E%3Ctext x='100' y='194' font-size='18' text-anchor='middle' fill='%23700018' font-family='Arial'%3EUser%3C/text%3E%3C/svg%3E";

function vj_header_col_exists($conn, $table, $column) {
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $q = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $q && mysqli_num_rows($q) > 0;
}

function vj_header_table_exists($conn, $table) {
    $table = mysqli_real_escape_string($conn, $table);
    $q = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    return $q && mysqli_num_rows($q) > 0;
}

function vj_header_photo_path($path, $defaultAvatar) {
    $path = trim((string)$path);
    if ($path === '') return '';
    $path = str_replace('\\', '/', $path);
    if (preg_match('#^https?://#i', $path) || strpos($path, 'data:image') === 0) return $path;
    $path = ltrim($path, '/');
    if (file_exists(__DIR__ . '/' . $path)) return $path;
    $base = basename($path);
    foreach (['profile-photo/', 'profile-photp/', 'uploads/matrimonial/', 'uploads/', 'images/'] as $dir) {
        $try = $dir . $base;
        if ($base !== '' && file_exists(__DIR__ . '/' . $try)) return $try;
    }
    return '';
}

function vj_header_find_user_photo($conn, $site_user_id, $defaultAvatar) {
    $site_user_id = (int)$site_user_id;
    if ($site_user_id <= 0) return '';

    if (vj_header_table_exists($conn, 'users')) {
        $cols = [];
        foreach (['photo','profile_photo','image','avatar'] as $c) {
            if (vj_header_col_exists($conn, 'users', $c)) $cols[] = "`$c`";
        }
        if (!empty($cols)) {
            $q = mysqli_query($conn, "SELECT " . implode(',', $cols) . " FROM users WHERE id=$site_user_id LIMIT 1");
            if ($q && mysqli_num_rows($q) > 0) {
                $r = mysqli_fetch_assoc($q);
                foreach (['photo','profile_photo','image','avatar'] as $c) {
                    if (!empty($r[$c])) {
                        $p = vj_header_photo_path($r[$c], $defaultAvatar);
                        if ($p !== '') return $p;
                    }
                }
            }
        }
    }

    if (vj_header_table_exists($conn, 'matrimonial_users')) {
        $where = "";
        if (vj_header_col_exists($conn, 'matrimonial_users', 'user_id')) {
            $where = "user_id=$site_user_id";
        } else {
            $where = "id=$site_user_id";
        }
        $cols = [];
        foreach (['profile_photo','photo','image','avatar'] as $c) {
            if (vj_header_col_exists($conn, 'matrimonial_users', $c)) $cols[] = "`$c`";
        }
        if (!empty($cols)) {
            $q = mysqli_query($conn, "SELECT " . implode(',', $cols) . " FROM matrimonial_users WHERE $where ORDER BY id DESC LIMIT 1");
            if ($q && mysqli_num_rows($q) > 0) {
                $r = mysqli_fetch_assoc($q);
                foreach (['profile_photo','photo','image','avatar'] as $c) {
                    if (!empty($r[$c])) {
                        $p = vj_header_photo_path($r[$c], $defaultAvatar);
                        if ($p !== '') return $p;
                    }
                }
            }
        }
    }

    return '';
}

$userPhoto = '';
$notificationCount = 0;

if ($isLoggedIn) {
    $header_site_user_id = (int)($_SESSION['site_user_id'] ?? $_SESSION['user_id'] ?? 0);
    $header_conn = @mysqli_connect("127.0.0.1", "root", "", "vjm_db", 3307);
    if ($header_conn) {
        mysqli_set_charset($header_conn, "utf8mb4");
        $userPhoto = vj_header_find_user_photo($header_conn, $header_site_user_id, $defaultAvatar);

        mysqli_query($header_conn, "
            CREATE TABLE IF NOT EXISTS matrimonial_notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                receiver_profile_id INT NOT NULL,
                sender_profile_id INT NOT NULL,
                type VARCHAR(50) NOT NULL,
                title VARCHAR(180) NOT NULL,
                message TEXT NULL,
                link VARCHAR(255) NULL,
                is_read TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_receiver_read (receiver_profile_id, is_read),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        if ($header_site_user_id > 0 && vj_header_table_exists($header_conn, 'matrimonial_users')) {
            $profile_q = mysqli_query($header_conn, "SELECT id FROM matrimonial_users WHERE user_id=$header_site_user_id ORDER BY id DESC LIMIT 1");
            if ($profile_q && mysqli_num_rows($profile_q) > 0) {
                $profile_row = mysqli_fetch_assoc($profile_q);
                $header_profile_id = (int)$profile_row['id'];
                $count_q = mysqli_query($header_conn, "SELECT COUNT(*) AS total FROM matrimonial_notifications WHERE receiver_profile_id=$header_profile_id AND is_read=0");
                if ($count_q) {
                    $count_row = mysqli_fetch_assoc($count_q);
                    $notificationCount = (int)($count_row['total'] ?? 0);
                    $_SESSION['notification_count'] = $notificationCount;
                }
            }
        }
    }
}

if ($userPhoto === '') {
    $userPhoto = $defaultAvatar;
}
$_SESSION['site_user_photo'] = $userPhoto;
?>

<!-- Header CSS -->
<link rel="stylesheet" href="/vishwakarma-jagruti-manch/css/header.css">

<!-- Font Awesome Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<header class="vj-header">

    <!-- Top Header Start -->
    <div class="vj-top-header">

        <!-- Logo / Brand Start -->
        <div class="vj-brand">

            <a href="index.php" class="vj-logo-link">
                <img src="images/logo.png" alt="Vishwakarma Jagruti Manch">
            </a>

            <div class="vj-brand-text">
                <h1>
                    <span>VISHWAKARMA</span><br>
                    JAGRUTI MANCH
                </h1>
                <p>एकता • सेवा • संस्कार • समृद्धि</p>
            </div>

        </div>
        <!-- Logo / Brand End -->

        <!-- Contact Info Start -->
        <div class="vj-contact-info">
            <span>
                <i class="fa-solid fa-location-dot"></i>
                Sirohi, Rajasthan, India
            </span>

            <span>
                <i class="fa-solid fa-envelope"></i>
                info@vishwakarmajagrutimanch.com
            </span>

            <span>
                <i class="fa-solid fa-phone"></i>
                8097523298
            </span>
        </div>
        <!-- Contact Info End -->

        <!-- Auth Buttons Start -->
        <div class="vj-auth-buttons">

            <?php if ($isLoggedIn) { ?>

                <!-- Notification Bell -->
                <a href="notifications.php" class="vj-notification-btn" title="Notifications">
                    <i class="fa-solid fa-bell"></i>

                    <?php if ((int)$notificationCount > 0) { ?>
                        <span class="vj-notification-badge">
                            <?= ((int)$notificationCount > 99) ? '99+' : (int)$notificationCount; ?>
                        </span>
                    <?php } ?>
                </a>

                <!-- User Profile -->
                <a href="profile.php" class="vj-user-profile" title="My Profile">
                    <img src="<?= htmlspecialchars($userPhoto); ?>" alt="User Profile" onerror="this.onerror=null;this.src='<?= htmlspecialchars($defaultAvatar); ?>';">
                    <span><?= htmlspecialchars($userName); ?></span>
                </a>

                <!-- Logout -->
                <a href="logout.php" class="vj-logout">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    Logout
                </a>

            <?php } else { ?>

                <!-- Login -->
                <a href="login.php" class="vj-login">
                    <i class="fa-solid fa-user"></i>
                    Login
                </a>

                <!-- Register -->
                <a href="register.php" class="vj-register">
                    <i class="fa-solid fa-user-plus"></i>
                    Register
                </a>

            <?php } ?>

        </div>
        <!-- Auth Buttons End -->

    </div>
    <!-- Top Header End -->


    <!-- 3D Navbar Start -->
    <nav class="vj-navbar">

        <!-- Mobile Toggle -->
        <input type="checkbox" id="vj-menu-toggle">

        <label for="vj-menu-toggle" class="vj-menu-icon">
            <span></span>
            <span></span>
            <span></span>
        </label>

        <!-- Menu Start -->
        <ul class="vj-menu">

            <li>
                <a href="index.php" class="<?= ($currentPage == 'index.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-house"></i>
                    <span>Home</span>
                </a>
            </li>

            <li>
                <a href="temple.php" class="<?= ($currentPage == 'temple.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-gopuram"></i>
                    <span>Temples</span>
                </a>
            </li>

           <li>
    <a href="matrimonial-entry.php" class="<?= (
        $currentPage == 'matrimonial-entry.php' || 
        $currentPage == 'matrimonial.php' || 
        $currentPage == 'plan.php' || 
        $currentPage == 'payment-m.php' || 
        $currentPage == 'register-m-m.php'
    ) ? 'active' : ''; ?>">
        <i class="fa-solid fa-users"></i>
        <span>Matrimonial</span>
    </a>
</li>
            <li>
                <a href="ebook.php" class="<?= ($currentPage == 'ebook.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-book-open"></i>
                    <span>eBooks</span>
                </a>
            </li>

            <li>
    <a href="hostel-booking.php" class="<?= ($currentPage == 'hostel-booking.php') ? 'active' : ''; ?>">
        <i class="fa-solid fa-bed"></i>
        <span>Hostel Booking</span>
    </a>
</li>

            <li>
                <a href="address-book.php" class="<?= ($currentPage == 'address-book.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-address-book"></i>
                    <span>Address Book</span>
                </a>
            </li>

            <li>
                <a href="gallery.php" class="<?= ($currentPage == 'gallery.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-image"></i>
                    <span>Gallery</span>
                </a>
            </li>

            <li>
                <a href="about.php" class="<?= ($currentPage == 'about.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>About Us</span>
                </a>
            </li>

            <li>
                <a href="contact.php" class="<?= ($currentPage == 'contact.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-phone-volume"></i>
                    <span>Contact Us</span>
                </a>
            </li>

        </ul>
        <!-- Menu End -->

    </nav>
    <!-- 3D Navbar End -->

</header>
