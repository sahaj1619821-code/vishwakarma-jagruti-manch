<?php
include 'auth.php';

$conn = mysqli_connect("127.0.0.1", "root", "", "vjm_db", 3307);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

$ebook_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($ebook_id <= 0) {
    die("Invalid Ebook ID");
}

function safe($value, $default = "Not Added") {
    if (isset($value) && $value !== "" && $value !== null) {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    return htmlspecialchars($default, ENT_QUOTES, 'UTF-8');
}

function findFilePath($fileName, $folders = []) {
    if (empty($fileName)) {
        return "";
    }

    $fileName = trim($fileName);

    if (preg_match('/^https?:\/\//i', $fileName)) {
        return $fileName;
    }

    if (file_exists(__DIR__ . "/" . $fileName)) {
        return $fileName;
    }

    $baseName = basename($fileName);

    foreach ($folders as $folder) {
        $path = rtrim($folder, "/") . "/" . $baseName;

        if (file_exists(__DIR__ . "/" . $path)) {
            return $path;
        }
    }

    return "";
}

/* Ebook data */
$sql = "SELECT * FROM ebooks WHERE id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $ebook_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Ebook not found");
}

$ebook = $result->fetch_assoc();

$cover_image = findFilePath($ebook['cover_image'] ?? '', [
    "uploads/ebooks",
    "uploads",
    "images",
    "ebook"
]);

$book_file = findFilePath($ebook['book_file'] ?? '', [
    "uploads/ebooks",
    "uploads/pdf",
    "uploads",
    "ebook"
]);

if ($cover_image == "") {
    $cover_image = "images/default-book-cover.png";
}

/* Pages data */
$pages = [];

$tableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'ebook_pages'");

if ($tableCheck && mysqli_num_rows($tableCheck) > 0) {
    $pageSql = "SELECT * FROM ebook_pages WHERE ebook_id = ? ORDER BY page_no ASC, id ASC";
    $pageStmt = $conn->prepare($pageSql);
    $pageStmt->bind_param("i", $ebook_id);
    $pageStmt->execute();
    $pageResult = $pageStmt->get_result();

    while ($page = $pageResult->fetch_assoc()) {
        $imgPath = findFilePath($page['image_path'] ?? '', [
            "uploads/ebooks/pages",
            "uploads/ebooks",
            "uploads",
            "images"
        ]);

        if ($imgPath != "") {
            $pages[] = [
                "type" => "image",
                "title" => $page['page_title'] ?? "",
                "image" => $imgPath,
                "content" => $page['content'] ?? ""
            ];
        } else {
            $pages[] = [
                "type" => "text",
                "title" => $page['page_title'] ?? "Page " . ($page['page_no'] ?? ""),
                "content" => $page['content'] ?? ""
            ];
        }
    }
}

/* अगर ebook_pages खाली है तो default pages */
if (count($pages) == 0) {
    $pages[] = [
        "type" => "cover",
        "title" => $ebook['title'] ?? "Ebook",
        "image" => $cover_image
    ];

    $pages[] = [
        "type" => "text",
        "title" => "WELCOME",
        "content" => $ebook['description'] ?? "Description not added."
    ];

    $pages[] = [
        "type" => "text",
        "title" => "Book Details",
        "content" =>
            "Title: " . ($ebook['title'] ?? "Not Added") . "\n\n" .
            "Author: " . ($ebook['author'] ?? "Not Added") . "\n\n" .
            "Category: " . ($ebook['category'] ?? "Not Added") . "\n\n" .
            "Uploaded By: " . ($ebook['uploaded_by'] ?? "Not Added")
    ];

    $pages[] = [
        "type" => "download",
        "title" => "Read Full Ebook",
        "content" => "Click below button to open full ebook file.",
        "file" => $book_file
    ];
}
/* Last Back Cover add */
$pages[] = [
    "type" => "back_cover",
    "title" => "समाप्त",
    "content" => "धन्यवाद\nआपने यह eBook पढ़ी।"
];
$totalRealPages = count($pages);

/* अगर pages odd हैं तो एक blank page add */
if (count($pages) % 2 != 0) {
    $pages[] = [
        "type" => "text",
        "title" => "End",
        "content" => "Thank you for reading."
    ];
}

/* Pair pages into sheets */
$sheets = [];

for ($i = 0; $i < count($pages); $i += 2) {
    $sheets[] = [
        "front" => $pages[$i],
        "back" => $pages[$i + 1]
    ];
}

