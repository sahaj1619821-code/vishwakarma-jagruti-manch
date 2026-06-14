<?php
session_start();

$conn = mysqli_connect("localhost", "root", "", "vjm_db", 3307);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

$booking_id = intval($_GET['booking_id'] ?? 0);

if ($booking_id <= 0) {
    die("Invalid booking ID");
}

$q = mysqli_query($conn, "
    SELECT * FROM hostel_bookings
    WHERE id = '$booking_id'
    LIMIT 1
");

$booking = mysqli_fetch_assoc($q);

if (!$booking) {
    die("Booking not found");
}

$total_amount = floatval($booking['total_amount'] ?? 0);
?>
<!DOCTYPE html>
<html lang="hi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hostel Payment</title>

<link rel="stylesheet" href="css/hostel-booking.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
.payment-page {
    margin: 0 24px;
    background: #f6efe5;
    color: #240006;
    padding: 25px;
    border-left: 1px solid #7d5611;
    border-right: 1px solid #7d5611;
}
.payment-box {
    max-width: 1000px;
    margin: auto;
    display: grid;
    grid-template-columns: 1fr 360px;
    gap: 20px;
}
.pay-card,
.pay-summary {
    background: #fff8ef;
    border: 1px solid #e0d0bc;
    border-radius: 12px;
    padding: 22px;
}
.pay-card h2 {
    color: #650017;
    margin-bottom: 15px;
}
.amount {
    font-size: 34px;
    font-weight: 900;
    color: #650017;
    margin: 12px 0;
}
.qr-box {
    text-align: center;
    background: #fff;
    border: 1px dashed #c89728;
    border-radius: 10px;
    padding: 18px;
    margin: 18px 0;
}
.qr-box img {
    max-width: 230px;
    width: 100%;
}
.pay-form label {
    display: block;
    font-weight: 700;
    margin: 13px 0 6px;
}
.pay-form input,
.pay-form select {
    width: 100%;
    padding: 12px;
    border: 1px solid #d8c9b8;
    border-radius: 7px;
    background: #fff;
}
.submit-btn {
    width: 100%;
    margin-top: 16px;
    padding: 14px;
    border: none;
    border-radius: 7px;
    background: linear-gradient(90deg, #f8d34d, #d98d0c);
    color: #260006;
    font-weight: 900;
    cursor: pointer;
}
.pay-summary {
    background: linear-gradient(180deg, #4b0014, #180006);
    color: #fff;
    border-color: #8b6114;
    height: fit-content;
}
.pay-summary h3 {
    color: #f6d54c;
    margin-bottom: 15px;
}
.summary-row2 {
    display: flex;
    justify-content: space-between;
    border-bottom: 1px solid rgba(255,255,255,.1);
    padding: 10px 0;
}
@media(max-width:850px) {
    .payment-box {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body>

<?php include 'header.php'; ?>
<div class="page-wrapper">
<section class="hero-title">
        <div class="title-icon">
            <i class="fa-solid fa-indian-rupee-sign"></i>
        </div>
        <div>
            <h1>Payment</h1>
            <p>QR scan karke transaction details submit kare</p>
        </div>
    </section>

    <section class="booking-steps">
        <div class="step"><span>1</span><b>Choose Hostel</b></div>
        <i class="fa-solid fa-arrow-right"></i>
        <div class="step"><span>2</span><b>Select Room</b></div>
        <i class="fa-solid fa-arrow-right"></i>
        <div class="step"><span>3</span><b>Choose Dates</b></div>
        <i class="fa-solid fa-arrow-right"></i>
        <div class="step"><span>4</span><b>Confirm Booking</b></div>
        <i class="fa-solid fa-arrow-right"></i>
        <div class="step active"><span>5</span><b>Payment</b></div>
    </section>

    <main class="payment-page">
        <div class="payment-box">

            <div class="pay-card">
                <h2>Payment Details</h2>

                <p><b>Booking ID:</b> #<?= intval($booking['id']); ?></p>
                <p><b>Hostel:</b> <?= htmlspecialchars($booking['hostel_name'] ?? 'Hostel Booking'); ?></p>

                <div class="amount">₹<?= number_format($total_amount); ?></div>

                <div class="qr-box">
                    <p><b>Scan QR Code</b></p>
                    <img src="uploads/payment-qr.png" alt="Payment QR">
                    <p style="font-size:13px;">QR image path: uploads/payment-qr.png</p>
                </div>

                <form class="pay-form" method="POST" action="submit-hostel-payment.php" enctype="multipart/form-data">
                    <input type="hidden" name="booking_id" value="<?= intval($booking['id']); ?>">

                    <label>Payment Method</label>
                    <select name="payment_method" required>
                        <option value="UPI">UPI</option>
                        <option value="PhonePe">PhonePe</option>
                        <option value="Google Pay">Google Pay</option>
                        <option value="Paytm">Paytm</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                    </select>

                    <label>Transaction ID / UTR Number *</label>
                    <input type="text" name="transaction_id" required>

                    <label>Payment Screenshot</label>
                    <input type="file" name="payment_screenshot" accept="image/*">

                    <button class="submit-btn" type="submit">
                        Submit Payment Details <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </form>
            </div>

            <aside class="pay-summary">
                <h3>Booking Summary</h3>
                <div class="summary-row2"><span>Name</span><b><?= htmlspecialchars($booking['name'] ?? ''); ?></b></div>
                <div class="summary-row2"><span>Mobile</span><b><?= htmlspecialchars($booking['mobile'] ?? ''); ?></b></div>
                <div class="summary-row2"><span>Check-in</span><b><?= htmlspecialchars($booking['check_in'] ?? ''); ?></b></div>
                <div class="summary-row2"><span>Check-out</span><b><?= htmlspecialchars($booking['check_out'] ?? ''); ?></b></div>
                <div class="summary-row2"><span>Persons</span><b><?= intval($booking['total_persons'] ?? 0); ?></b></div>
                <div class="summary-row2"><span>Rooms</span><b><?= intval($booking['number_of_rooms'] ?? 1); ?></b></div>
                <div class="summary-row2"><span>Nights</span><b><?= intval($booking['nights'] ?? 1); ?></b></div>
                <div class="summary-row2"><span>Total</span><b>₹<?= number_format($total_amount); ?></b></div>
            </aside>

        </div>
    </main>

    <footer class="footer">
        <p>© 2026 Vishwakarma Jagruti Manch. All Rights Reserved.</p>
        <p>Ekta <span>•</span> Seva <span>•</span> Sanskar <span>•</span> Samriddhi</p>
    </footer>

</div>
</body>
</html>
