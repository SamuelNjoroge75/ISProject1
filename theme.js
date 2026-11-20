// Applies the saved light/dark mode before the page paints (no flash).
(function () {
  var root = document.documentElement;
  root.classList.add('js');
  try {
    var mode = localStorage.getItem('isp_mode');
    if (['light', 'dark'].indexOf(mode) !== -1) { root.setAttribute('data-mode', mode); }
  } catch (e) { /* storage blocked: use the device setting */ }
})();
