<?php
require __DIR__ . '/lib.php';

if (current_user()) {
    header('Location: dashboard.php');
    exit;
}

// A throwaway hash so unknown usernames take about as long to check as real ones
const DUMMY_HASH = '$2y$10$iRWDbmWaYCEubB8Z8v98z.mPR6rTuKfvzCEpkSVrN/MxyNjV8hkSG';

$error = '';
$lockSeconds = 0;
$deptChoice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $deptChoice = (string)($_POST['department'] ?? '');
    $ip = client_ip();

    if (is_locked($username, $ip)) {
        // Attempts made while locked are not logged, so the lock always ends on time
        $error = 'Too many failed attempts. Try again in ' . LOCK_MINUTES . ' minutes.';
        $lockSeconds = lock_seconds_left($username, $ip);
    } else {
        $stmt = db()->prepare('SELECT u.id, u.username, u.password, u.role, u.last_login, d.slug, d.name
                                 FROM users u JOIN departments d ON d.id = u.department_id WHERE u.username = ?');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->bind_result($id, $dbUser, $dbHash, $dbRole, $dbLast, $dbDeptSlug, $dbDeptName);
        $found = $stmt->fetch();
        $stmt->close();

        if ($found) {
            $ok = password_verify($password, $dbHash);
        } else {
            password_verify($password, DUMMY_HASH);   // wasted work on purpose, result ignored
            $ok = false;
        }

        $deptMismatch = false;
        if ($ok && $deptChoice !== $dbDeptSlug) {
            // Right password, wrong department: do not log in and do not count it as a failed password
            $error = 'That is not the department on your account. Pick the one you signed up with.';
            $ok = false;
            $deptMismatch = true;
        }

        if ($ok) {
            log_attempt($username, $ip, true);
            session_regenerate_id(true);               // stops session fixation
            $_SESSION['user_id']    = (int)$id;
            $_SESSION['username']   = $dbUser;
            $_SESSION['role']       = $dbRole;
            $_SESSION['dept']       = $dbDeptSlug;
            $_SESSION['dept_name']  = $dbDeptName;
            $_SESSION['prev_login'] = $dbLast;
            $_SESSION['last_activity'] = time();

            $upd = db()->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
            $upd->bind_param('i', $id);
            $upd->execute();
            $upd->close();

            // Upgrade old hashes automatically if the algorithm changes
            if (password_needs_rehash($dbHash, preferred_algo())) {
                $newHash = hash_password($password);
                $upd = db()->prepare('UPDATE users SET password = ? WHERE id = ?');
                $upd->bind_param('si', $newHash, $id);
                $upd->execute();
                $upd->close();
            }
            header('Location: dashboard.php');
            exit;
        }

        if (!$deptMismatch) {
            log_attempt($username, $ip, false);
            [$ipFails, $userFails] = recent_failures($username, $ip);
            $left = MAX_FAILS - $userFails;
            if ($left <= 0 || $ipFails >= MAX_IP_FAILS) {
                $error = 'Too many failed attempts. Try again in ' . LOCK_MINUTES . ' minutes.';
                $lockSeconds = lock_seconds_left($username, $ip);
            } else {
                $error = 'Incorrect username or password. ' . $left . ' attempt' . ($left === 1 ? '' : 's')
                       . ' left before a temporary lock.';
            }
        }
    }
}

page_header('Log in');
?>
<section class="auth wide">
  <h1>Welcome back &#128075;</h1>
  <p class="lead">Log in and tell us which department you are in.</p>
  <?php if ($lockSeconds > 0): ?>
    <div class="notice lock" role="alert">&#9749; <strong>Too many failed attempts.</strong> Take a breather and try again in
      <strong id="countdown" data-seconds="<?= (int)$lockSeconds ?>"><?= (int)ceil($lockSeconds / 60) ?> min</strong>.</div>
  <?php elseif ($error): ?>
    <div class="notice error" role="alert">&#128584; <?= e($error) ?></div>
  <?php endif; ?>
  <form method="POST" action="login.php">
    <?= csrf_field() ?>
    <label>Username
      <input type="text" name="username" required maxlength="30" autocomplete="username">
    </label>
    <?= department_picker($deptChoice) ?>
    <label>Password
      <div class="pw">
        <input type="password" id="login-password" name="password" required maxlength="128" autocomplete="current-password">
        <button type="button" class="eye" data-target="login-password" aria-label="Show password">Show</button>
      </div>
    </label>
    <button type="submit">Log in</button>
  </form>
  <p>No account yet? <a href="signup.php">Sign up, it takes 20 seconds</a></p>
</section>
<?php page_footer(); ?>
