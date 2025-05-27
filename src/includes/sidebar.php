<div class="profile_nav">
  <ul>
    <li><a href="profile.php">Profile Settings</a></li>

    <?php if (!$_SESSION['oauth']) { ?>
      <li><a href="update-password.php">Update Password</a></li>
    <?php } ?>
    <li><a href="get-verified.php">Get Verified</a></li>
    <li><a href="my-booking.php">My Booking</a></li>
    <li><a href="post-testimonial.php">Post a Testimonial</a></li>
    <li><a href="my-testimonials.php">My Testimonials</a></li>
    <li><a href="logout.php">Sign Out</a></li>
  </ul>
</div>
</div>