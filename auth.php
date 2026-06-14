<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* Database connection */
if (!isset($conn) || !$conn) {
    $conn = mysqli_connect("127.0.0.1", "root", "", "vjm_db", 3307);
    if (!$conn) {
        die("Database connection failed: " . mysqli_connect_error());
    }
    mysqli_set_charset($conn, "utf8mb4");
}

if (!function_exists('table_exists')) {
    function table_exists($conn, $table) {
        $table = $conn->real_escape_string($table);
        $res = $conn->query("SHOW TABLES LIKE '$table'");
        return ($res && $res->num_rows > 0);
    }
}

if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: $url");
        exit;
    }
}

/*
    Admin Authentication + Role Security
    Is file ko har admin page ke top par include kare:
    include 'auth.php';
    requireRole(['role_key']);
*/

if (
    empty($_SESSION['admin_logged_in']) ||
    empty($_SESSION['admin_id']) ||
    empty($_SESSION['admin_role'])
) {
    header("Location: index.php");
    exit;
}

$current_role = $_SESSION['admin_role'] ?? '';

function roleHomePage($role = '') {
    $home_pages = [
        'super_admin'         => 'dashboard.php',
        'hostel_admin'        => 'hostel-booking.php',
        'hostel_manager'      => 'hostel-booking.php',
        'matrimonial_admin'   => 'matrimonial.php',
        'ebook_admin'         => 'ebooks.php',
        'gallery_admin'       => 'gallery.php',
        'temple_admin'        => 'temples.php',
        'address_admin'       => 'address-book.php',
        'enquiry_admin'       => 'enquiries.php',
        'village_surveyor'    => 'village-survey.php'
    ];

    return $home_pages[$role] ?? 'index.php';
}

function hasRole($allowed_roles = []) {
    $current_role = $_SESSION['admin_role'] ?? '';

    if ($current_role === 'super_admin') {
        return true;
    }

    return in_array($current_role, $allowed_roles, true);
}

function requireRole($allowed_roles = []) {
    if (!hasRole($allowed_roles)) {
        $redirect = roleHomePage($_SESSION['admin_role'] ?? '');

        echo "<script>
            alert('Access Denied! Aapko is section ki permission nahi hai.');
            window.location.href='" . addslashes($redirect) . "';
        </script>";
        exit;
    }
}

/*
    Sidebar/dashboard me menu hide karne ke liye:
    <?php if (canOpenPage('gallery.php')) { ?> ... <?php } ?>
*/
function canOpenPage($page_name) {
    $current_role = $_SESSION['admin_role'] ?? '';

    if ($current_role === 'super_admin') {
        return true;
    }

    $page_roles = [
        'dashboard.php'       => ['super_admin'],
        'users.php'           => ['super_admin'],

        'hostel-booking.php'  => ['hostel_admin','hostel_manager'],
        'matrimonial.php'     => ['matrimonial_admin'],
        'ebooks.php'          => ['ebook_admin'],
        'ebook.php'           => ['ebook_admin'],
        'gallery.php'         => ['gallery_admin'],
        'temples.php'         => ['temple_admin'],
        'temple.php'          => ['temple_admin'],
        'address-book.php'    => ['address_admin'],
        'enquiries.php'       => ['enquiry_admin'],
        'village-survey.php'  => ['village_surveyor'],
        'testimonial.php'     => ['village_surveyor']
    ];

    if (!isset($page_roles[$page_name])) {
        return false;
    }

    return in_array($current_role, $page_roles[$page_name], true);
}
?>
