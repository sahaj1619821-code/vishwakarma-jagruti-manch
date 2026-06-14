<?php
session_start();

$conn = mysqli_connect("localhost", "root", "", "vjm_db", 3307);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

function table_has_column($conn, $table, $column) {
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $q = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($q && mysqli_num_rows($q) > 0);
}

$booking_id = intval($_POST['booking_id'] ?? 0);
$payment_method = mysqli_real_escape_string($conn, $_POST['payment_method'] ?? '');
$transaction_id = mysqli_real_escape_string($conn, $_POST['transaction_id'] ?? '');

if ($booking_id <= 0 || $transaction_id == "") {
    die("Payment details incomplete hain.");
}

$screenshot_name = "";

if (!empty($_FILES['payment_screenshot']['name'])) {
    $upload_dir = "uploads/payments/";

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $ext = strtolower(pathinfo($_FILES['payment_screenshot']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];

    if (!in_array($ext, $allowed)) {
        die("Only JPG, PNG, WEBP image allowed.");
    }

    $screenshot_name = "payment_" . time() . "_" . rand(1000, 9999) . "." . $ext;
    $target = $upload_dir . $screenshot_name;

    if (!move_uploaded_file($_FILES['payment_screenshot']['tmp_name'], $target)) {
        die("Payment screenshot upload nahi hua.");
    }
}

$set_parts = [];

if (table_has_column($conn, "hostel_bookings", "payment_status")) {
    $set_parts[] = "payment_status = 'pending'";
}

if (table_has_column($conn, "hostel_bookings", "transaction_id")) {
    $set_parts[] = "transaction_id = '$transaction_id'";
}

if (table_has_column($conn, "hostel_bookings", "payment_method")) {
    $set_parts[] = "payment_method = '$payment_method'";
}

if ($screenshot_name != "" && table_has_column($conn, "hostel_bookings", "payment_screenshot")) {
    $set_parts[] = "payment_screenshot = '$screenshot_name'";
}

if (table_has_column($conn, "hostel_bookings", "booking_status")) {
    $set_parts[] = "booking_status = 'pending'";
}

if (count($set_parts) == 0) {
    die("Payment columns table me nahi hain.");
}

$sql = "UPDATE hostel_bookings SET " . implode(", ", $set_parts) . " WHERE id = '$booking_id'";

if (mysqli_query($conn, $sql)) {
    echo "<script>
        alert('Payment details submit ho gayi. Admin verify karke booking approve karega.');
        window.location.href='hostel-booking.php';
    </script>";
    exit;
} else {
    echo "Payment save nahi hua: " . mysqli_error($conn);
}
?>
