<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}

$role = $_SESSION['admin_role'] ?? '';

function allow_roles($allowed_roles = []) {
    global $role;

    if (!in_array($role, $allowed_roles)) {
        echo "<script>
            alert('Aapko is section ki permission nahi hai');
            window.location.href='index.php';
        </script>";
        exit;
    }
}
?>

<?php include 'includes/footer.php'; ?>
