<?php
require __DIR__ . '/lib.php';
$user = require_login();

// A person can only open the feedback form of their OWN department (taken from the account, never from the URL)
$departments = departments();
$dept = $departments[$user['dept']] ?? null;
if ($dept === null) {
    header('Location: dashboard.php');
    exit;
}

const RECOMMEND_CHOICES = [
    'definitely' => 'Definitely &#128588;',
    'probably'   => 'Probably &#128578;',
    'not_sure'   => 'Not sure &#129300;',
    'no'         => 'No &#128581;',
];
const RATING_FACES = [1 => ['&#128542;', 'Rough'], 2 => ['&#128577;', 'Meh'], 3 => ['&#128528;', 'Okay'],
                      4 => ['&#128578;', 'Good'], 5 => ['&#129321;', 'Amazing']];

$errors = [];
$rating = 0;
$recommend = '';
$improvement = '';
$anonymous = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $rating      = (int)($_POST['rating'] ?? 0);
    $recommend   = (string)($_POST['recommend'] ?? '');
    $improvement = trim((string)($_POST['improvement'] ?? ''));
    $anonymous   = isset($_POST['anonymous']);

    // Server-side validation
    if ($rating < 1 || $rating > 5) {
        $errors[] = 'Please rate your experience from 1 to 5.';
    }
    if (!array_key_exists($recommend, RECOMMEND_CHOICES)) {
        $errors[] = 'Please tell us whether you would recommend working here.';
    }
    $len = strlen($improvement);
    if ($len < 5 || $len > 500) {
        $errors[] = 'Tell us what to improve, in 5 to 500 characters.';
    }

    if (!$errors) {
        $deptId = $dept['id'];
        $userId = $anonymous ? null : $user['id'];   // anonymous feedback keeps no link to the person
        $anon   = $anonymous ? 1 : 0;
        $stmt = db()->prepare(
            'INSERT INTO feedback (department_id, user_id, rating, recommend, improvement, anonymous)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('iiissi', $deptId, $userId, $rating, $recommend, $improvement, $anon);
        $stmt->execute();
        $stmt->close();
        header('Location: dashboard.php?thanks=1');
        exit;
    }
}

page_header($dept['name'] . ' feedback');
?>
<section class="dept-banner dept-<?= e($dept['slug']) ?>">
  <div class="big-emoji" aria-hidden="true"><?= $dept['emoji'] ?></div>
  <div>
    <h1><?= e($dept['name']) ?> feedback</h1>
    <p>Three quick questions. It takes about a minute.</p>
  </div>
</section>

<section class="sheet dept-<?= e($dept['slug']) ?>">
  <?php foreach ($errors as $err): ?><div class="notice error" role="alert">&#128584; <?= e($err) ?></div><?php endforeach; ?>
  <form method="POST" action="feedback.php">
    <?= csrf_field() ?>

    <fieldset class="question">
      <legend>1. How would you rate your overall experience at the company so far?</legend>
      <div class="rating">
        <?php foreach (RATING_FACES as $n => [$face, $label]): ?>
          <label class="face"><input type="radio" name="rating" value="<?= $n ?>" required<?= $rating === $n ? ' checked' : '' ?>>
            <span><b><?= $face ?></b><small><?= e($label) ?></small></span></label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <fieldset class="question">
      <legend>2. Would you recommend working here to a friend?</legend>
      <div class="chips">
        <?php foreach (RECOMMEND_CHOICES as $value => $label): ?>
          <label class="chip plain"><input type="radio" name="recommend" value="<?= e($value) ?>" required<?= $recommend === $value ? ' checked' : '' ?>>
            <span><?= $label ?></span></label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <label class="question">3. What would you most like us to improve?
      <textarea id="improvement" name="improvement" rows="4" required minlength="5" maxlength="500"
                placeholder="<?= e($dept['hint']) ?>" data-counter="improvement-count"><?= e($improvement) ?></textarea>
      <small><span id="improvement-count">0</span> / 500</small>
    </label>

    <label class="check"><input type="checkbox" name="anonymous" value="1"<?= $anonymous ? ' checked' : '' ?>>
      <span>Send my feedback anonymously (your name will not be saved with it)</span></label>

    <button type="submit">Send feedback &#128640;</button>
  </form>
  <p><a href="dashboard.php">&larr; Back to my dashboard</a></p>
</section>
<?php page_footer(); ?>
