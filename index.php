<?php
require __DIR__ . '/lib.php';
page_header('Home');
?>
<h1>ISProject1</h1>
<p>A secure PHP and MariaDB web application. Create an account, log in, and see your activity.</p>
<div class="cards">
  <div class="card"><h2>Hashed passwords</h2><p>Passwords are stored as Argon2id hashes, never as plain text.</p></div>
  <div class="card"><h2>Login protection</h2><p>Too many wrong attempts lock the login for a short time.</p></div>
  <div class="card"><h2>Activity log</h2><p>Administrators can review every sign-in attempt.</p></div>
</div>
<p><a class="button" href="signup.php">Create an account</a> <a class="button secondary" href="login.php">Log in</a></p>
<?php page_footer(); ?>
