<?php
session_start();

$conn = mysqli_connect("localhost", "root", "", "vjm_db", 3307);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

include 'auth.php';

if (!function_exists('user_booking_badge_class')) {
    function user_booking_badge_class($status) {
        $status = strtolower($status ?? 'pending');

        if ($status == 'approved') {
            return 'status-approved';
        }

        if ($status == 'rejected' || $status == 'cancelled') {
            return 'status-rejected';
        }

        if ($status == 'pending') {
            return 'status-pending';
        }

        return 'status-default';
    }
}

/* =========================
   FILTER VALUES
========================= */
$state = $_GET['state'] ?? 'Rajasthan';
$city = $_GET['city'] ?? 'Sirohi';
$check_in = $_GET['check_in'] ?? '2024-06-01';
$check_out = $_GET['check_out'] ?? '2024-06-05';
$total_persons = intval($_GET['total_persons'] ?? 2);
$sort = $_GET['sort'] ?? 'recommended';

if ($total_persons < 1) {
    $total_persons = 1;
}

/* =========================
   DATE / NIGHTS CALCULATION
========================= */
$nights = 1;

try {
    if (!empty($check_in) && !empty($check_out)) {
        $date1 = new DateTime($check_in);
        $date2 = new DateTime($check_out);
        $nights = $date1->diff($date2)->days;

        if ($nights < 1) {
            $nights = 1;
        }
    }
} catch (Exception $e) {
    $check_in = date('Y-m-d');
    $check_out = date('Y-m-d', strtotime('+1 day'));
    $nights = 1;
}

/* =========================
   SAFE VALUES
========================= */
$state_safe = mysqli_real_escape_string($conn, $state);
$city_safe = mysqli_real_escape_string($conn, $city);

/* =========================
   SORTING
========================= */
$order_by = "id DESC";

if ($sort == "low") {
    $order_by = "price_per_day ASC";
} elseif ($sort == "high") {
    $order_by = "price_per_day DESC";
} elseif ($sort == "rating") {
    $order_by = "rating DESC";
}

