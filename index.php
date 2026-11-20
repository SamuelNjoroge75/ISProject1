<?php
require __DIR__ . '/lib.php';
page_header('Home');
?>
<section class="hero">
  <p class="eyebrow">Business &middot; IT &middot; your voice</p>
  <h1>How is life at the company?</h1>
  <p>Every department has its own short feedback form. Tell us what is going well and what to improve, in about a minute.</p>
  <p class="hero-actions"><a class="button light" href="signup.php">Create an account</a> <a class="button ghost" href="login.php">Log in</a></p>
</section>

<h2>Our departments</h2>
<div class="dept-grid">
  <?php foreach (departments() as $d): ?>
    <div class="dept-tile dept-<?= e($d['slug']) ?>">
      <div class="emoji"><?= $d['emoji'] ?></div>
      <h3><?= e($d['name']) ?></h3>
      <p><?= e($d['blurb']) ?></p>
    </div>
  <?php endforeach; ?>
</div>

<h2>How it works</h2>
<ol class="steps">
  <li class="s1"><strong>Sign up</strong> and pick your department.</li>
  <li class="s2"><strong>Log in</strong> and confirm your department.</li>
  <li class="s3">Your department's <strong>feedback form slides up</strong>.</li>
  <li class="s4">Answer <strong>two quick questions</strong> and tell us what to improve.</li>
</ol>
<?php page_footer(); ?>
