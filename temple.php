<?php
 include 'auth.php'; 
$conn = mysqli_connect("127.0.0.1", "root", "", "vjm_db", 3307);

if (!$conn) {
    die("Connection Failed: " . mysqli_connect_error());
}

$filter = $_GET['filter'] ?? 'all';
$sort   = $_GET['sort'] ?? 'recent';

$where = "1";

if ($filter == 'nearby') {
    $where = "city = 'Sirohi'";
} elseif ($filter == 'popular') {
    $where = "rating >= 4";
} elseif ($filter == 'recent') {
    $where = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

$orderBy = "id DESC";

if ($sort == 'popular') {
    $orderBy = "rating DESC";
} elseif ($sort == 'rating') {
    $orderBy = "rating DESC";
} elseif ($sort == 'recent') {
    $orderBy = "id DESC";
}

$query = "SELECT * FROM temples WHERE $where ORDER BY $orderBy";
$result = mysqli_query($conn, $query);

if (!$result) {
    die("Temple Query Error: " . mysqli_error($conn));
}

/* Tabs Count */
$totalTemplesQ = mysqli_query($conn, "SELECT COUNT(*) AS total FROM temples");
$totalTemples = mysqli_fetch_assoc($totalTemplesQ)['total'] ?? 0;

$nearbyTemplesQ = mysqli_query($conn, "SELECT COUNT(*) AS total FROM temples WHERE city = 'Sirohi'");
$nearbyTemples = mysqli_fetch_assoc($nearbyTemplesQ)['total'] ?? 0;

$popularTemplesQ = mysqli_query($conn, "SELECT COUNT(*) AS total FROM temples WHERE rating >= 4");
$popularTemples = mysqli_fetch_assoc($popularTemplesQ)['total'] ?? 0;

$recentTemplesQ = mysqli_query($conn, "
    SELECT COUNT(*) AS total 
    FROM temples 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$recentTemples = mysqli_fetch_assoc($recentTemplesQ)['total'] ?? 0;

/* Sidebar Statistics */
$thisMonthQ = mysqli_query($conn, "
    SELECT COUNT(*) AS total 
    FROM temples 
    WHERE MONTH(created_at) = MONTH(CURDATE()) 
      AND YEAR(created_at) = YEAR(CURDATE())
");
$thisMonthAdded = mysqli_fetch_assoc($thisMonthQ)['total'] ?? 0;

$totalStatesQ = mysqli_query($conn, "
    SELECT COUNT(DISTINCT state) AS total 
    FROM temples 
    WHERE state IS NOT NULL AND TRIM(state) != ''
");
$totalStates = mysqli_fetch_assoc($totalStatesQ)['total'] ?? 0;

$totalCitiesQ = mysqli_query($conn, "
    SELECT COUNT(DISTINCT city) AS total 
    FROM temples 
    WHERE city IS NOT NULL AND TRIM(city) != ''
");
$totalCities = mysqli_fetch_assoc($totalCitiesQ)['total'] ?? 0;

/* Temple Categories */
$categoryQ = mysqli_query($conn, "
    SELECT category, COUNT(*) AS total 
    FROM temples 
    WHERE category IS NOT NULL 
      AND TRIM(category) != ''
    GROUP BY category 
    ORDER BY total DESC
");

if (!$categoryQ) {
    die("Category Query Error: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Temples Directory - Vishwakarma Jagruti Manch</title>

  <link rel="stylesheet" href="css/temple.css" />

  <link rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<?php include 'header.php'; ?>
<!-- Main Layout -->
  <main class="main-layout">

    <!-- Left Sidebar -->
    <aside class="left-sidebar">
      <h3>FIND TEMPLES</h3>

      <div class="search-box">
        <input type="text" id="searchInput" placeholder="Search temples...">
        <i class="fa-solid fa-magnifying-glass"></i>
      </div>

      <label>Select State</label>
      <select>
        <option>All States</option>
        <option>Rajasthan</option>
        <option>Gujarat</option>
        <option>Maharashtra</option>
        <option>Delhi</option>
      </select>

      <label>Select City</label>
      <select>
        <option>All Cities</option>
        <option>Sirohi</option>
        <option>Jaipur</option>
        <option>Ajmer</option>
        <option>Udaipur</option>
      </select>

      <label>Select Category</label>
      <select>
        <option>All Categories</option>
        <option>Vishwakarma Temple</option>
        <option>Vishwakarma Dham</option>
        <option>Community Temple</option>
      </select>

      <label>Temple Type</label>
      <select>
        <option>All Types</option>
        <option>Ancient</option>
        <option>Modern</option>
        <option>Community</option>
      </select>

      <h4>Facilities</h4>

      <div class="check"><input type="checkbox"> Parking Available</div>
      <div class="check"><input type="checkbox"> Accommodation</div>
      <div class="check"><input type="checkbox"> Prasad Available</div>
      <div class="check"><input type="checkbox"> Wheelchair Accessible</div>
      <div class="check"><input type="checkbox"> Online Donations</div>

      <button class="search-btn">
        <i class="fa-solid fa-magnifying-glass"></i> Search Temples
      </button>

      <button class="reset-btn">
        <i class="fa-solid fa-rotate-right"></i> Reset Filters
      </button>
    </aside>

    <!-- Center Content -->
    <section class="content">

      <div class="content-header">
        <div>
          <h2><i class="fa-solid fa-gopuram"></i> Temples Directory</h2>
          <p>Discover and connect with Vishwakarma temples across India.</p>
        </div>

        <button class="add-btn">
          <i class="fa-solid fa-plus"></i> Add New Temple
        </button>
      </div>

      <div class="tabs-row">
    <div class="tabs">
        <button class="tab active">All Temples (1,245)</button>
        <button class="tab"><i class="fa-solid fa-location-dot"></i> Nearby Temples</button>
        <button class="tab"><i class="fa-regular fa-star"></i> Popular Temples</button>
        <button class="tab"><i class="fa-regular fa-clock"></i> Recently Added</button>
    </div>

    <div class="sort-box">
        <span>Sort By :</span>
        <select>
            <option>Recently Added</option>
            <option>Popular</option>
            <option>Rating</option>
        </select>
        <button class="grid-btn"><i class="fa-solid fa-table-cells"></i></button>
        <button class="list-btn"><i class="fa-solid fa-list"></i></button>
    </div>
</div>
      <div class="temple-grid" id="templeGrid">

<?php while($row = mysqli_fetch_assoc($result)) { ?>

<?php
    $templeName = $row['temple_name'] ?? 'Temple Name';
    $city       = $row['city'] ?? '';
    $state      = $row['state'] ?? '';
    $category   = $row['category'] ?? '';
    $distance   = $row['distance'] ?? '';
    $rating     = $row['rating'] ?? '';

    // Database me image column me full path hai:
    // uploads/temples/temple_1781054994_6441.jpg
    $imagePath = !empty($row['image']) ? $row['image'] : 'images/default-temple.jpg';

    // Google Map ke liye address
    $mapSearch = trim($templeName . ' ' . $city . ' ' . $state);
    $mapUrl = 'https://maps.google.com/maps?q=' . urlencode($mapSearch) . '&output=embed';
?>

    <div class="temple-card">

        <div class="img-box">
            <img 
                src="<?php echo htmlspecialchars($imagePath); ?>" 
                alt="<?php echo htmlspecialchars($templeName); ?>"
            >
        </div>

        <div class="card-body">

    <h4><?php echo htmlspecialchars($templeName); ?></h4>

    <p>
        <i class="fa-solid fa-city"></i>
        <b>City:</b>
        <?php echo htmlspecialchars($city ?: 'Not Available'); ?>
    </p>

    <p>
        <i class="fa-solid fa-map-location-dot"></i>
        <b>State:</b>
        <?php echo htmlspecialchars($state ?: 'Not Available'); ?>
    </p>

    <p>
        <i class="fa-solid fa-tag"></i>
        <b>Category:</b>
        <?php echo htmlspecialchars($category ?: 'Not Available'); ?>
    </p>

    <p>
        <i class="fa-solid fa-star"></i>
        <b>Rating:</b>
        <?php echo htmlspecialchars($rating ?: '0'); ?>
    </p>
    <a class="details-btn" href="temple-details.php?id=<?php echo (int)$row['id']; ?>">
        View Details
    </a>

</div>
</a>
        </div>

    </div>

<?php } ?>

</div>
      <!-- Pagination -->
      <div class="pagination">
        <button><i class="fa-solid fa-angles-left"></i></button>
        <button><i class="fa-solid fa-angle-left"></i></button>
        <button class="active">1</button>
        <button>2</button>
        <button>3</button>
        <button>4</button>
        <button>5</button>
        <button>...</button>
        <button>125</button>
        <button><i class="fa-solid fa-angle-right"></i></button>
        <button><i class="fa-solid fa-angles-right"></i></button>
      </div>

    </section>

    <!-- Right Sidebar -->
    <aside class="right-sidebar">

      <div class="side-card">
        <h3>FEATURED TEMPLE</h3>
        <img src="images/temple1.jpg" alt="Featured Temple">
        <h4>Shri Vishwakarma Temple</h4>
        <p>Sirohi, Rajasthan</p>
        <div class="stars">
          ★★★★★ <span>4.8 (125 Reviews)</span>
        </div>
        <p>Ancient and sacred temple dedicated to Lord Vishwakarma.</p>
        <button>View Temple</button>
      </div>

      <div class="side-card">
    <h3>TEMPLE CATEGORIES</h3>

    <?php if ($categoryQ && mysqli_num_rows($categoryQ) > 0) { ?>

        <?php while ($cat = mysqli_fetch_assoc($categoryQ)) { ?>
            <div class="category-row">
                <span>
                    <i class="fa-solid fa-gopuram"></i>
                    <?php echo htmlspecialchars($cat['category']); ?>
                </span>
                <b><?php echo number_format($cat['total']); ?></b>
            </div>
        <?php } ?>

    <?php } else { ?>

        <div class="category-row">
            <span>
                <i class="fa-solid fa-gopuram"></i>
                No Category Found
            </span>
            <b>0</b>
        </div>

    <?php } ?>

    <a href="temple.php">
        View All Categories <i class="fa-solid fa-arrow-right"></i>
    </a>
</div>

      <div class="side-card">
    <h3>TEMPLE STATISTICS</h3>

    <div class="stat-row">
        <span><i class="fa-solid fa-gopuram"></i> Total Temples</span>
        <b><?php echo number_format($totalTemples); ?></b>
    </div>

    <div class="stat-row">
        <span><i class="fa-regular fa-calendar-plus"></i> This Month Added</span>
        <b><?php echo number_format($thisMonthAdded); ?></b>
    </div>

    <div class="stat-row">
        <span><i class="fa-solid fa-shield-heart"></i> Total States</span>
        <b><?php echo number_format($totalStates); ?></b>
    </div>

    <div class="stat-row">
        <span><i class="fa-solid fa-building"></i> Total Cities</span>
        <b><?php echo number_format($totalCities); ?></b>
    </div>
</div>

    </aside>

  </main>

  <!-- Bottom Stats -->
  <section class="bottom-stats">
    <div>
      <i class="fa-solid fa-gopuram"></i>
      <h3>1,245+</h3>
      <p>Total Temples</p>
    </div>

    <div>
      <i class="fa-solid fa-map-location-dot"></i>
      <h3>22</h3>
      <p>States Covered</p>
    </div>

    <div>
      <i class="fa-solid fa-globe"></i>
      <h3>186+</h3>
      <p>Cities Covered</p>
    </div>

    <div>
      <i class="fa-solid fa-users"></i>
      <h3>50k+</h3>
      <p>Devotees Connected</p>
    </div>

    <div>
      <i class="fa-solid fa-gopuram"></i>
      <h3>28</h3>
      <p>Added This Month</p>
    </div>

    <div>
      <i class="fa-solid fa-shield-halved"></i>
      <h3>100%</h3>
      <p>Verified Temples</p>
    </div>
  </section>

  <!-- Footer -->
  <footer>
    <p>© 2026 Vishwakarma Jagruti Manch. All Rights Reserved.</p>
    <p>Ekta • Seva • Sanskar • Samriddhi</p>
  </footer>

  <script src="temples.js"></script>
</body>
</html