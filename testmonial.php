<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
    Public Testimonial Page
    File name: testmonial.php
    Note: User requested spelling "testmonial.php". If your menu uses testimonial.php,
    either rename this file or change menu link accordingly.
*/

// Safe error display off for public page
mysqli_report(MYSQLI_REPORT_OFF);

// Database connection
// Agar aapke project me config/db file se $conn already milta hai to pehle use include karne ki koshish karega.
$conn = null;

$possible_config_files = [
    __DIR__ . '/config.php',
    __DIR__ . '/db.php',
    __DIR__ . '/database.php',
    __DIR__ . '/includes/config.php',
    __DIR__ . '/includes/db.php',
    __DIR__ . '/admin/config.php',
    __DIR__ . '/admin/db.php'
];

foreach ($possible_config_files as $config_file) {
    if (file_exists($config_file)) {
        include_once $config_file;
        if (isset($conn) && $conn instanceof mysqli) {
            break;
        }
    }
}

// Fallback connection for your local XAMPP setup
if (!isset($conn) || !($conn instanceof mysqli)) {
    $conn = mysqli_connect('127.0.0.1', 'root', '', 'vjm_db', 3307);
}

function t_e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function t_table_exists($conn, $table) {
    if (!$conn) return false;
    $table = mysqli_real_escape_string($conn, $table);
    $res = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    return ($res && mysqli_num_rows($res) > 0);
}

function t_column_exists($conn, $table, $column) {
    if (!$conn || !t_table_exists($conn, $table)) return false;
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($res && mysqli_num_rows($res) > 0);
}

function t_photo_src($photo) {
    $photo = trim($photo ?? '');

    if ($photo === '') {
        return 'images/default-profile.png';
    }

    // Already full URL or relative path
    if (preg_match('/^(https?:\/\/|\/|images\/|uploads\/|testimonial-photo\/|profile-photo\/)/i', $photo)) {
        return $photo;
    }

    // Common folder for testimonial photos
    if (file_exists(__DIR__ . '/testimonial-photo/' . $photo)) {
        return 'testimonial-photo/' . rawurlencode($photo);
    }

    if (file_exists(__DIR__ . '/uploads/testimonials/' . $photo)) {
        return 'uploads/testimonials/' . rawurlencode($photo);
    }

    if (file_exists(__DIR__ . '/profile-photo/' . $photo)) {
        return 'profile-photo/' . rawurlencode($photo);
    }

    return 'images/default-profile.png';
}

$table = 'testimonials';
$db_error = '';
$testimonials = [];

