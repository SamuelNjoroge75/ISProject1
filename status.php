<?php
define('NO_SESSION', true);   // the status page does not need a session
require __DIR__ . '/lib.php';

header('Cache-Control: no-store');

$dbOk = false;
try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $c = config();
    $m = new mysqli($c['db_host'], $c['db_user'], $c['db_pass'], $c['db_name']);
    $m->query('SELECT 1');
    $m->close();
    $dbOk = true;
} catch (Throwable $t) {
    $dbOk = false;
}

if (!$dbOk) {
    http_response_code(503);
}
$time  = gmdate('Y-m-d H:i:s') . ' UTC';
$https = is_https();

if (($_GET['format'] ?? '') === 'json') {
    header('Content-Type: application/json');
    echo json_encode(['application' => 'up', 'database' => $dbOk ? 'up' : 'down', 'https' => $https, 'time' => $time]);
    exit;
}

page_header('Status');
?>
<h1>System status</h1>
<div class="table-wrap"><table>
  <tr><th>Component</th><th>State</th></tr>
  <tr><td>Web application</td><td><span class="badge good">Operational</span></td></tr>
  <tr><td>Database</td><td><span class="badge <?= $dbOk ? 'good' : 'bad' ?>"><?= $dbOk ? 'Operational' : 'Unavailable' ?></span></td></tr>
  <tr><td>Secure connection (HTTPS)</td><td><span class="badge <?= $https ? 'good' : 'bad' ?>"><?= $https ? 'Yes' : 'No' ?></span></td></tr>
  <tr><td>Server time</td><td><?= e($time) ?></td></tr>
</table></div>
<p>Machine-readable version: <a href="status.php?format=json">status.php?format=json</a></p>
<?php page_footer(); ?>
