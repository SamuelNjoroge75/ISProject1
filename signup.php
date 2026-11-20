<?php
require __DIR__ . '/lib.php';

if (current_user()) {
    header('Location: dashboard.php');
    exit;
}

$errors  = [];
$success = false;
$username = '';
$email = '';
$deptSlug = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim((string)($_POST['username'] ?? ''));
    $email    = trim((string)($_POST['email'] ?? ''));
    $deptSlug = (string)($_POST['department'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['confirm'] ?? '');
    $departments = departments();

    // Server-side validation (the HTML checks alone are easy to bypass)
    if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
        $errors[] = 'Username must be 3-30 characters: letters, numbers or underscore.';
    }
    if (strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if (!isset($departments[$deptSlug])) {
        $errors[] = 'Please pick your department.';
    }
    if (strlen($password) < 10 || strlen($password) > 128
        || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors[] = 'Password must be 10-128 characters and include at least one letter and one number.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        $db = db();
        $stmt = $db->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $stmt->bind_param('ss', $username, $email);
        $stmt->execute();
        $stmt->store_result();
        $taken = $stmt->num_rows > 0;
        $stmt->close();

        if ($taken) {
            $errors[] = 'That username or email is already registered.';
        } else {
            $hash = hash_password($password);   // only the hash is stored
            $deptId = $departments[$deptSlug]['id'];
            try {
                $stmt = $db->prepare('INSERT INTO users (username, email, password, department_id) VALUES (?, ?, ?, ?)');
                $stmt->bind_param('sssi', $username, $email, $hash, $deptId);
                $stmt->execute();
                $stmt->close();
                $success = true;
            } catch (mysqli_sql_exception $ex) {
                $errors[] = ($ex->getCode() === 1062)
                    ? 'That username or email is already registered.'
                    : 'Could not create the account. Please try again.';
            }
        }
    }
}

page_header('Sign up');
?>
<section class="auth wide">
  <h1>Join the team &#127881;</h1>
  <p class="lead">Create your account and tell us which department you work in.</p>
  <?php if ($success): ?>
    <div class="notice ok" data-confetti>&#127881; <strong>Account created!</strong> Welcome aboard. <a href="login.php">Log in now</a></div>
  <?php else: ?>
    <?php foreach ($errors as $err): ?><div class="notice error" role="alert">&#128584; <?= e($err) ?></div><?php endforeach; ?>
    <form method="POST" action="signup.php" autocomplete="on">
      <?= csrf_field() ?>
      <label>Username
        <input type="text" name="username" required minlength="3" maxlength="30"
               pattern="[A-Za-z0-9_]{3,30}" autocomplete="username" value="<?= e($username) ?>">
        <small>3-30 letters, numbers or underscores.</small>
      </label>
      <label>Email
        <input type="email" name="email" required maxlength="100" autocomplete="email" value="<?= e($email) ?>">
      </label>
      <?= department_picker($deptSlug) ?>
      <label>Password
        <div class="pw">
          <input type="password" id="password" name="password" required minlength="10" maxlength="128"
                 autocomplete="new-password">
          <button type="button" class="eye" data-target="password" aria-label="Show password">Show</button>
        </div>
      </label>
      <div class="strength" aria-live="polite">
        <div class="bar"><span id="strength-fill" class="fill lvl-0"></span></div>
        <span id="strength-text">Use 10+ characters with letters, numbers and symbols.</span>
      </div>
      <label>Confirm password
        <input type="password" name="confirm" required minlength="10" maxlength="128" autocomplete="new-password">
      </label>
      <button type="submit">Create my account</button>
    </form>
    <p>Already have an account? <a href="login.php">Log in</a></p>
    <script src="strength.js" defer></script>
  <?php endif; ?>
</section>
<?php page_footer(); ?>