function renderPageContent($page) {
    $type = $page['type'] ?? 'text';

    if ($type == "back_cover") {
    echo '<div class="back-cover-page">';
    echo '<div class="back-cover-frame">';
    echo '<h1>' . safe($page['title'] ?? 'The End') . '</h1>';
    echo '<p>' . nl2br(safe($page['content'] ?? 'Thank you for reading.')) . '</p>';
    echo '</div>';
    echo '</div>';
    return;
}
if ($type == "end") {
    echo '<div class="cover-page end-page">';
    echo '<div class="cover-frame end-frame">';

    if (!empty($page['image'])) {
        echo '<img src="' . safe($page['image']) . '" alt="End Page">';
    }

    echo '<h1>समाप्त</h1>';
    echo '<h3>धन्यवाद</h3>';
    echo '<p>आपने यह ई-बुक पढ़ी, इसके लिए धन्यवाद।</p>';
    echo '<div class="end-line"></div>';
    echo '<h4>Vishwakarma Jagruti Manch</h4>';

    echo '</div>';
    echo '</div>';
    return;
}
    if ($type == "cover") {
        echo '<div class="cover-page">';
        echo '<div class="cover-frame">';
        echo '<img src="' . safe($page['image'] ?? '') . '" alt="Cover">';
        echo '<h1>' . safe($page['title'] ?? 'Ebook') . '</h1>';
        echo '<h3>History, Heritage & Culture</h3>';
        echo '</div>';
        echo '</div>';
        return;
    }

    if ($type == "image") {
        echo '<div class="image-page">';
        if (!empty($page['title'])) {
            echo '<h2>' . safe($page['title']) . '</h2>';
        }
        echo '<img src="' . safe($page['image'] ?? '') . '" alt="Book Page">';
        if (!empty($page['content'])) {
            echo '<p>' . nl2br(safe($page['content'])) . '</p>';
        }
        echo '</div>';
        return;
    }

    if ($type == "download") {
        echo '<div class="text-page center-page">';
        echo '<h2>' . safe($page['title'] ?? 'Read Ebook') . '</h2>';
        echo '<p>' . nl2br(safe($page['content'] ?? '')) . '</p>';

        if (!empty($page['file'])) {
            echo '<a class="open-book-btn" href="' . safe($page['file']) . '" target="_blank">Open Full Ebook</a>';
        } else {
            echo '<p class="not-found">Book file not found</p>';
        }

        echo '</div>';
        return;
    }

    echo '<div class="text-page">';
    echo '<h2>' . safe($page['title'] ?? 'Page') . '</h2>';
    echo '<p>' . nl2br(safe($page['content'] ?? 'Content not added.')) . '</p>';
    echo '</div>';
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <title><?php echo safe($ebook['title'] ?? 'Ebook Viewer'); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="css/ebook-3d.css?v=20">
</head>
<body>


<?php include 'header.php'; ?>
<div class="viewer-wrapper">

    <div class="top-bar">
        <a href="ebook.php" class="back-btn">← Back to eBooks</a>

        <div class="ebook-title-box">
            <h2><?php echo safe($ebook['title'] ?? 'Vishwakarma Samaj eBook'); ?></h2>
        </div>

        <div class="page-badge">
            Page <span id="currentPageNo">1</span> / <?php echo $totalRealPages; ?>
        </div>
    </div>

    <div class="book-area">

        <button class="side-btn" onclick="prevPage()">‹</button>

        <div class="book-scene">
            <div class="book" id="book" data-total-pages="<?php echo $totalRealPages; ?>">

                <div class="book-base-left"></div>
                <div class="book-base-right"></div>
                <div class="book-spine"></div>

                <?php
                $totalSheets = count($sheets);

                foreach ($sheets as $index => $sheet) {
                    $z = $totalSheets - $index;
                    ?>
                    <div class="sheet" style="z-index: <?php echo $z; ?>;">
                        <div class="page-face page-front">
                            <?php renderPageContent($sheet['front']); ?>
                            <span class="page-number"><?php echo ($index * 2) + 1; ?></span>
                        </div>

                        <div class="page-face page-back">
                            <?php renderPageContent($sheet['back']); ?>
                            <span class="page-number"><?php echo ($index * 2) + 2; ?></span>
                        </div>
                    </div>
                    <?php
                }
                ?>

            </div>
        </div>

        <button class="side-btn" onclick="nextPage()">›</button>

    </div>

    <div class="controls">
        <button onclick="prevPage()">← Previous</button>
        <button onclick="nextPage()" class="next-control">Next →</button>

        <?php if (!empty($book_file)) { ?>
            <a href="<?php echo safe($book_file); ?>" target="_blank" class="pdf-btn">Open PDF / File</a>
        <?php } ?>
    </div>

    <div class="features">
        <div>📖 <span>3D Page Flip Effect</span></div>
        <div>📱 <span>Mobile Responsive</span></div>
        <div>📄 <span>PDF Reader Option</span></div>
        <div>🎨 <span>Attractive UI</span></div>
    </div>

</div>

<script src="js/ebook-3d.js?v=20"></script>
</body>
</html>