<?php
include 'auth.php';
requireRole(['super_admin']);
include 'includes/header.php';
include 'includes/sidebar.php';
if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('dashboard_table_exists')) {
    function dashboard_table_exists($conn, $table) {
        $table = mysqli_real_escape_string($conn, $table);
        $res = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
        return ($res && mysqli_num_rows($res) > 0);
    }
}

if (!function_exists('dashboard_column_exists')) {
    function dashboard_column_exists($conn, $table, $column) {
        if (!dashboard_table_exists($conn, $table)) {
            return false;
        }
        $table = mysqli_real_escape_string($conn, $table);
        $column = mysqli_real_escape_string($conn, $column);
        $res = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
        return ($res && mysqli_num_rows($res) > 0);
    }
}

if (!function_exists('dashboard_count_rows')) {
    function dashboard_count_rows($conn, $table) {
        if (function_exists('count_rows')) {
            return (int) count_rows($conn, $table);
        }

        if (!dashboard_table_exists($conn, $table)) {
            return 0;
        }

        $table = mysqli_real_escape_string($conn, $table);
        $q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM `$table`");
        if ($q) {
            $r = mysqli_fetch_assoc($q);
            return (int)($r['total'] ?? 0);
        }

        return 0;
    }
}

if (!function_exists('dashboard_matrimonial_count')) {
    function dashboard_matrimonial_count($conn) {
        $table = 'matrimonial_users';

        if (!dashboard_table_exists($conn, $table)) {
            return 0;
        }

        /*
            Admin matrimonial.php me approved/public record:
            verification_status = admin_approved
            status = approved
            verified = 1

            Agar verification_status column available hai to dashboard par approved profiles count show hoga.
            Agar old table me ye column nahi hai to total matrimonial_users count show hoga.
        */
        if (dashboard_column_exists($conn, $table, 'verification_status')) {
            $q = mysqli_query($conn, "
                SELECT COUNT(*) AS total
                FROM `$table`
                WHERE verification_status = 'admin_approved'
            ");
        } elseif (dashboard_column_exists($conn, $table, 'status')) {
            $q = mysqli_query($conn, "
                SELECT COUNT(*) AS total
                FROM `$table`
                WHERE status = 'approved'
            ");
        } else {
            $q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM `$table`");
        }

        if ($q) {
            $r = mysqli_fetch_assoc($q);
            return (int)($r['total'] ?? 0);
        }

        return 0;
    }
}
?>

<h2 class="page-title">Dashboard</h2>

<div class="row g-3">
    <?php
    $stats = [
        ['Users', 'users', 'fa-users', 'users.php'],
        ['Matrimonial', 'matrimonial_users', 'fa-heart', 'matrimonial.php'],
        ['Temples', 'temples', 'fa-gopuram', 'temples.php'],
        ['Hostel Booking', 'hostel_bookings', 'fa-hotel', 'hostel-booking.php'],
        ['eBooks', 'ebooks', 'fa-book', 'ebooks.php'],
        ['Address Book', 'address_book', 'fa-address-book', 'address-book.php'],
        ['Gallery', 'gallery', 'fa-image', 'gallery.php'],
        ['Enquiries', 'enquires', 'fa-envelope', 'enquiries.php']
    ];

    foreach ($stats as $s):
        $title = $s[0];
        $table = $s[1];
        $icon  = $s[2];
        $link  = $s[3];

        if ($title === 'Matrimonial') {
            $total = dashboard_matrimonial_count($conn);
        } else {
            $total = dashboard_count_rows($conn, $table);
        }
    ?>
        <div class="col-md-3 col-6">
            <a href="<?= e($link); ?>" style="text-decoration:none;">
                <div class="stat">
                    <i class="fa <?= e($icon); ?>"></i>
                    <h3><?= e($total); ?></h3>
                    <p><?= e($title); ?></p>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="card-dark mt-4">
    <h4>Quick Manage</h4>

    <a href="users.php" class="btn btn-gold mt-2">
        Users / Role Manage
    </a>

    <a href="matrimonial.php" class="btn btn-gold mt-2">
        Matrimonial Manage
    </a>

    <a href="hostel-booking.php" class="btn btn-gold mt-2">
        Hostel Booking Manage
    </a>

    <a href="ebooks.php" class="btn btn-gold mt-2">
        eBooks Manage
    </a>
</div>

<?php include 'includes/footer.php'; ?>
