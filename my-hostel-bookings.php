<?php
session_start();

$conn = mysqli_connect("localhost", "root", "", "vjm_db", 3307);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

if (file_exists("auth.php")) {
    include "auth.php";
}

/*
User login system me agar user_id hai to usse filter kar sakte ho.
Abhi mobile/email ke base par latest booking show karne ke liye all bookings show kar raha hai.
Agar sirf logged-in user ki booking chahiye to user_id column use karna padega.
*/

$query = "SELECT * FROM hostel_bookings ORDER BY id DESC";
$result = mysqli_query($conn, $query);

function statusBadge($status) {
    $status = strtolower($status ?? 'pending');

    if ($status == 'approved' || $status == 'paid') {
        return 'badge-approved';
    }

    if ($status == 'rejected' || $status == 'cancelled' || $status == 'failed') {
        return 'badge-rejected';
    }

    return 'badge-pending';
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Hostel Bookings</title>

<link rel="stylesheet" href="css/hostel-booking.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
.status-page {
    margin: 0 24px;
    background: #f6efe5;
    padding: 25px;
    color: #260006;
}

.status-card {
    background: #fff8ef;
    border: 1px solid #e0d0bc;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 18px;
}

.status-card h3 {
    color: #650017;
    margin-bottom: 10px;
}

.status-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-top: 12px;
}

.status-item {
    background: #fff;
    border: 1px solid #eadac8;
    border-radius: 8px;
    padding: 12px;
}

.status-item small {
    display: block;
    color: #777;
    margin-bottom: 5px;
}

.badge-status {
    display: inline-block;
    padding: 7px 13px;
    border-radius: 20px;
    font-weight: 800;
    font-size: 13px;
}

.badge-pending {
    background: #fff3cd;
    color: #856404;
}

.badge-approved {
    background: #d4edda;
    color: #155724;
}

.badge-rejected {
    background: #f8d7da;
    color: #721c24;
}

.back-btn {
    display: inline-block;
    margin-bottom: 15px;
    background: #650017;
    color: #ffd84d;
    padding: 10px 16px;
    border-radius: 7px;
    text-decoration: none;
    font-weight: bold;
}

@media(max-width: 900px) {
    .status-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media(max-width: 600px) {
    .status-grid {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body>

<div class="page-wrapper">

    <header class="header">
        <div class="logo-area">
            <img src="images/logo.png" alt="Logo">
            <div>
                <h1>VISHWAKARMA</h1>
                <h2>JAGRUTI MANCH</h2>
                <p>एकता • सेवा • संस्कार • समृद्धि</p>
            </div>
        </div>
    </header>

    <nav class="navbar">
        <a href="index.php"><i class="fa-solid fa-house"></i><span>Home</span></a>
        <a href="hostel-booking.php" class="active"><i class="fa-solid fa-bed"></i><span>Hostel Booking</span></a>
        <a href="my-hostel-bookings.php"><i class="fa-solid fa-list-check"></i><span>My Bookings</span></a>
        <a href="logout.php">Logout</a>
    </nav>

    <section class="hero-title">
        <div class="title-icon">
            <i class="fa-solid fa-list-check"></i>
        </div>
        <div>
            <h1>My Hostel Bookings</h1>
            <p>Booking status, payment status aur approval details dekhein</p>
        </div>
    </section>

    <main class="status-page">

        <a href="hostel-booking.php" class="back-btn">
            <i class="fa-solid fa-arrow-left"></i> Back to Hostel Booking
        </a>

        <?php if ($result && mysqli_num_rows($result) > 0) { ?>

            <?php while ($row = mysqli_fetch_assoc($result)) { ?>

                <?php
                $booking_status = $row['booking_status'] ?? 'pending';
                $payment_status = $row['payment_status'] ?? 'pending';
                ?>

                <div class="status-card">
                    <h3>
                        <?= htmlspecialchars($row['hostel_name'] ?? 'Hostel Booking'); ?>
                    </h3>

                    <p>
                        <b>Booking ID:</b> #<?= intval($row['id']); ?>
                    </p>

                    <p>
                        <b>Booking Status:</b>
                        <span class="badge-status <?= statusBadge($booking_status); ?>">
                            <?= htmlspecialchars(ucfirst($booking_status)); ?>
                        </span>

                        &nbsp;

                        <b>Payment Status:</b>
                        <span class="badge-status <?= statusBadge($payment_status); ?>">
                            <?= htmlspecialchars(ucfirst($payment_status)); ?>
                        </span>
                    </p>

                    <div class="status-grid">

                        <div class="status-item">
                            <small>Name</small>
                            <b><?= htmlspecialchars($row['name'] ?? '-'); ?></b>
                        </div>

                        <div class="status-item">
                            <small>Mobile</small>
                            <b><?= htmlspecialchars($row['mobile'] ?? '-'); ?></b>
                        </div>

                        <div class="status-item">
                            <small>Check-in</small>
                            <b>
                                <?= !empty($row['check_in']) ? date("d M Y", strtotime($row['check_in'])) : '-'; ?>
                            </b>
                        </div>

                        <div class="status-item">
                            <small>Check-out</small>
                            <b>
                                <?= !empty($row['check_out']) ? date("d M Y", strtotime($row['check_out'])) : '-'; ?>
                            </b>
                        </div>

                        <div class="status-item">
                            <small>Persons</small>
                            <b><?= intval($row['total_persons'] ?? 0); ?></b>
                        </div>

                        <div class="status-item">
                            <small>Rooms</small>
                            <b><?= intval($row['number_of_rooms'] ?? 1); ?></b>
                        </div>

                        <div class="status-item">
                            <small>Nights</small>
                            <b><?= intval($row['nights'] ?? 1); ?></b>
                        </div>

                        <div class="status-item">
                            <small>Total Amount</small>
                            <b>₹<?= number_format(floatval($row['total_amount'] ?? 0)); ?></b>
                        </div>

                        <div class="status-item">
                            <small>Payment Method</small>
                            <b><?= htmlspecialchars($row['payment_method'] ?? '-'); ?></b>
                        </div>

                        <div class="status-item">
                            <small>Transaction ID</small>
                            <b><?= htmlspecialchars($row['transaction_id'] ?? '-'); ?></b>
                        </div>

                        <div class="status-item">
                            <small>Created</small>
                            <b>
                                <?= !empty($row['created_at']) ? date("d M Y h:i A", strtotime($row['created_at'])) : '-'; ?>
                            </b>
                        </div>

                    </div>

                    <?php if ($booking_status == 'pending') { ?>
                        <p style="margin-top:12px;color:#856404;">
                            <i class="fa-solid fa-clock"></i>
                            Aapki booking pending hai. Admin verify karke approve karega.
                        </p>
                    <?php } elseif ($booking_status == 'approved') { ?>
                        <p style="margin-top:12px;color:#155724;">
                            <i class="fa-solid fa-circle-check"></i>
                            Aapki booking approved ho gayi hai.
                        </p>
                    <?php } elseif ($booking_status == 'rejected') { ?>
                        <p style="margin-top:12px;color:#721c24;">
                            <i class="fa-solid fa-circle-xmark"></i>
                            Aapki booking rejected ho gayi hai.
                        </p>
                    <?php } ?>

                </div>

            <?php } ?>

        <?php } else { ?>

            <div class="status-card">
                <h3>No Booking Found</h3>
                <p>Abhi koi hostel booking available nahi hai.</p>
            </div>

        <?php } ?>

    </main>

</div>

</body>
</html>