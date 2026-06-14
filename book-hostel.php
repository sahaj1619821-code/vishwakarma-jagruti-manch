<?php
session_start();

$conn = mysqli_connect("localhost", "root", "", "vjm_db", 3307);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

if (file_exists("auth.php")) {
    include "auth.php";
}

$hostel_id = intval($_GET['id'] ?? 0);
$check_in = $_GET['check_in'] ?? date('Y-m-d');
$check_out = $_GET['check_out'] ?? date('Y-m-d', strtotime('+1 day'));
$total_persons = intval($_GET['total_persons'] ?? 1);

if ($total_persons < 1) {
    $total_persons = 1;
}

if ($hostel_id <= 0) {
    die("Invalid hostel selected");
}

$hostel_q = mysqli_query($conn, "
    SELECT * FROM hostels 
    WHERE id = '$hostel_id' 
    AND status = 'active'
    LIMIT 1
");

$hostel = mysqli_fetch_assoc($hostel_q);

if (!$hostel) {
    die("Hostel not found or inactive");
}

try {
    $date1 = new DateTime($check_in);
    $date2 = new DateTime($check_out);
    $nights = $date1->diff($date2)->days;

    if ($nights < 1) {
        $nights = 1;
    }
} catch (Exception $e) {
    $check_in = date('Y-m-d');
    $check_out = date('Y-m-d', strtotime('+1 day'));
    $nights = 1;
}

$price = floatval($hostel['price_per_day'] ?? 0);
$total_amount = $price * $nights;

$imgName = "";
if (!empty($hostel['hostel_image'])) {
    $imgName = basename($hostel['hostel_image']);
} elseif (!empty($hostel['photo'])) {
    $imgName = basename($hostel['photo']);
}

$imagePath = "images/hostel-hero.jpg";
if ($imgName != "") {
    $possiblePath = "uploads/hostels/" . $imgName;
    if (file_exists($possiblePath)) {
        $imagePath = $possiblePath;
    }
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Book Hostel - Vishwakarma Jagruti Manch</title>

<link rel="stylesheet" href="css/hostel-booking.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
.booking-page-box {
    margin: 0 24px;
    background: #f6efe5;
    color: #210007;
    padding: 20px;
    border-left: 1px solid #7d5611;
    border-right: 1px solid #7d5611;
}

.booking-grid {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 20px;
}

.booking-card,
.form-card,
.summary-card-custom {
    background: #fff8ef;
    border: 1px solid #e1d1bd;
    border-radius: 10px;
    padding: 18px;
}

.booking-card img {
    width: 100%;
    max-height: 260px;
    object-fit: cover;
    border-radius: 8px;
    margin-bottom: 15px;
}

.booking-card h2,
.form-card h2 {
    color: #4b0011;
    margin-bottom: 14px;
}

.booking-card p {
    margin: 8px 0;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 14px 18px;
}

.form-card label {
    display: block;
    font-weight: 700;
    font-size: 13px;
    margin-bottom: 6px;
}

.form-card input,
.form-card textarea,
.form-card select {
    width: 100%;
    border: 1px solid #d8c9b8;
    border-radius: 6px;
    padding: 12px;
    outline: none;
    background: #fff;
    font-size: 15px;
}

.form-card textarea {
    min-height: 95px;
    resize: vertical;
}

.full {
    grid-column: 1 / -1;
}

.confirm-btn {
    width: 100%;
    margin-top: 16px;
    padding: 14px;
    border: none;
    border-radius: 7px;
    background: linear-gradient(90deg, #f8d34d, #d98d0c);
    color: #260006;
    font-weight: 800;
    cursor: pointer;
    font-size: 16px;
}

.back-btn {
    display: inline-block;
    margin-bottom: 12px;
    color: #650017;
    font-weight: 700;
}

.summary-card-custom {
    background: linear-gradient(180deg, #4b0014, #180006);
    color: #fff;
    border-color: #8b6114;
    height: fit-content;
    position: sticky;
    top: 15px;
}

.summary-card-custom h3 {
    color: #f6d54c;
    margin-bottom: 15px;
}

.summary-line {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    padding: 11px 0;
    border-bottom: 1px solid rgba(255,255,255,0.08);
}

.summary-line i {
    color: #f6c63b;
    margin-right: 7px;
}

.summary-total b {
    font-size: 24px;
    color: #fff;
}

@media(max-width: 900px) {
    .booking-grid {
        grid-template-columns: 1fr;
    }
    .form-grid {
        grid-template-columns: 1fr;
    }
    .summary-card-custom {
        position: static;
    }
}
</style>

<script>
function calculateBookingAmount() {
    const pricePerDay = parseFloat(document.getElementById("price_per_day").value || 0);
    const checkIn = document.getElementById("check_in").value;
    const checkOut = document.getElementById("check_out").value;
    const rooms = parseInt(document.getElementById("number_of_rooms").value || 1);

    let nights = 1;

    if (checkIn && checkOut) {
        const d1 = new Date(checkIn);
        const d2 = new Date(checkOut);
        const diff = d2 - d1;

        if (diff > 0) {
            nights = Math.ceil(diff / (1000 * 60 * 60 * 24));
        }
    }

    const total = pricePerDay * nights * rooms;

    document.getElementById("nights").value = nights;
    document.getElementById("total_amount").value = total;

    document.getElementById("summary_check_in").innerText = formatDate(checkIn);
    document.getElementById("summary_check_out").innerText = formatDate(checkOut);
    document.getElementById("summary_nights").innerText = nights + " Nights";
    document.getElementById("summary_rooms").innerText = rooms + " Room(s)";
    document.getElementById("summary_total").innerText = "₹" + total.toLocaleString("en-IN");
}

function formatDate(dateValue) {
    if (!dateValue) return "-";
    const d = new Date(dateValue);
    return d.toLocaleDateString("en-GB", {
        day: "2-digit",
        month: "short",
        year: "numeric"
    });
}

window.addEventListener("load", calculateBookingAmount);
</script>
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

        <div class="header-info">
            <span><i class="fa-solid fa-location-dot"></i> Sirohi, Rajasthan, India</span>
            <span><i class="fa-solid fa-envelope"></i> info@vishwakarmajagrutimanch.com</span>
            <span><i class="fa-solid fa-phone"></i> 8097523298</span>
        </div>
    </header>

    <nav class="navbar">
        <a href="index.php"><i class="fa-solid fa-house"></i><span>Home</span></a>
        <a href="temple.php"><i class="fa-solid fa-gopuram"></i><span>Temples</span></a>
        <a href="plan.php"><i class="fa-solid fa-users"></i><span>Matrimonial</span></a>
        <a href="ebook.php"><i class="fa-solid fa-book-open"></i><span>eBooks</span></a>
        <a href="hostel-booking.php" class="active"><i class="fa-solid fa-bed"></i><span>Hostel Booking</span></a>
        <a href="address-book.php"><i class="fa-solid fa-address-book"></i><span>Address Book</span></a>
        <a href="gallery.php"><i class="fa-solid fa-image"></i><span>Gallery</span></a>
        <a href="about.php"><i class="fa-solid fa-circle-info"></i><span>About Us</span></a>
        <a href="contact.php"><i class="fa-solid fa-phone-volume"></i><span>Contact Us</span></a>
        <a href="logout.php">Logout</a>
    </nav>

    <section class="hero-title">
        <div class="title-icon">
            <i class="fa-solid fa-bed"></i>
        </div>
        <div>
            <h1>Hostel Booking</h1>
            <p>Confirm your hostel booking request</p>
        </div>
    </section>

    <section class="booking-steps">
        <div class="step"><span>1</span><b>Choose Hostel</b></div>
        <i class="fa-solid fa-arrow-right"></i>
        <div class="step active"><span>2</span><b>Select Room</b></div>
        <i class="fa-solid fa-arrow-right"></i>
        <div class="step active"><span>3</span><b>Choose Dates</b></div>
        <i class="fa-solid fa-arrow-right"></i>
        <div class="step active"><span>4</span><b>Confirm Booking</b></div>
        <i class="fa-solid fa-arrow-right"></i>
        <div class="step"><span>5</span><b>Payment</b></div>
    </section>

    <section class="booking-page-box">

        <a class="back-btn" href="hostel-booking.php">
            <i class="fa-solid fa-arrow-left"></i> Back to Hostel Search
        </a>

        <div class="booking-grid">

            <div>
                <div class="booking-card">
                    <img src="<?= htmlspecialchars($imagePath); ?>" alt="<?= htmlspecialchars($hostel['hostel_name']); ?>">

                    <h2><?= htmlspecialchars($hostel['hostel_name']); ?></h2>

                    <p>
                        <i class="fa-solid fa-location-dot"></i>
                        <?= htmlspecialchars($hostel['address'] ?? ''); ?>,
                        <?= htmlspecialchars($hostel['city'] ?? ''); ?>,
                        <?= htmlspecialchars($hostel['state'] ?? ''); ?>
                    </p>

                    <p><b>Manager:</b> <?= htmlspecialchars($hostel['manager_name'] ?? ''); ?></p>
                    <p><b>Mobile:</b> <?= htmlspecialchars($hostel['manager_mobile'] ?? ''); ?></p>
                    <p><b>Description:</b> <?= htmlspecialchars($hostel['description'] ?? ''); ?></p>
                    <p><b>Rules:</b> <?= htmlspecialchars($hostel['rules'] ?? ''); ?></p>
                    <p><b>Available Rooms:</b> <?= intval($hostel['available_rooms'] ?? 0); ?></p>
                    <p><b>Price Per Day:</b> ₹<?= number_format($price); ?></p>
                </div>

                <div class="form-card" style="margin-top:20px;">
                    <h2>Guest Details</h2>

                    <form method="POST" action="save-hostel-booking.php">
                        <input type="hidden" name="hostel_id" value="<?= intval($hostel['id']); ?>">
                        <input type="hidden" name="hostel_name" value="<?= htmlspecialchars($hostel['hostel_name']); ?>">
                        <input type="hidden" name="room_type" value="Standard Room">
                        <input type="hidden" name="rent" id="price_per_day" value="<?= htmlspecialchars($price); ?>">
                        <input type="hidden" name="city" value="<?= htmlspecialchars($hostel['city'] ?? ''); ?>">
                        <input type="hidden" name="nights" id="nights" value="<?= intval($nights); ?>">
                        <input type="hidden" name="total_amount" id="total_amount" value="<?= htmlspecialchars($total_amount); ?>">

                        <div class="form-grid">
                            <div>
                                <label>Name *</label>
                                <input type="text" name="name" required>
                            </div>

                            <div>
                                <label>Mobile *</label>
                                <input type="text" name="mobile" required>
                            </div>

                            <div>
                                <label>Email</label>
                                <input type="email" name="email">
                            </div>

                            <div>
                                <label>Total Persons *</label>
                                <input type="number" name="total_persons" value="<?= intval($total_persons); ?>" min="1" required>
                            </div>

                            <div>
                                <label>Number of Rooms *</label>
                                <input type="number" name="number_of_rooms" id="number_of_rooms" value="1" min="1" max="<?= intval($hostel['available_rooms'] ?? 1); ?>" onchange="calculateBookingAmount()" onkeyup="calculateBookingAmount()" required>
                            </div>

                            <div>
                                <label>Room Type</label>
                                <select name="room_type_select" disabled>
                                    <option>Standard Room</option>
                                </select>
                            </div>

                            <div>
                                <label>Check-in Date *</label>
                                <input type="date" name="check_in" id="check_in" value="<?= htmlspecialchars($check_in); ?>" onchange="calculateBookingAmount()" required>
                            </div>

                            <div>
                                <label>Check-out Date *</label>
                                <input type="date" name="check_out" id="check_out" value="<?= htmlspecialchars($check_out); ?>" onchange="calculateBookingAmount()" required>
                            </div>

                            <div>
                                <label>Check-in Time *</label>
                                <input type="time" name="check_in_time" value="12:00" required>
                            </div>

                            <div>
                                <label>Check-out Time *</label>
                                <input type="time" name="check_out_time" value="10:00" required>
                            </div>

                            <div class="full">
                                <label>Message</label>
                                <textarea name="message" placeholder="Any special request"></textarea>
                            </div>
                        </div>

                        <button class="confirm-btn" type="submit">
                            Confirm Booking Request <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </form>
                </div>
            </div>

            <aside class="summary-card-custom">
                <h3><i class="fa-solid fa-calendar-check"></i> Booking Summary</h3>

                <div class="summary-line">
                    <span><i class="fa-regular fa-calendar-days"></i> Check-in</span>
                    <b id="summary_check_in"><?= date("d M Y", strtotime($check_in)); ?></b>
                </div>

                <div class="summary-line">
                    <span><i class="fa-regular fa-calendar-days"></i> Check-out</span>
                    <b id="summary_check_out"><?= date("d M Y", strtotime($check_out)); ?></b>
                </div>

                <div class="summary-line">
                    <span><i class="fa-solid fa-users"></i> Guests</span>
                    <b><?= intval($total_persons); ?> Guests</b>
                </div>

                <div class="summary-line">
                    <span><i class="fa-solid fa-calendar-day"></i> Nights</span>
                    <b id="summary_nights"><?= intval($nights); ?> Nights</b>
                </div>

                <div class="summary-line">
                    <span><i class="fa-solid fa-door-open"></i> Rooms</span>
                    <b id="summary_rooms">1 Room(s)</b>
                </div>

                <div class="summary-line">
                    <span><i class="fa-solid fa-clock"></i> Time</span>
                    <b>12:00 PM - 10:00 AM</b>
                </div>

                <div class="summary-line">
                    <span><i class="fa-solid fa-bed"></i> Room</span>
                    <b>Standard Room</b>
                </div>

                <div class="summary-line">
                    <span><i class="fa-solid fa-indian-rupee-sign"></i> Price / Day</span>
                    <b>₹<?= number_format($price); ?></b>
                </div>

                <div class="summary-line summary-total">
                    <span>Total Amount</span>
                    <b id="summary_total">₹<?= number_format($total_amount); ?></b>
                </div>
            </aside>

        </div>
    </section>

    <footer class="footer">
        <p>© 2026 Vishwakarma Jagruti Manch. All Rights Reserved.</p>
        <p>Ekta <span>•</span> Seva <span>•</span> Sanskar <span>•</span> Samriddhi</p>
    </footer>

</div>

</body>
</html>
<?php
session_start();

$conn = mysqli_connect("localhost", "root", "", "vjm_db", 3307);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

if (file_exists("auth.php")) {
    include "auth.php";
}

$hostel_id = intval($_GET['id'] ?? 0);
$check_in = $_GET['check_in'] ?? date('Y-m-d');
$check_out = $_GET['check_out'] ?? date('Y-m-d', strtotime('+1 day'));
$total_persons = intval($_GET['total_persons'] ?? 1);

if ($total_persons < 1) {
    $total_persons = 1;
}

if ($hostel_id <= 0) {
    die("Invalid hostel selected");
}

$hostel_q = mysqli_query($conn, "
    SELECT * FROM hostels 
    WHERE id = '$hostel_id' 
    AND status = 'active'
    LIMIT 1
");

$hostel = mysqli_fetch_assoc($hostel_q);

if (!$hostel) {
    die("Hostel not found or inactive");
}

try {
    $date1 = new DateTime($check_in);
    $date2 = new DateTime($check_out);
    $nights = $date1->diff($date2)->days;

    if ($nights < 1) {
        $nights = 1;
    }
} catch (Exception $e) {
    $check_in = date('Y-m-d');
    $check_out = date('Y-m-d', strtotime('+1 day'));
    $nights = 1;
}

$price = floatval($hostel['price_per_day'] ?? 0);
$total_amount = $price * $nights;

$imgName = "";
if (!empty($hostel['hostel_image'])) {
    $imgName = basename($hostel['hostel_image']);
} elseif (!empty($hostel['photo'])) {
    $imgName = basename($hostel['photo']);
}

$imagePath = "images/hostel-hero.jpg";
if ($imgName != "") {
    $possiblePath = "uploads/hostels/" . $imgName;
    if (file_exists($possiblePath)) {
        $imagePath = $possiblePath;
    }
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Book Hostel - Vishwakarma Jagruti Manch</title>

<link rel="stylesheet" href="css/hostel-booking.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
.booking-page-box {
    margin: 0 24px;
    background: #f6efe5;
    color: #210007;
    padding: 20px;
    border-left: 1px solid #7d5611;
    border-right: 1px solid #7d5611;
}

.booking-grid {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 20px;
}

.booking-card,
.form-card,
.summary-card-custom {
    background: #fff8ef;
    border: 1px solid #e1d1bd;
    border-radius: 10px;
    padding: 18px;
}

.booking-card img {
    width: 100%;
    max-height: 260px;
    object-fit: cover;
    border-radius: 8px;
    margin-bottom: 15px;
}

.booking-card h2,
.form-card h2 {
    color: #4b0011;
    margin-bottom: 14px;
}

.booking-card p {
    margin: 8px 0;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 14px 18px;
}

.form-card label {
    display: block;
    font-weight: 700;
    font-size: 13px;
    margin-bottom: 6px;
}

.form-card input,
.form-card textarea,
.form-card select {
    width: 100%;
    border: 1px solid #d8c9b8;
    border-radius: 6px;
    padding: 12px;
    outline: none;
    background: #fff;
    font-size: 15px;
}

.form-card textarea {
    min-height: 95px;
    resize: vertical;
}

.full {
    grid-column: 1 / -1;
}

.confirm-btn {
    width: 100%;
    margin-top: 16px;
    padding: 14px;
    border: none;
    border-radius: 7px;
    background: linear-gradient(90deg, #f8d34d, #d98d0c);
    color: #260006;
    font-weight: 800;
    cursor: pointer;
    font-size: 16px;
}

.back-btn {
    display: inline-block;
    margin-bottom: 12px;
    color: #650017;
    font-weight: 700;
}

.summary-card-custom {
    background: linear-gradient(180deg, #4b0014, #180006);
    color: #fff;
    border-color: #8b6114;
    height: fit-content;
    position: sticky;
    top: 15px;
}

.summary-card-custom h3 {
    color: #f6d54c;
    margin-bottom: 15px;
}

.summary-line {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    padding: 11px 0;
    border-bottom: 1px solid rgba(255,255,255,0.08);
}

.summary-line i {
    color: #f6c63b;
    margin-right: 7px;
}

.summary-total b {
    font-size: 24px;
    color: #fff;
}

@media(max-width: 900px) {
    .booking-grid {
        grid-template-columns: 1fr;
    }
    .form-grid {
        grid-template-columns: 1fr;
    }
    .summary-card-custom {
        position: static;
    }
}
</style>

<script>
function calculateBookingAmount() {
    const pricePerDay = parseFloat(document.getElementById("price_per_day").value || 0);
    const checkIn = document.getElementById("check_in").value;
    const checkOut = document.getElementById("check_out").value;
    const rooms = parseInt(document.getElementById("number_of_rooms").value || 1);

    let nights = 1;

    if (checkIn && checkOut) {
        const d1 = new Date(checkIn);
        const d2 = new Date(checkOut);
        const diff = d2 - d1;

        if (diff > 0) {
            nights = Math.ceil(diff / (1000 * 60 * 60 * 24));
        }
    }

    const total = pricePerDay * nights * rooms;

    document.getElementById("nights").value = nights;
    document.getElementById("total_amount").value = total;

    document.getElementById("summary_check_in").innerText = formatDate(checkIn);
    document.getElementById("summary_check_out").innerText = formatDate(checkOut);
    document.getElementById("summary_nights").innerText = nights + " Nights";
    document.getElementById("summary_rooms").innerText = rooms + " Room(s)";
    document.getElementById("summary_total").innerText = "₹" + total.toLocaleString("en-IN");
}

function formatDate(dateValue) {
    if (!dateValue) return "-";
    const d = new Date(dateValue);
    return d.toLocaleDateString("en-GB", {
        day: "2-digit",
        month: "short",
        year: "numeric"
    });
}

window.addEventListener("load", calculateBookingAmount);
</script>
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

        <div class="header-info">
            <span><i class="fa-solid fa-location-dot"></i> Sirohi, Rajasthan, India</span>
            <span><i class="fa-solid fa-envelope"></i> info@vishwakarmajagrutimanch.com</span>
            <span><i class="fa-solid fa-phone"></i> 8097523298</span>
        </div>
    </header>

    <nav class="navbar">
        <a href="index.php"><i class="fa-solid fa-house"></i><span>Home</span></a>
        <a href="temple.php"><i class="fa-solid fa-gopuram"></i><span>Temples</span></a>
        <a href="login-m.php"><i class="fa-solid fa-users"></i><span>Matrimonial</span></a>
        <a href="ebook.php"><i class="fa-solid fa-book-open"></i><span>eBooks</span></a>
        <a href="hostel-booking.php" class="active"><i class="fa-solid fa-bed"></i><span>Hostel Booking</span></a>
        <a href="address-book.php"><i class="fa-solid fa-address-book"></i><span>Address Book</span></a>
        <a href="gallery.php"><i class="fa-solid fa-image"></i><span>Gallery</span></a>
        <a href="about.php"><i class="fa-solid fa-circle-info"></i><span>About Us</span></a>
        <a href="contact.php"><i class="fa-solid fa-phone-volume"></i><span>Contact Us</span></a>
        <a href="logout.php">Logout</a>
    </nav>

    <section class="hero-title">
        <div class="title-icon">
            <i class="fa-solid fa-bed"></i>
        </div>
        <div>
            <h1>Hostel Booking</h1>
            <p>Confirm your hostel booking request</p>
        </div>
    </section>

    <section class="booking-steps">
        <div class="step"><span>1</span><b>Choose Hostel</b></div>
        <i class="fa-solid fa-arrow-right"></i>
        <div class="step active"><span>2</span><b>Select Room</b></div>
        <i class="fa-solid fa-arrow-right"></i>
        <div class="step active"><span>3</span><b>Choose Dates</b></div>
        <i class="fa-solid fa-arrow-right"></i>
        <div class="step active"><span>4</span><b>Confirm Booking</b></div>
        <i class="fa-solid fa-arrow-right"></i>
        <div class="step"><span>5</span><b>Payment</b></div>
    </section>

    <section class="booking-page-box">

        <a class="back-btn" href="hostel-booking.php">
            <i class="fa-solid fa-arrow-left"></i> Back to Hostel Search
        </a>

        <div class="booking-grid">

            <div>
                <div class="booking-card">
                    <img src="<?= htmlspecialchars($imagePath); ?>" alt="<?= htmlspecialchars($hostel['hostel_name']); ?>">

                    <h2><?= htmlspecialchars($hostel['hostel_name']); ?></h2>

                    <p>
                        <i class="fa-solid fa-location-dot"></i>
                        <?= htmlspecialchars($hostel['address'] ?? ''); ?>,
                        <?= htmlspecialchars($hostel['city'] ?? ''); ?>,
                        <?= htmlspecialchars($hostel['state'] ?? ''); ?>
                    </p>

                    <p><b>Manager:</b> <?= htmlspecialchars($hostel['manager_name'] ?? ''); ?></p>
                    <p><b>Mobile:</b> <?= htmlspecialchars($hostel['manager_mobile'] ?? ''); ?></p>
                    <p><b>Description:</b> <?= htmlspecialchars($hostel['description'] ?? ''); ?></p>
                    <p><b>Rules:</b> <?= htmlspecialchars($hostel['rules'] ?? ''); ?></p>
                    <p><b>Available Rooms:</b> <?= intval($hostel['available_rooms'] ?? 0); ?></p>
                    <p><b>Price Per Day:</b> ₹<?= number_format($price); ?></p>
                </div>

                <div class="form-card" style="margin-top:20px;">
                    <h2>Guest Details</h2>

                    <form method="POST" action="save-hostel-booking.php">
                        <input type="hidden" name="hostel_id" value="<?= intval($hostel['id']); ?>">
                        <input type="hidden" name="hostel_name" value="<?= htmlspecialchars($hostel['hostel_name']); ?>">
                        <input type="hidden" name="room_type" value="Standard Room">
                        <input type="hidden" name="rent" id="price_per_day" value="<?= htmlspecialchars($price); ?>">
                        <input type="hidden" name="city" value="<?= htmlspecialchars($hostel['city'] ?? ''); ?>">
                        <input type="hidden" name="nights" id="nights" value="<?= intval($nights); ?>">
                        <input type="hidden" name="total_amount" id="total_amount" value="<?= htmlspecialchars($total_amount); ?>">

                        <div class="form-grid">
                            <div>
                                <label>Name *</label>
                                <input type="text" name="name" required>
                            </div>

                            <div>
                                <label>Mobile *</label>
                                <input type="text" name="mobile" required>
                            </div>

                            <div>
                                <label>Email</label>
                                <input type="email" name="email">
                            </div>

                            <div>
                                <label>Total Persons *</label>
                                <input type="number" name="total_persons" value="<?= intval($total_persons); ?>" min="1" required>
                            </div>

                            <div>
                                <label>Number of Rooms *</label>
                                <input type="number" name="number_of_rooms" id="number_of_rooms" value="1" min="1" max="<?= intval($hostel['available_rooms'] ?? 1); ?>" onchange="calculateBookingAmount()" onkeyup="calculateBookingAmount()" required>
                            </div>

                            <div>
                                <label>Room Type</label>
                                <select name="room_type_select" disabled>
                                    <option>Standard Room</option>
                                </select>
                            </div>

                            <div>
                                <label>Check-in Date *</label>
                                <input type="date" name="check_in" id="check_in" value="<?= htmlspecialchars($check_in); ?>" onchange="calculateBookingAmount()" required>
                            </div>

                            <div>
                                <label>Check-out Date *</label>
                                <input type="date" name="check_out" id="check_out" value="<?= htmlspecialchars($check_out); ?>" onchange="calculateBookingAmount()" required>
                            </div>

                            <div>
                                <label>Check-in Time *</label>
                                <input type="time" name="check_in_time" value="12:00" required>
                            </div>

                            <div>
                                <label>Check-out Time *</label>
                                <input type="time" name="check_out_time" value="10:00" required>
                            </div>

                            <div class="full">
                                <label>Message</label>
                                <textarea name="message" placeholder="Any special request"></textarea>
                            </div>
                        </div>

                        <button class="confirm-btn" type="submit">
                            Confirm Booking Request <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </form>
                </div>
            </div>

            <aside class="summary-card-custom">
                <h3><i class="fa-solid fa-calendar-check"></i> Booking Summary</h3>

                <div class="summary-line">
                    <span><i class="fa-regular fa-calendar-days"></i> Check-in</span>
                    <b id="summary_check_in"><?= date("d M Y", strtotime($check_in)); ?></b>
                </div>

                <div class="summary-line">
                    <span><i class="fa-regular fa-calendar-days"></i> Check-out</span>
                    <b id="summary_check_out"><?= date("d M Y", strtotime($check_out)); ?></b>
                </div>

                <div class="summary-line">
                    <span><i class="fa-solid fa-users"></i> Guests</span>
                    <b><?= intval($total_persons); ?> Guests</b>
                </div>

                <div class="summary-line">
                    <span><i class="fa-solid fa-calendar-day"></i> Nights</span>
                    <b id="summary_nights"><?= intval($nights); ?> Nights</b>
                </div>

                <div class="summary-line">
                    <span><i class="fa-solid fa-door-open"></i> Rooms</span>
                    <b id="summary_rooms">1 Room(s)</b>
                </div>

                <div class="summary-line">
                    <span><i class="fa-solid fa-clock"></i> Time</span>
                    <b>12:00 PM - 10:00 AM</b>
                </div>

                <div class="summary-line">
                    <span><i class="fa-solid fa-bed"></i> Room</span>
                    <b>Standard Room</b>
                </div>

                <div class="summary-line">
                    <span><i class="fa-solid fa-indian-rupee-sign"></i> Price / Day</span>
                    <b>₹<?= number_format($price); ?></b>
                </div>

                <div class="summary-line summary-total">
                    <span>Total Amount</span>
                    <b id="summary_total">₹<?= number_format($total_amount); ?></b>
                </div>
            </aside>

        </div>
    </section>

    <footer class="footer">
        <p>© 2026 Vishwakarma Jagruti Manch. All Rights Reserved.</p>
        <p>Ekta <span>•</span> Seva <span>•</span> Sanskar <span>•</span> Samriddhi</p>
    </footer>

</div>

</body>
</html>
