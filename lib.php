<?php
declare(strict_types=1);

/* ISProject1 shared helpers: security headers, sessions, database, CSRF,
   login lockout, page layout. Every page starts with: require __DIR__ . '/lib.php'; */

const MAX_FAILS    = 5;     // wrong passwords allowed per username...
const MAX_IP_FAILS = 20;    // ...and per IP address (higher, because many people can share one IP)
const LOCK_MINUTES = 15;    // ...within this many minutes
const IDLE_SECONDS = 1800;  // log out after 30 minutes without activity

function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function is_https(): bool {
    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}

/* ---------- Security headers (creative feature: hardening) ---------- */
function send_security_headers(): void {
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    header_remove('X-Powered-By');
}

/* ---------- Sessions ---------- */
function start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Strict',
        'secure'   => is_https(),
    ]);
    session_name('isp_session');
    session_start();

    // Idle timeout
    if (isset($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity']) > IDLE_SECONDS) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last_activity'] = time();
}

/* ---------- Configuration and database ---------- */
function config(): array {
    static $cfg = null;
    if ($cfg === null) {
        $file = __DIR__ . '/config.php';
        if (!is_file($file)) {
            http_response_code(500);
            exit('Missing config.php. Copy config.example.php to config.php and fill it in.');
        }
        $cfg = require $file;
    }
    return $cfg;
}

function db(): mysqli {
    static $conn = null;
    if ($conn === null) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $c = config();
        try {
            $conn = new mysqli($c['db_host'], $c['db_user'], $c['db_pass'], $c['db_name']);
            $conn->set_charset('utf8mb4');
        } catch (mysqli_sql_exception $ex) {
            error_log('ISProject1 DB connection failed: ' . $ex->getMessage());
            http_response_code(503);
            exit('Service temporarily unavailable.');
        }
    }
    return $conn;
}

/* ---------- CSRF protection ---------- */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void {
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals((string)($_SESSION['csrf'] ?? ''), $sent)) {
        http_response_code(403);
        exit('Invalid or expired form. Go back, refresh the page and try again.');
    }
}

/* ---------- Password hashing ---------- */
function preferred_algo(): string {
    return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
}

function hash_password(string $password): string {
    return password_hash($password, preferred_algo());
}

/* ---------- Login lockout (creative feature: rate limiting) ---------- */
function client_ip(): string {
    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/** Returns [failures from this IP, failures for this username] in the lock window. */
function recent_failures(string $username, string $ip): array {
    $stmt = db()->prepare(
        'SELECT COALESCE(SUM(ip = ?), 0), COALESCE(SUM(username = ?), 0)
           FROM login_attempts
          WHERE success = 0 AND attempted_at > (NOW() - INTERVAL ? MINUTE)'
    );
    $minutes = LOCK_MINUTES;
    $stmt->bind_param('ssi', $ip, $username, $minutes);
    $stmt->execute();
    $stmt->bind_result($ipFails, $userFails);
    $stmt->fetch();
    $stmt->close();
    return [(int)$ipFails, (int)$userFails];
}

function is_locked(string $username, string $ip): bool {
    [$ipFails, $userFails] = recent_failures($username, $ip);
    return $userFails >= MAX_FAILS || $ipFails >= MAX_IP_FAILS;
}

function log_attempt(string $username, string $ip, bool $success): void {
    // Keep the log clean: printable ASCII only, at most 30 characters (no mbstring needed)
    $username = substr((string)preg_replace('/[^\x20-\x7E]/', '?', $username), 0, 30);
    $flag = $success ? 1 : 0;
    $stmt = db()->prepare('INSERT INTO login_attempts (username, ip, success) VALUES (?, ?, ?)');
    $stmt->bind_param('ssi', $username, $ip, $flag);
    $stmt->execute();
    $stmt->close();
}

/* ---------- Authentication helpers ---------- */
function current_user(): ?array {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id'       => (int)$_SESSION['user_id'],
        'username' => (string)$_SESSION['username'],
        'role'     => (string)($_SESSION['role'] ?? 'user'),
    ];
}

function require_login(): array {
    $user = current_user();
    if ($user === null) {
        header('Location: login.php');
        exit;
    }
    header('Cache-Control: no-store');
    return $user;
}

function require_admin(): array {
    $user = require_login();
    // Re-check the role in the database, not just the session
    $stmt = db()->prepare('SELECT role FROM users WHERE id = ?');
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $stmt->bind_result($role);
    $found = $stmt->fetch();
    $stmt->close();
    if (!$found || $role !== 'admin') {
        http_response_code(403);
        page_header('Forbidden');
        echo '<h1>403: Not allowed</h1><p class="error">Only administrators can open this page.</p>';
        page_footer();
        exit;
    }
    return $user;
}

/* ---------- Page layout ---------- */
function page_header(string $title): void {
    $user = current_user();
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . e($title) . ' | ISProject1</title>';
    echo '<link rel="stylesheet" href="style.css"></head><body>';
    echo '<header><a class="brand" href="index.php">ISProject1</a><nav>';
    if ($user) {
        echo '<a href="dashboard.php">Dashboard</a>';
        if ($user['role'] === 'admin') {
            echo '<a href="admin.php">Admin</a>';
        }
        echo '<a href="status.php">Status</a>';
        echo '<form class="inline" method="POST" action="logout.php">' . csrf_field()
           . '<button class="linklike" type="submit">Log out (' . e($user['username']) . ')</button></form>';
    } else {
        echo '<a href="login.php">Log in</a><a href="signup.php">Sign up</a><a href="status.php">Status</a>';
    }
    echo '</nav></header><main>';
}

function page_footer(): void {
    echo '</main></body></html>';
}

/* ---------- Runs on every page that includes this file ---------- */
send_security_headers();
if (!defined('NO_SESSION')) {
    start_session();
}
