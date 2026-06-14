<!DOCTYPE html>
<html lang="hi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contact Us - Vishwakarma Jagruti Manch</title>

  <link rel="stylesheet" href="css/contact.css?v=20260613_temple_effect">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<?php include 'header.php'; ?>

<section class="contact-hero">
  <div class="hero-content">
    <div class="hero-text">
      <h1>Contact Us</h1>
      <p>हमसे संपर्क करें और Vishwakarma community को मजबूत बनाने में साथ दें।</p>
    </div>

    <a href="testmonial.php" class="feedback-btn">
      <i class="fa-solid fa-comments"></i> Feedback
    </a>
  </div>
</section>

<section class="contact-section">
  <div class="contact-card">
    <i class="fa-solid fa-phone-volume"></i>
    <h3>Call Us</h3>
    <p>8097523298</p>
    <a href="tel:8097523298">Call Now</a>
  </div>

  <div class="contact-card">
    <i class="fa-solid fa-envelope-open-text"></i>
    <h3>Email Us</h3>
    <p>info@vishwakarmajagrutimanch.com</p>
    <a href="mailto:info@vishwakarmajagrutimanch.com">Send Email</a>
  </div>

  <div class="contact-card">
    <i class="fa-solid fa-location-dot"></i>
    <h3>Our Address</h3>
    <p>Sirohi, Rajasthan, India</p>
    <a href="#map">View Location</a>
  </div>
</section>

<!-- HORIZONTAL LINE: SEND MESSAGE | CONTACT INFORMATION | GOOGLE MAP -->
<section class="main-contact contact-three-line">

  <div class="contact-form-box">
    <h2>Send Your Message</h2>
    <p>नीचे दिया गया फॉर्म भरकर हमें अपना संदेश भेजें।</p>

    <form action="#" method="post">
      <div class="input-group">
        <input type="text" name="name" placeholder="Your Name" required>
        <input type="tel" name="phone" placeholder="Mobile Number" required>
      </div>

      <div class="input-group">
        <input type="email" name="email" placeholder="Email Address">
        <input type="text" name="subject" placeholder="Subject">
      </div>

      <textarea name="message" placeholder="Write your message here..." required></textarea>

      <button type="submit">
        <i class="fa-solid fa-paper-plane"></i> Send Message
      </button>
    </form>
  </div>

  <div class="contact-info-box">
    <h2>Contact Information</h2>

    <div class="info-row">
      <i class="fa-solid fa-phone"></i>
      <div>
        <h4>Phone Number</h4>
        <p>8097523298</p>
      </div>
    </div>

    <div class="info-row">
      <i class="fa-solid fa-envelope"></i>
      <div>
        <h4>Email Address</h4>
        <p>info@vishwakarmajagrutimanch.com</p>
      </div>
    </div>

    <div class="info-row">
      <i class="fa-solid fa-location-dot"></i>
      <div>
        <h4>Location</h4>
        <p>Sirohi, Rajasthan, India</p>
      </div>
    </div>

    <div class="info-row">
      <i class="fa-solid fa-clock"></i>
      <div>
        <h4>Working Time</h4>
        <p>Mon - Sat : 09:00 AM - 06:00 PM</p>
      </div>
    </div>

    <div class="social-icons">
      <a href="#"><i class="fa-brands fa-facebook-f"></i></a>
      <a href="#"><i class="fa-brands fa-instagram"></i></a>
      <a href="#"><i class="fa-brands fa-youtube"></i></a>
      <a href="#"><i class="fa-brands fa-whatsapp"></i></a>
      <a href="#"><i class="fa-brands fa-telegram"></i></a>
    </div>
  </div>

  <section class="map-section" id="map">
    <h2>Find Us On Map</h2>
    <iframe
      src="https://www.google.com/maps?q=Sirohi,Rajasthan,India&output=embed"
      loading="lazy"
      referrerpolicy="no-referrer-when-downgrade">
    </iframe>
  </section>

</section>

<footer>
  <p>© 2026 Vishwakarma Jagruti Manch. All Rights Reserved.</p>
  <p>Designed with ❤️ for Vishwakarma Community</p>
</footer>

</body>
</html>
