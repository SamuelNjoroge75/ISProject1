<?php
require __DIR__ . '/lib.php';
$user = require_login();
$prev = $_SESSION['prev_login'] ?? null;
page_header('Dashboard');
?>
<h1>Welcome, <?= e($user['username']) ?></h1>
<p class="ok">You are logged in.</p>
<div class="cards">
  <div class="card"><h2>Role</h2><p><span class="badge"><?= e($user['role']) ?></span></p></div>
  <div class="card"><h2>Previous login</h2><p><?= $prev ? e($prev) : 'This is your first login.' ?></p></div>
</div>
<?php if ($user['role'] === 'admin'): ?>
  <p><a class="button" href="admin.php">Open admin page</a></p>
<?php endif; ?>
<?php page_footer(); ?>
