<?php
require __DIR__ . '/lib.php';

if (current_user()) {
    header('Location: dashboard.php');
    exit;
}

// A throwaway hash so unknown usernames take about as long to check as real ones
const DUMMY_HASH = '$2y$10$iRWDbmWaYCEubB8Z8v98z.mPR6rTuKfvzCEpkSVrN/MxyNjV8hkSG';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $ip = client_ip();

    if (is_locked($username, $ip)) {
        // Attempts made while locked are not logged, so the lock always ends on time
        $error = 'Too many failed attempts. Try again in ' . LOCK_MINUTES . ' minutes.';
    } else {
        $stmt = db()->prepare('SELECT id, username, password, role, last_login FROM users WHERE username = ?');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->bind_result($id, $dbUser, $dbHash, $dbRole, $dbLast);
        $found = $stmt->fetch();
        $stmt->close();

        if ($found) {
            $ok = password_verify($password, $dbHash);
        } else {
            password_verify($password, DUMMY_HASH);   // wasted work on purpose, result ignored
            $ok = false;
        }

        if ($ok) {
            log_attempt($username, $ip, true);
            session_regenerate_id(true);               // stops session fixation
            $_SESSION['user_id']    = (int)$id;
            $_SESSION['username']   = $dbUser;
            $_SESSION['role']       = $dbRole;
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

        log_attempt($username, $ip, false);
        [$ipFails, $userFails] = recent_failures($username, $ip);
        $left = MAX_FAILS - $userFails;
        if ($left <= 0 || $ipFails >= MAX_IP_FAILS) {
            $error = 'Too many failed attempts. Try again in ' . LOCK_MINUTES . ' minutes.';
        } else {
            $error = 'Incorrect username or password. ' . $left . ' attempt' . ($left === 1 ? '' : 's')
                   . ' left before a temporary lock.';
        }
    }
}

page_header('Log in');
?>
<h1>Log in</h1>
<?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
<form method="POST" action="login.php">
  <?= csrf_field() ?>
  <label>Username
    <input type="text" name="username" required maxlength="30" autocomplete="username">
  </label>
  <label>Password
    <input type="password" name="password" required maxlength="128" autocomplete="current-password">
  </label>
  <button type="submit">Log in</button>
</form>
<p>No account yet? <a href="signup.php">Sign up</a></p>
<?php page_footer(); ?>
