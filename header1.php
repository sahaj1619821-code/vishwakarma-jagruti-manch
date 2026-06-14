<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['PHP_SELF']);

/*
Login successful hone ke baad login.php me ye session set hone chahiye:

$_SESSION['site_logged_in'] = true;
$_SESSION['site_user_id'] = $user['id'];
$_SESSION['site_user_name'] = $user['name'];
$_SESSION['site_user_photo'] = $user['photo'] ?? 'images/user.png';
*/

$isLoggedIn = isset($_SESSION['site_logged_in']) && $_SESSION['site_logged_in'] === true;

$userName  = $_SESSION['site_user_name'] ?? 'User';
$userPhoto = $_SESSION['site_user_photo'] ?? 'images/user.png';
$notificationCount = $_SESSION['notification_count'] ?? 0;
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
                    <img src="<?= htmlspecialchars($userPhoto); ?>" alt="User Profile">
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
