<?php
$conn = mysqli_connect("127.0.0.1", "root", "", "vjm_db", 3307);

if (!$conn) {
    die("Connection Failed: " . mysqli_connect_error());
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$query = "SELECT * FROM temples WHERE id = $id LIMIT 1";
$result = mysqli_query($conn, $query);
$temple = mysqli_fetch_assoc($result);

if (!$temple) {
    die("Temple not found");
}

$templeName = $temple['temple_name'] ?? 'Temple Details';
$imagePath = !empty($temple['image']) ? $temple['image'] : 'images/default-temple.jpg';

$city = $temple['city'] ?? '';
$state = $temple['state'] ?? '';
$category = $temple['category'] ?? '';
$rating = $temple['rating'] ?? '';
$address = trim($templeName . ' ' . $city . ' ' . $state);
$mapLink = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($address);
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($templeName); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f7f2ea;
            margin: 0;
            padding: 30px;
        }

        .details-card {
            max-width: 900px;
            margin: auto;
            background: #fff;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }

        .details-card img {
            width: 100%;
            height: 360px;
            object-fit: cover;
        }

        .details-body {
            padding: 25px;
        }

        .details-body h1 {
            margin-top: 0;
            color: #8a4b12;
        }

        .btn-row {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }

        .btn-row a {
            text-decoration: none;
            padding: 12px 18px;
            border-radius: 8px;
            font-weight: bold;
        }

        .back-btn {
            background: #e5e7eb;
            color: #111827;
        }

        .map-btn {
            background: #2563eb;
            color: #fff;
        }
    </style>
</head>
<body>

<div class="details-card">
    <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="Temple">

    <div class="details-body">
        <h1><?php echo htmlspecialchars($templeName); ?></h1>

        <p><b>City:</b> <?php echo htmlspecialchars($city); ?></p>
        <p><b>State:</b> <?php echo htmlspecialchars($state); ?></p>
        <p><b>Category:</b> <?php echo htmlspecialchars($category); ?></p>
        <p><b>Rating:</b> <?php echo htmlspecialchars($rating); ?></p>

        <div class="btn-row">
            <a class="back-btn" href="temple.php">Back</a>
            <a class="map-btn" href="<?php echo htmlspecialchars($mapLink); ?>" target="_blank">Open Google Map</a>
        </div>
    </div>
</div>

</body>
</html>