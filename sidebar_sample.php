<?php
/* includes/sidebar.php me links ko is tarah condition se show kare.
   Dhyan rahe: sidebar include hone se pehle page me auth.php include hona chahiye.
*/
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">


<ul class="sidebar-menu side-menu">

    <?php if (canOpenPage('dashboard.php')) { ?>
        <li><a href="dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
    <?php } ?>

    <?php if (canOpenPage('users.php')) { ?>
        <li><a href="users.php"><i class="fa-solid fa-users-gear"></i> Users / Roles</a></li>
    <?php } ?>

    <?php if (canOpenPage('hostel-booking.php')) { ?>
        <li><a href="hostel-booking.php"><i class="fa-solid fa-bed"></i> Hostel Booking</a></li>
    <?php } ?>

    <?php if (canOpenPage('matrimonial.php')) { ?>
        <li><a href="matrimonial.php"><i class="fa-solid fa-users"></i> Matrimonial</a></li>
    <?php } ?>

    <?php if (canOpenPage('ebooks.php')) { ?>
        <li><a href="ebooks.php"><i class="fa-solid fa-book-open"></i> eBooks</a></li>
    <?php } ?>

    <?php if (canOpenPage('gallery.php')) { ?>
        <li><a href="gallery.php"><i class="fa-solid fa-image"></i> Gallery</a></li>
    <?php } ?>

    <?php if (canOpenPage('temples.php')) { ?>
        <li><a href="temples.php"><i class="fa-solid fa-gopuram"></i> Temples</a></li>
    <?php } ?>

    <?php if (canOpenPage('address-book.php')) { ?>
        <li><a href="address-book.php"><i class="fa-solid fa-address-book"></i> Address Book</a></li>
    <?php } ?>

    <?php if (canOpenPage('enquiries.php')) { ?>
        <li><a href="enquiries.php"><i class="fa-solid fa-message"></i> Enquiries</a></li>
    <?php } ?>

    <li><a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>

</ul>

<link rel="stylesheet" href="admin-public-theme.css?v=1">
