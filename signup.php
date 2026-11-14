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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim((string)($_POST['username'] ?? ''));
    $email    = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['confirm'] ?? '');

    // Server-side validation (the HTML checks alone are easy to bypass)
    if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
        $errors[] = 'Username must be 3-30 characters: letters, numbers or underscore.';
    }
    if (strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
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
            try {
                $stmt = $db->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)');
                $stmt->bind_param('sss', $username, $email, $hash);
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
<h1>Create an account</h1>
<?php if ($success): ?>
  <p class="ok">Account created. <a href="login.php">Log in</a></p>
<?php else: ?>
  <?php foreach ($errors as $err): ?><p class="error"><?= e($err) ?></p><?php endforeach; ?>
  <form method="POST" action="signup.php" autocomplete="on">
    <?= csrf_field() ?>
    <label>Username
      <input type="text" name="username" required minlength="3" maxlength="30"
             pattern="[A-Za-z0-9_]{3,30}" autocomplete="username" value="<?= e($username) ?>">
    </label>
    <label>Email
      <input type="email" name="email" required maxlength="100" autocomplete="email" value="<?= e($email) ?>">
    </label>
    <label>Password
      <input type="password" id="password" name="password" required minlength="10" maxlength="128"
             autocomplete="new-password">
    </label>
    <div class="strength">
      <meter id="strength-meter" min="0" max="4" low="2" high="3" optimum="4" value="0"></meter>
      <span id="strength-text">Use 10+ characters with letters, numbers and symbols.</span>
    </div>
    <label>Confirm password
      <input type="password" name="confirm" required minlength="10" maxlength="128" autocomplete="new-password">
    </label>
    <button type="submit">Sign up</button>
  </form>
  <script src="strength.js" defer></script>
<?php endif; ?>
<?php page_footer(); ?>
