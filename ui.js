// Small bits of fun and friendliness: dark mode, show/hide password, greeting, countdown, confetti.
(function () {
  'use strict';
  var root = document.documentElement;
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ----- light / dark mode ----- */
  function save(key, value) { try { localStorage.setItem(key, value); } catch (e) { /* ignore */ } }
  function prefersDark() { return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches; }
  function currentMode() { return root.getAttribute('data-mode') || (prefersDark() ? 'dark' : 'light'); }

  var modeBtn = document.getElementById('mode-toggle');
  function refreshModeButton() {
    if (modeBtn) { modeBtn.textContent = currentMode() === 'dark' ? '\u2600\uFE0F' : '\uD83C\uDF19'; }
  }
  if (modeBtn) {
    modeBtn.addEventListener('click', function () {
      var next = currentMode() === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-mode', next);
      save('isp_mode', next);
      refreshModeButton();
    });
  }
  refreshModeButton();

  /* ----- character counter for text areas ----- */
  document.querySelectorAll('textarea[data-counter]').forEach(function (box) {
    var out = document.getElementById(box.getAttribute('data-counter'));
    if (!out) { return; }
    var update = function () { out.textContent = box.value.length; };
    box.addEventListener('input', update);
    update();
  });

  /* ----- show / hide password ----- */
  document.querySelectorAll('.eye').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.getAttribute('data-target'));
      if (!input) { return; }
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.textContent = show ? 'Hide' : 'Show';
      btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
  });

  /* ----- greeting by time of day ----- */
  var greet = document.getElementById('greeting');
  if (greet) {
    var h = new Date().getHours();
    var part = h < 5 ? 'Still up' : h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening';
    var icon = h < 5 ? '\uD83C\uDF19' : h < 12 ? '\u2600\uFE0F' : h < 18 ? '\uD83C\uDF24\uFE0F' : '\uD83C\uDF06';
    greet.textContent = part + ', ' + greet.getAttribute('data-name') + ' ' + icon;
  }

  /* ----- lockout countdown ----- */
  var cd = document.getElementById('countdown');
  if (cd) {
    var left = parseInt(cd.getAttribute('data-seconds'), 10) || 0;
    var tick = function () {
      if (left <= 0) { cd.textContent = 'now, go ahead!'; return; }
      var m = Math.floor(left / 60), s = left % 60;
      cd.textContent = m + ':' + (s < 10 ? '0' : '') + s;
      left -= 1;
      setTimeout(tick, 1000);
    };
    tick();
  }

  /* ----- toast ----- */
  function toast(message) {
    var t = document.createElement('div');
    t.className = 'toast';
    t.setAttribute('role', 'status');
    t.textContent = message;
    document.body.appendChild(t);
    setTimeout(function () { t.remove(); }, 3500);
  }

  /* ----- confetti ----- */
  function confetti() {
    if (reduceMotion) { return; }
    var canvas = document.createElement('canvas');
    canvas.setAttribute('aria-hidden', 'true');
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
    canvas.style.position = 'fixed';
    canvas.style.left = '0';
    canvas.style.top = '0';
    canvas.style.pointerEvents = 'none';
    canvas.style.zIndex = '60';
    document.body.appendChild(canvas);
    var ctx = canvas.getContext('2d');
    var colors = ['#f94144', '#f3722c', '#f9c74f', '#90be6d', '#43aa8b', '#577590', '#b794f6', '#f472b6'];
    var pieces = [];
    for (var i = 0; i < 130; i++) {
      pieces.push({
        x: canvas.width / 2, y: canvas.height * 0.35,
        vx: (Math.random() - 0.5) * 14, vy: Math.random() * -12 - 3,
        size: Math.random() * 7 + 4, color: colors[i % colors.length],
        rot: Math.random() * 6, vr: (Math.random() - 0.5) * 0.4
      });
    }
    var frames = 0;
    (function draw() {
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      pieces.forEach(function (p) {
        p.vy += 0.3; p.x += p.vx; p.y += p.vy; p.rot += p.vr;
        ctx.save();
        ctx.translate(p.x, p.y);
        ctx.rotate(p.rot);
        ctx.fillStyle = p.color;
        ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size * 0.6);
        ctx.restore();
      });
      frames++;
      if (frames < 150) { requestAnimationFrame(draw); } else { canvas.remove(); }
    })();
  }
  if (document.querySelector('[data-confetti]')) { confetti(); }

  /* ----- Konami code easter egg ----- */
  var code = [38, 38, 40, 40, 37, 39, 37, 39, 66, 65], pos = 0;
  document.addEventListener('keydown', function (e) {
    pos = (e.keyCode === code[pos]) ? pos + 1 : (e.keyCode === code[0] ? 1 : 0);
    if (pos === code.length) {
      pos = 0;
      confetti();
      toast('\uD83C\uDF89 You found the secret! Take a bow.');
    }
  });
})();
