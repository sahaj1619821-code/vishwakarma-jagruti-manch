<?php
session_start();
include 'auth.php';

$conn = mysqli_connect("127.0.0.1", "root", "", "vjm_db", 3307);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

/* Current login user id */
$current_user_id = 0;
if (isset($_SESSION['site_user_id'])) {
    $current_user_id = (int)$_SESSION['site_user_id'];
} elseif (isset($_SESSION['matrimonial_user_id'])) {
    $current_user_id = (int)$_SESSION['matrimonial_user_id'];
} elseif (isset($_SESSION['user_id'])) {
    $current_user_id = (int)$_SESSION['user_id'];
}

if ($current_user_id <= 0) {
    header("Location: login.php");
    exit;
}

/* Helper functions */
function safe($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function table_has_column($conn, $table, $column) {
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $q = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $q && mysqli_num_rows($q) > 0;
}

function get_first_existing_column($conn, $table, $columns) {
    foreach ($columns as $col) {
        if (table_has_column($conn, $table, $col)) {
            return $col;
        }
    }
    return null;
}

$message = "";

/* Create interest if user clicks Interested */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_interest') {
    $interest_user_id = (int)($_POST['interest_user_id'] ?? 0);

    if ($interest_user_id <= 0 || $interest_user_id == $current_user_id) {
        $message = "Invalid profile selected.";
    } else {
        mysqli_query($conn, "
            INSERT IGNORE INTO matrimonial_interests 
            (user_id, interested_user_id, created_at)
            VALUES ($current_user_id, $interest_user_id, NOW())
        ");
        $message = "Profile interested list में add हो गई है.";
    }
}

/* Current user data for My Match search */
$current_user_q = mysqli_query($conn, "SELECT * FROM matrimonial_users WHERE id=$current_user_id LIMIT 1");
$current_user = mysqli_fetch_assoc($current_user_q);

/* Dynamic search filters */
$where = [];
$where[] = "mu.id != $current_user_id";

$name_search = trim($_GET['name_search'] ?? '');
$looking_for = trim($_GET['looking_for'] ?? '');
$age_from = (int)($_GET['age_from'] ?? 0);
$age_to = (int)($_GET['age_to'] ?? 0);
$state = trim($_GET['state'] ?? '');
$city = trim($_GET['city'] ?? '');
$profession = trim($_GET['profession'] ?? '');
$my_match = isset($_GET['my_match']) ? 1 : 0;

/* Name / username minimum 3 characters */
if ($name_search !== '') {
    if (mb_strlen($name_search) < 3) {
        $message = "Name/User Name search के लिए minimum 3 characters डालना जरूरी है.";
    } else {
        $safe_name = mysqli_real_escape_string($conn, $name_search);
        $name_conditions = ["mu.full_name LIKE '%$safe_name%'"];
        if (table_has_column($conn, 'matrimonial_users', 'username')) {
            $name_conditions[] = "mu.username LIKE '%$safe_name%'";
        }
        $where[] = "(" . implode(" OR ", $name_conditions) . ")";
    }
}

/* Looking For */
if ($looking_for !== '' && $looking_for !== 'All') {
    $safe_gender = mysqli_real_escape_string($conn, $looking_for);
    $where[] = "mu.gender = '$safe_gender'";
}

/* State / City */
if ($state !== '' && $state !== 'All States') {
    $safe_state = mysqli_real_escape_string($conn, $state);
    $where[] = "mu.state = '$safe_state'";
}

if ($city !== '' && $city !== 'All Cities') {
    $safe_city = mysqli_real_escape_string($conn, $city);
    $where[] = "mu.city = '$safe_city'";
}

/* Profession column may be profession/occupation/job */
$profession_col = get_first_existing_column($conn, 'matrimonial_users', ['profession', 'occupation', 'job', 'work']);
if ($profession !== '' && $profession !== 'All Professions' && $profession_col) {
    $safe_profession = mysqli_real_escape_string($conn, $profession);
    $where[] = "mu.`$profession_col` LIKE '%$safe_profession%'";
}

/* Age filter - supports age column or dob/date_of_birth column */
if ($age_from > 0 || $age_to > 0) {
    if (table_has_column($conn, 'matrimonial_users', 'age')) {
        if ($age_from > 0) $where[] = "mu.age >= $age_from";
        if ($age_to > 0) $where[] = "mu.age <= $age_to";
    } else {
        $dob_col = get_first_existing_column($conn, 'matrimonial_users', ['dob', 'date_of_birth', 'birth_date']);
        if ($dob_col) {
            if ($age_from > 0) $where[] = "TIMESTAMPDIFF(YEAR, mu.`$dob_col`, CURDATE()) >= $age_from";
            if ($age_to > 0) $where[] = "TIMESTAMPDIFF(YEAR, mu.`$dob_col`, CURDATE()) <= $age_to";
        }
    }
}

/* My Match search - opposite gender + same state/city when available */
if ($my_match && $current_user) {
    if (!empty($current_user['gender'])) {
        $opposite = '';
        $g = strtolower(trim($current_user['gender']));
        if (in_array($g, ['male', 'groom', 'पुरुष', 'boy'])) {
            $opposite = 'Female';
        } elseif (in_array($g, ['female', 'bride', 'महिला', 'girl'])) {
            $opposite = 'Male';
        }

        if ($opposite != '') {
            $safe_opposite = mysqli_real_escape_string($conn, $opposite);
            $where[] = "mu.gender = '$safe_opposite'";
        }
    }

    if (!empty($current_user['state'])) {
        $safe_user_state = mysqli_real_escape_string($conn, $current_user['state']);
        $where[] = "mu.state = '$safe_user_state'";
    }

    if (!empty($current_user['city'])) {
        $safe_user_city = mysqli_real_escape_string($conn, $current_user['city']);
        $where[] = "mu.city = '$safe_user_city'";
    }
}

$where_sql = implode(" AND ", $where);

/* List data for dropdowns */
$states_q = mysqli_query($conn, "SELECT DISTINCT state FROM matrimonial_users WHERE state IS NOT NULL AND state!='' ORDER BY state ASC");
$cities_q = mysqli_query($conn, "SELECT DISTINCT city FROM matrimonial_users WHERE city IS NOT NULL AND city!='' ORDER BY city ASC");
$professions_q = null;
if ($profession_col) {
    $professions_q = mysqli_query($conn, "SELECT DISTINCT `$profession_col` AS profession_name FROM matrimonial_users WHERE `$profession_col` IS NOT NULL AND `$profession_col`!='' ORDER BY `$profession_col` ASC");
}

/* Fetch profiles with interest/match information */
$query = "
    SELECT 
        mu.*,
        my_i.id AS my_interest_id,
        other_i.id AS other_interest_id
    FROM matrimonial_users mu
    LEFT JOIN matrimonial_interests my_i 
        ON my_i.user_id = $current_user_id AND my_i.interested_user_id = mu.id
    LEFT JOIN matrimonial_interests other_i
        ON other_i.user_id = mu.id AND other_i.interested_user_id = $current_user_id
    WHERE $where_sql
    ORDER BY 
        CASE WHEN my_i.id IS NOT NULL AND other_i.id IS NOT NULL THEN 0 ELSE 1 END,
        mu.id DESC
";

$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Matrimonial - Vishwakarma Jagruti Manch</title>
  <link rel="stylesheet" href="css/matrimonial.css?v=13">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  <style>
    .search-name-input {
        width: 100%;
        padding: 12px;
        border-radius: 5px;
        border: 0;
        background: #fffaf3;
        color: #3b0010;
    }

    .match-check {
        display: flex;
        gap: 8px;
        align-items: center;
        margin-top: 14px;
        color: #ffd35a;
        font-size: 14px;
    }

    .profile-actions {
        display: grid !important;
        grid-template-columns: 1fr 1fr;
        gap: 8px !important;
        margin-top: 10px !important;
    }

    .profile-actions a,
    .profile-actions form {
        width: 100%;
        display: block !important;
        margin: 0 !important;
    }

    .profile-actions button {
        width: 100%;
        padding: 9px 7px;
        border: 1px solid #9a001d;
        background: white;
        color: #8a001a;
        border-radius: 5px;
        font-weight: bold;
        cursor: pointer;
        font-size: 12px;
    }

    .profile-actions .interest-btn {
        background: #fffaf3;
    }

    .profile-actions .added-btn {
        background: #149000;
        color: #fff;
        border-color: #149000;
    }

    .profile-actions .chat-btn {
        background: #4b0011;
        color: #fff;
        border-color: #ffc328;
    }

    .match-badge {
        display: inline-block;
        padding: 4px 8px;
        background: #4b0011;
        color: #ffc328;
        border-radius: 14px;
        font-size: 11px;
        margin-top: 5px;
        border: 1px solid #ffc328;
    }

    .alert-popup {
        position: fixed;
        top: 25px;
        right: 25px;
        max-width: 360px;
        padding: 14px 18px;
        background: #3b0010;
        color: #ffc328;
        border: 1px solid #ffb22c;
        border-radius: 8px;
        z-index: 9999;
        box-shadow: 0 10px 25px rgba(0,0,0,.35);
        animation: fadeOut 5s forwards;
    }

    @keyframes fadeOut {
        0% { opacity: 1; transform: translateY(0); }
        80% { opacity: 1; transform: translateY(0); }
        100% { opacity: 0; transform: translateY(-15px); display: none; }
    }
  </style>
</head>

<body>

<?php if ($message != "") { ?>
  <div class="alert-popup"><?= safe($message); ?></div>
<?php } ?>

<header class="top">
  <div class="logo">
    <img src="images/logo.png" alt="Logo">
    <div>
      <h1>VISHWAKARMA<br><span>JAGRUTI MANCH</span></h1>
      <p>एकता • सेवा • संस्कार • समृद्धि</p>
    </div>
  </div>
  <div class="contact">
    <span><i class="fa-solid fa-location-dot"></i> Sirohi, Rajasthan</span>
    <span><i class="fa-solid fa-envelope"></i> info@vishwakarmajagrutimanch.com</span>
    <span><i class="fa-solid fa-phone"></i> 8097523298</span>
  </div>
  <div class="auth">
    <a href="logout.php"><button><i class="fa-solid fa-user"></i> Logout</button></a>
  </div>
</header>

<nav>
  <a href="index.php"><i class="fa-solid fa-house"></i><span>Home</span></a>
  <a href="temple.php"><i class="fa-solid fa-gopuram"></i><span>Temples</span></a>
  <a href="matrimonial-entry.php" class="active"><i class="fa-solid fa-users"></i><span>Matrimonial</span></a>
  <a href="ebook.php"><i class="fa-solid fa-book-open"></i><span>eBooks</span></a>
  <a href="hostel-booking.php"><i class="fa-solid fa-bed"></i><span>Hostel Booking</span></a>
  <a href="address-book.php"><i class="fa-solid fa-address-book"></i><span>Address Book</span></a>
  <a href="gallery.php"><i class="fa-solid fa-image"></i><span>Gallery</span></a>
  <a href="about.php"><i class="fa-solid fa-circle-info"></i><span>About Us</span></a>
  <a href="contact.php"><i class="fa-solid fa-phone-volume"></i><span>Contact Us</span></a>
  <a href="logout.php">Logout</a>
</nav>

<section class="page-title">
  <div>
    <h2>Matrimonial</h2>
    <p>Home › Matrimonial</p>
  </div>
  <h3>Find your perfect life partner within the Vishwakarma community</h3>
  <a href="register-m-m.php">
    <button><i class="fa-solid fa-user-plus"></i> Register Free</button>
  </a>
</section>

<main class="matri-layout">

  <aside class="left-box">
    <h3><i class="fa-solid fa-magnifying-glass"></i> Quick Search</h3>

    <form method="GET" action="matrimonial.php">
      <label>Name / User Name <small>(minimum 3 letters)</small></label>
      <input class="search-name-input" type="text" name="name_search" minlength="3" placeholder="Enter minimum 3 letters" value="<?= safe($name_search); ?>">

      <label>Looking For</label>
      <select name="looking_for">
        <option value="All">All</option>
        <option value="Female" <?= $looking_for=='Female' ? 'selected' : ''; ?>>Bride</option>
        <option value="Male" <?= $looking_for=='Male' ? 'selected' : ''; ?>>Groom</option>
      </select>

      <div class="age-row">
        <div>
          <label>Age From</label>
          <select name="age_from">
            <option value="">Any</option>
            <?php for($i=18; $i<=60; $i++) { ?>
              <option value="<?= $i; ?>" <?= $age_from==$i ? 'selected' : ''; ?>><?= $i; ?></option>
            <?php } ?>
          </select>
        </div>
        <div>
          <label>To</label>
          <select name="age_to">
            <option value="">Any</option>
            <?php for($i=18; $i<=60; $i++) { ?>
              <option value="<?= $i; ?>" <?= $age_to==$i ? 'selected' : ''; ?>><?= $i; ?></option>
            <?php } ?>
          </select>
        </div>
      </div>

      <label>Select State</label>
      <select name="state">
        <option>All States</option>
        <?php while($s = mysqli_fetch_assoc($states_q)) { ?>
          <option value="<?= safe($s['state']); ?>" <?= $state==$s['state'] ? 'selected' : ''; ?>><?= safe($s['state']); ?></option>
        <?php } ?>
      </select>

      <label>Select City</label>
      <select name="city">
        <option>All Cities</option>
        <?php while($c = mysqli_fetch_assoc($cities_q)) { ?>
          <option value="<?= safe($c['city']); ?>" <?= $city==$c['city'] ? 'selected' : ''; ?>><?= safe($c['city']); ?></option>
        <?php } ?>
      </select>

      <label>Profession</label>
      <select name="profession">
        <option>All Professions</option>
        <?php if ($professions_q) { while($pr = mysqli_fetch_assoc($professions_q)) { ?>
          <option value="<?= safe($pr['profession_name']); ?>" <?= $profession==$pr['profession_name'] ? 'selected' : ''; ?>><?= safe($pr['profession_name']); ?></option>
        <?php }} ?>
      </select>

      <label class="match-check">
        <input type="checkbox" name="my_match" value="1" <?= $my_match ? 'checked' : ''; ?>>
        Show profiles according to my profile
      </label>

      <button type="submit" class="search-btn"><i class="fa-solid fa-search"></i> Search</button>
      <a href="matrimonial.php" class="advanced">Reset Search</a>
    </form>
  </aside>

  <section class="profiles-area">
    <div class="profiles-grid">

<?php if ($result && mysqli_num_rows($result) > 0) { ?>
<?php while($row = mysqli_fetch_assoc($result)) { ?>

    <?php
    $profile_img = "images/default-profile.png";

    if (!empty($row['profile_photo'])) {
        $file_name = basename($row['profile_photo']);

        $paths = [
            "profile-photp/" . $file_name,
            "profile-photo/" . $file_name,
            "uploads/profile-photo/" . $file_name
        ];

        foreach ($paths as $photo_path) {
            if (file_exists($photo_path)) {
                $profile_img = $photo_path;
                break;
            }
        }
    }

    $is_interested = !empty($row['my_interest_id']);
    $is_other_interested = !empty($row['other_interest_id']);
    $is_match = $is_interested && $is_other_interested;
    ?>

    <div class="profile-card new verified online">

        <img src="<?= safe($profile_img); ?>" alt="Profile" class="profile-img">
        <h3><?= safe($row['full_name'] ?? 'No Name'); ?></h3>

        <p>
            <?= safe($row['gender'] ?? ''); ?><br>
            <?= safe($row['city'] ?? ''); ?>,
            <?= safe($row['state'] ?? 'Rajasthan'); ?><br>
            Gotra: <?= safe($row['gotra'] ?? ''); ?><br>
            Marital Status: <?= safe($row['marital_status'] ?? ''); ?>
        </p>

        <small>● Online</small>

        <?php if ($is_match) { ?>
          <span class="match-badge">Matched Profile</span>
        <?php } elseif ($is_interested) { ?>
          <span class="match-badge">Interested Added</span>
        <?php } elseif ($is_other_interested) { ?>
          <span class="match-badge">Interested in You</span>
        <?php } ?>

        <div class="profile-actions">
            <a href="view-profile.php?id=<?= (int)$row['id']; ?>">
                <button type="button">View</button>
            </a>

            <?php if ($is_interested) { ?>
                <button type="button" class="added-btn">Added</button>
            <?php } else { ?>
                <form method="POST">
                    <input type="hidden" name="action" value="add_interest">
                    <input type="hidden" name="interest_user_id" value="<?= (int)$row['id']; ?>">
                    <button type="submit" class="interest-btn">♡ Interested</button>
                </form>
            <?php } ?>

            <?php if ($is_match) { ?>
                <a href="match-chat.php?user_id=<?= (int)$row['id']; ?>">
                    <button type="button" class="chat-btn">Chat</button>
                </a>
            <?php } else { ?>
                <button type="button" disabled title="Chat दोनों तरफ से interest होने के बाद open होगा">Chat Locked</button>
            <?php } ?>
        </div>

    </div>

<?php } ?>
<?php } else { ?>
    <p>No matching profile found.</p>
<?php } ?>

    </div>
  </section>

  <aside class="right-box">
    <div class="why">
      <h3>Why Choose Us?</h3>
      <p><i class="fa-solid fa-shield"></i> <b>100% Verified Profiles</b><br>All profiles are manually verified.</p>
      <p><i class="fa-solid fa-handshake"></i> <b>Trusted Community</b><br>Only Vishwakarma members.</p>
      <p><i class="fa-solid fa-lock"></i> <b>Privacy & Security</b><br>Your data security is our priority.</p>
      <p><i class="fa-solid fa-headset"></i> <b>Dedicated Support</b><br>Support team always ready.</p>
    </div>

    <div class="story">
      <h3>Success Stories</h3>
      <img src="./images/couple.jpg" alt="Success Story">
      <h4>Ankit & Pooja</h4>
      <p>हमने अपने समुदाय के भीतर अपने जीवनसाथी को पाया।</p>
    </div>

    <div class="create-box">
      <h3>Don’t find the right match?</h3>
      <p>Create your profile now.</p>
      <a href="register-m-m.php"><button>Create Profile</button></a>
    </div>
  </aside>

</main>

<section class="bottom-stats">
  <div><i class="fa-solid fa-users"></i><h3>50K+</h3><p>Registered Members</p></div>
  <div><i class="fa-solid fa-heart"></i><h3>1000+</h3><p>Successful Matches</p></div>
  <div><i class="fa-solid fa-shield"></i><h3>25+</h3><p>Years of Trust</p></div>
  <div><i class="fa-solid fa-lock"></i><h3>100%</h3><p>Privacy Protection</p></div>
  <div><i class="fa-solid fa-headset"></i><h3>24/7</h3><p>Customer Support</p></div>
</section>

<footer>
  © 2026 Vishwakarma Jagruti Manch. All Rights Reserved.
  <span>Designed with ❤️ for Vishwakarma Community</span>
</footer>

<script src="js/matrimonial.js"></script>
</body>
</html>
