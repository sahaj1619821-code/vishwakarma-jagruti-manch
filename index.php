
<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="/vishwakarma-jagruti-manch/css/header.css?v=50">
  
  <title>Vishwakarma Jagruti Manch</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  <style>
    /* INDEX PAGE OLD CSS CONFLICT FIX */
    .vj-header,
    .vj-header * {
      box-sizing: border-box;
    }

    .vj-header .vj-navbar {
      display: block !important;
      width: calc(100% - 40px) !important;
      margin: 0 auto !important;
      padding: 0 !important;
      min-height: 98px !important;
      overflow: hidden !important;
    }

    .vj-header .vj-navbar .vj-menu {
      width: 100% !important;
      min-height: 98px !important;
      margin: 0 !important;
      padding: 0 !important;
      list-style: none !important;
      display: grid !important;
      grid-template-columns: repeat(9, minmax(0, 1fr)) !important;
      gap: 0 !important;
      background: transparent !important;
    }

    .vj-header .vj-navbar .vj-menu li {
      width: 100% !important;
      min-width: 0 !important;
      margin: 0 !important;
      padding: 0 !important;
      list-style: none !important;
      display: block !important;
    }

    .vj-header .vj-navbar .vj-menu li a {
      width: 100% !important;
      height: 98px !important;
      margin: 0 !important;
      padding: 12px 8px !important;
      float: none !important;
      display: flex !important;
      flex-direction: column !important;
      align-items: center !important;
      justify-content: center !important;
      text-align: center !important;
      gap: 0 !important;
    }

    .vj-header .vj-navbar .vj-menu li a i {
      display: block !important;
      margin: 0 0 8px 0 !important;
    }

    @media (max-width: 900px) {
      .vj-header .vj-navbar {
        overflow: visible !important;
      }

      .vj-header .vj-navbar .vj-menu {
        display: none !important;
        grid-template-columns: 1fr !important;
      }

      .vj-header .vj-navbar #vj-menu-toggle:checked ~ .vj-menu {
        display: grid !important;
      }

      .vj-header .vj-navbar .vj-menu li a {
        height: 75px !important;
        flex-direction: row !important;
        justify-content: flex-start !important;
        text-align: left !important;
        gap: 12px !important;
      }

      .vj-header .vj-navbar .vj-menu li a i {
        margin: 0 !important;
      }
    }
  </style>

</head>

<body>
  
<?php include 'header.php'; ?>
<!-- Popup Start -->
<div class="popup-overlay" id="popupAd">
  <div class="popup-box">
    <button class="close-btn" onclick="closePopup()">×</button>
    <img src="images/amrti-popup.jpeg" alt="Advertisement Popup">
  </div>
</div>
<!-- Popup End --><section class="hero">

    <div class="hero-content">
        <span class="welcome">Welcome to</span>

        <h1>
            Vishwakarma<br>       
           <span>Jagruti Manch</span>
        </h1>

        <p>
            Connecting Vishwakarma community
            for a better tomorrow.
        </p>
    </div>

    <div class="quote-box">
        हमारा संगठन <br>
        हमारी पहचान <br>
        हमारा समाज <br>
        हमारा अभिमान
    </div>

