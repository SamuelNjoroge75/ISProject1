<?php
require __DIR__ . '/lib.php';
$user = require_login();
$db = db();
$prev = $_SESSION['prev_login'] ?? null;
$thanks = ($_GET['thanks'] ?? '') === '1';
$dept = departments()[$user['dept']] ?? null;

$stmt = $db->prepare('SELECT COUNT(*) FROM login_attempts WHERE username = ? AND success = 1');
$stmt->bind_param('s', $user['username']);
$stmt->execute();
$stmt->bind_result($logins);
$stmt->fetch();
$stmt->close();
$logins = (int)$logins;

$stmt = $db->prepare('SELECT created_at FROM users WHERE id = ?');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$stmt->bind_result($since);
$stmt->fetch();
$stmt->close();

$tips = [
    'Use a passphrase: four random words are stronger and easier to remember than one clever word.',
    'Never reuse a password. If one site leaks, the others stay safe.',
    'A password manager remembers everything for you, so every password can be long and unique.',
    'Check for the padlock in the address bar before typing a password.',
    'Log out on shared computers. It takes one click.',
    'If an email asks you to log in urgently, go to the website yourself instead of clicking the link.',
    'Updates fix security holes. Install them when your device asks.',
    'Two-step login (2FA) stops most account takeovers, even when a password leaks.',
];
$tip = $tips[(int)date('z') % count($tips)];
$avatar = 'av' . (abs(crc32($user['username'])) % 6);

page_header('Dashboard');
?>
<div class="profile">
  <div class="avatar <?= e($avatar) ?>" aria-hidden="true"><?= e(strtoupper(substr($user['username'], 0, 1))) ?></div>
  <div>
    <h1 id="greeting" data-name="<?= e($user['username']) ?>">Welcome, <?= e($user['username']) ?> &#128075;</h1>
    <p><span class="badge"><?= e($user['role']) ?></span>
       <?php if ($dept): ?><span class="badge dept-pill dept-<?= e($dept['slug']) ?>"><?= $dept['emoji'] ?> <?= e($dept['name']) ?></span><?php endif; ?></p>
  </div>
</div>

<?php if ($thanks): ?>
  <div class="notice ok" data-confetti>&#127881; <strong>Thank you!</strong> Your feedback was saved. It really helps us improve.</div>
<?php elseif (!$prev): ?>
  <div class="notice ok" data-confetti>&#127881; This is your first login. Welcome!</div>
<?php endif; ?>

<?php if ($dept): ?>
  <section class="dept-card slide-up dept-<?= e($dept['slug']) ?>" aria-label="Your department feedback form">
    <div class="big-emoji" aria-hidden="true"><?= $dept['emoji'] ?></div>
    <div class="dept-text">
      <p class="eyebrow">Your department</p>
      <h2><?= e($dept['name']) ?></h2>
      <p><?= e($dept['blurb']) ?> Your team's feedback form is ready, and it takes about a minute.</p>
    </div>
    <a class="button light" href="feedback.php">Share your feedback &rarr;</a>
  </section>
<?php endif; ?>

<div class="cards">
  <div class="card c1"><h2>Logins so far</h2><p class="big"><?= $logins ?></p></div>
  <div class="card c2"><h2>Previous login</h2><p><?= $prev ? e($prev) : 'This is your first login.' ?></p></div>
  <div class="card c3"><h2>Member since</h2><p><?= e(substr((string)$since, 0, 10)) ?></p></div>
</div>

<div class="cards two">
  <div class="card tip c4"><h2>&#128161; Tip of the day</h2><p><?= e($tip) ?></p></div>
  <div class="card c5"><h2>&#128737;&#65039; How your account is protected</h2>
    <ul class="checks">
      <li>Password stored as an Argon2id hash</li>
      <li>Lockout after repeated wrong passwords</li>
      <li>Automatic log out after 30 idle minutes</li>
      <li>Forms protected against forged requests</li>
    </ul>
  </div>
</div>

<?php if ($user['role'] === 'admin'): ?>
  <p><a class="button" href="admin.php">Open admin page</a></p>
<?php endif; ?>
<?php page_footer(); ?>
