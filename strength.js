// Password strength meter (creative feature). Runs in the browser only;
// the server still enforces the real rules in signup.php.
(function () {
  var input = document.getElementById('password');
  var meter = document.getElementById('strength-meter');
  var text = document.getElementById('strength-text');
  if (!input || !meter || !text) { return; }
  var labels = ['Too weak', 'Weak', 'Fair', 'Good', 'Strong'];
  input.addEventListener('input', function () {
    var p = input.value, score = 0;
    if (p.length >= 10) { score++; }
    if (p.length >= 14) { score++; }
    if (/[a-z]/.test(p) && /[A-Z]/.test(p)) { score++; }
    if (/\d/.test(p) && /[^A-Za-z0-9]/.test(p)) { score++; }
    if (p.length === 0) { score = 0; }
    meter.value = score;
    text.textContent = p.length === 0
      ? 'Use 10+ characters with letters, numbers and symbols.'
      : 'Password strength: ' + labels[score];
  });
})();