</section>
  <section class="stats">
    <div class="stat"><i class="fa-solid fa-users"></i><h3>50K+</h3><p>Registered Members</p></div>
    <div class="stat"><i class="fa-solid fa-gopuram"></i><h3>1000+</h3><p>Temples</p></div>
    <div class="stat"><i class="fa-solid fa-book-open"></i><h3>5000+</h3><p>eBooks</p></div>
    <div class="stat"><i class="fa-solid fa-bed"></i><h3>200+</h3><p>Hostels</p></div>
    <div class="stat"><i class="fa-solid fa-users"></i><h3>1000+</h3><p>Matrimonial Profiles</p></div>
    <div class="stat"><i class="fa-solid fa-shield-halved"></i><h3>25+</h3><p>Years of Trust</p></div>
  </section>

  <section class="cards">
    <div class="card">
      <img src="images/temple.jpeg" alt="">
      <div class="icon-circle"><i class="fa-solid fa-gopuram"></i></div>
      <h3>Temples Directory</h3>
      <p>Find nearby Vishwakarma Temples</p>
      <a href="temple.php"><button>View More →</button></a>
    </div>

    <div class="card">
      <img src="images/couple.jpeg" alt="">
      <div class="icon-circle"><i class="fa-solid fa-users"></i></div>
      <h3>Matrimonial Services</h3>
      <p>Find your perfect life partner within community</p>
      <a href="matrimonial-entry.php"><button>View More →</button></a>
    </div>

    <div class="card">
      <img src="images/ebook1.jpeg" alt="">
      <div class="icon-circle"><i class="fa-solid fa-book-open"></i></div>
      <h3>eBooks Library</h3>
      <p>Read and download spiritual & technical books</p>
      <a href="ebook.php"><button>View More →</button></a>
    </div>

    <div class="card">
      <img src="images/hostal1.jpeg" alt="">
      <div class="icon-circle"><i class="fa-solid fa-bed"></i></div>
      <h3>Hostel Booking</h3>
      <p>Book hostels & dharamshalas online easily.</p>
      <a href="hostel-booking.php"><button>View More →</button></a>
    </div>

    <div class="card">
      <img src="images/address.jpeg" alt="">
      <div class="icon-circle"><i class="fa-solid fa-address-book"></i></div>
      <h3>Address Book</h3>
      <p>Connect with community members easily</p>
      <a href="address-book.php"><button>View More →</button></a>
    </div>

    <div class="card">
      <img src="images/gallery1.jpeg" alt="">
      <div class="icon-circle"><i class="fa-solid fa-image"></i></div>
      <h3>Photo Gallery</h3>
      <p>See our community events & memories</p>
      <a href="gallery.php"><button>View More →</button></a>
    </div>
  </section>

  <footer>
    <div class="footer-grid">
      
      <div>
        <h3>About Us</h3>
        <p>Vishwakarma Jagruti Manch is dedicated to the upliftment and development of the Vishwakarma community.</p>
      </div>

      <div>
        <h3>Quick Links</h3>
        <ul>
          <li>Home</li>
          <li>Temples</li>
          <li>Matrimonial</li>
          <li>eBooks</li>
          <li>Gallery</li>
        </ul>
      </div>

      <div>
        <h3>Contact Info</h3>
        <p><i class="fa-solid fa-location-dot"></i> Sirohi, Rajasthan, India</p>
        <p><i class="fa-solid fa-phone"></i> 8097523298</p>
        <p><i class="fa-solid fa-envelope"></i> info@vishwakarmajagrutimanch.com</p>
        <p><i class="fa-solid fa-clock"></i> Mon - Sat: 09:00 AM - 06:00 PM</p>
      </div>
      <div>
        <h3>Our Mission</h3>
        <ul>
          <li>To unite the strong, educated and united Vishwakarma community.</li>
          <li>To connect, inspire and empower every member.</li>
          <li>To promote Equality, Service, Sanskar and Prosperity.</li>
        </ul>
      </div>

      <div>
        <h3>Stay Connected</h3>
        <div class="footer-social">
          <i class="fa-brands fa-facebook-f"></i>
          <i class="fa-brands fa-instagram"></i>
          <i class="fa-brands fa-youtube"></i>
          <i class="fa-brands fa-whatsapp"></i>
          <i class="fa-brands fa-telegram"></i>
          <i class="fa-brands fa-linkedin-in"></i>
        </div>
        <div class="member-box">
          <h3>Total Members<br><span style="color:#ffc928">50,000+</span></h3>
          <p>And Growing Stronger Every Day</p>
        </div>
      </div>
    </div>

    <div class="bottom">
      © 2026 Vishwakarma Jagruti Manch. All Rights Reserved.  
      Designed with ❤️ for Vishwakarma Community
    </div>
  </footer>

<script>
  function closePopup() {
    document.getElementById("popupAd").style.display = "none";
  }

  // page open hote hi popup show hoga
  window.onload = function () {
    document.getElementById("popupAd").style.display = "flex";
  };

  // agar 5 second baad auto close karna ho to niche wali line use karein
  // setTimeout(closePopup, 5000);
</script>
</body>
</html>