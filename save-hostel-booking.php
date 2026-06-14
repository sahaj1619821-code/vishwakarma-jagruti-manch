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

$hostel_id = intval($_POST['hostel_id'] ?? 0);
$hostel_name = trim($_POST['hostel_name'] ?? '');
$room_type = trim($_POST['room_type'] ?? 'Standard Room');
$rent = floatval($_POST['rent'] ?? 0);

$name = trim($_POST['name'] ?? '');
$mobile = trim($_POST['mobile'] ?? '');
$email = trim($_POST['email'] ?? '');
$city = trim($_POST['city'] ?? '');
$check_in = trim($_POST['check_in'] ?? '');
$check_out = trim($_POST['check_out'] ?? '');
$check_in_time = trim($_POST['check_in_time'] ?? '');
$check_out_time = trim($_POST['check_out_time'] ?? '');
$total_persons = intval($_POST['total_persons'] ?? 1);
$number_of_rooms = intval($_POST['number_of_rooms'] ?? 1);
$nights = intval($_POST['nights'] ?? 1);
$total_amount = floatval($_POST['total_amount'] ?? 0);
$message = trim($_POST['message'] ?? '');

if ($name == "" || $mobile == "" || $city == "" || $check_in == "" || $check_out == "") {
    die("Required fields empty hain.");
}

if ($total_persons < 1) {
    $total_persons = 1;
}

if ($number_of_rooms < 1) {
    $number_of_rooms = 1;
}

if ($nights < 1) {
    $nights = 1;
}

if ($total_amount <= 0 && $rent > 0) {
    $total_amount = $rent * $nights * $number_of_rooms;
}

/* Available rooms check */
if ($hostel_id > 0) {
    $room_q = mysqli_query($conn, "SELECT available_rooms FROM hostels WHERE id='$hostel_id' LIMIT 1");
    $room_row = mysqli_fetch_assoc($room_q);

    if (!$room_row) {
        die("Hostel not found.");
    }

    if (intval($room_row['available_rooms']) < $number_of_rooms) {
        die("Itne rooms available nahi hain.");
    }
}

$data = [
    "name" => $name,
    "mobile" => $mobile,
    "email" => $email,
    "city" => $city,
    "check_in" => $check_in,
    "check_out" => $check_out,
    "total_persons" => $total_persons,
    "message" => $message,
    "booking_status" => "pending"
];

/* Ye columns agar hostel_bookings table me honge to automatic save honge */
$optional = [
    "hostel_id" => $hostel_id,
    "hostel_name" => $hostel_name,
    "room_type" => $room_type,
    "rent" => $rent,
    "number_of_rooms" => $number_of_rooms,
    "check_in_time" => $check_in_time,
    "check_out_time" => $check_out_time,
    "nights" => $nights,
    "total_amount" => $total_amount,
    "payment_status" => "pending"
];

foreach ($optional as $col => $val) {
    if (table_has_column($conn, "hostel_bookings", $col)) {
        $data[$col] = $val;
    }
}

$columns = array_keys($data);
$escaped_columns = array_map(function($c) {
    return "`" . $c . "`";
}, $columns);

$values = array_values($data);
$placeholders = implode(",", array_fill(0, count($values), "?"));
$types = "";

foreach ($values as $v) {
    if (is_int($v)) {
        $types .= "i";
    } elseif (is_float($v)) {
        $types .= "d";
    } else {
        $types .= "s";
    }
}

$sql = "INSERT INTO hostel_bookings (" . implode(",", $escaped_columns) . ") VALUES ($placeholders)";
$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Prepare failed: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, $types, ...$values);

if (mysqli_stmt_execute($stmt)) {
    $booking_id = mysqli_insert_id($conn);

    if ($hostel_id > 0) {
        mysqli_query($conn, "
            UPDATE hostels 
            SET available_rooms = GREATEST(available_rooms - $number_of_rooms, 0)
            WHERE id = '$hostel_id'
        ");
    }

    echo "<script>
        alert('Booking request submit ho gayi. Ab payment details submit kare.');
        window.location.href='hostel-payment.php?booking_id=" . $booking_id . "';
    </script>";
    exit;
} else {
    echo "Booking save nahi hui: " . mysqli_stmt_error($stmt);
}
?>