/* =========================
   DROPDOWN DATA FROM DATABASE
========================= */
$states_result = mysqli_query($conn, "
    SELECT DISTINCT state 
    FROM hostels 
    WHERE state IS NOT NULL AND state != ''
    ORDER BY state ASC
");

$cities_result = mysqli_query($conn, "
    SELECT DISTINCT city 
    FROM hostels 
    WHERE city IS NOT NULL 
    AND city != ''
    ORDER BY city ASC
");

/* =========================
   HOSTEL SEARCH QUERY
========================= */
$where = "WHERE status = 'active' AND available_rooms > 0";

if ($state_safe != '') {
    $where .= " AND TRIM(LOWER(state)) = TRIM(LOWER('$state_safe'))";
}

if ($city_safe != '') {
    $where .= " AND TRIM(LOWER(city)) = TRIM(LOWER('$city_safe'))";
}

$query = "
SELECT *
FROM hostels
$where
ORDER BY $order_by
";

$result = mysqli_query($conn, $query);

if (!$result) {
    die("Hostel query failed: " . mysqli_error($conn));
}

$total_hostels = mysqli_num_rows($result);

/* =========================
   BOOKING SUMMARY AMOUNT
   Minimum price hostel ke hisab se estimate amount
========================= */
$summary_amount = 0;

$amount_where = "WHERE status = 'active' AND available_rooms > 0";

if ($state_safe != '') {
    $amount_where .= " AND TRIM(LOWER(state)) = TRIM(LOWER('$state_safe'))";
}

if ($city_safe != '') {
    $amount_where .= " AND TRIM(LOWER(city)) = TRIM(LOWER('$city_safe'))";
}

$amount_q = mysqli_query($conn, "
    SELECT MIN(price_per_day) AS min_price
    FROM hostels
    $amount_where
");

if ($amount_q) {
    $amount_row = mysqli_fetch_assoc($amount_q);

    if ($amount_row && $amount_row['min_price'] !== null) {
        $summary_amount = floatval($amount_row['min_price']) * $nights;
    }
}

/* =========================
   STATS DATA
========================= */
$stats_hostels = 0;
$stats_bookings = 0;
$stats_rating = 0;

$stats_hostel_q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM hostels WHERE status='active'");
if ($stats_hostel_q) {
    $stats_hostels = intval(mysqli_fetch_assoc($stats_hostel_q)['total'] ?? 0);
}

$stats_booking_q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM hostel_bookings");
if ($stats_booking_q) {
    $stats_bookings = intval(mysqli_fetch_assoc($stats_booking_q)['total'] ?? 0);
}

$stats_rating_q = mysqli_query($conn, "SELECT AVG(rating) AS avg_rating FROM hostels WHERE status='active'");
if ($stats_rating_q) {
    $avg = mysqli_fetch_assoc($stats_rating_q)['avg_rating'] ?? 0;
    $stats_rating = number_format(floatval($avg), 1);
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Hostel Booking - Vishwakarma Jagruti Manch</title>

  <link rel="stylesheet" href="css/hostel-booking.css" />

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

<style>
.booking-status-box {
    margin: 10px 0;
    padding: 10px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 800;
    text-align: center;
    border: 1px solid transparent;
}

.booking-status-box strong {
    font-weight: 900;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
    border-color: #f5d97a;
}

.status-approved {
    background: #d4edda;
    color: #155724;
    border-color: #93d3a2;
}

.status-rejected,
.status-cancelled {
    background: #f8d7da;
    color: #721c24;
    border-color: #e3a1a8;
}

.status-default {
    background: #e9ecef;
    color: #333;
    border-color: #ccc;
}
</style>

</head>
<body>

  
<?php include 'header.php'; ?>
<div class="page-wrapper"><!-- PAGE TITLE -->
    <section class="hero-title">
      <div class="title-icon">
        <i class="fa-solid fa-bed"></i>
      </div>
      <div>
        <h1>Hostel Booking</h1>
        <p>Find, Book & Stay in Vishwakarma Hostels</p>
      </div>
    </section>

    <!-- BOOKING STEPS -->
    <section class="booking-steps">
      <div class="step active">
        <span>1</span>
        <b>Choose Hostel</b>
      </div>
      <i class="fa-solid fa-arrow-right"></i>

      <div class="step">
        <span>2</span>
        <b>Select Room</b>
      </div>
      <i class="fa-solid fa-arrow-right"></i>

      <div class="step">
        <span>3</span>
        <b>Choose Dates</b>
      </div>
      <i class="fa-solid fa-arrow-right"></i>

      <div class="step">
        <span>4</span>
        <b>Confirm Booking</b>
      </div>
      <i class="fa-solid fa-arrow-right"></i>

      <div class="step">
        <span>5</span>
        <b>Payment</b>
      </div>
    </section>

    <!-- MAIN CONTENT -->
    <main class="main-layout">

      <!-- LEFT FILTER -->
      <aside class="left-filter">
        <h3>Search & Filters <i class="fa-solid fa-filter"></i></h3>

        <form method="GET" action="hostel-booking.php">

          <label>Select State</label>
          <select name="state">
            <?php if ($states_result && mysqli_num_rows($states_result) > 0) { ?>
              <?php while ($s = mysqli_fetch_assoc($states_result)) { ?>
                <option value="<?= htmlspecialchars($s['state']); ?>" <?= ($state == $s['state']) ? 'selected' : ''; ?>>
                  <?= htmlspecialchars($s['state']); ?>
                </option>
              <?php } ?>
            <?php } else { ?>
              <option value="Rajasthan">Rajasthan</option>
            <?php } ?>
          </select>

          <label>Select City</label>
          <select name="city">
            <?php if ($cities_result && mysqli_num_rows($cities_result) > 0) { ?>
              <?php while ($c = mysqli_fetch_assoc($cities_result)) { ?>
                <option value="<?= htmlspecialchars($c['city']); ?>" <?= ($city == $c['city']) ? 'selected' : ''; ?>>
                  <?= htmlspecialchars($c['city']); ?>
                </option>
              <?php } ?>
            <?php } else { ?>
              <option value="<?= htmlspecialchars($city); ?>"><?= htmlspecialchars($city); ?></option>
            <?php } ?>
          </select>

          <label>Check-in Date</label>
          <div class="date-box">
            <input type="date" name="check_in" value="<?= htmlspecialchars($check_in); ?>">
            <i class="fa-solid fa-calendar-days"></i>
          </div>

          <label>Check-out Date</label>
          <div class="date-box">
            <input type="date" name="check_out" value="<?= htmlspecialchars($check_out); ?>">
            <i class="fa-solid fa-calendar-days"></i>
          </div>

          <label>Guests</label>
          <select name="total_persons">
            <?php for ($i = 1; $i <= 10; $i++) { ?>
              <option value="<?= $i; ?>" <?= ($total_persons == $i) ? 'selected' : ''; ?>>
                <?= $i; ?> Guests
              </option>
            <?php } ?>
          </select>

          <button type="submit" class="search-btn">
            <i class="fa-solid fa-magnifying-glass"></i> Search Hostels
          </button>

          <a href="hostel-booking.php" class="reset-btn" style="display:block;text-align:center;">
            <i class="fa-solid fa-rotate-right"></i> Reset Filters
          </a>
        </form>

        <div class="why-box">
          <button class="close-btn" type="button">×</button>
          <h3>Why Book With Us?</h3>

          <p><i class="fa-solid fa-shield-heart"></i> Verified & Safe Hostels</p>
          <p><i class="fa-solid fa-headset"></i> 24x7 Support Available</p>
          <p><i class="fa-solid fa-credit-card"></i> Secure Online Payment</p>
          <p><i class="fa-solid fa-star"></i> Best Prices Guaranteed</p>

          <img src="images/vishwakarma-small.png" alt="Vishwakarma">
        </div>
      </aside>

      <!-- HOSTEL LIST -->
      <section class="hostel-section" id="hostel-list">

        <div class="section-head">
          <h2><i class="fa-solid fa-bed"></i> Available Hostels</h2>
          <span><?= $total_hostels; ?> Hostels Found</span>

          <div class="sort-box">
            <label>Sort By</label>
            <select onchange="window.location.href=this.value;">
              <option value="hostel-booking.php?state=<?= urlencode($state); ?>&city=<?= urlencode($city); ?>&check_in=<?= urlencode($check_in); ?>&check_out=<?= urlencode($check_out); ?>&total_persons=<?= $total_persons; ?>&sort=recommended" <?= ($sort == 'recommended') ? 'selected' : ''; ?>>Recommended</option>

              <option value="hostel-booking.php?state=<?= urlencode($state); ?>&city=<?= urlencode($city); ?>&check_in=<?= urlencode($check_in); ?>&check_out=<?= urlencode($check_out); ?>&total_persons=<?= $total_persons; ?>&sort=low" <?= ($sort == 'low') ? 'selected' : ''; ?>>Low to High</option>

              <option value="hostel-booking.php?state=<?= urlencode($state); ?>&city=<?= urlencode($city); ?>&check_in=<?= urlencode($check_in); ?>&check_out=<?= urlencode($check_out); ?>&total_persons=<?= $total_persons; ?>&sort=high" <?= ($sort == 'high') ? 'selected' : ''; ?>>High to Low</option>

              <option value="hostel-booking.php?state=<?= urlencode($state); ?>&city=<?= urlencode($city); ?>&check_in=<?= urlencode($check_in); ?>&check_out=<?= urlencode($check_out); ?>&total_persons=<?= $total_persons; ?>&sort=rating" <?= ($sort == 'rating') ? 'selected' : ''; ?>>Rating</option>
            </select>
          </div>
        </div>

        <div class="hostel-grid">

          <?php if ($total_hostels > 0) { ?>

            <?php while($row = mysqli_fetch_assoc($result)) { ?>

              <?php
                $price = floatval($row['price_per_day'] ?? 0);
                $total_amount = $price * $nights;

                $imgName = '';
                if (!empty($row['hostel_image'])) {
                    $imgName = basename($row['hostel_image']);
                } elseif (!empty($row['photo'])) {
                    $imgName = basename($row['photo']);
                }

                $imagePath = 'images/hostel-hero.jpg';

                if ($imgName != '') {
                    $possiblePath = 'uploads/hostels/' . $imgName;

                    if (file_exists($possiblePath)) {
                        $imagePath = $possiblePath;
                    }
                }

                $facilities = $row['facilities'] ?? '';
                $rating = $row['rating'] ?? '4.5';

                /*
                   User booking status:
                   Agar login session me mobile/email hai to us user ki latest booking show hogi.
                   Agar session data nahi mila to is hostel ki latest booking show hogi.
                */
                $user_mobile = $_SESSION['site_user_mobile'] ?? $_SESSION['user_mobile'] ?? '';
                $user_email  = $_SESSION['site_user_email'] ?? $_SESSION['user_email'] ?? '';

                $latest_booking = null;
                $booking_filter = "hostel_id = '" . intval($row['id']) . "'";

                if ($user_mobile != '') {
                    $safe_mobile = mysqli_real_escape_string($conn, $user_mobile);
                    $booking_filter .= " AND mobile = '$safe_mobile'";
                } elseif ($user_email != '') {
                    $safe_email = mysqli_real_escape_string($conn, $user_email);
                    $booking_filter .= " AND email = '$safe_email'";
                }

                $booking_q = mysqli_query($conn, "
                    SELECT booking_status, total_amount, created_at
                    FROM hostel_bookings
                    WHERE $booking_filter
                    ORDER BY id DESC
                    LIMIT 1
                ");

                if ($booking_q && mysqli_num_rows($booking_q) > 0) {
                    $latest_booking = mysqli_fetch_assoc($booking_q);
                }
              ?>

              <div class="hostel-card">

                <div class="image-box">
                  <img src="<?= htmlspecialchars($imagePath); ?>" alt="<?= htmlspecialchars($row['hostel_name']); ?>">
                  <span class="badge green">Available</span>
                  <button class="heart" type="button">
                    <i class="fa-regular fa-heart"></i>
                  </button>
                </div>

                <div class="card-content">
                  <h3><?= htmlspecialchars($row['hostel_name']); ?></h3>

                  <p>
                    <i class="fa-solid fa-location-dot"></i>
                    <?= htmlspecialchars($row['city']); ?>,
                    <?= htmlspecialchars($row['state']); ?>
                  </p>

                  <p>
                    <i class="fa-solid fa-star"></i>
                    Rating: <?= htmlspecialchars($rating); ?>/5
                  </p>

                  <p><?= htmlspecialchars($row['description'] ?? ''); ?></p>

                  <p>
                    <i class="fa-solid fa-door-open"></i>
                    Available Rooms: <?= htmlspecialchars($row['available_rooms']); ?>
                  </p>

                  <div class="features">
                    <?php if (!empty($facilities)) { ?>
                      <?php
                        $facility_arr = explode(',', $facilities);
                        foreach ($facility_arr as $facility) {
                            $facility = trim($facility);
                            if ($facility != '') {
                                echo '<span><i class="fa-solid fa-check"></i> ' . htmlspecialchars($facility) . '</span>';
                            }
                        }
                      ?>
                    <?php } else { ?>
                      <span><i class="fa-solid fa-wifi"></i> Wi-Fi</span>
                      <span><i class="fa-solid fa-utensils"></i> Food</span>
                      <span><i class="fa-solid fa-square-parking"></i> Parking</span>
                    <?php } ?>
                  </div>

                  <div class="price">
                    ₹<?= number_format($price); ?>
                    <span>/ day</span>
                  </div>

                  <p><b>Estimated:</b> ₹<?= number_format($total_amount); ?> for <?= $nights; ?> night(s)</p>

                  <?php if (!empty($latest_booking)) { ?>
                    <?php
                      $booking_status_show = strtolower($latest_booking['booking_status'] ?? 'pending');
                      $status_class = user_booking_badge_class($booking_status_show);
                    ?>

                    <div class="booking-status-box <?= $status_class; ?>">
                      <strong>Booking Status:</strong>
                      <?= htmlspecialchars(ucfirst($booking_status_show)); ?>
                    </div>
                  <?php } ?>

                  <a href="book-hostel.php?id=<?= intval($row['id']); ?>&check_in=<?= urlencode($check_in); ?>&check_out=<?= urlencode($check_out); ?>&total_persons=<?= intval($total_persons); ?>">
                    <button type="button">Select Hostel</button>
                  </a>
                </div>

              </div>

            <?php } ?>

          <?php } else { ?>

            <div class="hostel-card">
              <div class="card-content">
                <h3>No Hostel Found</h3>
                <p>Selected city/state me abhi active hostel available nahi hai.</p>
                <p>Filter change karke dobara search kare.</p>
              </div>
            </div>

          <?php } ?>

        </div>

      </section>

      <!-- RIGHT SUMMARY -->
      <aside class="right-summary">

        <div class="summary-card">
          <div class="summary-title">
            <h3><i class="fa-solid fa-calendar-check"></i> Booking Summary</h3>
            <button type="button">×</button>
          </div>

          <div class="summary-row">
            <span><i class="fa-regular fa-calendar-days"></i> Check-in</span>
            <b><?= date("d M Y", strtotime($check_in)); ?></b>
          </div>

          <div class="summary-row">
            <span><i class="fa-regular fa-calendar-days"></i> Check-out</span>
            <b><?= date("d M Y", strtotime($check_out)); ?></b>
          </div>

          <div class="summary-row">
            <span><i class="fa-solid fa-users"></i> Guests</span>
            <b><?= $total_persons; ?> Guests</b>
          </div>

          <div class="summary-row">
            <span><i class="fa-solid fa-calendar-day"></i> Nights</span>
            <b><?= $nights; ?> Nights</b>
          </div>

          <div class="summary-row amount">
            <span><i class="fa-solid fa-indian-rupee-sign"></i> Estimate Amount</span>
            <b>
              <?php if ($summary_amount > 0) { ?>
                ₹ <?= number_format($summary_amount); ?>
              <?php } else { ?>
                ₹ 0
              <?php } ?>
            </b>
          </div>

          <?php if ($total_hostels > 0) { ?>
            <a href="#hostel-list">
              <button class="book-btn" type="button">
                Select Hostel First <i class="fa-solid fa-arrow-right"></i>
              </button>
            </a>
          <?php } else { ?>
            <button class="book-btn" type="button" disabled>
              No Hostel Found
            </button>
          <?php } ?>

          <button class="save-btn" type="button">
            <i class="fa-regular fa-bookmark"></i> Save for Later
          </button>
        </div>

        <div class="facility-card">
          <h3>Popular Facilities</h3>

          <div class="facility-grid">
            <div><i class="fa-solid fa-tv"></i><span>AC Rooms</span></div>
            <div><i class="fa-solid fa-wifi"></i><span>Wi-Fi</span></div>
            <div><i class="fa-solid fa-utensils"></i><span>Canteen</span></div>
            <div><i class="fa-solid fa-square-parking"></i><span>Parking</span></div>
            <div><i class="fa-solid fa-video"></i><span>CCTV</span></div>
            <div><i class="fa-solid fa-shield-halved"></i><span>24x7 Security</span></div>
            <div><i class="fa-solid fa-elevator"></i><span>Lift</span></div>
            <div><i class="fa-solid fa-fire-flame-curved"></i><span>Hot Water</span></div>
          </div>
        </div>

        <div class="payment-card">
          <h3>Payment Methods</h3>

          <div class="payment-grid">
            <div>UPI</div>
            <div>VISA</div>
            <div>Master</div>
            <div>RuPay</div>
            <div>PhonePe</div>
            <div>Paytm</div>
            <div>G Pay</div>
            <div>Net Banking</div>
          </div>
        </div>

      </aside>

    </main>

    <!-- STATS -->
    <section class="bottom-stats">
      <div>
        <i class="fa-solid fa-bed"></i>
        <h3><?= $stats_hostels; ?>+</h3>
        <p>Hostels Listed</p>
      </div>

      <div>
        <i class="fa-solid fa-trophy"></i>
        <h3><?= $stats_bookings; ?>+</h3>
        <p>Bookings Completed</p>
      </div>

      <div>
        <i class="fa-solid fa-star"></i>
        <h3><?= $stats_rating; ?>/5</h3>
        <p>Average Rating</p>
      </div>

      <div>
        <i class="fa-solid fa-headset"></i>
        <h3>24/7</h3>
        <p>Customer Support</p>
      </div>

      <div>
        <i class="fa-solid fa-shield-halved"></i>
        <h3>100%</h3>
        <p>Secure Booking</p>
      </div>
    </section>

    <!-- FOOTER -->
    <footer class="footer">
      <p>© 2026 Vishwakarma Jagruti Manch. All Rights Reserved.</p>
      <p>Ekta <span>•</span> Seva <span>•</span> Sanskar <span>•</span> Samriddhi</p>
    </footer>

  </div>

</body>
</html>
