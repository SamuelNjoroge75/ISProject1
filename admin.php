<?php
require __DIR__ . '/lib.php';
$user = require_admin();
$db = db();

$totalUsers = (int)$db->query('SELECT COUNT(*) FROM users')->fetch_row()[0];
$fails24h   = (int)$db->query("SELECT COUNT(*) FROM login_attempts WHERE success = 0 AND attempted_at > (NOW() - INTERVAL 1 DAY)")->fetch_row()[0];
$logins24h  = (int)$db->query("SELECT COUNT(*) FROM login_attempts WHERE success = 1 AND attempted_at > (NOW() - INTERVAL 1 DAY)")->fetch_row()[0];

$minutes = LOCK_MINUTES;
$maxFails = MAX_FAILS;
$maxIpFails = MAX_IP_FAILS;
$stmt = $db->prepare(
    "SELECT 'Username' AS kind, username AS name, COUNT(*) AS fails, MAX(attempted_at) AS last_try
       FROM login_attempts
      WHERE success = 0 AND attempted_at > (NOW() - INTERVAL ? MINUTE)
      GROUP BY username HAVING COUNT(*) >= ?
     UNION ALL
     SELECT 'IP address', ip, COUNT(*), MAX(attempted_at)
       FROM login_attempts
      WHERE success = 0 AND attempted_at > (NOW() - INTERVAL ? MINUTE)
      GROUP BY ip HAVING COUNT(*) >= ?"
);
$stmt->bind_param('iiii', $minutes, $maxFails, $minutes, $maxIpFails);
$stmt->execute();
$locked = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$attempts = $db->query('SELECT attempted_at, username, ip, success FROM login_attempts ORDER BY id DESC LIMIT 50')->fetch_all(MYSQLI_ASSOC);
$users    = $db->query('SELECT id, username, email, role, last_login, created_at FROM users ORDER BY id')->fetch_all(MYSQLI_ASSOC);

page_header('Admin');
?>
<h1>Admin: activity log</h1>
<div class="cards">
  <div class="card"><h2>Users</h2><p class="big"><?= $totalUsers ?></p></div>
  <div class="card"><h2>Logins (24h)</h2><p class="big"><?= $logins24h ?></p></div>
  <div class="card"><h2>Failed (24h)</h2><p class="big"><?= $fails24h ?></p></div>
</div>

<h2>Currently locked (usernames and IP addresses)</h2>
<?php if (!$locked): ?>
  <p>None right now.</p>
<?php else: ?>
  <div class="table-wrap"><table>
    <tr><th>Type</th><th>Name</th><th>Failed attempts</th><th>Last attempt</th></tr>
    <?php foreach ($locked as $row): ?>
      <tr><td><?= e($row['kind']) ?></td><td><?= e($row['name']) ?></td><td><?= (int)$row['fails'] ?></td><td><?= e($row['last_try']) ?></td></tr>
    <?php endforeach; ?>
  </table></div>
<?php endif; ?>

<h2>Recent sign-in attempts</h2>
<div class="table-wrap"><table>
  <tr><th>Time</th><th>Username</th><th>IP</th><th>Result</th></tr>
  <?php foreach ($attempts as $row): ?>
    <tr>
      <td><?= e($row['attempted_at']) ?></td>
      <td><?= e($row['username']) ?></td>
      <td><?= e($row['ip']) ?></td>
      <td><span class="badge <?= $row['success'] ? 'good' : 'bad' ?>"><?= $row['success'] ? 'Success' : 'Failed' ?></span></td>
    </tr>
  <?php endforeach; ?>
</table></div>

<h2>Registered users</h2>
<div class="table-wrap"><table>
  <tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Last login</th><th>Created</th></tr>
  <?php foreach ($users as $row): ?>
    <tr>
      <td><?= (int)$row['id'] ?></td>
      <td><?= e($row['username']) ?></td>
      <td><?= e($row['email']) ?></td>
      <td><?= e($row['role']) ?></td>
      <td><?= e($row['last_login'] ?? '-') ?></td>
      <td><?= e($row['created_at']) ?></td>
    </tr>
  <?php endforeach; ?>
</table></div>
<?php page_footer(); ?>
