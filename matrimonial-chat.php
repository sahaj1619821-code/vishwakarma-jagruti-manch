<?php
session_start();
include 'auth.php';

$conn = mysqli_connect("127.0.0.1", "root", "", "vjm_db", 3307);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

function safe($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}


function vjm_create_notification_table($conn) {
    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS matrimonial_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            receiver_profile_id INT NOT NULL,
            sender_profile_id INT NOT NULL,
            type VARCHAR(50) NOT NULL,
            title VARCHAR(180) NOT NULL,
            message TEXT NULL,
            link VARCHAR(255) NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_receiver_read (receiver_profile_id, is_read),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function vjm_profile_display_name($conn, $profile_id) {
    $profile_id = (int)$profile_id;
    if ($profile_id <= 0) return 'Someone';
    $q = mysqli_query($conn, "SELECT full_name, name FROM matrimonial_users WHERE id=$profile_id LIMIT 1");
    if ($q && mysqli_num_rows($q) > 0) {
        $r = mysqli_fetch_assoc($q);
        if (!empty($r['full_name'])) return $r['full_name'];
        if (!empty($r['name'])) return $r['name'];
    }
    return 'Someone';
}

function vjm_add_matrimonial_notification($conn, $receiver_profile_id, $sender_profile_id, $type, $title, $message, $link) {
    vjm_create_notification_table($conn);
    $receiver_profile_id = (int)$receiver_profile_id;
    $sender_profile_id = (int)$sender_profile_id;
    if ($receiver_profile_id <= 0 || $sender_profile_id <= 0 || $receiver_profile_id === $sender_profile_id) return false;

    $type = mysqli_real_escape_string($conn, $type);
    $title = mysqli_real_escape_string($conn, $title);
    $message = mysqli_real_escape_string($conn, $message);
    $link = mysqli_real_escape_string($conn, $link);

    mysqli_query($conn, "
        INSERT INTO matrimonial_notifications
        (receiver_profile_id, sender_profile_id, type, title, message, link, is_read, created_at)
        VALUES ($receiver_profile_id, $sender_profile_id, '$type', '$title', '$message', '$link', 0, NOW())
    ");
    return true;
}



$site_user_id = 0;
if (isset($_SESSION['site_user_id'])) {
    $site_user_id = (int)$_SESSION['site_user_id'];
} elseif (isset($_SESSION['user_id'])) {
    $site_user_id = (int)$_SESSION['user_id'];
}

if ($site_user_id <= 0) {
    header("Location: login.php");
    exit;
}

/* Current logged-in normal user ki matrimonial profile id nikaalo */
$current_profile_q = mysqli_query($conn, "
    SELECT id, full_name 
    FROM matrimonial_users 
    WHERE user_id = $site_user_id 
    ORDER BY id DESC 
    LIMIT 1
");

$current_profile = ($current_profile_q && mysqli_num_rows($current_profile_q) > 0) ? mysqli_fetch_assoc($current_profile_q) : null;

if (!$current_profile) {
    header("Location: plan.php");
    exit;
}

$current_user_id = (int)$current_profile['id'];

/* Page link me id=6 ya user_id=6 dono accept karega */
$chat_user_id = (int)($_GET['id'] ?? $_GET['user_id'] ?? $_POST['receiver_id'] ?? 0);

if ($chat_user_id <= 0 || $chat_user_id == $current_user_id) {
    die("Invalid chat user.");
}

mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS matrimonial_interests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        interested_user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_interest (user_id, interested_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

/* Chat only if both users are interested in each other */
$match_q = mysqli_query($conn, "
    SELECT 
        a.id AS my_interest,
        b.id AS other_interest
    FROM matrimonial_interests a
    JOIN matrimonial_interests b 
        ON b.user_id = $chat_user_id 
       AND b.interested_user_id = $current_user_id
    WHERE a.user_id = $current_user_id 
      AND a.interested_user_id = $chat_user_id
    LIMIT 1
");

if (!$match_q || mysqli_num_rows($match_q) == 0) {
    $user_q_lock = mysqli_query($conn, "SELECT full_name FROM matrimonial_users WHERE id=$chat_user_id LIMIT 1");
    $lock_user = ($user_q_lock && mysqli_num_rows($user_q_lock) > 0) ? mysqli_fetch_assoc($user_q_lock) : [];
    ?>
    <!DOCTYPE html>
    <html lang="hi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Chat Locked</title>
        <style>
            body{margin:0;background:#070000;color:#fff;font-family:Arial,sans-serif}.lock-box{max-width:650px;margin:70px auto;background:#260006;border:1px solid #9b5b13;border-radius:14px;padding:35px;text-align:center}.lock-box h1{color:#ffc328;font-family:Georgia,serif}.lock-box p{line-height:1.7;color:#fff1c7}.lock-actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:22px}.lock-actions a{padding:13px 22px;border-radius:10px;background:linear-gradient(#ffd65b,#ff9d00);color:#220000;font-weight:900;text-decoration:none}.name{color:#ffc328;font-weight:900}
        </style>
    </head>
    <body>
        <?php include 'header.php'; ?>
        <div class="lock-box">
            <h1>Chat Locked</h1>
            <p><span class="name"><?= safe($lock_user['full_name'] ?? 'इस profile'); ?></span> से chat करने के लिए दोनों users का interest match होना जरूरी है।</p>
            <p>पहले Matrimonial page पर जाकर <b>Send Interest</b> करें। सामने वाला user interest accept/send करेगा, उसके बाद chat open होगी।</p>
            <div class="lock-actions">
                <a href="matrimonial.php">← Back to Profiles</a>
                <a href="view-profile.php?id=<?= (int)$chat_user_id; ?>">View Profile</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$user_q = mysqli_query($conn, "SELECT * FROM matrimonial_users WHERE id=$chat_user_id LIMIT 1");
$chat_user = mysqli_fetch_assoc($user_q);

if (!$chat_user) {
    die("User not found.");
}

$message_error = "";

/* Plan chat limit check */
function get_chat_plan($conn, $user_id) {
    $q = mysqli_query($conn, "
        SELECT 
            mup.id,
            mup.used_chat_count,
            mup.end_date,
            mup.payment_status,
            mp.chat_limit
        FROM matrimonial_user_plans mup
        JOIN matrimonial_plans mp ON mp.id = mup.plan_id
        WHERE mup.user_id = $user_id
        ORDER BY mup.id DESC
        LIMIT 1
    ");
    return $q ? mysqli_fetch_assoc($q) : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $msg = trim($_POST['message']);

    if ($msg == "") {
        $message_error = "Message खाली नहीं होना चाहिए.";
    } else {
        $plan = get_chat_plan($conn, $current_user_id);

        if (!$plan || $plan['payment_status'] != 'approved') {
            $message_error = "Chat send करने के लिए active plan जरूरी है.";
        } elseif (empty($plan['end_date']) || strtotime($plan['end_date']) < time()) {
            mysqli_query($conn, "UPDATE matrimonial_user_plans SET payment_status='expired' WHERE id=" . (int)$plan['id']);
            $message_error = "आपका plan expire हो गया है.";
        } elseif ((int)$plan['chat_limit'] <= 0) {
            $message_error = "आपके plan में chat limit 0 है.";
        } elseif ((int)$plan['used_chat_count'] >= (int)$plan['chat_limit']) {
            mysqli_query($conn, "UPDATE matrimonial_user_plans SET payment_status='expired' WHERE id=" . (int)$plan['id']);
            $message_error = "आपकी chat limit पूरी हो गई है.";
        } else {
            $safe_msg = mysqli_real_escape_string($conn, $msg);

            mysqli_query($conn, "
                INSERT INTO matrimonial_chats
                (sender_id, receiver_id, message, created_at)
                VALUES
                ($current_user_id, $chat_user_id, '$safe_msg', NOW())
            ");

            $sender_name = vjm_profile_display_name($conn, $current_user_id);
            vjm_add_matrimonial_notification($conn, $chat_user_id, $current_user_id, 'chat_message', 'New Chat Message', $sender_name . ' ने आपको नया message भेजा है.', 'matrimonial-chat.php?id=' . $current_user_id);

            mysqli_query($conn, "
                UPDATE matrimonial_user_plans 
                SET used_chat_count = used_chat_count + 1 
                WHERE id=" . (int)$plan['id']
            );

            header("Location: matrimonial-chat.php?id=$chat_user_id");
            exit;
        }
    }
}

$messages_q = mysqli_query($conn, "
    SELECT * FROM matrimonial_chats
    WHERE 
        (sender_id=$current_user_id AND receiver_id=$chat_user_id)
        OR
        (sender_id=$chat_user_id AND receiver_id=$current_user_id)
    ORDER BY id ASC
");
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <title>Chat - <?= safe($chat_user['full_name'] ?? 'User'); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #070000;
            color: #fff;
            font-family: Arial, sans-serif;
        }

        .chat-wrapper {
            max-width: 850px;
            margin: 30px auto;
            background: #260006;
            border: 1px solid #9b5b13;
            border-radius: 10px;
            overflow: hidden;
        }

        .chat-header {
            padding: 16px 20px;
            background: linear-gradient(90deg, #520010, #210004);
            border-bottom: 1px solid #9b5b13;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .chat-header h2 {
            margin: 0;
            color: #ffc328;
            font-family: Georgia, serif;
        }

        .chat-header a {
            color: #ffd35a;
            text-decoration: none;
            font-weight: bold;
        }

        .chat-box {
            padding: 20px;
            min-height: 420px;
            max-height: 60vh;
            overflow-y: auto;
            background: #100002;
        }

        .msg {
            max-width: 70%;
            padding: 10px 12px;
            border-radius: 10px;
            margin-bottom: 12px;
            line-height: 1.4;
            word-wrap: break-word;
        }

        .mine {
            margin-left: auto;
            background: #ffc328;
            color: #220000;
        }

        .other {
            margin-right: auto;
            background: #fffaf3;
            color: #1b0000;
        }

        .msg small {
            display: block;
            margin-top: 5px;
            opacity: .75;
            font-size: 11px;
        }

        .chat-form {
            padding: 15px;
            border-top: 1px solid #9b5b13;
            display: flex;
            gap: 10px;
            background: #260006;
        }

        .chat-form textarea {
            flex: 1;
            resize: none;
            min-height: 48px;
            padding: 12px;
            border: 1px solid #9b5b13;
            border-radius: 8px;
            outline: none;
            font-size: 15px;
        }

        .chat-form button {
            width: 130px;
            border: 0;
            border-radius: 8px;
            background: linear-gradient(#ffd65b, #ff9d00);
            color: #220000;
            font-weight: bold;
            cursor: pointer;
        }

        .error {
            padding: 12px 15px;
            background: #3b0010;
            color: #ffb3b3;
            border-bottom: 1px solid #ff4d4d;
            text-align: center;
        }

        @media(max-width: 600px) {
            .chat-wrapper {
                margin: 0;
                min-height: 100vh;
                border-radius: 0;
            }

            .msg {
                max-width: 88%;
            }

            .chat-form {
                flex-direction: column;
            }

            .chat-form button {
                width: 100%;
                padding: 12px;
            }
        }
    </style>
</head>
<body>

<div class="chat-wrapper">
    <div class="chat-header">
        <h2>Chat with <?= safe($chat_user['full_name'] ?? 'User'); ?></h2>
        <a href="matrimonial.php">← Back</a>
    </div>

    <?php if ($message_error != "") { ?>
        <div class="error"><?= safe($message_error); ?></div>
    <?php } ?>

    <div class="chat-box" id="chatBox">
        <?php if ($messages_q && mysqli_num_rows($messages_q) > 0) { ?>
            <?php while($m = mysqli_fetch_assoc($messages_q)) { ?>
                <div class="msg <?= ((int)$m['sender_id'] === $current_user_id) ? 'mine' : 'other'; ?>">
                    <?= nl2br(safe($m['message'])); ?>
                    <small><?= date("d-m-Y h:i A", strtotime($m['created_at'])); ?></small>
                </div>
            <?php } ?>
        <?php } else { ?>
            <p style="text-align:center;color:#ffd35a;">No messages yet. Start conversation.</p>
        <?php } ?>
    </div>

    <form method="POST" class="chat-form">
        <input type="hidden" name="receiver_id" value="<?= $chat_user_id; ?>">
        <textarea name="message" placeholder="Type your message..." required></textarea>
        <button type="submit">Send</button>
    </form>
</div>

<script>
    const chatBox = document.getElementById('chatBox');
    chatBox.scrollTop = chatBox.scrollHeight;
</script>

</body>
</html>
