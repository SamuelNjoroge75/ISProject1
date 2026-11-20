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

$summary = $db->query(
    "SELECT d.slug, d.name, COUNT(f.id) AS responses, ROUND(AVG(f.rating), 1) AS avg_rating,
            ROUND(100 * SUM(f.recommend IN ('definitely','probably')) / NULLIF(COUNT(f.id), 0)) AS rec_pct
       FROM departments d LEFT JOIN feedback f ON f.department_id = d.id
      GROUP BY d.id, d.slug, d.name ORDER BY d.id"
)->fetch_all(MYSQLI_ASSOC);

$responses = $db->query(
    "SELECT f.submitted_at, d.slug, d.name AS dept, f.rating, f.recommend, f.improvement,
            IF(f.anonymous = 1, NULL, u.username) AS who
       FROM feedback f
       JOIN departments d ON d.id = f.department_id
       LEFT JOIN users u ON u.id = f.user_id
      ORDER BY f.id DESC LIMIT 50"
)->fetch_all(MYSQLI_ASSOC);

$attempts = $db->query('SELECT attempted_at, username, ip, success FROM login_attempts ORDER BY id DESC LIMIT 50')->fetch_all(MYSQLI_ASSOC);
$users    = $db->query('SELECT u.id, u.username, u.email, d.name AS dept, u.role, u.last_login, u.created_at FROM users u JOIN departments d ON d.id = u.department_id ORDER BY u.id')->fetch_all(MYSQLI_ASSOC);

page_header('Admin');
?>
<h1>&#128737;&#65039; Admin: feedback and activity</h1>
<div class="cards">
  <div class="card"><h2>Users</h2><p class="big"><?= $totalUsers ?></p></div>
  <div class="card"><h2>Logins (24h)</h2><p class="big"><?= $logins24h ?></p></div>
  <div class="card"><h2>Failed (24h)</h2><p class="big"><?= $fails24h ?></p></div>
</div>

<h2>&#128172; Feedback by department</h2>
<div class="table-wrap"><table>
  <tr><th>Department</th><th>Responses</th><th>Average rating (1-5)</th><th>Would recommend</th></tr>
  <?php foreach ($summary as $row): ?>
    <tr>
      <td><span class="badge dept-pill dept-<?= e($row['slug']) ?>"><?= e($row['name']) ?></span></td>
      <td><?= (int)$row['responses'] ?></td>
      <td><?= $row['avg_rating'] === null ? '-' : e((string)$row['avg_rating']) ?></td>
      <td><?= $row['rec_pct'] === null ? '-' : (int)$row['rec_pct'] . '%' ?></td>
    </tr>
  <?php endforeach; ?>
</table></div>

<h2>Latest feedback</h2>
<?php if (!$responses): ?>
  <p>No feedback yet.</p>
<?php else: ?>
  <div class="table-wrap"><table>
    <tr><th>Time</th><th>Department</th><th>Rating</th><th>Recommend</th><th>What to improve</th><th>From</th></tr>
    <?php foreach ($responses as $row): ?>
      <tr>
        <td><?= e($row['submitted_at']) ?></td>
        <td><span class="badge dept-pill dept-<?= e($row['slug']) ?>"><?= e($row['dept']) ?></span></td>
        <td><?= (int)$row['rating'] ?> / 5</td>
        <td><?= e(str_replace('_', ' ', $row['recommend'])) ?></td>
        <td><?= e($row['improvement']) ?></td>
        <td><?= $row['who'] === null ? 'Anonymous' : e($row['who']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
<?php endif; ?>

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
  <tr><th>ID</th><th>Username</th><th>Email</th><th>Department</th><th>Role</th><th>Last login</th><th>Created</th></tr>
  <?php foreach ($users as $row): ?>
    <tr>
      <td><?= (int)$row['id'] ?></td>
      <td><?= e($row['username']) ?></td>
      <td><?= e($row['email']) ?></td>
      <td><?= e($row['dept']) ?></td>
      <td><?= e($row['role']) ?></td>
      <td><?= e($row['last_login'] ?? '-') ?></td>
      <td><?= e($row['created_at']) ?></td>
    </tr>
  <?php endforeach; ?>
</table></div>
<?php page_footer(); ?>
