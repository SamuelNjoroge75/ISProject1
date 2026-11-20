// Password strength meter (browser only; signup.php still enforces the real rules).
(function () {
  var input = document.getElementById('password');
  var fill = document.getElementById('strength-fill');
  var text = document.getElementById('strength-text');
  if (!input || !fill || !text) { return; }
  var messages = [
    'Use 10+ characters with letters, numbers and symbols.',
    'Easy to guess \uD83E\uDD14 keep going',
    'Getting there \uD83D\uDE42',
    'Nice and strong \uD83D\uDCAA',
    'Fort Knox! \uD83C\uDFF0'
  ];
  input.addEventListener('input', function () {
    var p = input.value, score = 0;
    if (p.length >= 10) { score++; }
    if (p.length >= 14) { score++; }
    if (/[a-z]/.test(p) && /[A-Z]/.test(p)) { score++; }
    if (/\d/.test(p) && /[^A-Za-z0-9]/.test(p)) { score++; }
    if (p.length > 0 && score === 0) { score = 1; }
    if (p.length === 0) { score = 0; }
    fill.className = 'fill lvl-' + score;
    text.textContent = messages[score];
  });
})();