if (!$conn) {
    $db_error = 'Database connection failed.';
} elseif (!t_table_exists($conn, $table)) {
    $db_error = 'testimonials table database me nahi hai.';
} else {
    $where = "WHERE 1";

    if (t_column_exists($conn, $table, 'village_status')) {
        $where .= " AND village_status='approved'";
    }

    if (t_column_exists($conn, $table, 'admin_status')) {
        $where .= " AND admin_status='approved'";
    }

    if (t_column_exists($conn, $table, 'status')) {
        $where .= " AND status='active'";
    }

    $order = t_column_exists($conn, $table, 'created_at') ? 'created_at DESC' : 'id DESC';

    $sql = "SELECT * FROM `$table` $where ORDER BY $order LIMIT 60";
    $result = mysqli_query($conn, $sql);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $testimonials[] = $row;
        }
    } else {
        $db_error = mysqli_error($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback / Testimonials - Vishwakarma Jagruti Manch</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        :root {
            --bg-dark: #070303;
            --bg-deep: #120207;
            --card: #210008;
            --card-dark: #080303;
            --maroon: #570018;
            --gold: #f5c542;
            --orange: #ff8a00;
            --muted: #e2d1a5;
            --white: #ffffff;
        }

        body {
            background: var(--bg-dark);
            color: var(--white);
            overflow-x: hidden;
        }

        a {
            text-decoration: none;
        }

        .testimonial-hero {
            min-height: 300px;
            background:
                linear-gradient(rgba(0,0,0,0.78), rgba(0,0,0,0.78)),
                url('images/hero.jpg');
            background-size: cover;
            background-position: center;
            display: flex;
            align-items: center;
            padding: 50px 5%;
            border-bottom: 1px solid #8b5a13;
        }

        .testimonial-hero h1 {
            color: var(--gold);
            font-size: 58px;
            line-height: 1.1;
            margin-bottom: 14px;
        }

        .testimonial-hero p {
            max-width: 850px;
            color: #f5f5f5;
            font-size: 20px;
            line-height: 1.6;
        }

        .testimonial-wrap {
            width: calc(100% - 24px);
            margin: 24px 12px 42px;
        }

        .testimonial-title {
            text-align: center;
            margin-bottom: 28px;
        }

        .testimonial-title h2 {
            color: var(--gold);
            font-size: 38px;
            margin-bottom: 8px;
        }

        .testimonial-title p {
            color: var(--muted);
            font-size: 17px;
        }

        .testimonial-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .testimonial-card {
            background: linear-gradient(180deg, var(--card), var(--card-dark));
            border: 1px solid #9c7315;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 0 18px rgba(245,197,66,0.08);
            position: relative;
            overflow: hidden;
            min-height: 280px;
        }

        .testimonial-card::before {
            content: "\f10d";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            right: 18px;
            top: 14px;
            color: rgba(245,197,66,0.14);
            font-size: 64px;
        }

        .testimonial-top {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 16px;
            position: relative;
            z-index: 1;
        }

        .testimonial-photo {
            width: 78px;
            height: 78px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--gold);
            background: #120207;
            flex: 0 0 78px;
        }

        .testimonial-name h3 {
            color: #fff;
            font-size: 21px;
            margin-bottom: 5px;
        }

        .testimonial-name p {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.4;
        }

        .rating {
            color: var(--gold);
            margin: 10px 0 14px;
            letter-spacing: 2px;
            position: relative;
            z-index: 1;
        }

        .message {
            color: #f1e5c5;
            font-size: 16px;
            line-height: 1.7;
            position: relative;
            z-index: 1;
            word-break: break-word;
        }

        .empty-box,
        .error-box {
            width: calc(100% - 24px);
            margin: 34px 12px;
            padding: 28px;
            border-radius: 18px;
            text-align: center;
            background: linear-gradient(180deg, var(--card), var(--card-dark));
            border: 1px solid #9c7315;
            color: var(--muted);
            font-size: 18px;
        }

        .error-box {
            border-color: #ff6868;
            color: #ffd0d0;
        }

        footer {
            background: linear-gradient(90deg, #3b0011, #080303, #3b0011);
            border-top: 1px solid #9c7315;
            padding: 18px 30px;
            text-align: center;
            color: #eee;
            font-size: 14px;
        }

        @media (max-width: 1050px) {
            .testimonial-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .testimonial-hero h1 {
                font-size: 46px;
            }
        }

        @media (max-width: 700px) {
            .testimonial-hero {
                min-height: 240px;
                padding: 35px 18px;
            }

            .testimonial-hero h1 {
                font-size: 36px;
            }

            .testimonial-hero p {
                font-size: 16px;
            }

            .testimonial-grid {
                grid-template-columns: 1fr;
            }

            .testimonial-title h2 {
                font-size: 30px;
            }
        }
    </style>
</head>
<body>

<?php if (file_exists(__DIR__ . '/header.php')) { include 'header.php'; } ?>

<section class="testimonial-hero">
    <div>
        <h1>Feedback</h1>
        <p>Vishwakarma Jagruti Manch से जुड़े सदस्यों और समाजबंधुओं के अनुभव, सुझाव और प्रशंसा।</p>
    </div>
</section>


<?php if ($db_error !== ''): ?>
    <div class="error-box">
        <b>Error:</b> <?= t_e($db_error) ?>
    </div>
<?php elseif (empty($testimonials)): ?>
    <div class="empty-box">
        अभी कोई approved feedback उपलब्ध नहीं है। Admin approve करने के बाद feedback यहाँ दिखाई देगा।
    </div>
<?php else: ?>
    <section class="testimonial-wrap">
        <div class="testimonial-title">
            <h2>What People Say</h2>
            <p>Approved feedback and testimonials</p>
        </div>

        <div class="testimonial-grid">
            <?php foreach ($testimonials as $row): ?>
                <?php
                    $name = $row['name'] ?? 'Member';
                    $village = $row['village'] ?? '';
                    $district = $row['district'] ?? '';
                    $message = $row['message'] ?? '';
                    $photo = $row['photo'] ?? '';
                    $rating = isset($row['rating']) ? (int)$row['rating'] : 5;
                    if ($rating < 1) $rating = 1;
                    if ($rating > 5) $rating = 5;
                    $location_parts = array_filter([$village, $district]);
                    $location = implode(', ', $location_parts);
                ?>
                <div class="testimonial-card">
                    <div class="testimonial-top">
                        <img src="<?= t_e(t_photo_src($photo)) ?>" class="testimonial-photo" alt="<?= t_e($name) ?>" onerror="this.src='images/default-profile.png';">
                        <div class="testimonial-name">
                            <h3><?= t_e($name) ?></h3>
                            <?php if ($location !== ''): ?>
                                <p><i class="fa-solid fa-location-dot"></i> <?= t_e($location) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="rating" aria-label="Rating <?= $rating ?> out of 5">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?php if ($i <= $rating): ?>
                                <i class="fa-solid fa-star"></i>
                            <?php else: ?>
                                <i class="fa-regular fa-star"></i>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>

                    <p class="message"><?= nl2br(t_e($message)) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if (file_exists(__DIR__ . '/footer.php')) { include 'footer.php'; } else { ?>
<footer>
    <p>© 2026 Vishwakarma Jagruti Manch. All Rights Reserved.</p>
    <p>Designed with ❤️ for Vishwakarma Community</p>
</footer>
<?php } ?>

</body>
</html>
