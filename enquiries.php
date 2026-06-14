<?php
$conn = mysqli_connect("127.0.0.1", "root", "", "vjm_db", 3307);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

$msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name    = trim($_POST['name'] ?? '');
    $mobile  = trim($_POST['mobile'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name == "" || $mobile == "" || $message == "") {
        $msg = "Name, Mobile और Message भरना जरूरी है";
    } else {

        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO enquires (name, mobile, email, subject, message) VALUES (?, ?, ?, ?, ?)"
        );

        mysqli_stmt_bind_param($stmt, "sssss", $name, $mobile, $email, $subject, $message);

        if (mysqli_stmt_execute($stmt)) {
            echo "<script>
                alert('Enquiry successfully submit ho gayi');
                window.location.href='enquiries.php';
            </script>";
            exit;
        } else {
            $msg = "Database Error: " . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <title>Enquiries - Vishwakarma Jagruti Manch</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- CSS -->
    <link rel="stylesheet" href="css/enquiries.css?v=1">

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

</head>
<body>
<?php include 'header.php'; ?>
<section class="enquiry-page">

    <div class="left-panel">
        <div class="left-content">
            <img src="images/logo.png" alt="Logo" class="main-logo">

            <h1>Enquiries</h1>
            <h2>Vishwakarma Jagruti Manch</h2>

            <p>आपका सुझाव, समस्या या जानकारी हमें भेजें।</p>
            <p>हम जल्द से जल्द आपसे संपर्क करेंगे।</p>
        </div>
    </div>

    <div class="right-panel">
        <div class="enquiry-card">

            <h3>Enquiry Form</h3>

            <?php if (!empty($msg)) { ?>
                <div class="msg-box"><?php echo $msg; ?></div>
            <?php } ?>

            <form method="POST" action="">

                <div class="form-group">
                    <input type="text" name="name" placeholder="Full Name" required>
                </div>

                <div class="form-group">
                    <input type="text" name="mobile" placeholder="Mobile Number" required>
                </div>

                <div class="form-group">
                    <input type="email" name="email" placeholder="Email Address">
                </div>

                <div class="form-group">
                    <input type="text" name="subject" placeholder="Subject">
                </div>

                <div class="form-group">
                    <textarea name="message" placeholder="Write your message..." required></textarea>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fa-solid fa-paper-plane"></i> Submit Enquiry
                </button>

            </form>

        </div>
    </div>

</section>

</body>
</html>